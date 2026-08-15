<?php
session_start();
require_once '../includes/functions.php';

// Security & validation
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isLoggedIn()) {
    header('Location: /index.php');
    exit;
}

if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
    $_SESSION['flash_message'] = ['type' => 'danger', 'message' => 'Invalid security token.'];
    header('Location: /index.php');
    exit;
}

$pet_id = filter_input(INPUT_POST, 'pet_id', FILTER_VALIDATE_INT);
$amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
$message = trim($_POST['message'] ?? '');

if (!$pet_id || !$amount || $amount <= 0) {
    $_SESSION['flash_message'] = ['type' => 'danger', 'message' => 'Invalid donation amount.'];
    header("Location: /pet.php?id={$pet_id}");
    exit;
}

$pdo = get_db();
$donor_id = $_SESSION['user_id'];

// Get pet details
$pet = getPetById($pet_id);

// Check if donation is possible
if (!$pet || $pet['life_status'] !== 'deceased' || !$pet['is_memorial_enabled']) {
    $_SESSION['flash_message'] = ['type' => 'warning', 'message' => 'This pet is not accepting donations at this time.'];
    header("Location: /pet.php?id={$pet_id}");
    exit;
}

if ($pet['user_id'] == $donor_id) {
    $_SESSION['flash_message'] = ['type' => 'warning', 'message' => 'You cannot donate to your own pet\'s memorial.'];
    header("Location: /pet.php?id={$pet_id}");
    exit;
}

$remaining_goal = $pet['donation_goal'] - $pet['donations_received'];
if ($amount > $remaining_goal) {
    $amount = $remaining_goal; // Cap donation to the remaining goal
}

if ($amount <= 0) {
    $_SESSION['flash_message'] = ['type' => 'info', 'message' => 'The donation goal for this memorial has been met. Thank you for your kindness.'];
    header("Location: /pet.php?id={$pet_id}");
    exit;
}

// Process donation (for now, we assume payment is successful)
$pdo->beginTransaction();
try {
    // 1. Record the donation
    $stmt = $pdo->prepare(
        'INSERT INTO pet_donations (pet_id, donor_user_id, amount_usd, message) VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$pet_id, $donor_id, $amount, $message]);
    $donation_id = $pdo->lastInsertId();

    // 2. Update the total donations received for the pet
    $stmt = $pdo->prepare('UPDATE pets SET donations_received = donations_received + ? WHERE id = ?');
    $stmt->execute([$amount, $pet_id]);

    // 3. Notify the pet owner
    // createNotification($pet['user_id'], $donor_id, $pet_id, 'donation', $donation_id);

    $pdo->commit();
    $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Thank you for your generous donation!'];
} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['flash_message'] = ['type' => 'danger', 'message' => 'There was an error processing your donation. Please try again.'];
    // Log error: error_log($e->getMessage());
}

header("Location: /pet.php?id={$pet_id}");
exit;
