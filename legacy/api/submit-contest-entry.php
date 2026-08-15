<?php
/**
 * Money Paws - Submit Photo Contest Entry API
 * Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>
 */
require_once '../includes/functions.php';
require_once '../includes/social_features.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Please log in to enter contests.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$contest_id = intval($_POST['contest_id'] ?? 0);
$pet_id = intval($_POST['pet_id'] ?? 0);
$description = trim($_POST['description'] ?? '');

if ($contest_id <= 0 || $pet_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid contest or pet ID.']);
    exit;
}

$result = submitPhotoContestEntry($_SESSION['user_id'], $contest_id, $pet_id, $description);

echo json_encode($result);
?>