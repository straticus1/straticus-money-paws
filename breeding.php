<?php
/**
 * Money Paws - Breeding Page
 * Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>
 */

require_once 'includes/functions.php';
require_once 'includes/security.php';

requireLogin();

$user_id = $_SESSION['user_id'];
$user_pets = getUserPets($user_id);

include 'includes/header.php';
?>

<div class="container mt-5">
    <h1 class="text-center mb-4">🧬 Advanced AI Pet Breeding</h1>
    <div id="breeding-alert-container"></div>
    
    <!-- Breeding Method Selection -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5>🔬 Breeding Enhancement Options</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="breeding-method" data-method="natural">
                                <div class="method-icon">🌿</div>
                                <h6>Natural Breeding</h6>
                                <p>Traditional breeding with genetic inheritance</p>
                                <div class="price">Free</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="breeding-method" data-method="ai_assisted">
                                <div class="method-icon">🤖</div>
                                <h6>AI-Assisted</h6>
                                <p>Enhanced success rates and trait prediction</p>
                                <div class="price">$2.50</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="breeding-method" data-method="genetic_enhancement">
                                <div class="method-icon">⚡</div>
                                <h6>Genetic Enhancement</h6>
                                <p>Maximum success with rare trait chances</p>
                                <div class="price">$5.00</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5>🐾 Select Parents</h5>
                </div>
                <div class="card-body">
                    <form id="breeding-form">
                        <?php echo getCSRFTokenField(); ?>
                        <input type="hidden" id="breeding-method" name="breeding_method" value="natural">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="mother-select">Select Mother 🤱</label>
                                    <select class="form-control" id="mother-select" name="mother_id">
                                        <option value="">-- Select a Pet --</option>
                                        <?php foreach ($user_pets as $pet): ?>
                                            <option value="<?php echo htmlspecialchars($pet['id']); ?>" 
                                                    data-species="<?php echo htmlspecialchars($pet['species'] ?? 'dog'); ?>">
                                                <?php echo htmlspecialchars($pet['original_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div id="mother-preview" class="pet-preview"></div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="father-select">Select Father 👨</label>
                                    <select class="form-control" id="father-select" name="father_id">
                                        <option value="">-- Select a Pet --</option>
                                        <?php foreach ($user_pets as $pet): ?>
                                            <option value="<?php echo htmlspecialchars($pet['id']); ?>"
                                                    data-species="<?php echo htmlspecialchars($pet['species'] ?? 'dog'); ?>">
                                                <?php echo htmlspecialchars($pet['original_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div id="father-preview" class="pet-preview"></div>
                            </div>
                        </div>
                        
                        <!-- Compatibility Analysis -->
                        <div id="compatibility-analysis" class="mt-4" style="display: none;">
                            <div class="alert alert-info">
                                <h6>🧬 Genetic Compatibility Analysis</h6>
                                <div id="compatibility-details"></div>
                            </div>
                        </div>
                        
                        <div class="form-group mt-3">
                            <label for="new-pet-name">Offspring's Name 👶</label>
                            <input type="text" class="form-control" id="new-pet-name" name="name" 
                                   placeholder="Enter a name for the new pet" required>
                        </div>
                        
                        <div id="breeding-cost" class="alert alert-warning mt-3" style="display: none;">
                            <strong>Breeding Cost:</strong> <span id="cost-amount">$0.00</span>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-block mt-4" id="breed-button">
                            🧬 Create New Pet
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5>📊 Breeding Statistics</h5>
                </div>
                <div class="card-body">
                    <div class="stat-item">
                        <span class="stat-label">Success Rate:</span>
                        <span id="success-rate" class="stat-value">--</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Genetic Diversity:</span>
                        <span id="genetic-diversity" class="stat-value">--</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Rare Trait Chance:</span>
                        <span id="rare-trait-chance" class="stat-value">--</span>
                    </div>
                </div>
            </div>
            
            <div class="card mt-3">
                <div class="card-header">
                    <h5>🏆 Recent Offspring</h5>
                </div>
                <div class="card-body">
                    <div id="recent-offspring">Loading...</div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.breeding-method {
    text-align: center;
    padding: 20px;
    border: 2px solid #e9ecef;
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.3s ease;
    margin-bottom: 10px;
}

.breeding-method:hover {
    border-color: #007bff;
    background-color: #f8f9fa;
}

.breeding-method.selected {
    border-color: #007bff;
    background-color: #e3f2fd;
}

.method-icon {
    font-size: 2rem;
    margin-bottom: 10px;
}

.price {
    font-weight: bold;
    color: #28a745;
    font-size: 1.1rem;
}

.pet-preview {
    margin-top: 10px;
    padding: 10px;
    border: 1px solid #dee2e6;
    border-radius: 5px;
    min-height: 100px;
    background-color: #f8f9fa;
}

.stat-item {
    display: flex;
    justify-content: space-between;
    margin-bottom: 10px;
    padding: 5px 0;
    border-bottom: 1px solid #eee;
}

.stat-label {
    font-weight: 500;
}

.stat-value {
    font-weight: bold;
    color: #007bff;
}
</style>

<?php
include 'includes/footer.php';
?>
<script src="assets/js/breeding.js"></script>
