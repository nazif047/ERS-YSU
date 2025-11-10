<?php
/**
 * Get User Emergencies Endpoint
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

    // Get query parameters
    $status = $_GET['status'] ?? null;
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(50, max(5, (int)($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;

    // Validate status filter
    $validStatuses = ['pending', 'in_progress', 'resolved', 'closed'];
    if ($status && !in_array($status, $validStatuses)) {
        sendError('Invalid status filter', HTTP_BAD_REQUEST);
    }

    // Build base query
    $baseQuery = "
        FROM emergencies e
        JOIN emergency_types et ON e.emergency_type_id = et.id
        JOIN locations l ON e.location_id = l.id
        WHERE e.user_id = ?
    ";

    $params = [$user['id']];

    // Add status filter if provided
    if ($status) {
        $baseQuery .= " AND e.status = ?";
        $params[] = $status;
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
            assigned_user.full_name as assigned_responder_name,
            assigned_user.role as assigned_responder_role,
            (SELECT COUNT(*) FROM emergency_updates eu WHERE eu.emergency_id = e.id) as update_count,
            (SELECT eu.created_at FROM emergency_updates eu WHERE eu.emergency_id = e.id ORDER BY eu.created_at DESC LIMIT 1) as last_update_at
        $baseQuery
        ORDER BY e.reported_at DESC
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

    // Get status statistics for user
    $statsQuery = "
        SELECT
            e.status,
            COUNT(*) as count
        FROM emergencies e
        WHERE e.user_id = ?
        GROUP BY e.status
    ";
    $statsStmt = $pdo->prepare($statsQuery);
    $statsStmt->execute([$user['id']]);
    $statusStats = [];
    while ($row = $statsStmt->fetch()) {
        $statusStats[$row['status']] = (int)$row['count'];
    }

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
                'category' => $emergency['location_category']
            ],
            'description' => $emergency['description'],
            'status' => $emergency['status'],
            'severity' => $emergency['severity'],
            'reported_at' => $emergency['reported_at'],
            'resolved_at' => $emergency['resolved_at'],
            'response_time_minutes' => $emergency['response_time_minutes'],
            'assigned_responder' => $emergency['assigned_to'] ? [
                'name' => $emergency['assigned_responder_name'],
                'role' => $emergency['assigned_responder_role']
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
            'current_status_stats' => $statusStats
        ],
        'summary' => [
            'total_emergencies' => $totalRecords,
            'active_emergencies' => ($statusStats['pending'] ?? 0) + ($statusStats['in_progress'] ?? 0),
            'resolved_emergencies' => ($statusStats['resolved'] ?? 0) + ($statusStats['closed'] ?? 0),
            'average_response_time' => $this->calculateAverageResponseTime($pdo, $user['id'])
        ]
    ];

    // Log access
    logActivity(sprintf(
        "User emergencies retrieved: %s by %s (Page %d, %d records)",
        $user['full_name'],
        $user['id'],
        $page,
        count($formattedEmergencies)
    ), "INFO");

    sendResponse(true, 'User emergencies retrieved successfully', $response, HTTP_OK);

} catch (Exception $e) {
    logActivity("Get user emergencies error: " . $e->getMessage(), "ERROR");
    sendError($e->getMessage(), HTTP_INTERNAL_SERVER_ERROR);
}

/**
 * Calculate average response time for user's resolved emergencies
 */
function calculateAverageResponseTime($pdo, $userId) {
    $stmt = $pdo->prepare("
        SELECT AVG(response_time_minutes) as avg_time
        FROM emergencies
        WHERE user_id = ?
        AND status IN ('resolved', 'closed')
        AND response_time_minutes IS NOT NULL
    ");
    $stmt->execute([$userId]);
    $result = $stmt->fetch();
    return $result ? round((float)$result['avg_time'], 2) : null;
}
?>