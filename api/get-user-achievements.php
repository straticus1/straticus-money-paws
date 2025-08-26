<?php
/**
 * Paws.money - Get User Achievements API
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/functions.php';

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'User not logged in.']);
    http_response_code(401);
    exit;
}

$user_id = $_SESSION['user_id'];
$db = get_db();

// This query fetches all achievements and joins them with the user's specific progress.
// A LEFT JOIN ensures all achievements are returned, even those the user hasn't started.
$stmt = $db->prepare(
    'SELECT 
        a.id AS achievement_id, 
        a.achievement_name, 
        a.achievement_description, 
        a.goal_value, 
        a.reward_amount, 
        a.reward_currency, 
        COALESCE(ua.progress, 0) AS progress, 
        COALESCE(ua.status, \'not_started\') AS status, 
        ua.id AS user_achievement_id
    FROM achievements a
    LEFT JOIN user_achievements ua ON a.id = ua.achievement_id AND ua.user_id = ?
    ORDER BY a.id'
);

$stmt->execute([$user_id]);
$achievements = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($achievements);
