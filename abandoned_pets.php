<?php
/**
 * Money Paws - Abandoned Pets Page
 * Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>
 */
require_once 'includes/functions.php';

// Pagination
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1;
$page = max(1, $page);
$limit = 12;
$offset = ($page - 1) * $limit;

// Get abandoned pets with pagination
$pets = getAbandonedPets($limit, $offset);

// Get total count for pagination
if ($pdo) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM pet_stats WHERE last_cared_for < DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $stmt->execute();
    $totalPets = $stmt->fetchColumn();
} else {
    $totalPets = 0; // No database connection
}
$totalPages = ceil($totalPets / $limit);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Abandoned Pets - Money Paws</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <main>
        <div class="container">
                        <div class="hero py-2">
                <h1>Abandoned Pets</h1>
                <p>These pets haven't been cared for in over 30 days. Give them a new home!</p>
            </div>

            <div class="card">
                <?php if (empty($pets)): ?>
                                        <div class="text-center py-4">
                        <h3>No Abandoned Pets Found!</h3>
                        <p>All pets are currently being cared for by their owners.</p>
                    </div>
                <?php else: ?>
                    <div class="gallery-grid">
                        <?php foreach ($pets as $pet): ?>
                            <div class="pet-card">
                                <img src="<?php echo UPLOAD_DIR . htmlspecialchars($pet['filename']); ?>" 
                                     alt="<?php echo htmlspecialchars($pet['original_name']); ?>" 
                                     class="pet-image">
                                <div class="pet-info">
                                    <h3><?php echo htmlspecialchars($pet['original_name']); ?></h3>
                                    <p><strong>Owner:</strong> <?php echo htmlspecialchars($pet['owner_name']); ?></p>
                                    <p><em>Last cared for: <?php echo date('M j, Y', strtotime($pet['last_cared_for'])); ?></em></p>
                                                                        <a href="adoption.php?pet_id=<?php echo $pet['id']; ?>" class="btn btn-primary mt-1">Adopt Me</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                                                <div class="text-center mt-3">
                            <div class="pagination">
                                <?php if ($page > 1): ?>
                                                                        <a href="?page=<?php echo htmlspecialchars($page - 1); ?>" class="btn btn-secondary">&larr; Previous</a>
                                <?php endif; ?>
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                                                        <a href="?page=<?php echo htmlspecialchars($i); ?>" class="btn <?php echo ($i == $page) ? 'btn-primary' : 'btn-secondary'; ?>"><?php echo htmlspecialchars($i); ?></a>
                                <?php endfor; ?>
                                <?php if ($page < $totalPages): ?>
                                                                        <a href="?page=<?php echo htmlspecialchars($page + 1); ?>" class="btn btn-secondary">Next &rarr;</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <?php include 'includes/footer.php'; ?>
</body>
</html>
