<?php
/**
 * Money Paws - Community Hub
 * User-generated content, guilds, mentorship, and AI personalities
 * Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>
 */
require_once 'includes/functions.php';
require_once 'includes/community_content.php';

requireLogin();

$user_id = $_SESSION['user_id'];
$active_tab = $_GET['tab'] ?? 'quests';

// Get user's reputation and capabilities
$user_reputation = getUserReputationScore($user_id);
$can_create_quests = canCreateUserQuests($user_id);
$can_create_guild = canCreateGuild($user_id);
$can_be_mentor = canBecomeMentor($user_id);

// Get content based on tab
$user_quests = getUserGeneratedQuests('active', null, 15);
$guilds = getGuilds(null, 'activity');

$pageTitle = 'Community Hub';
require_once 'includes/html_head.php';
require_once 'includes/header.php';
?>

<main>
    <div class="container">
        <div class="hero hero-padding">
            <h1>🏘️ Community Hub</h1>
            <p>Create, collaborate, and build the future of Money Paws together!</p>
        </div>

        <!-- User Status Banner -->
        <div class="user-status-banner card">
            <div class="status-info">
                <h3>Your Community Standing</h3>
                <div class="status-stats">
                    <div class="status-stat">
                        <div class="stat-icon">⭐</div>
                        <div class="stat-info">
                            <div class="stat-number"><?php echo number_format($user_reputation); ?></div>
                            <div class="stat-label">Reputation</div>
                        </div>
                    </div>
                    <div class="status-stat">
                        <div class="stat-icon">🎯</div>
                        <div class="stat-info">
                            <div class="stat-number"><?php echo $can_create_quests ? 'Unlocked' : 'Locked'; ?></div>
                            <div class="stat-label">Quest Creation</div>
                        </div>
                    </div>
                    <div class="status-stat">
                        <div class="stat-icon">🏰</div>
                        <div class="stat-info">
                            <div class="stat-number"><?php echo $can_create_guild ? 'Available' : 'Not Yet'; ?></div>
                            <div class="stat-label">Guild Creation</div>
                        </div>
                    </div>
                    <div class="status-stat">
                        <div class="stat-icon">👨‍🏫</div>
                        <div class="stat-info">
                            <div class="stat-number"><?php echo $can_be_mentor ? 'Qualified' : 'In Progress'; ?></div>
                            <div class="stat-label">Mentorship</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <div class="tabs">
            <button class="tab-button <?php echo $active_tab === 'quests' ? 'active' : ''; ?>" onclick="switchTab('quests')">
                🎯 Community Quests
            </button>
            <button class="tab-button <?php echo $active_tab === 'personalities' ? 'active' : ''; ?>" onclick="switchTab('personalities')">
                🧠 AI Personalities
            </button>
            <button class="tab-button <?php echo $active_tab === 'guilds' ? 'active' : ''; ?>" onclick="switchTab('guilds')">
                🏰 Guilds
            </button>
            <button class="tab-button <?php echo $active_tab === 'mentorship' ? 'active' : ''; ?>" onclick="switchTab('mentorship')">
                👨‍🏫 Mentorship
            </button>
        </div>

        <!-- Community Quests Tab -->
        <div id="quests-tab" class="tab-content <?php echo $active_tab === 'quests' ? 'active' : ''; ?>">
            <div class="card">
                <div class="quest-header">
                    <h3>🎯 Community Created Quests</h3>
                    <p>Complete challenges created by fellow community members and earn Care Coins!</p>
                    
                    <?php if ($can_create_quests): ?>
                        <button class="btn btn-primary" onclick="openQuestCreator()">
                            ✏️ Create New Quest
                        </button>
                    <?php else: ?>
                        <div class="locked-feature">
                            <p>🔒 <strong>Quest Creation Locked</strong></p>
                            <small>Need 100+ reputation, 3+ completed tutorials, and 7+ day old account</small>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="quest-filters">
                    <button class="filter-btn active" onclick="filterQuests('all')">All Quests</button>
                    <button class="filter-btn" onclick="filterQuests('pet_care')">Pet Care</button>
                    <button class="filter-btn" onclick="filterQuests('community')">Community</button>
                    <button class="filter-btn" onclick="filterQuests('creative')">Creative</button>
                    <button class="filter-btn" onclick="filterQuests('educational')">Educational</button>
                </div>

                <div class="quests-grid">
                    <?php foreach ($user_quests as $quest): ?>
                        <div class="quest-card" data-category="<?php echo $quest['category']; ?>">
                            <div class="quest-header-card">
                                <h4><?php echo htmlspecialchars($quest['title']); ?></h4>
                                <div class="quest-difficulty">
                                    <?php for ($i = 1; $i <= 3; $i++): ?>
                                        <span class="star <?php echo $i <= $quest['difficulty'] ? 'active' : ''; ?>">⭐</span>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            
                            <p><?php echo htmlspecialchars($quest['description']); ?></p>
                            
                            <div class="quest-meta">
                                <div class="creator-info">
                                    <span class="creator">By: <?php echo htmlspecialchars($quest['creator_name']); ?></span>
                                    <span class="rating">
                                        ⭐ <?php echo number_format($quest['avg_rating'] ?? 0, 1); ?>
                                        (<?php echo $quest['completion_count']; ?> completed)
                                    </span>
                                </div>
                                
                                <div class="quest-reward">
                                    💰 <?php echo $quest['reward_coins']; ?> Care Coins
                                </div>
                            </div>
                            
                            <div class="quest-stats">
                                <span class="attempts">👥 <?php echo $quest['attempt_count']; ?> attempts</span>
                                <span class="category-badge category-<?php echo $quest['category']; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $quest['category'])); ?>
                                </span>
                            </div>
                            
                            <?php if ($quest['is_repeatable']): ?>
                                <div class="repeatable-badge">🔄 Repeatable</div>
                            <?php endif; ?>
                            
                            <button class="btn btn-success btn-block" onclick="attemptQuest(<?php echo $quest['id']; ?>)">
                                🎯 Start Quest
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- AI Personalities Tab -->
        <div id="personalities-tab" class="tab-content <?php echo $active_tab === 'personalities' ? 'active' : ''; ?>">
            <div class="card">
                <h3>🧠 AI Pet Personalities</h3>
                <p>Your pets develop unique personalities based on how you care for them. Watch them grow and change!</p>
                
                <div class="personality-explanation">
                    <h4>How Pet Personalities Work</h4>
                    <div class="personality-info-grid">
                        <div class="info-card">
                            <div class="info-icon">🎮</div>
                            <h5>Dynamic Traits</h5>
                            <p>Pets develop 8 core personality traits that change based on your interactions</p>
                        </div>
                        <div class="info-card">
                            <div class="info-icon">🔄</div>
                            <h5>Adaptive Responses</h5>
                            <p>Personality affects how your pet responds to feeding, playing, and social interactions</p>
                        </div>
                        <div class="info-card">
                            <div class="info-icon">📈</div>
                            <h5>Long-term Growth</h5>
                            <p>Consistent care patterns shape lasting personality characteristics</p>
                        </div>
                    </div>
                </div>

                <?php 
                $user_pets = getUserPets($user_id);
                if (!empty($user_pets)): 
                ?>
                    <div class="pets-personality-grid">
                        <?php foreach ($user_pets as $pet): ?>
                            <div class="personality-card" onclick="viewPetPersonality(<?php echo $pet['id']; ?>)">
                                <img src="uploads/<?php echo htmlspecialchars($pet['filename']); ?>" 
                                     alt="<?php echo htmlspecialchars($pet['original_name']); ?>" 
                                     class="personality-pet-image">
                                
                                <div class="personality-info">
                                    <h4><?php echo htmlspecialchars($pet['original_name']); ?></h4>
                                    
                                    <!-- Sample personality traits preview -->
                                    <div class="trait-preview">
                                        <div class="trait-bar">
                                            <span class="trait-label">Playfulness</span>
                                            <div class="trait-progress">
                                                <div class="trait-fill" style="width: <?php echo rand(30, 90); ?>%"></div>
                                            </div>
                                        </div>
                                        <div class="trait-bar">
                                            <span class="trait-label">Friendliness</span>
                                            <div class="trait-progress">
                                                <div class="trait-fill" style="width: <?php echo rand(40, 95); ?>%"></div>
                                            </div>
                                        </div>
                                        <div class="trait-bar">
                                            <span class="trait-label">Energy</span>
                                            <div class="trait-progress">
                                                <div class="trait-fill" style="width: <?php echo rand(25, 85); ?>%"></div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <button class="btn btn-outline btn-sm">View Full Profile</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="no-pets-message">
                        <p>You don't have any pets yet! <a href="gallery.php">Adopt your first pet</a> to start building their unique personality.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Guilds Tab -->
        <div id="guilds-tab" class="tab-content <?php echo $active_tab === 'guilds' ? 'active' : ''; ?>">
            <div class="card">
                <div class="guild-header">
                    <h3>🏰 Community Guilds</h3>
                    <p>Join or create guilds to collaborate on larger projects and compete in guild challenges!</p>
                    
                    <?php if ($can_create_guild): ?>
                        <button class="btn btn-primary" onclick="openGuildCreator()">
                            🏰 Create New Guild
                        </button>
                    <?php else: ?>
                        <div class="locked-feature">
                            <p>🔒 <strong>Guild Creation Locked</strong></p>
                            <small>Need 250+ reputation and cannot already lead a guild</small>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="guild-filters">
                    <button class="filter-btn active" onclick="filterGuilds('all')">All Guilds</button>
                    <button class="filter-btn" onclick="filterGuilds('pet_care')">Pet Care Focus</button>
                    <button class="filter-btn" onclick="filterGuilds('education')">Educational</button>
                    <button class="filter-btn" onclick="filterGuilds('social')">Social</button>
                    <button class="filter-btn" onclick="filterGuilds('competition')">Competitive</button>
                </div>

                <div class="guilds-grid">
                    <?php foreach ($guilds as $guild): ?>
                        <div class="guild-card" data-focus="<?php echo $guild['focus_area']; ?>">
                            <div class="guild-header-card">
                                <h4><?php echo htmlspecialchars($guild['name']); ?></h4>
                                <div class="guild-badge">
                                    <?php echo ucfirst($guild['focus_area']); ?>
                                </div>
                            </div>
                            
                            <p><?php echo htmlspecialchars(substr($guild['description'], 0, 120)) . '...'; ?></p>
                            
                            <div class="guild-stats">
                                <div class="guild-stat">
                                    <span class="stat-number"><?php echo $guild['member_count']; ?></span>
                                    <span class="stat-label">Members</span>
                                </div>
                                <div class="guild-stat">
                                    <span class="stat-number"><?php echo number_format($guild['total_contribution']); ?></span>
                                    <span class="stat-label">Contribution</span>
                                </div>
                                <div class="guild-stat">
                                    <span class="stat-number"><?php echo $guild['last_activity'] ? time_elapsed_string($guild['last_activity']) : 'New'; ?></span>
                                    <span class="stat-label">Last Active</span>
                                </div>
                            </div>
                            
                            <div class="guild-meta">
                                <span class="founder">Founded by: <?php echo htmlspecialchars($guild['founder_name']); ?></span>
                                <span class="capacity">
                                    <?php echo $guild['member_count']; ?>/<?php echo $guild['max_members']; ?> members
                                </span>
                            </div>
                            
                            <button class="btn btn-success btn-block" onclick="joinGuild(<?php echo $guild['id']; ?>)">
                                <?php echo $guild['join_type'] === 'open' ? '🚪 Join Guild' : '📝 Apply to Join'; ?>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Mentorship Tab -->
        <div id="mentorship-tab" class="tab-content <?php echo $active_tab === 'mentorship' ? 'active' : ''; ?>">
            <div class="card">
                <div class="mentorship-header">
                    <h3>👨‍🏫 Mentorship Program</h3>
                    <p>Learn from experienced community members or share your knowledge with newcomers!</p>
                    
                    <?php if ($can_be_mentor): ?>
                        <button class="btn btn-primary" onclick="becomeMentor()">
                            🌟 Become a Mentor
                        </button>
                    <?php else: ?>
                        <div class="mentor-requirements">
                            <p>📚 <strong>Mentor Requirements</strong></p>
                            <ul>
                                <li>Complete 10+ educational modules</li>
                                <li>Earn 500+ reputation points</li>
                                <li>Account active for 30+ days</li>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="mentorship-sections">
                    <div class="mentorship-section">
                        <h4>🎓 Find a Mentor</h4>
                        <p>Get personalized guidance from experienced community members</p>
                        <button class="btn btn-outline" onclick="browseMentors()">Browse Available Mentors</button>
                    </div>
                    
                    <div class="mentorship-section">
                        <h4>🤝 Peer Learning</h4>
                        <p>Connect with other learners at your level for mutual support</p>
                        <button class="btn btn-outline" onclick="findPeers()">Find Study Partners</button>
                    </div>
                    
                    <div class="mentorship-section">
                        <h4>📖 Mentorship Resources</h4>
                        <p>Guides and tools for effective mentoring relationships</p>
                        <button class="btn btn-outline" onclick="viewResources()">View Resources</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Quest Creator Modal -->
<div id="questCreatorModal" class="modal">
    <div class="modal-content large">
        <span class="modal-close">&times;</span>
        <h3>✏️ Create Community Quest</h3>
        <form id="questCreatorForm">
            <div class="form-group">
                <label for="quest-title">Quest Title:</label>
                <input type="text" id="quest-title" name="title" class="form-control" 
                       placeholder="Give your quest an engaging title" maxlength="100" required>
            </div>
            
            <div class="form-group">
                <label for="quest-description">Description:</label>
                <textarea id="quest-description" name="description" class="form-control" 
                          placeholder="Describe what participants need to do..." rows="4" required></textarea>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="quest-category">Category:</label>
                    <select id="quest-category" name="category" class="form-control" required>
                        <option value="pet_care">Pet Care</option>
                        <option value="community">Community</option>
                        <option value="creative">Creative</option>
                        <option value="educational">Educational</option>
                        <option value="social">Social</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="quest-difficulty">Difficulty:</label>
                    <select id="quest-difficulty" name="difficulty" class="form-control" required>
                        <option value="1">⭐ Easy</option>
                        <option value="2">⭐⭐ Medium</option>
                        <option value="3">⭐⭐⭐ Hard</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="quest-reward">Reward (Care Coins):</label>
                    <input type="number" id="quest-reward" name="reward_coins" class="form-control" 
                           min="5" max="50" value="15" required>
                </div>
            </div>
            
            <div class="form-group">
                <label>
                    <input type="checkbox" name="is_repeatable"> 
                    Allow users to repeat this quest
                </label>
            </div>
            
            <button type="submit" class="btn btn-primary">
                🎯 Create Quest (+15 Care Coins)
            </button>
        </form>
    </div>
</div>

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

function filterQuests(category) {
    const quests = document.querySelectorAll('.quest-card');
    const buttons = document.querySelectorAll('.quest-filters .filter-btn');
    
    buttons.forEach(btn => btn.classList.remove('active'));
    event.target.classList.add('active');
    
    quests.forEach(quest => {
        if (category === 'all' || quest.dataset.category === category) {
            quest.style.display = 'block';
        } else {
            quest.style.display = 'none';
        }
    });
}

function filterGuilds(focus) {
    const guilds = document.querySelectorAll('.guild-card');
    const buttons = document.querySelectorAll('.guild-filters .filter-btn');
    
    buttons.forEach(btn => btn.classList.remove('active'));
    event.target.classList.add('active');
    
    guilds.forEach(guild => {
        if (focus === 'all' || guild.dataset.focus === focus) {
            guild.style.display = 'block';
        } else {
            guild.style.display = 'none';
        }
    });
}

function openQuestCreator() {
    document.getElementById('questCreatorModal').classList.add('is-visible');
}

function attemptQuest(questId) {
    if (confirm('Start this community quest? You can earn Care Coins by completing it!')) {
        window.location.href = `/community-quest.php?id=${questId}`;
    }
}

function viewPetPersonality(petId) {
    window.location.href = `/pet-personality.php?id=${petId}`;
}

function joinGuild(guildId) {
    if (confirm('Join this guild? You can collaborate with other members on projects!')) {
        fetch('/api/join-guild.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `guild_id=${guildId}`
        })
        .then(response => response.json())
        .then(data => {
            alert(data.message);
            if (data.success) {
                location.reload();
            }
        });
    }
}

// Quest Creator Form
document.getElementById('questCreatorForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch('/api/create-user-quest.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        alert(data.message);
        if (data.success) {
            document.getElementById('questCreatorModal').classList.remove('is-visible');
            location.reload();
        }
    });
});

// Modal functionality
document.querySelector('.modal-close')?.addEventListener('click', function() {
    document.getElementById('questCreatorModal').classList.remove('is-visible');
});
</script>

<style>
.user-status-banner {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    margin-bottom: 30px;
}

.status-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.status-stat {
    display: flex;
    align-items: center;
    gap: 15px;
    background: rgba(255, 255, 255, 0.1);
    padding: 20px;
    border-radius: 10px;
}

.stat-icon {
    font-size: 2rem;
}

.stat-number {
    font-size: 1.2rem;
    font-weight: bold;
}

.stat-label {
    font-size: 0.9rem;
    opacity: 0.9;
}

.locked-feature {
    background: #fff3cd;
    color: #856404;
    padding: 15px;
    border-radius: 10px;
    text-align: center;
    margin: 15px 0;
}

.quest-filters, .guild-filters {
    display: flex;
    gap: 10px;
    margin: 20px 0;
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

.quests-grid, .guilds-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 20px;
}

.quest-card, .guild-card {
    border: 1px solid #e1e5e9;
    border-radius: 15px;
    padding: 20px;
    transition: transform 0.3s, box-shadow 0.3s;
}

.quest-card:hover, .guild-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
}

.quest-header-card, .guild-header-card {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.quest-difficulty .star {
    color: #ddd;
}

.quest-difficulty .star.active {
    color: #ffd700;
}

.quest-meta, .guild-meta {
    margin: 15px 0;
    font-size: 0.9rem;
}

.creator-info {
    margin-bottom: 10px;
}

.creator {
    color: #666;
    display: block;
}

.rating {
    color: #ffd700;
}

.quest-reward {
    background: rgba(0, 123, 255, 0.1);
    color: #007bff;
    padding: 5px 10px;
    border-radius: 15px;
    font-weight: bold;
    text-align: center;
}

.quest-stats, .guild-stats {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin: 15px 0;
}

.category-badge {
    padding: 3px 8px;
    border-radius: 10px;
    font-size: 0.8rem;
    font-weight: bold;
    color: white;
}

.category-pet_care { background: #28a745; }
.category-community { background: #17a2b8; }
.category-creative { background: #e83e8c; }
.category-educational { background: #6f42c1; }
.category-social { background: #fd7e14; }

.repeatable-badge {
    background: #ffc107;
    color: #856404;
    padding: 3px 8px;
    border-radius: 10px;
    font-size: 0.8rem;
    text-align: center;
    margin: 10px 0;
}

.guild-badge {
    background: #007bff;
    color: white;
    padding: 3px 8px;
    border-radius: 10px;
    font-size: 0.8rem;
}

.guild-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
    margin: 20px 0;
}

.guild-stat {
    text-align: center;
    background: #f8f9fa;
    padding: 10px;
    border-radius: 8px;
}

.guild-stat .stat-number {
    font-weight: bold;
    color: #007bff;
    display: block;
}

.guild-stat .stat-label {
    font-size: 0.8rem;
    color: #666;
}

.personality-info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.info-card {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 10px;
    text-align: center;
}

.info-icon {
    font-size: 2rem;
    margin-bottom: 10px;
}

.pets-personality-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-top: 30px;
}

.personality-card {
    border: 1px solid #e1e5e9;
    border-radius: 15px;
    padding: 20px;
    cursor: pointer;
    transition: all 0.3s;
}

.personality-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
}

.personality-pet-image {
    width: 100%;
    height: 150px;
    object-fit: cover;
    border-radius: 10px;
    margin-bottom: 15px;
}

.trait-preview {
    margin: 15px 0;
}

.trait-bar {
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 8px 0;
}

.trait-label {
    font-size: 0.85rem;
    color: #666;
    width: 80px;
}

.trait-progress {
    flex: 1;
    height: 6px;
    background: #e9ecef;
    border-radius: 3px;
    overflow: hidden;
}

.trait-fill {
    height: 100%;
    background: linear-gradient(90deg, #28a745, #20c997);
    transition: width 0.3s;
}

.mentorship-sections {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-top: 30px;
}

.mentorship-section {
    background: #f8f9fa;
    padding: 30px;
    border-radius: 15px;
    text-align: center;
}

.mentor-requirements {
    background: #d1ecf1;
    color: #0c5460;
    padding: 20px;
    border-radius: 10px;
    margin: 15px 0;
}

.mentor-requirements ul {
    margin: 10px 0;
    text-align: left;
}

.modal.large .modal-content {
    max-width: 600px;
}

.form-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
}

@media (max-width: 768px) {
    .status-stats {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .quests-grid, .guilds-grid {
        grid-template-columns: 1fr;
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
}
</style>

<?php require_once 'includes/scripts.php'; ?>