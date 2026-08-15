<?php
/**
 * Paws.money - Quests and Achievements System
 * 
 * This file contains the core logic for managing quests, including assigning
 * daily quests to users and tracking their progress.
 */

require_once __DIR__ . '/functions.php';

/**
 * Assigns daily quests to a user if they don't have any active ones for the day.
 *
 * @param int $user_id The ID of the user.
 * @return bool True if quests were assigned or already exist, false on error.
 */
function assign_daily_quests_if_needed(int $user_id): bool {
    $db = get_db();

    // Check if the user already has active daily quests assigned today
    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM user_quests uq '
        . 'JOIN quests q ON uq.quest_id = q.id '
        . 'WHERE uq.user_id = ? AND q.quest_type = \'daily\' AND DATE(uq.assigned_at) = CURDATE()'
    );
    $stmt->execute([$user_id]);
    if ($stmt->fetchColumn() > 0) {
        return true; // Quests already assigned for today
    }

    // Fetch 3 random active daily quests
    $quest_stmt = $db->query(
        'SELECT id FROM quests WHERE quest_type = \'daily\' AND is_active = 1 ORDER BY RAND() LIMIT 3'
    );
    $quests_to_assign = $quest_stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($quests_to_assign)) {
        return true; // No daily quests available to assign
    }

    // Assign the quests to the user
    $db->beginTransaction();
    try {
        $insert_stmt = $db->prepare(
            'INSERT INTO user_quests (user_id, quest_id) VALUES (?, ?)'
        );
        foreach ($quests_to_assign as $quest_id) {
            $insert_stmt->execute([$user_id, $quest_id]);
        }
        $db->commit();
    } catch (Exception $e) {
        $db->rollBack();
        // Log error if needed
        return false;
    }

    return true;
}

/**
 * Updates a user's progress for a specific action type.
 *
 * @param int $user_id The ID of the user.
 * @param string $action_type The type of action performed (e.g., 'feed_pet').
 * @param int $value_to_add The amount to add to the progress (usually 1).
 */
function update_quest_progress(int $user_id, string $action_type, int $value_to_add = 1): void {
    $db = get_db();

    // Find active, uncompleted quests for this user and action type
    $stmt = $db->prepare(
        'UPDATE user_quests uq '
        . 'JOIN quests q ON uq.quest_id = q.id '
        . 'SET uq.progress = uq.progress + ? '
        . 'WHERE uq.user_id = ? '
        . 'AND q.action_type = ? '
        . 'AND uq.status = \'in_progress\''
    );
    $stmt->execute([$value_to_add, $user_id, $action_type]);

    // Check for completed quests and update their status
    $check_stmt = $db->prepare(
        'UPDATE user_quests uq '
        . 'JOIN quests q ON uq.quest_id = q.id '
        . 'SET uq.status = \'completed\', uq.completed_at = NOW() '
        . 'WHERE uq.user_id = ? '
        . 'AND uq.status = \'in_progress\' '
        . 'AND uq.progress >= q.goal_value'
    );
    $check_stmt->execute([$user_id]);
}
