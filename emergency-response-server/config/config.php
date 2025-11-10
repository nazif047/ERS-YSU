<?php
/**
 * Main Configuration File
 * Yobe State University Emergency Response System
 */

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'emergency_response_system');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// JWT Configuration
define('JWT_SECRET_KEY', 'YSU_Emergency_Response_2024_Secret_Key_Change_In_Production');
define('JWT_ALGORITHM', 'HS256');
define('JWT_EXPIRE_TIME', 3600); // 1 hour in seconds
define('JWT_REFRESH_EXPIRE_TIME', 604800); // 7 days in seconds

// API Configuration
define('API_VERSION', '1.0');
define('API_BASE_URL', 'http://localhost/emergency-response-server/api');
define('CORS_ORIGIN', '*'); // Change to your frontend domain in production

// Security Configuration
define('PASSWORD_MIN_LENGTH', 8);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes in seconds

// File Upload Configuration
define('UPLOAD_MAX_SIZE', 5242880); // 5MB in bytes
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif']);
define('UPLOAD_PATH', __DIR__ . '/../uploads/');

// Emergency Response Configuration
define('EMERGENCY_AUTO_ASSIGN', true);
define('NOTIFICATION_ENABLED', true);
define('RESPONSE_TIMEOUT_MINUTES', 30);

// Logging Configuration
define('LOG_ENABLED', true);
define('LOG_FILE', __DIR__ . '/../logs/error.log');
define('ACCESS_LOG_FILE', __DIR__ . '/../logs/access.log');

// Email Configuration (for notifications)
define('SMTP_HOST', 'localhost');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', '');
define('SMTP_PASSWORD', '');
define('SMTP_FROM_EMAIL', 'noreply@ysu.edu.ng');
define('SMTP_FROM_NAME', 'YSU Emergency Response');

// University Specific Configuration
define('UNIVERSITY_NAME', 'Yobe State University');
define('UNIVERSITY_SHORT_NAME', 'YSU');
define('EMERGENCY_CONTACTS', [
    'security' => '08012345678',
    'health' => '08012345679',
    'fire' => '08012345680'
]);

// Rate Limiting Configuration
define('RATE_LIMIT_REQUESTS', 100); // requests per hour per IP
define('RATE_LIMIT_WINDOW', 3600); // 1 hour in seconds

// Error Reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', LOG_FILE);

// Timezone
date_default_timezone_set('Africa/Lagos');

// Response Headers
header('Content-Type: application/json; charset=UTF-8');
header('X-API-Version: ' . API_VERSION);

// CORS Headers
header('Access-Control-Allow-Origin: ' . CORS_ORIGIN);
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Credentials: true');

// Handle preflight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// Custom Error Handler
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    if (LOG_ENABLED) {
        $message = "[$errno] $errstr in $errfile on line $errline";
        error_log(date('Y-m-d H:i:s') . " - " . $message . "\n", 3, LOG_FILE);
    }

    if (ini_get('display_errors')) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Internal server error',
            'error' => $errstr
        ]);
        exit;
    }
});

// Custom Exception Handler
set_exception_handler(function($exception) {
    if (LOG_ENABLED) {
        error_log(date('Y-m-d H:i:s') . " - Uncaught exception: " . $exception->getMessage() . "\n", 3, LOG_FILE);
    }

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error',
        'error' => $exception->getMessage()
    ]);
});
?>