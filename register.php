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
    $name = sanitizeInput($_POST['name']);
    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    
    if (empty($name) || empty($email) || empty($password) || empty($confirmPassword)) {
        $error = 'Please fill in all fields.';
    } elseif (!isValidEmail($email)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } elseif (getUserByEmail($email)) {
        $error = 'An account with this email already exists.';
    } else {
        if (createUser($email, $password, $name)) {
            $user = getUserByEmail($email);
            loginUser($user['id']);
            redirectTo('index.php');
        } else {
            $error = 'Registration failed. Please try again.';
        }
    }
}
?>
<?php
$pageTitle = 'Register';
require_once 'includes/html_head.php';
?>
<?php require_once 'includes/header.php'; ?>

    <main>
        <div class="container">
            <div class="form-container">
                <div class="card">
                    <h1>Join Money Paws</h1>
                    <p>Create your account and start sharing amazing AI pet creations!</p>

                    <?php if ($error): ?>
                        <div class="alert alert-error"><?php echo $error; ?></div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php endif; ?>

                    <div class="oauth-buttons">
                        <h3>Quick Sign Up:</h3>
                        <a href="oauth/google.php" class="btn btn-google"><span>📧</span> Sign up with Google</a>
                        <a href="oauth/facebook.php" class="btn btn-facebook"><span>📘</span> Sign up with Facebook</a>
                        <a href="oauth/apple.php" class="btn btn-apple"><span>🍎</span> Sign up with Apple</a>
                        <a href="oauth/twitter.php" class="btn btn-twitter"><span>🐦</span> Sign up with X (Twitter)</a>
                    </div>

                    <div class="divider">
                        <span>Or sign up manually</span>
                    </div>

                    <form action="register.php" method="POST">
                        <?php echo getCSRFTokenField(); ?>
                        <div class="form-group">
                            <label for="name">Full Name</label>
                            <input type="text" id="name" name="name" class="form-control" 
                                   value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" 
                                   required>
                        </div>

                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" name="email" class="form-control" 
                                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                                   required>
                        </div>

                        <div class="form-group">
                            <label for="password">Password</label>
                            <input type="password" id="password" name="password" class="form-control" 
                                   minlength="6" required>
                            <small class="form-text">Must be at least 6 characters long</small>
                        </div>

                        <div class="form-group">
                            <label for="confirm_password">Confirm Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" 
                                   minlength="6" required>
                        </div>

                        <div class="form-group">
                            <label class="terms-label">
                                <input type="checkbox" required>
                                I agree to the <a href="terms.php" target="_blank">Terms of Service</a> 
                                and <a href="privacy.php" target="_blank">Privacy Policy</a>
                            </label>
                        </div>

                                                <button type="submit" class="btn btn-primary btn-block">Create Account</button>
                    </form>

                                        <div class="form-footer">
                        <p>Already have an account? <a href="login.php">Sign in here</a></p>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php require_once 'includes/footer.php'; ?>

<script>
    // Password confirmation validation
    document.getElementById('confirm_password').addEventListener('input', function() {
        const password = document.getElementById('password').value;
        const confirmPassword = this.value;
        
        if (password !== confirmPassword) {
            this.setCustomValidity('Passwords do not match');
        } else {
            this.setCustomValidity('');
        }
    });
</script>

<?php require_once 'includes/scripts.php'; ?>
