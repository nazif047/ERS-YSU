<?php
/**
 * User Registration Endpoint
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
        'email' => ['required' => true, 'email' => true],
        'password' => ['required' => true, 'min' => PASSWORD_MIN_LENGTH],
        'full_name' => ['required' => true, 'min' => 2, 'max' => 100],
        'school_id' => ['required' => false, 'school_id' => true],
        'phone' => ['required' => false, 'phone' => true],
        'department' => ['required' => false, 'max' => 100],
        'role' => ['required' => false, 'enum' => [ROLE_STUDENT, ROLE_STAFF], 'default' => ROLE_STUDENT]
    ];

    // Validate input
    $data = validationMiddleware($data, $rules);

    // Database connection
    $pdo = getDB();

    // Check if email already exists
    $emailCheck = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $emailCheck->execute([$data['email']]);
    if ($emailCheck->fetch()) {
        sendError(ERR_EMAIL_EXISTS, HTTP_CONFLICT);
    }

    // Check if school ID already exists (if provided)
    if (!empty($data['school_id'])) {
        $schoolIdCheck = $pdo->prepare("SELECT id FROM users WHERE school_id = ?");
        $schoolIdCheck->execute([$data['school_id']]);
        if ($schoolIdCheck->fetch()) {
            sendError(ERR_SCHOOL_ID_EXISTS, HTTP_CONFLICT);
        }
    }

    // Hash password
    $passwordHash = hashPassword($data['password']);

    // Prepare user data
    $userData = [
        'email' => $data['email'],
        'password_hash' => $passwordHash,
        'full_name' => $data['full_name'],
        'school_id' => $data['school_id'] ?? null,
        'phone' => $data['phone'] ?? null,
        'department' => $data['department'] ?? null,
        'role' => $data['role'] ?? ROLE_STUDENT,
        'is_active' => 1
    ];

    // Insert user
    $insertStmt = $pdo->prepare("
        INSERT INTO users (email, password_hash, full_name, school_id, phone, department, role, is_active, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");

    $insertStmt->execute([
        $userData['email'],
        $userData['password_hash'],
        $userData['full_name'],
        $userData['school_id'],
        $userData['phone'],
        $userData['department'],
        $userData['role'],
        $userData['is_active']
    ]);

    $userId = $pdo->lastInsertId();

    // Get created user data (without password)
    $userStmt = $pdo->prepare("
        SELECT id, email, full_name, school_id, phone, department, role, created_at
        FROM users WHERE id = ?
    ");
    $userStmt->execute([$userId]);
    $user = $userStmt->fetch();

    // Generate tokens for immediate login
    $accessToken = JWTHelper::generateToken($user);
    $refreshToken = JWTHelper::generateRefreshToken($user);

    // Prepare response data
    $response = [
        'user' => $user,
        'access_token' => $accessToken,
        'refresh_token' => $refreshToken,
        'token_type' => 'Bearer',
        'expires_in' => JWT_EXPIRE_TIME,
        'expires_at' => date('Y-m-d H:i:s', time() + JWT_EXPIRE_TIME)
    ];

    logActivity("New user registered: " . $user['email'], "INFO");

    sendResponse(true, MSG_REGISTER_SUCCESS, $response, HTTP_CREATED);

} catch (Exception $e) {
    logActivity("Registration error: " . $e->getMessage(), "ERROR");
    sendError('Registration failed. Please try again.', HTTP_INTERNAL_SERVER_ERROR);
}
?>