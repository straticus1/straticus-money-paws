<?php
/**
 * Paws.money - Claim Achievement Reward API
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/functions.php';

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
$user_achievement_id = isset($input['user_achievement_id']) ? (int)$input['user_achievement_id'] : 0;
$user_id = $_SESSION['user_id'];

if ($user_achievement_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid achievement ID.']);
    http_response_code(400);
    exit;
}

$db = get_db();
$db->beginTransaction();

try {
    $stmt = $db->prepare(
        'SELECT ua.id, ua.status, a.reward_currency, a.reward_amount '
        . 'FROM user_achievements ua '
        . 'JOIN achievements a ON ua.achievement_id = a.id '
        . 'WHERE ua.id = ? AND ua.user_id = ?'
    );
    $stmt->execute([$user_achievement_id, $user_id]);
    $user_achievement = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user_achievement) {
        throw new Exception('Achievement not found or does not belong to the user.');
    }

    if ($user_achievement['status'] !== 'completed') {
        throw new Exception('Achievement is not completed yet or has already been claimed.');
    }

    $currency_column = $user_achievement['reward_currency'];
    if (!in_array($currency_column, ['paw_coins', 'gems'])) {
        throw new Exception('Invalid reward currency.');
    }

    $update_balance_stmt = $db->prepare(
        "UPDATE users SET {$currency_column} = {$currency_column} + ? WHERE id = ?"
    );
    $update_balance_stmt->execute([$user_achievement['reward_amount'], $user_id]);

    $update_achievement_stmt = $db->prepare(
        'UPDATE user_achievements SET status = \'claimed\' WHERE id = ?'
    );
    $update_achievement_stmt->execute([$user_achievement_id]);

    $db->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Reward claimed!',
        'reward_amount' => $user_achievement['reward_amount'],
        'reward_currency' => $user_achievement['reward_currency']
    ]);

} catch (Exception $e) {
    $db->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    http_response_code(500);
}
