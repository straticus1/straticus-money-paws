<?php
/**
 * Money Paws - Advanced AI Breeding API
 * Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>
 */

header('Content-Type: application/json');
require_once '../includes/functions.php';
require_once '../includes/security.php';
require_once '../includes/advanced_genetics.php';

requireCSRFToken();

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'You must be logged in to breed pets.']);
    exit;
}

$user_id = $_SESSION['user_id'];
$mother_id = $_POST['mother_id'] ?? 0;
$father_id = $_POST['father_id'] ?? 0;
$new_pet_name = $_POST['name'] ?? 'Unnamed Offspring';
$breeding_method = $_POST['breeding_method'] ?? 'natural';

if (empty($mother_id) || empty($father_id)) {
    echo json_encode(['success' => false, 'message' => 'Both parent pets must be selected.']);
    exit;
}

if ($mother_id === $father_id) {
    echo json_encode(['success' => false, 'message' => 'A pet cannot breed with itself.']);
    exit;
}

// Validate ownership and get pet data
$mother = getPetByIdAndOwner($mother_id, $user_id);
$father = getPetByIdAndOwner($father_id, $user_id);

if (!$mother || !$father) {
    echo json_encode(['success' => false, 'message' => 'You do not own one or both of the selected pets.']);
    exit;
}

// Check pet ages
$mother_age = getPetAgeInPetDays($mother['birth_date']);
$father_age = getPetAgeInPetDays($father['birth_date']);
$minimum_age = 18;

if ($mother_age < $minimum_age) {
    echo json_encode(['success' => false, 'message' => 'The mother is too young to breed. It must be at least ' . $minimum_age . ' pet days old.']);
    exit;
}

if ($father_age < $minimum_age) {
    echo json_encode(['success' => false, 'message' => 'The father is too young to breed. It must be at least ' . $minimum_age . ' pet days old.']);
    exit;
}

// Check for breeding cooldowns
$mother_cooldown = getBreedingCooldown($mother_id);
$father_cooldown = getBreedingCooldown($father_id);

if ($mother_cooldown) {
    echo json_encode(['success' => false, 'message' => 'The mother is still on a breeding cooldown.']);
    exit;
}

if ($father_cooldown) {
    echo json_encode(['success' => false, 'message' => 'The father is still on a breeding cooldown.']);
    exit;
}

// Validate breeding method and process payment
$breeding_costs = [
    'natural' => 0.00,
    'ai_assisted' => 2.50,
    'genetic_enhancement' => 5.00
];

$cost = $breeding_costs[$breeding_method] ?? 0.00;

if ($cost > 0) {
    // Check user balance (simplified - in production would integrate with crypto payment)
    $user_balance = getUserBalance($user_id, 'USDC');
    if ($user_balance < $cost) {
        echo json_encode(['success' => false, 'message' => 'Insufficient balance for this breeding method.']);
        exit;
    }
    
    // Deduct cost
    updateUserBalance($user_id, 'USDC', -$cost);
}

try {
    $genetics_engine = new AdvancedGeneticsEngine();
    
    // Generate DNA for parents if they don't have it
    if (empty($mother['dna'])) {
        $mother['dna'] = generate_dna();
        $pdo = get_db();
        $stmt = $pdo->prepare("UPDATE pets SET dna = ? WHERE id = ?");
        $stmt->execute([$mother['dna'], $mother_id]);
    }

    if (empty($father['dna'])) {
        $father['dna'] = generate_dna();
        $pdo = get_db();
        $stmt = $pdo->prepare("UPDATE pets SET dna = ? WHERE id = ?");
        $stmt->execute([$father['dna'], $father_id]);
    }

    // Advanced breeding with AI genetics
    $breeding_result = $genetics_engine->breedPetsAdvanced($mother_id, $father_id, $breeding_method);
    
    // Check if breeding was successful based on probability
    $success_roll = mt_rand() / mt_getrandmax();
    if ($success_roll > $breeding_result['success_probability']) {
        echo json_encode([
            'success' => false, 
            'message' => 'Breeding attempt failed. The genetic compatibility was not sufficient this time.',
            'success_probability' => $breeding_result['success_probability']
        ]);
        exit;
    }
    
    // Create the new pet with advanced genetics
    $offspring_dna = breed_pets($mother['dna'], $father['dna']);
    $new_pet_id = createBredPet($user_id, $new_pet_name, $offspring_dna, $mother_id, $father_id);

    if ($new_pet_id) {
        // Assign advanced traits to the new pet
        $genetics_engine->assignTraitsToPet($new_pet_id, $breeding_result['traits']);
        
        // Record breeding in advanced breeding records
        $pdo = get_db();
        $stmt = $pdo->prepare("
            INSERT INTO breeding_records (
                mother_id, father_id, offspring_id, breeding_method, 
                success_probability, genetic_diversity_score, mutation_events, 
                breeding_cost_usd, ai_enhancement_used
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $mother_id,
            $father_id,
            $new_pet_id,
            $breeding_method,
            $breeding_result['success_probability'],
            $breeding_result['diversity_score'],
            $breeding_result['mutation_events'],
            $cost,
            $breeding_method !== 'natural'
        ]);
        
        // Set cooldowns for both parents
        $cooldown_seconds = 86400; // 24 hours
        setBreedingCooldown($mother_id, $cooldown_seconds);
        setBreedingCooldown($father_id, $cooldown_seconds);

        // Assign initial personalities and health
        assignInitialPersonalities($new_pet_id);
        initializePetHealth($new_pet_id);

        // Increase happiness of parents
        updatePetHappiness($mother_id, 25);
        updatePetHappiness($father_id, 25);
        
        // Calculate estimated market value
        $estimated_value = $genetics_engine->calculatePetValue($new_pet_id);

        echo json_encode([
            'success' => true, 
            'message' => 'Congratulations! Your advanced breeding was successful!',
            'new_pet_id' => $new_pet_id,
            'breeding_stats' => [
                'success_probability' => $breeding_result['success_probability'],
                'genetic_diversity' => $breeding_result['diversity_score'],
                'mutation_events' => $breeding_result['mutation_events'],
                'traits_inherited' => count($breeding_result['traits']),
                'estimated_value' => $estimated_value
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'An error occurred while creating the new pet.']);
    }
    
} catch (Exception $e) {
    error_log("Advanced breeding error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An unexpected error occurred during breeding.']);
}

// Helper functions
function getUserBalance($user_id, $crypto_type) {
    $pdo = get_db();
    $stmt = $pdo->prepare("SELECT balance FROM user_balances WHERE user_id = ? AND crypto_type = ?");
    $stmt->execute([$user_id, $crypto_type]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['balance'] : 0;
}

function updateUserBalance($user_id, $crypto_type, $amount) {
    $pdo = get_db();
    $stmt = $pdo->prepare("
        INSERT INTO user_balances (user_id, crypto_type, balance)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE balance = balance + VALUES(balance)
    ");
    $stmt->execute([$user_id, $crypto_type, $amount]);
}
?>
