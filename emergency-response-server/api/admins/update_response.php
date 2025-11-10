<?php
/**
 * Update Emergency Response Endpoint
 * Yobe State University Emergency Response System
 */

// Headers
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
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

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Method not allowed', HTTP_METHOD_NOT_ALLOWED);
}

try {
    // Rate limiting
    rateLimitMiddleware();

    // Database connection
    $pdo = getDB();

    // Authenticate user - require department admin privileges
    $user = JWTHelper::authenticateUser($pdo);

    // Check admin permissions
    if (!in_array($user['role'], ['health_admin', 'fire_admin', 'security_admin', 'super_admin'])) {
        sendError('Department admin privileges required', HTTP_FORBIDDEN);
    }

    // Get emergency ID from URL parameter
    $emergencyId = $_POST['emergency_id'] ?? null;

    if (!$emergencyId || !is_numeric($emergencyId)) {
        sendError('Emergency ID is required and must be numeric', HTTP_BAD_REQUEST);
    }

    // Get request data
    $newStatus = $_POST['new_status'] ?? null;
    $updateText = $_POST['update_text'] ?? null;
    $estimatedTime = $_POST['estimated_time'] ?? null;
    $notes = $_POST['notes'] ?? null;

    // Validation
    $validStatuses = ['pending', 'in_progress', 'resolved', 'closed'];
    if (!$newStatus || !in_array($newStatus, $validStatuses)) {
        sendError('Valid new_status is required', HTTP_BAD_REQUEST);
    }

    if (!$updateText || strlen(trim($updateText)) < 5) {
        sendError('Update text is required and must be at least 5 characters', HTTP_BAD_REQUEST);
    }

    if ($estimatedTime && !is_numeric($estimatedTime) || $estimatedTime < 0) {
        sendError('Estimated time must be a positive number', HTTP_BAD_REQUEST);
    }

    // Get department from user role
    $userDepartment = $user['role'] === 'super_admin' ? null : str_replace('_admin', '', $user['role']);

    // Start transaction
    $pdo->beginTransaction();

    try {
        // Get current emergency details and check permissions
        $currentStmt = $pdo->prepare("
            SELECT
                e.*,
                et.department as emergency_department,
                et.name as emergency_type_name,
                l.name as location_name,
                u.full_name as reporter_name,
                u.phone as reporter_phone,
                u.email as reporter_email
            FROM emergencies e
            JOIN emergency_types et ON e.emergency_type_id = et.id
            JOIN locations l ON e.location_id = l.id
            JOIN users u ON e.user_id = u.id
            WHERE e.id = ?
        ");
        $currentStmt->execute([$emergencyId]);
        $currentEmergency = $currentStmt->fetch();

        if (!$currentEmergency) {
            $pdo->rollBack();
            sendError('Emergency not found', HTTP_NOT_FOUND);
        }

        // Check department permissions (unless super admin)
        if ($userDepartment && $currentEmergency['emergency_department'] !== $userDepartment) {
            $pdo->rollBack();
            sendError('Emergency not found or access denied', HTTP_NOT_FOUND);
        }

        // Validate status transitions
        $currentStatus = $currentEmergency['status'];
        if (!isValidStatusTransition($currentStatus, $newStatus)) {
            $pdo->rollBack();
            sendError('Invalid status transition from ' . $currentStatus . ' to ' . $newStatus, HTTP_BAD_REQUEST);
        }

        // Calculate response time if resolving
        $responseTimeMinutes = null;
        if ($newStatus === 'resolved' || $newStatus === 'closed') {
            $reportTime = new DateTime($currentEmergency['reported_at']);
            $resolveTime = new DateTime();
            $responseTimeMinutes = $resolveTime->diff($reportTime)->i + ($resolveTime->diff($reportTime)->h * 60);
        }

        // Update emergency status
        $updateStmt = $pdo->prepare("
            UPDATE emergencies
            SET
                status = ?,
                assigned_to = COALESCE(assigned_to, ?),
                updated_at = NOW(),
                resolved_at = ?,
                response_time_minutes = ?,
                estimated_resolution_time = ?
            WHERE id = ?
        ");

        $resolvedAt = ($newStatus === 'resolved' || $newStatus === 'closed') ? date('Y-m-d H:i:s') : null;

        $updateStmt->execute([
            $newStatus,
            $user['id'],
            $resolvedAt,
            $responseTimeMinutes,
            $estimatedTime,
            $emergencyId
        ]);

        // Add emergency update entry
        $updateTextFull = $updateText;
        if ($estimatedTime) {
            $updateTextFull .= "\n\nEstimated resolution time: " . $estimatedTime . " minutes";
        }
        if ($notes) {
            $updateTextFull .= "\n\nAdditional notes: " . $notes;
        }

        $updateRecordStmt = $pdo->prepare("
            INSERT INTO emergency_updates (emergency_id, responder_id, update_text, status, estimated_time, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");

        $updateRecordStmt->execute([
            $emergencyId,
            $user['id'],
            $updateTextFull,
            $newStatus,
            $estimatedTime
        ]);

        // Create notification for the emergency reporter
        if (NOTIFICATION_ENABLED) {
            $notificationTitle = getNotificationTitle($newStatus);
            $notificationMessage = sprintf(
                "Your %s emergency at %s has been updated to: %s\n\nUpdate: %s\n\nUpdated by: %s (%s)",
                $currentEmergency['emergency_type_name'],
                $currentEmergency['location_name'],
                getStatusDisplayName($newStatus),
                $updateText,
                $user['full_name'],
                $user['role']
            );

            $notificationStmt = $pdo->prepare("
                INSERT INTO notifications (user_id, emergency_id, title, message, type, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");

            $notificationStmt->execute([
                $currentEmergency['user_id'],
                $emergencyId,
                $notificationTitle,
                $notificationMessage,
                'emergency_status_update'
            ]);
        }

        // If emergency is resolved/closed, send completion notification
        if ($newStatus === 'resolved' || $newStatus === 'closed') {
            if (NOTIFICATION_ENABLED) {
                $completionMessage = sprintf(
                    "Emergency #%d has been marked as %s\n\nType: %s\nLocation: %s\nReported by: %s\nResponse time: %d minutes\n\nResolved by: %s (%s)",
                    $emergencyId,
                    getStatusDisplayName($newStatus),
                    $currentEmergency['emergency_type_name'],
                    $currentEmergency['location_name'],
                    $currentEmergency['reporter_name'],
                    $responseTimeMinutes,
                    $user['full_name'],
                    $user['role']
                );

                $completionStmt = $pdo->prepare("
                    INSERT INTO notifications (user_id, emergency_id, title, message, type, created_at)
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");

                $completionStmt->execute([
                    $currentEmergency['user_id'],
                    $emergencyId,
                    'Emergency ' . ucfirst($newStatus),
                    $completionMessage,
                    'emergency_completed'
                ]);
            }
        }

        // Get updated emergency details
        $updatedStmt = $pdo->prepare("
            SELECT
                e.*,
                et.name as emergency_type_name,
                et.department as emergency_department,
                l.name as location_name,
                u.full_name as reporter_name,
                responder.full_name as assigned_responder_name
            FROM emergencies e
            JOIN emergency_types et ON e.emergency_type_id = et.id
            JOIN locations l ON e.location_id = l.id
            JOIN users u ON e.user_id = u.id
            LEFT JOIN users responder ON e.assigned_to = responder.id
            WHERE e.id = ?
        ");
        $updatedStmt->execute([$emergencyId]);
        $updatedEmergency = $updatedStmt->fetch();

        // Log the update
        logActivity(sprintf(
            "Emergency response updated: ID %s from %s to %s by %s (%s)",
            $emergencyId,
            $currentStatus,
            $newStatus,
            $user['full_name'],
            $user['role']
        ), "INFO");

        // Commit transaction
        $pdo->commit();

        // Format response
        $response = [
            'emergency' => [
                'id' => $updatedEmergency['id'],
                'status' => $updatedEmergency['status'],
                'resolved_at' => $updatedEmergency['resolved_at'],
                'response_time_minutes' => $updatedEmergency['response_time_minutes'],
                'estimated_resolution_time' => $updatedEmergency['estimated_resolution_time'],
                'assigned_responder' => $updatedEmergency['assigned_responder_name']
            ],
            'update_details' => [
                'previous_status' => $currentStatus,
                'new_status' => $newStatus,
                'update_text' => $updateTextFull,
                'estimated_time' => $estimatedTime,
                'updated_by' => [
                    'id' => $user['id'],
                    'name' => $user['full_name'],
                    'role' => $user['role']
                ],
                'updated_at' => date('Y-m-d H:i:s')
            ],
            'notifications_sent' => [
                'reporter_notified' => true,
                'completion_notification' => in_array($newStatus, ['resolved', 'closed'])
            ]
        ];

        sendResponse(true, 'Emergency response updated successfully', $response, HTTP_OK);

    } catch (Exception $e) {
        // Rollback transaction
        $pdo->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    logActivity("Emergency response update error: " . $e->getMessage(), "ERROR");
    sendError($e->getMessage(), HTTP_INTERNAL_SERVER_ERROR);
}

/**
 * Check if status transition is valid
 */
function isValidStatusTransition($from, $to) {
    $validTransitions = [
        'pending' => ['in_progress', 'resolved', 'closed'],
        'in_progress' => ['resolved', 'closed'],
        'resolved' => ['closed'],
        'closed' => [] // Cannot reopen closed emergencies
    ];

    return in_array($to, $validTransitions[$from] ?? []);
}

/**
 * Get notification title based on status
 */
function getNotificationTitle($status) {
    $titles = [
        'pending' => 'Emergency Reported',
        'in_progress' => 'Emergency Response Started',
        'resolved' => 'Emergency Resolved',
        'closed' => 'Emergency Closed'
    ];

    return $titles[$status] ?? 'Emergency Status Update';
}

/**
 * Get display name for status
 */
function getStatusDisplayName($status) {
    $displayNames = [
        'pending' => 'Pending',
        'in_progress' => 'In Progress',
        'resolved' => 'Resolved',
        'closed' => 'Closed'
    ];

    return $displayNames[$status] ?? ucfirst($status);
}
?>