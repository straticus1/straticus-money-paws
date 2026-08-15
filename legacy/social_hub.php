<?php
/**
 * Money Paws - Social Hub
 * Central hub for contests, stories, achievements, and social features
 * Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>
 */
require_once 'includes/functions.php';
require_once 'includes/social_features.php';

requireLogin();

$user_id = $_SESSION['user_id'];
$active_tab = $_GET['tab'] ?? 'contests';

// Get current contest
$current_contest = getCurrentPhotoContest();

// Get recent stories
$recent_stories = getPetStories(null, 10);

// Get user achievements
$user_achievements = getUserAchievements($user_id);

// Get referral stats
$referral_stats = getUserReferralStats($user_id);
$referral_code = getUserReferralCode($user_id);

$pageTitle = 'Social Hub';
require_once 'includes/html_head.php';
require_once 'includes/header.php';
?>

<main>
    <div class="container">
        <div class="hero hero-padding">
            <h1>🌟 Social Hub</h1>
            <p>Connect, compete, and share your pet adventures with the Money Paws community!</p>
        </div>

        <!-- Navigation Tabs -->
        <div class="tabs">
            <button class="tab-button <?php echo $active_tab === 'contests' ? 'active' : ''; ?>" onclick="switchTab('contests')">
                🏆 Photo Contests
            </button>
            <button class="tab-button <?php echo $active_tab === 'stories' ? 'active' : ''; ?>" onclick="switchTab('stories')">
                📖 Pet Stories  
            </button>
            <button class="tab-button <?php echo $active_tab === 'achievements' ? 'active' : ''; ?>" onclick="switchTab('achievements')">
                🎖️ Achievements
            </button>
            <button class="tab-button <?php echo $active_tab === 'referrals' ? 'active' : ''; ?>" onclick="switchTab('referrals')">
                👥 Refer Friends
            </button>
        </div>

        <!-- Photo Contests Tab -->
        <div id="contests-tab" class="tab-content <?php echo $active_tab === 'contests' ? 'active' : ''; ?>">
            <div class="card">
                <?php if ($current_contest): ?>
                    <div class="current-contest">
                        <h2>🏆 Current Contest: <?php echo htmlspecialchars($current_contest['title']); ?></h2>
                        <p><?php echo htmlspecialchars($current_contest['description']); ?></p>
                        
                        <div class="contest-info">
                            <div class="contest-detail">
                                <strong>Prize Pool:</strong> <?php echo number_format($current_contest['prize_coins']); ?> Care Coins
                            </div>
                            <div class="contest-detail">
                                <strong>Ends:</strong> <?php echo date('M j, Y g:i A', strtotime($current_contest['end_date'])); ?>
                            </div>
                            <div class="contest-detail">
                                <strong>Theme:</strong> <?php echo htmlspecialchars($current_contest['theme']); ?>
                            </div>
                        </div>

                        <div class="contest-actions">
                            <button class="btn btn-primary" onclick="openEntryModal()">
                                📸 Enter Contest
                            </button>
                            <button class="btn btn-secondary" onclick="viewContestEntries(<?php echo $current_contest['id']; ?>)">
                                👀 View Entries
                            </button>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="no-contest">
                        <h3>No Active Contest</h3>
                        <p>Check back soon for our next exciting photo contest!</p>
                    </div>
                <?php endif; ?>

                <!-- Past Contests -->
                <div class="past-contests">
                    <h3>Recent Contests</h3>
                    <div class="contest-grid">
                        <?php 
                        $past_contests = getPhotoContests(6);
                        foreach ($past_contests as $contest): 
                        ?>
                            <div class="contest-card">
                                <h4><?php echo htmlspecialchars($contest['title']); ?></h4>
                                <p class="contest-theme">Theme: <?php echo htmlspecialchars($contest['theme']); ?></p>
                                <p class="entry-count"><?php echo $contest['entry_count']; ?> entries</p>
                                <button class="btn btn-sm btn-outline" onclick="viewContestEntries(<?php echo $contest['id']; ?>)">
                                    View Results
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pet Stories Tab -->
        <div id="stories-tab" class="tab-content <?php echo $active_tab === 'stories' ? 'active' : ''; ?>">
            <div class="card">
                <div class="story-creator">
                    <h3>📖 Share Your Pet's Story</h3>
                    <div class="story-form">
                        <select id="story-pet" class="form-control">
                            <option value="">Choose a pet...</option>
                            <?php 
                            $user_pets = getUserPets($user_id);
                            foreach ($user_pets as $pet): 
                            ?>
                                <option value="<?php echo $pet['id']; ?>"><?php echo htmlspecialchars($pet['original_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <textarea id="story-content" placeholder="What's happening with your pet today?" class="form-control" maxlength="280"></textarea>
                        <div class="story-actions">
                            <button class="btn btn-primary" onclick="createStory()">
                                📱 Share Story (+5 Care Coins)
                            </button>
                        </div>
                    </div>
                </div>

                <div class="stories-feed">
                    <h3>🌟 Community Stories</h3>
                    <div class="stories-list">
                        <?php foreach ($recent_stories as $story): ?>
                            <div class="story-item">
                                <div class="story-header">
                                    <img src="uploads/<?php echo htmlspecialchars($story['filename']); ?>" 
                                         alt="<?php echo htmlspecialchars($story['original_name']); ?>" 
                                         class="story-pet-thumb">
                                    <div class="story-info">
                                        <strong><?php echo htmlspecialchars($story['user_name']); ?></strong>
                                        <span class="story-pet-name"><?php echo htmlspecialchars($story['original_name']); ?></span>
                                        <span class="story-time"><?php echo time_elapsed_string($story['created_at']); ?></span>
                                    </div>
                                </div>
                                <div class="story-content">
                                    <?php echo nl2br(htmlspecialchars($story['content'])); ?>
                                </div>
                                <div class="story-actions">
                                    <button class="btn btn-sm btn-like" onclick="likeStory(<?php echo $story['id']; ?>)">
                                        ❤️ <?php echo $story['like_count']; ?>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Achievements Tab -->
        <div id="achievements-tab" class="tab-content <?php echo $active_tab === 'achievements' ? 'active' : ''; ?>">
            <div class="card">
                <h3>🎖️ Your Achievements</h3>
                
                <?php if (empty($user_achievements)): ?>
                    <div class="no-achievements">
                        <p>Start your journey! Complete daily quests, help the community, and participate in contests to earn achievements.</p>
                    </div>
                <?php else: ?>
                    <div class="achievements-grid">
                        <?php foreach ($user_achievements as $achievement): ?>
                            <div class="achievement-card earned">
                                <div class="achievement-icon"><?php echo $achievement['icon']; ?></div>
                                <div class="achievement-info">
                                    <h4><?php echo htmlspecialchars($achievement['name']); ?></h4>
                                    <p><?php echo htmlspecialchars($achievement['description']); ?></p>
                                    <div class="achievement-reward">
                                        💰 <?php echo number_format($achievement['reward_coins']); ?> Care Coins
                                    </div>
                                    <div class="achievement-date">
                                        Earned: <?php echo date('M j, Y', strtotime($achievement['earned_at'])); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Available Achievements -->
                <div class="available-achievements">
                    <h3>🎯 Available Achievements</h3>
                    <div class="achievements-grid">
                        <?php 
                        $available_achievements = getAvailableAchievements();
                        foreach ($available_achievements as $achievement):
                            if (!hasUserEarnedAchievement($user_id, $achievement['id'])):
                        ?>
                            <div class="achievement-card available">
                                <div class="achievement-icon"><?php echo $achievement['icon']; ?></div>
                                <div class="achievement-info">
                                    <h4><?php echo htmlspecialchars($achievement['name']); ?></h4>
                                    <p><?php echo htmlspecialchars($achievement['description']); ?></p>
                                    <div class="achievement-reward">
                                        💰 <?php echo number_format($achievement['reward_coins']); ?> Care Coins
                                    </div>
                                    <div class="achievement-progress">
                                        <?php echo getAchievementProgressText($user_id, $achievement); ?>
                                    </div>
                                </div>
                            </div>
                        <?php 
                            endif;
                        endforeach; 
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Referrals Tab -->
        <div id="referrals-tab" class="tab-content <?php echo $active_tab === 'referrals' ? 'active' : ''; ?>">
            <div class="card">
                <h3>👥 Refer Friends & Earn Together!</h3>
                
                <div class="referral-stats">
                    <div class="stat-card">
                        <h4><?php echo $referral_stats['referral_count']; ?></h4>
                        <p>Total Referrals</p>
                    </div>
                    <div class="stat-card">
                        <h4><?php echo $referral_stats['recent_referrals']; ?></h4>
                        <p>This Month</p>
                    </div>
                    <div class="stat-card">
                        <h4><?php echo $referral_stats['referral_count'] * 50; ?></h4>
                        <p>Care Coins Earned</p>
                    </div>
                </div>

                <div class="referral-code-section">
                    <h4>Your Referral Code</h4>
                    <div class="referral-code">
                        <input type="text" value="<?php echo $referral_code; ?>" id="referral-code-input" readonly>
                        <button class="btn btn-secondary" onclick="copyReferralCode()">📋 Copy</button>
                    </div>
                    <p class="referral-benefits">
                        🎁 <strong>You get 50 Care Coins</strong> for each friend who joins<br>
                        🎁 <strong>Your friends get 25 Care Coins</strong> when they sign up with your code
                    </p>
                </div>

                <div class="referral-links">
                    <h4>Share Your Referral</h4>
                    <div class="share-buttons">
                        <button class="btn btn-social btn-twitter" onclick="shareOnTwitter()">
                            🐦 Share on Twitter
                        </button>
                        <button class="btn btn-social btn-facebook" onclick="shareOnFacebook()">
                            📘 Share on Facebook
                        </button>
                        <button class="btn btn-social btn-copy" onclick="copyReferralLink()">
                            🔗 Copy Link
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Contest Entry Modal -->
<div id="entryModal" class="modal">
    <div class="modal-content">
        <span class="modal-close">&times;</span>
        <h3>📸 Enter Photo Contest</h3>
        <form id="contestEntryForm">
            <?php if ($current_contest): ?>
                <input type="hidden" name="contest_id" value="<?php echo $current_contest['id']; ?>">
                <div class="form-group">
                    <label for="contest-pet">Choose Your Pet:</label>
                    <select name="pet_id" id="contest-pet" class="form-control" required>
                        <option value="">Select a pet...</option>
                        <?php foreach ($user_pets as $pet): ?>
                            <option value="<?php echo $pet['id']; ?>"><?php echo htmlspecialchars($pet['original_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="contest-description">Description (Optional):</label>
                    <textarea name="description" id="contest-description" class="form-control" 
                              placeholder="Tell us about this photo..." maxlength="200"></textarea>
                </div>
                <button type="submit" class="btn btn-primary">
                    🏆 Submit Entry (+10 Care Coins)
                </button>
            <?php endif; ?>
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

function openEntryModal() {
    document.getElementById('entryModal').classList.add('is-visible');
}

function createStory() {
    const petId = document.getElementById('story-pet').value;
    const content = document.getElementById('story-content').value;
    
    if (!petId || !content.trim()) {
        alert('Please select a pet and write some content');
        return;
    }
    
    fetch('/api/create-pet-story.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `pet_id=${petId}&content=${encodeURIComponent(content)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert(data.message);
        }
    });
}

function likeStory(storyId) {
    fetch('/api/like-pet-story.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `story_id=${storyId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        }
    });
}

function copyReferralCode() {
    const input = document.getElementById('referral-code-input');
    input.select();
    document.execCommand('copy');
    alert('Referral code copied!');
}

function shareOnTwitter() {
    const code = document.getElementById('referral-code-input').value;
    const text = `Join me on Money Paws - the most fun way to care for virtual pets and learn about animal care! Use code ${code} and we both get Care Coins! 🐾`;
    const url = `https://twitter.com/intent/tweet?text=${encodeURIComponent(text)}&url=https://paws.money`;
    window.open(url, '_blank');
}

function shareOnFacebook() {
    const url = `https://www.facebook.com/sharer/sharer.php?u=https://paws.money`;
    window.open(url, '_blank');
}

function copyReferralLink() {
    const code = document.getElementById('referral-code-input').value;
    const link = `https://paws.money/register.php?ref=${code}`;
    navigator.clipboard.writeText(link).then(() => {
        alert('Referral link copied!');
    });
}

// Contest entry form
document.getElementById('contestEntryForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch('/api/submit-contest-entry.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        alert(data.message);
        if (data.success) {
            document.getElementById('entryModal').classList.remove('is-visible');
            location.reload();
        }
    });
});

// Modal close functionality
document.querySelector('.modal-close')?.addEventListener('click', function() {
    document.getElementById('entryModal').classList.remove('is-visible');
});
</script>

<style>
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

.current-contest {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 30px;
    border-radius: 15px;
    margin-bottom: 30px;
}

.contest-info {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.contest-detail {
    background: rgba(255, 255, 255, 0.1);
    padding: 15px;
    border-radius: 10px;
}

.contest-actions {
    margin-top: 20px;
}

.contest-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.contest-card {
    border: 1px solid #e1e5e9;
    border-radius: 10px;
    padding: 20px;
    text-align: center;
}

.story-creator {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 30px;
}

.story-form textarea {
    min-height: 80px;
    resize: vertical;
}

.story-actions {
    margin-top: 15px;
}

.stories-list {
    max-height: 600px;
    overflow-y: auto;
}

.story-item {
    border: 1px solid #e1e5e9;
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 15px;
}

.story-header {
    display: flex;
    align-items: center;
    margin-bottom: 15px;
}

.story-pet-thumb {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
    margin-right: 15px;
}

.story-info {
    display: flex;
    flex-direction: column;
}

.story-pet-name {
    color: #666;
    font-size: 14px;
}

.story-time {
    color: #999;
    font-size: 12px;
}

.achievements-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.achievement-card {
    border: 1px solid #e1e5e9;
    border-radius: 10px;
    padding: 20px;
    display: flex;
    align-items: center;
}

.achievement-card.earned {
    background: linear-gradient(135deg, #ffeaa7 0%, #fab1a0 100%);
    border-color: #fdcb6e;
}

.achievement-card.available {
    background: #f8f9fa;
    opacity: 0.7;
}

.achievement-icon {
    font-size: 48px;
    margin-right: 20px;
}

.achievement-reward {
    background: rgba(0, 123, 255, 0.1);
    color: #007bff;
    padding: 5px 10px;
    border-radius: 15px;
    font-size: 12px;
    margin-top: 10px;
    display: inline-block;
}

.referral-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: linear-gradient(135deg, #74b9ff 0%, #0984e3 100%);
    color: white;
    padding: 20px;
    border-radius: 10px;
    text-align: center;
}

.stat-card h4 {
    font-size: 2rem;
    margin: 0;
}

.referral-code-section {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 30px;
}

.referral-code {
    display: flex;
    gap: 10px;
    margin: 15px 0;
}

.referral-code input {
    flex: 1;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 5px;
    font-family: monospace;
    font-size: 18px;
    text-align: center;
    font-weight: bold;
}

.share-buttons {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.btn-social {
    padding: 10px 20px;
    border-radius: 5px;
    border: none;
    cursor: pointer;
    font-weight: bold;
}

.btn-twitter { background: #1da1f2; color: white; }
.btn-facebook { background: #4267b2; color: white; }
.btn-copy { background: #6c757d; color: white; }
</style>

<?php require_once 'includes/scripts.php'; ?>