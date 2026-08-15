<?php
header('Content-Type: application/json');
require_once '../includes/functions.php';

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Not logged in.']);
    exit;
}

$user_id = $_SESSION['user_id'];
$pdo = get_db();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM pet_messages WHERE recipient_user_id = :user_id AND is_read = 0');
$stmt->execute(['user_id' => $user_id]);
$count = $stmt->fetchColumn();

echo json_encode(['success' => true, 'unread_count' => (int)$count]);
