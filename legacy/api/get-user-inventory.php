<?php
require_once '../includes/functions.php';
require_once '../includes/db.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'You must be logged in to view your inventory.']);
    exit;
}

$current_user_id = $_SESSION['user_id'];
$pdo = get_db();

$stmt = $pdo->prepare("
    SELECT si.id, si.name, ui.quantity
    FROM user_inventory ui
    JOIN store_items si ON ui.item_id = si.id
    WHERE ui.user_id = :user_id AND ui.quantity > 0
");

$stmt->execute(['user_id' => $current_user_id]);
$inventory = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['success' => true, 'inventory' => $inventory]);
