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
if (!$pet_id) {
    header('Location: /index.php');
    exit;
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

// Mark the pet as deceased
$stmt = $pdo->prepare(
    'UPDATE pets SET life_status = ?, deceased_date = CURRENT_TIMESTAMP, is_for_sale = FALSE, is_public = TRUE WHERE id = ?'
);
$stmt->execute(['deceased', $pet_id]);

$_SESSION['flash_message'] = ['type' => 'success', 'message' => htmlspecialchars($pet['original_name']) . ' has been moved to a memorial.'];
header("Location: /pet.php?id={$pet_id}");
exit;
