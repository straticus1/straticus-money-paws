<?php
/**
 * Money Paws - Hourly Cron Job Tasks
 * This script should be run every hour via a cron job.
 * It handles tasks like pet health degradation and random illnesses.
 */

if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.\n");
}

require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/health.php';

echo "Starting hourly tasks...\n";

$pdo = get_db();

// --- 1. Process Pet Health Degradation ---
echo "Processing pet health degradation...\n";
$stmt = $pdo->query('SELECT id FROM pets');
$pets = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($pets as $pet) {
    // Decrease health by a small amount, e.g., 1 point per hour
    updatePetHealth($pet['id'], -1);
}
echo count($pets) . " pets processed for health degradation.\n";

// --- 2. Process Random Illness Chance ---
echo "Processing random illness chance...\n";
$illness_chance = 0.05; // 5% chance per pet per hour

$illnesses_stmt = $pdo->query('SELECT id FROM illnesses');
$available_illnesses = $illnesses_stmt->fetchAll(PDO::FETCH_COLUMN);

if (!empty($available_illnesses)) {
    $sick_pets_count = 0;
    foreach ($pets as $pet) {
        if ((mt_rand() / mt_getrandmax()) < $illness_chance) {
            $random_illness_id = $available_illnesses[array_rand($available_illnesses)];
            inflictIllness($pet['id'], $random_illness_id);
            $sick_pets_count++;
        }
    }
    echo $sick_pets_count . " pets have newly contracted an illness.\n";
} else {
    echo "No illnesses found in the database to inflict.\n";
}

echo "Hourly tasks completed successfully.\n";
