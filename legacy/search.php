<?php
require_once 'includes/functions.php';

$query = sanitizeInput($_GET['q'] ?? '');
$results = [];
if (!empty($query)) {
    $results = searchSite($query);
}

include 'includes/header.php';
?>

<main class="container">
    <h1>Search Results for "<?php echo htmlspecialchars($query); ?>"</h1>

    <div class="search-results-container">
        <div class="search-results-section">
            <h2>Users</h2>
            <?php if (!empty($results['users'])): ?>
                <ul>
                    <?php foreach ($results['users'] as $user): ?>
                        <li><a href="/profile.php?id=<?php echo htmlspecialchars($user['id']); ?>"><?php echo htmlspecialchars($user['username']); ?></a></li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p>No users found.</p>
            <?php endif; ?>
        </div>

        <div class="search-results-section">
            <h2>Pets</h2>
            <?php if (!empty($results['pets'])): ?>
                <ul>
                    <?php foreach ($results['pets'] as $pet): ?>
                        <li><a href="/profile.php?id=<?php echo htmlspecialchars($pet['user_id']); ?>#pet-<?php echo htmlspecialchars($pet['id']); ?>"><?php echo htmlspecialchars($pet['name']); ?></a> (owned by <?php echo htmlspecialchars($pet['username']); ?>)</li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p>No public pets found.</p>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php include 'includes/footer.php'; ?>
