<?php
session_start();
require_once '../includes/functions.php';

// Security checks
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /index.php');
    exit;
}

if (!isLoggedIn()) {
    header('Location: /login.php');
    exit;
}

// CSRF validation
if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
    $_SESSION['flash_message'] = ['type' => 'danger', 'message' => 'Invalid security token. Please try again.'];
    header('Location: /index.php');
    exit;
}

// Input validation
$pet_id = filter_input(INPUT_POST, 'pet_id', FILTER_VALIDATE_INT);
$enable_memorial = isset($_POST['enable_memorial']) ? 1 : 0;
$donation_goal = filter_input(INPUT_POST, 'donation_goal', FILTER_VALIDATE_FLOAT);

if (!$pet_id) {
    header('Location: /index.php');
    exit;
}

// Validate and cap the donation goal
if ($donation_goal !== false) {
    if ($donation_goal < 0) {
        $donation_goal = 0;
    }
    if ($donation_goal > 1000) {
        $donation_goal = 1000;
    }
} else {
    $donation_goal = 0;
}

$user_id = $_SESSION['user_id'];
$pdo = get_db();

// Verify pet ownership
$pet = getPetById($pet_id);
if (!$pet || $pet['user_id'] != $user_id) {
    $_SESSION['flash_message'] = ['type' => 'danger', 'message' => 'You are not authorized to perform this action.'];
    header("Location: /pet.php?id={$pet_id}");
    exit;
}

// Ensure pet is deceased
if ($pet['life_status'] !== 'deceased') {
    $_SESSION['flash_message'] = ['type' => 'warning', 'message' => 'You can only configure a memorial for a deceased pet.'];
    header("Location: /pet.php?id={$pet_id}");
    exit;
}

// Update memorial settings
$stmt = $pdo->prepare(
    'UPDATE pets SET is_memorial_enabled = ?, donation_goal = ? WHERE id = ?'
);
$stmt->execute([$enable_memorial, $donation_goal, $pet_id]);

$_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Memorial settings have been updated.'];
header("Location: /pet.php?id={$pet_id}");
exit;
