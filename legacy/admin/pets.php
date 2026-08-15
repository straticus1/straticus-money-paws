<?php
require_once '../includes/functions.php';
require_once 'header.php';

// Admin authentication check
if (!isAdmin()) {
    header('Location: /login.php');
    exit;
}

$pets = getAllPetsForAdmin();
?>

<main class="container">
    <h1>Pet Management</h1>
    
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Owner</th>
                <th>Uploaded</th>
                <th>Public?</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($pets as $pet): ?>
            <tr>
                <td><?php echo htmlspecialchars($pet['id']); ?></td>
                <td><?php echo htmlspecialchars($pet['original_name']); ?></td>
                <td><?php echo htmlspecialchars($pet['owner_name']); ?></td>
                <td><?php echo htmlspecialchars($pet['uploaded_at']); ?></td>
                <td><?php echo $pet['is_public'] ? 'Yes' : 'No'; ?></td>
                <td>
                                        <form action="delete_pet.php" method="POST" class="d-inline delete-confirmation">
                        <?php echo getCSRFTokenField(); ?>
                        <input type="hidden" name="pet_id" value="<?php echo $pet['id']; ?>">
                        <button type="submit" class="btn btn-danger">
                            Delete
                        </button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</main>

<?php require_once 'footer.php'; ?>
