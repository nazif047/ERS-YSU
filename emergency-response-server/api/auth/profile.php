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
    // Rate limiting
    rateLimitMiddleware();

    // Database connection
    $pdo = getDB();

    // Authenticate user
    $user = JWTHelper::authenticateUser($pdo);

    // Get user statistics
    $statsStmt = $pdo->prepare("
        SELECT
            COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_emergencies,
            COUNT(CASE WHEN status = 'in_progress' THEN 1 END) as active_emergencies,
            COUNT(CASE WHEN status = 'resolved' AND DATE(resolved_at) = CURDATE() THEN 1 END) as resolved_today,
            COUNT(*) as total_emergencies
        FROM emergencies
        WHERE user_id = ?
        AND reported_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $statsStmt->execute([$user['id']]);
    $stats = $statsStmt->fetch();

    // Get recent emergencies
    $recentStmt = $pdo->prepare("
        SELECT
            e.id,
            e.description,
            e.status,
            e.severity,
            e.reported_at,
            e.resolved_at,
            et.name as emergency_type,
            et.department,
            l.name as location_name,
            l.category as location_category
        FROM emergencies e
        JOIN emergency_types et ON e.emergency_type_id = et.id
        JOIN locations l ON e.location_id = l.id
        WHERE e.user_id = ?
        ORDER BY e.reported_at DESC
        LIMIT 5
    ");
    $recentStmt->execute([$user['id']]);
    $recent_emergencies = $recentStmt->fetchAll();

    // Format recent emergencies
    foreach ($recent_emergencies as &$emergency) {
        $emergency['reported_at'] = timeAgo($emergency['reported_at']);
        $emergency['resolved_at'] = $emergency['resolved_at'] ? timeAgo($emergency['resolved_at']) : null;
    }

    // Prepare response
    $response = [
        'user' => [
            'id' => $user['id'],
            'school_id' => $user['school_id'],
            'email' => $user['email'],
            'full_name' => $user['full_name'],
            'phone' => $user['phone'],
            'role' => $user['role'],
            'department' => $user['department'],
            'created_at' => formatDate($user['created_at']),
            'last_login' => $user['last_login'] ? formatDate($user['last_login']) : null
        ],
        'statistics' => $stats,
        'recent_emergencies' => $recent_emergencies,
        'permissions' => [
            'can_report_emergency' => true,
            'can_view_dashboard' => true,
            'can_manage_users' => in_array($user['role'], [ROLE_SUPER_ADMIN, ROLE_SECURITY_ADMIN, ROLE_HEALTH_ADMIN, ROLE_FIRE_ADMIN]),
            'can_manage_locations' => in_array($user['role'], [ROLE_SUPER_ADMIN, ROLE_SECURITY_ADMIN, ROLE_HEALTH_ADMIN, ROLE_FIRE_ADMIN])
        ]
    ];

    sendResponse(true, 'Profile retrieved successfully', $response, HTTP_OK);

} catch (Exception $e) {
    logActivity("Profile error: " . $e->getMessage(), "ERROR");
    sendError('Failed to retrieve profile', HTTP_INTERNAL_SERVER_ERROR);
}
?>