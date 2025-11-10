<?php
/**
 * Update User Profile Endpoint
 * Yobe State University Emergency Response System
 */

// Headers
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: PUT, PATCH, OPTIONS');
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

// Only allow PUT and PATCH requests
if (!in_array($_SERVER['REQUEST_METHOD'], ['PUT', 'PATCH'])) {
    sendError('Method not allowed', HTTP_METHOD_NOT_ALLOWED);
}

try {
    // Rate limiting
    rateLimitMiddleware();

    // Database connection
    $pdo = getDB();

    // Authenticate user
    $user = JWTHelper::authenticateUser($pdo);

    // Get request data
    $data = getRequestData();

    // Validation rules
    $rules = [
        'full_name' => ['required' => false, 'min' => 2, 'max' => 100],
        'phone' => ['required' => false, 'pattern' => '/^[\+]?[0-9]{10,15}$/'],
        'department' => ['required' => false, 'max' => 50],
        'current_password' => ['required' => false, 'min' => 6],
        'new_password' => ['required' => false, 'min' => 8],
        'confirm_password' => ['required' => false]
    ];

    // Validate input
    $data = validationMiddleware($data, $rules);

    // Check if at least one field is being updated
    if (empty($data)) {
        sendError('At least one field must be provided for update', HTTP_BAD_REQUEST);
    }

    // Start transaction
    $pdo->beginTransaction();

    try {
        // Get current user data
        $currentStmt = $pdo->prepare("
            SELECT * FROM users WHERE id = ?
        ");
        $currentStmt->execute([$user['id']]);
        $currentUser = $currentStmt->fetch();

        if (!$currentUser) {
            $pdo->rollBack();
            sendError('User not found', HTTP_NOT_FOUND);
        }

        // Handle password change
        if (isset($data['new_password'])) {
            // Verify current password
            if (!isset($data['current_password'])) {
                $pdo->rollBack();
                sendError('Current password is required to change password', HTTP_BAD_REQUEST);
            }

            if (!password_verify($data['current_password'], $currentUser['password_hash'])) {
                $pdo->rollBack();
                sendError('Current password is incorrect', HTTP_UNAUTHORIZED);
            }

            // Check password confirmation
            if (!isset($data['confirm_password']) || $data['new_password'] !== $data['confirm_password']) {
                $pdo->rollBack();
                sendError('New password and confirmation do not match', HTTP_BAD_REQUEST);
            }

            // Validate password strength
            if (!isStrongPassword($data['new_password'])) {
                $pdo->rollBack();
                sendError('New password does not meet security requirements', HTTP_BAD_REQUEST);
            }

            // Hash new password
            $data['password_hash'] = password_hash($data['new_password'], PASSWORD_DEFAULT);

            // Remove password fields from data array for the main update
            unset($data['current_password'], $data['new_password'], $data['confirm_password']);
        }

        // Validate phone number uniqueness if being updated
        if (isset($data['phone']) && $data['phone'] !== $currentUser['phone']) {
            $phoneCheckStmt = $pdo->prepare("
                SELECT id FROM users WHERE phone = ? AND id != ?
            ");
            $phoneCheckStmt->execute([$data['phone'], $user['id']]);
            if ($phoneCheckStmt->fetch()) {
                $pdo->rollBack();
                sendError('Phone number is already in use', HTTP_CONFLICT);
            }
        }

        // Validate department if being updated
        if (isset($data['department'])) {
            $validDepartments = ['academic', 'admin', 'health', 'security', 'technical', 'other'];
            if (!in_array($data['department'], $validDepartments)) {
                $pdo->rollBack();
                sendError('Invalid department', HTTP_BAD_REQUEST);
            }
        }

        // Remove fields that shouldn't be updated directly
        $restrictedFields = ['email', 'school_id', 'role', 'is_active', 'email_verified_at', 'phone_verified_at'];
        foreach ($restrictedFields as $field) {
            unset($data[$field]);
        }

        // Build dynamic update query
        $updateFields = [];
        $updateParams = [];

        foreach ($data as $field => $value) {
            $updateFields[] = "$field = ?";
            $updateParams[] = $value;
        }

        $updateParams[] = $user['id'];

        $updateQuery = "
            UPDATE users
            SET " . implode(', ', $updateFields) . ", updated_at = NOW()
            WHERE id = ?
        ";

        $updateStmt = $pdo->prepare($updateQuery);
        $result = $updateStmt->execute($updateParams);

        if (!$result) {
            throw new Exception('Failed to update profile');
        }

        // Get updated user data
        $updatedStmt = $pdo->prepare("
            SELECT
                id, full_name, email, phone, school_id, department, role,
                is_active, created_at, updated_at, last_login,
                email_verified_at, phone_verified_at
            FROM users
            WHERE id = ?
        ");
        $updatedStmt->execute([$user['id']]);
        $updatedUser = $updatedStmt->fetch();

        // Log the change (without sensitive data)
        $changes = [];
        foreach ($data as $field => $newValue) {
            if ($field !== 'password_hash') { // Don't log password changes
                $oldValue = $currentUser[$field];
                if ($oldValue != $newValue) {
                    $changes[] = "$field updated";
                }
            }

            if ($field === 'password_hash') {
                $changes[] = "password changed";
            }
        }

        logActivity(sprintf(
            "Profile updated: %s (%s). Changes: %s",
            $updatedUser['full_name'],
            $updatedUser['role'],
            implode(', ', $changes)
        ), "INFO");

        // Commit transaction
        $pdo->commit();

        // Format response
        $response = [
            'user' => [
                'id' => $updatedUser['id'],
                'full_name' => $updatedUser['full_name'],
                'email' => $updatedUser['email'],
                'phone' => $updatedUser['phone'],
                'school_id' => $updatedUser['school_id'],
                'department' => $updatedUser['department'],
                'role' => $updatedUser['role'],
                'is_active' => (bool)$updatedUser['is_active'],
                'verification_status' => [
                    'email_verified' => !is_null($updatedUser['email_verified_at']),
                    'phone_verified' => !is_null($updatedUser['phone_verified_at'])
                ],
                'timestamps' => [
                    'created_at' => $updatedUser['created_at'],
                    'updated_at' => $updatedUser['updated_at'],
                    'last_login' => $updatedUser['last_login']
                ]
            ],
            'changes_made' => $changes,
            'message' => 'Profile updated successfully'
        ];

        sendResponse(true, 'Profile updated successfully', $response, HTTP_OK);

    } catch (Exception $e) {
        // Rollback transaction
        $pdo->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    logActivity("Profile update error: " . $e->getMessage(), "ERROR");
    sendError($e->getMessage(), HTTP_INTERNAL_SERVER_ERROR);
}

/**
 * Check password strength
 */
function isStrongPassword($password) {
    // At least 8 characters
    if (strlen($password) < 8) {
        return false;
    }

    // Contains at least one uppercase letter
    if (!preg_match('/[A-Z]/', $password)) {
        return false;
    }

    // Contains at least one lowercase letter
    if (!preg_match('/[a-z]/', $password)) {
        return false;
    }

    // Contains at least one number
    if (!preg_match('/[0-9]/', $password)) {
        return false;
    }

    // Contains at least one special character
    if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
        return false;
    }

    return true;
}
?>