<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/quests.php';

header('Content-Type: application/json');

session_start();

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit;
}

$current_user_id = $_SESSION['user_id'];
$request_id = isset($_POST['request_id']) ? (int)$_POST['request_id'] : 0;
$action = isset($_POST['action']) ? $_POST['action'] : ''; // 'accept' or 'decline'

if ($request_id <= 0 || !in_array($action, ['accept', 'decline'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request data.']);
    exit;
}

$pdo = get_db();

// Verify the request exists and is pending for the current user
$stmt = $pdo->prepare("SELECT * FROM user_friends WHERE id = ? AND user_id_2 = ? AND status = 'pending'");
$stmt->execute([$request_id, $current_user_id]);
$request = $stmt->fetch();

if (!$request) {
    echo json_encode(['success' => false, 'message' => 'Friend request not found or you do not have permission to respond.']);
    exit;
}

if ($action === 'accept') {
    // Update the status to 'accepted'
    $stmt = $pdo->prepare("UPDATE user_friends SET status = 'accepted' WHERE id = ?");
    if ($stmt->execute([$request_id])) {
        // Increment friends_count for both users
                $pdo->exec("UPDATE users SET friends_count = friends_count + 1 WHERE id = {$request['user_id_1']} OR id = {$request['user_id_2']}");
        // Update quest progress for both users for adding a friend
        update_quest_progress($request['user_id_1'], 'add_friend');
        update_quest_progress($request['user_id_2'], 'add_friend');
        // Notify the original sender that their request was accepted
        create_notification($request['user_id_1'], 'friend_accept', 'Your friend request was accepted.', 'friends.php');
        echo json_encode(['success' => true, 'message' => 'Friend request accepted.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to accept friend request.']);
    }
} elseif ($action === 'decline') {
    // Delete the request from the table
    $stmt = $pdo->prepare("DELETE FROM user_friends WHERE id = ?");
    if ($stmt->execute([$request_id])) {
        echo json_encode(['success' => true, 'message' => 'Friend request declined.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to decline friend request.']);
    }
}
?>
