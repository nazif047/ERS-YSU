<?php
/**
 * Get User Notifications Endpoint
 * Yobe State University Emergency Response System
 */

// Headers
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
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

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendError('Method not allowed', HTTP_METHOD_NOT_ALLOWED);
}

try {
    // Database connection
    $pdo = getDB();

    // Authenticate user
    $user = JWTHelper::authenticateUser($pdo);

    // Get query parameters
    $is_read = $_GET['is_read'] ?? null;
    $type = $_GET['type'] ?? null;
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(50, max(5, (int)($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;
    $mark_as_read = filter_var($_GET['mark_as_read'] ?? false, FILTER_VALIDATE_BOOLEAN);

    // Validate filters
    $validTypes = ['system', 'emergency', 'emergency_assigned', 'emergency_status_update', 'emergency_completed', 'announcement', 'warning', 'info'];
    if ($type && !in_array($type, $validTypes)) {
        sendError('Invalid notification type filter', HTTP_BAD_REQUEST);
    }

    if ($is_read !== null) {
        $is_read = filter_var($is_read, FILTER_VALIDATE_BOOLEAN);
    }

    // Start transaction for consistent read
    $pdo->beginTransaction();

    try {
        // Build base query
        $baseQuery = "
            FROM notifications n
            LEFT JOIN emergencies e ON n.emergency_id = e.id
            LEFT JOIN emergency_types et ON e.emergency_type_id = et.id
            LEFT JOIN users sender ON n.sender_id = sender.id
            WHERE n.user_id = ?
        ";

        $params = [$user['id']];

        // Add read status filter
        if ($is_read !== null) {
            $baseQuery .= " AND n.is_read = ?";
            $params[] = $is_read;
        }

        // Add type filter
        if ($type) {
            $baseQuery .= " AND n.type = ?";
            $params[] = $type;
        }

        // Get total count
        $countQuery = "SELECT COUNT(*) as total " . $baseQuery;
        $countStmt = $pdo->prepare($countQuery);
        $countStmt->execute($params);
        $totalRecords = (int)$countStmt->fetch()['total'];

        // Get notifications with pagination
        $notificationsQuery = "
            SELECT
                n.id,
                n.title,
                n.message,
                n.type,
                n.priority,
                n.is_read,
                n.created_at,
                n.read_at,
                e.id as emergency_id,
                e.status as emergency_status,
                e.severity as emergency_severity,
                et.name as emergency_type,
                et.department as emergency_department,
                et.icon as emergency_icon,
                et.color as emergency_color,
                l.name as emergency_location,
                sender.full_name as sender_name,
                sender.role as sender_role
            $baseQuery
            ORDER BY n.created_at DESC
            LIMIT ? OFFSET ?
        ";

        $notificationsStmt = $pdo->prepare($notificationsQuery);
        $params[] = $limit;
        $params[] = $offset;
        $notificationsStmt->execute($params);
        $notifications = $notificationsStmt->fetchAll();

        // Calculate pagination info
        $totalPages = ceil($totalRecords / $limit);
        $hasNextPage = $page < $totalPages;
        $hasPrevPage = $page > 1;

        // Get notification statistics
        $statsQuery = "
            SELECT
                n.type,
                n.priority,
                n.is_read,
                COUNT(*) as count
            FROM notifications n
            WHERE n.user_id = ?
        ";

        $statsParams = [$user['id']];

        if ($type) {
            $statsQuery .= " AND n.type = ?";
            $statsParams[] = $type;
        }

        $statsQuery .= " GROUP BY n.type, n.priority, n.is_read";
        $statsStmt = $pdo->prepare($statsQuery);
        $statsStmt->execute($statsParams);
        $rawStats = $statsStmt->fetchAll();

        // Process statistics
        $statistics = [
            'total_notifications' => $totalRecords,
            'unread_notifications' => 0,
            'by_type' => [],
            'by_priority' => ['low' => 0, 'medium' => 0, 'high' => 0, 'critical' => 0]
        ];

        foreach ($rawStats as $stat) {
            if (!$stat['is_read']) {
                $statistics['unread_notifications'] += (int)$stat['count'];
            }

            if (!isset($statistics['by_type'][$stat['type']])) {
                $statistics['by_type'][$stat['type']] = ['total' => 0, 'unread' => 0];
            }
            $statistics['by_type'][$stat['type']]['total'] += (int)$stat['count'];
            if (!$stat['is_read']) {
                $statistics['by_type'][$stat['type']]['unread'] += (int)$stat['count'];
            }

            $statistics['by_priority'][$stat['priority']] += (int)$stat['count'];
        }

        // Mark notifications as read if requested
        $markedAsRead = 0;
        if ($mark_as_read && !empty($notifications)) {
            $notificationIds = array_column($notifications, 'id');
            $placeholders = str_repeat('?,', count($notificationIds) - 1) . '?';

            $markReadStmt = $pdo->prepare("
                UPDATE notifications
                SET is_read = 1, read_at = NOW()
                WHERE id IN ($placeholders) AND user_id = ? AND is_read = 0
            ");

            $markParams = array_merge($notificationIds, [$user['id']]);
            $markReadStmt->execute($markParams);
            $markedAsRead = $markReadStmt->rowCount();
        }

        // Format notifications data
        $formattedNotifications = array_map(function($notification) {
            $formatted = [
                'id' => $notification['id'],
                'title' => $notification['title'],
                'message' => $notification['message'],
                'type' => $notification['type'],
                'priority' => $notification['priority'],
                'is_read' => (bool)$notification['is_read'],
                'created_at' => $notification['created_at'],
                'read_at' => $notification['read_at']
            ];

            // Add emergency information if available
            if ($notification['emergency_id']) {
                $formatted['emergency'] = [
                    'id' => $notification['emergency_id'],
                    'status' => $notification['emergency_status'],
                    'severity' => $notification['emergency_severity'],
                    'type' => [
                        'name' => $notification['emergency_type'],
                        'department' => $notification['emergency_department'],
                        'icon' => $notification['emergency_icon'],
                        'color' => $notification['emergency_color']
                    ],
                    'location' => $notification['emergency_location']
                ];
            }

            // Add sender information if available
            if ($notification['sender_name']) {
                $formatted['sender'] = [
                    'name' => $notification['sender_name'],
                    'role' => $notification['sender_role']
                ];
            }

            return $formatted;
        }, $notifications);

        // Commit transaction
        $pdo->commit();

        // Build response
        $response = [
            'notifications' => $formattedNotifications,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => $totalPages,
                'total_records' => $totalRecords,
                'records_per_page' => $limit,
                'has_next_page' => $hasNextPage,
                'has_prev_page' => $hasPrevPage
            ],
            'filters' => [
                'is_read' => $is_read,
                'type' => $type,
                'mark_as_read' => $mark_as_read
            ],
            'statistics' => $statistics,
            'actions_taken' => [
                'marked_as_read' => $markedAsRead
            ]
        ];

        // Log access
        logActivity(sprintf(
            "Notifications retrieved: %s (Page %d, %d records, %d unread)",
            $user['full_name'],
            $page,
            count($formattedNotifications),
            $statistics['unread_notifications']
        ), "INFO");

        sendResponse(true, 'Notifications retrieved successfully', $response, HTTP_OK);

    } catch (Exception $e) {
        // Rollback transaction
        $pdo->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    logActivity("Get notifications error: " . $e->getMessage(), "ERROR");
    sendError($e->getMessage(), HTTP_INTERNAL_SERVER_ERROR);
}
?>