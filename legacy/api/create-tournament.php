<?php
/**
 * Money Paws - Create Tournament API
 * Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>
 */

header('Content-Type: application/json');
require_once '../includes/functions.php';
require_once '../includes/security.php';
require_once '../includes/tournament_engine.php';

requireCSRFToken();

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'You must be logged in to create tournaments.']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Validate input
$tournament_name = sanitizeInput($_POST['tournament_name'] ?? '');
$tournament_type = $_POST['tournament_type'] ?? '';
$game_mode = $_POST['game_mode'] ?? '';
$entry_fee = floatval($_POST['entry_fee'] ?? 0);
$max_participants = intval($_POST['max_participants'] ?? 8);
$registration_deadline = $_POST['registration_deadline'] ?? '';
$start_time = $_POST['start_time'] ?? '';

// Validation
if (empty($tournament_name)) {
    echo json_encode(['success' => false, 'message' => 'Tournament name is required.']);
    exit;
}

if (!in_array($tournament_type, ['single_elimination', 'double_elimination', 'round_robin', 'swiss'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid tournament type.']);
    exit;
}

if (!in_array($game_mode, ['pet_battle', 'racing', 'agility', 'beauty_contest', 'intelligence'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid game mode.']);
    exit;
}

if ($entry_fee < 0 || $entry_fee > 100) {
    echo json_encode(['success' => false, 'message' => 'Entry fee must be between $0 and $100.']);
    exit;
}

if (!in_array($max_participants, [8, 16, 32, 64])) {
    echo json_encode(['success' => false, 'message' => 'Invalid participant limit.']);
    exit;
}

// Validate dates
$reg_deadline = new DateTime($registration_deadline);
$tournament_start = new DateTime($start_time);
$now = new DateTime();

if ($reg_deadline <= $now) {
    echo json_encode(['success' => false, 'message' => 'Registration deadline must be in the future.']);
    exit;
}

if ($tournament_start <= $reg_deadline) {
    echo json_encode(['success' => false, 'message' => 'Tournament start must be after registration deadline.']);
    exit;
}

try {
    $tournament_engine = new TournamentEngine();
    
    $tournament_data = [
        'name' => $tournament_name,
        'type' => $tournament_type,
        'game_mode' => $game_mode,
        'entry_fee' => $entry_fee,
        'max_participants' => $max_participants,
        'registration_deadline' => $registration_deadline,
        'start_time' => $start_time
    ];
    
    $tournament_id = $tournament_engine->createTournament($user_id, $tournament_data);
    
    echo json_encode([
        'success' => true,
        'message' => 'Tournament created successfully!',
        'tournament_id' => $tournament_id
    ]);
    
} catch (Exception $e) {
    error_log("Tournament creation error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to create tournament. Please try again.']);
}
?>
