<?php
/**
 * Money Paws - Breeding Page
 * Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>
 */

require_once 'includes/functions.php';

requireLogin();

$user_id = $_SESSION['user_id'];
$user_pets = getUserPets($user_id);

include 'includes/header.php';
?>

<div class="container mt-5">
    <h1 class="text-center mb-4">Pet Breeding</h1>
    <div id="breeding-alert-container"></div>
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <div class="card">
                <div class="card-body">
                    <form id="breeding-form">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="mother-select">Select Mother</label>
                                    <select class="form-control" id="mother-select" name="mother_id">
                                        <option value="">-- Select a Pet --</option>
                                        <?php foreach ($user_pets as $pet): ?>
                                            <option value="<?php echo htmlspecialchars($pet['id']); ?>"><?php echo htmlspecialchars($pet['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="father-select">Select Father</label>
                                    <select class="form-control" id="father-select" name="father_id">
                                        <option value="">-- Select a Pet --</option>
                                        <?php foreach ($user_pets as $pet): ?>
                                            <option value="<?php echo htmlspecialchars($pet['id']); ?>"><?php echo htmlspecialchars($pet['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="form-group mt-3">
                            <label for="new-pet-name">Offspring's Name</label>
                            <input type="text" class="form-control" id="new-pet-name" name="name" placeholder="Enter a name for the new pet">
                        </div>
                        <button type="submit" class="btn btn-primary btn-block mt-4">Breed Pets</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
include 'includes/footer.php';
?>
<script src="assets/js/breeding.js"></script>
