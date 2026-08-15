<?php
/**
 * Money Paws - List Item on Marketplace API
 * Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>
 */

header('Content-Type: application/json');
require_once '../includes/functions.php';
require_once '../includes/marketplace.php';

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'You must be logged in to list items.']);
    exit;
}

$user_id = $_SESSION['user_id'];
$item_id = $_POST['item_id'] ?? 0;
$quantity = $_POST['quantity'] ?? 0;
$price = $_POST['price'] ?? 0.0;

if (empty($item_id) || empty($quantity) || empty($price)) {
    echo json_encode(['success' => false, 'message' => 'Item ID, quantity, and price are required.']);
    exit;
}

if (!is_numeric($quantity) || $quantity <= 0 || !is_numeric($price) || $price <= 0) {
    echo json_encode(['success' => false, 'message' => 'Quantity and price must be positive numbers.']);
    exit;
}

$result = listItemOnMarketplace($user_id, (int)$item_id, (int)$quantity, (float)$price);

echo json_encode($result);
