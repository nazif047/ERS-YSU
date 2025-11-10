<?php
/**
 * Update Emergency Status Endpoint
 * Yobe State University Emergency Response System
 */

// Headers
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: PUT, OPTIONS');
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

// Only allow PUT requests
if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    sendError('Method not allowed', HTTP_METHOD_NOT_ALLOWED);
}

try {
    // Rate limiting
    rateLimitMiddleware();

    // Database connection
    $pdo = getDB();

    // Authenticate user
    $user = JWTHelper::authenticateUser($pdo);

    // Get request data
    $data = getRequestData();

    // Validation rules
    $rules = [
        'emergency_id' => ['required' => true],
        'status' => ['required' => true, 'enum' => [STATUS_PENDING, STATUS_IN_PROGRESS, STATUS_RESOLVED, STATUS_CLOSED]],
        'update_text' => ['required' => true, 'min' => 5, 'max' => 500]
    ];

    // Validate input
    $data = validationMiddleware($data, $rules);

    // Start transaction
    $pdo->beginTransaction();

    try {
        // Get emergency details
        $emergencyStmt = $pdo->prepare("
            SELECT
                e.*,
                et.name as emergency_type,
                et.department as emergency_department,
                l.name as location_name,
                u.full_name as reporter_name
            FROM emergencies e
            JOIN emergency_types et ON e.emergency_type_id = et.id
            JOIN locations l ON e.location_id = l.id
            JOIN users u ON e.user_id = u.id
            WHERE e.id = ?
        ");
        $emergencyStmt->execute([$data['emergency_id']]);
        $emergency = $emergencyStmt->fetch();

        if (!$emergency) {
            throw new Exception('Emergency not found');
        }

        // Check permissions based on user role
        $canUpdate = false;

        if ($user['role'] === ROLE_SUPER_ADMIN) {
            $canUpdate = true;
        } elseif (in_array($user['role'], [ROLE_SECURITY_ADMIN, ROLE_HEALTH_ADMIN, ROLE_FIRE_ADMIN])) {
            // Admin can update emergencies in their department
            $userDepartment = str_replace('_admin', '', $user['role']);
            if ($emergency['emergency_department'] === $userDepartment) {
                $canUpdate = true;
            }
        } elseif ($emergency['user_id'] === $user['id']) {
            // Users can update their own emergencies (but only certain statuses)
            if (in_array($data['status'], [STATUS_RESOLVED, STATUS_CLOSED])) {
                $canUpdate = true;
            }
        }

        if (!$canUpdate) {
            throw new Exception(ERR_PERMISSION_DENIED);
        }

        // Validate status transition
        $validTransitions = [
            STATUS_PENDING => [STATUS_IN_PROGRESS, STATUS_RESOLVED, STATUS_CLOSED],
            STATUS_IN_PROGRESS => [STATUS_RESOLVED, STATUS_CLOSED],
            STATUS_RESOLVED => [STATUS_CLOSED],
            STATUS_CLOSED => []
        ];

        if (!in_array($data['status'], $validTransitions[$emergency['status']])) {
            throw new Exception('Invalid status transition from ' . $emergency['status'] . ' to ' . $data['status']);
        }

        // Update emergency status
        $updateStmt = $pdo->prepare("
            UPDATE emergencies
            SET status = ?, assigned_to = ?, resolved_at = ?
            WHERE id = ?
        ");

        $resolvedAt = ($data['status'] === STATUS_RESOLVED || $data['status'] === STATUS_CLOSED) ? date('Y-m-d H:i:s') : null;
        $assignedTo = ($data['status'] === STATUS_IN_PROGRESS && !$emergency['assigned_to']) ? $user['id'] : $emergency['assigned_to'];

        $updateStmt->execute([$data['status'], $assignedTo, $resolvedAt, $data['emergency_id']]);

        // Create status update record
        $updateRecordStmt = $pdo->prepare("
            INSERT INTO emergency_updates (emergency_id, responder_id, update_text, status, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $updateRecordStmt->execute([
            $data['emergency_id'],
            $user['id'],
            $data['update_text'],
            $data['status']
        ]);

        // Send notifications if status changed to resolved or closed
        if (in_array($data['status'], [STATUS_RESOLVED, STATUS_CLOSED]) && NOTIFICATION_ENABLED) {
            // Notify the original reporter
            $notificationStmt = $pdo->prepare("
                INSERT INTO notifications (user_id, emergency_id, title, message, type, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $notificationStmt->execute([
                $emergency['user_id'],
                $data['emergency_id'],
                'Emergency ' . $data['status'],
                sprintf(
                    'Your emergency report at %s has been marked as %s. %s',
                    $emergency['location_name'],
                    $data['status'],
                    $data['update_text']
                ),
                'emergency_resolved'
            ]);
        }

        // Get updated emergency details
        $updatedStmt = $pdo->prepare("
            SELECT
                e.id,
                e.status,
                e.resolved_at,
                et.name as emergency_type,
                et.department as emergency_department,
                l.name as location_name,
                u.full_name as reporter_name
            FROM emergencies e
            JOIN emergency_types et ON e.emergency_type_id = et.id
            JOIN locations l ON e.location_id = l.id
            JOIN users u ON e.user_id = u.id
            WHERE e.id = ?
        ");
        $updatedStmt->execute([$data['emergency_id']]);
        $updatedEmergency = $updatedStmt->fetch();

        // Format response
        $response = [
            'emergency' => $updatedEmergency,
            'status_updated' => [
                'from' => $emergency['status'],
                'to' => $data['status'],
                'updated_by' => $user['full_name'],
                'update_text' => $data['update_text'],
                'updated_at' => date('Y-m-d H:i:s')
            ]
        ];

        // Commit transaction
        $pdo->commit();

        // Log activity
        logActivity(sprintf(
            "Emergency %d status updated from %s to %s by %s",
            $data['emergency_id'],
            $emergency['status'],
            $data['status'],
            $user['full_name']
        ), "INFO");

        sendResponse(true, MSG_EMERGENCY_UPDATED, $response, HTTP_OK);

    } catch (Exception $e) {
        // Rollback transaction
        $pdo->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    logActivity("Emergency status update error: " . $e->getMessage(), "ERROR");
    sendError($e->getMessage(), HTTP_INTERNAL_SERVER_ERROR);
}
?>