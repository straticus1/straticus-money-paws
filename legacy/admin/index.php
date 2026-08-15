<?php
require_once '../includes/functions.php';
require_once 'header.php';

// Admin authentication check
if (!isAdmin()) {
    header('Location: /login.php');
    exit;
}

$stats = getSiteStatistics();

?>

<main class="container">
    <h1>Admin Dashboard</h1>
    <p>Welcome to the admin panel. From here you can manage users, pets, and site settings.</p>
    
    <div class="dashboard-widgets">
        <div class="widget">
            <h2>Site Statistics</h2>
            <ul>
                <li><strong>Total Users:</strong> <?php echo number_format($stats['total_users']); ?></li>
                <li><strong>Total Pets:</strong> <?php echo number_format($stats['total_pets']); ?></li>
                <li><strong>Total Interactions:</strong> <?php echo number_format($stats['total_interactions']); ?></li>
            </ul>
        </div>
    </div>
</main>

<?php require_once 'footer.php'; ?>
