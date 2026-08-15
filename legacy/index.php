<?php
/**
 * Money Paws - Cryptocurrency-Powered Pet Platform
 * Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>
 */
require_once 'includes/functions.php';

$pageTitle = 'Home';
require_once 'includes/html_head.php';
require_once 'includes/header.php';
?>

<main>
        <section class="hero">
            <div class="container">
                <h1>Welcome to Money Paws</h1>
                <p>Share and discover amazing AI-generated pets from around the world</p>
                <?php if (!isLoggedIn()): ?>
                    <a href="register.php" class="btn btn-primary">Get Started</a>
                    <a href="gallery.php" class="btn btn-secondary">Browse Gallery</a>
                <?php else: ?>
                    <a href="upload.php" class="btn btn-primary">Upload Your Pet</a>
                    <a href="gallery.php" class="btn btn-secondary">View Gallery</a>
                <?php endif; ?>
            </div>
        </section>

        <div class="container">
            <div class="card">
                <h2>🎨 AI Pet Creation</h2>
                <p>Transform your beloved pets into stunning AI-generated artwork. Our platform uses cutting-edge artificial intelligence to create unique, beautiful representations of your furry friends.</p>
            </div>

            <div class="card">
                <h2>🌟 Community Gallery</h2>
                <p>Explore thousands of AI pets created by our community. Like, share, and discover incredible pet artwork from pet lovers around the world.</p>
            </div>

            <div class="card">
                <h2>💰 Monetize Your Creations</h2>
                <p>Turn your AI pet creations into potential income. Share your unique pet artwork and connect with other pet enthusiasts who appreciate your creativity.</p>
            </div>

            <?php if (isLoggedIn()): ?>
                <?php
                $currentUser = getUserById($_SESSION['user_id']);
                $userPets = getUserPets($_SESSION['user_id']);
                ?>
                <div class="card">
                    <h2>Welcome back, <?php echo htmlspecialchars($currentUser['name']); ?>! 👋</h2>
                    <p>You have uploaded <?php echo count($userPets); ?> pet<?php echo count($userPets) !== 1 ? 's' : ''; ?> to your gallery.</p>
                    <a href="upload.php" class="btn btn-primary">Upload New Pet</a>
                    <a href="profile.php" class="btn btn-secondary">View Your Profile</a>
                </div>
            <?php endif; ?>

            <div class="card">
                <h2>🚀 Getting Started</h2>
                <ol class="getting-started-list">
                    <li><strong>Create an Account:</strong> Sign up using your email or social media accounts</li>
                    <li><strong>Upload Your Pet:</strong> Share photos of your pets to create AI artwork</li>
                    <li><strong>Explore Gallery:</strong> Browse and interact with the community's creations</li>
                    <li><strong>Connect & Share:</strong> Build your following and showcase your pet's personality</li>
                </ol>
            </div>
        </div>
    </main>

<?php require_once 'includes/footer.php'; ?>

<script>
    // Animate cards on the homepage using CSS animations
    document.addEventListener('DOMContentLoaded', function() {
        const cards = document.querySelectorAll('.container > .card');
        cards.forEach((card, index) => {
            // Set initial state for animation
            card.style.opacity = '0';
            // Add animation class and delay
            card.classList.add('animate-in');
            card.style.animationDelay = `${index * 100}ms`;
        });
    });
</script>

<?php require_once 'includes/scripts.php'; ?>
