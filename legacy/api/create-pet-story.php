<?php
/**
 * Money Paws - Create Pet Story API
 * Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>
 */
require_once '../includes/functions.php';
require_once '../includes/social_features.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Please log in to create stories.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$pet_id = intval($_POST['pet_id'] ?? 0);
$content = trim($_POST['content'] ?? '');

if ($pet_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Please select a pet.']);
    exit;
}

if (empty($content) || strlen($content) > 280) {
    echo json_encode(['success' => false, 'message' => 'Story content must be between 1 and 280 characters.']);
    exit;
}

$result = createPetStory($_SESSION['user_id'], $pet_id, $content);

echo json_encode($result);
?>