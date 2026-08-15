<?php
header('Content-Type: application/json');
require_once '../includes/functions.php';
require_once '../includes/health.php';

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Not logged in.']);
    exit;
}

$user_id = $_SESSION['user_id'];
$pdo = get_db();

// Find pets with health below a certain threshold (e.g., 30)
$stmt = $pdo->prepare(
    'SELECT p.original_name as name FROM pets p '
    . 'JOIN pet_health ph ON p.id = ph.pet_id '
    . 'WHERE p.user_id = :user_id AND ph.health_points < 30'
);
$stmt->execute(['user_id' => $user_id]);
$pets = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['success' => true, 'data' => $pets]);
