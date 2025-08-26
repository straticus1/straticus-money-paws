<?php
header('Content-Type: application/json');
require_once '../includes/functions.php';
require_once '../includes/health.php';

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'You must be logged in.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$pet_id = $_POST['pet_id'] ?? null;
$user_id = $_SESSION['user_id'];

if (!$pet_id) {
    echo json_encode(['success' => false, 'message' => 'Pet ID is required.']);
    exit;
}

$pdo = get_db();
$stmt = $pdo->prepare('SELECT i.id, i.treatment_cost FROM illnesses i JOIN pet_active_illnesses pai ON i.id = pai.illness_id WHERE pai.pet_id = :pet_id');
$stmt->execute(['pet_id' => $pet_id]);
$illnesses = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_cost = 0;
foreach ($illnesses as $illness) {
    $total_cost += $illness['treatment_cost'];
}

$user = getUserById($user_id);

if ($user['balance'] < $total_cost) {
    echo json_encode(['success' => false, 'message' => 'Insufficient funds.']);
    exit;
}

// Deduct cost and cure illnesses
$pdo->beginTransaction();
try {
    $user_stmt = $pdo->prepare('UPDATE users SET balance = balance - :cost WHERE id = :user_id');
    $user_stmt->execute(['cost' => $total_cost, 'user_id' => $user_id]);

    foreach ($illnesses as $illness) {
        cureIllness($pet_id, $illness['id']);
    }
    
    // Restore some health
    updatePetHealth($pet_id, 20); // Give 20 HP back

    $pdo->commit();
    header('Location: /vet_clinic.php?success=1');
    exit;
} catch (Exception $e) {
    $pdo->rollBack();
    header('Location: /vet_clinic.php?error=1');
    exit;
}
