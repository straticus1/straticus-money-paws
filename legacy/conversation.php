<?php
require_once 'includes/functions.php';
requireLogin();

$conversationId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$conversationId) {
    redirectTo('messages.php');
}

// Handle sending a new message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['body'])) {
    requireCSRFToken();
    $body = trim($_POST['body']);
    $recipientId = isset($_POST['recipient_id']) ? (int)$_POST['recipient_id'] : null;
    if ($body && $recipientId) {
        sendMessage($conversationId, $_SESSION['user_id'], $recipientId, $body);
        redirectTo('conversation.php?id=' . $conversationId);
    }
}

$messages = getMessagesForConversation($conversationId, $_SESSION['user_id']);

// Determine the other user in the conversation
$otherUser = null;
if (!empty($messages)) {
    $firstMessage = $messages[0];
    $otherUserId = ($firstMessage['sender_id'] == $_SESSION['user_id']) ? $firstMessage['recipient_id'] : $firstMessage['sender_id'];
    $otherUser = getUserById($otherUserId);
}

$pageTitle = 'Conversation with ' . ($otherUser ? htmlspecialchars($otherUser['name']) : 'User');
include 'includes/header.php';
?>

<main class="container">
    <h1><?php echo $pageTitle; ?></h1>

    <div class="message-thread">
        <?php foreach ($messages as $message): ?>
            <div class="message-bubble <?php echo ($message['sender_id'] == $_SESSION['user_id']) ? 'sent' : 'received'; ?>">
                <p><?php echo nl2br(htmlspecialchars($message['body'])); ?></p>
                <span class="message-time"><?php echo time_ago($message['created_at']); ?></span>
            </div>
        <?php endforeach; ?>
    </div>

        <form action="conversation.php?id=<?php echo htmlspecialchars($conversationId); ?>" method="POST" class="message-form">
                <?php echo getCSRFTokenField(); ?>
                <input type="hidden" name="recipient_id" value="<?php echo $otherUser ? htmlspecialchars($otherUser['id']) : ''; ?>">
        <textarea name="body" placeholder="Type your message..." required></textarea>
        <button type="submit" class="btn">Send</button>
    </form>
</main>

<?php include 'includes/footer.php'; ?>
