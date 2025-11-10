<?php
/**
 * Delete Location Endpoint (Soft Delete)
 * Yobe State University Emergency Response System
 */

// Headers
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Required includes
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/middleware.php';
require_once __DIR__ . '/../../includes/jwt_helper.php';

// Only allow DELETE requests
if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    sendError('Method not allowed', HTTP_METHOD_NOT_ALLOWED);
}

try {
    // Rate limiting
    rateLimitMiddleware();

    // Database connection
    $pdo = getDB();

    // Authenticate user - require admin privileges
    $user = JWTHelper::authenticateUser($pdo);

    // Check admin permissions
    if (!in_array($user['role'], ['super_admin', 'health_admin', 'fire_admin', 'security_admin'])) {
        sendError('Admin privileges required', HTTP_FORBIDDEN);
    }

    // Get location ID from URL parameter
    $locationId = $_GET['location_id'] ?? null;

    if (!$locationId || !is_numeric($locationId)) {
        sendError('Location ID is required and must be numeric', HTTP_BAD_REQUEST);
    }

    // Start transaction
    $pdo->beginTransaction();

    try {
        // Get current location data
        $currentStmt = $pdo->prepare("
            SELECT l.*,
                   (SELECT COUNT(*) FROM emergencies e WHERE e.location_id = l.id AND e.status IN ('pending', 'in_progress')) as active_emergencies,
                   (SELECT COUNT(*) FROM emergencies e WHERE e.location_id = l.id) as total_emergencies
            FROM locations l
            WHERE l.id = ?
        ");
        $currentStmt->execute([$locationId]);
        $location = $currentStmt->fetch();

        if (!$location) {
            $pdo->rollBack();
            sendError('Location not found', HTTP_NOT_FOUND);
        }

        // Check if location is already inactive
        if (!$location['is_active']) {
            $pdo->rollBack();
            sendError('Location is already inactive', HTTP_BAD_REQUEST);
        }

        // Check for active emergencies
        if ($location['active_emergencies'] > 0) {
            $pdo->rollBack();
            sendError('Cannot delete location with active emergencies', HTTP_BAD_REQUEST);
        }

        // Soft delete - set is_active to false
        $deleteStmt = $pdo->prepare("
            UPDATE locations
            SET is_active = false, updated_at = NOW()
            WHERE id = ?
        ");
        $result = $deleteStmt->execute([$locationId]);

        if (!$result) {
            throw new Exception('Failed to delete location');
        }

        // Log the deletion
        logActivity(sprintf(
            "Location soft deleted: ID %s (%s) by %s. Total emergencies: %d",
            $locationId,
            $location['name'],
            $user['full_name'],
            $location['total_emergencies']
        ), "INFO");

        // Commit transaction
        $pdo->commit();

        // Format response
        $response = [
            'location' => [
                'id' => $location['id'],
                'name' => $location['name'],
                'category' => $location['category'],
                'is_active' => false,
                'deleted_at' => date('Y-m-d H:i:s')
            ],
            'emergency_history' => [
                'total_emergencies' => (int)$location['total_emergencies'],
                'active_emergencies_at_deletion' => (int)$location['active_emergencies']
            ],
            'message' => 'Location successfully marked as inactive. No new emergencies can be reported at this location.'
        ];

        sendResponse(true, 'Location deleted successfully', $response, HTTP_OK);

    } catch (Exception $e) {
        // Rollback transaction
        $pdo->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    logActivity("Location deletion error: " . $e->getMessage(), "ERROR");
    sendError($e->getMessage(), HTTP_INTERNAL_SERVER_ERROR);
}
?>