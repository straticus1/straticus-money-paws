<?php
/**
 * Money Paws - Metaverse 3D Pet Worlds
 * Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>
 */

require_once 'includes/functions.php';
require_once 'includes/daily_quests.php';

requireLogin();

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Handle world entry
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enter_world'])) {
    requireCSRFToken();
    
    $world_id = intval($_POST['world_id']);
    $pet_ids = isset($_POST['pet_ids']) ? array_map('intval', $_POST['pet_ids']) : [];
    
    // Validate pets belong to user
    if (!empty($pet_ids)) {
        $pdo = get_db();
        $placeholders = str_repeat('?,', count($pet_ids) - 1) . '?';
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM pets WHERE user_id = ? AND id IN ($placeholders)");
        $stmt->execute(array_merge([$user_id], $pet_ids));
        
        if ($stmt->fetchColumn() !== count($pet_ids)) {
            $error = 'Invalid pet selection';
        }
    }
    
    if (!$error) {
        // Create world session
        $pdo = get_db();
        $stmt = $pdo->prepare("
            INSERT INTO user_world_sessions 
            (user_id, world_id, pet_ids, session_start)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$user_id, $world_id, json_encode($pet_ids)]);
        
        // Update quest progress
        updateQuestProgress($user_id, 'visit_metaverse');
        
        $success = 'Entering 3D world... Loading environment!';
    }
}

// Get available worlds
$pdo = get_db();
$stmt = $pdo->prepare("
    SELECT * FROM metaverse_worlds 
    WHERE is_active = 1 
    ORDER BY world_type, name
");
$stmt->execute();
$available_worlds = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user's pets for world entry
$stmt = $pdo->prepare("SELECT id, name, image_url FROM pets WHERE user_id = ? ORDER BY name");
$stmt->execute([$user_id]);
$user_pets = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent world sessions
$stmt = $pdo->prepare("
    SELECT ws.*, mw.name as world_name, mw.world_type
    FROM user_world_sessions ws
    JOIN metaverse_worlds mw ON ws.world_id = mw.id
    WHERE ws.user_id = ?
    ORDER BY ws.session_start DESC
    LIMIT 10
");
$stmt->execute([$user_id]);
$recent_sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-12">
            <h1 class="text-center mb-4">🌍 Metaverse Pet Worlds</h1>
            <p class="text-center text-muted mb-4">
                Explore immersive 3D worlds with your pets, learn, and socialize with the community!
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

    <!-- 3D World Viewer -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-vr-cardboard"></i> 3D World Viewer</h5>
                    <div class="world-controls">
                        <button id="fullscreen-btn" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-expand"></i> Fullscreen
                        </button>
                        <button id="weather-btn" class="btn btn-sm btn-outline-info">
                            <i class="fas fa-cloud-sun"></i> Weather: Sunny
                        </button>
                        <button id="time-btn" class="btn btn-sm btn-outline-warning">
                            <i class="fas fa-clock"></i> Time: 12:00
                        </button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div id="metaverse-container" style="height: 500px; background: linear-gradient(to bottom, #87CEEB, #98FB98);">
                        <canvas id="metaverse-canvas" style="width: 100%; height: 100%;"></canvas>
                        
                        <!-- Loading Overlay -->
                        <div id="loading-overlay" class="position-absolute w-100 h-100 d-flex align-items-center justify-content-center" 
                             style="top: 0; left: 0; background: rgba(135, 206, 235, 0.9); z-index: 10;">
                            <div class="text-center text-white">
                                <div class="spinner-border mb-3" role="status"></div>
                                <h5>Loading 3D World...</h5>
                                <p>Initializing environment and pet avatars</p>
                            </div>
                        </div>

                        <!-- World Entry Required -->
                        <div id="entry-required" class="position-absolute w-100 h-100 d-flex align-items-center justify-content-center" 
                             style="top: 0; left: 0; background: rgba(0, 0, 0, 0.7); z-index: 5;">
                            <div class="text-center text-white">
                                <i class="fas fa-door-open fa-3x mb-3"></i>
                                <h4>Choose a World to Enter</h4>
                                <p>Select a 3D world below to begin your adventure!</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-md-8">
                            <small class="text-muted">
                                <i class="fas fa-info-circle"></i>
                                Use mouse to look around, WASD to move, click on objects to interact
                            </small>
                        </div>
                        <div class="col-md-4 text-right">
                            <small class="text-muted">
                                Online users: <span id="online-count">0</span>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Available Worlds -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-globe"></i> Available Worlds</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($available_worlds as $world): ?>
                            <?php
                            $world_icons = [
                                'park' => '🏞️',
                                'beach' => '🏖️', 
                                'forest' => '🌲',
                                'city' => '🏙️',
                                'space_station' => '🚀',
                                'educational_zone' => '🎓'
                            ];
                            $icon = $world_icons[$world['world_type']] ?? '🌍';
                            
                            $world_colors = [
                                'park' => 'success',
                                'beach' => 'info',
                                'forest' => 'primary',
                                'educational_zone' => 'warning'
                            ];
                            $color = $world_colors[$world['world_type']] ?? 'secondary';
                            ?>
                            
                            <div class="col-md-6 mb-3">
                                <div class="card border-<?= $color ?>">
                                    <div class="card-body">
                                        <div class="text-center mb-2">
                                            <div style="font-size: 2rem;"><?= $icon ?></div>
                                            <h6 class="card-title"><?= htmlspecialchars($world['name']) ?></h6>
                                        </div>
                                        
                                        <p class="card-text small text-muted">
                                            <?= htmlspecialchars($world['description']) ?>
                                        </p>
                                        
                                        <div class="mb-2">
                                            <span class="badge badge-<?= $color ?>">
                                                <?= ucwords(str_replace('_', ' ', $world['world_type'])) ?>
                                            </span>
                                            <?php if ($world['weather_enabled']): ?>
                                                <span class="badge badge-info">Dynamic Weather</span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="text-center">
                                            <button type="button" class="btn btn-<?= $color ?> btn-sm" 
                                                    onclick="showWorldEntry(<?= $world['id'] ?>, '<?= htmlspecialchars($world['name']) ?>')">
                                                <i class="fas fa-sign-in-alt"></i> Enter World
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Session History & Social -->
        <div class="col-md-4">
            <div class="card mb-3">
                <div class="card-header">
                    <h6><i class="fas fa-history"></i> Recent Sessions</h6>
                </div>
                <div class="card-body" style="max-height: 300px; overflow-y: auto;">
                    <?php if (empty($recent_sessions)): ?>
                        <p class="text-muted text-center">No world visits yet!</p>
                    <?php else: ?>
                        <?php foreach ($recent_sessions as $session): ?>
                            <div class="mb-2 pb-2 border-bottom">
                                <div class="d-flex justify-content-between">
                                    <strong><?= htmlspecialchars($session['world_name']) ?></strong>
                                    <small class="text-muted"><?= timeAgo($session['session_start']) ?></small>
                                </div>
                                <small class="text-muted">
                                    <?= $session['care_coins_earned'] ?> coins earned
                                    • <?= $session['social_interactions_count'] ?> interactions
                                </small>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h6><i class="fas fa-users"></i> Social Features</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <strong>🤝 Interactions Available:</strong>
                        <ul class="small mb-2">
                            <li>Wave at other users</li>
                            <li>Collaborative pet games</li>
                            <li>Help requests & assistance</li>
                            <li>Educational activities</li>
                        </ul>
                    </div>
                    
                    <div class="mb-3">
                        <strong>🎓 Learning Zones:</strong>
                        <ul class="small mb-2">
                            <li>Biology Laboratory</li>
                            <li>Ecology Center</li>
                            <li>Pet Care Training</li>
                        </ul>
                    </div>
                    
                    <div class="alert alert-info">
                        <small>
                            <i class="fas fa-coins"></i>
                            Earn Care Coins by helping others and completing educational activities in the metaverse!
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- World Entry Modal -->
<div class="modal fade" id="worldEntryModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="POST" action="">
                <?= getCSRFTokenField() ?>
                <input type="hidden" name="enter_world" value="1">
                <input type="hidden" name="world_id" id="entry_world_id">
                
                <div class="modal-header">
                    <h5 class="modal-title">Enter <span id="entry_world_name"></span></h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Bring Pets (Optional):</label>
                        <div class="pet-selection" style="max-height: 200px; overflow-y: auto;">
                            <?php foreach ($user_pets as $pet): ?>
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" name="pet_ids[]" value="<?= $pet['id'] ?>" 
                                           class="custom-control-input" id="pet_<?= $pet['id'] ?>">
                                    <label class="custom-control-label" for="pet_<?= $pet['id'] ?>">
                                        <img src="<?= htmlspecialchars($pet['image_url']) ?>" 
                                             alt="<?= htmlspecialchars($pet['name']) ?>" 
                                             style="width: 30px; height: 30px; border-radius: 50%; margin-right: 8px;">
                                        <?= htmlspecialchars($pet['name']) ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if (empty($user_pets)): ?>
                            <p class="text-muted">You don't have any pets yet. <a href="upload.php">Upload some pets</a> to bring them to the metaverse!</p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="alert alert-info">
                        <small>
                            <i class="fas fa-info-circle"></i>
                            Your pets will appear as 3D avatars that you can interact with and customize in the virtual world.
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-rocket"></i> Enter World
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Include Three.js and metaverse scripts -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
<script type="module" src="metaverse/environments/PetWorld3D.js"></script>
<script type="module" src="metaverse/environments/WeatherSystem.js"></script>

<script>
let petWorld3D = null;
let currentWorldId = null;

function showWorldEntry(worldId, worldName) {
    document.getElementById('entry_world_id').value = worldId;
    document.getElementById('entry_world_name').textContent = worldName;
    $('#worldEntryModal').modal('show');
}

// Initialize 3D world when page loads
document.addEventListener('DOMContentLoaded', async function() {
    const canvas = document.getElementById('metaverse-canvas');
    
    try {
        // Import and initialize 3D world
        const { PetWorld3D } = await import('./metaverse/environments/PetWorld3D.js');
        
        // Initialize with default park world
        petWorld3D = new PetWorld3D(canvas, {
            type: 'park',
            showHelpers: false,
            autoWeatherChange: true
        });
        
        // Hide loading overlay
        setTimeout(() => {
            document.getElementById('loading-overlay').style.display = 'none';
        }, 2000);
        
        // Add educational module event listener
        canvas.addEventListener('openEducationalModule', function(event) {
            const moduleType = event.detail.moduleType;
            alert(`Opening educational module: ${moduleType}\nThis would redirect to the learning interface!`);
        });
        
        console.log('Metaverse initialized successfully');
        
    } catch (error) {
        console.error('Failed to initialize metaverse:', error);
        
        // Show error message
        document.getElementById('loading-overlay').innerHTML = `
            <div class="text-center text-white">
                <i class="fas fa-exclamation-triangle fa-3x mb-3"></i>
                <h5>3D World Unavailable</h5>
                <p>The 3D metaverse requires a modern browser with WebGL support.</p>
                <small>You can still enjoy other features of Money Paws!</small>
            </div>
        `;
    }
});

// World controls
document.getElementById('fullscreen-btn').addEventListener('click', function() {
    const container = document.getElementById('metaverse-container');
    if (container.requestFullscreen) {
        container.requestFullscreen();
    }
});

document.getElementById('weather-btn').addEventListener('click', function() {
    if (petWorld3D) {
        const weatherTypes = ['sunny', 'partly_cloudy', 'rainy', 'snowy', 'foggy'];
        const randomWeather = weatherTypes[Math.floor(Math.random() * weatherTypes.length)];
        petWorld3D.setWeather(randomWeather);
        this.innerHTML = `<i class="fas fa-cloud-sun"></i> Weather: ${randomWeather.replace('_', ' ')}`;
    }
});

document.getElementById('time-btn').addEventListener('click', function() {
    if (petWorld3D) {
        const randomHour = Math.floor(Math.random() * 24);
        petWorld3D.setTimeOfDay(randomHour);
        this.innerHTML = `<i class="fas fa-clock"></i> Time: ${randomHour.toString().padStart(2, '0')}:00`;
    }
});

// Form submission handler
document.addEventListener('submit', function(e) {
    if (e.target.querySelector('input[name="enter_world"]')) {
        // Hide entry required overlay
        document.getElementById('entry-required').style.display = 'none';
        
        // Start the 3D world
        if (petWorld3D) {
            petWorld3D.start();
        }
    }
});

// Simulate online user count
function updateOnlineCount() {
    document.getElementById('online-count').textContent = Math.floor(Math.random() * 50) + 10;
}

updateOnlineCount();
setInterval(updateOnlineCount, 30000);
</script>

<style>
.world-controls {
    display: flex;
    gap: 8px;
}

.world-controls .btn {
    font-size: 0.75rem;
}

#metaverse-container {
    position: relative;
    border-radius: 8px;
    overflow: hidden;
}

.pet-selection {
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 10px;
}

.custom-control-label img {
    object-fit: cover;
}

@media (max-width: 768px) {
    .world-controls {
        flex-direction: column;
        gap: 4px;
    }
    
    .world-controls .btn {
        font-size: 0.7rem;
        padding: 0.25rem 0.5rem;
    }
}
</style>

<?php include 'includes/footer.php'; ?>