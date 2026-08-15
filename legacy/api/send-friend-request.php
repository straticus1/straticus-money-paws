<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

session_start();

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'You must be logged in to send a friend request.']);
    exit;
}

$sender_id = $_SESSION['user_id'];
$recipient_id = isset($_POST['recipient_id']) ? (int)$_POST['recipient_id'] : 0;

if ($sender_id === $recipient_id) {
    echo json_encode(['success' => false, 'message' => 'You cannot send a friend request to yourself.']);
    exit;
}

if ($recipient_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid recipient ID.']);
    exit;
}

$pdo = get_db();

// Check if a relationship already exists
$stmt = $pdo->prepare("SELECT * FROM user_friends WHERE (user_id_1 = ? AND user_id_2 = ?) OR (user_id_1 = ? AND user_id_2 = ?)");
$stmt->execute([$sender_id, $recipient_id, $recipient_id, $sender_id]);
$existing_friendship = $stmt->fetch();

if ($existing_friendship) {
    if ($existing_friendship['status'] === 'accepted') {
        echo json_encode(['success' => false, 'message' => 'You are already friends with this user.']);
    } elseif ($existing_friendship['status'] === 'pending') {
        echo json_encode(['success' => false, 'message' => 'A friend request is already pending.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Cannot send a friend request at this time.']);
    }
    exit;
}

// Create new friend request
$stmt = $pdo->prepare("INSERT INTO user_friends (user_id_1, user_id_2, status) VALUES (?, ?, 'pending')");
if ($stmt->execute([$sender_id, $recipient_id])) {
    // Create a notification for the recipient
    create_notification($recipient_id, 'friend_request', 'You have a new friend request.', 'friends.php');
    echo json_encode(['success' => true, 'message' => 'Friend request sent successfully.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to send friend request.']);
}

?>
