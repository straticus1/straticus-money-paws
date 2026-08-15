<?php
/**
 * Money Paws - Get Adventure Quests API
 *
 * This API endpoint fetches the list of available adventure quests for a given pet,
 * based on the pet's level.
 *
 * Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>
 */

session_start();
require_once '../includes/adventures.php';
header('Content-Type: application/json');

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'You must be logged in to view quests.']);
    exit;
}

$pet_id = isset($_GET['pet_id']) ? (int)$_GET['pet_id'] : 0;
$user_id = $_SESSION['user_id'];

if ($pet_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'A valid pet ID is required.']);
    exit;
}

$pdo = get_db();

// Verify pet ownership
$stmt = $pdo->prepare("SELECT level, user_id FROM pets WHERE id = ?");
$stmt->execute([$pet_id]);
$pet = $stmt->fetch();

if (!$pet) {
    echo json_encode(['success' => false, 'message' => 'Pet not found.']);
    exit;
}

if ($pet['user_id'] != $user_id) {
    echo json_encode(['success' => false, 'message' => 'You do not own this pet.']);
    exit;
}

try {
    $quests = get_available_quests($pet['level']);
    echo json_encode(['success' => true, 'quests' => $quests]);
} catch (Exception $e) {
    // Log error properly in a real application
    echo json_encode(['success' => false, 'message' => 'An error occurred while fetching quests.']);
}
