<?php
/**
 * CORS Configuration
 * Yobe State University Emergency Response System
 */

// Enable CORS for development
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-API-Version');
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400');
    header('Content-Length: 0');
    header('Content-Type: text/plain');
    exit(0);
}

// Set CORS headers for all requests
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-API-Version');
header('Access-Control-Allow-Credentials: true');

// CORS preflight cache
header('Access-Control-Max-Age: 86400');

// Handle CORS request validation
function validateCORS() {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $method = $_SERVER['REQUEST_METHOD'] ?? '';

    // Log CORS requests for debugging
    if ($origin) {
        logActivity("CORS Request from: $origin, Method: $method");
    }

    return true;
}
?>