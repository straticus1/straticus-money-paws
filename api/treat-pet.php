<?php
/**
 * Money Paws - Cryptocurrency-Powered Pet Platform
 * Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>
 */
require_once '../includes/functions.php';
require_once '../includes/pet_care.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Please log in to give treats to pets.']);
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

// If no item specified, try to find a suitable treat item from user's inventory
if ($itemId <= 0) {
    $inventory = getUserInventory($_SESSION['user_id']);
    $treatItems = array_filter($inventory, function($item) {
        return $item['item_type'] === 'treat' && $item['quantity'] > 0;
    });
    
    if (empty($treatItems)) {
        echo json_encode(['success' => false, 'message' => 'You need treat items to give treats to pets. Visit the store to buy some!']);
        exit;
    }
    
    // Use the first available treat item
    $treatItem = reset($treatItems);
    $itemId = $treatItem['id'];
}

// Check if user has the item
$userItem = getUserInventoryItem($_SESSION['user_id'], $itemId);
if (!$userItem || $userItem['quantity'] <= 0) {
    echo json_encode(['success' => false, 'message' => 'You don\'t have this item in your inventory.']);
    exit;
}

// Check if item is treat
if ($userItem['item_type'] !== 'treat') {
    echo json_encode(['success' => false, 'message' => 'This item cannot be used as a treat for pets.']);
    exit;
}

// Give treat to the pet
$result = treatPetWithItem($_SESSION['user_id'], $petId, $itemId);

if ($result['success']) {
    // Get updated pet stats
    $updatedStats = getPetStats($petId);
    $happinessStatus = getPetHappinessStatus($updatedStats['happiness_level']);
    
    echo json_encode([
        'success' => true,
        'message' => $result['message'],
        'pet_stats' => $updatedStats,
        'happiness_status' => $happinessStatus,
        'remaining_quantity' => $result['remaining_quantity']
    ]);
} else {
    echo json_encode(['success' => false, 'message' => $result['message']]);
}
?>
