<?php
/**
 * Money Paws - Cryptocurrency-Powered Pet Platform
 * Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>
 */
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Pet ID required']);
    exit;
}

$petId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

try {
    $stmt = $pdo->prepare("
        SELECT p.*, u.name as owner_name 
        FROM pets p 
        JOIN users u ON p.user_id = u.id 
        WHERE p.id = ? AND p.is_public = 1
    ");
    $stmt->execute([$petId]);
    $pet = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$pet) {
        echo json_encode(['success' => false, 'message' => 'Pet not found']);
        exit;
    }
    
    // Add full path to filename
    $pet['filename'] = UPLOAD_DIR . $pet['filename'];
    
    echo json_encode(['success' => true, 'pet' => $pet]);
    
} catch (Exception $e) {
    error_log('Get pet error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
