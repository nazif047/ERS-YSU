<?php
/**
 * Main Configuration File
 * Yobe State University Emergency Response System
 */

// Environment Detection
define('ENVIRONMENT', $_ENV['APP_ENV'] ?? 'development'); // development, staging, production

// Database Configuration
define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'emergency_response_system');
define('DB_USER', $_ENV['DB_USER'] ?? 'root');
define('DB_PASS', $_ENV['DB_PASSWORD'] ?? '');
define('DB_CHARSET', 'utf8mb4');
define('DB_PORT', $_ENV['DB_PORT'] ?? '3306');

// JWT Configuration
define('JWT_SECRET_KEY', $_ENV['JWT_SECRET'] ?? 'YSU_Emergency_Response_2024_Secret_Key_Change_In_Production');
define('JWT_ALGORITHM', 'HS256');
define('JWT_EXPIRE_TIME', 3600); // 1 hour in seconds
define('JWT_REFRESH_EXPIRE_TIME', 604800); // 7 days in seconds
define('JWT_ISSUER', 'YSU-Emergency-Response');
define('JWT_AUDIENCE', 'YSU-Users');

// API Configuration
define('API_VERSION', '1.0');
define('API_BASE_URL', $_ENV['API_BASE_URL'] ?? 'http://localhost/emergency-response-server/api');
define('CORS_ORIGIN', ENVIRONMENT === 'production' ? 'https://yourdomain.com' : '*');
define('API_RATE_LIMIT', true);
define('API_THROTTLE_REQUESTS', 100); // requests per hour per IP
define('API_THROTTLE_WINDOW', 3600); // 1 hour in seconds

// Security Configuration
define('PASSWORD_MIN_LENGTH', 8);
define('PASSWORD_REQUIRE_UPPERCASE', true);
define('PASSWORD_REQUIRE_LOWERCASE', true);
define('PASSWORD_REQUIRE_NUMBER', true);
define('PASSWORD_REQUIRE_SPECIAL', true);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes in seconds
define('SESSION_TIMEOUT', 7200); // 2 hours in seconds
define('CSRF_PROTECTION', true);
define('ENABLE_HTTPS', ENVIRONMENT === 'production');

// File Upload Configuration
define('UPLOAD_MAX_SIZE', 5242880); // 5MB in bytes
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('UPLOAD_BASE_URL', $_ENV['UPLOAD_BASE_URL'] ?? 'http://localhost/emergency-response-server/uploads/');
define('IMAGE_COMPRESSION_QUALITY', 85);
define('THUMBNAIL_SIZE', 300); // pixels

// Emergency Response Configuration
define('EMERGENCY_AUTO_ASSIGN', true);
define('NOTIFICATION_ENABLED', true);
define('RESPONSE_TIMEOUT_MINUTES', 30);
define('ESCALATION_TIMEOUT_MINUTES', 60);
define('MAX_EMERGENCY_DESCRIPTION_LENGTH', 1000);
define('MIN_EMERGENCY_DESCRIPTION_LENGTH', 10);
define('PANIC_BUTTON_COOLDOWN_SECONDS', 30);

// Push Notification Configuration
define('FCM_SERVER_KEY', $_ENV['FCM_SERVER_KEY'] ?? '');
define('FCM_SENDER_ID', $_ENV['FCM_SENDER_ID'] ?? '');
define('PUSH_NOTIFICATION_ENABLED', !empty(FCM_SERVER_KEY));

// SMS Configuration
define('SMS_ENABLED', false);
define('SMS_PROVIDER', $_ENV['SMS_PROVIDER'] ?? 'twilio');
define('TWILIO_ACCOUNT_SID', $_ENV['TWILIO_ACCOUNT_SID'] ?? '');
define('TWILIO_AUTH_TOKEN', $_ENV['TWILIO_AUTH_TOKEN'] ?? '');
define('TWILIO_PHONE_NUMBER', $_ENV['TWILIO_PHONE_NUMBER'] ?? '');

// Email Configuration (for notifications)
define('EMAIL_ENABLED', true);
define('SMTP_HOST', $_ENV['SMTP_HOST'] ?? 'localhost');
define('SMTP_PORT', $_ENV['SMTP_PORT'] ?? 587);
define('SMTP_USERNAME', $_ENV['SMTP_USERNAME'] ?? '');
define('SMTP_PASSWORD', $_ENV['SMTP_PASSWORD'] ?? '');
define('SMTP_ENCRYPTION', $_ENV['SMTP_ENCRYPTION'] ?? 'tls'); // tls or ssl
define('SMTP_FROM_EMAIL', $_ENV['SMTP_FROM_EMAIL'] ?? 'noreply@ysu.edu.ng');
define('SMTP_FROM_NAME', 'YSU Emergency Response System');

// University Specific Configuration
define('UNIVERSITY_NAME', 'Yobe State University');
define('UNIVERSITY_SHORT_NAME', 'YSU');
define('UNIVERSITY_ADDRESS', 'Damaturu, Yobe State, Nigeria');
define('UNIVERSITY_PHONE', '+234-XXX-XXXX-XXXX');
define('UNIVERSITY_EMAIL', 'info@ysu.edu.ng');

// Emergency Contacts
define('EMERGENCY_CONTACTS', [
    'security' => [
        'phone' => $_ENV['SECURITY_PHONE'] ?? '08012345678',
        'email' => $_ENV['SECURITY_EMAIL'] ?? 'security@ysu.edu.ng',
        'name' => 'Campus Security'
    ],
    'health' => [
        'phone' => $_ENV['HEALTH_PHONE'] ?? '08012345679',
        'email' => $_ENV['HEALTH_EMAIL'] ?? 'health@ysu.edu.ng',
        'name' => 'University Health Center'
    ],
    'fire' => [
        'phone' => $_ENV['FIRE_PHONE'] ?? '08012345680',
        'email' => $_ENV['FIRE_EMAIL'] ?? 'fire@ysu.edu.ng',
        'name' => 'Fire Safety Department'
    ]
]);

// Cache Configuration
define('CACHE_ENABLED', true);
define('CACHE_DRIVER', $_ENV['CACHE_DRIVER'] ?? 'file'); // file, redis, memcached
define('CACHE_PREFIX', 'ers_');
define('CACHE_DEFAULT_TTL', 3600); // 1 hour

// Redis Configuration (if using redis cache)
define('REDIS_HOST', $_ENV['REDIS_HOST'] ?? '127.0.0.1');
define('REDIS_PORT', $_ENV['REDIS_PORT'] ?? 6379);
define('REDIS_PASSWORD', $_ENV['REDIS_PASSWORD'] ?? null);
define('REDIS_DB', $_ENV['REDIS_DB'] ?? 0);

// Logging Configuration
define('LOG_ENABLED', true);
define('LOG_LEVEL', ENVIRONMENT === 'production' ? 'ERROR' : 'DEBUG'); // DEBUG, INFO, WARNING, ERROR
define('LOG_FILE', __DIR__ . '/../logs/error.log');
define('ACCESS_LOG_FILE', __DIR__ . '/../logs/access.log');
define('EMERGENCY_LOG_FILE', __DIR__ . '/../logs/emergency.log');
define('SECURITY_LOG_FILE', __DIR__ . '/../logs/security.log');
define('LOG_MAX_FILES', 30); // keep last 30 log files
define('LOG_MAX_SIZE', 10485760); // 10MB per log file

// Backup Configuration
define('BACKUP_ENABLED', true);
define('BACKUP_SCHEDULE', '0 2 * * *'); // daily at 2 AM (cron format)
define('BACKUP_RETENTION_DAYS', 30);
define('BACKUP_PATH', __DIR__ . '/../backups/');

// Monitoring Configuration
define('MONITORING_ENABLED', true);
define('HEALTH_CHECK_ENDPOINT', '/health');
define('METRICS_ENABLED', true);
define('METRICS_ENDPOINT', '/metrics');

// Development/Debug Configuration
define('DEBUG_MODE', ENVIRONMENT === 'development');
define('SHOW_ERRORS', DEBUG_MODE);
define('PROFILING_ENABLED', DEBUG_MODE);

// Timezone
date_default_timezone_set('Africa/Lagos');

// Security Headers
if (ENABLE_HTTPS && empty($_SERVER['HTTPS'])) {
    $redirect_url = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    header('Location: ' . $redirect_url);
    exit();
}

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

// API Headers
header('Content-Type: application/json; charset=UTF-8');
header('X-API-Version: ' . API_VERSION);
header('X-Powered-By: YSU Emergency Response System v' . API_VERSION);

// CORS Headers
header('Access-Control-Allow-Origin: ' . CORS_ORIGIN);
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-API-Key');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Max-Age: 86400'); // 24 hours

// Handle preflight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// Error Reporting Configuration
if (SHOW_ERRORS) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(E_ERROR | E_PARSE | E_CORE_ERROR | E_CORE_WARNING | E_COMPILE_ERROR | E_COMPILE_WARNING | E_USER_ERROR | E_USER_WARNING);
    ini_set('display_errors', 0);
}

ini_set('log_errors', 1);
ini_set('error_log', LOG_FILE);

// Custom Error Handler
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    if (LOG_ENABLED && LOG_LEVEL !== 'ERROR') {
        $message = "[$errno] $errstr in $errfile on line $errline";
        error_log(date('Y-m-d H:i:s') . " - " . $message . "\n", 3, LOG_FILE);
    }

    if (SHOW_ERRORS) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Internal server error',
            'error' => $errstr,
            'file' => $errfile,
            'line' => $errline
        ]);
        exit;
    }
});

// Custom Exception Handler
set_exception_handler(function($exception) {
    if (LOG_ENABLED) {
        error_log(date('Y-m-d H:i:s') . " - Uncaught exception: " . $exception->getMessage() . " in " . $exception->getFile() . " on line " . $exception->getLine() . "\n", 3, LOG_FILE);
    }

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error',
        'error' => SHOW_ERRORS ? $exception->getMessage() : 'An error occurred while processing your request'
    ]);
});

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