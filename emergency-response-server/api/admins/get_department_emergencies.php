<?php
/**
 * Get Department Emergencies Endpoint
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

    // Authenticate user - require department admin privileges
    $user = JWTHelper::authenticateUser($pdo);

    // Check admin permissions
    if (!in_array($user['role'], ['health_admin', 'fire_admin', 'security_admin', 'super_admin'])) {
        sendError('Department admin privileges required', HTTP_FORBIDDEN);
    }

    // Get department from user role
    $userDepartment = $user['role'] === 'super_admin' ? null : str_replace('_admin', '', $user['role']);

    // Get query parameters
    $status = $_GET['status'] ?? null;
    $severity = $_GET['severity'] ?? null;
    $date_from = $_GET['date_from'] ?? null;
    $date_to = $_GET['date_to'] ?? null;
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(50, max(5, (int)($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;

    // Validate filters
    $validStatuses = ['pending', 'in_progress', 'resolved', 'closed'];
    $validSeverities = ['low', 'medium', 'high', 'critical'];

    if ($status && !in_array($status, $validStatuses)) {
        sendError('Invalid status filter', HTTP_BAD_REQUEST);
    }

    if ($severity && !in_array($severity, $validSeverities)) {
        sendError('Invalid severity filter', HTTP_BAD_REQUEST);
    }

    if ($date_from && !strtotime($date_from)) {
        sendError('Invalid date_from format', HTTP_BAD_REQUEST);
    }

    if ($date_to && !strtotime($date_to)) {
        sendError('Invalid date_to format', HTTP_BAD_REQUEST);
    }

    // Build base query
    $baseQuery = "
        FROM emergencies e
        JOIN emergency_types et ON e.emergency_type_id = et.id
        JOIN locations l ON e.location_id = l.id
        JOIN users u ON e.user_id = u.id
        LEFT JOIN users assigned_user ON e.assigned_to = assigned_user.id
        WHERE 1=1
    ";

    $params = [];

    // Filter by department (unless super admin)
    if ($userDepartment) {
        $baseQuery .= " AND et.department = ?";
        $params[] = $userDepartment;
    }

    // Add status filter
    if ($status) {
        $baseQuery .= " AND e.status = ?";
        $params[] = $status;
    }

    // Add severity filter
    if ($severity) {
        $baseQuery .= " AND e.severity = ?";
        $params[] = $severity;
    }

    // Add date range filter
    if ($date_from) {
        $baseQuery .= " AND e.reported_at >= ?";
        $params[] = $date_from . ' 00:00:00';
    }

    if ($date_to) {
        $baseQuery .= " AND e.reported_at <= ?";
        $params[] = $date_to . ' 23:59:59';
    }

    // Get total count
    $countQuery = "SELECT COUNT(*) as total " . $baseQuery;
    $countStmt = $pdo->prepare($countQuery);
    $countStmt->execute($params);
    $totalRecords = (int)$countStmt->fetch()['total'];

    // Get emergencies with pagination
    $emergenciesQuery = "
        SELECT
            e.id,
            e.description,
            e.status,
            e.severity,
            e.reported_at,
            e.resolved_at,
            e.response_time_minutes,
            e.assigned_to,
            et.name as emergency_type,
            et.department as emergency_department,
            et.icon as emergency_icon,
            et.color as emergency_color,
            l.name as location_name,
            l.category as location_category,
            l.latitude as location_latitude,
            l.longitude as location_longitude,
            u.id as reporter_id,
            u.full_name as reporter_name,
            u.phone as reporter_phone,
            u.email as reporter_email,
            u.department as reporter_department,
            assigned_user.full_name as assigned_responder_name,
            assigned_user.phone as assigned_responder_phone,
            assigned_user.email as assigned_responder_email,
            (SELECT COUNT(*) FROM emergency_updates eu WHERE eu.emergency_id = e.id) as update_count,
            (SELECT eu.created_at FROM emergency_updates eu WHERE eu.emergency_id = e.id ORDER BY eu.created_at DESC LIMIT 1) as last_update_at,
            TIMESTAMPDIFF(MINUTE, e.reported_at, NOW()) as minutes_since_reported
        $baseQuery
        ORDER BY
            CASE WHEN e.status = 'pending' THEN 1 ELSE 2 END,
            CASE WHEN e.severity = 'critical' THEN 1 WHEN e.severity = 'high' THEN 2 WHEN e.severity = 'medium' THEN 3 ELSE 4 END,
            e.reported_at DESC
        LIMIT ? OFFSET ?
    ";

    $emergenciesStmt = $pdo->prepare($emergenciesQuery);
    $params[] = $limit;
    $params[] = $offset;
    $emergenciesStmt->execute($params);
    $emergencies = $emergenciesStmt->fetchAll();

    // Calculate pagination info
    $totalPages = ceil($totalRecords / $limit);
    $hasNextPage = $page < $totalPages;
    $hasPrevPage = $page > 1;

    // Get department statistics
    $statsQuery = "
        SELECT
            e.status,
            e.severity,
            COUNT(*) as count,
            AVG(e.response_time_minutes) as avg_response_time
        FROM emergencies e
        JOIN emergency_types et ON e.emergency_type_id = et.id
        WHERE 1=1
    ";

    $statsParams = [];
    if ($userDepartment) {
        $statsQuery .= " AND et.department = ?";
        $statsParams[] = $userDepartment;
    }

    if ($date_from) {
        $statsQuery .= " AND e.reported_at >= ?";
        $statsParams[] = $date_from . ' 00:00:00';
    }

    if ($date_to) {
        $statsQuery .= " AND e.reported_at <= ?";
        $statsParams[] = $date_to . ' 23:59:59';
    }

    $statsQuery .= " GROUP BY e.status, e.severity";
    $statsStmt = $pdo->prepare($statsQuery);
    $statsStmt->execute($statsParams);
    $rawStats = $statsStmt->fetchAll();

    // Process statistics
    $statistics = [
        'by_status' => ['pending' => 0, 'in_progress' => 0, 'resolved' => 0, 'closed' => 0],
        'by_severity' => ['low' => 0, 'medium' => 0, 'high' => 0, 'critical' => 0],
        'avg_response_times' => []
    ];

    foreach ($rawStats as $stat) {
        $statistics['by_status'][$stat['status']] += (int)$stat['count'];
        $statistics['by_severity'][$stat['severity']] += (int)$stat['count'];

        if ($stat['avg_response_time']) {
            $statistics['avg_response_times'][$stat['status']] = round((float)$stat['avg_response_time'], 2);
        }
    }

    // Get workload statistics
    $workloadQuery = "
        SELECT
            assigned_user.id as responder_id,
            assigned_user.full_name as responder_name,
            COUNT(*) as assigned_count,
            SUM(CASE WHEN e.status IN ('pending', 'in_progress') THEN 1 ELSE 0 END) as active_count,
            AVG(e.response_time_minutes) as avg_response_time
        FROM emergencies e
        JOIN emergency_types et ON e.emergency_type_id = et.id
        LEFT JOIN users assigned_user ON e.assigned_to = assigned_user.id
        WHERE 1=1
    ";

    $workloadParams = [];
    if ($userDepartment) {
        $workloadQuery .= " AND et.department = ?";
        $workloadParams[] = $userDepartment;
    }

    $workloadQuery .= " GROUP BY assigned_user.id, assigned_user.full_name";
    $workloadStmt = $pdo->prepare($workloadQuery);
    $workloadStmt->execute($workloadParams);
    $workloadStats = $workloadStmt->fetchAll();

    // Format emergencies data
    $formattedEmergencies = array_map(function($emergency) {
        return [
            'id' => $emergency['id'],
            'type' => [
                'name' => $emergency['emergency_type'],
                'department' => $emergency['emergency_department'],
                'icon' => $emergency['emergency_icon'],
                'color' => $emergency['emergency_color']
            ],
            'location' => [
                'name' => $emergency['location_name'],
                'category' => $emergency['location_category'],
                'coordinates' => [
                    'latitude' => (float)$emergency['location_latitude'],
                    'longitude' => (float)$emergency['location_longitude']
                ]
            ],
            'description' => $emergency['description'],
            'status' => $emergency['status'],
            'severity' => $emergency['severity'],
            'reported_at' => $emergency['reported_at'],
            'resolved_at' => $emergency['resolved_at'],
            'response_time_minutes' => $emergency['response_time_minutes'],
            'minutes_since_reported' => (int)$emergency['minutes_since_reported'],
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
            ] : null,
            'update_count' => (int)$emergency['update_count'],
            'last_update_at' => $emergency['last_update_at']
        ];
    }, $emergencies);

    // Build response
    $response = [
        'emergencies' => $formattedEmergencies,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_records' => $totalRecords,
            'records_per_page' => $limit,
            'has_next_page' => $hasNextPage,
            'has_prev_page' => $hasPrevPage
        ],
        'filters' => [
            'status' => $status,
            'severity' => $severity,
            'date_from' => $date_from,
            'date_to' => $date_to,
            'department' => $userDepartment
        ],
        'statistics' => $statistics,
        'workload' => array_map(function($stat) {
            return [
                'responder' => [
                    'id' => $stat['responder_id'],
                    'name' => $stat['responder_name']
                ],
                'assigned_count' => (int)$stat['assigned_count'],
                'active_count' => (int)$stat['active_count'],
                'avg_response_time' => $stat['avg_response_time'] ? round((float)$stat['avg_response_time'], 2) : null
            ];
        }, $workloadStats),
        'summary' => [
            'total_emergencies' => $totalRecords,
            'active_emergencies' => $statistics['by_status']['pending'] + $statistics['by_status']['in_progress'],
            'critical_emergencies' => $statistics['by_severity']['critical'],
            'high_emergencies' => $statistics['by_severity']['high'],
            'department' => $userDepartment ?? 'all_departments'
        ]
    ];

    // Log access
    logActivity(sprintf(
        "Department emergencies retrieved: %s admin %s (Page %d, %d records)",
        $userDepartment ?? 'Super',
        $user['full_name'],
        $page,
        count($formattedEmergencies)
    ), "INFO");

    sendResponse(true, 'Department emergencies retrieved successfully', $response, HTTP_OK);

} catch (Exception $e) {
    logActivity("Get department emergencies error: " . $e->getMessage(), "ERROR");
    sendError($e->getMessage(), HTTP_INTERNAL_SERVER_ERROR);
}
?>