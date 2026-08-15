<?php
header('Content-Type: application/json');
require_once '../includes/functions.php';
require_once '../includes/personalities.php';

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'You must be logged in.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$message_id = $data['message_id'] ?? null;

if (empty($message_id) || !is_numeric($message_id)) {
    echo json_encode(['success' => false, 'message' => 'Invalid message ID.']);
    exit;
}

$user_id = $_SESSION['user_id'];
$result = markPetMessageAsRead((int)$message_id, $user_id);

if ($result) {
    echo json_encode(['success' => true, 'message' => 'Message marked as read.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to mark message as read.']);
}
