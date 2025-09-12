<?php
/**
 * Money Paws - Daily Quests Page
 * Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>
 */

require_once 'includes/functions.php';
require_once 'includes/daily_quests.php';

requireLogin();

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Get daily quests and user data
$daily_quests = getDailyQuests($user_id);
$user_care_coins = getUserCareCoins($user_id);
$care_coins_store_items = getCareCoinsStoreItems();

// Get today's care coins progress
$today = date('Y-m-d');
$pdo = get_db();
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(amount), 0) as today_earned
    FROM care_coin_transactions 
    WHERE user_id = ? AND DATE(created_at) = ? AND transaction_type = 'earned'
");
$stmt->execute([$user_id, $today]);
$today_earned = $stmt->fetchColumn();

$daily_limit_remaining = CARE_COIN_DAILY_LIMIT - $today_earned;

include 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-12">
            <h1 class="text-center mb-4">🎯 Daily Quests & Care Coins</h1>
            <p class="text-center text-muted mb-4">
                Complete daily challenges to earn Care Coins and help the community!
            </p>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($success) ?>
            <button type="button" class="close" data-dismiss="alert">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    <?php endif; ?>

    <!-- Care Coins Status -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="text-success"><?= $user_care_coins ?></h3>
                    <p class="card-text">Care Coins</p>
                    <small class="text-muted">Your earned currency</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="text-info"><?= $today_earned ?></h3>
                    <p class="card-text">Earned Today</p>
                    <small class="text-muted">Daily progress</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="text-warning"><?= max(0, $daily_limit_remaining) ?></h3>
                    <p class="card-text">Remaining Today</p>
                    <small class="text-muted">Out of <?= CARE_COIN_DAILY_LIMIT ?> daily limit</small>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Daily Quests -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-tasks"></i> Today's Quests</h5>
                    <small class="text-muted">Complete these activities to earn Care Coins</small>
                </div>
                <div class="card-body">
                    <?php if (empty($daily_quests)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-calendar-check fa-3x text-success mb-3"></i>
                            <h4>All quests completed!</h4>
                            <p class="text-muted">Come back tomorrow for new challenges.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($daily_quests as $quest): ?>
                            <div class="card mb-3 <?= $quest['completed'] ? 'border-success' : 'border-warning' ?>">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col-1">
                                            <?php if ($quest['completed']): ?>
                                                <i class="fas fa-check-circle text-success fa-2x"></i>
                                            <?php else: ?>
                                                <i class="fas fa-clock text-warning fa-2x"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-8">
                                            <h6 class="mb-1"><?= htmlspecialchars($quest['title']) ?></h6>
                                            <p class="text-muted mb-2"><?= htmlspecialchars($quest['description']) ?></p>
                                            
                                            <?php if (!$quest['completed']): ?>
                                                <div class="progress mb-2" style="height: 20px;">
                                                    <?php 
                                                    $progress_percent = ($quest['current_progress'] / $quest['target_value']) * 100;
                                                    ?>
                                                    <div class="progress-bar" style="width: <?= $progress_percent ?>%">
                                                        <?= $quest['current_progress'] ?> / <?= $quest['target_value'] ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-3 text-right">
                                            <div class="text-success font-weight-bold">
                                                <i class="fas fa-coins"></i> <?= $quest['reward_care_coins'] ?>
                                            </div>
                                            <?php if ($quest['completed']): ?>
                                                <small class="text-success">✓ Completed</small>
                                            <?php else: ?>
                                                <small class="text-muted">Care Coins</small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <?php if (!$quest['completed']): ?>
                                        <div class="mt-2">
                                            <small class="text-info">
                                                <i class="fas fa-lightbulb"></i> 
                                                <?php
                                                $quest_hints = [
                                                    'feed_pets' => 'Visit your pets or the community care page to feed pets',
                                                    'visit_friends' => 'Browse the gallery or visit user profiles',
                                                    'help_community' => 'Go to Community Care to help abandoned pets',
                                                    'learn_something' => 'Try the educational games or modules',
                                                    'creative_time' => 'Upload a new pet image or customize existing ones',
                                                    'social_interaction' => 'Send friendly messages to other users',
                                                    'pet_training' => 'Play educational games in the Smart Gaming Hub'
                                                ];
                                                echo $quest_hints[$quest['quest_type']] ?? 'Complete the activity described above';
                                                ?>
                                            </small>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Care Coins Store -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h6><i class="fas fa-store"></i> Care Coins Store</h6>
                </div>
                <div class="card-body" style="max-height: 500px; overflow-y: auto;">
                    <?php foreach ($care_coins_store_items as $item_id => $item): ?>
                        <div class="card mb-2 border-light">
                            <div class="card-body p-2">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1"><?= htmlspecialchars($item['name']) ?></h6>
                                        <small class="text-muted"><?= htmlspecialchars($item['description']) ?></small>
                                        <div class="mt-1">
                                            <span class="badge badge-success"><?= $item['cost'] ?> coins</span>
                                            <?php if ($item['type'] === 'bundle'): ?>
                                                <span class="badge badge-info">Bundle</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div>
                                        <?php if ($user_care_coins >= $item['cost']): ?>
                                            <button class="btn btn-sm btn-outline-success" 
                                                    onclick="purchaseItem('<?= $item_id ?>')">
                                                Buy
                                            </button>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-outline-secondary" disabled>
                                                Need <?= $item['cost'] - $user_care_coins ?> more
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <div class="text-center mt-3">
                        <small class="text-muted">
                            <i class="fas fa-info-circle"></i>
                            Earn Care Coins by completing daily quests and helping the community!
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row mt-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h6><i class="fas fa-rocket"></i> Quick Actions to Earn Care Coins</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 mb-2">
                            <a href="community_care.php" class="btn btn-outline-primary btn-block">
                                <i class="fas fa-hands-helping"></i><br>
                                <small>Help Community Pets</small>
                            </a>
                        </div>
                        <div class="col-md-3 mb-2">
                            <a href="adaptive_gaming.php" class="btn btn-outline-success btn-block">
                                <i class="fas fa-graduation-cap"></i><br>
                                <small>Educational Games</small>
                            </a>
                        </div>
                        <div class="col-md-3 mb-2">
                            <a href="gallery.php" class="btn btn-outline-info btn-block">
                                <i class="fas fa-users"></i><br>
                                <small>Visit Friends</small>
                            </a>
                        </div>
                        <div class="col-md-3 mb-2">
                            <a href="upload.php" class="btn btn-outline-warning btn-block">
                                <i class="fas fa-camera"></i><br>
                                <small>Upload Pet Photos</small>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Purchase Confirmation Modal -->
<div class="modal fade" id="purchaseModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Purchase Confirmation</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body" id="purchaseModalBody">
                <!-- Purchase details will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="confirmPurchase">Confirm Purchase</button>
            </div>
        </div>
    </div>
</div>

<script>
const storeItems = <?= json_encode($care_coins_store_items) ?>;
const userCareCoins = <?= $user_care_coins ?>;

function purchaseItem(itemId) {
    const item = storeItems[itemId];
    if (!item) return;
    
    document.getElementById('purchaseModalBody').innerHTML = `
        <div class="text-center">
            <h6>${item.name}</h6>
            <p class="text-muted">${item.description}</p>
            <div class="alert alert-info">
                <strong>Cost:</strong> ${item.cost} Care Coins<br>
                <strong>Your Balance:</strong> ${userCareCoins} Care Coins<br>
                <strong>After Purchase:</strong> ${userCareCoins - item.cost} Care Coins
            </div>
        </div>
    `;
    
    document.getElementById('confirmPurchase').onclick = function() {
        // Here you would make an AJAX call to purchase the item
        alert('Purchase system will be implemented with the store integration!');
        $('#purchaseModal').modal('hide');
    };
    
    $('#purchaseModal').modal('show');
}

// Auto-refresh quest progress every 30 seconds
setInterval(function() {
    if (document.hidden) return;
    
    // Check for quest progress updates
    fetch('api/get-quest-progress.php')
        .then(response => response.json())
        .then(data => {
            if (data.updated) {
                location.reload();
            }
        })
        .catch(error => console.log('Quest update check failed'));
}, 30000);

// Show celebration for completed quests
document.addEventListener('DOMContentLoaded', function() {
    <?php if (isset($_SESSION['quest_completed'])): ?>
        // Show celebration modal or notification
        const completedQuest = <?= json_encode($_SESSION['quest_completed']) ?>;
        if (completedQuest) {
            setTimeout(function() {
                alert(`🎉 Quest Completed: ${completedQuest.title}!\nYou earned ${completedQuest.reward} Care Coins!`);
            }, 500);
        }
        <?php unset($_SESSION['quest_completed']); ?>
    <?php endif; ?>
});
</script>

<?php include 'includes/footer.php'; ?>