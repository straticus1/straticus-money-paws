<?php
/**
 * Money Paws - Tournament Registration API
 * Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>
 */

header('Content-Type: application/json');
require_once '../includes/functions.php';
require_once '../includes/security.php';
require_once '../includes/tournament_engine.php';

requireCSRFToken();

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'You must be logged in to register for tournaments.']);
    exit;
}

$user_id = $_SESSION['user_id'];
$tournament_id = intval($_POST['tournament_id'] ?? 0);
$pet_id = intval($_POST['pet_id'] ?? 0);

if (empty($tournament_id) || empty($pet_id)) {
    echo json_encode(['success' => false, 'message' => 'Tournament and pet selection are required.']);
    exit;
}

try {
    $tournament_engine = new TournamentEngine();
    
    // Get tournament details for payment
    $tournament = $tournament_engine->getTournament($tournament_id);
    if (!$tournament) {
        echo json_encode(['success' => false, 'message' => 'Tournament not found.']);
        exit;
    }
    
    // Simulate payment processing (in production, integrate with crypto payment gateway)
    $payment_data = [
        'usd_amount' => $tournament['entry_fee_usd'],
        'crypto_type' => 'USDC',
        'crypto_amount' => $tournament['entry_fee_usd'] // 1:1 for simplicity
    ];
    
    // Check user balance
    $user_balance = getUserBalance($user_id, 'USDC');
    if ($user_balance < $payment_data['usd_amount']) {
        echo json_encode(['success' => false, 'message' => 'Insufficient USDC balance for tournament entry.']);
        exit;
    }
    
    // Register for tournament
    $participant_id = $tournament_engine->registerForTournament($tournament_id, $user_id, $pet_id, $payment_data);
    
    // Deduct entry fee from user balance
    updateUserBalance($user_id, 'USDC', -$payment_data['usd_amount']);
    
    echo json_encode([
        'success' => true,
        'message' => 'Successfully registered for tournament!',
        'participant_id' => $participant_id
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// Helper functions
function getUserBalance($user_id, $crypto_type) {
    $pdo = get_db();
    $stmt = $pdo->prepare("SELECT balance FROM user_balances WHERE user_id = ? AND crypto_type = ?");
    $stmt->execute([$user_id, $crypto_type]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['balance'] : 0;
}

function updateUserBalance($user_id, $crypto_type, $amount) {
    $pdo = get_db();
    $stmt = $pdo->prepare("
        INSERT INTO user_balances (user_id, crypto_type, balance)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE balance = balance + VALUES(balance)
    ");
    $stmt->execute([$user_id, $crypto_type, $amount]);
}
?>
