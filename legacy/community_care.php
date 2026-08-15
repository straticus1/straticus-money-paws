<?php
/**
 * Money Paws - Community Pet Care Page
 * Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>
 */

require_once 'includes/functions.php';
require_once 'includes/community_economy.php';
require_once 'includes/daily_quests.php';

requireLogin();

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Handle care provision
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['provide_care'])) {
    requireCSRFToken();
    
    $pet_id = intval($_POST['pet_id']);
    $care_type = sanitizeInput($_POST['care_type']);
    $care_message = sanitizeInput($_POST['care_message']);
    
    $result = provideCommunityPetCare($user_id, $pet_id, $care_type, $care_message);
    
    if ($result['success']) {
        $success = $result['message'];
    } else {
        $error = $result['message'];
    }
}

// Handle appreciation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['express_appreciation'])) {
    requireCSRFToken();
    
    $care_id = intval($_POST['care_id']);
    $appreciation_message = sanitizeInput($_POST['appreciation_message']);
    
    $result = expressAppreciation($user_id, $care_id, $appreciation_message);
    
    if ($result['success']) {
        $success = $result['message'];
    } else {
        $error = $result['message'];
    }
}

// Get data for the page
$available_pets = getAvailableCommunityPets($user_id);
$community_stats = getCommunityStats($user_id);
$care_history = getCommunityHistory($user_id, 'both', 20);
$user_care_coins = getUserCareCoins($user_id);

include 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-12">
            <h1 class="text-center mb-4">🤝 Community Pet Care</h1>
            <p class="text-center text-muted mb-4">
                Help care for pets in our community and earn Care Coins! Building a caring community together.
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

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($error) ?>
            <button type="button" class="close" data-dismiss="alert">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    <?php endif; ?>

    <!-- Community Stats Dashboard -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="text-primary"><?= $user_care_coins ?></h3>
                    <p class="card-text">Care Coins</p>
                    <small class="text-muted">Your earned currency</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="text-success"><?= $community_stats['care_given']['total_care_given'] ?? 0 ?></h3>
                    <p class="card-text">Pets Helped</p>
                    <small class="text-muted">Total care actions</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="text-info"><?= $community_stats['community_rank'] ?></h3>
                    <p class="card-text">Community Rank</p>
                    <small class="text-muted">Among all helpers</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="text-warning"><?= $community_stats['care_given']['unique_pets_helped'] ?? 0 ?></h3>
                    <p class="card-text">Different Pets</p>
                    <small class="text-muted">Unique pets helped</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Available Pets for Care -->
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-hand-holding-heart"></i> Pets Needing Care</h5>
                    <small class="text-muted">Help these pets and earn Care Coins</small>
                </div>
                <div class="card-body">
                    <?php if (empty($available_pets)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-heart fa-3x text-success mb-3"></i>
                            <h4>All pets are well cared for!</h4>
                            <p class="text-muted">Check back later to help more pets in need.</p>
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($available_pets as $pet): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="card border-left-warning">
                                        <div class="card-body p-3">
                                            <div class="row align-items-center">
                                                <div class="col-3">
                                                    <img src="<?= htmlspecialchars($pet['image_url']) ?>" 
                                                         alt="<?= htmlspecialchars($pet['name']) ?>" 
                                                         class="img-fluid rounded-circle" 
                                                         style="width: 60px; height: 60px; object-fit: cover;">
                                                </div>
                                                <div class="col-9">
                                                    <h6 class="mb-1"><?= htmlspecialchars($pet['name']) ?></h6>
                                                    <small class="text-muted">Owner: <?= htmlspecialchars($pet['owner_name']) ?></small>
                                                    
                                                    <div class="mt-1">
                                                        <div class="d-flex justify-content-between">
                                                            <span>Hunger:</span>
                                                            <div class="progress" style="width: 60px; height: 15px;">
                                                                <div class="progress-bar <?= $pet['hunger_level'] < 30 ? 'bg-danger' : ($pet['hunger_level'] < 60 ? 'bg-warning' : 'bg-success') ?>" 
                                                                     style="width: <?= $pet['hunger_level'] ?>%"></div>
                                                            </div>
                                                        </div>
                                                        <div class="d-flex justify-content-between mt-1">
                                                            <span>Happy:</span>
                                                            <div class="progress" style="width: 60px; height: 15px;">
                                                                <div class="progress-bar <?= $pet['happiness_level'] < 30 ? 'bg-danger' : ($pet['happiness_level'] < 60 ? 'bg-warning' : 'bg-success') ?>" 
                                                                     style="width: <?= $pet['happiness_level'] ?>%"></div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <button type="button" class="btn btn-sm btn-outline-primary mt-2" 
                                                            onclick="openCareModal(<?= $pet['id'] ?>, '<?= htmlspecialchars($pet['name']) ?>')">
                                                        <i class="fas fa-heart"></i> Provide Care
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Community Activity Feed -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h6><i class="fas fa-history"></i> Recent Activity</h6>
                </div>
                <div class="card-body" style="max-height: 500px; overflow-y: auto;">
                    <?php if (empty($care_history)): ?>
                        <p class="text-muted text-center">No activity yet. Start helping pets to see your history!</p>
                    <?php else: ?>
                        <?php foreach (array_slice($care_history, 0, 10) as $activity): ?>
                            <div class="media mb-3">
                                <img src="<?= htmlspecialchars($activity['pet_image']) ?>" 
                                     class="rounded-circle mr-2" 
                                     style="width: 40px; height: 40px; object-fit: cover;">
                                <div class="media-body">
                                    <div class="d-flex justify-content-between">
                                        <small class="font-weight-bold">
                                            <?php if ($activity['action_type'] === 'given'): ?>
                                                You helped <?= htmlspecialchars($activity['pet_name']) ?>
                                            <?php else: ?>
                                                <?= htmlspecialchars($activity['caregiver_name']) ?> helped <?= htmlspecialchars($activity['pet_name']) ?>
                                            <?php endif; ?>
                                        </small>
                                        <small class="text-muted"><?= timeAgo($activity['cared_at']) ?></small>
                                    </div>
                                    <small class="text-muted">
                                        <?= ucfirst($activity['care_type']) ?>
                                        <?php if ($activity['action_type'] === 'given'): ?>
                                            - Earned <?= $activity['care_coins_earned'] ?> coins
                                        <?php endif; ?>
                                    </small>
                                    
                                    <?php if ($activity['action_type'] === 'received' && !$activity['appreciation_given']): ?>
                                        <div class="mt-1">
                                            <button class="btn btn-sm btn-outline-success" 
                                                    onclick="openAppreciationModal(<?= $activity['id'] ?>, '<?= htmlspecialchars($activity['caregiver_name']) ?>')">
                                                <i class="fas fa-thumbs-up"></i> Thank
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Care Modal -->
<div class="modal fade" id="careModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="POST" action="">
                <?= getCSRFTokenField() ?>
                <input type="hidden" name="provide_care" value="1">
                <input type="hidden" name="pet_id" id="care_pet_id">
                
                <div class="modal-header">
                    <h5 class="modal-title">Provide Care for <span id="care_pet_name"></span></h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Type of Care:</label>
                        <select name="care_type" class="form-control" required>
                            <option value="">Select care type</option>
                            <option value="feeding">🍯 Feeding (+25 hunger, +5 happiness) - 8+ coins</option>
                            <option value="playing">🎾 Playing (+20 happiness, -5 hunger) - 6+ coins</option>
                            <option value="grooming">✨ Grooming (+15 happiness) - 5+ coins</option>
                            <option value="training">🎓 Training (+10 happiness, +1 intelligence) - 10+ coins</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Care Message (Optional):</label>
                        <textarea name="care_message" class="form-control" rows="2" 
                                  placeholder="Leave a kind message for the pet owner..."></textarea>
                    </div>
                    <div class="alert alert-info">
                        <small>
                            <i class="fas fa-info-circle"></i> 
                            You can care for each pet up to 3 times per day and earn more coins for pets in greater need!
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-heart"></i> Provide Care
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Appreciation Modal -->
<div class="modal fade" id="appreciationModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="POST" action="">
                <?= getCSRFTokenField() ?>
                <input type="hidden" name="express_appreciation" value="1">
                <input type="hidden" name="care_id" id="appreciation_care_id">
                
                <div class="modal-header">
                    <h5 class="modal-title">Thank <span id="appreciation_caregiver_name"></span></h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Appreciation Message (Optional):</label>
                        <textarea name="appreciation_message" class="form-control" rows="3" 
                                  placeholder="Thank them for caring for your pet..."></textarea>
                    </div>
                    <div class="alert alert-success">
                        <small>
                            <i class="fas fa-gift"></i> 
                            Expressing appreciation will award 3 bonus Care Coins to the helper!
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-heart"></i> Express Thanks
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openCareModal(petId, petName) {
    document.getElementById('care_pet_id').value = petId;
    document.getElementById('care_pet_name').textContent = petName;
    $('#careModal').modal('show');
}

function openAppreciationModal(careId, caregiverName) {
    document.getElementById('appreciation_care_id').value = careId;
    document.getElementById('appreciation_caregiver_name').textContent = caregiverName;
    $('#appreciationModal').modal('show');
}

// Auto-refresh available pets every 2 minutes
setInterval(function() {
    if (document.hidden) return; // Don't refresh if page is not visible
    location.reload();
}, 120000);
</script>

<?php include 'includes/footer.php'; ?>