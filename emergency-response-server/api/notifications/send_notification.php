<?php
/**
 * Send Notification Endpoint
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

    // Database connection
    $pdo = getDB();

    // Authenticate user - require admin privileges
    $user = JWTHelper::authenticateUser($pdo);

    // Check admin permissions
    if (!in_array($user['role'], ['super_admin', 'health_admin', 'fire_admin', 'security_admin'])) {
        sendError('Admin privileges required', HTTP_FORBIDDEN);
    }

    // Get request data
    $data = getRequestData();

    // Validation rules
    $rules = [
        'recipient_type' => ['required' => true, 'enum' => ['user', 'role', 'department', 'all']],
        'recipient_id' => ['required' => false, 'numeric'],
        'recipient_role' => ['required' => false, 'enum' => ['student', 'staff', 'health_admin', 'fire_admin', 'security_admin', 'super_admin']],
        'recipient_department' => ['required' => false, 'enum' => ['academic', 'admin', 'health', 'security', 'technical', 'other']],
        'title' => ['required' => true, 'min' => 3, 'max' => 100],
        'message' => ['required' => true, 'min' => 10, 'max' => 1000],
        'notification_type' => ['required' => false, 'enum' => ['system', 'emergency', 'announcement', 'warning', 'info']],
        'emergency_id' => ['required' => false, 'numeric'],
        'priority' => ['required' => false, 'enum' => ['low', 'medium', 'high', 'critical']],
        'send_email' => ['required' => false, 'boolean'],
        'send_sms' => ['required' => false, 'boolean']
    ];

    // Validate input
    $data = validationMiddleware($data, $rules);

    // Set default values
    $data['notification_type'] = $data['notification_type'] ?? 'info';
    $data['priority'] = $data['priority'] ?? 'medium';
    $data['send_email'] = $data['send_email'] ?? false;
    $data['send_sms'] = $data['send_sms'] ?? false;

    // Validate recipient-specific requirements
    if ($data['recipient_type'] === 'user' && !isset($data['recipient_id'])) {
        sendError('recipient_id is required for user notifications', HTTP_BAD_REQUEST);
    }

    if ($data['recipient_type'] === 'role' && !isset($data['recipient_role'])) {
        sendError('recipient_role is required for role notifications', HTTP_BAD_REQUEST);
    }

    if ($data['recipient_type'] === 'department' && !isset($data['recipient_department'])) {
        sendError('recipient_department is required for department notifications', HTTP_BAD_REQUEST);
    }

    // Validate emergency_id if provided
    if (isset($data['emergency_id'])) {
        $emergencyCheckStmt = $pdo->prepare("
            SELECT id FROM emergencies WHERE id = ?
        ");
        $emergencyCheckStmt->execute([$data['emergency_id']]);
        if (!$emergencyCheckStmt->fetch()) {
            sendError('Invalid emergency_id', HTTP_BAD_REQUEST);
        }
    }

    // Check department permissions for non-super admins
    $userDepartment = null;
    if ($user['role'] !== 'super_admin') {
        $userDepartment = str_replace('_admin', '', $user['role']);

        // Validate that the notification targets are within the admin's department
        if ($data['recipient_type'] === 'department' && $data['recipient_department'] !== $userDepartment) {
            sendError('Can only send notifications to your department', HTTP_FORBIDDEN);
        }

        if ($data['recipient_type'] === 'role' && str_contains($data['recipient_role'], 'admin') && $data['recipient_role'] !== $user['role']) {
            sendError('Can only send notifications to your admin role', HTTP_FORBIDDEN);
        }
    }

    // Start transaction
    $pdo->beginTransaction();

    try {
        $recipients = [];
        $notificationCount = 0;

        // Get recipients based on type
        switch ($data['recipient_type']) {
            case 'user':
                $userStmt = $pdo->prepare("
                    SELECT id, full_name, email, phone, is_active
                    FROM users
                    WHERE id = ? AND is_active = 1
                ");
                $userStmt->execute([$data['recipient_id']]);
                $userData = $userStmt->fetch();

                if (!$userData) {
                    $pdo->rollBack();
                    sendError('Recipient user not found or inactive', HTTP_NOT_FOUND);
                }

                $recipients = [$userData];
                break;

            case 'role':
                $roleQuery = "
                    SELECT id, full_name, email, phone, is_active
                    FROM users
                    WHERE role = ? AND is_active = 1
                ";
                $roleParams = [$data['recipient_role']];

                // Non-super admins can only send to their department or lower privilege roles
                if ($userDepartment && $user['role'] !== 'super_admin') {
                    $roleQuery .= " AND (department = ? OR role IN ('student', 'staff'))";
                    $roleParams[] = $userDepartment;
                }

                $roleStmt = $pdo->prepare($roleQuery);
                $roleStmt->execute($roleParams);
                $recipients = $roleStmt->fetchAll();
                break;

            case 'department':
                $deptQuery = "
                    SELECT id, full_name, email, phone, is_active
                    FROM users
                    WHERE department = ? AND is_active = 1
                ";
                $deptParams = [$data['recipient_department']];

                // Non-super admins can't send to other departments' admins
                if ($userDepartment && $user['role'] !== 'super_admin') {
                    $deptQuery .= " AND role NOT LIKE '%_admin'";
                }

                $deptStmt = $pdo->prepare($deptQuery);
                $deptStmt->execute($deptParams);
                $recipients = $deptStmt->fetchAll();
                break;

            case 'all':
                if ($user['role'] !== 'super_admin') {
                    $pdo->rollBack();
                    sendError('Only super admins can send notifications to all users', HTTP_FORBIDDEN);
                }

                $allStmt = $pdo->prepare("
                    SELECT id, full_name, email, phone, is_active
                    FROM users
                    WHERE is_active = 1
                ");
                $allStmt->execute();
                $recipients = $allStmt->fetchAll();
                break;
        }

        if (empty($recipients)) {
            $pdo->rollBack();
            sendError('No valid recipients found', HTTP_BAD_REQUEST);
        }

        // Prepare notification insert statement
        $notificationInsertStmt = $pdo->prepare("
            INSERT INTO notifications (user_id, emergency_id, title, message, type, priority, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");

        $emailsSent = 0;
        $smsSent = 0;

        // Create notifications for each recipient
        foreach ($recipients as $recipient) {
            $notificationInsertStmt->execute([
                $recipient['id'],
                $data['emergency_id'] ?? null,
                $data['title'],
                $data['message'],
                $data['notification_type'],
                $data['priority']
            ]);

            $notificationCount++;

            // Send email if requested and email is available
            if ($data['send_email'] && !empty($recipient['email'])) {
                if (sendEmailNotification($recipient['email'], $data['title'], $data['message'])) {
                    $emailsSent++;
                }
            }

            // Send SMS if requested and phone is available
            if ($data['send_sms'] && !empty($recipient['phone'])) {
                if (sendSMSNotification($recipient['phone'], $data['message'])) {
                    $smsSent++;
                }
            }
        }

        // Send push notifications (if implemented)
        $pushNotificationsSent = 0;
        if (NOTIFICATION_ENABLED) {
            foreach ($recipients as $recipient) {
                if (sendPushNotification($recipient['id'], $data['title'], $data['message'])) {
                    $pushNotificationsSent++;
                }
            }
        }

        // Log the notification sending
        logActivity(sprintf(
            "Bulk notification sent by %s (%s): %d recipients (%d emails, %d SMS, %d push)",
            $user['full_name'],
            $user['role'],
            $notificationCount,
            $emailsSent,
            $smsSent,
            $pushNotificationsSent
        ), "INFO");

        // Commit transaction
        $pdo->commit();

        // Format response
        $response = [
            'notification' => [
                'title' => $data['title'],
                'message' => $data['message'],
                'type' => $data['notification_type'],
                'priority' => $data['priority'],
                'emergency_id' => $data['emergency_id'] ?? null
            ],
            'delivery' => [
                'total_recipients' => $notificationCount,
                'in_app_notifications' => $notificationCount,
                'emails_sent' => $emailsSent,
                'sms_sent' => $smsSent,
                'push_notifications_sent' => $pushNotificationsSent
            ],
            'recipients' => [
                'type' => $data['recipient_type'],
                'count' => count($recipients)
            ],
            'sent_by' => [
                'id' => $user['id'],
                'name' => $user['full_name'],
                'role' => $user['role']
            ],
            'sent_at' => date('Y-m-d H:i:s')
        ];

        sendResponse(true, 'Notification sent successfully', $response, HTTP_CREATED);

    } catch (Exception $e) {
        // Rollback transaction
        $pdo->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    logActivity("Send notification error: " . $e->getMessage(), "ERROR");
    sendError($e->getMessage(), HTTP_INTERNAL_SERVER_ERROR);
}

/**
 * Send email notification (placeholder implementation)
 */
function sendEmailNotification($email, $title, $message) {
    // In a real implementation, this would use a service like PHPMailer, SendGrid, etc.
    // For now, we'll just log the email
    logActivity("Email notification sent to: $email - Title: $title", "INFO");
    return true;
}

/**
 * Send SMS notification (placeholder implementation)
 */
function sendSMSNotification($phone, $message) {
    // In a real implementation, this would use a service like Twilio, AWS SNS, etc.
    // For now, we'll just log the SMS
    logActivity("SMS notification sent to: $phone - Message: $message", "INFO");
    return true;
}

/**
 * Send push notification (placeholder implementation)
 */
function sendPushNotification($userId, $title, $message) {
    // In a real implementation, this would use Firebase Cloud Messaging or similar
    // For now, we'll just log the push notification
    logActivity("Push notification sent to user: $userId - Title: $title", "INFO");
    return true;
}
?>