<?php
/**
 * Money Paws - Cryptocurrency-Powered Pet Platform
 * Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>
 */
require_once '../includes/functions.php';
require_once '../includes/pet_care.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Please log in to feed pets.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$petId = intval($_POST['pet_id'] ?? 0);
$itemId = intval($_POST['item_id'] ?? 0);

if ($petId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid pet ID.']);
    exit;
}

// Get pet details
$pet = getPetById($petId);
if (!$pet) {
    echo json_encode(['success' => false, 'message' => 'Pet not found.']);
    exit;
}

// If no item specified, try to find a suitable food item from user's inventory
if ($itemId <= 0) {
    $inventory = getUserInventory($_SESSION['user_id']);
    $foodItems = array_filter($inventory, function($item) {
        return $item['item_type'] === 'food' && $item['quantity'] > 0;
    });
    
    if (empty($foodItems)) {
        echo json_encode(['success' => false, 'message' => 'You need food items to feed pets. Visit the store to buy some!']);
        exit;
    }
    
    // Use the first available food item
    $foodItem = reset($foodItems);
    $itemId = $foodItem['id'];
}

// Check if user has the item
$userItem = getUserInventoryItem($_SESSION['user_id'], $itemId);
if (!$userItem || $userItem['quantity'] <= 0) {
    echo json_encode(['success' => false, 'message' => 'You don\'t have this item in your inventory.']);
    exit;
}

// Check if item is food
if ($userItem['item_type'] !== 'food') {
    echo json_encode(['success' => false, 'message' => 'This item cannot be used to feed pets.']);
    exit;
}

// Feed the pet
$result = feedPetWithItem($_SESSION['user_id'], $petId, $itemId);

if ($result['success']) {
    // Get updated pet stats
    $updatedStats = getPetStats($petId);
    $hungerStatus = getPetHungerStatus($updatedStats['hunger_level']);
    
    echo json_encode([
        'success' => true,
        'message' => $result['message'],
        'pet_stats' => $updatedStats,
        'hunger_status' => $hungerStatus,
        'remaining_quantity' => $result['remaining_quantity']
    ]);
} else {
    echo json_encode(['success' => false, 'message' => $result['message']]);
}
?>
