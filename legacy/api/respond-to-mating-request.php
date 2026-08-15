<?php
session_start();
require_once '../includes/functions.php';
require_once '../includes/health.php';
require_once '../includes/genetics.php';

// 1. Security Checks
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /notifications.php');
    exit;
}

if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
    $_SESSION['flash_message'] = ['type' => 'danger', 'message' => 'Invalid CSRF token.'];
    header('Location: /notifications.php');
    exit;
}

// 2. Input validation
$user_id = $_SESSION['user_id'];
$request_id = filter_input(INPUT_POST, 'request_id', FILTER_VALIDATE_INT);
$action = $_POST['action'] ?? '';

if (!$request_id || !in_array($action, ['accept', 'reject'])) {
    $_SESSION['flash_message'] = ['type' => 'danger', 'message' => 'Invalid request data.'];
    header('Location: /notifications.php');
    exit;
}

$pdo = get_db();

// 3. Verify Mating Request
$stmt = $pdo->prepare('SELECT * FROM mating_requests WHERE id = ?');
$stmt->execute([$request_id]);
$request = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$request) {
    $_SESSION['flash_message'] = ['type' => 'danger', 'message' => 'Mating request not found.'];
    header('Location: /notifications.php');
    exit;
}

if ($request['requested_user_id'] != $user_id) {
    $_SESSION['flash_message'] = ['type' => 'danger', 'message' => 'You are not authorized to respond to this request.'];
    header('Location: /notifications.php');
    exit;
}

if ($request['status'] !== 'pending') {
    $_SESSION['flash_message'] = ['type' => 'info', 'message' => 'This mating request has already been responded to.'];
    header('Location: /notifications.php');
    exit;
}

// 4. Process Action
if ($action === 'reject') {
    $stmt = $pdo->prepare('UPDATE mating_requests SET status = ?, responded_at = CURRENT_TIMESTAMP WHERE id = ?');
    $stmt->execute(['rejected', $request_id]);

    createNotification($request['requester_user_id'], $user_id, $request['requested_pet_id'], 'mating_response', null, $request_id);

    $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Mating request rejected.'];
} elseif ($action === 'accept') {
    // --- Full Breeding Logic ---

    // 1. Get parent pets
    $requester_pet = getPetById($request['requester_pet_id']);
    $requested_pet = getPetById($request['requested_pet_id']);

    if (!$requester_pet || !$requested_pet) {
        $_SESSION['flash_message'] = ['type' => 'danger', 'message' => 'One of the pets involved in the request no longer exists.'];
        header('Location: /notifications.php');
        exit;
    }

    // 2. Determine mother and father (assuming male requests female)
    $mother_pet = ($requester_pet['gender'] == 'Female') ? $requester_pet : $requested_pet;
    $father_pet = ($requester_pet['gender'] == 'Male') ? $requester_pet : $requested_pet;

    if ($mother_pet['gender'] == $father_pet['gender']) {
         $_SESSION['flash_message'] = ['type' => 'danger', 'message' => 'Breeding requires pets of opposite genders.'];
         header('Location: /notifications.php');
         exit;
    }

    // 3. Final validation checks (age, cooldown)
    $minimum_age = 18; // In pet days
    if (getPetAgeInPetDays($mother_pet['birth_date']) < $minimum_age || getPetAgeInPetDays($father_pet['birth_date']) < $minimum_age) {
        $_SESSION['flash_message'] = ['type' => 'danger', 'message' => 'One or both pets are not old enough to breed.'];
        header('Location: /notifications.php');
        exit;
    }
    if (getBreedingCooldown($mother_pet['id']) > 0 || getBreedingCooldown($father_pet['id']) > 0) {
        $_SESSION['flash_message'] = ['type' => 'danger', 'message' => 'One or both pets are on a breeding cooldown.'];
        header('Location: /notifications.php');
        exit;
    }

    // 4. Breed pets and create offspring
    $offspring_dna = breed_pets($mother_pet['dna'], $father_pet['dna']);
    $new_pet_id = createBredPet($request['requester_user_id'], 'New Offspring', $offspring_dna, $mother_pet['id'], $father_pet['id']);

    if ($new_pet_id) {
        // 5. Update system state
        $pdo->prepare('UPDATE mating_requests SET status = ?, responded_at = CURRENT_TIMESTAMP WHERE id = ?')->execute(['accepted', $request_id]);

        // Set cooldowns (e.g., 24 hours)
        $cooldown_seconds = 86400;
        setBreedingCooldown($mother_pet['id'], $cooldown_seconds);
        setBreedingCooldown($father_pet['id'], $cooldown_seconds);

        // Boost happiness
        updatePetHappiness($mother_pet['id'], 20);
        updatePetHappiness($father_pet['id'], 20);

        // Initialize new pet's health and personality
        initializePetHealth($new_pet_id);
        assignInitialPersonalities($new_pet_id);

        // 6. Notify requester of success, linking the new pet
        createNotification($request['requester_user_id'], $user_id, $new_pet_id, 'mating_response', null, $request_id);

        $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Mating request accepted! A new pet has been born!'];
    } else {
        $_SESSION['flash_message'] = ['type' => 'danger', 'message' => 'An unexpected error occurred during breeding. Please try again.'];
    }
}

header('Location: /notifications.php');
exit;

