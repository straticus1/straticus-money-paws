<?php
/**
 * Money Paws - Cryptocurrency-Powered Pet Platform
 * Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>
 */
require_once '../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$petId = intval($input['pet_id'] ?? 0);

if ($petId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid pet ID']);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE pets SET views_count = views_count + 1 WHERE id = ?");
    $stmt->execute([$petId]);
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    error_log('Increment views error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
