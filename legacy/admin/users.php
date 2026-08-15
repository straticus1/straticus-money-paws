<?php
require_once '../includes/functions.php';
require_once 'header.php';

// Admin authentication check
if (!isAdmin()) {
    header('Location: /login.php');
    exit;
}

$users = getAllUsers();
?>

<main class="container">
    <h1>User Management</h1>
    
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Email</th>
                <th>Registered</th>
                <th>Is Admin?</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $user): ?>
            <tr>
                <td><?php echo htmlspecialchars($user['id']); ?></td>
                <td><?php echo htmlspecialchars($user['name']); ?></td>
                <td><?php echo htmlspecialchars($user['email']); ?></td>
                <td><?php echo htmlspecialchars($user['created_at']); ?></td>
                <td><?php echo $user['is_admin'] ? 'Yes' : 'No'; ?></td>
                <td>
                                        <form action="toggle_admin.php" method="POST" class="d-inline">
                        <?php echo getCSRFTokenField(); ?>
                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                        <input type="hidden" name="is_admin" value="<?php echo $user['is_admin'] ? '0' : '1'; ?>">
                        <button type="submit" class="btn">
                            <?php echo $user['is_admin'] ? 'Revoke Admin' : 'Make Admin'; ?>
                        </button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</main>

<?php require_once 'footer.php'; ?>
