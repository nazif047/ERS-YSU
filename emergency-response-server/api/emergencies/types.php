<?php
/**
 * Get Emergency Types Endpoint
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

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendError('Method not allowed', HTTP_METHOD_NOT_ALLOWED);
}

try {
    // Rate limiting (relaxed for public endpoint)
    if (RATE_LIMIT_REQUESTS > 10) {
        checkRateLimit('emergency_types');
    }

    // Database connection
    $pdo = getDB();

    // Get query parameters
    $department = $_GET['department'] ?? null;
    $active_only = $_GET['active_only'] ?? 'true';

    // Build WHERE clause
    $whereConditions = [];
    $params = [];

    if ($active_only === 'true') {
        $whereConditions[] = "is_active = 1";
    }

    if ($department && in_array($department, [DEPT_HEALTH, DEPT_FIRE, DEPT_SECURITY])) {
        $whereConditions[] = "department = ?";
        $params[] = $department;
    }

    $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

    // Get emergency types grouped by department
    $sql = "
        SELECT
            id,
            name,
            department,
            description,
            icon,
            color,
            priority,
            is_active
        FROM emergency_types
        $whereClause
        ORDER BY department, priority DESC, name
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $emergencyTypes = $stmt->fetchAll();

    // Group by department
    $groupedTypes = [];
    $departmentCounts = [];

    foreach ($emergencyTypes as $type) {
        $dept = $type['department'];
        if (!isset($groupedTypes[$dept])) {
            $groupedTypes[$dept] = [];
        }
        $groupedTypes[$dept][] = $type;
        $departmentCounts[$dept] = ($departmentCounts[$dept] ?? 0) + 1;
    }

    // Department information
    $departments = [
        DEPT_HEALTH => [
            'name' => 'Health Emergencies',
            'description' => 'Medical and health-related emergencies',
            'color' => '#4CD964',
            'icon' => '🏥',
            'count' => $departmentCounts[DEPT_HEALTH] ?? 0
        ],
        DEPT_FIRE => [
            'name' => 'Fire Emergencies',
            'description' => 'Fire-related emergencies and safety hazards',
            'color' => '#FF9500',
            'icon' => '🔥',
            'count' => $departmentCounts[DEPT_FIRE] ?? 0
        ],
        DEPT_SECURITY => [
            'name' => 'Security Emergencies',
            'description' => 'Security threats and safety concerns',
            'color' => '#007AFF',
            'icon' => '🚔',
            'count' => $departmentCounts[DEPT_SECURITY] ?? 0
        ]
    ];

    // Get most common emergency types (based on recent reports)
    $commonStmt = $pdo->prepare("
        SELECT
            et.id,
            et.name,
            et.department,
            et.icon,
            et.color,
            COUNT(e.id) as report_count
        FROM emergency_types et
        LEFT JOIN emergencies e ON et.id = e.emergency_type_id
        AND e.reported_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        WHERE et.is_active = 1
        GROUP BY et.id, et.name, et.department, et.icon, et.color
        ORDER BY report_count DESC
        LIMIT 10
    ");
    $commonStmt->execute();
    $commonTypes = $commonStmt->fetchAll();

    // Prepare response
    $response = [
        'departments' => $departments,
        'emergency_types' => $groupedTypes,
        'common_emergencies' => $commonTypes,
        'total_types' => count($emergencyTypes),
        'filters' => [
            'department' => $department,
            'active_only' => $active_only === 'true'
        ],
        'last_updated' => date('Y-m-d H:i:s')
    ];

    // Add cache headers
    header('Cache-Control: public, max-age=3600'); // Cache for 1 hour
    header('ETag: "' . md5(json_encode($response)) . '"');

    sendResponse(true, 'Emergency types retrieved successfully', $response, HTTP_OK);

} catch (Exception $e) {
    logActivity("Emergency types retrieval error: " . $e->getMessage(), "ERROR");
    sendError('Failed to retrieve emergency types', HTTP_INTERNAL_SERVER_ERROR);
}
?>