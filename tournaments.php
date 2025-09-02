<?php
/**
 * Money Paws - Tournament Hub
 * Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>
 */

require_once 'includes/functions.php';
require_once 'includes/security.php';
require_once 'includes/tournament_engine.php';

requireLogin();

$user_id = $_SESSION['user_id'];
$tournament_engine = new TournamentEngine();

// Get active tournaments
$stmt = get_db()->prepare("
    SELECT t.*, u.name as creator_name,
           COUNT(tp.id) as participant_count
    FROM tournaments t
    LEFT JOIN users u ON t.created_by_user_id = u.id
    LEFT JOIN tournament_participants tp ON t.id = tp.tournament_id
    WHERE t.status IN ('registration', 'in_progress')
    GROUP BY t.id
    ORDER BY t.created_at DESC
");
$stmt->execute();
$active_tournaments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user's pets for tournament registration
$user_pets = getUserPets($user_id);

include 'includes/header.php';
?>

<div class="container mt-5">
    <div class="row">
        <div class="col-md-12">
            <h1 class="text-center mb-4">🏆 Tournament Arena</h1>
            
            <!-- Tournament Navigation -->
            <ul class="nav nav-tabs mb-4" id="tournamentTabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="active-tab" data-toggle="tab" href="#active" role="tab">
                        🔥 Active Tournaments
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="create-tab" data-toggle="tab" href="#create" role="tab">
                        ➕ Create Tournament
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="history-tab" data-toggle="tab" href="#history" role="tab">
                        📜 My History
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="leaderboard-tab" data-toggle="tab" href="#leaderboard" role="tab">
                        🥇 Leaderboards
                    </a>
                </li>
            </ul>

            <div class="tab-content" id="tournamentTabContent">
                <!-- Active Tournaments Tab -->
                <div class="tab-pane fade show active" id="active" role="tabpanel">
                    <div class="row">
                        <?php if (empty($active_tournaments)): ?>
                            <div class="col-md-12">
                                <div class="alert alert-info text-center">
                                    <h5>🎯 No Active Tournaments</h5>
                                    <p>Be the first to create an exciting tournament!</p>
                                    <button class="btn btn-primary" onclick="$('#create-tab').click()">
                                        Create Tournament
                                    </button>
                                </div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($active_tournaments as $tournament): ?>
                                <div class="col-md-6 mb-4">
                                    <div class="card tournament-card">
                                        <div class="card-header d-flex justify-content-between">
                                            <h6><?php echo htmlspecialchars($tournament['tournament_name']); ?></h6>
                                            <span class="badge badge-<?php echo $tournament['status'] === 'registration' ? 'success' : 'warning'; ?>">
                                                <?php echo ucfirst($tournament['status']); ?>
                                            </span>
                                        </div>
                                        <div class="card-body">
                                            <div class="tournament-info">
                                                <div class="info-row">
                                                    <span class="info-label">🎮 Game Mode:</span>
                                                    <span><?php echo ucfirst(str_replace('_', ' ', $tournament['game_mode'])); ?></span>
                                                </div>
                                                <div class="info-row">
                                                    <span class="info-label">💰 Entry Fee:</span>
                                                    <span>$<?php echo number_format($tournament['entry_fee_usd'], 2); ?></span>
                                                </div>
                                                <div class="info-row">
                                                    <span class="info-label">👥 Participants:</span>
                                                    <span><?php echo $tournament['participant_count']; ?>/<?php echo $tournament['max_participants']; ?></span>
                                                </div>
                                                <div class="info-row">
                                                    <span class="info-label">🏆 Prize Pool:</span>
                                                    <span>$<?php echo number_format($tournament['prize_pool_usd'], 2); ?></span>
                                                </div>
                                                <div class="info-row">
                                                    <span class="info-label">📅 Starts:</span>
                                                    <span><?php echo date('M j, Y g:i A', strtotime($tournament['start_time'])); ?></span>
                                                </div>
                                            </div>
                                            
                                            <?php if ($tournament['status'] === 'registration'): ?>
                                                <button class="btn btn-primary btn-block mt-3" 
                                                        onclick="showRegistrationModal(<?php echo $tournament['id']; ?>)">
                                                    🎯 Register Now
                                                </button>
                                            <?php else: ?>
                                                <button class="btn btn-info btn-block mt-3" 
                                                        onclick="viewTournament(<?php echo $tournament['id']; ?>)">
                                                    👀 View Tournament
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Create Tournament Tab -->
                <div class="tab-pane fade" id="create" role="tabpanel">
                    <div class="row">
                        <div class="col-md-8 offset-md-2">
                            <div class="card">
                                <div class="card-header">
                                    <h5>🏆 Create New Tournament</h5>
                                </div>
                                <div class="card-body">
                                    <form id="create-tournament-form">
                                        <?php echo getCSRFTokenField(); ?>
                                        
                                        <div class="form-group">
                                            <label for="tournament-name">Tournament Name</label>
                                            <input type="text" class="form-control" id="tournament-name" 
                                                   name="tournament_name" required 
                                                   placeholder="Epic Pet Battle Championship">
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="tournament-type">Tournament Type</label>
                                                    <select class="form-control" id="tournament-type" name="tournament_type" required>
                                                        <option value="single_elimination">Single Elimination</option>
                                                        <option value="double_elimination">Double Elimination</option>
                                                        <option value="round_robin">Round Robin</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="game-mode">Game Mode</label>
                                                    <select class="form-control" id="game-mode" name="game_mode" required>
                                                        <option value="pet_battle">Pet Battle</option>
                                                        <option value="racing">Racing</option>
                                                        <option value="agility">Agility Course</option>
                                                        <option value="beauty_contest">Beauty Contest</option>
                                                        <option value="intelligence">Intelligence Test</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="entry-fee">Entry Fee (USD)</label>
                                                    <input type="number" class="form-control" id="entry-fee" 
                                                           name="entry_fee" min="0" step="0.01" required value="5.00">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="max-participants">Max Participants</label>
                                                    <select class="form-control" id="max-participants" name="max_participants" required>
                                                        <option value="8">8 Players</option>
                                                        <option value="16">16 Players</option>
                                                        <option value="32">32 Players</option>
                                                        <option value="64">64 Players</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="registration-deadline">Registration Deadline</label>
                                                    <input type="datetime-local" class="form-control" 
                                                           id="registration-deadline" name="registration_deadline" required>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="start-time">Tournament Start</label>
                                                    <input type="datetime-local" class="form-control" 
                                                           id="start-time" name="start_time" required>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <button type="submit" class="btn btn-success btn-block">
                                            🏆 Create Tournament
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tournament History Tab -->
                <div class="tab-pane fade" id="history" role="tabpanel">
                    <div id="tournament-history">
                        <div class="text-center">
                            <div class="spinner-border" role="status">
                                <span class="sr-only">Loading...</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Leaderboards Tab -->
                <div class="tab-pane fade" id="leaderboard" role="tabpanel">
                    <div id="tournament-leaderboards">
                        <div class="text-center">
                            <div class="spinner-border" role="status">
                                <span class="sr-only">Loading...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tournament Registration Modal -->
<div class="modal fade" id="registrationModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">🎯 Tournament Registration</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="registration-form">
                    <?php echo getCSRFTokenField(); ?>
                    <input type="hidden" id="reg-tournament-id" name="tournament_id">
                    
                    <div class="form-group">
                        <label for="reg-pet-select">Select Your Champion</label>
                        <select class="form-control" id="reg-pet-select" name="pet_id" required>
                            <option value="">-- Choose Your Pet --</option>
                            <?php foreach ($user_pets as $pet): ?>
                                <option value="<?php echo htmlspecialchars($pet['id']); ?>">
                                    <?php echo htmlspecialchars($pet['original_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div id="pet-battle-stats" class="mt-3" style="display: none;">
                        <h6>Battle Statistics</h6>
                        <div class="row">
                            <div class="col-6">
                                <small>ELO Rating: <span id="pet-elo">--</span></small>
                            </div>
                            <div class="col-6">
                                <small>Win Rate: <span id="pet-winrate">--</span></small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-info mt-3">
                        <strong>Entry Fee:</strong> $<span id="modal-entry-fee">0.00</span><br>
                        <strong>Prize Pool:</strong> $<span id="modal-prize-pool">0.00</span>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="registerForTournament()">
                    🎯 Register Pet
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.tournament-card {
    transition: transform 0.2s ease;
    border: 1px solid #dee2e6;
}

.tournament-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.tournament-info .info-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
    padding: 4px 0;
    border-bottom: 1px solid #f1f1f1;
}

.info-label {
    font-weight: 500;
    color: #666;
}

.nav-tabs .nav-link {
    color: #495057;
    font-weight: 500;
}

.nav-tabs .nav-link.active {
    background-color: #007bff;
    border-color: #007bff;
    color: white;
}

.badge {
    font-size: 0.75rem;
}
</style>

<?php include 'includes/footer.php'; ?>
<script src="assets/js/tournaments.js"></script>
