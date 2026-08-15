<?php
require_once '../includes/functions.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

requireCSRFToken();

$user_id = $_SESSION['user_id'];
$pet_id = $_POST['pet_id'] ?? null;
$action = $_POST['action'] ?? null;
$price = $_POST['price'] ?? null;

if (!$pet_id || !$action) {
    header('Location: ../sell_pet.php?error=Missing required fields');
    exit;
}

// Sanitize inputs
$pet_id = filter_var($pet_id, FILTER_SANITIZE_NUMBER_INT);
$action = sanitizeInput($action);

// Verify pet ownership
global $pdo;
$stmt = $pdo->prepare("SELECT * FROM pets WHERE id = ? AND user_id = ?");
$stmt->execute([$pet_id, $user_id]);
$pet = $stmt->fetch();

if (!$pet) {
    header('Location: ../sell_pet.php?error=Pet not found or you do not own this pet.');
    exit;
}

try {
    if ($action === 'sell') {
        if ($price === null || !is_numeric($price) || $price <= 0) {
            header('Location: ../sell_pet.php?error=Invalid price specified.');
            exit;
        }
        $price = filter_var($price, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);

        $stmt = $pdo->prepare("UPDATE pets SET is_for_sale = 1, sale_price_usd = ? WHERE id = ?");
        $stmt->execute([$price, $pet_id]);
        header('Location: ../sell_pet.php?success=Pet listed for sale successfully!');

    } elseif ($action === 'unlist') {
        $stmt = $pdo->prepare("UPDATE pets SET is_for_sale = 0, sale_price_usd = NULL WHERE id = ?");
        $stmt->execute([$pet_id]);
        header('Location: ../sell_pet.php?success=Pet removed from sale.');

    } else {
        header('Location: ../sell_pet.php?error=Invalid action.');
    }
} catch (PDOException $e) {
    // In a real app, log this error instead of showing it to the user
    header('Location: ../sell_pet.php?error=Database error occurred.');
}

exit;
