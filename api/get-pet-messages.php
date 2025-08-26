<?php
header('Content-Type: application/json');
require_once '../includes/functions.php';
require_once '../includes/personalities.php';

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'You must be logged in to view messages.']);
    exit;
}

$user_id = $_SESSION['user_id'];
$messages = getPetMessagesForUser($user_id);

echo json_encode(['success' => true, 'messages' => $messages]);
