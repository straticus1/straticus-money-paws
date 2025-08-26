<?php
/**
 * Money Paws - List Pet on Marketplace API
 * Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>
 */

header('Content-Type: application/json');
require_once '../includes/functions.php';
require_once '../includes/marketplace.php';

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'You must be logged in to list pets.']);
    exit;
}

$user_id = $_SESSION['user_id'];
$pet_id = $_POST['pet_id'] ?? 0;
$price = $_POST['price'] ?? 0.0;

if (empty($pet_id) || empty($price)) {
    echo json_encode(['success' => false, 'message' => 'Pet ID and price are required.']);
    exit;
}

if (!is_numeric($price) || $price <= 0) {
    echo json_encode(['success' => false, 'message' => 'Price must be a positive number.']);
    exit;
}

$result = listPetOnMarketplace($user_id, (int)$pet_id, (float)$price);

echo json_encode($result);
