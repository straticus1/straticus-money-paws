<?php
/**
 * Money Paws - Cryptocurrency-Powered Pet Platform
 * Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>
 */
require_once 'includes/functions.php';
require_once 'includes/crypto.php';

requireLogin();

$currentUser = getUserById($_SESSION['user_id']);
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['deposit'])) {
    requireCSRFToken();
    $cryptoType = sanitizeInput($_POST['crypto_type']);
    $usdAmount = floatval($_POST['usd_amount']);
    
    if (!array_key_exists($cryptoType, SUPPORTED_CRYPTOS)) {
        $error = 'Invalid cryptocurrency selected.';
    } elseif ($usdAmount < 1 || $usdAmount > 1000) {
        $error = 'Deposit amount must be between $1 and $1000.';
    } else {
        $result = initiateCoinbaseDeposit($_SESSION['user_id'], $cryptoType, $usdAmount);
        
        if ($result['success']) {
            redirectTo($result['hosted_url']);
        } else {
            $error = $result['message'];
        }
    }
}

// Get user crypto balances
$balances = [];
foreach (SUPPORTED_CRYPTOS as $crypto => $name) {
    $balances[$crypto] = getUserCryptoBalance($_SESSION['user_id'], $crypto);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deposit Funds - Money Paws</title>
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
                <li><a href="profile.php">Profile</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </nav>
    </header>

    <main>
        <div class="container">
            <div class="hero hero-padding">
                <h1>💰 Add Funds</h1>
                <p>Deposit cryptocurrency to play games and generate AI pets</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <div class="form-container">
                <div class="card">
                    <h2>Current Balances</h2>
                    <div class="balance-grid-large">
                        <?php foreach (SUPPORTED_CRYPTOS as $crypto => $name): ?>
                            <div class="balance-item-large">
                                <h4><?php echo $crypto; ?></h4>
                                <p class="balance-amount-large">
                                    <?php echo number_format($balances[$crypto], 8); ?>
                                </p>
                                <small class="text-muted"><?php echo $name; ?></small>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <h2>Make a Deposit</h2>
                    <form action="deposit.php" method="POST" id="depositForm">
                        <?php echo getCSRFTokenField(); ?>
                        <div class="form-group">
                            <label for="usd_amount">Deposit Amount (USD)</label>
                            <input type="number" id="usd_amount" name="usd_amount" class="form-control" 
                                   min="1" max="1000" step="0.01" placeholder="25.00" required>
                            <small class="text-muted">Minimum: $1.00 | Maximum: $1000.00</small>
                        </div>

                        <div class="form-group">
                            <label>Choose Cryptocurrency</label>
                            <div class="crypto-options-grid">
                                <?php foreach (SUPPORTED_CRYPTOS as $crypto => $name): ?>
                                                                        <div class="crypto-option" data-crypto="<?php echo $crypto; ?>">
                                        <h4><?php echo $crypto; ?></h4>
                                        <p><?php echo $name; ?></p>
                                        <div id="crypto-amount-<?php echo $crypto; ?>" class="crypto-amount-display">
                                            <!-- Amount will be calculated here -->
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <input type="hidden" name="crypto_type" id="selectedCrypto" value="">
                        </div>

                        <div class="alert alert-info">
                            <h4>🔒 Secure Payment via Coinbase</h4>
                            <p>Your deposit will be processed securely through Coinbase Commerce. You can pay with any supported cryptocurrency wallet.</p>
                            <ul class="info-list">
                                <li>✅ Secure and encrypted transactions</li>
                                <li>✅ No personal crypto wallet required</li>
                                <li>✅ Instant balance updates upon confirmation</li>
                                <li>✅ Support for all major wallets</li>
                            </ul>
                        </div>

                        <button type="submit" name="deposit" class="btn btn-primary btn-block btn-lg" disabled id="depositBtn">
                            🚀 Proceed to Coinbase Payment
                        </button>
                    </form>
                </div>

                <div class="card">
                    <h2>💡 Why Add Funds?</h2>
                    <div class="features-grid">
                        <div class="feature-item">
                            <div class="feature-item-emoji">🎮</div>
                            <h4>Play Games</h4>
                            <p>Entry fee: $<?php echo GAME_ENTRY_FEE; ?> per game</p>
                            <p>Win up to $5.00 in rewards!</p>
                        </div>
                        <div class="feature-item">
                            <div class="feature-item-emoji">🎨</div>
                            <h4>Generate AI Pets</h4>
                            <p>Cost: $<?php echo AI_PET_GENERATION_PRICE; ?> per generation</p>
                            <p>Create unique AI artwork!</p>
                        </div>
                        <div class="feature-item">
                            <div class="feature-item-emoji">⭐</div>
                            <h4>Premium Features</h4>
                            <p>Unlock: $<?php echo PREMIUM_UPLOAD_PRICE; ?> per upload</p>
                            <p>Enhanced gallery features!</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; 2024 Money Paws. All rights reserved.</p>
        </div>
    </footer>

    <script src="assets/js/deposit.js"></script>
</body>
</html>
