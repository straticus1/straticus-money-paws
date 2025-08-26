<?php
header('Content-Type: application/json');
require_once '../includes/db.php';
require_once '../includes/session.php';
require_once '../includes/marketplace.php';

start_session_if_not_started();

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'You must be logged in to make a purchase.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$listing_id = $data['listing_id'] ?? null;

if (empty($listing_id) || !is_numeric($listing_id)) {
    echo json_encode(['success' => false, 'message' => 'Invalid listing ID provided.']);
    exit;
}

$buyer_id = $_SESSION['user_id'];
$result = purchaseMarketplaceListing($buyer_id, (int)$listing_id);

echo json_encode($result);
