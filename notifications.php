<?php
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$notifications = getNotifications($userId);
markNotificationsAsRead($userId);

include 'includes/header.php';
?>

<main class="container">
    <h1>Notifications</h1>
    <div class="notifications-list">
        <?php if (empty($notifications)): ?>
            <p>You have no new notifications.</p>
        <?php else: ?>
            <?php foreach ($notifications as $notification): ?>
                <div class="notification-item <?php echo $notification['is_read'] ? '' : 'unread'; ?>">
                    <p>
                        <a href="/profile.php?id=<?php echo htmlspecialchars($notification['sender_user_id']); ?>">
                            <strong><?php echo htmlspecialchars($notification['sender_username']); ?></strong>
                        </a>
                        <?php 
                        switch ($notification['notification_type']) {
                            case 'like':
                                echo 'liked your pet ';
                                break;
                            case 'feed':
                                echo 'fed your pet ';
                                break;
                            case 'treat':
                                echo 'gave a treat to your pet ';
                                break;
                            case 'adoption':
                                echo 'adopted one of your pets: ';
                                break;
                            case 'new_message':
                                echo 'sent you a new message.';
                                break;
                        }
                        ?>
                        <?php if ($notification['pet_id']): ?>
                        <a href="/profile.php?id=<?php echo htmlspecialchars($notification['recipient_user_id']); ?>#pet-<?php echo htmlspecialchars($notification['pet_id']); ?>">
                            <strong><?php echo htmlspecialchars($notification['pet_name']); ?></strong>
                        </a>
                        <?php endif; ?>
                    </p>
                    <span class="notification-time"><?php echo time_ago($notification['created_at']); ?></span>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</main>

<?php include 'includes/footer.php'; ?>
