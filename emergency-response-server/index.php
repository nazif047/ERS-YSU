<?php
/**
 * Main Entry Point
 * Yobe State University Emergency Response System API
 */

// Include configuration
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/constants.php';

// Include helpers
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/cors.php';

// Set security headers
securityHeadersMiddleware();

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Route handling
$request_uri = $_SERVER['REQUEST_URI'];
$request_method = $_SERVER['REQUEST_METHOD'];

// Remove query string from URI
$request_path = parse_url($request_uri, PHP_URL_PATH);
$request_path = rtrim($request_path, '/');

// API Routes
$routes = [
    // Health check
    'GET' => [
        '/' => 'health_check.php',
        '/api' => 'api_info.php',
        '/api/health' => 'health_check.php'
    ],

    // Authentication routes
    'POST' => [
        '/api/auth/login' => 'api/auth/login.php',
        '/api/auth/register' => 'api/auth/register.php',
        '/api/auth/refresh' => 'api/auth/refresh_token.php'
    ],

    // Profile routes
    'GET' => [
        '/api/auth/profile' => 'api/auth/profile.php'
    ],
    'PUT' => [
        '/api/auth/profile' => 'api/auth/update_profile.php'
    ],

    // Emergency routes
    'GET' => [
        '/api/emergencies' => 'api/emergencies/list.php',
        '/api/emergencies/types' => 'api/emergencies/types.php',
        '/api/emergencies/{id}' => 'api/emergencies/get_details.php',
        '/api/emergencies/user/{id}' => 'api/emergencies/get_user_emergencies.php'
    ],
    'POST' => [
        '/api/emergencies/create' => 'api/emergencies/create.php'
    ],
    'PUT' => [
        '/api/emergencies/{id}/status' => 'api/emergencies/update_status.php'
    ],

    // Location routes
    'GET' => [
        '/api/locations' => 'api/locations/get_locations.php',
        '/api/locations/{id}' => 'api/locations/get_location.php'
    ],
    'POST' => [
        '/api/locations/add' => 'api/locations/add_location.php'
    ],
    'PUT' => [
        '/api/locations/{id}' => 'api/locations/update_location.php'
    ],
    'DELETE' => [
        '/api/locations/{id}' => 'api/locations/delete_location.php'
    ],

    // Admin routes
    'GET' => [
        '/api/admins/dashboard' => 'api/admins/get_dashboard.php',
        '/api/admins/analytics' => 'api/admins/get_analytics.php',
        '/api/admins/users' => 'api/users/get_users.php'
    ],

    // Notification routes
    'GET' => [
        '/api/notifications' => 'api/notifications/get_notifications.php'
    ],
    'POST' => [
        '/api/notifications/send' => 'api/notifications/send_notification.php'
    ],
    'PUT' => [
        '/api/notifications/{id}/read' => 'api/notifications/mark_read.php'
    ]
];

// Handle route parameters
function handleRoute($route, $request_path, $request_method) {
    // Convert route pattern to regex
    $pattern = str_replace('{id}', '(\d+)', $route);
    $pattern = '#^' . $pattern . '$#';

    if (preg_match($pattern, $request_path, $matches)) {
        // Set route parameters as GET variables
        if (isset($matches[1])) {
            $_GET['id'] = $matches[1];
        }
        return $route;
    }
    return false;
}

// Find matching route
$handler_file = null;
if (isset($routes[$request_method])) {
    foreach ($routes[$request_method] as $route => $file) {
        if ($route === $request_path) {
            $handler_file = $file;
            break;
        } elseif (strpos($route, '{') !== false) {
            if (handleRoute($route, $request_path, $request_method)) {
                $handler_file = $file;
                break;
            }
        }
    }
}

// Handle 404
if (!$handler_file) {
    http_response_code(HTTP_NOT_FOUND);
    echo json_encode([
        'success' => false,
        'message' => 'Endpoint not found',
        'available_endpoints' => array_keys($routes[$request_method] ?? []),
        'request_path' => $request_path,
        'request_method' => $request_method
    ]);
    exit;
}

// Include the handler file
$full_path = __DIR__ . '/' . $handler_file;

if (file_exists($full_path)) {
    // Rate limiting
    if ($request_method !== 'GET' || !in_array($handler_file, ['api/locations/get_locations.php', 'api/emergencies/types.php'])) {
        rateLimitMiddleware();
    }

    include $full_path;
} else {
    http_response_code(HTTP_INTERNAL_SERVER_ERROR);
    echo json_encode([
        'success' => false,
        'message' => 'Handler file not found',
        'handler' => $handler_file
    ]);
}
?>