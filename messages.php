<?php
require_once 'includes/functions.php';
requireLogin();

$pageTitle = 'My Messages';
include 'includes/header.php';

$conversations = getConversationsForUser($_SESSION['user_id']);
?>

<main class="container">
    <h1>My Messages</h1>

    <div class="conversations-list">
        <?php if (empty($conversations)): ?>
            <p>You have no messages.</p>
        <?php else: ?>
            <?php foreach ($conversations as $convo): ?>
                                <a href="conversation.php?id=<?php echo htmlspecialchars($convo['id']); ?>" class="conversation-item <?php echo ($convo['unread_count'] > 0) ? 'unread' : ''; ?>">
                    <img src="<?php echo htmlspecialchars($convo['other_user_avatar'] ?? '/assets/images/default-avatar.png'); ?>" alt="" class="avatar">
                    <div class="convo-details">
                        <div class="convo-header">
                            <strong><?php echo htmlspecialchars($convo['other_user_name']); ?></strong>
                            <span class="convo-time"><?php echo $convo['last_message_time'] ? time_ago($convo['last_message_time']) : ''; ?></span>
                        </div>
                        <p class="last-message">
                            <?php echo htmlspecialchars($convo['last_message']); ?>
                        </p>
                    </div>
                    <?php if ($convo['unread_count'] > 0): ?>
                        <span class="unread-badge"><?php echo $convo['unread_count']; ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</main>

<?php include 'includes/footer.php'; ?>
