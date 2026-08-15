<?php
require_once '../includes/functions.php';
requireLogin();

header('Content-Type: application/json');

$userId = $_SESSION['user_id'];

// Set a time limit for the long poll, e.g., 25 seconds
set_time_limit(30);

// Continuously check for new notifications
for ($i = 0; $i < 25; $i++) {
    $notifications = getUnreadNotifications($userId);

    if (!empty($notifications)) {
        // Mark notifications as delivered (but not necessarily read)
        // This is a simple approach. A more robust system might have a 'delivered' status.
        $notificationIds = array_column($notifications, 'id');
        $placeholders = implode(',', array_fill(0, count($notificationIds), '?'));
        
        // For this implementation, we'll just return them. The client can decide what to do.
        // A more advanced implementation might mark them as 'delivered' here.

        echo json_encode(['success' => true, 'notifications' => $notifications]);
        exit;
    }

    // Wait for 1 second before checking again
    sleep(1);
}

// If no notifications are found after the timeout, send an empty response
echo json_encode(['success' => true, 'notifications' => []]);
?>
