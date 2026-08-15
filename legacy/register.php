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
    $birth_date = sanitizeInput($_POST['birth_date'] ?? '');
    $gaming_preference = sanitizeInput($_POST['gaming_preference'] ?? 'educational');
    
    if (empty($name) || empty($email) || empty($password) || empty($confirmPassword)) {
        $error = 'Please fill in all required fields.';
    } elseif (!isValidEmail($email)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } elseif (getUserByEmail($email)) {
        $error = 'An account with this email already exists.';
    } else {
        // Validate birth date if provided
        $age = null;
        if (!empty($birth_date)) {
            $birth_date_obj = DateTime::createFromFormat('Y-m-d', $birth_date);
            if ($birth_date_obj && $birth_date_obj <= new DateTime()) {
                $today = new DateTime();
                $age = $today->diff($birth_date_obj)->y;
                
                // Age-based gaming preference validation
                if ($age < 13) {
                    $error = 'You must be at least 13 years old to create an account.';
                } elseif ($age < 18 && $gaming_preference !== 'educational') {
                    $gaming_preference = 'educational'; // Force educational for minors
                }
            } elseif (!empty($birth_date)) {
                $error = 'Please enter a valid birth date.';
            }
        }
        
        if (!$error) {
            if (createUserWithProfile($email, $password, $name, $birth_date, $gaming_preference)) {
                $user = getUserByEmail($email);
                loginUser($user['id']);
                
                // Process referral code if provided
                if (isset($_GET['ref']) && !empty($_GET['ref'])) {
                    require_once 'includes/social_features.php';
                    processReferral($_GET['ref'], $user['id']);
                }
                
                // Show age-appropriate welcome message
                if ($age !== null && $age < 18) {
                    $_SESSION['welcome_message'] = 'Welcome to Money Paws! Your account is set up for educational gaming to help you learn while having fun.';
                }
                
                redirectTo('index.php');
            } else {
                $error = 'Registration failed. Please try again.';
            }
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
                            <label for="birth_date">Birth Date (Optional)</label>
                            <input type="date" id="birth_date" name="birth_date" class="form-control"
                                   value="<?php echo isset($_POST['birth_date']) ? htmlspecialchars($_POST['birth_date']) : ''; ?>"
                                   max="<?php echo date('Y-m-d'); ?>">
                            <small class="form-text">
                                Helps us provide age-appropriate content. Users under 18 automatically get educational gaming mode.
                            </small>
                        </div>

                        <div class="form-group">
                            <label for="gaming_preference">Gaming Experience Preference</label>
                            <select id="gaming_preference" name="gaming_preference" class="form-control">
                                <option value="educational" 
                                        <?php echo (isset($_POST['gaming_preference']) && $_POST['gaming_preference'] === 'educational') ? 'selected' : ''; ?>>
                                    🎓 Educational - Learn while you play (recommended for all ages)
                                </option>
                                <option value="mixed" 
                                        <?php echo (isset($_POST['gaming_preference']) && $_POST['gaming_preference'] === 'mixed') ? 'selected' : ''; ?>>
                                    ⚖️ Mixed - Educational and traditional games (18+)
                                </option>
                                <option value="traditional" 
                                        <?php echo (isset($_POST['gaming_preference']) && $_POST['gaming_preference'] === 'traditional') ? 'selected' : ''; ?>>
                                    🎲 Traditional - Classic gaming with responsible features (18+)
                                </option>
                            </select>
                            <small class="form-text">
                                You can change this anytime. All users have access to educational content.
                            </small>
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
