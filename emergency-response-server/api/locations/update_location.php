<?php
/**
 * Update Location Endpoint
 * Yobe State University Emergency Response System
 */

// Headers
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: PUT, PATCH, OPTIONS');
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

// Only allow PUT and PATCH requests
if (!in_array($_SERVER['REQUEST_METHOD'], ['PUT', 'PATCH'])) {
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

    // Get request data
    $data = getRequestData();

    // Validation rules
    $rules = [
        'name' => ['required' => false, 'min' => 2, 'max' => 100],
        'description' => ['required' => false, 'max' => 500],
        'category' => ['required' => false, 'enum' => ['academic', 'hostel', 'admin', 'recreational', 'medical', 'other']],
        'latitude' => ['required' => false, 'pattern' => '/^-?\d{1,3}\.\d{1,8}$/'],
        'longitude' => ['required' => false, 'pattern' => '/^-?\d{1,4}\.\d{1,8}$/'],
        'is_active' => ['required' => false, 'boolean' => true]
    ];

    // Validate input
    $data = validationMiddleware($data, $rules);

    // Check if at least one field is being updated
    if (empty($data)) {
        sendError('At least one field must be provided for update', HTTP_BAD_REQUEST);
    }

    // Start transaction
    $pdo->beginTransaction();

    try {
        // Get current location data
        $currentStmt = $pdo->prepare("
            SELECT * FROM locations WHERE id = ?
        ");
        $currentStmt->execute([$locationId]);
        $currentLocation = $currentStmt->fetch();

        if (!$currentLocation) {
            $pdo->rollBack();
            sendError('Location not found', HTTP_NOT_FOUND);
        }

        // Build dynamic update query
        $updateFields = [];
        $updateParams = [];

        foreach ($data as $field => $value) {
            $updateFields[] = "$field = ?";
            $updateParams[] = $value;
        }

        $updateParams[] = $locationId;

        $updateQuery = "
            UPDATE locations
            SET " . implode(', ', $updateFields) . ", updated_at = NOW()
            WHERE id = ?
        ";

        $updateStmt = $pdo->prepare($updateQuery);
        $result = $updateStmt->execute($updateParams);

        if (!$result) {
            throw new Exception('Failed to update location');
        }

        // Get updated location data
        $updatedStmt = $pdo->prepare("
            SELECT
                l.*,
                (SELECT COUNT(*) FROM emergencies e WHERE e.location_id = l.id) as emergency_count
            FROM locations l
            WHERE l.id = ?
        ");
        $updatedStmt->execute([$locationId]);
        $updatedLocation = $updatedStmt->fetch();

        // Log the change
        $changes = [];
        foreach ($data as $field => $newValue) {
            $oldValue = $currentLocation[$field];
            if ($oldValue != $newValue) {
                $changes[] = "$field: '$oldValue' -> '$newValue'";
            }
        }

        logActivity(sprintf(
            "Location updated: ID %s (%s) by %s. Changes: %s",
            $locationId,
            $updatedLocation['name'],
            $user['full_name'],
            implode(', ', $changes)
        ), "INFO");

        // Commit transaction
        $pdo->commit();

        // Format response
        $response = [
            'location' => [
                'id' => $updatedLocation['id'],
                'name' => $updatedLocation['name'],
                'description' => $updatedLocation['description'],
                'category' => $updatedLocation['category'],
                'latitude' => $updatedLocation['latitude'],
                'longitude' => $updatedLocation['longitude'],
                'is_active' => (bool)$updatedLocation['is_active'],
                'emergency_count' => (int)$updatedLocation['emergency_count'],
                'created_at' => $updatedLocation['created_at'],
                'updated_at' => $updatedLocation['updated_at']
            ],
            'changes_made' => $changes
        ];

        sendResponse(true, 'Location updated successfully', $response, HTTP_OK);

    } catch (Exception $e) {
        // Rollback transaction
        $pdo->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    logActivity("Location update error: " . $e->getMessage(), "ERROR");
    sendError($e->getMessage(), HTTP_INTERNAL_SERVER_ERROR);
}
?>