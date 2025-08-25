<?php
/**
 * Money Paws - Cryptocurrency-Powered Pet Platform
 * Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>
 */
require_once 'includes/functions.php';

// Require login to access upload page
requireLogin();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRFToken();
    $description = sanitizeInput($_POST['description'] ?? '');
    
    if (!isset($_FILES['pet_image']) || $_FILES['pet_image']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Please select a valid image file.';
    } else {
        $file = $_FILES['pet_image'];
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $maxSize = 5 * 1024 * 1024; // 5MB

        // Basic validation
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $imageInfo = getimagesize($file['tmp_name']);

        if (!$imageInfo || !in_array($imageInfo['mime'], $allowedTypes)) {
            $error = 'The uploaded file is not a valid image.';
        } elseif (!in_array($extension, $allowedExtensions)) {
            $error = 'Please upload a valid image file (JPEG, PNG, GIF, or WebP).';
        } elseif ($file['size'] > $maxSize) {
            $error = 'File size must be less than 5MB.';
        } else {
            // Create upload directory if it doesn't exist
            if (!file_exists(UPLOAD_DIR)) {
                mkdir(UPLOAD_DIR, 0755, true);
            }
            
            // Generate unique filename
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = uniqid() . '_' . time() . '.' . $extension;
            $filepath = UPLOAD_DIR . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                // Save to database
                if (uploadPet($_SESSION['user_id'], $filename, $file['name'], $description)) {
                    $success = 'Your pet has been uploaded successfully!';
                } else {
                    $error = 'Failed to save pet information to database.';
                    // Clean up uploaded file
                    unlink($filepath);
                }
            } else {
                $error = 'Failed to upload file. Please try again.';
            }
        }
    }
}

$currentUser = getUserById($_SESSION['user_id']);
$userPets = getUserPets($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Pet - Money Paws</title>
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
                <li><a href="profile.php">Profile</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </nav>
    </header>

    <main>
        <div class="container">
            <div class="hero hero-padding">
                <h1>Upload Your AI Pet</h1>
                <p>Share your amazing AI-generated pet with the Money Paws community</p>
            </div>

            <div class="form-container">
                <div class="card">
                    <?php if ($error): ?>
                        <div class="alert alert-error"><?php echo $error; ?></div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <?php echo $success; ?>
                            <br><br>
                            <a href="gallery.php" class="btn btn-primary">View in Gallery</a>
                            <a href="upload.php" class="btn btn-secondary">Upload Another</a>
                        </div>
                    <?php endif; ?>

                                        <form method="POST" enctype="multipart/form-data" id="uploadForm">
                        <?php echo getCSRFTokenField(); ?>
                        <div class="upload-area" id="uploadArea">
                            <div id="uploadPrompt">
                                <h3>📸 Drop your pet image here</h3>
                                <p>or click to browse files</p>
                                                                <input type="file" id="pet_image" name="pet_image" accept="image/*" class="d-none" required>
                                                                <button type="button" id="chooseFileBtn" class="btn btn-primary">
                                    Choose File
                                </button>
                                <p class="mt-3 text-muted small">
                                    Supported formats: JPEG, PNG, GIF, WebP<br>
                                    Maximum file size: 5MB
                                </p>
                            </div>
                            <div id="imagePreview" class="d-none">
                                <img id="previewImg">
                                <p id="fileName"></p>
                                                                <button type="button" id="clearImageBtn" class="btn btn-secondary mt-3">
                                    Choose Different Image
                                </button>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="description">Description (Optional)</label>
                            <textarea id="description" name="description" class="form-control" rows="4" 
                                      placeholder="Tell us about your AI pet creation..."><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                        </div>

                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="is_public" value="1" checked>
                                Make this pet visible in the public gallery
                            </label>
                        </div>

                        <button type="submit" class="btn btn-primary btn-block" id="submitBtn">
                            🚀 Upload Pet
                        </button>
                    </form>
                </div>

                <?php if (!empty($userPets)): ?>
                    <div class="card">
                        <h2>Your Recent Uploads</h2>
                        <div class="gallery-grid">
                            <?php foreach (array_slice($userPets, 0, 6) as $pet): ?>
                                <div class="pet-card">
                                    <img src="<?php echo UPLOAD_DIR . htmlspecialchars($pet['filename']); ?>" 
                                         alt="<?php echo htmlspecialchars($pet['original_name']); ?>" 
                                         class="pet-image">
                                    <div class="pet-info">
                                        <h4><?php echo htmlspecialchars($pet['original_name']); ?></h4>
                                        <p class="pet-info-meta">
                                            Uploaded <?php echo date('M j, Y', strtotime($pet['uploaded_at'])); ?>
                                        </p>
                                        <div class="pet-info-stats">
                                            <span>👁️ <?php echo $pet['views_count']; ?> views</span>
                                            <span>❤️ <?php echo $pet['likes_count']; ?> likes</span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if (count($userPets) > 6): ?>
                            <div class="text-center mt-3">
                                <a href="profile.php" class="btn btn-secondary">View All Your Pets</a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; 2024 Money Paws. All rights reserved.</p>
        </div>
    </footer>

    <script>
        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('pet_image');
        const uploadPrompt = document.getElementById('uploadPrompt');
        const imagePreview = document.getElementById('imagePreview');
        const previewImg = document.getElementById('previewImg');
        const fileName = document.getElementById('fileName');
        const submitBtn = document.getElementById('submitBtn');
        const chooseFileBtn = document.getElementById('chooseFileBtn');
        const clearImageBtn = document.getElementById('clearImageBtn');

        chooseFileBtn.addEventListener('click', () => fileInput.click());
        clearImageBtn.addEventListener('click', clearImage);

        // Drag and drop functionality
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        });

        uploadArea.addEventListener('dragleave', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
        });

        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                handleFileSelect(files[0]);
            }
        });

        fileInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                handleFileSelect(e.target.files[0]);
            }
        });

        function handleFileSelect(file) {
            // Validate file type
            const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!allowedTypes.includes(file.type)) {
                alert('Please select a valid image file (JPEG, PNG, GIF, or WebP).');
                return;
            }

            // Validate file size (5MB)
            if (file.size > 5 * 1024 * 1024) {
                alert('File size must be less than 5MB.');
                return;
            }

            // Show preview
            const reader = new FileReader();
            reader.onload = (e) => {
                previewImg.src = e.target.result;
                fileName.textContent = file.name;
                uploadPrompt.classList.add('d-none');
                imagePreview.classList.remove('d-none');
            };
            reader.readAsDataURL(file);
        }

        function clearImage() {
            fileInput.value = '';
            uploadPrompt.classList.remove('d-none');
            imagePreview.classList.add('d-none');
            previewImg.src = '';
            fileName.textContent = '';
        }

        // Form submission with loading state
        document.getElementById('uploadForm').addEventListener('submit', function() {
            submitBtn.innerHTML = '<div class="spinner-inline"></div> Uploading...';
            submitBtn.disabled = true;
        });

        // Auto-resize textarea
        document.getElementById('description').addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = this.scrollHeight + 'px';
        });
    </script>
</body>
</html>
