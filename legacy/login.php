<?php
/**
 * Money Paws - Cryptocurrency-Powered Pet Platform
 * Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>
 */
require_once 'includes/functions.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRFToken();
    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password'];
    
    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields.';
    } elseif (!isValidEmail($email)) {
        $error = 'Please enter a valid email address.';
    } else {
        // First try demo account authentication (only in developer mode)
        $demoUserId = authenticateDemoUser($email, $password);
        if ($demoUserId) {
            loginUser($demoUserId);
            redirectTo('index.php');
        } else {
            // Fall back to regular database authentication
            $user = getUserByEmail($email);
            
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['is_admin'] = (bool)$user['is_admin'];
                redirectTo('index.php');
            } else {
                $error = 'Invalid email or password.';
            }
        }
    }
}

$pageTitle = 'Login';
require_once 'includes/html_head.php';
?>
<?php require_once 'includes/header.php'; ?>

    <main>
        <div class="container">
            <div class="form-container">
                <div class="card">
                    <h1>Login to Money Paws</h1>
                    <p>Welcome back! Sign in to your account to continue.</p>

                    <?php if ($error): ?>
                        <div class="alert alert-error"><?php echo $error; ?></div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php endif; ?>

                    <?php if (defined('DEVELOPER_MODE') && DEVELOPER_MODE): ?>
                        <div class="alert alert-info">
                            <strong>🔧 Developer Mode - Demo Accounts Available:</strong><br>
                            <strong>Admin:</strong> <?php echo DEMO_ADMIN_EMAIL; ?> / <?php echo DEMO_ADMIN_PASSWORD; ?><br>
                            <strong>User:</strong> <?php echo DEMO_USER_EMAIL; ?> / <?php echo DEMO_USER_PASSWORD; ?>
                        </div>
                    <?php endif; ?>

                                        <form method="POST" action="login.php">
                        <?php echo getCSRFTokenField(); ?>
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" name="email" class="form-control" 
                                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                                   required>
                        </div>

                        <div class="form-group">
                            <label for="password">Password</label>
                            <input type="password" id="password" name="password" class="form-control" required>
                        </div>

                                                <button type="submit" class="btn btn-primary btn-block">Sign In</button>
                    </form>

                    <div class="oauth-buttons">
                        <h3>Or sign in with:</h3>
                        
                        <a href="oauth/google.php" class="btn btn-google">
                            <span>📧</span> Continue with Google
                        </a>
                        
                        <a href="oauth/facebook.php" class="btn btn-facebook">
                            <span>📘</span> Continue with Facebook
                        </a>
                        
                        <a href="oauth/apple.php" class="btn btn-apple">
                            <span>🍎</span> Continue with Apple
                        </a>
                        
                        <a href="oauth/twitter.php" class="btn btn-twitter">
                            <span>🐦</span> Continue with X (Twitter)
                        </a>
                    </div>

                    <div class="form-footer">
                        <p>Don't have an account? <a href="register.php">Sign up here</a></p>
                        <p><a href="forgot-password.php" class="muted-link">Forgot your password?</a></p>
                    </div>
                </div>
            </div>
        </div>
    </main>

<?php require_once 'includes/footer.php'; ?>
</body>
</html>
