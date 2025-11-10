<?php
/**
 * Get Campus Locations Endpoint
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
    // Rate limiting (more relaxed for public endpoints)
    if (RATE_LIMIT_REQUESTS > 10) {
        checkRateLimit('locations');
    }

    // Database connection
    $pdo = getDB();

    // Get query parameters
    $category = $_GET['category'] ?? null;
    $search = $_GET['search'] ?? null;
    $active_only = $_GET['active_only'] ?? 'true';

    // Build WHERE clause
    $whereConditions = [];
    $params = [];

    if ($active_only === 'true') {
        $whereConditions[] = "is_active = 1";
    }

    if ($category && in_array($category, [CAT_ACADEMIC, CAT_HOSTEL, CAT_ADMIN, CAT_RECREATIONAL, CAT_MEDICAL, CAT_OTHER])) {
        $whereConditions[] = "category = ?";
        $params[] = $category;
    }

    if ($search) {
        $whereConditions[] = "(name LIKE ? OR description LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

    // Get locations grouped by category
    $sql = "
        SELECT
            id,
            name,
            category,
            description,
            latitude,
            longitude,
            is_active,
            created_at
        FROM locations
        $whereClause
        ORDER BY category, name
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $locations = $stmt->fetchAll();

    // Group locations by category
    $groupedLocations = [];
    $categoryCounts = [];

    foreach ($locations as $location) {
        $category = $location['category'];
        if (!isset($groupedLocations[$category])) {
            $groupedLocations[$category] = [];
        }
        $groupedLocations[$category][] = $location;
        $categoryCounts[$category] = ($categoryCounts[$category] ?? 0) + 1;
    }

    // Get category information with icons
    $categories = [
        CAT_ACADEMIC => [
            'name' => 'Academic Buildings',
            'description' => 'Lecture halls, faculty buildings, and academic facilities',
            'icon' => '🎓',
            'count' => $categoryCounts[CAT_ACADEMIC] ?? 0
        ],
        CAT_HOSTEL => [
            'name' => 'Hostel Accommodations',
            'description' => 'Student residential halls and accommodations',
            'icon' => '🏠',
            'count' => $categoryCounts[CAT_HOSTEL] ?? 0
        ],
        CAT_ADMIN => [
            'name' => 'Administrative Buildings',
            'description' => 'Administrative offices and service departments',
            'icon' => '🏢',
            'count' => $categoryCounts[CAT_ADMIN] ?? 0
        ],
        CAT_RECREATIONAL => [
            'name' => 'Recreational Facilities',
            'description' => 'Sports facilities, cafeterias, and recreational areas',
            'icon' => '🎮',
            'count' => $categoryCounts[CAT_RECREATIONAL] ?? 0
        ],
        CAT_MEDICAL => [
            'name' => 'Medical Facilities',
            'description' => 'Health centers and medical facilities',
            'icon' => '🏥',
            'count' => $categoryCounts[CAT_MEDICAL] ?? 0
        ],
        CAT_OTHER => [
            'name' => 'Other Locations',
            'description' => 'Other campus facilities and locations',
            'icon' => '📍',
            'count' => $categoryCounts[CAT_OTHER] ?? 0
        ]
    ];

    // Get recent locations (most recently used for emergencies)
    $recentStmt = $pdo->prepare("
        SELECT
            l.id,
            l.name,
            l.category,
            COUNT(e.id) as emergency_count
        FROM locations l
        JOIN emergencies e ON l.id = e.location_id
        WHERE l.is_active = 1
        AND e.reported_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY l.id, l.name, l.category
        ORDER BY emergency_count DESC
        LIMIT 10
    ");
    $recentStmt->execute();
    $recentLocations = $recentStmt->fetchAll();

    // Prepare response
    $response = [
        'categories' => $categories,
        'locations' => $groupedLocations,
        'recent_locations' => $recentLocations,
        'total_locations' => count($locations),
        'filters' => [
            'category' => $category,
            'search' => $search,
            'active_only' => $active_only === 'true'
        ],
        'last_updated' => date('Y-m-d H:i:s')
    ];

    // Add cache headers for better performance
    header('Cache-Control: public, max-age=3600'); // Cache for 1 hour
    header('ETag: "' . md5(json_encode($response)) . '"');

    sendResponse(true, 'Locations retrieved successfully', $response, HTTP_OK);

} catch (Exception $e) {
    logActivity("Locations retrieval error: " . $e->getMessage(), "ERROR");
    sendError('Failed to retrieve locations', HTTP_INTERNAL_SERVER_ERROR);
}
?>