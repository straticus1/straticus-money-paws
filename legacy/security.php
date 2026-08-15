<?php
session_start();
require 'vendor/autoload.php';
require 'config/database.php';
require 'includes/functions.php';

use PragmaRX\Google2FA\Google2FA;

requireLogin();

$google2fa = new Google2FA();
$user_id = $_SESSION['user_id'];
$user = getUserById($user_id);
$user_2fa_settings = getUser2FASettings($user_id);
$setup_error = null;
$setup_success = null;

// Handle form submission for enabling/disabling 2FA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_2fa'])) {
    $new_status = !$user_2fa_settings['mfa_enabled'];

    if ($new_status) {
        // Enabling 2FA. The user will need to configure a method.
        updateUser2FASettings($user_id, true);
    } else {
        // Disabling 2FA. Clear all associated settings for security.
        updateUser2FASettings($user_id, false, null, null, null);
    }

    // Refresh settings from the database to show the updated state
    $user_2fa_settings = getUser2FASettings($user_id);
    $success_message = "2FA status has been updated successfully.";
}

// Handle request to set up Google Authenticator
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['setup_google_authenticator'])) {
    // Generate a new secret key and store it in the session for verification
    $_SESSION['totp_secret'] = $google2fa->generateSecretKey();
}

// Handle verification of Google Authenticator
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_google_authenticator'])) {
    $secret = $_SESSION['totp_secret'] ?? null;
    $code = $_POST['totp_code'] ?? '';

    if ($secret && $google2fa->verifyKey($secret, $code)) {
        // Code is valid, save the secret to the database and set as active method
        updateUser2FASettings($user_id, true, 'authenticator', null, $secret);
        unset($_SESSION['totp_secret']);
        $user_2fa_settings = getUser2FASettings($user_id); // Refresh settings
        $setup_success = "Google Authenticator has been enabled successfully!";
    } else {
        // Code is invalid
        $setup_error = "The verification code is incorrect. Please try again.";
    }
}

// Handle enabling Email 2FA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enable_email_2fa'])) {
    // Simply set the method to 'email'. The secret/phone fields are not used for this method.
    updateUser2FASettings($user_id, true, 'email');
    $user_2fa_settings = getUser2FASettings($user_id); // Refresh settings
    $setup_success = "Email verification has been enabled as your 2FA method.";
}

include 'includes/header.php';
?>

<main>
    <div class="container">
        <div class="page-header">
            <h1>🔒 2FA Security Settings</h1>
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success"><?php echo $success_message; ?></div>
            <?php endif; ?>
            <p class="lead">Manage your Two-Factor Authentication settings to enhance account security.</p>
        </div>

        <div class="card">
            <h2>Two-Factor Authentication (2FA)</h2>
            <p>2FA adds an extra layer of security to your account by requiring a second verification step when you sign in or perform sensitive actions like withdrawing funds.</p>

            <div class="security-setting-item">
                <h4>Current Status</h4>
                <div class="setting-status">
                    <span class="status-label <?php echo $user_2fa_settings['mfa_enabled'] ? 'text-success' : 'text-danger'; ?>">
                        <?php echo $user_2fa_settings['mfa_enabled'] ? 'Enabled' : 'Disabled'; ?>
                    </span>
                </div>
            </div>

            <form method="POST" action="security.php" class="mt-3">
                <button type="submit" name="toggle_2fa" class="btn <?php echo $user_2fa_settings['mfa_enabled'] ? 'btn-danger' : 'btn-success'; ?>">
                    <?php echo $user_2fa_settings['mfa_enabled'] ? 'Disable 2FA' : 'Enable 2FA'; ?>
                </button>
            </form>
        </div>

        <?php if ($user_2fa_settings['mfa_enabled']): ?>
            <div class="card mt-4">
                <h2>Configuration Options</h2>
                <p>Choose your preferred method for receiving verification codes.</p>

                <div class="list-group">
                    <!-- Google Authenticator Option -->
                    <div class="list-group-item">
                        <div class="d-flex w-100 justify-content-between">
                            <h5 class="mb-1">Google Authenticator (TOTP)</h5>
                            <small><?php echo ($user_2fa_settings['mfa_method'] === 'authenticator') ? '<span class="badge bg-success">Active</span>' : ''; ?></small>
                        </div>
                        <p class="mb-1">Use an authenticator app like Google Authenticator, Authy, or 1Password to generate time-based one-time passwords.</p>
                        
                        <?php if ($user_2fa_settings['mfa_method'] !== 'authenticator'): ?>
                            <form method="POST" action="security.php" class="mt-2">
                                <button type="submit" name="setup_google_authenticator" class="btn btn-primary">Set Up</button>
                            </form>
                        <?php endif; ?>
                    </div>

                    <!-- Email Verification Option -->
                    <div class="list-group-item">
                        <div class="d-flex w-100 justify-content-between">
                            <h5 class="mb-1">📧 Email Verification</h5>
                            <small><?php echo ($user_2fa_settings['mfa_method'] === 'email') ? '<span class="badge bg-success">Active</span>' : ''; ?></small>
                        </div>
                        <p class="mb-1">Receive a verification code at your registered email address: <strong><?php echo htmlspecialchars($user['email']); ?></strong></p>
                        
                        <?php if ($user_2fa_settings['mfa_method'] !== 'email'): ?>
                            <form method="POST" action="security.php" class="mt-2">
                                <button type="submit" name="enable_email_2fa" class="btn btn-primary">Enable Email Verification</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (isset($_SESSION['totp_secret'])): ?>
                <div class="mt-4 p-3 border rounded">
                    <h4>Set Up Google Authenticator</h4>
                    <p>Scan the QR code below with your authenticator app. If you cannot scan it, you can manually enter the secret key.</p>
                    
                    <?php
                    $qrCodeUrl = $google2fa->getQRCodeUrl(
                        'Paws.money',
                        $user['email'],
                        $_SESSION['totp_secret']
                    );
                    echo '<div class="text-center my-3"><img src="' . $qrCodeUrl . '" alt="QR Code"></div>';
                    ?>
                    
                    <p><strong>Secret Key:</strong> <code><?php echo htmlspecialchars($_SESSION['totp_secret'], ENT_QUOTES, 'UTF-8'); ?></code></p>

                    <hr>

                    <h5>Verify Code</h5>
                    <p>Enter the 6-digit code from your authenticator app to complete the setup.</p>
                    
                    <?php if ($setup_error): ?>
                        <div class="alert alert-danger"><?php echo $setup_error; ?></div>
                    <?php endif; ?>

                    <form method="POST" action="security.php">
                        <div class="mb-3">
                            <label for="totp_code" class="form-label">Verification Code</label>
                            <input type="text" name="totp_code" id="totp_code" class="form-control" required maxlength="6" pattern="[0-9]{6}">
                        </div>
                        <button type="submit" name="verify_google_authenticator" class="btn btn-success">Verify and Activate</button>
                    </form>
                </div>
                <?php endif; ?>

                <?php if ($setup_success): ?>
                    <div class="alert alert-success mt-3"><?php echo $setup_success; ?></div>
                <?php endif; ?>

            </div>
        <?php endif; ?>

    </div>
</main>

<?php include 'includes/footer.php'; ?>
