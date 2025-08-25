<?php
require_once 'includes/functions.php';
require_once 'includes/pet_care.php';

requireLogin();

$user_id = $_SESSION['user_id'];
$user_pets = getUserPets($user_id);

$error = $_GET['error'] ?? '';
$success = $_GET['success'] ?? '';

$pageTitle = 'Sell Your Pet';
require_once 'includes/html_head.php';
?>
<?php require_once 'includes/header.php'; ?>

    <main>
        <div class="container">
                        <div class="hero py-2">
                <h1>💰 Sell Your Pet</h1>
                <p>List your pets for other users to adopt for a fee.</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <div class="card">
                <h2>Your Pets</h2>
                <?php if (empty($user_pets)): ?>
                    <p>You don't have any pets to sell. <a href="upload.php">Upload a pet</a> or <a href="adoption.php">adopt one</a>!</p>
                <?php else: ?>
                    <div class="pet-grid">
                        <?php foreach ($user_pets as $pet): ?>
                            <div class="pet-card">
                                <img src="uploads/<?php echo htmlspecialchars($pet['filename']); ?>" alt="<?php echo htmlspecialchars($pet['original_name']); ?>">
                                <div class="pet-info">
                                    <h3><?php echo htmlspecialchars($pet['original_name']); ?></h3>
                                    <?php if ($pet['is_for_sale']): ?>
                                        <div class="for-sale-badge">
                                            For Sale: $<?php echo number_format($pet['sale_price_usd'], 2); ?>
                                        </div>
                                                                                <form action="api/sell-pet.php" method="POST" class="sale-form">
                                            <?php echo getCSRFTokenField(); ?>
                                            <input type="hidden" name="pet_id" value="<?php echo $pet['id']; ?>">
                                            <input type="hidden" name="action" value="unlist">
                                                                                        <button type="submit" class="btn btn-secondary btn-block">Remove from Sale</button>
                                        </form>
                                    <?php else: ?>
                                                                                <form action="api/sell-pet.php" method="POST" class="sale-form">
                                            <?php echo getCSRFTokenField(); ?>
                                            <input type="hidden" name="pet_id" value="<?php echo $pet['id']; ?>">
                                            <input type="hidden" name="action" value="sell">
                                            <div class="form-group">
                                                <label for="price_<?php echo $pet['id']; ?>">Sale Price (USD)</label>
                                                <input type="number" name="price" id="price_<?php echo $pet['id']; ?>" step="0.01" min="0.01" required class="form-control">
                                            </div>
                                                                                        <button type="submit" class="btn btn-primary btn-block">List for Sale</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

<?php require_once 'includes/footer.php'; ?>
<?php require_once 'includes/scripts.php'; ?>
