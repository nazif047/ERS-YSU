<?php
/**
 * Get Admin Dashboard Data Endpoint
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

    // Check admin permissions
    $allowedRoles = [ROLE_SUPER_ADMIN, ROLE_SECURITY_ADMIN, ROLE_HEALTH_ADMIN, ROLE_FIRE_ADMIN];
    if (!in_array($user['role'], $allowedRoles)) {
        sendError(ERR_PERMISSION_DENIED, HTTP_FORBIDDEN);
    }

    // Determine department filter
    $department = null;
    if ($user['role'] !== ROLE_SUPER_ADMIN) {
        $department = str_replace('_admin', '', $user['role']);
    }

    // Get time period from query parameter (default: 7 days)
    $period = intval($_GET['period'] ?? 7);
    $period = max(1, min(365, $period)); // Limit between 1 and 365 days

    // Build base WHERE conditions
    $whereConditions = ["e.reported_at >= DATE_SUB(NOW(), INTERVAL ? DAY)"];
    $params = [$period];

    if ($department) {
        $whereConditions[] = "et.department = ?";
        $params[] = $department;
    }

    $whereClause = implode(" AND ", $whereConditions);

    // Get dashboard statistics
    $statsStmt = $pdo->prepare("
        SELECT
            COUNT(CASE WHEN e.status = 'pending' THEN 1 END) as pending_count,
            COUNT(CASE WHEN e.status = 'in_progress' THEN 1 END) as in_progress_count,
            COUNT(CASE WHEN e.status = 'resolved' THEN 1 END) as resolved_count,
            COUNT(CASE WHEN e.status = 'closed' THEN 1 END) as closed_count,
            COUNT(CASE WHEN e.severity = 'critical' THEN 1 END) as critical_count,
            COUNT(CASE WHEN e.severity = 'high' THEN 1 END) as high_count,
            COUNT(CASE WHEN DATE(e.reported_at) = CURDATE() THEN 1 END) as today_count,
            COUNT(CASE WHEN DATE(e.reported_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY) THEN 1 END) as yesterday_count,
            AVG(TIMESTAMPDIFF(MINUTE, e.reported_at, e.resolved_at)) as avg_resolution_time,
            COUNT(*) as total_emergencies
        FROM emergencies e
        JOIN emergency_types et ON e.emergency_type_id = et.id
        WHERE $whereClause
    ");
    $statsStmt->execute($params);
    $stats = $statsStmt->fetch();

    // Get recent emergencies
    $recentStmt = $pdo->prepare("
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
            u.full_name as reporter_name,
            u.phone as reporter_phone,
            assigned.full_name as assigned_to_name
        FROM emergencies e
        JOIN emergency_types et ON e.emergency_type_id = et.id
        JOIN locations l ON e.location_id = l.id
        JOIN users u ON e.user_id = u.id
        LEFT JOIN users assigned ON e.assigned_to = assigned.id
        WHERE $whereClause
        ORDER BY e.reported_at DESC
        LIMIT 10
    ");
    $recentStmt->execute($params);
    $recent_emergencies = $recentStmt->fetchAll();

    // Format recent emergencies
    foreach ($recent_emergencies as &$emergency) {
        $emergency['reported_at'] = timeAgo($emergency['reported_at']);
        $emergency['description'] = substr($emergency['description'], 0, 150) . (strlen($emergency['description']) > 150 ? '...' : '');
    }

    // Get emergency trends (daily counts for the last 14 days)
    $trendsStmt = $pdo->prepare("
        SELECT
            DATE(e.reported_at) as date,
            COUNT(*) as count,
            et.department
        FROM emergencies e
        JOIN emergency_types et ON e.emergency_type_id = et.id
        WHERE e.reported_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
        " . ($department ? "AND et.department = ?" : "") . "
        GROUP BY DATE(e.reported_at), et.department
        ORDER BY date DESC
    ");
    $trendsParams = $department ? [$department] : [];
    $trendsStmt->execute($trendsParams);
    $trends_data = $trendsStmt->fetchAll();

    // Group trends by date
    $trends = [];
    foreach ($trends_data as $trend) {
        $date = $trend['date'];
        if (!isset($trends[$date])) {
            $trends[$date] = ['date' => $date, 'total' => 0];
        }
        $trends[$date][$trend['department']] = $trend['count'];
        $trends[$date]['total'] += $trend['count'];
    }

    // Get top emergency types
    $topTypesStmt = $pdo->prepare("
        SELECT
            et.name,
            et.department,
            et.icon,
            et.color,
            COUNT(e.id) as count
        FROM emergency_types et
        LEFT JOIN emergencies e ON et.id = e.emergency_type_id
        AND e.reported_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        " . ($department ? "AND et.department = ?" : "") . "
        WHERE et.is_active = 1
        GROUP BY et.id, et.name, et.department, et.icon, et.color
        ORDER BY count DESC
        LIMIT 10
    ");
    $topTypesParams = $department ? [$period, $department] : [$period];
    $topTypesStmt->execute($topTypesParams);
    $top_emergency_types = $topTypesStmt->fetchAll();

    // Get top locations
    $topLocationsStmt = $pdo->prepare("
        SELECT
            l.name,
            l.category,
            COUNT(e.id) as count
        FROM locations l
        LEFT JOIN emergencies e ON l.id = e.location_id
        AND e.reported_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        " . ($department ? "
        JOIN emergency_types et ON e.emergency_type_id = et.id
        WHERE et.department = ?
        AND l.is_active = 1
        " : "WHERE l.is_active = 1") . "
        GROUP BY l.id, l.name, l.category
        ORDER BY count DESC
        LIMIT 10
    ");
    $topLocationsParams = $department ? [$period, $department] : [$period];
    $topLocationsStmt->execute($topLocationsParams);
    $top_locations = $topLocationsStmt->fetchAll();

    // Get response time metrics
    $responseTimeStmt = $pdo->prepare("
        SELECT
            et.department,
            AVG(TIMESTAMPDIFF(MINUTE, e.reported_at,
                CASE
                    WHEN e.status IN ('resolved', 'closed') AND e.resolved_at IS NOT NULL
                    THEN e.resolved_at
                    ELSE NOW()
                END
            )) as avg_response_time,
            MIN(TIMESTAMPDIFF(MINUTE, e.reported_at,
                CASE
                    WHEN e.status IN ('resolved', 'closed') AND e.resolved_at IS NOT NULL
                    THEN e.resolved_at
                    ELSE NOW()
                END
            )) as min_response_time,
            MAX(TIMESTAMPDIFF(MINUTE, e.reported_at,
                CASE
                    WHEN e.status IN ('resolved', 'closed') AND e.resolved_at IS NOT NULL
                    THEN e.resolved_at
                    ELSE NOW()
                END
            )) as max_response_time
        FROM emergencies e
        JOIN emergency_types et ON e.emergency_type_id = et.id
        WHERE $whereClause
        " . ($department ? "" : "GROUP BY et.department") . "
    ");
    $responseTimeStmt->execute($params);
    $response_times = $responseTimeStmt->fetchAll();

    // Prepare response
    $response = [
        'statistics' => [
            'total_emergencies' => intval($stats['total_emergencies']),
            'pending' => intval($stats['pending_count']),
            'in_progress' => intval($stats['in_progress_count']),
            'resolved' => intval($stats['resolved_count']),
            'closed' => intval($stats['closed_count']),
            'critical' => intval($stats['critical_count']),
            'high' => intval($stats['high_count']),
            'today' => intval($stats['today_count']),
            'yesterday' => intval($stats['yesterday_count']),
            'avg_resolution_time' => round($stats['avg_resolution_time'] ?? 0, 1),
            'resolution_rate' => $stats['total_emergencies'] > 0 ? round((intval($stats['resolved_count']) + intval($stats['closed_count'])) / $stats['total_emergencies'] * 100, 1) : 0
        ],
        'recent_emergencies' => $recent_emergencies,
        'trends' => array_values($trends),
        'top_emergency_types' => $top_emergency_types,
        'top_locations' => $top_locations,
        'response_times' => $response_times,
        'dashboard_info' => [
            'department' => $department,
            'period_days' => $period,
            'generated_at' => date('Y-m-d H:i:s'),
            'user_role' => $user['role'],
            'user_name' => $user['full_name']
        ]
    ];

    sendResponse(true, 'Dashboard data retrieved successfully', $response, HTTP_OK);

} catch (Exception $e) {
    logActivity("Dashboard data error: " . $e->getMessage(), "ERROR");
    sendError('Failed to retrieve dashboard data', HTTP_INTERNAL_SERVER_ERROR);
}
?>