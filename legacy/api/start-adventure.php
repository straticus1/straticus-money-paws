<?php
/**
 * Money Paws - Start Adventure API
 *
 * This API endpoint allows a user to start an adventure for one of their pets.
 *
 * Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>
 */

session_start();
require_once '../includes/adventures.php';
header('Content-Type: application/json');

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'You must be logged in to start an adventure.']);
    exit;
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
$pet_id = isset($data['pet_id']) ? (int)$data['pet_id'] : 0;
$quest_id = isset($data['quest_id']) ? (int)$data['quest_id'] : 0;
$user_id = $_SESSION['user_id'];

if ($pet_id <= 0 || $quest_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'A valid pet ID and quest ID are required.']);
    exit;
}

try {
    $result = start_adventure($pet_id, $quest_id, $user_id);
    echo json_encode($result);
} catch (Exception $e) {
    // Log error properly in a real application
    echo json_encode(['success' => false, 'message' => 'An error occurred while starting the adventure.']);
}
