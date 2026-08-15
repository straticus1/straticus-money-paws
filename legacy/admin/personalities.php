<?php
require_once '../includes/functions.php';
require_once '../includes/personalities.php';

if (!is_logged_in() || !is_admin()) {
    header('Location: /login.php');
    exit;
}

$pets = getAllPetPersonalities();

include 'header.php';
?>

<div class="container mt-4">
    <h2>Manage Pet Personalities</h2>
    <p>Here you can view and adjust the personality traits of all pets in the game.</p>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">Personality updated successfully!</div>
    <?php elseif (isset($_GET['error'])): ?>
        <div class="alert alert-danger">There was an error updating the personality.</div>
    <?php endif; ?>

    <div class="table-responsive">
        <table class="table table-bordered table-striped">
            <thead class="thead-dark">
                <tr>
                    <th>Pet Name</th>
                    <th>Owner</th>
                    <th>Bravery</th>
                    <th>Friendliness</th>
                    <th>Curiosity</th>
                    <th>Laziness</th>
                    <th>Greed</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pets as $pet): ?>
                <tr>
                    <td><?php echo htmlspecialchars($pet['pet_name']); ?></td>
                    <td><?php echo htmlspecialchars($pet['owner_name']); ?></td>
                    <form action="/api/admin-update-pet-personality.php" method="POST">
                        <input type="hidden" name="pet_id" value="<?php echo $pet['pet_id']; ?>">
                        <td><input type="number" name="bravery" class="form-control" value="<?php echo $pet['personalities']['bravery'] ?? 0; ?>" min="0" max="100"></td>
                        <td><input type="number" name="friendliness" class="form-control" value="<?php echo $pet['personalities']['friendliness'] ?? 0; ?>" min="0" max="100"></td>
                        <td><input type="number" name="curiosity" class="form-control" value="<?php echo $pet['personalities']['curiosity'] ?? 0; ?>" min="0" max="100"></td>
                        <td><input type="number" name="laziness" class="form-control" value="<?php echo $pet['personalities']['laziness'] ?? 0; ?>" min="0" max="100"></td>
                        <td><input type="number" name="greed" class="form-control" value="<?php echo $pet['personalities']['greed'] ?? 0; ?>" min="0" max="100"></td>
                        <td><button type="submit" class="btn btn-primary btn-sm">Update</button></td>
                    </form>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'footer.php'; ?>
