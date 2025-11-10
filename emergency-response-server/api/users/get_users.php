<?php
/**
 * Get Users List Endpoint (Admin Only)
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
    if (!in_array($user['role'], ['super_admin', 'health_admin', 'fire_admin', 'security_admin'])) {
        sendError('Admin privileges required', HTTP_FORBIDDEN);
    }

    // Get query parameters
    $role = $_GET['role'] ?? null;
    $department = $_GET['department'] ?? null;
    $search = $_GET['search'] ?? null;
    $is_active = $_GET['is_active'] ?? null;
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(100, max(5, (int)($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;

    // Validate filters
    $validRoles = ['student', 'staff', 'health_admin', 'fire_admin', 'security_admin', 'super_admin'];
    $validDepartments = ['academic', 'admin', 'health', 'security', 'technical', 'other'];

    if ($role && !in_array($role, $validRoles)) {
        sendError('Invalid role filter', HTTP_BAD_REQUEST);
    }

    if ($department && !in_array($department, $validDepartments)) {
        sendError('Invalid department filter', HTTP_BAD_REQUEST);
    }

    if ($is_active !== null) {
        $is_active = filter_var($is_active, FILTER_VALIDATE_BOOLEAN);
    }

    // Build base query
    $baseQuery = "
        FROM users u
        WHERE 1=1
    ";

    $params = [];

    // Add role filter
    if ($role) {
        $baseQuery .= " AND u.role = ?";
        $params[] = $role;
    }

    // Add department filter
    if ($department) {
        $baseQuery .= " AND u.department = ?";
        $params[] = $department;
    }

    // Add search filter
    if ($search) {
        $baseQuery .= " AND (u.full_name LIKE ? OR u.email LIKE ? OR u.school_id LIKE ?)";
        $searchParam = '%' . $search . '%';
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }

    // Add active status filter
    if ($is_active !== null) {
        $baseQuery .= " AND u.is_active = ?";
        $params[] = $is_active;
    }

    // Non-super admins can only view users from their department or with lower privileges
    if ($user['role'] !== 'super_admin') {
        $userDepartment = str_replace('_admin', '', $user['role']);
        $baseQuery .= " AND (
            u.department = ? OR
            u.role = 'student' OR
            u.role = 'staff' OR
            u.role = ?
        )";
        $params[] = $userDepartment;
        $params[] = $user['role'];
    }

    // Get total count
    $countQuery = "SELECT COUNT(*) as total " . $baseQuery;
    $countStmt = $pdo->prepare($countQuery);
    $countStmt->execute($params);
    $totalRecords = (int)$countStmt->fetch()['total'];

    // Get users with pagination
    $usersQuery = "
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
            (SELECT AVG(e.response_time_minutes) FROM emergencies e WHERE e.user_id = u.id AND e.response_time_minutes IS NOT NULL) as avg_response_time
        $baseQuery
        ORDER BY u.created_at DESC
        LIMIT ? OFFSET ?
    ";

    $usersStmt = $pdo->prepare($usersQuery);
    $params[] = $limit;
    $params[] = $offset;
    $usersStmt->execute($params);
    $users = $usersStmt->fetchAll();

    // Calculate pagination info
    $totalPages = ceil($totalRecords / $limit);
    $hasNextPage = $page < $totalPages;
    $hasPrevPage = $page > 1;

    // Get role and department statistics
    $statsQuery = "
        SELECT
            u.role,
            COUNT(*) as count,
            SUM(CASE WHEN u.is_active = 1 THEN 1 ELSE 0 END) as active_count
        FROM users u
        WHERE 1=1
    ";

    $statsParams = [];

    if ($user['role'] !== 'super_admin') {
        $userDepartment = str_replace('_admin', '', $user['role']);
        $statsQuery .= " AND (
            u.department = ? OR
            u.role = 'student' OR
            u.role = 'staff' OR
            u.role = ?
        )";
        $statsParams[] = $userDepartment;
        $statsParams[] = $user['role'];
    }

    $statsQuery .= " GROUP BY u.role";
    $statsStmt = $pdo->prepare($statsQuery);
    $statsStmt->execute($statsParams);
    $roleStats = $statsStmt->fetchAll();

    // Get department statistics
    $deptStatsQuery = "
        SELECT
            u.department,
            COUNT(*) as count,
            SUM(CASE WHEN u.is_active = 1 THEN 1 ELSE 0 END) as active_count
        FROM users u
        WHERE u.department IS NOT NULL
    ";

    $deptStatsParams = [];

    if ($user['role'] !== 'super_admin') {
        $userDepartment = str_replace('_admin', '', $user['role']);
        $deptStatsQuery .= " AND (
            u.department = ? OR
            u.role = 'student' OR
            u.role = 'staff' OR
            u.role = ?
        )";
        $deptStatsParams[] = $userDepartment;
        $deptStatsParams[] = $user['role'];
    }

    $deptStatsQuery .= " GROUP BY u.department";
    $deptStatsStmt = $pdo->prepare($deptStatsQuery);
    $deptStatsStmt->execute($deptStatsParams);
    $deptStats = $deptStatsStmt->fetchAll();

    // Format users data
    $formattedUsers = array_map(function($userRecord) {
        return [
            'id' => $userRecord['id'],
            'full_name' => $userRecord['full_name'],
            'email' => $userRecord['email'],
            'phone' => $userRecord['phone'],
            'school_id' => $userRecord['school_id'],
            'department' => $userRecord['department'],
            'role' => $userRecord['role'],
            'is_active' => (bool)$userRecord['is_active'],
            'verification_status' => [
                'email_verified' => !is_null($userRecord['email_verified_at']),
                'phone_verified' => !is_null($userRecord['phone_verified_at'])
            ],
            'statistics' => [
                'total_emergencies' => (int)$userRecord['total_emergencies'],
                'active_emergencies' => (int)$userRecord['active_emergencies'],
                'avg_response_time' => $userRecord['avg_response_time'] ? round((float)$userRecord['avg_response_time'], 2) : null
            ],
            'timestamps' => [
                'created_at' => $userRecord['created_at'],
                'updated_at' => $userRecord['updated_at'],
                'last_login' => $userRecord['last_login']
            ]
        ];
    }, $users);

    // Build response
    $response = [
        'users' => $formattedUsers,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_records' => $totalRecords,
            'records_per_page' => $limit,
            'has_next_page' => $hasNextPage,
            'has_prev_page' => $hasPrevPage
        ],
        'filters' => [
            'role' => $role,
            'department' => $department,
            'search' => $search,
            'is_active' => $is_active
        ],
        'statistics' => [
            'by_role' => array_map(function($stat) {
                return [
                    'role' => $stat['role'],
                    'total_count' => (int)$stat['count'],
                    'active_count' => (int)$stat['active_count']
                ];
            }, $roleStats),
            'by_department' => array_map(function($stat) {
                return [
                    'department' => $stat['department'],
                    'total_count' => (int)$stat['count'],
                    'active_count' => (int)$stat['active_count']
                ];
            }, $deptStats)
        ],
        'summary' => [
            'total_users' => $totalRecords,
            'active_users' => array_sum(array_column($roleStats, 'active_count')),
            'admin_users' => array_sum(array_filter($roleStats, fn($r) => str_contains($r['role'], 'admin')))['count'] ?? 0
        ]
    ];

    // Log access
    logActivity(sprintf(
        "Users list retrieved: %s admin %s (Page %d, %d records)",
        $user['role'],
        $user['full_name'],
        $page,
        count($formattedUsers)
    ), "INFO");

    sendResponse(true, 'Users retrieved successfully', $response, HTTP_OK);

} catch (Exception $e) {
    logActivity("Get users error: " . $e->getMessage(), "ERROR");
    sendError($e->getMessage(), HTTP_INTERNAL_SERVER_ERROR);
}
?>