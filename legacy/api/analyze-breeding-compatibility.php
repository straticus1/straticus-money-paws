<?php
/**
 * Money Paws - Analyze Breeding Compatibility API
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

if (empty($mother_id) || empty($father_id)) {
    echo json_encode(['success' => false, 'message' => 'Both parent IDs are required.']);
    exit;
}

try {
    $pdo = get_db();
    
    // Verify pet ownership
    $stmt = $pdo->prepare("
        SELECT id, species, original_name 
        FROM pets 
        WHERE id IN (?, ?) AND user_id = ?
    ");
    $stmt->execute([$mother_id, $father_id, $user_id]);
    $pets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($pets) !== 2) {
        echo json_encode(['success' => false, 'message' => 'Invalid pet selection or ownership.']);
        exit;
    }
    
    // Get species for compatibility check
    $mother_species = null;
    $father_species = null;
    
    foreach ($pets as $pet) {
        if ($pet['id'] == $mother_id) {
            $mother_species = $pet['species'] ?? 'dog';
        } else {
            $father_species = $pet['species'] ?? 'dog';
        }
    }
    
    // Check breeding compatibility
    $stmt = $pdo->prepare("
        SELECT * FROM breeding_compatibility 
        WHERE (species_a = ? AND species_b = ?) 
           OR (species_a = ? AND species_b = ?)
        LIMIT 1
    ");
    $stmt->execute([$mother_species, $father_species, $father_species, $mother_species]);
    $compatibility = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$compatibility) {
        // Default compatibility for unknown combinations
        $compatibility = [
            'compatibility_score' => 0.50,
            'success_rate_modifier' => 0.75,
            'is_hybrid' => false,
            'offspring_species' => $mother_species
        ];
    }
    
    // Calculate genetic diversity
    $genetics_engine = new AdvancedGeneticsEngine();
    $mother_traits = $genetics_engine->getPetTraits($mother_id);
    $father_traits = $genetics_engine->getPetTraits($father_id);
    
    $mother_trait_ids = array_column($mother_traits, 'trait_id');
    $father_trait_ids = array_column($father_traits, 'trait_id');
    
    $common_traits = array_intersect($mother_trait_ids, $father_trait_ids);
    $total_unique_traits = count(array_unique(array_merge($mother_trait_ids, $father_trait_ids)));
    
    $genetic_diversity = $total_unique_traits > 0 ? 
        1 - (count($common_traits) / $total_unique_traits) : 0.5;
    
    echo json_encode([
        'success' => true,
        'analysis' => [
            'compatibility_score' => floatval($compatibility['compatibility_score']),
            'genetic_diversity' => round($genetic_diversity, 3),
            'success_rate_modifier' => floatval($compatibility['success_rate_modifier']),
            'is_hybrid' => boolval($compatibility['is_hybrid']),
            'offspring_species' => $compatibility['offspring_species'],
            'mother_traits' => count($mother_trait_ids),
            'father_traits' => count($father_trait_ids),
            'common_traits' => count($common_traits)
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Breeding compatibility analysis error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to analyze compatibility.']);
}
?>
