<?php
/**
 * Money Paws - Cryptocurrency-Powered Pet Platform
 * Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>
 */
require 'vendor/autoload.php';
require_once 'includes/functions.php';
require_once 'includes/crypto.php';
require_once 'includes/security.php';

use PragmaRX\Google2FA\Google2FA;

requireLogin();

$google2fa = new Google2FA();
$currentUser = getUserById($_SESSION['user_id']);
$error = '';
$success = '';
$step = $_GET['step'] ?? 'request';

// Get user crypto balances
$balances = [];
foreach (SUPPORTED_CRYPTOS as $crypto => $name) {
    $balances[$crypto] = getUserCryptoBalance($_SESSION['user_id'], $crypto);
}

$user_2fa_settings = getUser2FASettings($_SESSION['user_id']);

// Handle withdrawal request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRFToken();
    
    if (isset($_POST['request_withdrawal'])) {
        $cryptoType = sanitizeInput($_POST['crypto_type']);
        $cryptoAmount = floatval($_POST['crypto_amount']);
        $walletAddress = sanitizeInput($_POST['wallet_address']);
        
        if (!array_key_exists($cryptoType, SUPPORTED_CRYPTOS)) {
            $error = 'Invalid cryptocurrency selected.';
        } elseif ($cryptoAmount <= 0) {
            $error = 'Invalid withdrawal amount.';
        } elseif (empty($walletAddress)) {
            $error = 'Wallet address is required.';
        } else {
            $userBalance = getUserCryptoBalance($_SESSION['user_id'], $cryptoType);
            
            if ($userBalance < $cryptoAmount) {
                $error = 'Insufficient balance.';
            } else {
                // Convert to USD for limit checking
                $usdAmount = convertCryptoToUSD($cryptoAmount, $cryptoType);
                
                if ($usdAmount === null) {
                    $error = 'Unable to get crypto price. Please try again.';
                } else {
                    $limitCheck = checkWithdrawalLimits($_SESSION['user_id'], $usdAmount);
                    
                    if (!$limitCheck['allowed']) {
                        $error = $limitCheck['message'];
                    } elseif (!$user_2fa_settings['mfa_enabled']) {
                        $error = 'You must enable Two-Factor Authentication (2FA) to make a withdrawal. Please update your security settings.';
                    } else {
                        // Create withdrawal request
                        $stmt = $pdo->prepare("
                            INSERT INTO withdrawal_requests 
                            (user_id, crypto_type, crypto_amount, usd_amount, wallet_address) 
                            VALUES (?, ?, ?, ?, ?)
                        ");
                        
                        if ($stmt->execute([$_SESSION['user_id'], $cryptoType, $cryptoAmount, $usdAmount, $walletAddress])) {
                            $withdrawalId = $pdo->lastInsertId();
                            $_SESSION['withdrawal_id'] = $withdrawalId;
                            
                            logSecurityEvent($_SESSION['user_id'], 'withdrawal_requested', 
                                "Amount: $cryptoAmount $cryptoType, USD: $usdAmount");
                            
                            header('Location: withdrawal.php?step=verify');
                            exit;
                        } else {
                            $error = 'Failed to create withdrawal request.';
                        }
                    }
                }
            }
        }
    } elseif (isset($_POST['verify_withdrawal'])) {
        $withdrawalId = $_SESSION['withdrawal_id'] ?? 0;
        
        if (!$withdrawalId) {
            $error = 'Invalid withdrawal session.';
        } else {
            $verified = false;
            $verificationMethod = $user_2fa_settings['mfa_method'];

            switch ($verificationMethod) {
                case 'email':
                    $emailCode = sanitizeInput($_POST['email_code'] ?? '');
                    if (empty($emailCode)) {
                        $error = 'Please enter the email verification code.';
                    } elseif (verifyCode($_SESSION['user_id'], '2fa_email', $emailCode)) {
                        $verified = true;
                    } else {
                        $error = 'Invalid email verification code.';
                    }
                    break;

                case 'sms':
                    $smsCode = sanitizeInput($_POST['sms_code'] ?? '');
                    if (empty($smsCode)) {
                        $error = 'Please enter the SMS verification code.';
                    } elseif (verifyCode($_SESSION['user_id'], '2fa_sms', $smsCode)) {
                        $verified = true;
                    } else {
                        $error = 'Invalid SMS verification code.';
                    }
                    break;

                case 'authenticator':
                    $googleCode = sanitizeInput($_POST['google_code'] ?? '');
                    if (empty($googleCode)) {
                        $error = 'Please enter the Google Authenticator code.';
                    } elseif ($google2fa->verifyKey($user_2fa_settings['totp_secret'], $googleCode)) {
                        $verified = true;
                    } else {
                        $error = 'Invalid Google Authenticator code.';
                    }
                    break;

                default:
                    $error = 'No valid 2FA method is configured. Please check your security settings.';
                    break;
            }

            if ($verified) {
                // Update withdrawal request to be database-agnostic
                $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
                $now_function = ($driver === 'sqlite') ? "datetime('now')" : "NOW()";
                $sql = "
                    UPDATE withdrawal_requests 
                    SET status = 'verified', verification_method = ?, verification_completed_at = $now_function
                    WHERE id = ? AND user_id = ?
                ";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$verificationMethod, $withdrawalId, $_SESSION['user_id']]);
                
                logSecurityEvent($_SESSION['user_id'], 'withdrawal_verified', 
                    "Method: $verificationMethod, Request ID: $withdrawalId");
                
                $success = 'Withdrawal verified successfully! Your request is now being processed.';
                unset($_SESSION['withdrawal_id']);
                $step = 'complete';
            } elseif (!$error) {
                $error = 'Invalid verification code. Please try again.';
            }
        }
    } elseif (isset($_POST['send_email_code'])) {
        $code = generateEmailVerificationCode();
        if (storeVerificationCode($_SESSION['user_id'], '2fa_email', $code)) {
            if (sendEmailVerification($currentUser['email'], $code)) {
                $success = 'Verification code sent to your email.';
            } else {
                $error = 'Failed to send email verification code.';
            }
        } else {
            $error = 'Failed to store verification code.';
        }
    } elseif (isset($_POST['send_sms_code'])) {
        if (empty($user_2fa_settings['phone_number'])) {
            $error = 'No phone number configured. Please update your security settings.';
        } else {
            $code = generateSMSVerificationCode();
            if (storeVerificationCode($_SESSION['user_id'], '2fa_sms', $code)) {
                if (sendSMSVerification($user_2fa_settings['phone_number'], $code)) {
                    $success = 'Verification code sent to your phone.';
                } else {
                    $error = 'Failed to send SMS verification code.';
                }
            } else {
                $error = 'Failed to store verification code.';
            }
        }
    }
}

// Get pending withdrawal for verification step
$pendingWithdrawal = null;
if ($step === 'verify' && isset($_SESSION['withdrawal_id'])) {
    $stmt = $pdo->prepare("
        SELECT * FROM withdrawal_requests 
        WHERE id = ? AND user_id = ? AND status = 'pending'
    ");
    $stmt->execute([$_SESSION['withdrawal_id'], $_SESSION['user_id']]);
    $pendingWithdrawal = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Withdraw Cryptocurrency - Money Paws</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <header>
        <nav class="container">
            <a href="index.php" class="logo">🐾 Money Paws</a>
            <ul class="nav-links">
                <li><a href="index.php">Home</a></li>
                <li><a href="gallery.php">Gallery</a></li>
                <li><a href="upload.php">Upload</a></li>
                <li><a href="ai-generator.php">AI Generator</a></li>
                <li><a href="game.php">Games</a></li>
                <li><a href="store.php">Store</a></li>
                <li><a href="profile.php">Profile</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </nav>
    </header>

    <main>
        <div class="container">
            <div class="hero hero-padding">
                <h1>💸 Withdraw Cryptocurrency</h1>
                <p>Securely withdraw your crypto with multi-factor authentication</p>
            </div>

            <!-- Progress Steps -->
            <div class="withdrawal-steps">
                <div class="step <?php echo $step === 'request' ? 'active' : ($step !== 'request' ? 'completed' : ''); ?>">
                    1. Request
                </div>
                <div class="step <?php echo $step === 'verify' ? 'active' : ($step === 'complete' ? 'completed' : ''); ?>">
                    2. Verify
                </div>
                <div class="step <?php echo $step === 'complete' ? 'active' : ''; ?>">
                    3. Complete
                </div>
            </div>

            <?php if (defined('DEVELOPER_MODE') && DEVELOPER_MODE): ?>
                <div class="alert alert-info">
                    🔧 <strong>Developer Mode Active</strong> - Withdrawals are simulated for testing
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <?php if ($step === 'request'): ?>
                <!-- Step 1: Withdrawal Request -->
                <div class="card">
                    <h2>Request Withdrawal</h2>
                    
                    <div class="security-warning">
                        <h4>🔒 Security Notice</h4>
                        <p>All withdrawals require multi-factor authentication. Make sure you have access to your verification methods before proceeding.</p>
                        <ul>
                            <li>Daily limit: $<?php echo number_format(WITHDRAWAL_DAILY_LIMIT, 2); ?></li>
                            <li>Cooling period: <?php echo WITHDRAWAL_COOLING_PERIOD; ?> hours between withdrawals</li>
                            <li>Processing time: 1-24 hours after verification</li>
                        </ul>
                    </div>

                    <!-- Current Balances -->
                    <div class="withdrawal-summary">
                        <h3>Your Balances</h3>
                        <div class="balance-grid-small">
                            <?php foreach (SUPPORTED_CRYPTOS as $crypto => $name): ?>
                                <div class="balance-item-light">
                                    <h4><?php echo $crypto; ?></h4>
                                    <p class="balance-amount">
                                        <?php echo number_format($balances[$crypto], 8); ?>
                                    </p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <form method="POST">
                        <?php echo getCSRFTokenField(); ?>
                        
                        <div class="form-group">
                            <label for="crypto_type">Cryptocurrency</label>
                            <select id="crypto_type" name="crypto_type" class="form-control" required>
                                <option value="">Select cryptocurrency...</option>
                                <?php foreach (SUPPORTED_CRYPTOS as $crypto => $name): ?>
                                    <option value="<?php echo $crypto; ?>">
                                        <?php echo $crypto; ?> - <?php echo $name; ?> 
                                        (Balance: <?php echo number_format($balances[$crypto], 8); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="crypto_amount">Amount</label>
                            <input type="number" id="crypto_amount" name="crypto_amount" class="form-control" 
                                   step="0.00000001" min="0.00000001" required>
                            <small class="text-muted">Enter the amount you want to withdraw</small>
                        </div>

                        <div class="form-group">
                            <label for="wallet_address">Wallet Address</label>
                            <input type="text" id="wallet_address" name="wallet_address" class="form-control" 
                                   placeholder="Enter your wallet address" required>
                            <small class="text-muted">Double-check this address - transactions cannot be reversed!</small>
                        </div>

                        <button type="submit" name="request_withdrawal" class="btn btn-primary">
                            Continue to Verification
                        </button>
                    </form>
                </div>

            <?php elseif ($step === 'verify' && $pendingWithdrawal): ?>
                <!-- Step 2: Verification -->
                <div class="card">
                    <h2>Verify Withdrawal</h2>
                    
                    <div class="withdrawal-summary">
                        <h3>Withdrawal Summary</h3>
                        <p><strong>Amount:</strong> <?php echo number_format($pendingWithdrawal['crypto_amount'], 8); ?> <?php echo $pendingWithdrawal['crypto_type']; ?></p>
                        <p><strong>USD Value:</strong> $<?php echo number_format($pendingWithdrawal['usd_amount'], 2); ?></p>
                        <p><strong>Wallet:</strong> <?php echo htmlspecialchars($pendingWithdrawal['wallet_address']); ?></p>
                    </div>

                    <p>Please verify this withdrawal using one of the methods below:</p>

                    <form method="POST">
                        <?php echo getCSRFTokenField(); ?>
                        
                        <div class="verification-methods">
                            <?php 
                            switch ($user_2fa_settings['mfa_method']): 
                                case 'email': 
                            ?>
                                    <div class="verification-method enabled">
                                        <h4>📧 Email Verification</h4>
                                        <p>A verification code will be sent to your email: <strong><?php echo htmlspecialchars($currentUser['email']); ?></strong></p>
                                        <div class="input-group-flex">
                                            <div class="flex-grow-1">
                                                <input type="text" name="email_code" class="form-control" placeholder="Enter email code" maxlength="6" autofocus>
                                            </div>
                                            <button type="submit" name="send_email_code" class="btn btn-secondary">Send Code</button>
                                        </div>
                                    </div>
                            <?php 
                                    break;
                                case 'sms': 
                            ?>
                                    <div class="verification-method <?php echo !empty($user_2fa_settings['phone_number']) ? 'enabled' : 'disabled'; ?>">
                                        <h4>📱 SMS Verification</h4>
                                        <?php if (!empty($user_2fa_settings['phone_number'])): ?>
                                            <p>A verification code will be sent to your phone: <strong><?php echo htmlspecialchars($user_2fa_settings['phone_number']); ?></strong></p>
                                            <div class="input-group-flex">
                                                <div class="flex-grow-1">
                                                    <input type="text" name="sms_code" class="form-control" placeholder="Enter SMS code" maxlength="6" autofocus>
                                                </div>
                                                <button type="submit" name="send_sms_code" class="btn btn-secondary">Send Code</button>
                                            </div>
                                        <?php else: ?>
                                            <p class="text-muted">No phone number configured for SMS verification. <a href="security.php">Update security settings</a>.</p>
                                        <?php endif; ?>
                                    </div>
                            <?php 
                                    break;
                                case 'authenticator': 
                            ?>
                                    <div class="verification-method enabled">
                                        <h4>🔐 Google Authenticator</h4>
                                        <p>Enter the 6-digit code from your authenticator app.</p>
                                        <input type="text" name="google_code" class="form-control" placeholder="Enter authenticator code" maxlength="6" pattern="[0-9]{6}" required autofocus>
                                    </div>
                            <?php 
                                    break;
                            endswitch; 
                            ?>
                        </div>

                        <div class="mt-4">
                            <button type="submit" name="verify_withdrawal" class="btn btn-primary">
                                Verify Withdrawal
                            </button>
                            <a href="withdrawal.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>

            <?php elseif ($step === 'complete'): ?>
                <!-- Step 3: Complete -->
                <div class="card">
                    <div class="text-center">
                        <h2>✅ Withdrawal Verified</h2>
                        <p>Your withdrawal request has been verified and is now being processed.</p>
                        <p>You will receive an email confirmation once the transaction is completed.</p>
                        
                        <div class="mt-4">
                            <a href="profile.php" class="btn btn-primary">View Profile</a>
                            <a href="withdrawal.php" class="btn btn-secondary">New Withdrawal</a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; 2024 Money Paws. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>
