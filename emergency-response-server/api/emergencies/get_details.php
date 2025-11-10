<?php
/**
 * Get Emergency Details Endpoint
 * Yobe State University Emergency Response System
 */

// Headers
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
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

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendError('Method not allowed', HTTP_METHOD_NOT_ALLOWED);
}

try {
    // Database connection
    $pdo = getDB();

    // Authenticate user
    $user = JWTHelper::authenticateUser($pdo);

    // Get emergency ID from query parameters
    $emergencyId = $_GET['emergency_id'] ?? null;

    if (!$emergencyId || !is_numeric($emergencyId)) {
        sendError('Emergency ID is required and must be numeric', HTTP_BAD_REQUEST);
    }

    // Start transaction for consistent read
    $pdo->beginTransaction();

    try {
        // Check if user has permission to view this emergency
        $permissionStmt = $pdo->prepare("
            SELECT e.id
            FROM emergencies e
            WHERE e.id = ?
            AND (
                e.user_id = ?
                OR ? IN ('super_admin', 'health_admin', 'fire_admin', 'security_admin')
            )
        ");
        $permissionStmt->execute([$emergencyId, $user['id'], $user['role']]);
        $hasPermission = $permissionStmt->fetch();

        if (!$hasPermission) {
            $pdo->rollBack();
            sendError('Emergency not found or access denied', HTTP_NOT_FOUND);
        }

        // For department admins, check if emergency belongs to their department
        if (in_array($user['role'], ['health_admin', 'fire_admin', 'security_admin'])) {
            $departmentCheckStmt = $pdo->prepare("
                SELECT e.id
                FROM emergencies e
                JOIN emergency_types et ON e.emergency_type_id = et.id
                WHERE e.id = ? AND et.department = ?
            ");
            $userDepartment = str_replace('_admin', '', $user['role']);
            $departmentCheckStmt->execute([$emergencyId, $userDepartment]);
            $departmentPermission = $departmentCheckStmt->fetch();

            if (!$departmentPermission) {
                $pdo->rollBack();
                sendError('Emergency not found or access denied', HTTP_NOT_FOUND);
            }
        }

        // Get complete emergency details
        $detailsStmt = $pdo->prepare("
            SELECT
                e.id,
                e.description,
                e.status,
                e.severity,
                e.latitude,
                e.longitude,
                e.reported_at,
                e.assigned_to,
                e.resolved_at,
                e.response_time_minutes,
                et.name as emergency_type,
                et.department as emergency_department,
                et.icon as emergency_icon,
                et.color as emergency_color,
                et.description as emergency_type_description,
                l.id as location_id,
                l.name as location_name,
                l.category as location_category,
                l.description as location_description,
                l.latitude as location_latitude,
                l.longitude as location_longitude,
                u.id as reporter_id,
                u.full_name as reporter_name,
                u.phone as reporter_phone,
                u.email as reporter_email,
                u.department as reporter_department,
                assigned_user.full_name as assigned_responder_name,
                assigned_user.phone as assigned_responder_phone,
                assigned_user.email as assigned_responder_email
            FROM emergencies e
            JOIN emergency_types et ON e.emergency_type_id = et.id
            JOIN locations l ON e.location_id = l.id
            JOIN users u ON e.user_id = u.id
            LEFT JOIN users assigned_user ON e.assigned_to = assigned_user.id
            WHERE e.id = ?
        ");
        $detailsStmt->execute([$emergencyId]);
        $emergency = $detailsStmt->fetch();

        if (!$emergency) {
            $pdo->rollBack();
            sendError('Emergency not found', HTTP_NOT_FOUND);
        }

        // Get emergency updates/timeline
        $updatesStmt = $pdo->prepare("
            SELECT
                eu.id,
                eu.update_text,
                eu.status,
                eu.created_at,
                u.full_name as responder_name,
                u.role as responder_role
            FROM emergency_updates eu
            JOIN users u ON eu.responder_id = u.id
            WHERE eu.emergency_id = ?
            ORDER BY eu.created_at ASC
        ");
        $updatesStmt->execute([$emergencyId]);
        $updates = $updatesStmt->fetchAll();

        // Calculate response statistics
        $statsStmt = $pdo->prepare("
            SELECT
                COUNT(*) as total_updates,
                MIN(created_at) as first_update,
                MAX(created_at) as last_update
            FROM emergency_updates
            WHERE emergency_id = ?
        ");
        $statsStmt->execute([$emergencyId]);
        $stats = $statsStmt->fetch();

        // Get related emergencies (same location or type, last 30 days)
        $relatedStmt = $pdo->prepare("
            SELECT
                e.id,
                e.status,
                e.reported_at,
                et.name as emergency_type,
                l.name as location_name,
                u.full_name as reporter_name
            FROM emergencies e
            JOIN emergency_types et ON e.emergency_type_id = et.id
            JOIN locations l ON e.location_id = l.id
            JOIN users u ON e.user_id = u.id
            WHERE e.id != ?
            AND (
                e.emergency_type_id = ?
                OR e.location_id = ?
            )
            AND e.reported_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ORDER BY e.reported_at DESC
            LIMIT 5
        ");
        $relatedStmt->execute([$emergencyId, $emergency['emergency_type_id'], $emergency['location_id']]);
        $relatedEmergencies = $relatedStmt->fetchAll();

        // Format response data
        $response = [
            'emergency' => [
                'id' => $emergency['id'],
                'type' => [
                    'id' => $emergency['emergency_type_id'],
                    'name' => $emergency['emergency_type'],
                    'department' => $emergency['emergency_department'],
                    'icon' => $emergency['emergency_icon'],
                    'color' => $emergency['emergency_color'],
                    'description' => $emergency['emergency_type_description']
                ],
                'location' => [
                    'id' => $emergency['location_id'],
                    'name' => $emergency['location_name'],
                    'category' => $emergency['location_category'],
                    'description' => $emergency['location_description'],
                    'coordinates' => [
                        'latitude' => $emergency['location_latitude'],
                        'longitude' => $emergency['location_longitude']
                    ]
                ],
                'description' => $emergency['description'],
                'status' => $emergency['status'],
                'severity' => $emergency['severity'],
                'coordinates' => [
                    'latitude' => $emergency['latitude'],
                    'longitude' => $emergency['longitude']
                ],
                'reported_at' => $emergency['reported_at'],
                'resolved_at' => $emergency['resolved_at'],
                'response_time_minutes' => $emergency['response_time_minutes'],
                'reporter' => [
                    'id' => $emergency['reporter_id'],
                    'name' => $emergency['reporter_name'],
                    'phone' => $emergency['reporter_phone'],
                    'email' => $emergency['reporter_email'],
                    'department' => $emergency['reporter_department']
                ],
                'assigned_responder' => $emergency['assigned_to'] ? [
                    'id' => $emergency['assigned_to'],
                    'name' => $emergency['assigned_responder_name'],
                    'phone' => $emergency['assigned_responder_phone'],
                    'email' => $emergency['assigned_responder_email']
                ] : null
            ],
            'timeline' => array_map(function($update) {
                return [
                    'id' => $update['id'],
                    'text' => $update['update_text'],
                    'status' => $update['status'],
                    'created_at' => $update['created_at'],
                    'responder' => [
                        'name' => $update['responder_name'],
                        'role' => $update['responder_role']
                    ]
                ];
            }, $updates),
            'statistics' => [
                'total_updates' => (int)$stats['total_updates'],
                'first_update' => $stats['first_update'],
                'last_update' => $stats['last_update']
            ],
            'related_emergencies' => array_map(function($related) {
                return [
                    'id' => $related['id'],
                    'status' => $related['status'],
                    'reported_at' => $related['reported_at'],
                    'type' => $related['emergency_type'],
                    'location' => $related['location_name'],
                    'reporter' => $related['reporter_name']
                ];
            }, $relatedEmergencies),
            'permissions' => [
                'can_update_status' => in_array($user['role'], ['health_admin', 'fire_admin', 'security_admin', 'super_admin']),
                'can_view_details' => true,
                'can_add_updates' => $emergency['user_id'] == $user['id'] || in_array($user['role'], ['health_admin', 'fire_admin', 'security_admin', 'super_admin'])
            ]
        ];

        // Commit transaction
        $pdo->commit();

        // Log access
        logActivity(sprintf(
            "Emergency details viewed: ID %s by %s (%s)",
            $emergencyId,
            $user['full_name'],
            $user['role']
        ), "INFO");

        sendResponse(true, 'Emergency details retrieved successfully', $response, HTTP_OK);

    } catch (Exception $e) {
        // Rollback transaction
        $pdo->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    logActivity("Get emergency details error: " . $e->getMessage(), "ERROR");
    sendError($e->getMessage(), HTTP_INTERNAL_SERVER_ERROR);
}
?>