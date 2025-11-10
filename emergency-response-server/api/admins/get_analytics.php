<?php
/**
 * Get Department Analytics Endpoint
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

    // Authenticate user - require admin privileges
    $user = JWTHelper::authenticateUser($pdo);

    // Check admin permissions
    if (!in_array($user['role'], ['health_admin', 'fire_admin', 'security_admin', 'super_admin'])) {
        sendError('Admin privileges required', HTTP_FORBIDDEN);
    }

    // Get department from user role
    $userDepartment = $user['role'] === 'super_admin' ? null : str_replace('_admin', '', $user['role']);

    // Get query parameters
    $period = $_GET['period'] ?? '30'; // days
    $date_from = $_GET['date_from'] ?? null;
    $date_to = $_GET['date_to'] ?? null;

    // Validate period
    $period = max(1, min(365, (int)$period));

    // Calculate date range
    if (!$date_from) {
        $date_from = date('Y-m-d', strtotime("-$period days"));
    }
    if (!$date_to) {
        $date_to = date('Y-m-d');
    }

    // Validate dates
    if (!strtotime($date_from) || !strtotime($date_to)) {
        sendError('Invalid date range', HTTP_BAD_REQUEST);
    }

    // Start transaction for consistent read
    $pdo->beginTransaction();

    try {
        // Get department statistics using stored procedure if available
        $departmentStats = [];
        if ($userDepartment) {
            try {
                $stmt = $pdo->prepare("CALL get_department_stats(?, ?, ?)");
                $stmt->execute([$userDepartment, $date_from, $date_to]);
                $departmentStats = $stmt->fetchAll();
                $stmt->closeCursor();
            } catch (Exception $e) {
                // Fallback to manual calculation if stored procedure doesn't exist
                $departmentStats = calculateDepartmentStats($pdo, $userDepartment, $date_from, $date_to);
            }
        }

        // Get overall emergency statistics
        $overallStatsQuery = "
            SELECT
                COUNT(*) as total_emergencies,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_count,
                SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved_count,
                SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed_count,
                SUM(CASE WHEN severity = 'critical' THEN 1 ELSE 0 END) as critical_count,
                SUM(CASE WHEN severity = 'high' THEN 1 ELSE 0 END) as high_count,
                SUM(CASE WHEN severity = 'medium' THEN 1 ELSE 0 END) as medium_count,
                SUM(CASE WHEN severity = 'low' THEN 1 ELSE 0 END) as low_count,
                AVG(response_time_minutes) as avg_response_time,
                MIN(response_time_minutes) as min_response_time,
                MAX(response_time_minutes) as max_response_time
            FROM emergencies e
            JOIN emergency_types et ON e.emergency_type_id = et.id
            WHERE e.reported_at BETWEEN ? AND ?
        ";

        $params = [$date_from . ' 00:00:00', $date_to . ' 23:59:59'];

        if ($userDepartment) {
            $overallStatsQuery .= " AND et.department = ?";
            $params[] = $userDepartment;
        }

        $overallStmt = $pdo->prepare($overallStatsQuery);
        $overallStmt->execute($params);
        $overallStats = $overallStmt->fetch();

        // Get emergency type distribution
        $typeQuery = "
            SELECT
                et.name as emergency_type,
                et.department,
                COUNT(*) as count,
                SUM(CASE WHEN e.status IN ('pending', 'in_progress') THEN 1 ELSE 0 END) as active_count,
                AVG(e.response_time_minutes) as avg_response_time
            FROM emergencies e
            JOIN emergency_types et ON e.emergency_type_id = et.id
            WHERE e.reported_at BETWEEN ? AND ?
        ";

        $typeParams = [$date_from . ' 00:00:00', $date_to . ' 23:59:59'];

        if ($userDepartment) {
            $typeQuery .= " AND et.department = ?";
            $typeParams[] = $userDepartment;
        }

        $typeQuery .= " GROUP BY et.id, et.name, et.department ORDER BY count DESC";

        $typeStmt = $pdo->prepare($typeQuery);
        $typeStmt->execute($typeParams);
        $typeStats = $typeStmt->fetchAll();

        // Get location hotspots
        $locationQuery = "
            SELECT
                l.name as location_name,
                l.category as location_category,
                COUNT(*) as emergency_count,
                SUM(CASE WHEN e.severity IN ('critical', 'high') THEN 1 ELSE 0 END) as high_priority_count,
                AVG(e.response_time_minutes) as avg_response_time
            FROM emergencies e
            JOIN locations l ON e.location_id = l.id
            JOIN emergency_types et ON e.emergency_type_id = et.id
            WHERE e.reported_at BETWEEN ? AND ?
        ";

        $locationParams = [$date_from . ' 00:00:00', $date_to . ' 23:59:59'];

        if ($userDepartment) {
            $locationQuery .= " AND et.department = ?";
            $locationParams[] = $userDepartment;
        }

        $locationQuery .= " GROUP BY l.id, l.name, l.category HAVING emergency_count > 0 ORDER BY emergency_count DESC LIMIT 10";

        $locationStmt = $pdo->prepare($locationQuery);
        $locationStmt->execute($locationParams);
        $locationStats = $locationStmt->fetchAll();

        // Get time-based trends (daily)
        $trendsQuery = "
            SELECT
                DATE(e.reported_at) as date,
                COUNT(*) as total_count,
                SUM(CASE WHEN e.severity IN ('critical', 'high') THEN 1 ELSE 0 END) as high_priority_count,
                SUM(CASE WHEN e.status IN ('pending', 'in_progress') THEN 1 ELSE 0 END) as active_count,
                AVG(e.response_time_minutes) as avg_response_time
            FROM emergencies e
            JOIN emergency_types et ON e.emergency_type_id = et.id
            WHERE e.reported_at BETWEEN ? AND ?
        ";

        $trendsParams = [$date_from . ' 00:00:00', $date_to . ' 23:59:59'];

        if ($userDepartment) {
            $trendsQuery .= " AND et.department = ?";
            $trendsParams[] = $userDepartment;
        }

        $trendsQuery .= " GROUP BY DATE(e.reported_at) ORDER BY date";

        $trendsStmt = $pdo->prepare($trendsQuery);
        $trendsStmt->execute($trendsParams);
        $trends = $trendsStmt->fetchAll();

        // Get responder workload
        $workloadQuery = "
            SELECT
                u.id as responder_id,
                u.full_name as responder_name,
                u.role,
                COUNT(e.id) as assigned_count,
                SUM(CASE WHEN e.status IN ('pending', 'in_progress') THEN 1 ELSE 0 END) as active_count,
                AVG(e.response_time_minutes) as avg_response_time,
                MAX(e.reported_at) as last_assignment
            FROM users u
            LEFT JOIN emergencies e ON u.id = e.assigned_to
            JOIN emergency_types et ON e.emergency_type_id = et.id
            WHERE u.role LIKE '%_admin' OR u.role = 'super_admin'
        ";

        $workloadParams = [];

        if ($userDepartment) {
            $workloadQuery .= " AND et.department = ?";
            $workloadParams[] = $userDepartment;
        }

        $workloadQuery .= " GROUP BY u.id, u.full_name, u.role ORDER BY assigned_count DESC";

        $workloadStmt = $pdo->prepare($workloadQuery);
        $workloadStmt->execute($workloadParams);
        $workloadStats = $workloadStmt->fetchAll();

        // Get hourly distribution
        $hourlyQuery = "
            SELECT
                HOUR(e.reported_at) as hour,
                COUNT(*) as count,
                SUM(CASE WHEN e.severity IN ('critical', 'high') THEN 1 ELSE 0 END) as high_priority_count
            FROM emergencies e
            JOIN emergency_types et ON e.emergency_type_id = et.id
            WHERE e.reported_at BETWEEN ? AND ?
        ";

        $hourlyParams = [$date_from . ' 00:00:00', $date_to . ' 23:59:59'];

        if ($userDepartment) {
            $hourlyQuery .= " AND et.department = ?";
            $hourlyParams[] = $userDepartment;
        }

        $hourlyQuery .= " GROUP BY HOUR(e.reported_at) ORDER BY hour";

        $hourlyStmt = $pdo->prepare($hourlyQuery);
        $hourlyStmt->execute($hourlyParams);
        $hourlyStats = $hourlyStmt->fetchAll();

        // Commit transaction
        $pdo->commit();

        // Calculate performance metrics
        $performanceMetrics = [
            'avg_response_time' => $overallStats['avg_response_time'] ? round((float)$overallStats['avg_response_time'], 2) : 0,
            'resolution_rate' => $overallStats['total_emergencies'] > 0 ?
                round((($overallStats['resolved_count'] + $overallStats['closed_count']) / $overallStats['total_emergencies']) * 100, 2) : 0,
            'critical_response_rate' => ($overallStats['critical_count'] + $overallStats['high_count']) > 0 ?
                round(($overallStats['resolved_count'] + $overallStats['closed_count']) / ($overallStats['critical_count'] + $overallStats['high_count']) * 100, 2) : 0,
            'active_emergencies' => (int)$overallStats['pending_count'] + (int)$overallStats['in_progress_count']
        ];

        // Format response
        $response = [
            'summary' => [
                'total_emergencies' => (int)$overallStats['total_emergencies'],
                'active_emergencies' => $performanceMetrics['active_emergencies'],
                'resolved_emergencies' => (int)$overallStats['resolved_count'] + (int)$overallStats['closed_count'],
                'critical_emergencies' => (int)$overallStats['critical_count'],
                'high_emergencies' => (int)$overallStats['high_count']
            ],
            'status_distribution' => [
                'pending' => (int)$overallStats['pending_count'],
                'in_progress' => (int)$overallStats['in_progress_count'],
                'resolved' => (int)$overallStats['resolved_count'],
                'closed' => (int)$overallStats['closed_count']
            ],
            'severity_distribution' => [
                'critical' => (int)$overallStats['critical_count'],
                'high' => (int)$overallStats['high_count'],
                'medium' => (int)$overallStats['medium_count'],
                'low' => (int)$overallStats['low_count']
            ],
            'response_time_metrics' => [
                'average' => $performanceMetrics['avg_response_time'],
                'minimum' => $overallStats['min_response_time'] ? (float)$overallStats['min_response_time'] : 0,
                'maximum' => $overallStats['max_response_time'] ? (float)$overallStats['max_response_time'] : 0
            ],
            'performance_metrics' => $performanceMetrics,
            'emergency_types' => array_map(function($stat) {
                return [
                    'type' => $stat['emergency_type'],
                    'department' => $stat['department'],
                    'total_count' => (int)$stat['count'],
                    'active_count' => (int)$stat['active_count'],
                    'avg_response_time' => $stat['avg_response_time'] ? round((float)$stat['avg_response_time'], 2) : 0
                ];
            }, $typeStats),
            'location_hotspots' => array_map(function($stat) {
                return [
                    'location' => $stat['location_name'],
                    'category' => $stat['location_category'],
                    'emergency_count' => (int)$stat['emergency_count'],
                    'high_priority_count' => (int)$stat['high_priority_count'],
                    'avg_response_time' => $stat['avg_response_time'] ? round((float)$stat['avg_response_time'], 2) : 0
                ];
            }, $locationStats),
            'daily_trends' => array_map(function($trend) {
                return [
                    'date' => $trend['date'],
                    'total_count' => (int)$trend['total_count'],
                    'high_priority_count' => (int)$trend['high_priority_count'],
                    'active_count' => (int)$trend['active_count'],
                    'avg_response_time' => $trend['avg_response_time'] ? round((float)$trend['avg_response_time'], 2) : 0
                ];
            }, $trends),
            'hourly_distribution' => array_map(function($stat) {
                return [
                    'hour' => (int)$stat['hour'],
                    'count' => (int)$stat['count'],
                    'high_priority_count' => (int)$stat['high_priority_count']
                ];
            }, $hourlyStats),
            'responder_workload' => array_map(function($stat) {
                return [
                    'responder' => [
                        'id' => $stat['responder_id'],
                        'name' => $stat['responder_name'],
                        'role' => $stat['role']
                    ],
                    'assigned_count' => (int)$stat['assigned_count'],
                    'active_count' => (int)$stat['active_count'],
                    'avg_response_time' => $stat['avg_response_time'] ? round((float)$stat['avg_response_time'], 2) : 0,
                    'last_assignment' => $stat['last_assignment']
                ];
            }, $workloadStats),
            'filters' => [
                'department' => $userDepartment,
                'date_from' => $date_from,
                'date_to' => $date_to,
                'period_days' => $period
            ]
        ];

        // Log access
        logActivity(sprintf(
            "Analytics retrieved: %s admin %s (%s to %s)",
            $userDepartment ?? 'Super',
            $user['full_name'],
            $date_from,
            $date_to
        ), "INFO");

        sendResponse(true, 'Analytics retrieved successfully', $response, HTTP_OK);

    } catch (Exception $e) {
        // Rollback transaction
        $pdo->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    logActivity("Get analytics error: " . $e->getMessage(), "ERROR");
    sendError($e->getMessage(), HTTP_INTERNAL_SERVER_ERROR);
}

/**
 * Calculate department statistics (fallback for missing stored procedure)
 */
function calculateDepartmentStats($pdo, $department, $dateFrom, $dateTo) {
    // This would contain the logic equivalent to the stored procedure
    // For now, return empty array as the main queries cover most needed stats
    return [];
}
?>