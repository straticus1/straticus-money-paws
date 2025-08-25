<?php
/**
 * Money Paws - Purchase Store Item API
 * Handles item purchases with crypto payments
 */
require_once '../includes/functions.php';
require_once '../includes/crypto.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Check if user is logged in
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $itemId = intval($input['item_id'] ?? 0);
    $quantity = max(1, intval($input['quantity'] ?? 1));
    $cryptoType = sanitizeInput($input['crypto_type'] ?? '');
    
    if (!$itemId || !$cryptoType) {
        throw new Exception('Missing required parameters');
    }
    
    if (!array_key_exists($cryptoType, SUPPORTED_CRYPTOS)) {
        throw new Exception('Invalid cryptocurrency selected');
    }
    
    // Purchase the item
    $result = purchaseStoreItem($_SESSION['user_id'], $itemId, $quantity, $cryptoType);
    
    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'message' => "Successfully purchased {$quantity}x {$result['item']['name']}",
            'item' => $result['item'],
            'quantity' => $quantity,
            'crypto_amount' => $result['crypto_amount'],
            'crypto_type' => $cryptoType,
            'new_balance' => getUserCryptoBalance($_SESSION['user_id'], $cryptoType)
        ]);
    } else {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $result['message']
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Purchase failed',
        'error' => $e->getMessage()
    ]);
}
?>
