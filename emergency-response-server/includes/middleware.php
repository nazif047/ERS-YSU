<?php
/**
 * Middleware Functions
 * Yobe State University Emergency Response System
 */

require_once __DIR__ . '/functions.php';

/**
 * CORS Middleware
 */
function corsMiddleware() {
    // CORS headers are already set in config.php
    return true;
}

/**
 * Authentication Middleware
 */
function authMiddleware($pdo) {
    return getCurrentUser($pdo);
}

/**
 * Role-based Access Control Middleware
 */
function roleMiddleware($user, $allowedRoles) {
    checkRole($user['role'], $allowedRoles);
    return $user;
}

/**
 * Rate Limiting Middleware
 */
function rateLimitMiddleware() {
    checkRateLimit();
}

/**
 * Input Validation Middleware
 */
function validationMiddleware($data, $rules) {
    $errors = [];

    foreach ($rules as $field => $rule) {
        $value = $data[$field] ?? null;

        if (isset($rule['required']) && $rule['required'] && (empty($value) || $value === '')) {
            $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
            continue;
        }

        if (!empty($value)) {
            if (isset($rule['min']) && strlen($value) < $rule['min']) {
                $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' must be at least ' . $rule['min'] . ' characters';
            }

            if (isset($rule['max']) && strlen($value) > $rule['max']) {
                $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' must not exceed ' . $rule['max'] . ' characters';
            }

            if (isset($rule['pattern']) && !preg_match($rule['pattern'], $value)) {
                $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' format is invalid';
            }

            if (isset($rule['email']) && !validateEmail($value)) {
                $errors[$field] = 'Invalid email format';
            }

            if (isset($rule['phone']) && !validatePhone($value)) {
                $errors[$field] = 'Invalid phone number format';
            }

            if (isset($rule['school_id']) && !validateSchoolId($value)) {
                $errors[$field] = 'Invalid school ID format (YSU/YYYY/XXXX)';
            }

            if (isset($rule['enum']) && !in_array($value, $rule['enum'])) {
                $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' must be one of: ' . implode(', ', $rule['enum']);
            }
        }
    }

    if (!empty($errors)) {
        sendError('Validation failed', HTTP_UNPROCESSABLE_ENTITY, $errors);
    }

    return $data;
}

/**
 * File Upload Middleware
 */
function fileUploadMiddleware($file, $allowedTypes = ALLOWED_IMAGE_TYPES, $maxSize = UPLOAD_MAX_SIZE) {
    if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        sendError('File upload failed', HTTP_BAD_REQUEST);
    }

    // Check file size
    if ($file['size'] > $maxSize) {
        sendError('File size exceeds maximum allowed size', HTTP_BAD_REQUEST);
    }

    // Check file type
    $fileInfo = pathinfo($file['name']);
    $extension = strtolower($fileInfo['extension']);

    if (!in_array($extension, $allowedTypes)) {
        sendError('File type not allowed', HTTP_BAD_REQUEST);
    }

    // Generate unique filename
    $filename = uniqid() . '.' . $extension;
    $uploadPath = UPLOAD_PATH . $filename;

    // Create upload directory if it doesn't exist
    if (!is_dir(UPLOAD_PATH)) {
        mkdir(UPLOAD_PATH, 0755, true);
    }

    // Move file
    if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
        sendError('Failed to save uploaded file', HTTP_INTERNAL_SERVER_ERROR);
    }

    return [
        'filename' => $filename,
        'path' => $uploadPath,
        'size' => $file['size'],
        'type' => $file['type']
    ];
}

/**
 * Database Transaction Middleware
 */
function transactionMiddleware($pdo, $callback) {
    try {
        $pdo->beginTransaction();
        $result = $callback($pdo);
        $pdo->commit();
        return $result;
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Logging Middleware
 */
function loggingMiddleware($message, $level = 'INFO') {
    logActivity($message, $level);
}

/**
 * API Version Middleware
 */
function versionMiddleware($requiredVersion = API_VERSION) {
    $requestVersion = $_SERVER['HTTP_X_API_VERSION'] ?? $_GET['version'] ?? API_VERSION;

    if (version_compare($requestVersion, $requiredVersion, '<')) {
        sendError('API version not supported', HTTP_BAD_REQUEST);
    }

    return $requestVersion;
}

/**
 * IP Whitelist Middleware (for admin endpoints)
 */
function ipWhitelistMiddleware($allowedIPs = []) {
    $clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    if (!empty($allowedIPs) && !in_array($clientIP, $allowedIPs)) {
        sendError('Access denied from this IP', HTTP_FORBIDDEN);
    }

    return $clientIP;
}

/**
 * Maintenance Mode Middleware
 */
function maintenanceMiddleware($isMaintenance = false) {
    if ($isMaintenance) {
        $user = getCurrentUser($GLOBALS['pdo']) ?? null;

        // Allow super admins during maintenance
        if (!$user || $user['role'] !== ROLE_SUPER_ADMIN) {
            http_response_code(HTTP_SERVICE_UNAVAILABLE);
            echo json_encode([
                'success' => false,
                'message' => 'System is under maintenance. Please try again later.',
                'maintenance_mode' => true
            ]);
            exit;
        }
    }
}

/**
 * Security Headers Middleware
 */
function securityHeadersMiddleware() {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Content-Security-Policy: default-src \'self\'');
}
?>