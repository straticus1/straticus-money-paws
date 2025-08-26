<?php
/**
 * Money Paws - Pet Detail Page
 * Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>
 */
require_once 'includes/functions.php';
require_once 'includes/security.php';

$pet_id = $_GET['id'] ?? null;

if (!$pet_id) {
    header('Location: gallery.php');
    exit;
}

$pet = getPetById($pet_id);

if (!$pet) {
    http_response_code(404);
    include 'includes/header.php';
    echo "<main><div class='container'><div class='alert alert-error'>Pet not found.</div></div></main>";
    include 'includes/footer.php';
    exit;
}

$is_own_pet = isLoggedIn() && ($pet['user_id'] == $_SESSION['user_id']);
$owner = getUserById($pet['user_id']);

// Memorial and donation data
$donations = [];
$donation_progress = 0;
if ($pet['life_status'] === 'deceased' && $pet['is_memorial_enabled']) {
    $donations = getDonationsForPet($pet['id']);
    if ($pet['donation_goal'] > 0) {
        $donation_progress = round(($pet['donations_received'] / $pet['donation_goal']) * 100);
    }
}

// Mating request logic
$csrf_token = generate_csrf_token();
$can_request_mating = isLoggedIn() && !$is_own_pet && $pet['life_status'] === 'alive';
$user_pets_for_mating = [];
if ($can_request_mating) {
    $all_user_pets = getUserPets($_SESSION['user_id']);
    foreach ($all_user_pets as $user_pet) {
        if ($user_pet['gender'] !== $pet['gender']) {
            $user_pets_for_mating[] = $user_pet;
        }
    }
}

include 'includes/header.php';
?>

<main class="container mt-5">
    <?php if (isset($_SESSION['flash_message'])): ?>
        <div class="alert alert-<?php echo htmlspecialchars($_SESSION['flash_message']['type']); ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($_SESSION['flash_message']['message']); ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        </div>
        <?php unset($_SESSION['flash_message']); ?>
    <?php endif; ?>
        <div class="pet-detail-card">
        <?php if ($pet['life_status'] === 'deceased'): ?>
            <div class="alert alert-secondary text-center" role="alert">
                <h4 class="alert-heading">In Loving Memory</h4>
                <p>This page is a memorial for <strong><?php echo htmlspecialchars($pet['original_name']); ?></strong>. May they rest in peace.</p>
            </div>
        <?php endif; ?>
        <div class="row">
            <div class="col-md-6">
                <img src="<?php echo UPLOAD_DIR . htmlspecialchars($pet['filename']); ?>" alt="<?php echo htmlspecialchars($pet['original_name']); ?>" class="img-fluid rounded">
            </div>
            <div class="col-md-6">
                <h1><?php echo htmlspecialchars($pet['original_name']); ?></h1>
                <p>Owner: <a href="profile.php?id=<?php echo $owner['id']; ?>"><?php echo htmlspecialchars($owner['name']); ?></a></p>
                <p><strong>Description:</strong> <?php echo nl2br(htmlspecialchars($pet['description'])); ?></p>
                <p><strong>Gender:</strong> <?php echo htmlspecialchars(ucfirst($pet['gender'])); ?></p>
                <p><strong>Age:</strong> <?php echo getPetAgeInPetDays($pet['birth_date']); ?> pet days</p>

                <?php if ($pet['life_status'] === 'deceased'): ?>
                    <p><strong>Passed Away:</strong> <?php echo date('F j, Y', strtotime($pet['deceased_date'])); ?></p>
                <?php endif; ?>

                <hr>
                <?php if ($is_own_pet && $pet['life_status'] === 'alive'): ?>
                    <div class="owner-actions my-3">
                        <button type="button" class="btn btn-outline-danger" data-toggle="modal" data-target="#confirmMemorialModal">
                            Mark as Deceased
                        </button>
                    </div>
                    <hr>
                <?php endif; ?>

                <?php if ($is_own_pet && $pet['life_status'] === 'deceased'): ?>
                    <div class="memorial-config my-3">
                        <h4>Memorial Settings</h4>
                        <form action="/api/configure-memorial.php" method="POST">
                            <input type="hidden" name="pet_id" value="<?php echo htmlspecialchars($pet['id']); ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                            <div class="form-group form-check">
                                <input type="checkbox" class="form-check-input" id="enable_memorial" name="enable_memorial" <?php echo $pet['is_memorial_enabled'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="enable_memorial">Enable Public Memorial & Donations</label>
                            </div>
                            <div class="form-group">
                                <label for="donation_goal">Donation Goal ($500 - $1000 USD)</label>
                                <input type="number" class="form-control" id="donation_goal" name="donation_goal" min="0" max="1000" step="10" value="<?php echo htmlspecialchars($pet['donation_goal']); ?>">
                            </div>
                            <button type="submit" class="btn btn-info">Update Settings</button>
                        </form>
                    </div>
                    <hr>
                <?php endif; ?>

                <?php if ($can_request_mating && !empty($user_pets_for_mating)): ?>
                    <h3>Request Mating</h3>
                    <form action="api/send-mating-request.php" method="POST">
                        <?php echo getCSRFTokenField(); ?>
                        <input type="hidden" name="requested_pet_id" value="<?php echo htmlspecialchars($pet['id']); ?>">
                        <div class="form-group">
                            <label for="requester_pet_id">Select your pet to send request:</label>
                            <select name="requester_pet_id" id="requester_pet_id" class="form-control">
                                <?php foreach ($user_pets_for_mating as $user_pet): ?>
                                    <option value="<?php echo htmlspecialchars($user_pet['id']); ?>"><?php echo htmlspecialchars($user_pet['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">Send Mating Request</button>
                    </form>
                <?php elseif ($can_request_mating): ?>
                    <p class="text-muted">You do not have any pets of the opposite gender available for mating.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($pet['life_status'] === 'deceased' && $pet['is_memorial_enabled']): ?>
    <div class="memorial-donation-section mt-4">
        <div class="card">
            <div class="card-header">
                <h4>Memorial Fund</h4>
            </div>
            <div class="card-body">
                <p>In memory of <strong><?php echo htmlspecialchars($pet['original_name']); ?></strong>, the owner is accepting donations to honor their life. All funds go directly to the owner.</p>
                
                <!-- Donation Progress Bar -->
                <div class="progress my-3">
                    <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $donation_progress; ?>%;" aria-valuenow="<?php echo $donation_progress; ?>" aria-valuemin="0" aria-valuemax="100">
                        <?php echo $donation_progress; ?>%
                    </div>
                </div>
                <p class="text-center">
                    <strong>$<?php echo number_format($pet['donations_received'], 2); ?></strong> raised of
                    $<?php echo number_format($pet['donation_goal'], 2); ?> goal
                </p>

                <hr>

                <!-- Donation Form -->
                <?php if (isLoggedIn() && !$is_own_pet && $pet['donations_received'] < $pet['donation_goal']): ?>
                    <h5>Make a Donation</h5>
                    <form action="/api/make-donation.php" method="POST">
                        <input type="hidden" name="pet_id" value="<?php echo htmlspecialchars($pet['id']); ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <div class="form-row">
                            <div class="col-md-4 form-group">
                                <label for="amount">Amount (USD)</label>
                                <input type="number" class="form-control" id="amount" name="amount" min="1" max="<?php echo $pet['donation_goal'] - $pet['donations_received']; ?>" step="1" required>
                            </div>
                            <div class="col-md-8 form-group">
                                <label for="message">Message (Optional)</label>
                                <input type="text" class="form-control" id="message" name="message" placeholder="With deepest sympathy...">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">Donate Now</button>
                    </form>
                <?php elseif ($pet['donations_received'] >= $pet['donation_goal']): ?>
                    <div class="alert alert-info text-center">The donation goal for this memorial has been reached. Thank you to everyone who contributed.</div>
                <?php endif; ?>

                <hr>

                <!-- Recent Donations -->
                <h5>Recent Donations</h5>
                <?php if (empty($donations)): ?>
                    <p>No donations have been made yet.</p>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($donations as $donation): ?>
                            <li class="list-group-item">
                                <strong><?php echo htmlspecialchars($donation['donor_name']); ?></strong> donated <strong>$<?php echo number_format($donation['amount_usd'], 2); ?></strong>
                                <small class="text-muted float-right"><?php echo time_ago($donation['created_at']); ?></small>
                                <?php if (!empty($donation['message'])): ?>
                                    <p class="mb-0 mt-1"><em>"<?php echo htmlspecialchars($donation['message']); ?>"</em></p>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

</main>

<?php if ($is_own_pet && $pet['life_status'] === 'alive'): ?>
<!-- Memorial Confirmation Modal -->
<div class="modal fade" id="confirmMemorialModal" tabindex="-1" role="dialog" aria-labelledby="modalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalLabel">Confirm Memorial</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>This action is irreversible. It will mark <strong><?php echo htmlspecialchars($pet['original_name']); ?></strong> as deceased and turn this page into a permanent memorial.</p>
                <p>Are you sure you want to proceed?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <form action="/api/mark-pet-deceased.php" method="POST">
                    <input type="hidden" name="pet_id" value="<?php echo htmlspecialchars($pet['id']); ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <button type="submit" class="btn btn-danger">Yes, Create Memorial</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
