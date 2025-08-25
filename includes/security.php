<?php
/**
 * Money Paws - Cryptocurrency-Powered Pet Platform
 * Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>
 */
// Security functions for CSRF protection and 2FA

function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function getCSRFTokenField() {
    $token = generateCSRFToken();
    return '<input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="' . htmlspecialchars($token) . '">';
}

function requireCSRFToken() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST[CSRF_TOKEN_NAME] ?? '';
        if (!validateCSRFToken($token)) {
            die('CSRF token validation failed. Please refresh the page and try again.');
        }
    }
}

// 2FA Functions
function generate2FASecret() {
    return bin2hex(random_bytes(16));
}

function generateEmailVerificationCode() {
    return sprintf('%06d', mt_rand(100000, 999999));
}

function generateSMSVerificationCode() {
    return sprintf('%06d', mt_rand(100000, 999999));
}

function sendEmailVerification($email, $code) {
    $subject = 'Money Paws - Withdrawal Verification Code';
    $message = "Your withdrawal verification code is: $code\n\nThis code will expire in 10 minutes.\n\nIf you didn't request this withdrawal, please contact support immediately.";
    $headers = 'From: security@paws.money' . "\r\n" .
               'Reply-To: security@paws.money' . "\r\n" .
               'X-Mailer: PHP/' . phpversion();
    
    return mail($email, $subject, $message, $headers);
}

function sendSMSVerification($phone, $code) {
    // This would integrate with an SMS service like Twilio
    // For now, we'll simulate it
    if (defined('DEVELOPER_MODE') && DEVELOPER_MODE) {
        error_log("SMS Verification Code for $phone: $code");
        return true;
    }
    
    // TODO: Implement actual SMS sending with Twilio or similar service
    return false;
}

function verifyGoogleAuthenticator($secret, $code) {
    // Simple TOTP implementation for Google Authenticator
    $timeSlice = floor(time() / 30);
    
    // Check current time slice and previous/next for clock drift
    for ($i = -1; $i <= 1; $i++) {
        $calculatedCode = generateTOTP($secret, $timeSlice + $i);
        if (hash_equals($calculatedCode, $code)) {
            return true;
        }
    }
    
    return false;
}

function generateTOTP($secret, $timeSlice) {
    $key = hex2bin($secret);
    $time = pack('N*', 0) . pack('N*', $timeSlice);
    $hash = hash_hmac('sha1', $time, $key, true);
    $offset = ord($hash[19]) & 0xf;
    $code = (
        ((ord($hash[$offset + 0]) & 0x7f) << 24) |
        ((ord($hash[$offset + 1]) & 0xff) << 16) |
        ((ord($hash[$offset + 2]) & 0xff) << 8) |
        (ord($hash[$offset + 3]) & 0xff)
    ) % 1000000;
    
    return sprintf('%06d', $code);
}

function storeVerificationCode($userId, $type, $code, $expiryMinutes = 10) {
    global $pdo;

    $expiryTime = date('Y-m-d H:i:s', time() + ($expiryMinutes * 60));
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

    if ($driver === 'sqlite') {
        // SQLite uses REPLACE which is a bit different but works for this case
        // It deletes the old row (based on UNIQUE constraint) and inserts a new one.
        // We need a UNIQUE constraint on (user_id, code_type) for this to work as expected.
        $stmt = $pdo->prepare("
            REPLACE INTO verification_codes (user_id, code_type, code, expires_at, created_at)
            VALUES (?, ?, ?, ?, datetime('now'))
        ");
    } else {
        // MySQL-specific query
        $stmt = $pdo->prepare("
            INSERT INTO verification_codes (user_id, code_type, code, expires_at) 
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE code = VALUES(code), expires_at = VALUES(expires_at), created_at = NOW()
        ");
    }
    
    return $stmt->execute([$userId, $type, $code, $expiryTime]);
}

function verifyCode($userId, $type, $code) {
    global $pdo;
    
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $now_function = ($driver === 'sqlite') ? "datetime('now')" : "NOW()";

    $sql = "
        SELECT id FROM verification_codes 
        WHERE user_id = ? AND code_type = ? AND code = ? AND expires_at > $now_function
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId, $type, $code]);
    
    if ($stmt->fetch()) {
        // Delete used code to prevent reuse
        $delete_stmt = $pdo->prepare("DELETE FROM verification_codes WHERE user_id = ? AND code_type = ? AND code = ?");
        $delete_stmt->execute([$userId, $type, $code]);
        return true;
    }
    
    return false;
}

function checkWithdrawalLimits($userId, $usdAmount) {
    global $pdo;
    
    // Check daily limit
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(usd_amount), 0) as daily_total
        FROM crypto_transactions 
        WHERE user_id = ? AND transaction_type = 'withdrawal' 
        AND DATE(created_at) = CURDATE() AND status = 'completed'
    ");
    $stmt->execute([$userId]);
    $dailyTotal = $stmt->fetchColumn();
    
    if ($dailyTotal + $usdAmount > WITHDRAWAL_DAILY_LIMIT) {
        return [
            'allowed' => false,
            'message' => 'Daily withdrawal limit exceeded. Limit: $' . number_format(WITHDRAWAL_DAILY_LIMIT, 2) . 
                        ', Used: $' . number_format($dailyTotal, 2)
        ];
    }
    
    // Check cooling period
    $stmt = $pdo->prepare("
        SELECT created_at FROM crypto_transactions 
        WHERE user_id = ? AND transaction_type = 'withdrawal' AND status = 'completed'
        ORDER BY created_at DESC LIMIT 1
    ");
    $stmt->execute([$userId]);
    $lastWithdrawal = $stmt->fetchColumn();
    
    if ($lastWithdrawal) {
        $hoursSinceLastWithdrawal = (time() - strtotime($lastWithdrawal)) / 3600;
        if ($hoursSinceLastWithdrawal < WITHDRAWAL_COOLING_PERIOD) {
            $hoursRemaining = WITHDRAWAL_COOLING_PERIOD - $hoursSinceLastWithdrawal;
            return [
                'allowed' => false,
                'message' => 'Withdrawal cooling period active. Please wait ' . 
                           number_format($hoursRemaining, 1) . ' more hours.'
            ];
        }
    }
    
    return ['allowed' => true];
}


function logSecurityEvent($userId, $event, $details = '') {
    global $pdo;
    
    $stmt = $pdo->prepare("
        INSERT INTO security_logs (user_id, event_type, details, ip_address, user_agent)
        VALUES (?, ?, ?, ?, ?)
    ");
    
    return $stmt->execute([
        $userId,
        $event,
        $details,
        $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ]);
}
?>
