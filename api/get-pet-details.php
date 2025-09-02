<?php
/**
 * Money Paws - Get Pet Details API
 * Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>
 */

header('Content-Type: application/json');
require_once '../includes/functions.php';
require_once '../includes/security.php';

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit;
}

$pet_id = intval($_GET['id'] ?? 0);

if (empty($pet_id)) {
    echo json_encode(['success' => false, 'message' => 'Pet ID is required.']);
    exit;
}

try {
    $pdo = get_db();
    
    // Get pet details with trait count
    $stmt = $pdo->prepare("
        SELECT p.*, 
               DATEDIFF(NOW(), p.birth_date) as age_days,
               COUNT(pta.trait_id) as trait_count
        FROM pets p
        LEFT JOIN pet_trait_assignments pta ON p.id = pta.pet_id
        WHERE p.id = ?
        GROUP BY p.id
    ");
    $stmt->execute([$pet_id]);
    $pet = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$pet) {
        echo json_encode(['success' => false, 'message' => 'Pet not found.']);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'pet' => $pet
    ]);
    
} catch (Exception $e) {
    error_log("Get pet details error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to load pet details.']);
}
?>
