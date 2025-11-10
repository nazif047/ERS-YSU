<?php
/**
 * Create Emergency Report Endpoint
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

    // Authenticate user
    $user = JWTHelper::authenticateUser($pdo);

    // Get request data
    $data = getRequestData();

    // Validation rules
    $rules = [
        'emergency_type_id' => ['required' => true],
        'location_id' => ['required' => true],
        'description' => ['required' => true, 'min' => 10, 'max' => 1000],
        'severity' => ['required' => true, 'enum' => [SEVERITY_LOW, SEVERITY_MEDIUM, SEVERITY_HIGH, SEVERITY_CRITICAL]],
        'latitude' => ['required' => false, 'pattern' => '/^-?\d{1,3}\.\d{1,8}$/'],
        'longitude' => ['required' => false, 'pattern' => '/^-?\d{1,4}\.\d{1,8}$/']
    ];

    // Validate input
    $data = validationMiddleware($data, $rules);

    // Start transaction
    $pdo->beginTransaction();

    try {
        // Verify emergency type exists and is active
        $typeStmt = $pdo->prepare("
            SELECT et.*, d.name as department_name
            FROM emergency_types et
            LEFT JOIN departments d ON et.department = d.code
            WHERE et.id = ? AND et.is_active = 1
        ");
        $typeStmt->execute([$data['emergency_type_id']]);
        $emergencyType = $typeStmt->fetch();

        if (!$emergencyType) {
            throw new Exception('Invalid emergency type');
        }

        // Verify location exists and is active
        $locationStmt = $pdo->prepare("
            SELECT * FROM locations
            WHERE id = ? AND is_active = 1
        ");
        $locationStmt->execute([$data['location_id']]);
        $location = $locationStmt->fetch();

        if (!$location) {
            throw new Exception('Invalid location');
        }

        // Check for duplicate recent emergency (same user, type, location within 5 minutes)
        $duplicateStmt = $pdo->prepare("
            SELECT id FROM emergencies
            WHERE user_id = ?
            AND emergency_type_id = ?
            AND location_id = ?
            AND reported_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
            AND status IN ('pending', 'in_progress')
        ");
        $duplicateStmt->execute([
            $user['id'],
            $data['emergency_type_id'],
            $data['location_id']
        ]);
        $duplicate = $duplicateStmt->fetch();

        if ($duplicate) {
            throw new Exception('Similar emergency already reported recently. Please wait for response.');
        }

        // Create emergency record
        $emergencyData = [
            'user_id' => $user['id'],
            'emergency_type_id' => $data['emergency_type_id'],
            'location_id' => $data['location_id'],
            'description' => $data['description'],
            'severity' => $data['severity'],
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'status' => STATUS_PENDING,
            'reported_at' => date('Y-m-d H:i:s')
        ];

        $insertStmt = $pdo->prepare("
            INSERT INTO emergencies (user_id, emergency_type_id, location_id, description, severity, latitude, longitude, status, reported_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $insertStmt->execute([
            $emergencyData['user_id'],
            $emergencyData['emergency_type_id'],
            $emergencyData['location_id'],
            $emergencyData['description'],
            $emergencyData['severity'],
            $emergencyData['latitude'],
            $emergencyData['longitude'],
            $emergencyData['status'],
            $emergencyData['reported_at']
        ]);

        $emergencyId = $pdo->lastInsertId();

        // Auto-assign to appropriate department if enabled
        $assignedTo = null;
        if (EMERGENCY_AUTO_ASSIGN) {
            $assignStmt = $pdo->prepare("
                SELECT id FROM users
                WHERE role = ?
                AND is_active = 1
                ORDER BY last_login ASC
                LIMIT 1
            ");

            $adminRole = $emergencyType['department'] . '_admin';
            $assignStmt->execute([$adminRole]);
            $assignedAdmin = $assignStmt->fetch();

            if ($assignedAdmin) {
                // Update emergency with assigned responder
                $updateStmt = $pdo->prepare("
                    UPDATE emergencies SET assigned_to = ? WHERE id = ?
                ");
                $updateStmt->execute([$assignedAdmin['id'], $emergencyId]);
                $assignedTo = $assignedAdmin['id'];

                // Create notification for assigned responder
                if (NOTIFICATION_ENABLED) {
                    $notificationStmt = $pdo->prepare("
                        INSERT INTO notifications (user_id, emergency_id, title, message, type, created_at)
                        VALUES (?, ?, ?, ?, ?, NOW())
                    ");
                    $notificationStmt->execute([
                        $assignedAdmin['id'],
                        $emergencyId,
                        'New Emergency Assigned',
                        sprintf(
                            '%s emergency reported at %s by %s',
                            $emergencyType['name'],
                            $location['name'],
                            $user['full_name']
                        ),
                        'emergency_assigned'
                    ]);
                }
            }
        }

        // Create initial update record
        $updateStmt = $pdo->prepare("
            INSERT INTO emergency_updates (emergency_id, responder_id, update_text, status, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $updateStmt->execute([
            $emergencyId,
            $user['id'],
            'Emergency reported successfully',
            STATUS_PENDING
        ]);

        // Get complete emergency details
        $detailsStmt = $pdo->prepare("
            SELECT
                e.id,
                e.description,
                e.status,
                e.severity,
                e.reported_at,
                e.assigned_to,
                et.name as emergency_type,
                et.department as emergency_department,
                et.icon as emergency_icon,
                et.color as emergency_color,
                l.name as location_name,
                l.category as location_category,
                l.description as location_description,
                u.full_name as reporter_name,
                u.phone as reporter_phone
            FROM emergencies e
            JOIN emergency_types et ON e.emergency_type_id = et.id
            JOIN locations l ON e.location_id = l.id
            JOIN users u ON e.user_id = u.id
            WHERE e.id = ?
        ");
        $detailsStmt->execute([$emergencyId]);
        $emergencyDetails = $detailsStmt->fetch();

        // Format response
        $response = [
            'emergency_id' => $emergencyId,
            'status' => $emergencyData['status'],
            'assigned_department' => $emergencyType['department'],
            'assigned_to' => $assignedTo,
            'emergency_details' => $emergencyDetails,
            'reported_at' => $emergencyData['reported_at'],
            'next_steps' => [
                'Department notified: ' . ucfirst($emergencyType['department']) . ' team',
                'Response time: Within ' . RESPONSE_TIMEOUT_MINUTES . ' minutes',
                'You will receive updates on your emergency status'
            ]
        ];

        // Commit transaction
        $pdo->commit();

        // Log activity
        logActivity(sprintf(
            "Emergency reported: %s at %s by %s",
            $emergencyType['name'],
            $location['name'],
            $user['full_name']
        ), "INFO");

        sendResponse(true, MSG_EMERGENCY_REPORTED, $response, HTTP_CREATED);

    } catch (Exception $e) {
        // Rollback transaction
        $pdo->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    logActivity("Emergency creation error: " . $e->getMessage(), "ERROR");
    sendError($e->getMessage(), HTTP_INTERNAL_SERVER_ERROR);
}
?>