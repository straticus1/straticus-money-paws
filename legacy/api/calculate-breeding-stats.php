<?php
/**
 * Money Paws - Calculate Breeding Statistics API
 * Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>
 */

header('Content-Type: application/json');
require_once '../includes/functions.php';
require_once '../includes/security.php';
require_once '../includes/advanced_genetics.php';

requireCSRFToken();

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit;
}

$user_id = $_SESSION['user_id'];
$mother_id = intval($_POST['mother_id'] ?? 0);
$father_id = intval($_POST['father_id'] ?? 0);
$breeding_method = $_POST['breeding_method'] ?? 'natural';

if (empty($mother_id) || empty($father_id)) {
    echo json_encode(['success' => false, 'message' => 'Both parent IDs are required.']);
    exit;
}

try {
    $genetics_engine = new AdvancedGeneticsEngine();
    
    // Verify pet ownership
    $pdo = get_db();
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM pets 
        WHERE id IN (?, ?) AND user_id = ?
    ");
    $stmt->execute([$mother_id, $father_id, $user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['count'] !== 2) {
        echo json_encode(['success' => false, 'message' => 'Invalid pet selection.']);
        exit;
    }
    
    // Get breeding result preview (without actually breeding)
    $breeding_result = $genetics_engine->breedPetsAdvanced($mother_id, $father_id, $breeding_method);
    
    // Calculate rare trait chance based on method
    $rare_trait_chances = [
        'natural' => 0.05,        // 5% chance
        'ai_assisted' => 0.12,    // 12% chance
        'genetic_enhancement' => 0.25  // 25% chance
    ];
    
    $rare_trait_chance = $rare_trait_chances[$breeding_method] ?? 0.05;
    
    // Adjust based on parent traits
    $mother_traits = $genetics_engine->getPetTraits($mother_id);
    $father_traits = $genetics_engine->getPetTraits($father_id);
    
    $rare_parent_traits = 0;
    foreach (array_merge($mother_traits, $father_traits) as $trait) {
        if (in_array($trait['rarity'], ['rare', 'epic', 'legendary'])) {
            $rare_parent_traits++;
        }
    }
    
    // Bonus for rare parent traits
    $rare_trait_chance += ($rare_parent_traits * 0.02);
    $rare_trait_chance = min(0.50, $rare_trait_chance); // Cap at 50%
    
    echo json_encode([
        'success' => true,
        'stats' => [
            'success_probability' => $breeding_result['success_probability'],
            'genetic_diversity' => $breeding_result['diversity_score'],
            'rare_trait_chance' => round($rare_trait_chance, 3),
            'expected_mutations' => $breeding_result['mutation_events'] ?? 0,
            'parent_trait_count' => count($mother_traits) + count($father_traits)
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Breeding stats calculation error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to calculate breeding statistics.']);
}
?>
