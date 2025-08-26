<?php
/**
 * Money Paws - Cryptocurrency-Powered Pet Platform
 * Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>
 */
require_once '../includes/crypto.php';

header('Content-Type: application/json');

if (!isset($_GET['crypto']) || !isset($_GET['usd'])) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

$cryptoType = strtoupper(sanitizeInput($_GET['crypto']));
$usdAmount = filter_input(INPUT_GET, 'usd', FILTER_VALIDATE_FLOAT);

if (!array_key_exists($cryptoType, SUPPORTED_CRYPTOS)) {
    echo json_encode(['success' => false, 'message' => 'Unsupported cryptocurrency']);
    exit;
}

$cryptoAmount = convertUSDToCrypto($usdAmount, $cryptoType);

if ($cryptoAmount === null) {
    echo json_encode(['success' => false, 'message' => 'Unable to get crypto price']);
    exit;
}

echo json_encode([
    'success' => true,
    'crypto_type' => $cryptoType,
    'usd_amount' => $usdAmount,
    'crypto_amount' => $cryptoAmount
]);
?>
