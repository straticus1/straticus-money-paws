<?php
/**
 * Money Paws - Cryptocurrency-Powered Pet Platform
 * Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>
 */
require_once 'includes/functions.php';

// Pagination
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1;
$page = max(1, $page);
$limit = 12;
$offset = ($page - 1) * $limit;

// Get pets with pagination
$pets = getAllPets($limit, $offset);

// Get total count for pagination
if ($pdo) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM pets WHERE is_public = 1");
    $stmt->execute();
    $totalPets = $stmt->fetchColumn();
} else {
    $totalPets = 0; // No database connection, show 0 pets
}
$totalPages = ceil($totalPets / $limit);

$pageTitle = 'Pet Gallery';
require_once 'includes/html_head.php';
?>
<?php
require_once 'includes/header.php';
?>
<main>
        <div class="container">
            <div class="hero">
                <h1>AI Pet Gallery</h1>
                <p>Discover amazing AI-generated pets from our community</p>
                <?php if (!isLoggedIn()): ?>
                    <a href="register.php" class="btn btn-primary">Join & Upload Your Pet</a>
                <?php else: ?>
                    <a href="upload.php" class="btn btn-primary">Upload Your Pet</a>
                <?php endif; ?>
            </div>

            <div class="card">
                <div class="gallery-header">
                    <h2>Featured Pets (<?php echo $totalPets; ?> total)</h2>
                    <div>
                        <select id="sortBy" class="form-control form-control-auto">
                            <option value="newest">Newest First</option>
                            <option value="oldest">Oldest First</option>
                            <option value="popular">Most Popular</option>
                        </select>
                    </div>
                </div>

                <?php if (empty($pets)): ?>
                    <div class="empty-gallery-message">
                        <h3>No pets uploaded yet!</h3>
                        <p>Be the first to share your AI pet creation.</p>
                        <?php if (isLoggedIn()): ?>
                            <a href="upload.php" class="btn btn-primary">Upload First Pet</a>
                        <?php else: ?>
                            <a href="register.php" class="btn btn-primary">Sign Up to Upload</a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="gallery-grid">
                        <?php foreach ($pets as $pet): ?>
                            <div class="pet-card" data-pet-id="<?php echo $pet['id']; ?>">
                                <img src="<?php echo UPLOAD_DIR . htmlspecialchars($pet['filename']); ?>" 
                                     alt="<?php echo htmlspecialchars($pet['original_name']); ?>" 
                                     class="pet-image pet-modal-trigger"
                                     data-pet-id="<?php echo $pet['id']; ?>">
                                
                                <div class="pet-info">
                                    <h3><?php echo htmlspecialchars($pet['original_name']); ?></h3>
                                    <p><strong>By:</strong> <?php echo htmlspecialchars($pet['owner_name']); ?></p>
                                    
                                    <?php if (!empty($pet['description'])): ?>
                                        <p><?php echo htmlspecialchars(substr($pet['description'], 0, 100)); ?><?php echo strlen($pet['description']) > 100 ? '...' : ''; ?></p>
                                    <?php endif; ?>
                                    
                                    <?php 
                                    require_once 'includes/pet_care.php';
                                    $petStats = getPetStats($pet['id']);
                                    $hungerStatus = getPetHungerStatus($petStats['hunger_level']);
                                    $happinessStatus = getPetHappinessStatus($petStats['happiness_level']);
                                    ?>
                                    
                                    <!-- Pet Status -->
                                    <div class="pet-status-container">
                                        <div class="pet-status-box">
                                            <div><?php echo $hungerStatus['emoji']; ?></div>
                                            <div class="status-label"><?php echo $petStats['hunger_level']; ?>% Full</div>
                                        </div>
                                        <div class="pet-status-box">
                                            <div><?php echo $happinessStatus['emoji']; ?></div>
                                            <div class="status-label"><?php echo $petStats['happiness_level']; ?>% Happy</div>
                                        </div>
                                    </div>
                                    
                                    <!-- Pet Care Actions -->
                                    <?php if (isLoggedIn()): ?>
                                        <div class="pet-actions">
                                            <?php if (needsFood($petStats)): ?>
                                                <button data-pet-id="<?php echo $pet['id']; ?>" class="btn btn-success btn-sm feed-pet-btn">
                                                    🍖 Feed
                                                </button>
                                            <?php endif; ?>
                                            
                                            <?php if (needsTreat($petStats)): ?>
                                                <button data-pet-id="<?php echo $pet['id']; ?>" class="btn btn-primary btn-sm treat-pet-btn">
                                                    🥓 Treat
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="pet-meta">
                                        <div>
                                            <span class="meta-text">
                                                👁️ <?php echo $pet['views_count']; ?> views
                                            </span>
                                        </div>
                                        <div>
                                            <?php if (isLoggedIn()): ?>
                                                <button data-pet-id="<?php echo $pet['id']; ?>" class="btn btn-secondary btn-sm toggle-like-btn"> 
                                                    ❤️ <span id="likes-<?php echo $pet['id']; ?>"><?php echo $pet['likes_count']; ?></span>
                                                </button>
                                            <?php else: ?>
                                                <span class="meta-text">
                                                    ❤️ <?php echo $pet['likes_count']; ?> likes
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="upload-date">
                                        Uploaded <?php echo date('M j, Y', strtotime($pet['uploaded_at'])); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <div class="pagination-container">
                            <div class="pagination">
                                <?php if ($page > 1): ?>
                                    <a href="?page=<?php echo $page - 1; ?>" class="btn btn-secondary">← Previous</a>
                                <?php endif; ?>
                                
                                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                    <?php if ($i == $page): ?>
                                        <span class="btn btn-primary"><?php echo $i; ?></span>
                                    <?php else: ?>
                                        <a href="?page=<?php echo $i; ?>" class="btn btn-secondary"><?php echo $i; ?></a>
                                    <?php endif; ?>
                                <?php endfor; ?>
                                
                                <?php if ($page < $totalPages): ?>
                                    <a href="?page=<?php echo $page + 1; ?>" class="btn btn-secondary">Next →</a>
                                <?php endif; ?>
                            </div>
                            <p class="pagination-info">
                                Page <?php echo $page; ?> of <?php echo $totalPages; ?> 
                                (<?php echo $totalPets; ?> total pets)
                            </p>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Pet Modal -->
    <div id="petModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <div id="modalContent">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>

<?php require_once 'includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const galleryGrid = document.querySelector('.gallery-grid');
    const petModal = document.getElementById('petModal');
    const modalContent = document.getElementById('modalContent');
    const closeModalButton = petModal.querySelector('.close');

    function performFetch(url, body, successCallback, errorCallback) {
        fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                successCallback(data);
            } else {
                alert(data.message || 'An error occurred.');
            }
        })
        .catch(error => {
            console.error('Fetch Error:', error);
            if (errorCallback) errorCallback(error);
        });
    }

    function toggleLike(petId) {
        performFetch('api/toggle-like.php', `pet_id=${petId}`, data => {
            const galleryLikes = document.querySelector(`.toggle-like-btn[data-pet-id='${petId}'] span`);
            if (galleryLikes) galleryLikes.textContent = data.new_count;
            
            const modalLikes = document.getElementById('modalLikes');
            if (petModal.dataset.petId == petId && modalLikes) {
                modalLikes.textContent = data.new_count;
            }
        });
    }

    function feedPet(petId) {
        performFetch('api/feed-pet.php', `pet_id=${petId}`, data => {
            alert(data.message);
            location.reload();
        });
    }

    function giveTreat(petId) {
        performFetch('api/treat-pet.php', `pet_id=${petId}`, data => {
            alert(data.message);
            location.reload();
        });
    }

    function openPetModal(petId) {
        fetch(`api/increment-views.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `pet_id=${petId}`
        });

        fetch(`api/get-pet.php?id=${petId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const pet = data.pet;
                    petModal.classList.add('is-visible');
                    petModal.dataset.petId = pet.id;
                    
                    modalContent.innerHTML = `
                        <div class="modal-body-layout">
                            <img id="modalImage" src="${pet.file_path}" alt="${pet.original_name}" class="modal-pet-image">
                            <div>
                                <h2 id="modalTitle">${pet.original_name}</h2>
                                <p><strong>By:</strong> <span id="modalOwner">${pet.owner_name}</span></p>
                                <p id="modalDescription">${pet.description || 'No description available.'}</p>
                                <p><strong>Uploaded:</strong> <span id="modalDate">${new Date(pet.uploaded_at).toLocaleDateString()}</span></p>
                                <p>
                                    <span class="meta-text">👁️ <span id="modalViews">${pet.views_count}</span> views</span>
                                    <span class="meta-text modal-likes">❤️ <span id="modalLikes">${pet.likes_count}</span> likes</span>
                                </p>
                                <?php if (isLoggedIn()): ?>
                                <button data-pet-id="${pet.id}" class="btn btn-primary toggle-like-btn">Like</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    `;
                }
            })
            .catch(error => console.error('Error:', error));
    }

    function closePetModal() {
        petModal.classList.remove('is-visible');
        modalContent.innerHTML = '';
    }

    // Event Listeners
    document.getElementById('sortBy').addEventListener('change', function() {
        const currentUrl = new URL(window.location);
        currentUrl.searchParams.set('sort', this.value);
        currentUrl.searchParams.set('page', '1');
        window.location.href = currentUrl.toString();
    });

    if (galleryGrid) {
        galleryGrid.addEventListener('click', function(e) {
            const target = e.target;
            const petId = target.dataset.petId || target.closest('[data-pet-id]')?.dataset.petId;

            if (!petId) return;

            if (target.matches('.pet-modal-trigger')) {
                openPetModal(petId);
            }
            if (target.matches('.feed-pet-btn')) {
                feedPet(petId);
            }
            if (target.matches('.treat-pet-btn')) {
                giveTreat(petId);
            }
            if (target.matches('.toggle-like-btn')) {
                toggleLike(petId);
            }
        });
    }
    
    modalContent.addEventListener('click', function(e) {
        if (e.target.matches('.toggle-like-btn')) {
            const petId = e.target.dataset.petId;
            if (petId) toggleLike(petId);
        }
    });

    closeModalButton.addEventListener('click', closePetModal);

    window.addEventListener('click', function(event) {
        if (event.target === petModal) {
            closePetModal();
        }
    });
});
</script>

<?php require_once 'includes/scripts.php'; ?>
