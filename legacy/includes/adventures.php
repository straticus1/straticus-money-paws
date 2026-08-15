<?php
/**
 * Money Paws - Pet Adventures Backend
 * 
 * This file contains the core functions for managing pet adventures,
 * quests, rewards, and the player-driven marketplace.
 *
 * Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>
 */

require_once __DIR__ . '/functions.php';

/**
 * Fetches all available adventure quests that a pet can undertake.
 * 
 * @param int $pet_level The level of the pet, to filter quests.
 * @return array A list of available quests.
 */
function get_available_quests($pet_level) {
    $pdo = get_db();
    $stmt = $pdo->prepare("SELECT * FROM adventure_quests WHERE min_level <= ?");
    $stmt->execute([$pet_level]);
    return $stmt->fetchAll();
}

/**
 * Starts an adventure for a specific pet.
 *
 * @param int $pet_id The ID of the pet starting the adventure.
 * @param int $quest_id The ID of the quest to start.
 * @param int $user_id The ID of the user initiating the adventure.
 * @return array An array containing the success status and a message.
 */
function start_adventure($pet_id, $quest_id, $user_id) {
    $pdo = get_db();

    // 1. Fetch pet and quest data
    $stmt = $pdo->prepare("SELECT * FROM pets WHERE id = ?");
    $stmt->execute([$pet_id]);
    $pet = $stmt->fetch();

    $stmt = $pdo->prepare("SELECT * FROM adventure_quests WHERE id = ?");
    $stmt->execute([$quest_id]);
    $quest = $stmt->fetch();

    // 2. Validation
    if (!$pet) {
        return ['success' => false, 'message' => 'Pet not found.'];
    }
    if (!$quest) {
        return ['success' => false, 'message' => 'Quest not found.'];
    }
    if ($pet['user_id'] != $user_id) {
        return ['success' => false, 'message' => 'You do not own this pet.'];
    }
    if ($pet['level'] < $quest['min_level']) {
        return ['success' => false, 'message' => 'Your pet is not a high enough level for this quest.'];
    }

    // Check if pet is already on an adventure
    $stmt = $pdo->prepare("SELECT id FROM pet_adventures WHERE pet_id = ?");
    $stmt->execute([$pet_id]);
    if ($stmt->fetch()) {
        return ['success' => false, 'message' => 'This pet is already on an adventure.'];
    }

    // 3. Start the adventure
    try {
        $startTime = new DateTime();
        $endTime = (new DateTime())->add(new DateInterval('PT' . $quest['duration_minutes'] . 'M'));

        $stmt = $pdo->prepare("
            INSERT INTO pet_adventures (pet_id, quest_id, start_time, end_time)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $pet_id,
            $quest_id,
            $startTime->format('Y-m-d H:i:s'),
            $endTime->format('Y-m-d H:i:s')
        ]);

        return ['success' => true, 'message' => 'Your pet has started the adventure!', 'end_time' => $endTime];
    } catch (Exception $e) {
        // Log error properly in a real application
        return ['success' => false, 'message' => 'An error occurred while starting the adventure.'];
    }
}

/**
 * Completes an adventure for a pet and distributes rewards.
 *
 * @param int $adventure_id The ID of the adventure to complete.
 * @return array An array containing the success status, a message, and rewards info.
 */
function complete_adventure($adventure_id) {
    $pdo = get_db();
    $pdo->beginTransaction();

    try {
        // 1. Fetch adventure data and lock the row
        // 1. Fetch adventure data (no FOR UPDATE in SQLite)
        $stmt = $pdo->prepare("SELECT * FROM pet_adventures WHERE id = ?");
        $stmt->execute([$adventure_id]);
        $adventure = $stmt->fetch();

        if (!$adventure) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'Adventure not found.'];
        }

        // 2. Verify adventure is complete
        $endTime = new DateTime($adventure['end_time']);
        if (new DateTime() < $endTime) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'This adventure is not yet complete.'];
        }

        // 3. Fetch pet and quest data
        $stmt = $pdo->prepare("SELECT * FROM pets WHERE id = ?");
        $stmt->execute([$adventure['pet_id']]);
        $pet = $stmt->fetch();

        $stmt = $pdo->prepare("SELECT * FROM adventure_quests WHERE id = ?");
        $stmt->execute([$adventure['quest_id']]);
        $quest = $stmt->fetch();

        if (!$pet || !$quest) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'Pet or quest data missing.'];
        }

        // 4. Grant experience and handle level-ups
        $new_exp = $pet['experience'] + $quest['experience_reward'];
        $new_level = $pet['level'];
        $leveled_up = false;
        $exp_for_next_level = $new_level * 100; // Simple level-up formula

        while ($new_exp >= $exp_for_next_level) {
            $new_level++;
            $new_exp -= $exp_for_next_level;
            $leveled_up = true;
            $exp_for_next_level = $new_level * 100;
        }

        $stmt = $pdo->prepare("UPDATE pets SET experience = ?, level = ? WHERE id = ?");
        $stmt->execute([$new_exp, $new_level, $pet['id']]);

        // 5. Calculate and grant item rewards
        $stmt = $pdo->prepare("SELECT * FROM quest_rewards WHERE quest_id = ?");
        $stmt->execute([$quest['id']]);
        $potential_rewards = $stmt->fetchAll();
        $awarded_items = [];

        foreach ($potential_rewards as $reward) {
            if ((mt_rand(1, 10000) / 100) <= $reward['drop_chance']) {
                // SQLite compatible way to INSERT or UPDATE
                $stmt_check = $pdo->prepare("SELECT id FROM user_inventory WHERE user_id = ? AND item_id = ?");
                $stmt_check->execute([$pet['user_id'], $reward['item_id']]);
                if ($stmt_check->fetch()) {
                    $stmt_update = $pdo->prepare("UPDATE user_inventory SET quantity = quantity + 1 WHERE user_id = ? AND item_id = ?");
                    $stmt_update->execute([$pet['user_id'], $reward['item_id']]);
                } else {
                    $stmt_insert = $pdo->prepare("INSERT INTO user_inventory (user_id, item_id, quantity) VALUES (?, ?, 1)");
                    $stmt_insert->execute([$pet['user_id'], $reward['item_id']]);
                }

                $awarded_items[] = $reward['item_id'];
            }
        }

        // 6. Delete the completed adventure
        $stmt = $pdo->prepare("DELETE FROM pet_adventures WHERE id = ?");
        $stmt->execute([$adventure_id]);

        $pdo->commit();

        return [
            'success' => true,
            'message' => 'Adventure completed!',
            'rewards' => [
                'experience' => $quest['experience_reward'],
                'leveled_up' => $leveled_up,
                'new_level' => $new_level,
                'items' => $awarded_items
            ]
        ];

    } catch (Exception $e) {
        $pdo->rollBack();
        // Log error properly in a real application
        return ['success' => false, 'message' => 'An error occurred while completing the adventure.'];
    }
}

/**
 * Checks for and automatically completes any finished adventures for a user.
 *
 * @param int $user_id The ID of the user.
 * @return array A report of completed adventures and rewards.
 */
function check_and_complete_user_adventures($user_id) {
    $pdo = get_db();
    
    // 1. Find all of the user's adventures that are ready to be completed.
    $stmt = $pdo->prepare("
        SELECT pa.id FROM pet_adventures pa
        JOIN pets p ON pa.pet_id = p.id
        WHERE p.user_id = ? AND pa.end_time <= ?
    ");
    $stmt->execute([$user_id, (new DateTime())->format('Y-m-d H:i:s')]);
    $completed_adventures = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($completed_adventures)) {
        return ['completed_count' => 0, 'rewards' => []];
    }

    // 2. Complete each adventure and collect the results.
    $master_report = [
        'completed_count' => 0,
        'total_exp' => 0,
        'level_ups' => 0,
        'items' => []
    ];

    foreach ($completed_adventures as $adventure_id) {
        $result = complete_adventure($adventure_id);
        if ($result['success']) {
            $master_report['completed_count']++;
            $master_report['total_exp'] += $result['rewards']['experience'];
            if ($result['rewards']['leveled_up']) {
                $master_report['level_ups']++;
            }
            $master_report['items'] = array_merge($master_report['items'], $result['rewards']['items']);
        }
    }

    return $master_report;
}
