<?php
/**
 * Refresh Token Endpoint
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

    // Validation
    $rules = [
        'refresh_token' => ['required' => true]
    ];

    $data = validationMiddleware($data, $rules);

    // Database connection
    $pdo = getDB();

    // Refresh token and get new access token
    $newAccessToken = JWTHelper::refreshToken($data['refresh_token'], $pdo);

    // Prepare response
    $response = [
        'access_token' => $newAccessToken,
        'token_type' => 'Bearer',
        'expires_in' => JWT_EXPIRE_TIME,
        'expires_at' => date('Y-m-d H:i:s', time() + JWT_EXPIRE_TIME)
    ];

    logActivity("Access token refreshed", "INFO");

    sendResponse(true, 'Token refreshed successfully', $response, HTTP_OK);

} catch (Exception $e) {
    logActivity("Token refresh error: " . $e->getMessage(), "WARNING");
    sendError('Token refresh failed: ' . $e->getMessage(), HTTP_UNAUTHORIZED);
}
?>