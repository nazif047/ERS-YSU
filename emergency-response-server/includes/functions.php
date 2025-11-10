<?php
/**
 * Helper Functions
 * Yobe State University Emergency Response System
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/constants.php';

/**
 * Send JSON Response
 */
function sendResponse($success, $message, $data = null, $statusCode = HTTP_OK) {
    http_response_code($statusCode);

    $response = [
        'success' => $success,
        'message' => $message
    ];

    if ($data !== null) {
        $response['data'] = $data;
    }

    echo json_encode($response, JSON_PRETTY_PRINT);
    exit;
}

/**
 * Send Error Response
 */
function sendError($message, $statusCode = HTTP_BAD_REQUEST, $errorCode = null) {
    sendResponse(false, $message, $errorCode ? ['error_code' => $errorCode] : null, $statusCode);
}

/**
 * Validate Required Fields
 */
function validateRequired($data, $requiredFields) {
    $missing = [];

    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || trim($data[$field]) === '') {
            $missing[] = $field;
        }
    }

    if (!empty($missing)) {
        sendError('Missing required fields: ' . implode(', ', $missing), HTTP_BAD_REQUEST);
    }

    return true;
}

/**
 * Validate Email Format
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate Phone Number (Nigerian format)
 */
function validatePhone($phone) {
    return preg_match(PATTERN_PHONE, $phone);
}

/**
 * Validate School ID Format
 */
function validateSchoolId($schoolId) {
    return preg_match(PATTERN_SCHOOL_ID, $schoolId);
}

/**
 * Hash Password
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Verify Password
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Generate Random Token
 */
function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * Generate JWT Token
 */
function generateJWT($payload) {
    $header = json_encode(['typ' => 'JWT', 'alg' => JWT_ALGORITHM]);
    $payload = json_encode($payload);

    $headerEncoded = base64url_encode($header);
    $payloadEncoded = base64url_encode($payload);

    $signature = hash_hmac('sha256', $headerEncoded . "." . $payloadEncoded, JWT_SECRET_KEY, true);
    $signatureEncoded = base64url_encode($signature);

    return $headerEncoded . "." . $payloadEncoded . "." . $signatureEncoded;
}

/**
 * Verify JWT Token
 */
function verifyJWT($token) {
    if (!$token) {
        return false;
    }

    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        return false;
    }

    list($headerEncoded, $payloadEncoded, $signatureEncoded) = $parts;

    $header = json_decode(base64url_decode($headerEncoded), true);
    $payload = json_decode(base64url_decode($payloadEncoded), true);
    $signature = base64url_decode($signatureEncoded);

    // Verify signature
    $expectedSignature = hash_hmac('sha256', $headerEncoded . "." . $payloadEncoded, JWT_SECRET_KEY, true);
    if (!hash_equals($expectedSignature, $signature)) {
        return false;
    }

    // Check expiration
    if (isset($payload['exp']) && $payload['exp'] < time()) {
        return false;
    }

    return $payload;
}

/**
 * Base64 URL Encode
 */
function base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

/**
 * Base64 URL Decode
 */
function base64url_decode($data) {
    return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
}

/**
 * Get Current User from JWT
 */
function getCurrentUser($pdo) {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';

    if (!$authHeader || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        sendError('Authorization token required', HTTP_UNAUTHORIZED);
    }

    $token = $matches[1];
    $payload = verifyJWT($token);

    if (!$payload) {
        sendError(ERR_INVALID_TOKEN, HTTP_UNAUTHORIZED);
    }

    // Get user from database
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND is_active = 1");
    $stmt->execute([$payload['user_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        sendError(ERR_USER_NOT_FOUND, HTTP_UNAUTHORIZED);
    }

    // Update last login
    $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
    $updateStmt->execute([$payload['user_id']]);

    return $user;
}

/**
 * Check User Role
 */
function checkRole($userRole, $allowedRoles) {
    if (!in_array($userRole, $allowedRoles)) {
        sendError(ERR_ACCESS_DENIED, HTTP_FORBIDDEN);
    }
}

/**
 * Sanitize Input
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Get Request Method
 */
function getRequestMethod() {
    return $_SERVER['REQUEST_METHOD'];
}

/**
 * Get Request Data
 */
function getRequestData() {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        sendError('Invalid JSON data', HTTP_BAD_REQUEST);
    }

    return sanitizeInput($data);
}

/**
 * Log Activity
 */
function logActivity($message, $level = 'INFO') {
    if (!LOG_ENABLED) {
        return;
    }

    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

    $logEntry = "[$timestamp] [$level] IP: $ip - $message - User-Agent: $userAgent\n";
    error_log($logEntry, 3, LOG_FILE);
}

/**
 * Send Email Notification (Basic Implementation)
 */
function sendEmail($to, $subject, $message) {
    $headers = [
        'From: ' . SMTP_FROM_NAME . ' <' . SMTP_FROM_EMAIL . '>',
        'Reply-To: ' . SMTP_FROM_EMAIL,
        'Content-Type: text/html; charset=UTF-8'
    ];

    return mail($to, $subject, $message, implode("\r\n", $headers));
}

/**
 * Format Date for Display
 */
function formatDate($date, $format = 'Y-m-d H:i:s') {
    if (!$date) {
        return 'N/A';
    }

    $dateTime = new DateTime($date);
    return $dateTime->format($format);
}

/**
 * Calculate Time Difference
 */
function timeAgo($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;

    if ($diff < TIME_MINUTE) {
        return 'Just now';
    } elseif ($diff < TIME_HOUR) {
        return floor($diff / TIME_MINUTE) . ' minutes ago';
    } elseif ($diff < TIME_DAY) {
        return floor($diff / TIME_HOUR) . ' hours ago';
    } elseif ($diff < TIME_WEEK) {
        return floor($diff / TIME_DAY) . ' days ago';
    } else {
        return formatDate($datetime, 'M d, Y');
    }
}

/**
 * Pagination Helper
 */
function getPaginationParams($defaultLimit = DEFAULT_PAGE_SIZE) {
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = min(MAX_PAGE_SIZE, max(1, intval($_GET['limit'] ?? $defaultLimit)));
    $offset = ($page - 1) * $limit;

    return [
        'page' => $page,
        'limit' => $limit,
        'offset' => $offset
    ];
}

/**
 * Generate Pagination Response
 */
function generatePaginationResponse($total, $page, $limit, $data) {
    $totalPages = ceil($total / $limit);

    return [
        'data' => $data,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_records' => $total,
            'per_page' => $limit,
            'has_next' => $page < $totalPages,
            'has_prev' => $page > 1
        ]
    ];
}

/**
 * Rate Limiting Check
 */
function checkRateLimit($identifier = null) {
    $identifier = $identifier ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $key = 'rate_limit_' . md5($identifier);

    // Simple file-based rate limiting (for production, use Redis or database)
    $rateFile = sys_get_temp_dir() . '/' . $key;
    $currentTime = time();
    $windowStart = $currentTime - RATE_LIMIT_WINDOW;

    $attempts = [];
    if (file_exists($rateFile)) {
        $attempts = json_decode(file_get_contents($rateFile), true) ?: [];
    }

    // Clean old attempts
    $attempts = array_filter($attempts, function($time) use ($windowStart) {
        return $time > $windowStart;
    });

    // Check limit
    if (count($attempts) >= RATE_LIMIT_REQUESTS) {
        sendError(ERR_RATE_LIMIT_EXCEEDED, HTTP_TOO_MANY_REQUESTS);
    }

    // Add current attempt
    $attempts[] = $currentTime;
    file_put_contents($rateFile, json_encode($attempts));

    return true;
}

/**
 * Debug Function (remove in production)
 */
function debug($data, $die = false) {
    echo '<pre>';
    print_r($data);
    echo '</pre>';

    if ($die) {
        die();
    }
}
?>