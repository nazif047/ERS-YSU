<?php
/**
 * API Information Endpoint
 * Yobe State University Emergency Response System
 */

header('Content-Type: application/json; charset=UTF-8');

$response = [
    'name' => 'Yobe State University Emergency Response System API',
    'version' => API_VERSION,
    'description' => 'Comprehensive emergency response system for campus safety',
    'status' => 'active',
    'timestamp' => date('Y-m-d H:i:s'),
    'timezone' => date_default_timezone_get(),
    'endpoints' => [
        'authentication' => [
            'POST /api/auth/login' => 'User login',
            'POST /api/auth/register' => 'User registration',
            'POST /api/auth/refresh' => 'Refresh access token',
            'GET /api/auth/profile' => 'Get user profile'
        ],
        'emergencies' => [
            'POST /api/emergencies/create' => 'Report emergency',
            'GET /api/emergencies' => 'Get emergency list',
            'PUT /api/emergencies/{id}/status' => 'Update emergency status',
            'GET /api/emergencies/types' => 'Get emergency types'
        ],
        'locations' => [
            'GET /api/locations' => 'Get campus locations',
            'POST /api/locations/add' => 'Add new location (admin only)',
            'PUT /api/locations/{id}' => 'Update location (admin only)'
        ],
        'admin' => [
            'GET /api/admins/dashboard' => 'Get admin dashboard data',
            'GET /api/admins/analytics' => 'Get analytics data'
        ],
        'notifications' => [
            'GET /api/notifications' => 'Get user notifications',
            'POST /api/notifications/send' => 'Send notification'
        ]
    ],
    'emergency_types' => [
        'health' => 'Medical emergencies and health-related incidents',
        'fire' => 'Fire outbreaks and fire safety hazards',
        'security' => 'Security threats and safety concerns'
    ],
    'user_roles' => [
        'student' => 'Regular student users',
        'staff' => 'University staff members',
        'security_admin' => 'Security department administrators',
        'health_admin' => 'Health center administrators',
        'fire_admin' => 'Fire safety administrators',
        'super_admin' => 'System administrators'
    ],
    'contact' => [
        'university' => UNIVERSITY_NAME,
        'emergency_contacts' => EMERGENCY_CONTACTS
    ],
    'documentation' => [
        'api_version' => API_VERSION,
        'base_url' => API_BASE_URL,
        'authentication' => 'JWT Bearer tokens',
        'rate_limit' => RATE_LIMIT_REQUESTS . ' requests per hour'
    ]
];

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
?>