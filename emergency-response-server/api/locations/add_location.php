<?php
/**
 * Add Campus Location Endpoint (Admin Only)
 * Yobe State University Emergency Response System
 */

// Headers
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
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

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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

    // Get request data
    $data = getRequestData();

    // Validation rules
    $rules = [
        'name' => ['required' => true, 'min' => 3, 'max' => 200],
        'category' => ['required' => true, 'enum' => [CAT_ACADEMIC, CAT_HOSTEL, CAT_ADMIN, CAT_RECREATIONAL, CAT_MEDICAL, CAT_OTHER]],
        'description' => ['required' => false, 'max' => 500],
        'latitude' => ['required' => false, 'pattern' => '/^-?\d{1,3}\.\d{1,8}$/'],
        'longitude' => ['required' => false, 'pattern' => '/^-?\d{1,4}\.\d{1,8}$/'],
        'is_active' => ['required' => false, 'default' => 1]
    ];

    // Validate input
    $data = validationMiddleware($data, $rules);

    // Check for duplicate location name
    $duplicateStmt = $pdo->prepare("
        SELECT id FROM locations
        WHERE name = ? AND is_active = 1
    ");
    $duplicateStmt->execute([$data['name']]);
    if ($duplicateStmt->fetch()) {
        sendError('Location with this name already exists', HTTP_CONFLICT);
    }

    // Prepare location data
    $locationData = [
        'name' => trim($data['name']),
        'category' => $data['category'],
        'description' => $data['description'] ?? null,
        'latitude' => $data['latitude'] ?? null,
        'longitude' => $data['longitude'] ?? null,
        'is_active' => $data['is_active'] ?? 1,
        'created_at' => date('Y-m-d H:i:s')
    ];

    // Insert location
    $insertStmt = $pdo->prepare("
        INSERT INTO locations (name, category, description, latitude, longitude, is_active, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    $insertStmt->execute([
        $locationData['name'],
        $locationData['category'],
        $locationData['description'],
        $locationData['latitude'],
        $locationData['longitude'],
        $locationData['is_active'],
        $locationData['created_at']
    ]);

    $locationId = $pdo->lastInsertId();

    // Get created location
    $locationStmt = $pdo->prepare("
        SELECT * FROM locations WHERE id = ?
    ");
    $locationStmt->execute([$locationId]);
    $newLocation = $locationStmt->fetch();

    // Prepare response
    $response = [
        'location' => $newLocation,
        'created_by' => [
            'id' => $user['id'],
            'name' => $user['full_name'],
            'role' => $user['role']
        ],
        'location_id' => $locationId,
        'created_at' => $locationData['created_at']
    ];

    // Log activity
    logActivity(sprintf(
        "New location added: %s (%s) by %s",
        $locationData['name'],
        $locationData['category'],
        $user['full_name']
    ), "INFO");

    sendResponse(true, MSG_LOCATION_ADDED, $response, HTTP_CREATED);

} catch (Exception $e) {
    logActivity("Location creation error: " . $e->getMessage(), "ERROR");
    sendError('Failed to create location', HTTP_INTERNAL_SERVER_ERROR);
}
?>