<?php
/**
 * Money Paws - Adventures Page
 *
 * This page provides the user interface for sending pets on adventures,
 * viewing their progress, and collecting rewards.
 *
 * Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>
 */

include 'includes/header.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$pdo = get_db();

// Fetch user's pets
$stmt = $pdo->prepare("SELECT * FROM pets WHERE user_id = ?");
$stmt->execute([$user_id]);
$pets = $stmt->fetchAll();

?>

<div class="container mt-5">
    <h1 class="text-center mb-4">Pet Adventures</h1>
    <p class="text-center text-muted">Send your pets on exciting quests to earn experience and find rare items!</p>

    <!-- Adventure Status & Rewards Notification Area -->
    <div id="adventure-notifications" class="mb-4"></div>

    <div class="row">
        <!-- Left Column: Pet Selection -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h4>Your Pets</h4>
                </div>
                <div class="list-group list-group-flush" id="pet-selection-list">
                    <?php if (empty($pets)): ?>
                        <a href="#" class="list-group-item list-group-item-action">You don't have any pets yet.</a>
                    <?php else: ?>
                        <?php foreach ($pets as $pet): ?>
                            <a href="#" class="list-group-item list-group-item-action" data-pet-id="<?php echo $pet['id']; ?>">
                                <strong><?php echo htmlspecialchars($pet['name']); ?></strong>
                                <small class="d-block">Level: <?php echo $pet['level']; ?> | Exp: <?php echo $pet['experience']; ?></small>
                                <span class="adventure-status-badge" id="status-pet-<?php echo $pet['id']; ?>"></span>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right Column: Quests & Adventure Details -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h4 id="quest-list-header">Select a Pet to View Quests</h4>
                </div>
                <div class="card-body" id="quest-list-container">
                    <p class="text-muted">Please select a pet from the list on the left.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quest Details Modal -->
<div class="modal fade" id="questModal" tabindex="-1" aria-labelledby="questModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="questModalLabel">Quest Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="questModalBody">
        <!-- Quest details will be loaded here -->
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-primary" id="start-adventure-btn">Start Adventure</button>
      </div>
    </div>
  </div>
</div>

<?php include 'includes/footer.php'; ?>

<script src="assets/js/adventures.js"></script>
