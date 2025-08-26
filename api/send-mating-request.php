<?php
/**
 * API: Send Mating Request
 * This endpoint allows a user to send a mating request to another user's pet.
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';

header('Content-Type: application/json');

session_start();

requireCSRFToken();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'You must be logged in to send a mating request.']);
    exit;
}

$user_id = $_SESSION['user_id'];
$requester_pet_id = $_POST['requester_pet_id'] ?? null;
$requested_pet_id = $_POST['requested_pet_id'] ?? null;

if (!$requester_pet_id || !$requested_pet_id) {
    echo json_encode(['success' => false, 'message' => 'Both pets must be specified.']);
    exit;
}

if ($requester_pet_id == $requested_pet_id) {
    echo json_encode(['success' => false, 'message' => 'A pet cannot be mated with itself.']);
    exit;
}

$pdo = get_db();

// Get pet details
$requester_pet = getPetById($requester_pet_id);
$requested_pet = getPetById($requested_pet_id);

// Validate ownership of the requesting pet
if (!$requester_pet || $requester_pet['user_id'] != $user_id) {
    echo json_encode(['success' => false, 'message' => 'You do not own the requesting pet.']);
    exit;
}

if (!$requested_pet) {
    echo json_encode(['success' => false, 'message' => 'The requested pet does not exist.']);
    exit;
}

// Validate genders are opposite
if ($requester_pet['gender'] == $requested_pet['gender']) {
    echo json_encode(['success' => false, 'message' => 'Pets must be of opposite genders to mate.']);
    exit;
}

// Validate age
$minimum_age = 18;
if (getPetAgeInPetDays($requester_pet['birth_date']) < $minimum_age || getPetAgeInPetDays($requested_pet['birth_date']) < $minimum_age) {
    echo json_encode(['success' => false, 'message' => 'Both pets must be at least ' . $minimum_age . ' pet days old.']);
    exit;
}

// Check for breeding cooldowns
if (getBreedingCooldown($requester_pet_id) || getBreedingCooldown($requested_pet_id)) {
    echo json_encode(['success' => false, 'message' => 'One or both pets are on a breeding cooldown.']);
    exit;
}

// Check for existing pending request
$stmt = $pdo->prepare('SELECT id FROM mating_requests WHERE ((requester_pet_id = ? AND requested_pet_id = ?) OR (requester_pet_id = ? AND requested_pet_id = ?)) AND status = \'pending\'');
$stmt->execute([$requester_pet_id, $requested_pet_id, $requested_pet_id, $requester_pet_id]);
if ($stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'A mating request between these pets is already pending.']);
    exit;
}

// Create the mating request
$stmt = $pdo->prepare('INSERT INTO mating_requests (requester_pet_id, requested_pet_id, requester_user_id, requested_user_id) VALUES (?, ?, ?, ?)');
$success = $stmt->execute([$requester_pet_id, $requested_pet_id, $user_id, $requested_pet['user_id']]);

if ($success) {
    $request_id = $pdo->lastInsertId();
    // Create a notification for the other user
    createNotification($requested_pet['user_id'], $user_id, $requester_pet_id, 'mating_request', null, $request_id);
    echo json_encode(['success' => true, 'message' => 'Mating request sent successfully!']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to send mating request.']);
}
