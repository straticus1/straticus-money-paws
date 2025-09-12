<?php
/**
 * Money Paws - Like Pet Story API
 * Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>
 */
require_once '../includes/functions.php';
require_once '../includes/social_features.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Please log in to like stories.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$story_id = intval($_POST['story_id'] ?? 0);

if ($story_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid story ID.']);
    exit;
}

$result = likePetStory($_SESSION['user_id'], $story_id);

echo json_encode($result);
?>