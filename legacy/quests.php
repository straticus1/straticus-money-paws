<?php
/**
 * Paws.money - Quests & Achievements Page
 */
require_once 'includes/functions.php';
requireLogin();

$page_title = 'Quests & Achievements';
include 'includes/header.php';
?>

<div class="container mt-5">
    <div class="hero hero-padding">
        <h1><i class="fas fa-scroll"></i> Quests & Achievements</h1>
        <p>Complete daily tasks to earn rewards and unlock special achievements!</p>
    </div>

    <div class="tabs">
        <button class="tab-link active" onclick="openTab(event, 'quests')">Daily Quests</button>
        <button class="tab-link" onclick="openTab(event, 'achievements')">Achievements</button>
    </div>

    <div id="quests" class="tab-content active">
        <h2>Your Daily Quests</h2>
        <p class="text-muted">New quests appear every day. Check back tomorrow for more!</p>
        <div id="quest-list" class="quest-grid">
            <!-- Quests will be loaded here by JavaScript -->
            <div class="loader"></div>
        </div>
    </div>

    <div id="achievements" class="tab-content">
        <h2>Your Achievements</h2>
        <p class="text-muted">Unlock achievements by reaching milestones on Paws.money.</p>
        <div id="achievement-list" class="achievement-grid">
            <!-- Achievements will be loaded here by JavaScript -->
            <div class="loader"></div>
        </div>
    </div>
</div>

<?php
include 'includes/footer.php';
?>
<script src="assets/js/quests.js"></script>
