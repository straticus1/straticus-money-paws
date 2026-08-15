<?php
/**
 * Money Paws - Adaptive Gaming Interface
 * Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>
 */

require_once 'includes/functions.php';
require_once 'includes/adaptive_gaming.php';
require_once 'includes/daily_quests.php';

requireLogin();

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Handle preference update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_preference'])) {
    requireCSRFToken();
    
    $new_preference = sanitizeInput($_POST['gaming_preference']);
    if (updateGamingPreference($user_id, $new_preference)) {
        $success = 'Gaming preference updated successfully!';
    } else {
        $error = 'Unable to update preference. Age restrictions may apply.';
    }
}

// Handle educational game play
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['play_educational'])) {
    requireCSRFToken();
    
    $game_type = sanitizeInput($_POST['game_type']);
    $game_data = [];
    
    if (isset($_POST['answer'])) {
        $game_data['answer'] = intval($_POST['answer']);
    }
    
    $result = playEducationalGame($user_id, $game_type, $game_data);
    
    if ($result['success']) {
        if ($result['correct'] ?? false) {
            $success = $result['message'];
        } else {
            $error = $result['message'] . ' Correct answer: ' . ($result['explanation'] ?? '');
        }
    } else {
        $error = $result['message'];
    }
}

// Handle traditional game play
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['play_traditional'])) {
    requireCSRFToken();
    
    $game_type = sanitizeInput($_POST['game_type']);
    $bet_amount = floatval($_POST['bet_amount']);
    $crypto_type = sanitizeInput($_POST['crypto_type']);
    $game_data = [];
    
    if (isset($_POST['choice'])) {
        $game_data['choice'] = sanitizeInput($_POST['choice']);
    }
    
    // Check responsible gaming limits first
    $gambling_check = checkGamblingLimits($user_id);
    if ($gambling_check['needs_warning']) {
        $error = 'Responsible Gaming Notice: ' . implode(' ', $gambling_check['warnings']);
    } else {
        $result = playTraditionalGame($user_id, $game_type, $bet_amount, $crypto_type, $game_data);
        
        if ($result['success']) {
            if ($result['won'] ?? false) {
                $success = $result['message'] . " You won " . $result['win_amount'] . " {$crypto_type}!";
            } else {
                $error = $result['message'];
            }
        } else {
            $error = $result['message'];
        }
    }
}

// Get data for the page
$gaming_mode = determineGamingMode($user_id);
$available_games = getAvailableGames($user_id);
$gaming_stats = getGamingStats($user_id);
$user_care_coins = getUserCareCoins($user_id);

// Get user's crypto balances for traditional games
$balances = [];
if ($gaming_mode !== 'educational') {
    foreach (SUPPORTED_CRYPTOS as $crypto => $name) {
        $balances[$crypto] = getUserCryptoBalance($user_id, $crypto);
    }
}

// Get current user info for age display
$current_user = getUserById($user_id);
$user_age = null;
if ($current_user['birth_date']) {
    $birth_date = new DateTime($current_user['birth_date']);
    $today = new DateTime();
    $user_age = $today->diff($birth_date)->y;
}

include 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-12">
            <h1 class="text-center mb-4">🎮 Smart Gaming Hub</h1>
            <p class="text-center text-muted mb-4">
                Personalized gaming experience designed for your age and preferences
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

    <!-- Gaming Mode Info -->
    <div class="row mb-4">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5>
                        <i class="fas fa-user-cog"></i> Your Gaming Profile
                        <?php if ($user_age !== null): ?>
                            <span class="badge badge-info">Age: <?= $user_age ?></span>
                        <?php endif; ?>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <?php
                            $mode_info = [
                                'educational' => [
                                    'title' => 'Educational Gaming Mode',
                                    'description' => 'Learn while you play! Games focused on skill-building, knowledge, and positive development.',
                                    'color' => 'success',
                                    'icon' => '🎓'
                                ],
                                'mixed' => [
                                    'title' => 'Mixed Gaming Mode', 
                                    'description' => 'Enjoy both educational and traditional games. Best of both worlds with responsible gaming features.',
                                    'color' => 'warning',
                                    'icon' => '⚖️'
                                ],
                                'traditional' => [
                                    'title' => 'Traditional Gaming Mode',
                                    'description' => 'Classic casino-style games with crypto betting. Includes responsible gaming protections.',
                                    'color' => 'danger',
                                    'icon' => '🎲'
                                ]
                            ];
                            
                            $current_mode_info = $mode_info[$gaming_mode];
                            ?>
                            
                            <h6 class="text-<?= $current_mode_info['color'] ?>">
                                <?= $current_mode_info['icon'] ?> <?= $current_mode_info['title'] ?>
                            </h6>
                            <p class="mb-2"><?= $current_mode_info['description'] ?></p>
                            
                            <?php if ($user_age !== null && $user_age < 18): ?>
                                <div class="alert alert-info mb-2">
                                    <small>
                                        <i class="fas fa-shield-alt"></i> 
                                        Educational mode is automatically selected for users under 18 to promote healthy gaming habits.
                                    </small>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <?php if ($user_age === null || $user_age >= 18): ?>
                                <button type="button" class="btn btn-outline-primary" data-toggle="modal" data-target="#preferencesModal">
                                    <i class="fas fa-cog"></i> Change Mode
                                </button>
                            <?php else: ?>
                                <div class="text-muted">
                                    <small>Mode locked for your age group</small>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Gaming Stats -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h6><i class="fas fa-chart-bar"></i> Your Stats</h6>
                </div>
                <div class="card-body p-3">
                    <div class="mb-2">
                        <strong>Care Coins:</strong> 
                        <span class="text-success"><?= $user_care_coins ?></span>
                    </div>
                    <div class="mb-2">
                        <strong>Educational Games:</strong> 
                        <span class="text-info"><?= $gaming_stats['educational']['total_educational_games'] ?? 0 ?></span>
                    </div>
                    <div class="mb-2">
                        <strong>Success Rate:</strong> 
                        <?php
                        $total = $gaming_stats['educational']['total_educational_games'] ?? 0;
                        $successes = $gaming_stats['educational']['educational_successes'] ?? 0;
                        $rate = $total > 0 ? round(($successes / $total) * 100) : 0;
                        ?>
                        <span class="text-warning"><?= $rate ?>%</span>
                    </div>
                    <div>
                        <strong>Learning Rewards:</strong> 
                        <span class="text-success"><?= $gaming_stats['educational']['total_educational_rewards'] ?? 0 ?> coins</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Available Games -->
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-gamepad"></i> Available Games</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($available_games as $game_id => $game): ?>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="card h-100 <?= $game['type'] === 'chance' ? 'border-warning' : 'border-success' ?>">
                                    <div class="card-body">
                                        <div class="text-center mb-3">
                                            <div style="font-size: 2rem;"><?= $game['icon'] ?></div>
                                            <h6 class="card-title mt-2"><?= htmlspecialchars($game['name']) ?></h6>
                                        </div>
                                        
                                        <p class="card-text small text-muted">
                                            <?= htmlspecialchars($game['description']) ?>
                                        </p>
                                        
                                        <?php if ($game['educational_value']): ?>
                                            <div class="mb-2">
                                                <span class="badge badge-success">Educational</span>
                                                <small class="text-muted d-block">
                                                    Learn: <?= htmlspecialchars($game['educational_value']) ?>
                                                </small>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="mb-3">
                                            <?php if ($game['reward_type'] === 'care_coins'): ?>
                                                <small class="text-success">
                                                    <i class="fas fa-coins"></i> Earn up to <?= $game['base_reward'] ?> Care Coins
                                                </small>
                                            <?php else: ?>
                                                <small class="text-warning">
                                                    <i class="fas fa-money-bill-wave"></i> Win up to <?= $game['win_multiplier'] ?>x your bet
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <button type="button" 
                                                class="btn <?= $game['type'] === 'chance' ? 'btn-warning' : 'btn-success' ?> btn-block"
                                                onclick="startGame('<?= $game_id ?>', '<?= $game['type'] ?>')">
                                            <?= $game['entry_cost'] === 0 ? 'Play Free' : 'Play Game' ?>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Gaming Preferences Modal -->
<div class="modal fade" id="preferencesModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="POST" action="">
                <?= getCSRFTokenField() ?>
                <input type="hidden" name="update_preference" value="1">
                
                <div class="modal-header">
                    <h5 class="modal-title">Gaming Mode Preferences</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="text-muted mb-4">
                        Choose your preferred gaming experience. You can change this anytime.
                    </p>
                    
                    <div class="form-group">
                        <div class="custom-control custom-radio">
                            <input type="radio" name="gaming_preference" value="educational" 
                                   class="custom-control-input" id="pref_educational"
                                   <?= $gaming_mode === 'educational' ? 'checked' : '' ?>>
                            <label class="custom-control-label" for="pref_educational">
                                <strong>🎓 Educational Only</strong>
                                <div class="text-muted small">
                                    Focus on learning and skill development. All games are free and educational.
                                </div>
                            </label>
                        </div>
                    </div>
                    
                    <?php if ($user_age === null || $user_age >= 18): ?>
                        <div class="form-group">
                            <div class="custom-control custom-radio">
                                <input type="radio" name="gaming_preference" value="mixed" 
                                       class="custom-control-input" id="pref_mixed"
                                       <?= $gaming_mode === 'mixed' ? 'checked' : '' ?>>
                                <label class="custom-control-label" for="pref_mixed">
                                    <strong>⚖️ Mixed Experience</strong>
                                    <div class="text-muted small">
                                        Enjoy both educational and traditional games with responsible gaming features.
                                    </div>
                                </label>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <div class="custom-control custom-radio">
                                <input type="radio" name="gaming_preference" value="traditional" 
                                       class="custom-control-input" id="pref_traditional"
                                       <?= $gaming_mode === 'traditional' ? 'checked' : '' ?>>
                                <label class="custom-control-label" for="pref_traditional">
                                    <strong>🎲 Traditional Gaming</strong>
                                    <div class="text-muted small">
                                        Classic casino-style games with cryptocurrency betting and responsible gaming protections.
                                    </div>
                                </label>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="alert alert-info mt-3">
                        <small>
                            <i class="fas fa-info-circle"></i>
                            All users have access to educational games regardless of preference. 
                            Traditional games include automatic responsible gaming monitoring.
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Preferences</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Game Play Modal (will be populated by JavaScript) -->
<div class="modal fade" id="gameModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="gameModalTitle">Game</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body" id="gameModalBody">
                <!-- Game content will be loaded here -->
            </div>
        </div>
    </div>
</div>

<script>
const availableGames = <?= json_encode($available_games) ?>;
const userBalances = <?= json_encode($balances) ?>;

function startGame(gameId, gameType) {
    const game = availableGames[gameId];
    if (!game) return;
    
    document.getElementById('gameModalTitle').textContent = game.name;
    
    if (gameType === 'chance') {
        loadTraditionalGame(gameId, game);
    } else {
        loadEducationalGame(gameId, game);
    }
    
    $('#gameModal').modal('show');
}

function loadEducationalGame(gameId, game) {
    const scenarios = {
        'pet_care_challenge': [
            {
                situation: 'Your pet hasn\'t eaten in 8 hours and looks lethargic',
                options: [
                    'Give it a large meal immediately',
                    'Provide small amounts of food frequently',
                    'Wait until regular feeding time', 
                    'Give it treats to cheer it up'
                ]
            },
            {
                situation: 'Your pet is showing signs of dehydration',
                options: [
                    'Encourage small sips of water frequently',
                    'Force it to drink a large amount',
                    'Give it milk instead',
                    'Wait for it to drink on its own'
                ]
            }
        ],
        'genetics_puzzle': [
            {
                situation: 'If you breed a brown-eyed pet (Bb) with a blue-eyed pet (bb), what percentage of offspring will have brown eyes?',
                options: ['25%', '50%', '75%', '100%']
            }
        ]
    };
    
    const gameScenarios = scenarios[gameId] || [];
    const scenario = gameScenarios[Math.floor(Math.random() * gameScenarios.length)];
    
    if (!scenario) {
        document.getElementById('gameModalBody').innerHTML = '<p>Game scenarios not available.</p>';
        return;
    }
    
    let html = `
        <form method="POST" action="">
            ${document.querySelector('input[name="csrf_token"]').outerHTML}
            <input type="hidden" name="play_educational" value="1">
            <input type="hidden" name="game_type" value="${gameId}">
            
            <div class="mb-4">
                <h6>${game.icon} ${scenario.situation}</h6>
            </div>
            
            <div class="form-group">
                <label>Choose the best response:</label>
    `;
    
    scenario.options.forEach((option, index) => {
        html += `
            <div class="custom-control custom-radio mb-2">
                <input type="radio" name="answer" value="${index}" 
                       class="custom-control-input" id="option${index}" required>
                <label class="custom-control-label" for="option${index}">
                    ${option}
                </label>
            </div>
        `;
    });
    
    html += `
            </div>
            
            <div class="alert alert-success">
                <small>
                    <i class="fas fa-graduation-cap"></i>
                    Educational value: ${game.educational_value}<br>
                    Potential reward: Up to ${game.base_reward} Care Coins
                </small>
            </div>
            
            <div class="text-center">
                <button type="submit" class="btn btn-success btn-lg">
                    <i class="fas fa-play"></i> Submit Answer
                </button>
            </div>
        </form>
    `;
    
    document.getElementById('gameModalBody').innerHTML = html;
}

function loadTraditionalGame(gameId, game) {
    if (gameId === 'coin_flip') {
        let html = `
            <form method="POST" action="">
                ${document.querySelector('input[name="csrf_token"]').outerHTML}
                <input type="hidden" name="play_traditional" value="1">
                <input type="hidden" name="game_type" value="${gameId}">
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Your Choice:</label>
                            <div class="btn-group-toggle" data-toggle="buttons">
                                <label class="btn btn-outline-primary">
                                    <input type="radio" name="choice" value="heads" required> Heads
                                </label>
                                <label class="btn btn-outline-primary">
                                    <input type="radio" name="choice" value="tails" required> Tails
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Cryptocurrency:</label>
                            <select name="crypto_type" class="form-control" required>
                                <option value="">Select crypto</option>
        `;
        
        Object.keys(userBalances).forEach(crypto => {
            if (userBalances[crypto] > 0) {
                html += `<option value="${crypto}">${crypto} (${userBalances[crypto]} available)</option>`;
            }
        });
        
        html += `
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Bet Amount:</label>
                    <input type="number" name="bet_amount" class="form-control" 
                           min="0.00001" step="0.00001" required>
                </div>
                
                <div class="alert alert-warning">
                    <small>
                        <i class="fas fa-exclamation-triangle"></i>
                        Win multiplier: ${game.win_multiplier}x your bet<br>
                        Remember to gamble responsibly. Consider educational games for skill building!
                    </small>
                </div>
                
                <div class="text-center">
                    <button type="submit" class="btn btn-warning btn-lg">
                        <i class="fas fa-dice"></i> Flip Coin
                    </button>
                </div>
            </form>
        `;
        
        document.getElementById('gameModalBody').innerHTML = html;
    }
}
</script>

<?php include 'includes/footer.php'; ?>