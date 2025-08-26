<?php
// The header requires functions.php to be included first.
require_once 'includes/functions.php';
require_once 'includes/header.php';

if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}

$current_user_id = $_SESSION['user_id'];

require_once 'includes/db.php';
$pdo = get_db();

// Fetch pending friend requests
$stmt_pending = $pdo->prepare("
    SELECT uf.id, u.username, u.profile_pic
    FROM user_friends uf
    JOIN users u ON uf.user_id_1 = u.id
    WHERE uf.user_id_2 = :user_id AND uf.status = 'pending'
");
$stmt_pending->execute(['user_id' => $current_user_id]);
$pending_requests = $stmt_pending->fetchAll(PDO::FETCH_ASSOC);

// Fetch accepted friends
$stmt_friends = $pdo->prepare("
    SELECT u.id, u.username, u.profile_pic
    FROM user_friends uf
    JOIN users u ON (u.id = uf.user_id_1 OR u.id = uf.user_id_2)
    WHERE (uf.user_id_1 = :user_id OR uf.user_id_2 = :user_id)
    AND uf.status = 'accepted'
    AND u.id != :user_id
");
$stmt_friends->execute(['user_id' => $current_user_id]);
$friends = $stmt_friends->fetchAll(PDO::FETCH_ASSOC);

?>

<div class="container mt-5">
    <h1 class="text-center mb-4">Friends</h1>

    <ul class="nav nav-tabs" id="friendsTab" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="my-friends-tab" data-bs-toggle="tab" data-bs-target="#my-friends" type="button" role="tab" aria-controls="my-friends" aria-selected="true">My Friends</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="pending-requests-tab" data-bs-toggle="tab" data-bs-target="#pending-requests" type="button" role="tab" aria-controls="pending-requests" aria-selected="false">Pending Requests</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="find-friends-tab" data-bs-toggle="tab" data-bs-target="#find-friends" type="button" role="tab" aria-controls="find-friends" aria-selected="false">Find Friends</button>
        </li>
    </ul>

    <div class="tab-content" id="friendsTabContent">
        <!-- My Friends Tab -->
        <div class="tab-pane fade show active" id="my-friends" role="tabpanel" aria-labelledby="my-friends-tab">
            <div class="p-4" id="my-friends-list">
                <?php if (empty($friends)): ?>
                    <p>You haven't added any friends yet. Use the 'Find Friends' tab to search for people you know.</p>
                <?php else: ?>
                    <?php foreach ($friends as $friend): ?>
                        <div class="card mb-2" id="friend-<?php echo $friend['id']; ?>">
                            <div class="card-body d-flex justify-content-between align-items-center">
                                <div>
                                    <img src="<?php echo htmlspecialchars($friend['profile_pic'] ?: 'assets/images/default_avatar.png'); ?>" alt="<?php echo htmlspecialchars($friend['username']); ?>" class="rounded-circle" width="40" height="40">
                                    <a href="profile.php?id=<?php echo $friend['id']; ?>" class="ms-2"><strong><?php echo htmlspecialchars($friend['username']); ?></strong></a>
                                </div>
                                <div>
                                    <button class="btn btn-primary btn-sm gift-btn" data-bs-toggle="modal" data-bs-target="#giftModal" data-friend-id="<?php echo $friend['id']; ?>" data-friend-name="<?php echo htmlspecialchars($friend['username']); ?>">Gift</button>
                                    <button class="btn btn-danger btn-sm remove-friend-btn" data-friend-id="<?php echo $friend['id']; ?>">Remove</button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Pending Requests Tab -->
        <div class="tab-pane fade" id="pending-requests" role="tabpanel" aria-labelledby="pending-requests-tab">
            <div class="p-4" id="pending-requests-list">
                <?php if (empty($pending_requests)): ?>
                    <p>You have no pending friend requests.</p>
                <?php else: ?>
                    <?php foreach ($pending_requests as $request): ?>
                        <div class="card mb-2" id="request-<?php echo $request['id']; ?>">
                            <div class="card-body d-flex justify-content-between align-items-center">
                                <div>
                                    <img src="<?php echo htmlspecialchars($request['profile_pic'] ?: 'assets/images/default_avatar.png'); ?>" alt="<?php echo htmlspecialchars($request['username']); ?>" class="rounded-circle" width="40" height="40">
                                    <strong class="ms-2"><?php echo htmlspecialchars($request['username']); ?></strong>
                                </div>
                                <div>
                                    <button class="btn btn-success btn-sm respond-request-btn" data-request-id="<?php echo $request['id']; ?>" data-action="accept">Accept</button>
                                    <button class="btn btn-danger btn-sm respond-request-btn" data-request-id="<?php echo $request['id']; ?>" data-action="decline">Decline</button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Find Friends Tab -->
        <div class="tab-pane fade" id="find-friends" role="tabpanel" aria-labelledby="find-friends-tab">
            <div class="p-4">
                <input type="text" id="userSearchInput" class="form-control" placeholder="Search for users...">
                <div id="userSearchResults" class="mt-3"></div>
            </div>
        </div>
    </div>
</div>

<!-- Gift Modal -->
<div class="modal fade" id="giftModal" tabindex="-1" aria-labelledby="giftModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="giftModalLabel">Send a Gift to <span id="giftFriendName"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="giftForm">
                    <input type="hidden" id="giftFriendId" name="friend_id">
                    <div class="mb-3">
                        <label for="inventoryItemSelect" class="form-label">Select Item</label>
                        <select class="form-select" id="inventoryItemSelect" name="item_id" required>
                            <!-- Inventory items will be loaded here via JavaScript -->
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="giftQuantity" class="form-label">Quantity</label>
                        <input type="number" class="form-control" id="giftQuantity" name="quantity" value="1" min="1" required>
                    </div>
                    <div id="gift-message" class="mt-3"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="sendGiftBtn">Send Gift</button>
            </div>
        </div>
    </div>
</div>

<script src="/assets/js/friends.js"></script>
<?php require_once 'includes/footer.php'; ?>
