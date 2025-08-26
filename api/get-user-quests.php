<?php
/**
 * Paws.money - Get User Quests API
 * 
 * This API endpoint retrieves the current user's active and completed quests.
 * It will also assign daily quests if they haven't been assigned for the day.
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/quests.php';


if (!is_logged_in()) {
    echo json_encode(['error' => 'User not logged in.']);
    http_response_code(401);
    exit;
}

$user_id = $_SESSION['user_id'];

// Assign daily quests if the user doesn't have any for today
assign_daily_quests_if_needed($user_id);

$db = get_db();

// Fetch all quests for the user that are not yet claimed
$stmt = $db->prepare(
    'SELECT uq.id AS user_quest_id, q.quest_name, q.quest_description, q.goal_value, q.reward_amount, q.reward_currency, uq.progress, uq.status '
    . 'FROM user_quests uq '
    . 'JOIN quests q ON uq.quest_id = q.id '
    . 'WHERE uq.user_id = ? AND uq.status != \'claimed\' '
    . 'ORDER BY uq.assigned_at DESC'
);

$stmt->execute([$user_id]);
$quests = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($quests);
