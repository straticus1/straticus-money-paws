<?php
/**
 * Paws.money - Claim Quest Reward API
 *
 * This API handles claiming the reward for a completed quest.
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/functions.php';

session_start();

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'User not logged in.']);
    http_response_code(401);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    http_response_code(405);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$user_quest_id = isset($input['user_quest_id']) ? (int)$input['user_quest_id'] : 0;
$user_id = $_SESSION['user_id'];

if ($user_quest_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid quest ID.']);
    http_response_code(400);
    exit;
}

$db = get_db();
$db->beginTransaction();

try {
    // 1. Fetch the user quest and the main quest details to verify status and get reward info
    $stmt = $db->prepare(
        'SELECT uq.id, uq.status, q.reward_currency, q.reward_amount '
        . 'FROM user_quests uq '
        . 'JOIN quests q ON uq.quest_id = q.id '
        . 'WHERE uq.id = ? AND uq.user_id = ?'
    );
    $stmt->execute([$user_quest_id, $user_id]);
    $user_quest = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user_quest) {
        throw new Exception('Quest not found or does not belong to the user.');
    }

    if ($user_quest['status'] !== 'completed') {
        throw new Exception('Quest is not completed yet or has already been claimed.');
    }

    // 2. Add the reward to the user's balance
    $currency_column = $user_quest['reward_currency'];
    if (!in_array($currency_column, ['paw_coins', 'gems'])) {
        throw new Exception('Invalid reward currency.');
    }

    $update_balance_stmt = $db->prepare(
        "UPDATE users SET {$currency_column} = {$currency_column} + ? WHERE id = ?"
    );
    $update_balance_stmt->execute([$user_quest['reward_amount'], $user_id]);

    // 3. Update the quest status to 'claimed'
    $update_quest_stmt = $db->prepare(
        'UPDATE user_quests SET status = \'claimed\' WHERE id = ?'
    );
    $update_quest_stmt->execute([$user_quest_id]);

    $db->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Reward claimed!',
        'reward_amount' => $user_quest['reward_amount'],
        'reward_currency' => $user_quest['reward_currency']
    ]);

} catch (Exception $e) {
    $db->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    http_response_code(500);
}
