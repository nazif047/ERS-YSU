<?php
/**
 * Mark Notification as Read Endpoint
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
    // Database connection
    $pdo = getDB();

    // Authenticate user
    $user = JWTHelper::authenticateUser($pdo);

    // Get notification ID from URL parameter
    $notificationId = $_GET['notification_id'] ?? null;

    if (!$notificationId || !is_numeric($notificationId)) {
        sendError('Notification ID is required and must be numeric', HTTP_BAD_REQUEST);
    }

    // Get request data for bulk operations
    $data = getRequestData();
    $markAll = $data['mark_all'] ?? false;
    $notificationIds = $data['notification_ids'] ?? [];

    // Start transaction
    $pdo->beginTransaction();

    try {
        $markedCount = 0;

        if ($markAll) {
            // Mark all notifications as read for the user
            $markAllStmt = $pdo->prepare("
                UPDATE notifications
                SET is_read = 1, read_at = NOW()
                WHERE user_id = ? AND is_read = 0
            ");
            $markAllStmt->execute([$user['id']]);
            $markedCount = $markAllStmt->rowCount();

            // Log bulk action
            logActivity(sprintf(
                "All notifications marked as read: %s (%d notifications)",
                $user['full_name'],
                $markedCount
            ), "INFO");

        } elseif (!empty($notificationIds)) {
            // Mark specific notifications as read
            // Validate all notification IDs
            if (!is_array($notificationIds)) {
                $pdo->rollBack();
                sendError('notification_ids must be an array', HTTP_BAD_REQUEST);
            }

            if (count($notificationIds) > 100) {
                $pdo->rollBack();
                sendError('Cannot mark more than 100 notifications at once', HTTP_BAD_REQUEST);
            }

            // Verify all notifications belong to the user
            $placeholders = str_repeat('?,', count($notificationIds) - 1) . '?';
            $verifyStmt = $pdo->prepare("
                SELECT id FROM notifications
                WHERE id IN ($placeholders) AND user_id = ?
            ");
            $verifyParams = array_merge($notificationIds, [$user['id']]);
            $verifyStmt->execute($verifyParams);
            $validIds = $verifyStmt->fetchAll(PDO::FETCH_COLUMN);

            if (count($validIds) !== count($notificationIds)) {
                $pdo->rollBack();
                sendError('One or more notification IDs are invalid or do not belong to user', HTTP_BAD_REQUEST);
            }

            // Mark notifications as read
            $placeholders = str_repeat('?,', count($notificationIds) - 1) . '?';
            $markMultipleStmt = $pdo->prepare("
                UPDATE notifications
                SET is_read = 1, read_at = NOW()
                WHERE id IN ($placeholders) AND user_id = ? AND is_read = 0
            ");
            $markMultipleParams = array_merge($notificationIds, [$user['id']]);
            $markMultipleStmt->execute($markMultipleParams);
            $markedCount = $markMultipleStmt->rowCount();

            // Log bulk action
            logActivity(sprintf(
                "Multiple notifications marked as read: %s (%d notifications)",
                $user['full_name'],
                $markedCount
            ), "INFO");

        } else {
            // Mark single notification as read
            // Verify notification belongs to user
            $verifyStmt = $pdo->prepare("
                SELECT id, title, type, is_read FROM notifications
                WHERE id = ? AND user_id = ?
            ");
            $verifyStmt->execute([$notificationId, $user['id']]);
            $notification = $verifyStmt->fetch();

            if (!$notification) {
                $pdo->rollBack();
                sendError('Notification not found or access denied', HTTP_NOT_FOUND);
            }

            if ($notification['is_read']) {
                $pdo->rollBack();
                sendError('Notification is already marked as read', HTTP_BAD_REQUEST);
            }

            // Mark notification as read
            $markStmt = $pdo->prepare("
                UPDATE notifications
                SET is_read = 1, read_at = NOW()
                WHERE id = ? AND user_id = ?
            ");
            $result = $markStmt->execute([$notificationId, $user['id']]);

            if (!$result) {
                throw new Exception('Failed to mark notification as read');
            }

            $markedCount = 1;

            // Log single action
            logActivity(sprintf(
                "Notification marked as read: ID %s, Title '%s' by %s",
                $notificationId,
                $notification['title'],
                $user['full_name']
            ), "INFO");
        }

        // Get updated unread count
        $unreadCountStmt = $pdo->prepare("
            SELECT COUNT(*) as unread_count
            FROM notifications
            WHERE user_id = ? AND is_read = 0
        ");
        $unreadCountStmt->execute([$user['id']]);
        $unreadCount = (int)$unreadCountStmt->fetch()['unread_count'];

        // Commit transaction
        $pdo->commit();

        // Format response
        $response = [
            'marked_as_read' => $markedCount,
            'unread_count' => $unreadCount,
            'action_type' => $markAll ? 'all' : (!empty($notificationIds) ? 'bulk' : 'single'),
            'marked_at' => date('Y-m-d H:i:s')
        ];

        sendResponse(true, 'Notifications marked as read successfully', $response, HTTP_OK);

    } catch (Exception $e) {
        // Rollback transaction
        $pdo->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    logActivity("Mark notification as read error: " . $e->getMessage(), "ERROR");
    sendError($e->getMessage(), HTTP_INTERNAL_SERVER_ERROR);
}
?>