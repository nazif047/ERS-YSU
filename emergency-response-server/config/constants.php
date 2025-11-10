<?php
/**
 * Application Constants
 * Yobe State University Emergency Response System
 */

// HTTP Response Codes
define('HTTP_OK', 200);
define('HTTP_CREATED', 201);
define('HTTP_BAD_REQUEST', 400);
define('HTTP_UNAUTHORIZED', 401);
define('HTTP_FORBIDDEN', 403);
define('HTTP_NOT_FOUND', 404);
define('HTTP_METHOD_NOT_ALLOWED', 405);
define('HTTP_CONFLICT', 409);
define('HTTP_UNPROCESSABLE_ENTITY', 422);
define('HTTP_TOO_MANY_REQUESTS', 429);
define('HTTP_INTERNAL_SERVER_ERROR', 500);
define('HTTP_SERVICE_UNAVAILABLE', 503);

// User Roles
define('ROLE_STUDENT', 'student');
define('ROLE_STAFF', 'staff');
define('ROLE_SECURITY_ADMIN', 'security_admin');
define('ROLE_HEALTH_ADMIN', 'health_admin');
define('ROLE_FIRE_ADMIN', 'fire_admin');
define('ROLE_SUPER_ADMIN', 'super_admin');

// Emergency Status
define('STATUS_PENDING', 'pending');
define('STATUS_IN_PROGRESS', 'in_progress');
define('STATUS_RESOLVED', 'resolved');
define('STATUS_CLOSED', 'closed');

// Emergency Severity
define('SEVERITY_LOW', 'low');
define('SEVERITY_MEDIUM', 'medium');
define('SEVERITY_HIGH', 'high');
define('SEVERITY_CRITICAL', 'critical');

// Emergency Departments
define('DEPT_HEALTH', 'health');
define('DEPT_FIRE', 'fire');
define('DEPT_SECURITY', 'security');

// Location Categories
define('CAT_ACADEMIC', 'academic');
define('CAT_HOSTEL', 'hostel');
define('CAT_ADMIN', 'admin');
define('CAT_RECREATIONAL', 'recreational');
define('CAT_MEDICAL', 'medical');
define('CAT_OTHER', 'other');

// Notification Types
define('NOTIF_EMERGENCY_ASSIGNED', 'emergency_assigned');
define('NOTIF_STATUS_UPDATE', 'status_update');
define('NOTIF_EMERGENCY_RESOLVED', 'emergency_resolved');
define('NOTIF_SYSTEM', 'system');

// Error Messages
define('ERR_INVALID_CREDENTIALS', 'Invalid email or password');
define('ERR_USER_NOT_FOUND', 'User not found');
define('ERR_INACTIVE_ACCOUNT', 'Account is inactive');
define('ERR_ACCESS_DENIED', 'Access denied');
define('ERR_INVALID_TOKEN', 'Invalid or expired token');
define('ERR_EMAIL_EXISTS', 'Email already exists');
define('ERR_SCHOOL_ID_EXISTS', 'School ID already exists');
define('ERR_WEAK_PASSWORD', 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters');
define('ERR_TOO_MANY_ATTEMPTS', 'Too many login attempts. Please try again later');
define('ERR_MISSING_FIELDS', 'Required fields are missing');
define('ERR_INVALID_EMAIL', 'Invalid email format');
define('ERR_EMERGENCY_NOT_FOUND', 'Emergency not found');
define('ERR_LOCATION_NOT_FOUND', 'Location not found');
define('ERR_PERMISSION_DENIED', 'You do not have permission to perform this action');
define('ERR_SERVER_ERROR', 'Internal server error');
define('ERR_METHOD_NOT_ALLOWED', 'Method not allowed');
define('ERR_RATE_LIMIT_EXCEEDED', 'Rate limit exceeded. Please try again later');

// Success Messages
define('MSG_LOGIN_SUCCESS', 'Login successful');
define('MSG_LOGOUT_SUCCESS', 'Logout successful');
define('MSG_REGISTER_SUCCESS', 'Registration successful');
define('MSG_PROFILE_UPDATED', 'Profile updated successfully');
define('MSG_PASSWORD_CHANGED', 'Password changed successfully');
define('MSG_EMERGENCY_REPORTED', 'Emergency reported successfully');
define('MSG_EMERGENCY_UPDATED', 'Emergency status updated successfully');
define('MSG_LOCATION_ADDED', 'Location added successfully');
define('MSG_LOCATION_UPDATED', 'Location updated successfully');
define('MSG_NOTIFICATION_SENT', 'Notification sent successfully');

// Emergency Type Icons and Colors
$EMERGENCY_ICONS = [
    'Medical Emergency' => '🏥',
    'Injury/Accident' => '🩹',
    'Fainting/Collapse' => '😵',
    'Allergic Reaction' => '🤧',
    'Chest Pain' => '💔',
    'Poisoning' => '☠️',
    'Mental Health Crisis' => '🧠',
    'Fire Outbreak' => '🔥',
    'Fire Alarm' => '🚨',
    'Burning Smell' => '👃',
    'Electrical Fire' => '⚡',
    'Gas Leak' => '💨',
    'Explosion' => '💥',
    'Security Threat' => '⚠️',
    'Theft/Robbery' => '🔫',
    'Assault/Fight' => '🥊',
    'Suspicious Person' => '👤',
    'Vandalism' => '🔨',
    'Harassment' => '🚫',
    'Unauthorized Access' => '🚷',
    'Vehicle Accident' => '🚗'
];

$EMERGENCY_COLORS = [
    'health' => '#4CD964',
    'fire' => '#FF9500',
    'security' => '#007AFF'
];

// Location Categories with Icons
$LOCATION_ICONS = [
    'academic' => '🎓',
    'hostel' => '🏠',
    'admin' => '🏢',
    'recreational' => '🎮',
    'medical' => '🏥',
    'other' => '📍'
];

// Validation Patterns
define('PATTERN_EMAIL', '/^[^\s@]+@[^\s@]+\.[^\s@]+$/');
define('PATTERN_PHONE', '/^0[789][01]\d{8}$/'); // Nigerian phone format
define('PATTERN_SCHOOL_ID', '/^YSU\/\d{4}\/\d{4}$/'); // YSU/YYYY/XXXX format

// File Upload MIME Types
define('MIME_JPG', 'image/jpeg');
define('MIME_PNG', 'image/png');
define('MIME_GIF', 'image/gif');

// Time Constants
define('TIME_MINUTE', 60);
define('TIME_HOUR', 3600);
define('TIME_DAY', 86400);
define('TIME_WEEK', 604800);

// Pagination
define('DEFAULT_PAGE_SIZE', 20);
define('MAX_PAGE_SIZE', 100);

// Cache Duration (in seconds)
define('CACHE_LOCATIONS', 3600); // 1 hour
define('CACHE_EMERGENCY_TYPES', 3600); // 1 hour
define('CACHE_USER_PROFILE', 300); // 5 minutes
?>