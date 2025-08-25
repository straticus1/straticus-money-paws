<?php
/**
 * Money Paws - Cryptocurrency-Powered Pet Platform
 * Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>
 */
require_once '../includes/functions.php';
require_once '../includes/crypto.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Please log in']);
    exit;
}

$userId = $_SESSION['user_id'];
$balances = [];

foreach (SUPPORTED_CRYPTOS as $crypto => $name) {
    $balances[$crypto] = getUserCryptoBalance($userId, $crypto);
}

echo json_encode([
    'success' => true,
    'balances' => $balances
]);
?>
