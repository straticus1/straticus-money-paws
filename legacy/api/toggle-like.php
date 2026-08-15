<?php
/**
 * Money Paws - Cryptocurrency-Powered Pet Platform
 * Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>
 */
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Please log in to like pets']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$petId = intval($input['pet_id'] ?? 0);
$userId = $_SESSION['user_id'];

if ($petId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid pet ID']);
    exit;
}

try {
    // Check if user already liked this pet
    $stmt = $pdo->prepare("SELECT id FROM pet_likes WHERE user_id = ? AND pet_id = ?");
    $stmt->execute([$userId, $petId]);
    $existingLike = $stmt->fetch();
    
    if ($existingLike) {
        // Unlike the pet
        $stmt = $pdo->prepare("DELETE FROM pet_likes WHERE user_id = ? AND pet_id = ?");
        $stmt->execute([$userId, $petId]);
        
        // Decrement likes count
        $stmt = $pdo->prepare("UPDATE pets SET likes_count = likes_count - 1 WHERE id = ?");
        $stmt->execute([$petId]);
    } else {
        // Like the pet
        $stmt = $pdo->prepare("INSERT INTO pet_likes (user_id, pet_id) VALUES (?, ?)");
        $stmt->execute([$userId, $petId]);
        
        // Increment likes count
        $stmt = $pdo->prepare("UPDATE pets SET likes_count = likes_count + 1 WHERE id = ?");
        $stmt->execute([$petId]);

        // Create notification for pet owner
        $pet = getPetById($petId);
        if ($pet) {
            createNotification($pet['user_id'], $userId, $petId, null, 'like');
        }
    }
    
    // Get updated likes count
    $stmt = $pdo->prepare("SELECT likes_count FROM pets WHERE id = ?");
    $stmt->execute([$petId]);
    $likesCount = $stmt->fetchColumn();
    
    echo json_encode([
        'success' => true,
        'likes_count' => $likesCount,
        'liked' => !$existingLike
    ]);
    
} catch (Exception $e) {
    error_log('Toggle like error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
