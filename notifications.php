<?php
require_once 'includes/functions.php';
require_once 'includes/security.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$notifications = getNotifications($userId);
markNotificationsAsRead($userId);
$csrf_token = generate_csrf_token(); // Ensure CSRF token is available

include 'includes/header.php';
?>

<main class="container">
    <h1>Notifications</h1>

    <?php if (isset($_SESSION['flash_message'])): ?>
        <div class="alert alert-<?php echo htmlspecialchars($_SESSION['flash_message']['type']); ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($_SESSION['flash_message']['message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['flash_message']); ?>
    <?php endif; ?>
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
                            case 'mating_request':
                                echo 'wants to mate with your pet ';
                                break;
                            case 'mating_response':
                                echo 'responded to your mating request for ';
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
                    <?php if ($notification['notification_type'] == 'mating_request' && $notification['request_status'] == 'pending'): ?>
                        <div class="mating-request-actions">
                            <form action="/api/respond-to-mating-request.php" method="post" style="display: inline;">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                <input type="hidden" name="request_id" value="<?php echo htmlspecialchars($notification['request_id']); ?>">
                                <input type="hidden" name="action" value="accept">
                                <button type="submit" class="btn btn-success btn-sm">Accept</button>
                            </form>
                            <form action="/api/respond-to-mating-request.php" method="post" style="display: inline;">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                <input type="hidden" name="request_id" value="<?php echo htmlspecialchars($notification['request_id']); ?>">
                                <input type="hidden" name="action" value="reject">
                                <button type="submit" class="btn btn-danger btn-sm">Reject</button>
                            </form>
                        </div>
                    <?php elseif ($notification['notification_type'] == 'mating_request' || $notification['notification_type'] == 'mating_response'): ?>
                        <div class="mating-request-status">
                             <p class="mb-0">Request status: <strong class="status-<?php echo htmlspecialchars($notification['request_status']); ?>"><?php echo htmlspecialchars(ucfirst($notification['request_status'])); ?></strong></p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</main>

<?php include 'includes/footer.php'; ?>
