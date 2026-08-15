<?php
/**
 * Money Paws - Pet Health & Wellness Functions
 */

require_once 'db.php';

/**
 * Initializes health stats for a new pet.
 *
 * @param int $pet_id The ID of the new pet.
 */
function initializePetHealth($pet_id)
{
    $pdo = get_db();
    $stmt = $pdo->prepare('INSERT INTO pet_health (pet_id) VALUES (:pet_id)');
    $stmt->execute(['pet_id' => $pet_id]);
}

/**
 * Retrieves the health status of a specific pet.
 *
 * @param int $pet_id The ID of the pet.
 * @return array|false The pet's health data or false if not found.
 */
function getPetHealth($pet_id)
{
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT * FROM pet_health WHERE pet_id = :pet_id');
    $stmt->execute(['pet_id' => $pet_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Updates a pet's health points and status.
 *
 * @param int $pet_id The pet's ID.
 * @param int $hp_change The amount to change health by (can be negative).
 * @param string|null $new_status The new health status (e.g., 'sick').
 */
function updatePetHealth($pet_id, $hp_change, $new_status = null)
{
    $pdo = get_db();
    $current_health = getPetHealth($pet_id);
    $new_hp = $current_health['health_points'] + $hp_change;
    $new_hp = max(0, min(100, $new_hp)); // Clamp between 0 and 100

    $sql = 'UPDATE pet_health SET health_points = :hp';
    $params = ['hp' => $new_hp, 'pet_id' => $pet_id];

    if ($new_status !== null) {
        $sql .= ', status = :status';
        $params['status'] = $new_status;
    }

    $sql .= ' WHERE pet_id = :pet_id';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
}

/**
 * Inflicts a specific illness on a pet.
 *
 * @param int $pet_id The pet's ID.
 * @param int $illness_id The illness's ID.
 */
function inflictIllness($pet_id, $illness_id)
{
    $pdo = get_db();
    // Check if already sick with this illness
    $stmt = $pdo->prepare('SELECT id FROM pet_active_illnesses WHERE pet_id = :pet_id AND illness_id = :illness_id');
    $stmt->execute(['pet_id' => $pet_id, 'illness_id' => $illness_id]);
    if ($stmt->fetch()) {
        return; // Already has this illness
    }

    $stmt = $pdo->prepare('INSERT INTO pet_active_illnesses (pet_id, illness_id) VALUES (:pet_id, :illness_id)');
    $stmt->execute(['pet_id' => $pet_id, 'illness_id' => $illness_id]);
    updatePetHealth($pet_id, 0, 'sick');
}

/**
 * Cures a pet's illness.
 *
 * @param int $pet_id The pet's ID.
 * @param int $illness_id The illness's ID.
 */
function getIllnessById($illness_id)
{
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT * FROM illnesses WHERE id = :id');
    $stmt->execute(['id' => $illness_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getPetsWithHealthAndIllnesses($user_id)
{
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT p.*, ph.health_points, ph.status FROM pets p JOIN pet_health ph ON p.id = ph.pet_id WHERE p.user_id = :user_id');
    $stmt->execute(['user_id' => $user_id]);
    $pets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    for ($i = 0; $i < count($pets); $i++) {
        $illness_stmt = $pdo->prepare('SELECT i.* FROM illnesses i JOIN pet_active_illnesses pai ON i.id = pai.illness_id WHERE pai.pet_id = :pet_id');
        $illness_stmt->execute(['pet_id' => $pets[$i]['id']]);
        $pets[$i]['illnesses'] = $illness_stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    return $pets;
}

function cureIllness($pet_id, $illness_id)
{
    $pdo = get_db();
    $stmt = $pdo->prepare('DELETE FROM pet_active_illnesses WHERE pet_id = :pet_id AND illness_id = :illness_id');
    $stmt->execute(['pet_id' => $pet_id, 'illness_id' => $illness_id]);

    // If no other illnesses, set status to healthy
    $stmt = $pdo->prepare('SELECT id FROM pet_active_illnesses WHERE pet_id = :pet_id');
    $stmt->execute(['pet_id' => $pet_id]);
    if (!$stmt->fetch()) {
        updatePetHealth($pet_id, 0, 'healthy');
    }
}
