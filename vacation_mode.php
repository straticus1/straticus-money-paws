<?php
require_once 'includes/functions.php';
requireLogin();

$user = getUserById($_SESSION['user_id']);
$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRFToken();
    if (isset($_POST['toggle_vacation_mode'])) {
        if ($_POST['toggle_vacation_mode'] === 'enable') {
            $delegate_name = sanitizeInput($_POST['delegate_user']);
            $reserved_funds = filter_input(INPUT_POST, 'reserved_funds', FILTER_VALIDATE_FLOAT);

            if (empty($delegate_name)) {
                $error = "Please enter a delegate's username.";
            } elseif ($reserved_funds === false || $reserved_funds <= 0) {
                $error = "Please enter a valid amount for reserved funds.";
            } else {
                $delegate_user = getUserByName($delegate_name);
                if (!$delegate_user) {
                    $error = "The delegate user '{$delegate_name}' could not be found.";
                } elseif ($delegate_user['id'] == $_SESSION['user_id']) {
                    $error = "You cannot delegate pet care to yourself.";
                } else {
                    // TODO: Check if user has enough funds to reserve.
                    if (setVacationMode($_SESSION['user_id'], $delegate_user['id'], $reserved_funds)) {
                        $message = "Vacation mode has been enabled. Your pets will be cared for by {$delegate_user['name']}.";
                        $user = getUserById($_SESSION['user_id']); // Refresh user data
                    } else {
                        $error = "There was an error enabling vacation mode. Please try again.";
                    }
                }
            }
        } elseif ($_POST['toggle_vacation_mode'] === 'disable') {
            if (disableVacationMode($_SESSION['user_id'])) {
                $message = "Vacation mode has been disabled.";
                $user = getUserById($_SESSION['user_id']); // Refresh user data
            } else {
                $error = "There was an error disabling vacation mode. Please try again.";
            }
        }
    }
}

$isOnVacation = !empty($user['is_on_vacation']);
if ($isOnVacation) {
    $delegate = getUserById($user['vacation_delegate_id']);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vacation Mode - Money Paws</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <main>
        <div class="container">
                        <div class="hero py-2">
                <h1>Vacation Mode</h1>
                <p>Delegate pet care to another user while you're away.</p>
            </div>

            <?php if ($message): ?>
                                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="card">
                <h3>Manage Vacation Status</h3>
                <?php if ($isOnVacation): ?>
                    <p><strong>You are currently IN vacation mode.</strong></p>
                    <p>Your pets are being cared for by: <strong><?php echo htmlspecialchars($delegate['name'] ?? 'Unknown'); ?></strong></p>
                    <p>Reserved funds for pet care: <strong>$<?php echo number_format($user['vacation_reserved_funds'], 2); ?></strong></p>
                    <p>Vacation mode started on: <strong><?php echo date('F j, Y, g:i a', strtotime($user['vacation_start_date'])); ?></strong></p>
                                                            <form action="vacation_mode.php" method="post" class="mt-1">
                        <?php echo getCSRFTokenField(); ?>
                        <button type="submit" name="toggle_vacation_mode" value="disable" class="btn btn-danger">Disable Vacation Mode</button>
                    </form>
                <?php else: ?>
                                        <form action="vacation_mode.php" method="POST">
                    <?php echo getCSRFTokenField(); ?>
                        <div class="form-group">
                            <label for="vacation_status">Current Status:</label>
                            <p id="vacation_status"><strong>You are currently NOT in vacation mode.</strong></p>
                        </div>

                        <div class="form-group">
                            <label for="delegate_user">Delegate Pet Care To:</label>
                            <input type="text" id="delegate_user" name="delegate_user" class="form-control" placeholder="Enter username of delegate" required>
                            <small>This user will be able to care for your pets using your account funds.</small>
                        </div>

                        <div class="form-group">
                            <label for="reserved_funds">Reserve Funds for Pet Care (USD):</label>
                            <input type="number" id="reserved_funds" name="reserved_funds" class="form-control" min="0.01" step="0.01" placeholder="e.g., 25.00" required>
                            <small>These funds will be set aside for the delegate to use on pet care items from the store.</small>
                        </div>

                        <button type="submit" name="toggle_vacation_mode" value="enable" class="btn btn-primary">Enable Vacation Mode</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <?php include 'includes/footer.php'; ?>
</body>
</html>
