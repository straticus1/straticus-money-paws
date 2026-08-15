<?php
/**
 * Money Paws - Cryptocurrency-Powered Pet Platform
 * Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>
 */
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Please log in to delete pets']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['csrf_token']) || !validateCSRFToken($input['csrf_token'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}
$petId = intval($input['pet_id'] ?? 0);
$userId = $_SESSION['user_id'];

if ($petId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid pet ID']);
    exit;
}

try {
    // Verify pet belongs to user
    $stmt = $pdo->prepare("SELECT filename FROM pets WHERE id = ? AND user_id = ?");
    $stmt->execute([$petId, $userId]);
    $pet = $stmt->fetch();
    
    if (!$pet) {
        echo json_encode(['success' => false, 'message' => 'Pet not found or access denied']);
        exit;
    }
    
    // Delete file from filesystem
    $filepath = UPLOAD_DIR . $pet['filename'];
    if (file_exists($filepath)) {
        unlink($filepath);
    }
    
    // Delete from database (cascading will handle likes)
    $stmt = $pdo->prepare("DELETE FROM pets WHERE id = ? AND user_id = ?");
    $stmt->execute([$petId, $userId]);
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    error_log('Delete pet error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
