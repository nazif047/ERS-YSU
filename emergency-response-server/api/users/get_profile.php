<?php
/**
 * Get User Profile Endpoint
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

    // Get user's complete profile
    $profileQuery = "
        SELECT
            u.id,
            u.full_name,
            u.email,
            u.phone,
            u.school_id,
            u.department,
            u.role,
            u.is_active,
            u.created_at,
            u.updated_at,
            u.last_login,
            u.email_verified_at,
            u.phone_verified_at,
            (SELECT COUNT(*) FROM emergencies e WHERE e.user_id = u.id) as total_emergencies,
            (SELECT COUNT(*) FROM emergencies e WHERE e.user_id = u.id AND e.status IN ('pending', 'in_progress')) as active_emergencies,
            (SELECT COUNT(*) FROM emergencies e WHERE e.user_id = u.id AND e.reported_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as recent_emergencies,
            (SELECT AVG(e.response_time_minutes) FROM emergencies e WHERE e.user_id = u.id AND e.response_time_minutes IS NOT NULL) as avg_response_time
        FROM users u
        WHERE u.id = ?
    ";

    $profileStmt = $pdo->prepare($profileQuery);
    $profileStmt->execute([$user['id']]);
    $profile = $profileStmt->fetch();

    if (!$profile) {
        sendError('User profile not found', HTTP_NOT_FOUND);
    }

    // Get user's recent emergencies
    $recentEmergenciesQuery = "
        SELECT
            e.id,
            e.description,
            e.status,
            e.severity,
            e.reported_at,
            e.resolved_at,
            et.name as emergency_type,
            et.department as emergency_department,
            et.icon as emergency_icon,
            et.color as emergency_color,
            l.name as location_name,
            l.category as location_category
        FROM emergencies e
        JOIN emergency_types et ON e.emergency_type_id = et.id
        JOIN locations l ON e.location_id = l.id
        WHERE e.user_id = ?
        ORDER BY e.reported_at DESC
        LIMIT 5
    ";

    $recentEmergenciesStmt = $pdo->prepare($recentEmergenciesQuery);
    $recentEmergenciesStmt->execute([$user['id']]);
    $recentEmergencies = $recentEmergenciesStmt->fetchAll();

    // Get user's notifications
    $notificationsQuery = "
        SELECT
            n.id,
            n.title,
            n.message,
            n.type,
            n.is_read,
            n.created_at,
            e.id as emergency_id,
            et.name as emergency_type
        FROM notifications n
        LEFT JOIN emergencies e ON n.emergency_id = e.id
        LEFT JOIN emergency_types et ON e.emergency_type_id = et.id
        WHERE n.user_id = ?
        ORDER BY n.created_at DESC
        LIMIT 5
    ";

    $notificationsStmt = $pdo->prepare($notificationsQuery);
    $notificationsStmt->execute([$user['id']]);
    $notifications = $notificationsStmt->fetchAll();

    // Get department information if applicable
    $departmentInfo = null;
    if ($profile['department']) {
        $deptQuery = "
            SELECT
                code,
                name,
                description,
                contact_email,
                contact_phone
            FROM departments
            WHERE code = ?
        ";
        $deptStmt = $pdo->prepare($deptQuery);
        $deptStmt->execute([$profile['department']]);
        $departmentInfo = $deptStmt->fetch();
    }

    // Get role permissions
    $rolePermissions = getRolePermissions($profile['role']);

    // Format profile data
    $response = [
        'user' => [
            'id' => $profile['id'],
            'full_name' => $profile['full_name'],
            'email' => $profile['email'],
            'phone' => $profile['phone'],
            'school_id' => $profile['school_id'],
            'department' => $profile['department'],
            'role' => $profile['role'],
            'is_active' => (bool)$profile['is_active'],
            'verification_status' => [
                'email_verified' => !is_null($profile['email_verified_at']),
                'phone_verified' => !is_null($profile['phone_verified_at']),
                'email_verified_at' => $profile['email_verified_at'],
                'phone_verified_at' => $profile['phone_verified_at']
            ],
            'timestamps' => [
                'created_at' => $profile['created_at'],
                'updated_at' => $profile['updated_at'],
                'last_login' => $profile['last_login']
            ]
        ],
        'statistics' => [
            'total_emergencies' => (int)$profile['total_emergencies'],
            'active_emergencies' => (int)$profile['active_emergencies'],
            'recent_emergencies' => (int)$profile['recent_emergencies'],
            'avg_response_time' => $profile['avg_response_time'] ? round((float)$profile['avg_response_time'], 2) : null
        ],
        'department_info' => $departmentInfo ? [
            'code' => $departmentInfo['code'],
            'name' => $departmentInfo['name'],
            'description' => $departmentInfo['description'],
            'contact' => [
                'email' => $departmentInfo['contact_email'],
                'phone' => $departmentInfo['contact_phone']
            ]
        ] : null,
        'role_permissions' => $rolePermissions,
        'recent_emergencies' => array_map(function($emergency) {
            return [
                'id' => $emergency['id'],
                'description' => $emergency['description'],
                'status' => $emergency['status'],
                'severity' => $emergency['severity'],
                'reported_at' => $emergency['reported_at'],
                'resolved_at' => $emergency['resolved_at'],
                'type' => [
                    'name' => $emergency['emergency_type'],
                    'department' => $emergency['emergency_department'],
                    'icon' => $emergency['emergency_icon'],
                    'color' => $emergency['emergency_color']
                ],
                'location' => [
                    'name' => $emergency['location_name'],
                    'category' => $emergency['location_category']
                ]
            ];
        }, $recentEmergencies),
        'recent_notifications' => array_map(function($notification) {
            return [
                'id' => $notification['id'],
                'title' => $notification['title'],
                'message' => $notification['message'],
                'type' => $notification['type'],
                'is_read' => (bool)$notification['is_read'],
                'created_at' => $notification['created_at'],
                'emergency' => $notification['emergency_id'] ? [
                    'id' => $notification['emergency_id'],
                    'type' => $notification['emergency_type']
                ] : null
            ];
        }, $notifications)
    ];

    // Log access
    logActivity(sprintf(
        "User profile retrieved: %s (%s)",
        $profile['full_name'],
        $profile['role']
    ), "INFO");

    sendResponse(true, 'User profile retrieved successfully', $response, HTTP_OK);

} catch (Exception $e) {
    logActivity("Get user profile error: " . $e->getMessage(), "ERROR");
    sendError($e->getMessage(), HTTP_INTERNAL_SERVER_ERROR);
}

/**
 * Get role permissions
 */
function getRolePermissions($role) {
    $permissions = [
        'student' => [
            'report_emergencies' => true,
            'view_own_emergencies' => true,
            'update_own_profile' => true
        ],
        'staff' => [
            'report_emergencies' => true,
            'view_own_emergencies' => true,
            'update_own_profile' => true
        ],
        'health_admin' => [
            'report_emergencies' => true,
            'view_own_emergencies' => true,
            'view_department_emergencies' => true,
            'update_emergency_status' => true,
            'update_own_profile' => true,
            'view_department_analytics' => true,
            'manage_locations' => true
        ],
        'fire_admin' => [
            'report_emergencies' => true,
            'view_own_emergencies' => true,
            'view_department_emergencies' => true,
            'update_emergency_status' => true,
            'update_own_profile' => true,
            'view_department_analytics' => true,
            'manage_locations' => true
        ],
        'security_admin' => [
            'report_emergencies' => true,
            'view_own_emergencies' => true,
            'view_department_emergencies' => true,
            'update_emergency_status' => true,
            'update_own_profile' => true,
            'view_department_analytics' => true,
            'manage_locations' => true
        ],
        'super_admin' => [
            'report_emergencies' => true,
            'view_all_emergencies' => true,
            'view_own_emergencies' => true,
            'update_emergency_status' => true,
            'update_own_profile' => true,
            'view_all_analytics' => true,
            'manage_locations' => true,
            'manage_users' => true,
            'system_configuration' => true
        ]
    ];

    return $permissions[$role] ?? [];
}
?>