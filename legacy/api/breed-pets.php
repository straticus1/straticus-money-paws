<?php
/**
 * Money Paws - Breed Pets API
 * Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>
 */

header('Content-Type: application/json');
require_once '../includes/functions.php';
require_once '../includes/security.php';
require_once '../includes/genetics.php';
require_once '../includes/personalities.php';
require_once '../includes/health.php';

requireCSRFToken();

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'You must be logged in to breed pets.']);
    exit;
}

$user_id = $_SESSION['user_id'];
$mother_id = $_POST['mother_id'] ?? 0;
$father_id = $_POST['father_id'] ?? 0;
$new_pet_name = $_POST['name'] ?? 'Unnamed Offspring';

if (empty($mother_id) || empty($father_id)) {
    echo json_encode(['success' => false, 'message' => 'Both parent pets must be selected.']);
    exit;
}

if ($mother_id === $father_id) {
    echo json_encode(['success' => false, 'message' => 'A pet cannot breed with itself.']);
    exit;
}

// Validate ownership and get pet data
$mother = getPetByIdAndOwner($mother_id, $user_id);
$father = getPetByIdAndOwner($father_id, $user_id);

if (!$mother || !$father) {
    echo json_encode(['success' => false, 'message' => 'You do not own one or both of the selected pets.']);
    exit;
}

// Check pet ages
$mother_age = getPetAgeInPetDays($mother['birth_date']);
$father_age = getPetAgeInPetDays($father['birth_date']);
$minimum_age = 18;

if ($mother_age < $minimum_age) {
    echo json_encode(['success' => false, 'message' => 'The mother is too young to breed. It must be at least ' . $minimum_age . ' pet days old.']);
    exit;
}

if ($father_age < $minimum_age) {
    echo json_encode(['success' => false, 'message' => 'The father is too young to breed. It must be at least ' . $minimum_age . ' pet days old.']);
    exit;
}

// Check for breeding cooldowns
$mother_cooldown = getBreedingCooldown($mother_id);
$father_cooldown = getBreedingCooldown($father_id);

if ($mother_cooldown) {
    echo json_encode(['success' => false, 'message' => 'The mother is still on a breeding cooldown.']);
    exit;
}

if ($father_cooldown) {
    echo json_encode(['success' => false, 'message' => 'The father is still on a breeding cooldown.']);
    exit;
}

// Generate DNA for parents if they don't have it (for legacy pets)
if (empty($mother['dna'])) {
    $mother['dna'] = generate_dna();
    $pdo = get_db();
    $stmt = $pdo->prepare("UPDATE pets SET dna = ? WHERE id = ?");
    $stmt->execute([$mother['dna'], $mother_id]);
}

if (empty($father['dna'])) {
    $father['dna'] = generate_dna();
    $pdo = get_db();
    $stmt = $pdo->prepare("UPDATE pets SET dna = ? WHERE id = ?");
    $stmt->execute([$father['dna'], $father_id]);
}

// Breed the pets
$offspring_dna = breed_pets($mother['dna'], $father['dna']);

// Create the new pet
$new_pet_id = createBredPet($user_id, $new_pet_name, $offspring_dna, $mother_id, $father_id);

if ($new_pet_id) {
    // Set cooldowns for both parents (e.g., 24 hours)
    $cooldown_seconds = 86400;
    setBreedingCooldown($mother_id, $cooldown_seconds);
        setBreedingCooldown($father_id, $cooldown_seconds);

    // Assign initial personalities
    assignInitialPersonalities($new_pet_id);

    // Initialize health stats
    initializePetHealth($new_pet_id);

    // Increase happiness of parents
    updatePetHappiness($mother_id, 20); // +20 happiness
    updatePetHappiness($father_id, 20); // +20 happiness

    echo json_encode(['success' => true, 'message' => 'Congratulations! You have a new pet!', 'new_pet_id' => $new_pet_id]);
} else {
    echo json_encode(['success' => false, 'message' => 'An error occurred while creating the new pet.']);
}
