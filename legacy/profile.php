<?php
/**
 * Money Paws - Public User Profile Page
 * Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>
 */
require_once 'includes/functions.php';

// Determine the user ID to display
$profile_user_id = null;
if (isset($_GET['id'])) {
    $profile_user_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
} elseif (isLoggedIn()) {
    $profile_user_id = $_SESSION['user_id'];
} else {
    // If no ID is specified and user is not logged in, redirect or show error
    redirectTo('gallery.php'); // Redirect to gallery as a sensible default
}

// Check if the viewer is the owner of the profile
$is_own_profile = isLoggedIn() && ($profile_user_id == $_SESSION['user_id']);

// Fetch user data
$profile_user = getUserById($profile_user_id);

// Fetch 2FA settings for the user
$user_2fa_settings = $is_own_profile ? getUser2FASettings($profile_user_id) : null;

// If user doesn't exist, handle it gracefully
if (!$profile_user) {
    http_response_code(404);
    include('includes/header.php'); // Assuming a standard header
    echo "<main><div class='container'><div class='alert alert-error'>User not found.</div></div></main>";
    include('includes/footer.php'); // Assuming a standard footer
    exit;
}

// Fetch user's pets (show all for owner, only public for others)
$userPets = $is_own_profile ? getUserPets($profile_user_id) : getPublicUserPets($profile_user_id);

// Calculate user stats from the fetched pets
$totalViews = array_sum(array_column($userPets, 'views_count'));
$totalLikes = array_sum(array_column($userPets, 'likes_count'));

$pageTitle = htmlspecialchars($profile_user['name']) . "'s Profile";
require_once 'includes/html_head.php';
?>
<?php require_once 'includes/header.php'; ?>

    <main>
        <div class="container">
                        <div class="hero profile-hero">
                <h1><?php echo htmlspecialchars($profile_user['name']); ?>'s Profile</h1>
                <p>A member of the Money Paws community</p>
            </div>

            <div class="card">
                <div class="profile-header">
                    <div class="profile-avatar">
                        <?php echo strtoupper(substr($profile_user['name'], 0, 1)); ?>
                    </div>
                    <div>
                        <h2><?php echo htmlspecialchars($profile_user['name']); ?></h2>
                        <?php if ($is_own_profile): ?>
                            <p class="text-muted"><?php echo htmlspecialchars($profile_user['email']); ?></p>
                        <?php endif; ?>
                                                <p class="text-muted text-small">
                            Member since <?php echo date('F Y', strtotime($profile_user['created_at'])); ?>
                        </p>
                    </div>
                </div>

                <div class="stats-grid">
                    <div class="stat-box">
                        <h3><?php echo count($userPets); ?></h3>
                        <p>Pets</p>
                    </div>
                    <div class="stat-box">
                        <h3><?php echo $totalViews; ?></h3>
                        <p>Total Views</p>
                    </div>
                    <div class="stat-box">
                        <h3><?php echo $totalLikes; ?></h3>
                        <p>Total Likes</p>
                    </div>
                </div>

                <?php if ($is_own_profile): ?>
                <div class="profile-actions">
                    <a href="upload.php" class="btn btn-primary">Upload New Pet</a>
                    <a href="edit-profile.php" class="btn btn-secondary">Edit Profile</a>
                </div>
                <?php elseif (isLoggedIn()): ?>
                <div class="profile-actions">
                                        <a href="start_conversation.php?id=<?php echo htmlspecialchars($profile_user['id']); ?>" class="btn btn-primary">Message <?php echo htmlspecialchars($profile_user['name']); ?></a>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($is_own_profile): ?>
            <div class="card">
                <h2>💰 Crypto Wallets & Balance</h2>
                <p>Manage your wallets and balances. This section is only visible to you.</p>
                
                <div class="wallets-grid">
                    <!-- Ethereum Wallet -->
                    <div class="wallet-box wallet-box-eth">
                        <div class="icon">⟠</div>
                        <h3>Ethereum (ETH)</h3>
                        <p>Connect MetaMask or other Ethereum wallet</p>
                        <button id="connectEthWallet" class="btn btn-primary">Connect Ethereum Wallet</button>
                        <div id="ethWalletStatus" class="wallet-status"></div>
                    </div>

                    <!-- Solana Wallet -->
                    <div class="wallet-box wallet-box-sol">
                        <div class="icon">◎</div>
                        <h3>Solana (SOL)</h3>
                        <p>Connect Phantom or other Solana wallet</p>
                                                <button id="connectSolWallet" class="btn btn-solana">Connect Solana Wallet</button>
                        <div id="solWalletStatus" class="wallet-status"></div>
                    </div>
                </div>

                <div class="balance-box">
                    <h3>Your Balance</h3>
                    <div class="balance-grid">
                        <?php foreach (SUPPORTED_CRYPTOS as $symbol => $name): ?>
                            <div class="balance-item">
                                                                <strong><?php echo htmlspecialchars($symbol); ?></strong><br>
                                <span>
                                    <?php echo number_format(getUserCryptoBalance($_SESSION['user_id'], $symbol), 4); ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                                        <div class="profile-actions mt-3">
                        <a href="deposit.php" class="btn btn-success">💳 Deposit</a>
                        <a href="withdrawal.php" class="btn btn-secondary">💸 Withdraw</a>
                        <a href="game.php" class="btn btn-primary">🎮 Play Games</a>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($is_own_profile): ?>
            <div class="card">
                <h2>🔒 Security Settings</h2>
                <p>Enhance your account security by enabling Two-Factor Authentication (2FA). This section is only visible to you.</p>

                <div class="security-setting-item">
                    <h4>Two-Factor Authentication (2FA)</h4>
                    <div class="setting-status">
                        <span class="status-label <?php echo $user_2fa_settings['mfa_enabled'] ? 'text-success' : 'text-danger'; ?>">
                            <?php echo $user_2fa_settings['mfa_enabled'] ? 'Enabled' : 'Disabled'; ?>
                        </span>
                        <a href="security.php" class="btn btn-secondary btn-sm">Manage</a>
                    </div>
                </div>

                <?php if ($user_2fa_settings['mfa_enabled']): ?>
                <div class="security-setting-item">
                    <h4>Active Method</h4>
                    <p>Your active 2FA method is: <strong><?php echo htmlspecialchars(ucfirst($user_2fa_settings['mfa_method'])); ?></strong></p>
                </div>
                <?php endif; ?>

            </div>
            <?php endif; ?>

            <div class="card">
                <h2><?php echo $is_own_profile ? 'Your' : htmlspecialchars($profile_user['name']) . "'s"; ?> Pet Collection</h2>
                <?php if (empty($userPets)): ?>
                                        <div class="empty-state">
                        <h3>No pets to show here!</h3>
                        <?php if ($is_own_profile): ?>
                            <p>Share your first AI pet creation with the community!</p>
                            <a href="upload.php" class="btn btn-primary">Upload Your First Pet</a>
                        <?php else: ?>
                            <p>This user hasn't uploaded any public pets yet.</p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="gallery-grid">
                        <?php foreach ($userPets as $pet): ?>
                                                        <div class="pet-card profile-pet-card">
                                                                                                                                <a href="pet.php?id=<?php echo htmlspecialchars($pet['id']); ?>" class="nostyle-link">
                                    <img src="<?php echo UPLOAD_DIR . htmlspecialchars($pet['filename']); ?>" 
                                         alt="<?php echo htmlspecialchars($pet['original_name']); ?>" 
                                         class="pet-image">
                                </a>
                                <div class="pet-info">
                                    <h3><?php echo htmlspecialchars($pet['original_name']); ?></h3>
                                    <?php if (!empty($pet['description'])): ?>
                                        <p><?php echo htmlspecialchars(substr($pet['description'], 0, 100)); ?><?php echo strlen($pet['description']) > 100 ? '...' : ''; ?></p>
                                    <?php endif; ?>
                                    <div class="pet-meta">
                                        <div class="pet-stats">
                                                                                        <span class="mr-2">👁️ <?php echo $pet['views_count']; ?></span>
                                            <span>❤️ <?php echo $pet['likes_count']; ?></span>
                                        </div>
                                        <?php if ($is_own_profile): ?>
                                        <div class="pet-privacy">
                                                                                        <span class="<?php echo $pet['is_public'] ? 'text-success' : 'text-danger'; ?>">
                                                <?php echo $pet['is_public'] ? '🌍 Public' : '🔒 Private'; ?>
                                            </span>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="pet-date">
                                        Uploaded <?php echo date('M j, Y', strtotime($pet['uploaded_at'])); ?>
                                    </div>
                                    <?php if ($is_own_profile): ?>
                                    <div class="pet-actions">
                                                                                                                                                                <button data-pet-id="<?php echo htmlspecialchars($pet['id']); ?>" class="btn btn-secondary edit-pet-btn">Edit</button>
                                                                                                                                                                <button data-pet-id="<?php echo htmlspecialchars($pet['id']); ?>" class="btn btn-danger delete-pet-btn">Delete</button>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

<?php require_once 'includes/footer.php'; ?>

<?php if ($is_own_profile): ?>
<script src="assets/js/profile.js"></script>
<?php endif; ?>

<?php require_once 'includes/scripts.php'; ?>
