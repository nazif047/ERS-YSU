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

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendError('Method not allowed', HTTP_METHOD_NOT_ALLOWED);
}

try {
    // Database connection
    $pdo = getDB();

    // Get query parameters
    $category = $_GET['category'] ?? null;
    $include_inactive = filter_var($_GET['include_inactive'] ?? false, FILTER_VALIDATE_BOOLEAN);
    $search = $_GET['search'] ?? null;

    // Check cache (1 hour)
    $cacheKey = 'campus_locations_' . md5(serialize([
        'category' => $category,
        'include_inactive' => $include_inactive,
        'search' => $search
    ]));

    // For production, implement actual caching system
    // $cachedResult = getCache($cacheKey);
    // if ($cachedResult) {
    //     sendResponse(true, 'Campus locations retrieved from cache', $cachedResult, HTTP_OK);
    // }

    // Build base query
    $baseQuery = "
        SELECT
            l.id,
            l.name,
            l.description,
            l.category,
            l.latitude,
            l.longitude,
            l.is_active,
            l.created_at,
            l.updated_at,
            (SELECT COUNT(*) FROM emergencies e WHERE e.location_id = l.id) as emergency_count,
            (SELECT COUNT(*) FROM emergencies e WHERE e.location_id = l.id AND e.reported_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as recent_emergencies
        FROM locations l
        WHERE 1=1
    ";

    $params = [];

    // Add category filter
    if ($category) {
        $validCategories = ['academic', 'hostel', 'admin', 'recreational', 'medical', 'other'];
        if (!in_array($category, $validCategories)) {
            sendError('Invalid category', HTTP_BAD_REQUEST);
        }
        $baseQuery .= " AND l.category = ?";
        $params[] = $category;
    }

    // Add active filter (default to active only)
    if (!$include_inactive) {
        $baseQuery .= " AND l.is_active = 1";
    }

    // Add search filter
    if ($search) {
        $baseQuery .= " AND (l.name LIKE ? OR l.description LIKE ?)";
        $searchParam = '%' . $search . '%';
        $params[] = $searchParam;
        $params[] = $searchParam;
    }

    // Order by category and name
    $baseQuery .= " ORDER BY l.category ASC, l.name ASC";

    // Execute query
    $stmt = $pdo->prepare($baseQuery);
    $stmt->execute($params);
    $locations = $stmt->fetchAll();

    // Group locations by category
    $groupedLocations = [];
    $categories = [];

    foreach ($locations as $location) {
        $cat = $location['category'];

        if (!isset($groupedLocations[$cat])) {
            $groupedLocations[$cat] = [];
            $categories[$cat] = [
                'name' => ucfirst($cat),
                'count' => 0,
                'description' => getCategoryDescription($cat)
            ];
        }

        $groupedLocations[$cat][] = [
            'id' => $location['id'],
            'name' => $location['name'],
            'description' => $location['description'],
            'coordinates' => [
                'latitude' => (float)$location['latitude'],
                'longitude' => (float)$location['longitude']
            ],
            'is_active' => (bool)$location['is_active'],
            'emergency_count' => (int)$location['emergency_count'],
            'recent_emergencies' => (int)$location['recent_emergencies'],
            'created_at' => $location['created_at'],
            'updated_at' => $location['updated_at']
        ];

        $categories[$cat]['count']++;
    }

    // Get category statistics
    $categoryStats = [];
    foreach ($categories as $cat => $info) {
        $categoryStats[$cat] = [
            'name' => $info['name'],
            'description' => $info['description'],
            'location_count' => $info['count'],
            'total_emergencies' => array_sum(array_column($groupedLocations[$cat], 'emergency_count')),
            'recent_emergencies' => array_sum(array_column($groupedLocations[$cat], 'recent_emergencies'))
        ];
    }

    // Get overall statistics
    $totalLocations = count($locations);
    $activeLocations = count(array_filter($locations, fn($l) => $l['is_active']));
    $totalEmergencies = array_sum(array_column($locations, 'emergency_count'));
    $recentEmergencies = array_sum(array_column($locations, 'recent_emergencies'));

    // Find most active locations
    $mostActiveLocations = array_filter($locations, fn($l) => $l['recent_emergencies'] > 0);
    usort($mostActiveLocations, fn($a, $b) => $b['recent_emergencies'] <=> $a['recent_emergencies']);
    $mostActiveLocations = array_slice($mostActiveLocations, 0, 5);

    // Format response
    $response = [
        'locations_by_category' => $groupedLocations,
        'categories' => $categories,
        'category_statistics' => $categoryStats,
        'overall_statistics' => [
            'total_locations' => $totalLocations,
            'active_locations' => $activeLocations,
            'inactive_locations' => $totalLocations - $activeLocations,
            'total_emergencies' => $totalEmergencies,
            'recent_emergencies' => $recentEmergencies
        ],
        'most_active_locations' => array_map(function($location) {
            return [
                'id' => $location['id'],
                'name' => $location['name'],
                'category' => $location['category'],
                'recent_emergencies' => (int)$location['recent_emergencies'],
                'total_emergencies' => (int)$location['emergency_count']
            ];
        }, $mostActiveLocations),
        'filters_applied' => [
            'category' => $category,
            'include_inactive' => $include_inactive,
            'search' => $search
        ],
        'last_updated' => date('Y-m-d H:i:s')
    ];

    // Store in cache (for production)
    // setCache($cacheKey, $response, 3600); // 1 hour

    sendResponse(true, 'Campus locations retrieved successfully', $response, HTTP_OK);

} catch (Exception $e) {
    logActivity("Get campus locations error: " . $e->getMessage(), "ERROR");
    sendError($e->getMessage(), HTTP_INTERNAL_SERVER_ERROR);
}

/**
 * Get category description
 */
function getCategoryDescription($category) {
    $descriptions = [
        'academic' => 'Academic buildings including lecture halls, laboratories, and faculty offices',
        'hostel' => 'Student accommodation and residential facilities',
        'admin' => 'Administrative offices and university management buildings',
        'recreational' => 'Sports facilities, common areas, and recreational spaces',
        'medical' => 'Health centers, clinics, and medical facilities',
        'other' => 'Other campus facilities and miscellaneous locations'
    ];

    return $descriptions[$category] ?? 'Uncategorized locations';
}
?>