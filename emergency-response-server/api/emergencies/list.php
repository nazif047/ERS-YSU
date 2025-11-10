<?php
/**
 * Get Emergency List Endpoint
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

    // Get pagination parameters
    $pagination = getPaginationParams();

    // Get query parameters
    $status = $_GET['status'] ?? null;
    $severity = $_GET['severity'] ?? null;
    $department = $_GET['department'] ?? null;
    $startDate = $_GET['start_date'] ?? null;
    $endDate = $_GET['end_date'] ?? null;

    // Build WHERE clause based on user role and filters
    $whereConditions = [];
    $params = [];

    // User role-based filtering
    if (in_array($user['role'], [ROLE_SECURITY_ADMIN, ROLE_HEALTH_ADMIN, ROLE_FIRE_ADMIN])) {
        // Admin can see emergencies for their department
        $userDepartment = str_replace('_admin', '', $user['role']);
        $whereConditions[] = "et.department = ?";
        $params[] = $userDepartment;
    } elseif ($user['role'] === ROLE_SUPER_ADMIN) {
        // Super admin can see all emergencies
        // No additional filtering needed
    } else {
        // Regular users can only see their own emergencies
        $whereConditions[] = "e.user_id = ?";
        $params[] = $user['id'];
    }

    // Apply filters
    if ($status && in_array($status, [STATUS_PENDING, STATUS_IN_PROGRESS, STATUS_RESOLVED, STATUS_CLOSED])) {
        $whereConditions[] = "e.status = ?";
        $params[] = $status;
    }

    if ($severity && in_array($severity, [SEVERITY_LOW, SEVERITY_MEDIUM, SEVERITY_HIGH, SEVERITY_CRITICAL])) {
        $whereConditions[] = "e.severity = ?";
        $params[] = $severity;
    }

    if ($department && in_array($department, [DEPT_HEALTH, DEPT_FIRE, DEPT_SECURITY])) {
        $whereConditions[] = "et.department = ?";
        $params[] = $department;
    }

    if ($startDate) {
        $whereConditions[] = "DATE(e.reported_at) >= ?";
        $params[] = $startDate;
    }

    if ($endDate) {
        $whereConditions[] = "DATE(e.reported_at) <= ?";
        $params[] = $endDate;
    }

    $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

    // Get total count
    $countSql = "
        SELECT COUNT(*) as total
        FROM emergencies e
        JOIN emergency_types et ON e.emergency_type_id = et.id
        JOIN locations l ON e.location_id = l.id
        JOIN users u ON e.user_id = u.id
        $whereClause
    ";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $total = $countStmt->fetch()['total'];

    // Get emergencies list
    $listSql = "
        SELECT
            e.id,
            e.description,
            e.status,
            e.severity,
            e.reported_at,
            e.resolved_at,
            e.assigned_to,
            et.name as emergency_type,
            et.department as emergency_department,
            et.icon as emergency_icon,
            et.color as emergency_color,
            l.name as location_name,
            l.category as location_category,
            u.full_name as reporter_name,
            u.phone as reporter_phone,
            u.department as reporter_department,
            assigned.full_name as assigned_to_name
        FROM emergencies e
        JOIN emergency_types et ON e.emergency_type_id = et.id
        JOIN locations l ON e.location_id = l.id
        JOIN users u ON e.user_id = u.id
        LEFT JOIN users assigned ON e.assigned_to = assigned.id
        $whereClause
        ORDER BY e.reported_at DESC
        LIMIT ? OFFSET ?
    ";

    $listParams = array_merge($params, [$pagination['limit'], $pagination['offset']]);
    $listStmt = $pdo->prepare($listSql);
    $listStmt->execute($listParams);
    $emergencies = $listStmt->fetchAll();

    // Format emergencies
    foreach ($emergencies as &$emergency) {
        $emergency['reported_at'] = timeAgo($emergency['reported_at']);
        $emergency['resolved_at'] = $emergency['resolved_at'] ? timeAgo($emergency['resolved_at']) : null;
        $emergency['description'] = substr($emergency['description'], 0, 200) . (strlen($emergency['description']) > 200 ? '...' : '');
    }

    // Get statistics
    $statsSql = "
        SELECT
            COUNT(CASE WHEN e.status = 'pending' THEN 1 END) as pending_count,
            COUNT(CASE WHEN e.status = 'in_progress' THEN 1 END) as in_progress_count,
            COUNT(CASE WHEN e.status = 'resolved' THEN 1 END) as resolved_count,
            COUNT(CASE WHEN e.severity = 'critical' THEN 1 END) as critical_count,
            COUNT(CASE WHEN DATE(e.reported_at) = CURDATE() THEN 1 END) as today_count,
            AVG(TIMESTAMPDIFF(MINUTE, e.reported_at, e.resolved_at)) as avg_resolution_time
        FROM emergencies e
        JOIN emergency_types et ON e.emergency_type_id = et.id
        " . (!empty($whereConditions) ? "WHERE " . str_replace("e.", "e.", implode(" AND ", $whereConditions)) : "") . "
    ";
    $statsStmt = $pdo->prepare($statsSql);
    $statsStmt->execute($params);
    $stats = $statsStmt->fetch();

    // Prepare response
    $response = generatePaginationResponse($total, $pagination['page'], $pagination['limit'], [
        'emergencies' => $emergencies,
        'statistics' => [
            'pending' => intval($stats['pending_count']),
            'in_progress' => intval($stats['in_progress_count']),
            'resolved' => intval($stats['resolved_count']),
            'critical' => intval($stats['critical_count']),
            'today' => intval($stats['today_count']),
            'avg_resolution_time' => round($stats['avg_resolution_time'] ?? 0, 1)
        ],
        'filters' => [
            'status' => $status,
            'severity' => $severity,
            'department' => $department,
            'start_date' => $startDate,
            'end_date' => $endDate
        ]
    ]);

    sendResponse(true, 'Emergency list retrieved successfully', $response, HTTP_OK);

} catch (Exception $e) {
    logActivity("Emergency list error: " . $e->getMessage(), "ERROR");
    sendError('Failed to retrieve emergency list', HTTP_INTERNAL_SERVER_ERROR);
}
?>