<?php
/**
 * Money Paws - Educational Content Hub
 * Interactive learning, vet-verified guides, and educational games
 * Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>
 */
require_once 'includes/functions.php';
require_once 'includes/educational_content.php';

requireLogin();

$user_id = $_SESSION['user_id'];
$active_tab = $_GET['tab'] ?? 'modules';

// Check if user is in kid-safe mode
$kid_safe_mode = isKidSafeMode($user_id);

// Get user's educational progress
$progress = getUserEducationProgress($user_id);

// Get age-appropriate content
if ($kid_safe_mode) {
    $content = getKidSafeContent();
} else {
    $user = getUserById($user_id);
    $age = $user['birth_date'] ? calculateAge($user['birth_date']) : 25;
    
    $educational_modules = getEducationalModules(null, $age);
    $pet_care_guides = getPetCareGuides();
    $educational_games = getEducationalGames($age);
}

// Get leaderboard
$leaderboard = getEducationalLeaderboard('monthly');

$pageTitle = 'Educational Hub';
require_once 'includes/html_head.php';
require_once 'includes/header.php';
?>

<main>
    <div class="container">
        <div class="hero hero-padding">
            <h1>🎓 Educational Hub</h1>
            <p><?php echo $kid_safe_mode ? 'Fun learning adventures for young pet lovers!' : 'Learn real pet care from veterinary experts while earning Care Coins!'; ?></p>
            
            <?php if ($kid_safe_mode): ?>
                <div class="kid-safe-badge">
                    <span class="badge badge-success">🔒 Kid-Safe Mode Active</span>
                    <small>Age-appropriate content with extra safety features</small>
                </div>
            <?php endif; ?>
        </div>

        <!-- Progress Dashboard -->
        <div class="progress-dashboard card">
            <h3>📊 Your Learning Progress</h3>
            <div class="progress-stats">
                <div class="progress-stat">
                    <div class="stat-number"><?php echo $progress['completed_modules']; ?></div>
                    <div class="stat-label">Modules Completed</div>
                </div>
                <div class="progress-stat">
                    <div class="stat-number"><?php echo $progress['guides_read']; ?></div>
                    <div class="stat-label">Guides Read</div>
                </div>
                <div class="progress-stat">
                    <div class="stat-number"><?php echo $progress['games_played']; ?></div>
                    <div class="stat-label">Games Played</div>
                </div>
                <div class="progress-stat">
                    <div class="stat-number"><?php echo $progress['education_coins_earned']; ?></div>
                    <div class="stat-label">Education Coins</div>
                </div>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <div class="tabs">
            <button class="tab-button <?php echo $active_tab === 'modules' ? 'active' : ''; ?>" onclick="switchTab('modules')">
                📚 Interactive Modules
            </button>
            <button class="tab-button <?php echo $active_tab === 'guides' ? 'active' : ''; ?>" onclick="switchTab('guides')">
                👩‍⚕️ Vet Guides
            </button>
            <button class="tab-button <?php echo $active_tab === 'games' ? 'active' : ''; ?>" onclick="switchTab('games')">
                🎮 Learning Games
            </button>
            <button class="tab-button <?php echo $active_tab === 'leaderboard' ? 'active' : ''; ?>" onclick="switchTab('leaderboard')">
                🏆 Leaderboard
            </button>
        </div>

        <!-- Interactive Modules Tab -->
        <div id="modules-tab" class="tab-content <?php echo $active_tab === 'modules' ? 'active' : ''; ?>">
            <div class="card">
                <h3>📚 Interactive Learning Modules</h3>
                <p>Complete structured lessons created by veterinary professionals. Each module includes videos, quizzes, and hands-on activities.</p>
                
                <div class="module-categories">
                    <div class="category-filters">
                        <button class="filter-btn active" onclick="filterModules('all')">All Modules</button>
                        <button class="filter-btn" onclick="filterModules('basic')">Basic Care</button>
                        <button class="filter-btn" onclick="filterModules('health')">Health & Wellness</button>
                        <button class="filter-btn" onclick="filterModules('behavior')">Animal Behavior</button>
                        <button class="filter-btn" onclick="filterModules('nutrition')">Nutrition</button>
                    </div>
                </div>

                <div class="modules-grid">
                    <?php 
                    $modules = $kid_safe_mode ? $content['modules'] : $educational_modules;
                    foreach ($modules as $module): 
                    ?>
                        <div class="module-card" data-category="<?php echo $module['category']; ?>">
                            <div class="module-header">
                                <div class="module-icon"><?php echo $module['icon']; ?></div>
                                <div class="module-difficulty">
                                    <?php for ($i = 1; $i <= 3; $i++): ?>
                                        <span class="star <?php echo $i <= $module['difficulty_level'] ? 'active' : ''; ?>">⭐</span>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            
                            <h4><?php echo htmlspecialchars($module['title']); ?></h4>
                            <p><?php echo htmlspecialchars($module['description']); ?></p>
                            
                            <div class="module-meta">
                                <span class="module-duration">⏱️ <?php echo $module['estimated_duration']; ?> min</span>
                                <span class="module-reward">💰 <?php echo 20 + ($module['difficulty_level'] * 10); ?> coins</span>
                            </div>
                            
                            <div class="module-progress">
                                <?php if ($module['attempt_count'] > 0): ?>
                                    <span class="completion-badge">✅ Popular</span>
                                <?php endif; ?>
                                <span class="attempts-count"><?php echo $module['attempt_count']; ?> students</span>
                            </div>
                            
                            <button class="btn btn-primary btn-block" onclick="startModule(<?php echo $module['id']; ?>)">
                                Start Learning
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Veterinary Guides Tab -->
        <div id="guides-tab" class="tab-content <?php echo $active_tab === 'guides' ? 'active' : ''; ?>">
            <div class="card">
                <h3>👩‍⚕️ Veterinary Expert Guides</h3>
                <p>Professional pet care guides written and reviewed by licensed veterinarians. Learn evidence-based care practices.</p>
                
                <div class="guide-categories">
                    <div class="category-filters">
                        <button class="filter-btn active" onclick="filterGuides('all')">All Guides</button>
                        <button class="filter-btn" onclick="filterGuides('basic')">Basic Care</button>
                        <button class="filter-btn" onclick="filterGuides('emergency')">Emergency Care</button>
                        <button class="filter-btn" onclick="filterGuides('nutrition')">Feeding & Nutrition</button>
                        <button class="filter-btn" onclick="filterGuides('behavior')">Behavior & Training</button>
                    </div>
                </div>

                <div class="guides-grid">
                    <?php 
                    $guides = $kid_safe_mode ? $content['guides'] : $pet_care_guides;
                    foreach ($guides as $guide): 
                    ?>
                        <div class="guide-card" data-category="<?php echo $guide['category']; ?>">
                            <?php if ($guide['featured']): ?>
                                <div class="featured-badge">🌟 Featured</div>
                            <?php endif; ?>
                            
                            <div class="guide-header">
                                <img src="<?php echo $guide['thumbnail'] ?? '/images/default-guide.jpg'; ?>" 
                                     alt="<?php echo htmlspecialchars($guide['title']); ?>" 
                                     class="guide-thumbnail">
                            </div>
                            
                            <div class="guide-content">
                                <h4><?php echo htmlspecialchars($guide['title']); ?></h4>
                                <p><?php echo htmlspecialchars(substr($guide['excerpt'], 0, 120)) . '...'; ?></p>
                                
                                <div class="guide-meta">
                                    <div class="vet-info">
                                        <strong>By: Dr. <?php echo htmlspecialchars($guide['vet_name']); ?></strong>
                                        <span class="credentials"><?php echo htmlspecialchars($guide['credentials']); ?></span>
                                    </div>
                                    
                                    <div class="guide-stats">
                                        <div class="rating">
                                            <?php 
                                            $rating = round($guide['avg_rating'] ?? 0);
                                            for ($i = 1; $i <= 5; $i++): 
                                            ?>
                                                <span class="star <?php echo $i <= $rating ? 'active' : ''; ?>">⭐</span>
                                            <?php endfor; ?>
                                            <span class="rating-count">(<?php echo $guide['review_count']; ?>)</span>
                                        </div>
                                        
                                        <div class="difficulty">
                                            <span class="difficulty-badge difficulty-<?php echo $guide['difficulty_level']; ?>">
                                                <?php echo ucfirst($guide['difficulty_level']); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                
                                <button class="btn btn-primary btn-block" onclick="readGuide(<?php echo $guide['id']; ?>)">
                                    Read Guide (+3 coins)
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Educational Games Tab -->
        <div id="games-tab" class="tab-content <?php echo $active_tab === 'games' ? 'active' : ''; ?>">
            <div class="card">
                <h3>🎮 Educational Pet Care Games</h3>
                <p>Learn through play! Interactive games that teach pet care concepts while rewarding your progress.</p>
                
                <div class="games-grid">
                    <?php 
                    $games = $kid_safe_mode ? $content['games'] : $educational_games;
                    foreach ($games as $game): 
                    ?>
                        <div class="game-card">
                            <div class="game-header">
                                <img src="<?php echo $game['thumbnail'] ?? '/images/default-game.jpg'; ?>" 
                                     alt="<?php echo htmlspecialchars($game['title']); ?>" 
                                     class="game-thumbnail">
                                     
                                <?php if ($game['featured']): ?>
                                    <div class="featured-badge">🌟 Featured</div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="game-content">
                                <h4><?php echo htmlspecialchars($game['title']); ?></h4>
                                <p><?php echo htmlspecialchars($game['description']); ?></p>
                                
                                <div class="game-meta">
                                    <div class="game-stats">
                                        <span class="play-count">🎯 <?php echo number_format($game['play_count']); ?> plays</span>
                                        <span class="skill-level">📈 <?php echo ucfirst($game['skill_level']); ?></span>
                                        <span class="duration">⏱️ ~<?php echo $game['avg_play_time']; ?> min</span>
                                    </div>
                                    
                                    <div class="game-rewards">
                                        <span class="coin-reward">💰 Up to <?php echo $game['coin_reward_per_play'] + ($game['skill_level'] * 2) + 10; ?> coins</span>
                                    </div>
                                </div>
                                
                                <div class="daily-limit">
                                    Daily plays remaining: <strong><?php echo $game['daily_play_limit']; ?></strong>
                                </div>
                                
                                <button class="btn btn-success btn-block" onclick="playGame(<?php echo $game['id']; ?>)">
                                    🎮 Play Game
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Leaderboard Tab -->
        <div id="leaderboard-tab" class="tab-content <?php echo $active_tab === 'leaderboard' ? 'active' : ''; ?>">
            <div class="card">
                <h3>🏆 Educational Leaderboard</h3>
                <p>Top learners this month! See who's leading in educational achievements.</p>
                
                <div class="leaderboard">
                    <div class="leaderboard-header">
                        <div class="rank-header">Rank</div>
                        <div class="user-header">Learner</div>
                        <div class="stats-header">Progress</div>
                        <div class="score-header">Education Score</div>
                    </div>
                    
                    <?php foreach ($leaderboard as $index => $leader): ?>
                        <div class="leaderboard-row <?php echo $leader['id'] == $user_id ? 'current-user' : ''; ?>">
                            <div class="rank">
                                <?php if ($index === 0): ?>
                                    <span class="gold-medal">🥇</span>
                                <?php elseif ($index === 1): ?>
                                    <span class="silver-medal">🥈</span>
                                <?php elseif ($index === 2): ?>
                                    <span class="bronze-medal">🥉</span>
                                <?php else: ?>
                                    <span class="rank-number">#<?php echo $index + 1; ?></span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="user-info">
                                <strong><?php echo htmlspecialchars($leader['name']); ?></strong>
                                <?php if ($leader['id'] == $user_id): ?>
                                    <span class="you-badge">You!</span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="user-stats">
                                <div class="stat-item">📚 <?php echo $leader['modules_completed']; ?> modules</div>
                                <div class="stat-item">👩‍⚕️ <?php echo $leader['guides_read']; ?> guides</div>
                                <div class="stat-item">🎮 <?php echo $leader['games_played']; ?> games</div>
                            </div>
                            
                            <div class="education-score">
                                <strong><?php echo number_format($leader['education_score']); ?></strong> points
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once 'includes/footer.php'; ?>

<script>
function switchTab(tabName) {
    // Hide all tabs
    document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
    document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
    
    // Show selected tab
    document.getElementById(tabName + '-tab').classList.add('active');
    event.target.classList.add('active');
    
    // Update URL
    window.history.replaceState({}, '', `?tab=${tabName}`);
}

function filterModules(category) {
    const modules = document.querySelectorAll('.module-card');
    const buttons = document.querySelectorAll('.category-filters .filter-btn');
    
    // Update active button
    buttons.forEach(btn => btn.classList.remove('active'));
    event.target.classList.add('active');
    
    // Filter modules
    modules.forEach(module => {
        if (category === 'all' || module.dataset.category === category) {
            module.style.display = 'block';
        } else {
            module.style.display = 'none';
        }
    });
}

function filterGuides(category) {
    const guides = document.querySelectorAll('.guide-card');
    const buttons = document.querySelectorAll('.category-filters .filter-btn');
    
    // Update active button
    buttons.forEach(btn => btn.classList.remove('active'));
    event.target.classList.add('active');
    
    // Filter guides
    guides.forEach(guide => {
        if (category === 'all' || guide.dataset.category === category) {
            guide.style.display = 'block';
        } else {
            guide.style.display = 'none';
        }
    });
}

function startModule(moduleId) {
    if (confirm('Start this educational module? You\'ll earn Care Coins for completing it!')) {
        window.location.href = `/educational-module.php?id=${moduleId}`;
    }
}

function readGuide(guideId) {
    window.location.href = `/pet-care-guide.php?id=${guideId}`;
}

function playGame(gameId) {
    window.location.href = `/educational-game.php?id=${gameId}`;
}
</script>

<style>
.kid-safe-badge {
    background: linear-gradient(135deg, #4ecdc4 0%, #44a08d 100%);
    color: white;
    padding: 15px;
    border-radius: 10px;
    text-align: center;
    margin-top: 15px;
}

.progress-dashboard {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    margin-bottom: 30px;
}

.progress-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.progress-stat {
    text-align: center;
    background: rgba(255, 255, 255, 0.1);
    padding: 20px;
    border-radius: 10px;
}

.stat-number {
    font-size: 2.5rem;
    font-weight: bold;
    margin-bottom: 5px;
}

.stat-label {
    font-size: 0.9rem;
    opacity: 0.9;
}

.tabs {
    display: flex;
    border-bottom: 2px solid #e1e5e9;
    margin-bottom: 20px;
}

.tab-button {
    padding: 12px 24px;
    background: none;
    border: none;
    cursor: pointer;
    font-size: 16px;
    font-weight: 500;
    color: #666;
    border-bottom: 3px solid transparent;
    transition: all 0.3s;
}

.tab-button:hover {
    color: #333;
    background-color: #f8f9fa;
}

.tab-button.active {
    color: #007bff;
    border-bottom-color: #007bff;
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

.category-filters {
    display: flex;
    gap: 10px;
    margin-bottom: 30px;
    flex-wrap: wrap;
}

.filter-btn {
    padding: 8px 16px;
    border: 1px solid #ddd;
    background: white;
    border-radius: 20px;
    cursor: pointer;
    transition: all 0.3s;
}

.filter-btn:hover {
    background: #f8f9fa;
}

.filter-btn.active {
    background: #007bff;
    color: white;
    border-color: #007bff;
}

.modules-grid, .guides-grid, .games-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
}

.module-card, .guide-card, .game-card {
    border: 1px solid #e1e5e9;
    border-radius: 15px;
    padding: 20px;
    transition: transform 0.3s, box-shadow 0.3s;
}

.module-card:hover, .guide-card:hover, .game-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
}

.module-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.module-icon {
    font-size: 2rem;
}

.module-difficulty .star {
    color: #ddd;
}

.module-difficulty .star.active {
    color: #ffd700;
}

.module-meta {
    display: flex;
    justify-content: space-between;
    margin: 15px 0;
    font-size: 0.9rem;
    color: #666;
}

.module-progress {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.completion-badge {
    background: #28a745;
    color: white;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 0.8rem;
}

.featured-badge {
    position: absolute;
    top: 10px;
    right: 10px;
    background: linear-gradient(135deg, #ffd700 0%, #ffed4e 100%);
    color: #333;
    padding: 5px 10px;
    border-radius: 15px;
    font-size: 0.8rem;
    font-weight: bold;
}

.guide-header, .game-header {
    position: relative;
    margin-bottom: 15px;
}

.guide-thumbnail, .game-thumbnail {
    width: 100%;
    height: 150px;
    object-fit: cover;
    border-radius: 10px;
}

.vet-info {
    margin: 10px 0;
}

.credentials {
    color: #666;
    font-size: 0.9rem;
    display: block;
}

.guide-stats {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 15px;
}

.rating .star {
    color: #ddd;
}

.rating .star.active {
    color: #ffd700;
}

.rating-count {
    color: #666;
    font-size: 0.9rem;
    margin-left: 5px;
}

.difficulty-badge {
    padding: 3px 8px;
    border-radius: 10px;
    font-size: 0.8rem;
    font-weight: bold;
}

.difficulty-basic { background: #28a745; color: white; }
.difficulty-intermediate { background: #ffc107; color: #333; }
.difficulty-advanced { background: #dc3545; color: white; }

.game-meta {
    margin: 15px 0;
}

.game-stats {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 10px;
}

.game-stats span {
    font-size: 0.85rem;
    color: #666;
    background: #f8f9fa;
    padding: 2px 6px;
    border-radius: 8px;
}

.coin-reward {
    color: #007bff !important;
    background: rgba(0, 123, 255, 0.1) !important;
    font-weight: bold;
}

.daily-limit {
    background: #fff3cd;
    color: #856404;
    padding: 8px;
    border-radius: 5px;
    font-size: 0.9rem;
    margin: 10px 0;
    text-align: center;
}

.leaderboard {
    background: white;
    border-radius: 10px;
    overflow: hidden;
}

.leaderboard-header {
    display: grid;
    grid-template-columns: 80px 1fr 200px 120px;
    gap: 20px;
    padding: 15px 20px;
    background: #f8f9fa;
    font-weight: bold;
    border-bottom: 1px solid #e1e5e9;
}

.leaderboard-row {
    display: grid;
    grid-template-columns: 80px 1fr 200px 120px;
    gap: 20px;
    padding: 15px 20px;
    border-bottom: 1px solid #f0f0f0;
    transition: background 0.3s;
}

.leaderboard-row:hover {
    background: #f8f9fa;
}

.leaderboard-row.current-user {
    background: linear-gradient(135deg, rgba(0, 123, 255, 0.1) 0%, rgba(40, 167, 69, 0.1) 100%);
    border: 2px solid #007bff;
}

.rank {
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
}

.user-info {
    display: flex;
    align-items: center;
    gap: 10px;
}

.you-badge {
    background: #007bff;
    color: white;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 0.8rem;
}

.user-stats {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.stat-item {
    font-size: 0.85rem;
    color: #666;
}

.education-score {
    display: flex;
    align-items: center;
    justify-content: center;
    color: #007bff;
    font-size: 1.1rem;
}

@media (max-width: 768px) {
    .leaderboard-header, .leaderboard-row {
        grid-template-columns: 60px 1fr 100px;
        gap: 10px;
    }
    
    .stats-header, .user-stats {
        display: none;
    }
    
    .modules-grid, .guides-grid, .games-grid {
        grid-template-columns: 1fr;
    }
    
    .progress-stats {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>

<?php require_once 'includes/scripts.php'; ?>