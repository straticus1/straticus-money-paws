<?php
/**
 * Money Paws - Get Recent Offspring API
 * Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>
 */

header('Content-Type: application/json');
require_once '../includes/functions.php';
require_once '../includes/security.php';

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    $pdo = get_db();
    
    // Get recent offspring (pets with mother_id or father_id set)
    $stmt = $pdo->prepare("
        SELECT p.id, p.filename, p.original_name, p.birth_date,
               DATEDIFF(NOW(), p.birth_date) as days_ago,
               mother.original_name as mother_name,
               father.original_name as father_name
        FROM pets p
        LEFT JOIN pets mother ON p.mother_id = mother.id
        LEFT JOIN pets father ON p.father_id = father.id
        WHERE p.user_id = ? 
          AND (p.mother_id IS NOT NULL OR p.father_id IS NOT NULL)
          AND p.life_status = 'alive'
        ORDER BY p.birth_date DESC
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $offspring = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'offspring' => $offspring
    ]);
    
} catch (Exception $e) {
    error_log("Get recent offspring error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to load recent offspring.']);
}
?>
