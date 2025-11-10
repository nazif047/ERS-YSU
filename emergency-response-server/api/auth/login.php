<?php
/**
 * User Login Endpoint
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

    // Get request data
    $data = getRequestData();

    // Validation rules
    $rules = [
        'login' => ['required' => true],
        'password' => ['required' => true, 'min' => PASSWORD_MIN_LENGTH]
    ];

    // Validate input
    $data = validationMiddleware($data, $rules);

    // Database connection
    $pdo = getDB();

    // Find user by email or school_id
    $stmt = $pdo->prepare("
        SELECT * FROM users
        WHERE (email = ? OR school_id = ?)
        AND is_active = 1
    ");
    $stmt->execute([$data['login'], $data['login']]);
    $user = $stmt->fetch();

    if (!$user) {
        logActivity("Login attempt with invalid credentials: " . $data['login'], "WARNING");
        sendError(ERR_INVALID_CREDENTIALS, HTTP_UNAUTHORIZED);
    }

    // Verify password
    if (!verifyPassword($data['password'], $user['password_hash'])) {
        logActivity("Login attempt with wrong password for user: " . $user['email'], "WARNING");
        sendError(ERR_INVALID_CREDENTIALS, HTTP_UNAUTHORIZED);
    }

    // Generate tokens
    $accessToken = JWTHelper::generateToken($user);
    $refreshToken = JWTHelper::generateRefreshToken($user);

    // Update last login
    $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
    $updateStmt->execute([$user['id']]);

    // Remove sensitive data from response
    unset($user['password_hash']);

    // Prepare response data
    $response = [
        'user' => [
            'id' => $user['id'],
            'school_id' => $user['school_id'],
            'email' => $user['email'],
            'full_name' => $user['full_name'],
            'phone' => $user['phone'],
            'role' => $user['role'],
            'department' => $user['department'],
            'last_login' => $user['last_login']
        ],
        'access_token' => $accessToken,
        'refresh_token' => $refreshToken,
        'token_type' => 'Bearer',
        'expires_in' => JWT_EXPIRE_TIME,
        'expires_at' => date('Y-m-d H:i:s', time() + JWT_EXPIRE_TIME)
    ];

    logActivity("User logged in successfully: " . $user['email'], "INFO");

    sendResponse(true, MSG_LOGIN_SUCCESS, $response, HTTP_OK);

} catch (Exception $e) {
    logActivity("Login error: " . $e->getMessage(), "ERROR");
    sendError('Login failed. Please try again.', HTTP_INTERNAL_SERVER_ERROR);
}
?>