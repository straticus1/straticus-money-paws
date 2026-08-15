<?php
require_once '../includes/functions.php';
require_once '../includes/db.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'You must be logged in to perform this action.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$current_user_id = $_SESSION['user_id'];
$friend_id = isset($_POST['friend_id']) ? (int)$_POST['friend_id'] : 0;

if (empty($friend_id)) {
    echo json_encode(['success' => false, 'message' => 'Invalid friend ID.']);
    exit;
}

$pdo = get_db();

try {
    $pdo->beginTransaction();

    // Delete the friend relationship
    $stmt = $pdo->prepare("
        DELETE FROM user_friends
        WHERE (user_id_1 = :current_user_id AND user_id_2 = :friend_id)
           OR (user_id_1 = :friend_id AND user_id_2 = :current_user_id)
    ");
    $stmt->execute(['current_user_id' => $current_user_id, 'friend_id' => $friend_id]);

    // Decrement friends_count for both users
    $stmt_decrement = $pdo->prepare("
        UPDATE users
        SET friends_count = friends_count - 1
        WHERE id IN (:user1, :user2) AND friends_count > 0
    ");
    $stmt_decrement->execute(['user1' => $current_user_id, 'user2' => $friend_id]);

    $pdo->commit();

    echo json_encode(['success' => true, 'message' => 'Friend removed successfully.']);

} catch (PDOException $e) {
    $pdo->rollBack();
    // In a real app, you would log this error.
    echo json_encode(['success' => false, 'message' => 'An error occurred while removing the friend.']);
}
