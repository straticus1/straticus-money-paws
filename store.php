<?php
/**
 * Money Paws - Cryptocurrency-Powered Pet Platform
 * Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>
 */
require_once 'includes/functions.php';
require_once 'includes/crypto.php';
require_once 'includes/pet_care.php';

requireLogin();

$currentUser = getUserById($_SESSION['user_id']);
$error = '';
$success = '';

// Get user crypto balances
$balances = [];
foreach (SUPPORTED_CRYPTOS as $crypto => $name) {
    $balances[$crypto] = getUserCryptoBalance($_SESSION['user_id'], $crypto);
}

// Handle purchase
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['purchase_item'])) {
    requireCSRFToken();
    $itemId = intval($_POST['item_id']);
    $quantity = max(1, intval($_POST['quantity']));
    $cryptoType = sanitizeInput($_POST['crypto_type']);
    
    if (!array_key_exists($cryptoType, SUPPORTED_CRYPTOS)) {
        $error = 'Invalid cryptocurrency selected.';
    } else {
        $result = purchaseStoreItem($_SESSION['user_id'], $itemId, $quantity, $cryptoType);
        
        if ($result['success']) {
            $success = "Successfully purchased {$quantity}x {$result['item']['name']} for {$result['crypto_amount']} {$cryptoType}!";
        } else {
            $error = $result['message'];
        }
    }
}

$storeItems = getStoreItems();
$userInventory = getUserInventory($_SESSION['user_id']);

// Group items by type
$itemsByType = [];
foreach ($storeItems as $item) {
    $itemsByType[$item['item_type']][] = $item;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pet Store - Money Paws</title>
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
                <h1>🛒 Pet Store</h1>
                <p>Buy food, treats, and toys for your pets and others!</p>
            </div>

            <?php if (defined('DEVELOPER_MODE') && DEVELOPER_MODE): ?>
                <div class="alert alert-info">
                    🔧 <strong>Developer Mode Active</strong> - All purchases are FREE for testing
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <!-- User Balances -->
            <div class="card">
                <h3>Your Crypto Balances</h3>
                <div class="balance-grid-small">
                    <?php foreach (SUPPORTED_CRYPTOS as $crypto => $name): ?>
                        <div class="balance-item">
                            <h4><?php echo $crypto; ?></h4>
                            <p class="balance-amount">
                                <?php echo number_format($balances[$crypto], 8); ?>
                            </p>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="text-center mt-3">
                    <a href="deposit.php" class="btn btn-primary">Add Funds</a>
                </div>
            </div>

            <!-- User Inventory -->
            <?php if (!empty($userInventory)): ?>
                <div class="card">
                    <h2>🎒 Your Inventory</h2>
                    <div class="inventory-grid">
                        <?php foreach ($userInventory as $item): ?>
                            <div class="inventory-item">
                                <div class="quantity-badge"><?php echo $item['quantity']; ?></div>
                                <div class="inventory-item-emoji"><?php echo $item['emoji']; ?></div>
                                <h4><?php echo htmlspecialchars($item['name']); ?></h4>
                                <p class="text-muted"><?php echo htmlspecialchars($item['description']); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Store Items -->
            <?php foreach ($itemsByType as $type => $items): ?>
                <div class="store-section">
                    <div class="card">
                        <h2>
                            <?php 
                            $typeEmojis = [
                                'food' => '🍖',
                                'treat' => '🥓',
                                'toy' => '🎾',
                                'accessory' => '🎀'
                            ];
                            echo $typeEmojis[$type] ?? '🛍️';
                            ?> 
                            <?php echo ucfirst($type); ?>s
                        </h2>
                        
                        <div class="item-grid">
                            <?php foreach ($items as $item): ?>
                                <div class="item-card">
                                    <div class="item-emoji"><?php echo $item['emoji']; ?></div>
                                    <h3><?php echo htmlspecialchars($item['name']); ?></h3>
                                    <p class="text-muted mb-3"><?php echo htmlspecialchars($item['description']); ?></p>
                                    
                                    <div class="item-stats">
                                        <?php if ($item['hunger_restore'] > 0): ?>
                                            <div class="stat">
                                                <div class="stat-value stat-value-green">+<?php echo $item['hunger_restore']; ?></div>
                                                <div class="stat-label">Hunger</div>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($item['happiness_boost'] > 0): ?>
                                            <div class="stat">
                                                <div class="stat-value stat-value-yellow">+<?php echo $item['happiness_boost']; ?></div>
                                                <div class="stat-label">Happiness</div>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($item['duration_hours'] > 0): ?>
                                            <div class="stat">
                                                <div class="stat-value stat-value-blue"><?php echo $item['duration_hours']; ?>h</div>
                                                <div class="stat-label">Duration</div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="text-center mt-3">
                                        <div class="item-price">
                                            <?php if (defined('DEVELOPER_MODE') && DEVELOPER_MODE): ?>
                                                <span class="item-price-free">FREE</span>
                                                <div class="item-price-original">
                                                    $<?php echo number_format($item['price_usd'], 2); ?>
                                                </div>
                                            <?php else: ?>
                                                $<?php echo number_format($item['price_usd'], 2); ?>
                                            <?php endif; ?>
                                        </div>
                                        
                                                                                <button data-item='<?php echo htmlspecialchars(json_encode($item)); ?>' class="btn btn-primary purchase-btn">
                                            <?php echo (defined('DEVELOPER_MODE') && DEVELOPER_MODE) ? 'Get FREE' : 'Buy Now'; ?>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </main>

    <!-- Purchase Modal -->
    <div id="purchaseModal" class="modal">
        <div class="modal-content">
                        <span class="close" id="closeModalBtn">&times;</span>
            
            <div id="modalContent">
                <h2>Purchase Item</h2>
                                <form method="POST" id="purchaseForm">
                    <?php echo getCSRFTokenField(); ?>
                    <input type="hidden" name="item_id" id="modalItemId">
                    
                    <div id="itemDisplay" class="text-center mb-4">
                        <!-- Item details will be populated here -->
                    </div>
                    
                    <div class="form-group">
                        <label for="quantity">Quantity</label>
                                                <input type="number" id="quantity" name="quantity" class="form-control" min="1" max="10" value="1">
                    </div>
                    
                    <div class="form-group">
                        <label>Payment Method</label>
                        <div class="crypto-options-grid">
                            <?php foreach (SUPPORTED_CRYPTOS as $crypto => $name): ?>
                                                                <div class="crypto-option" data-crypto="<?php echo $crypto; ?>">
                                    <div class="crypto-name"><?php echo $crypto; ?></div>
                                    <div class="crypto-amount" id="crypto-amount-<?php echo $crypto; ?>">-</div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <input type="hidden" name="crypto_type" id="selectedCrypto" value="">
                    </div>
                    
                    <div id="totalCost" class="total-cost">
                        Total: $0.00
                    </div>
                    
                    <button type="submit" name="purchase_item" class="btn btn-primary btn-block" disabled id="purchaseBtn">
                        Complete Purchase
                    </button>
                </form>
            </div>
        </div>
    </div>

    <footer>
        <div class="container">
            <p>&copy; 2024 Money Paws. All rights reserved.</p>
        </div>
    </footer>

    <script>
        const SUPPORTED_CRYPTOS = <?php echo json_encode(array_keys(SUPPORTED_CRYPTOS)); ?>;
    </script>
    <script src="assets/js/store.js"></script>
</body>
</html>
