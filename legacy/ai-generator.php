ok<?php
/**
 * Money Paws - Cryptocurrency-Powered Pet Platform
 * Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>
 */
require_once 'includes/functions.php';
require_once 'includes/crypto.php';
require_once 'includes/ai_generation.php';

requireLogin();

$currentUser = getUserById($_SESSION['user_id']);
$error = '';
$success = '';

// Get user crypto balances
$balances = [];
foreach (SUPPORTED_CRYPTOS as $crypto => $name) {
    $balances[$crypto] = getUserCryptoBalance($_SESSION['user_id'], $crypto);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_pet'])) {
    requireCSRFToken();
    $description = sanitizeInput($_POST['description']);
    $animalType = sanitizeInput($_POST['animal_type']);
    $style = sanitizeInput($_POST['style']);
    $cryptoType = sanitizeInput($_POST['crypto_type']);
    
    if (empty($description)) {
        $error = 'Please provide a description for your AI pet.';
    } elseif (!array_key_exists($cryptoType, SUPPORTED_CRYPTOS)) {
        $error = 'Invalid cryptocurrency selected.';
    } else {
        $costUSD = AI_PET_GENERATION_PRICE;
        $cryptoAmount = convertUSDToCrypto($costUSD, $cryptoType);
        $userBalance = getUserCryptoBalance($_SESSION['user_id'], $cryptoType);
        
        if ($cryptoAmount === null) {
            $error = 'Unable to get current crypto prices. Please try again.';
        } elseif ($userBalance < $cryptoAmount) {
            $error = 'Insufficient ' . SUPPORTED_CRYPTOS[$cryptoType] . ' balance. You need ' . number_format($cryptoAmount, 8) . ' ' . $cryptoType;
        } else {
            // Deduct cost and create generation request
            if ($pdo) {
                updateUserBalance($_SESSION['user_id'], $cryptoType, $cryptoAmount, 'subtract');
                
                $stmt = $pdo->prepare("
                    INSERT INTO ai_generations (user_id, description, animal_type, style, cost_usd, crypto_type, crypto_amount, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
                ");
                $stmt->execute([$_SESSION['user_id'], $description, $animalType, $style, $costUSD, $cryptoType, $cryptoAmount]);
                
                $generationId = $pdo->lastInsertId();
                
                // Create transaction record
                createCryptoTransaction($_SESSION['user_id'], 'ai_generation', $cryptoType, $cryptoAmount, $costUSD);
                
                // Process AI generation immediately
                if (defined('DEVELOPER_MODE') && DEVELOPER_MODE) {
                    $result = processAIGenerationDemo($generationId);
                } else {
                    $result = processAIGeneration($generationId);
                }
                
                if ($result['success']) {
                    $success = 'Your AI pet has been generated successfully! <a href="' . htmlspecialchars($result['url']) . '" target="_blank">View your AI pet</a>';
                } else {
                    $success = 'Your AI pet generation request has been submitted! Processing may take a few minutes.';
                }
            } else {
                // Demo mode without database
                $success = 'Demo mode: Your AI pet would be generated here. Please set up database connection for full functionality.';
            }
        }
    }
}

// Get user's recent generations
if ($pdo) {
    $stmt = $pdo->prepare("SELECT * FROM ai_generations WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
    $stmt->execute([$_SESSION['user_id']]);
    $recentGenerations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $recentGenerations = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Pet Generator - Money Paws</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <header>
        <nav class="container">
            <a href="index.php" class="logo">🐾 Money Paws</a>
            <ul class="nav-links">
                <li><a href="index.php">Home</a></li>
                <li><a href="gallery.php">Gallery</a></li>
                <li><a href="upload.php">Upload</a></li>
                <li><a href="ai-generator.php">AI Generator</a></li>
                <li><a href="game.php">Games</a></li>
                <li><a href="profile.php">Profile</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </nav>
    </header>

    <main>
        <div class="container">
            <div class="hero hero-padding">
                <h1 class="text-center my-4 text-dark-custom">AI Pet Generator</h1>
                <p>Create unique AI pets from your imagination!</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <div class="form-container">
                <div class="card">
                    <h2>Generate Your AI Pet</h2>
                    <p><strong>Cost:</strong> $<?php echo AI_PET_GENERATION_PRICE; ?> (paid in crypto)</p>
                    
                                        <form method="POST" id="generationForm">
                        <?php echo getCSRFTokenField(); ?>
                        <div class="form-group">
                            <label for="description">Describe Your Dream Pet</label>
                            <textarea id="description" name="description" class="form-control" rows="4" 
                                      placeholder="A fluffy golden retriever puppy with bright blue eyes, sitting in a field of sunflowers, wearing a red bandana..." 
                                      required><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                            <small class="text-muted">Be as detailed as possible for best results!</small>
                        </div>

                        <div class="form-group">
                            <label>Animal Type</label>
                            <div class="animal-selector">
                                <div class="animal-option selected" data-animal="dog">
                                    <div class="animal-icon">🐕</div>
                                    <p>Dog</p>
                                </div>
                                <div class="animal-option" data-animal="cat">
                                    <div class="animal-icon">🐱</div>
                                    <p>Cat</p>
                                </div>
                                <div class="animal-option" data-animal="rabbit">
                                    <div class="animal-icon">🐰</div>
                                    <p>Rabbit</p>
                                </div>
                                <div class="animal-option" data-animal="fox">
                                    <div class="animal-icon">🦊</div>
                                    <p>Fox</p>
                                </div>
                                <div class="animal-option" data-animal="bear">
                                    <div class="animal-icon">🐻</div>
                                    <p>Bear</p>
                                </div>
                                <div class="animal-option" data-animal="other">
                                    <div class="animal-icon">🐾</div>
                                    <p>Other</p>
                                </div>
                            </div>
                            <input type="hidden" name="animal_type" id="selectedAnimal" value="dog">
                        </div>

                        <div class="form-group">
                            <label for="style">Art Style</label>
                            <select id="style" name="style" class="form-control" required>
                                <option value="realistic">Realistic</option>
                                <option value="cartoon">Cartoon</option>
                                <option value="anime">Anime</option>
                                <option value="watercolor">Watercolor</option>
                                <option value="oil_painting">Oil Painting</option>
                                <option value="digital_art">Digital Art</option>
                                <option value="pixel_art">Pixel Art</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Payment Method</label>
                            <div class="crypto-selector">
                                <?php foreach (SUPPORTED_CRYPTOS as $crypto => $name): ?>
                                    <div class="crypto-option" data-crypto="<?php echo $crypto; ?>">
                                        <h4><?php echo $crypto; ?></h4>
                                        <p><?php echo $name; ?></p>
                                        <small>Balance: <?php echo number_format($balances[$crypto], 8); ?></small>
                                        <div class="crypto-price">
                                            <?php 
                                            $cryptoAmount = convertUSDToCrypto(AI_PET_GENERATION_PRICE, $crypto);
                                            echo $cryptoAmount ? number_format($cryptoAmount, 8) . ' ' . $crypto : 'Price loading...';
                                            ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <input type="hidden" name="crypto_type" id="selectedCrypto" value="">
                        </div>

                        <button type="submit" name="generate_pet" class="btn btn-primary btn-block btn-large" disabled id="generateBtn">
                            🎨 Generate AI Pet
                        </button>
                    </form>
                </div>
            </div>

            <?php if (!empty($recentGenerations)): ?>
                <div class="card">
                    <h2>Your Recent Generations</h2>
                    <?php foreach ($recentGenerations as $generation): ?>
                        <div class="ai-generation-card">
                            <div class="ai-generation-card-content">
                                <h4><?php echo htmlspecialchars($generation['description']); ?></h4>
                                <p><strong>Animal:</strong> <?php echo ucfirst($generation['animal_type']); ?> | 
                                   <strong>Style:</strong> <?php echo ucfirst(str_replace('_', ' ', $generation['style'])); ?></p>
                                <p><strong>Cost:</strong> <?php echo number_format($generation['crypto_amount'], 8); ?> <?php echo $generation['crypto_type']; ?> 
                                   ($<?php echo $generation['cost_usd']; ?>)</p>
                                <small class="text-muted">
                                    <?php echo date('M j, Y g:i A', strtotime($generation['created_at'])); ?>
                                </small>
                            </div>
                            <div>
                                <span class="status-badge status-<?php echo $generation['status']; ?>">
                                    <?php echo ucfirst($generation['status']); ?>
                                </span>
                                <?php if ($generation['status'] === 'completed' && $generation['generated_image_url']): ?>
                                    <div class="generation-actions">
                                        <a href="<?php echo htmlspecialchars($generation['generated_image_url']); ?>" 
                                           class="btn btn-primary" target="_blank">View Image</a>
                                        <a href="save-to-gallery.php?generation_id=<?php echo $generation['id']; ?>" 
                                           class="btn btn-secondary">Save to Gallery</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="card">
                <h2>💡 Tips for Better AI Pets</h2>
                <ul class="tips-list">
                    <li><strong>Be Specific:</strong> Include details about color, size, pose, and setting</li>
                    <li><strong>Mention Emotions:</strong> "happy", "playful", "sleepy" help create personality</li>
                    <li><strong>Add Environment:</strong> Describe the background or setting</li>
                    <li><strong>Include Accessories:</strong> Collars, toys, or clothing add character</li>
                    <li><strong>Paw Preference:</strong> Dogs and cats work best, but any animal with paws is welcome!</li>
                </ul>
            </div>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; 2024 Money Paws. All rights reserved.</p>
        </div>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            let selectedCryptoType = '';

            // Animal selection
            document.querySelectorAll('.animal-option').forEach(option => {
                option.addEventListener('click', function() {
                    document.querySelectorAll('.animal-option').forEach(o => o.classList.remove('selected'));
                    this.classList.add('selected');
                    document.getElementById('selectedAnimal').value = this.dataset.animal;
                });
            });

            // Crypto selection
            document.querySelectorAll('.crypto-option').forEach(option => {
                option.addEventListener('click', function() {
                    document.querySelectorAll('.crypto-option').forEach(o => o.classList.remove('selected'));
                    this.classList.add('selected');
                    selectedCryptoType = this.dataset.crypto;
                    document.getElementById('selectedCrypto').value = selectedCryptoType;
                    document.getElementById('generateBtn').disabled = false;
                });
            });

            // Auto-resize textarea
            const descriptionTextarea = document.getElementById('description');
            if (descriptionTextarea) {
                descriptionTextarea.addEventListener('input', function() {
                    this.style.height = 'auto';
                    this.style.height = this.scrollHeight + 'px';
                });
            }

            // Form validation
            const generationForm = document.getElementById('generationForm');
            if (generationForm) {
                generationForm.addEventListener('submit', function(e) {
                    const description = document.getElementById('description').value.trim();
                    
                    if (description.length < 10) {
                        e.preventDefault();
                        alert('Please provide a more detailed description (at least 10 characters).');
                        return;
                    }
                    
                    if (!selectedCryptoType) {
                        e.preventDefault();
                        alert('Please select a payment method.');
                        return;
                    }
                    
                    const generateBtn = document.getElementById('generateBtn');
                    generateBtn.innerHTML = '<div class="spinner spinner-inline"></div> Generating...';
                    generateBtn.disabled = true;
                });
            }
        });
    </script>
</body>
</html>
