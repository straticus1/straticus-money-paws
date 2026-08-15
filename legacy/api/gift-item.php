<?php
require_once '../includes/functions.php';
require_once '../includes/db.php';
require_once '../includes/quests.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'You must be logged in to send a gift.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$current_user_id = $_SESSION['user_id'];
$friend_id = isset($_POST['friend_id']) ? (int)$_POST['friend_id'] : 0;
$item_id = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
$quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;

if (empty($friend_id) || empty($item_id) || $quantity <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid data provided.']);
    exit;
}

$pdo = get_db();

try {
    $pdo->beginTransaction();

    // 1. Check if sender has enough of the item
    $stmt_check = $pdo->prepare("SELECT quantity FROM user_inventory WHERE user_id = :user_id AND item_id = :item_id");
    $stmt_check->execute(['user_id' => $current_user_id, 'item_id' => $item_id]);
    $sender_inventory = $stmt_check->fetch(PDO::FETCH_ASSOC);

    if (!$sender_inventory || $sender_inventory['quantity'] < $quantity) {
        echo json_encode(['success' => false, 'message' => 'You do not have enough of this item to gift.']);
        $pdo->rollBack();
        exit;
    }

    // 2. Decrement from sender's inventory
    $new_quantity = $sender_inventory['quantity'] - $quantity;
    if ($new_quantity > 0) {
        $stmt_update_sender = $pdo->prepare("UPDATE user_inventory SET quantity = :quantity WHERE user_id = :user_id AND item_id = :item_id");
        $stmt_update_sender->execute(['quantity' => $new_quantity, 'user_id' => $current_user_id, 'item_id' => $item_id]);
    } else {
        $stmt_delete_sender = $pdo->prepare("DELETE FROM user_inventory WHERE user_id = :user_id AND item_id = :item_id");
        $stmt_delete_sender->execute(['user_id' => $current_user_id, 'item_id' => $item_id]);
    }

    // 3. Add to receiver's inventory
    $stmt_check_receiver = $pdo->prepare("SELECT quantity FROM user_inventory WHERE user_id = :user_id AND item_id = :item_id");
    $stmt_check_receiver->execute(['user_id' => $friend_id, 'item_id' => $item_id]);
    $receiver_inventory = $stmt_check_receiver->fetch(PDO::FETCH_ASSOC);

    if ($receiver_inventory) {
        $stmt_update_receiver = $pdo->prepare("UPDATE user_inventory SET quantity = quantity + :quantity WHERE user_id = :user_id AND item_id = :item_id");
        $stmt_update_receiver->execute(['quantity' => $quantity, 'user_id' => $friend_id, 'item_id' => $item_id]);
    } else {
        $stmt_insert_receiver = $pdo->prepare("INSERT INTO user_inventory (user_id, item_id, quantity) VALUES (:user_id, :item_id, :quantity)");
        $stmt_insert_receiver->execute(['user_id' => $friend_id, 'item_id' => $item_id, 'quantity' => $quantity]);
    }
    
    // 4. Create a notification for the recipient
    $sender_username = $_SESSION['username']; // Assuming username is in session
    $stmt_item_name = $pdo->prepare("SELECT name FROM store_items WHERE id = :item_id");
    $stmt_item_name->execute(['item_id' => $item_id]);
    $item_name = $stmt_item_name->fetchColumn();

    $notification_message = htmlspecialchars($sender_username) . " sent you {$quantity}x " . htmlspecialchars($item_name) . "!";
    create_notification($friend_id, 'gift_received', $notification_message);

        $pdo->commit();

    // Update quest progress for sending a gift
    update_quest_progress($current_user_id, 'send_gift');

    echo json_encode(['success' => true, 'message' => 'Gift sent successfully!']);

} catch (PDOException $e) {
    $pdo->rollBack();
    // Log error in a real application
    echo json_encode(['success' => false, 'message' => 'A database error occurred.']);
}
