<?php
/**
 * Money Paws - Cryptocurrency-Powered Pet Platform
 * Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>
 */
require_once 'functions.php';
require_once 'coinbase_commerce.php';

// Coinbase integration functions
function getCryptoPrice($cryptoType) {
    // TODO: Re-implement live price fetching from a reliable API
    // The previous implementation was using a deprecated and broken API call.

    // Fallback mock rates
    $mockRates = [
        'BTC' => 45000.00,
        'ETH' => 3000.00,
        'USDC' => 1.00,
        'SOL' => 100.00,
        'XRP' => 0.50
    ];
    
    return $mockRates[$cryptoType] ?? 1.00;
}

function convertUSDToCrypto($usdAmount, $cryptoType) {
    // This would typically call a real crypto price API
    // For demo purposes, using mock exchange rates
    $mockRates = [
        'BTC' => 45000.00,
        'ETH' => 3000.00,
        'USDC' => 1.00,
        'SOL' => 100.00,
        'XRP' => 0.50
    ];
    
    if (!isset($mockRates[$cryptoType])) {
        return null;
    }
    
    return $usdAmount / $mockRates[$cryptoType];
}

function convertCryptoToUSD($cryptoAmount, $cryptoType) {
    // This would typically call a real crypto price API
    // For demo purposes, using mock exchange rates
    $mockRates = [
        'BTC' => 45000.00,
        'ETH' => 3000.00,
        'USDC' => 1.00,
        'SOL' => 100.00,
        'XRP' => 0.50
    ];
    
    if (!isset($mockRates[$cryptoType])) {
        return null;
    }
    
    return $cryptoAmount * $mockRates[$cryptoType];
}


function updateUserBalance($userId, $cryptoType, $amount, $operation = 'add') {
    global $pdo;
    
    // In developer mode, simulate balance changes without actual database updates
    if (defined('DEVELOPER_MODE') && DEVELOPER_MODE) {
        return true; // Always succeed in developer mode
    }
    
    try {
        $pdo->beginTransaction();
        
        // Get current balance
        $stmt = $pdo->prepare("SELECT balance FROM user_balances WHERE user_id = ? AND crypto_type = ?");
        $stmt->execute([$userId, $cryptoType]);
        $currentBalance = $stmt->fetchColumn();
        
        if ($currentBalance === false) {
            // Create new balance record
            $newBalance = ($operation === 'add') ? $amount : 0;
            $stmt = $pdo->prepare("INSERT INTO user_balances (user_id, crypto_type, balance) VALUES (?, ?, ?)");
            $stmt->execute([$userId, $cryptoType, $newBalance]);
        } else {
            // Update existing balance
            $newBalance = ($operation === 'add') ? $currentBalance + $amount : $currentBalance - $amount;
            $newBalance = max(0, $newBalance); // Prevent negative balances
            
            $stmt = $pdo->prepare("UPDATE user_balances SET balance = ? WHERE user_id = ? AND crypto_type = ?");
            $stmt->execute([$newBalance, $userId, $cryptoType]);
        }
        
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollback();
        return false;
    }
}

function createCryptoTransaction($userId, $type, $cryptoType, $cryptoAmount, $usdAmount, $coinbaseId = null) {
    global $pdo;
    
    // In developer mode, simulate transactions without database records
    if (defined('DEVELOPER_MODE') && DEVELOPER_MODE) {
        return true; // Always succeed in developer mode
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO crypto_transactions 
        (user_id, transaction_type, crypto_type, crypto_amount, usd_amount, coinbase_transaction_id, status) 
        VALUES (?, ?, ?, ?, ?, ?, 'pending')
    ");
    
    return $stmt->execute([$userId, $type, $cryptoType, $cryptoAmount, $usdAmount, $coinbaseId]);
}

function initiateCoinbaseDeposit($userId, $cryptoType, $usdAmount) {
    $charge = create_coinbase_charge($usdAmount, 'USD', 'Money Paws Deposit', 'Deposit funds to Money Paws account');

    if ($charge && isset($charge['data']['id'])) {
        $chargeData = $charge['data'];
        // The actual crypto amount is determined by Coinbase at the time of payment.
        // We will store the USD amount and update the crypto amount upon webhook confirmation.
        createCryptoTransaction($userId, 'deposit', $cryptoType, 0, $usdAmount, $chargeData['id']);

        return [
            'success' => true,
            'charge_id' => $chargeData['id'],
            'hosted_url' => $chargeData['hosted_url'],
        ];
    }

    return ['success' => false, 'message' => 'Failed to create Coinbase charge'];
}

function processCoinbaseWebhook($payload, $signature) {
    if (!verify_coinbase_webhook($payload, $signature)) {
        return false;
    }

    $data = json_decode($payload, true);
    
    if ($data['event']['type'] === 'charge:confirmed') {
        $chargeId = $data['event']['data']['id'];
        $userId = $data['event']['data']['metadata']['user_id'];
        $cryptoType = $data['event']['data']['metadata']['crypto_type'];
        
        // Update transaction status
        global $pdo;
        $stmt = $pdo->prepare("
            UPDATE crypto_transactions 
            SET status = 'confirmed', confirmed_at = NOW() 
            WHERE coinbase_transaction_id = ?
        ");
        $stmt->execute([$chargeId]);
        
        // Get transaction details
        $stmt = $pdo->prepare("
            SELECT crypto_amount, crypto_type 
            FROM crypto_transactions 
            WHERE coinbase_transaction_id = ?
        ");
        $stmt->execute([$chargeId]);
        $transaction = $stmt->fetch();
        
        if ($transaction) {
            // Update user balance
            updateUserBalance($userId, $transaction['crypto_type'], $transaction['crypto_amount'], 'add');
        }
    }
    
    return true;
}
?>
