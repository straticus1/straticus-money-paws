<?php
/**
 * Money Paws - Smart Gaming Hub (Legacy Redirect)
 * Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>
 */
require_once 'includes/functions.php';
require_once 'includes/adaptive_gaming.php';
require_once 'includes/daily_quests.php';

requireLogin();

// Redirect to new adaptive gaming system
header('Location: adaptive_gaming.php');
exit;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paw Games - Money Paws</title>
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
                <li><a href="game.php">Games</a></li>
                <li><a href="profile.php">Profile</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </nav>
    </header>

    <main>
        <div class="container">
            <div class="hero hero-padding">
                <h1>🎮 Paw Games</h1>
                <p>Play addictive games and win crypto rewards!</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <div class="balance-display">
                <h3>Your Crypto Balances</h3>
                <div class="balance-grid">
                    <?php foreach (SUPPORTED_CRYPTOS as $crypto => $name): ?>
                        <div class="balance-item">
                            <h4><?php echo $crypto; ?></h4>
                            <p class="balance-amount">
                                <?php echo number_format($balances[$crypto], 8); ?>
                            </p>
                            <small class="text-muted"><?php echo $name; ?></small>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="text-center mt-3">
                    <a href="deposit.php" class="btn btn-primary">Add Funds</a>
                </div>
            </div>

            <div class="game-card">
                
                <?php if (defined('DEVELOPER_MODE') && DEVELOPER_MODE): ?>
                    <div class="alert alert-info">
                        🔧 <strong>Developer Mode Active</strong> - Free play enabled for testing
                    </div>
                <?php endif; ?>
                
                                <form method="POST" class="game-form">
                    <?php echo getCSRFTokenField(); ?>
                    <div class="form-group">
                        <label>Entry Fee <?php echo (defined('DEVELOPER_MODE') && DEVELOPER_MODE) ? '(FREE in Developer Mode)' : ''; ?></label>
                        <div class="crypto-selector">
                            <?php foreach (SUPPORTED_CRYPTOS as $crypto => $name): ?>
                                                                <div class="crypto-option" data-crypto="<?php echo $crypto; ?>">
                                    <div class="crypto-name"><?php echo $crypto; ?></div>
                                    <div class="crypto-amount" id="amount-<?php echo $crypto; ?>">
                                        <?php echo (defined('DEVELOPER_MODE') && DEVELOPER_MODE) ? 'FREE' : '-'; ?>
                                    </div>
                                    <div class="balance">Balance: <?php echo number_format($balances[$crypto], 8); ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <input type="hidden" name="crypto_type" id="selectedCrypto" value="">
                    </div>
                    
                    <button type="submit" name="play_game" class="btn btn-primary btn-large" disabled id="playBtn">
                        🎮 Play Game <?php echo (defined('DEVELOPER_MODE') && DEVELOPER_MODE) ? '(FREE)' : ''; ?>
                    </button>
                </form>
            </div>

            <div class="game-card">
                <h2>⚔️ Pet Battle Arena</h2>
                <p>Battle your AI pets against others in epic competitions!</p>
                <p><strong>Entry Fee:</strong> $<?php echo GAME_ENTRY_FEE; ?> (in crypto)</p>
                <p><strong>Status:</strong> <span class="status-coming-soon">Coming Soon!</span></p>
                <button class="btn btn-secondary" disabled>Coming Soon</button>
            </div>

            <div class="game-card">
                <h2>🏴‍☠️ Treasure Hunt</h2>
                <p>Search for hidden treasures with your virtual pets!</p>
                <p><strong>Entry Fee:</strong> $<?php echo GAME_ENTRY_FEE; ?> (in crypto)</p>
                <p><strong>Status:</strong> <span class="status-coming-soon">Coming Soon!</span></p>
                <button class="btn btn-secondary" disabled>Coming Soon</button>
            </div>

            <div class="card">
                <h2>🏆 Recent Winners</h2>
                <div id="recentWinners">
                    <div class="no-pets-container">
                        <p class="no-pets-icon">🏆</p>
                        <p>No games played yet. Be the first winner!</p>
                    </div>
                </div>
            </div>

            <div class="card">
                <h2>📊 Game Statistics</h2>
                <div class="stats-grid">
                    <div class="stat-item stat-item-blue">
                        <h3>0</h3>
                        <p>Games Played</p>
                    </div>
                    <div class="stat-item stat-item-green">
                        <h3>$0.00</h3>
                        <p>Total Winnings</p>
                    </div>
                    <div class="stat-item stat-item-yellow">
                        <h3>0</h3>
                        <p>High Score</p>
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

    <script src="assets/js/game.js"></script>
</body>
</html>
