<?php
header('Content-Type: application/json');
require_once '../includes/functions.php';
require_once '../includes/health.php';

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'You must be logged in.']);
    exit;
}

$pet_id = $_GET['pet_id'] ?? null;

if (!$pet_id) {
    echo json_encode(['success' => false, 'message' => 'Pet ID is required.']);
    exit;
}

$pdo = get_db();
$stmt = $pdo->prepare('SELECT i.treatment_cost FROM illnesses i JOIN pet_active_illnesses pai ON i.id = pai.illness_id WHERE pai.pet_id = :pet_id');
$stmt->execute(['pet_id' => $pet_id]);
$costs = $stmt->fetchAll(PDO::FETCH_COLUMN);

$total_cost = array_sum($costs);

echo json_encode(['success' => true, 'cost' => $total_cost]);
