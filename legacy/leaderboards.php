<?php
require_once 'includes/functions.php';

$topPets = getTopPets();
$topUsers = getTopUsersByLikes();
$activeUsers = getMostActiveUsers();

include 'includes/header.php';
?>

<main class="container">
    <h1>Community Leaderboards</h1>

    <div class="leaderboard-container">
        <div class="leaderboard">
            <h2>🏆 Top Pets</h2>
            <ol>
                <?php foreach ($topPets as $pet): ?>
                    <li>
                        <a href="/profile.php?id=<?php echo htmlspecialchars($pet['user_id']); ?>#pet-<?php echo htmlspecialchars($pet['id']); ?>">
                            <?php echo htmlspecialchars($pet['name']); ?>
                        </a>
                        <span class="likes-count"><?php echo htmlspecialchars($pet['likes_count']); ?> Likes</span>
                    </li>
                <?php endforeach; ?>
            </ol>
        </div>

        <div class="leaderboard">
            <h2>👑 Top Pet Owners</h2>
            <ol>
                <?php foreach ($topUsers as $user): ?>
                    <li>
                        <a href="/profile.php?id=<?php echo htmlspecialchars($user['id']); ?>">
                            <?php echo htmlspecialchars($user['username']); ?>
                        </a>
                        <span class="likes-count"><?php echo htmlspecialchars($user['total_likes']); ?> Total Likes</span>
                    </li>
                <?php endforeach; ?>
            </ol>
        </div>

        <div class="leaderboard">
            <h2>🌟 Most Active Users</h2>
            <ol>
                <?php foreach ($activeUsers as $user): ?>
                    <li>
                        <a href="/profile.php?id=<?php echo htmlspecialchars($user['id']); ?>">
                            <?php echo htmlspecialchars($user['username']); ?>
                        </a>
                        <span class="likes-count"><?php echo htmlspecialchars($user['interaction_count']); ?> Interactions</span>
                    </li>
                <?php endforeach; ?>
            </ol>
        </div>
    </div>
</main>

<?php include 'includes/footer.php'; ?>
