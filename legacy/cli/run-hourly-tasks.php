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

// --- 3. Process Overdue Vet Checkups ---
echo "Processing overdue vet checkups...\n";
$overdue_pets_stmt = $pdo->query("SELECT p.id, ph.last_checkup FROM pets p JOIN pet_health ph ON p.id = ph.pet_id WHERE ph.last_checkup IS NULL OR ph.last_checkup < DATE_SUB(NOW(), INTERVAL 30 DAY)");
$overdue_pets = $overdue_pets_stmt->fetchAll(PDO::FETCH_ASSOC);
$overdue_sickness_count = 0;

if (!empty($available_illnesses)) {
    foreach ($overdue_pets as $pet) {
        // Increased chance of sickness if overdue for checkup (e.g., 10%)
        if ((mt_rand() / mt_getrandmax()) < 0.10) {
            $random_illness_id = $available_illnesses[array_rand($available_illnesses)];
            inflictIllness($pet['id'], $random_illness_id);
            $overdue_sickness_count++;
        }
    }
}
echo $overdue_sickness_count . " pets got sick from overdue checkups.\n";

// --- 4. Process Missed Medicine Doses ---
echo "Processing missed medicine doses...\n";
$missed_dose_stmt = $pdo->query("SELECT pet_id, doses_per_day, doses_given_today FROM pet_prescriptions WHERE last_dose_at < DATE_SUB(NOW(), INTERVAL 24 HOUR) AND doses_given_today < doses_per_day");
$missed_dose_pets = $missed_dose_stmt->fetchAll(PDO::FETCH_ASSOC);
$penalty_count = 0;

foreach ($missed_dose_pets as $prescription) {
    // Penalize health and happiness for missed doses
    updatePetHealth($prescription['pet_id'], -2); // -2 HP
        updatePetHappiness($prescription['pet_id'], -5); // -5 Happiness
    $penalty_count++;
}
echo $penalty_count . " pets penalized for missing medicine.\n";

// --- 5. Daily Reset Tasks (run once a day, e.g., at midnight) ---
if (date('H') == '00') { // Run at midnight (00:00 - 00:59)
    echo "Running daily reset tasks...\n";

    // Reset doses_given_today for all prescriptions
    $pdo->query('UPDATE pet_prescriptions SET doses_given_today = 0');
    echo "Reset daily medicine doses.\n";
}

echo "Hourly tasks completed successfully.\n";
