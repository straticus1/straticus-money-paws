<?php
// This file is included by other pages, which should have already started the session
// and included functions.php
?>
<header>
    <nav class="container">
        <a href="index.php" class="logo">🐾 Money Paws</a>
        <form action="/search.php" method="get" class="search-form">
            <input type="search" name="q" placeholder="Search pets and users...">
            <button type="submit">Search</button>
        </form>
        <ul class="nav-links">
            <li><a href="index.php">Home</a></li>
            <li><a href="gallery.php">Gallery</a></li>
                        <li><a href="leaderboards.php">Leaderboards</a></li>
            <li><a href="abandoned_pets.php">Abandoned Pets</a></li>
            <?php if (isLoggedIn()): ?>
                <li><a href="upload.php">Upload</a></li>
                <li><a href="/profile.php">Profile</a></li>
                <li><a href="vacation_mode.php">Vacation Mode</a></li>
                <li><a href="/messages.php" class="nav-link">Messages</a>
                    <a href="/notifications.php" class="nav-link notifications-link"> <?php
                    $unreadCount = getUnreadNotificationCount($_SESSION['user_id']);
                    if ($unreadCount > 0) {
                        echo '<span class="notification-count">' . $unreadCount . '</span>';
                    }
                ?></a></li>
                <li><a href="/logout.php">Logout</a></li>
            <?php else: ?>
                <li><a href="login.php">Login</a></li>
                <li><a href="register.php">Sign Up</a></li>
            <?php endif; ?>
            <li><button id="theme-switcher" class="btn-theme">🌙</button></li>
        </ul>
    </nav>
</header>
