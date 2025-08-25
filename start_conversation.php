<?php
require_once 'includes/functions.php';
requireLogin();

$recipientId = isset($_GET['id']) ? (int)$_GET['id'] : null;

// Prevent users from messaging themselves or if the ID is invalid
if (!$recipientId || $recipientId == $_SESSION['user_id']) {
    redirectTo('gallery.php');
}

// Ensure the recipient exists
$recipient = getUserById($recipientId);
if (!$recipient) {
    redirectTo('gallery.php');
}

$conversationId = getOrCreateConversation($_SESSION['user_id'], $recipientId);

if ($conversationId) {
    redirectTo('conversation.php?id=' . $conversationId);
} else {
    // Handle potential errors, though getOrCreateConversation should always return an ID
    // For now, redirect back to the user's profile with an error message (optional)
    redirectTo('profile.php?id=' . $recipientId);
}
?>
