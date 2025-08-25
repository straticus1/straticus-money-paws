<?php
/**
 * Money Paws Web-based Installation Script
 * Run this after updating config/database.php with your credentials
 * Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>
 */

// Security check - remove this file after installation
if (file_exists('INSTALLATION_COMPLETE')) {
    die('Installation already completed. Please remove install.php for security.');
}

$step = $_GET['step'] ?? 1;
$errors = [];
$warnings = [];
$success = [];

function checkRequirements() {
    global $errors, $warnings, $success;
    
    // Check PHP version
    if (version_compare(PHP_VERSION, '7.4.0', '>=')) {
        $success[] = "PHP version: " . PHP_VERSION . " ✓";
    } else {
        $errors[] = "PHP 7.4 or higher required. Current: " . PHP_VERSION;
    }
    
    // Check required extensions
    $required_extensions = ['pdo', 'pdo_mysql', 'curl', 'json', 'mbstring', 'openssl', 'fileinfo'];
    foreach ($required_extensions as $ext) {
        if (extension_loaded($ext)) {
            $success[] = "PHP extension $ext: ✓";
        } else {
            $errors[] = "Required PHP extension missing: $ext";
        }
    }
    
    // Check if config file exists
    if (file_exists('config/database.php')) {
        $success[] = "Configuration file found ✓";
        
        // Check if default values are still present
        $config_content = file_get_contents('config/database.php');
        if (strpos($config_content, 'your_google_client_id') !== false || 
            strpos($config_content, 'your_coinbase_api_key') !== false) {
            $warnings[] = "Default configuration values detected. Please update API keys.";
        }
    } else {
        $errors[] = "Configuration file not found: config/database.php";
    }
    
    return empty($errors);
}

function testDatabase() {
    global $errors, $success;
    
    try {
        require_once 'config/database.php';
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $success[] = "Database connection successful ✓";
        return true;
    } catch (PDOException $e) {
        $errors[] = "Database connection failed: " . $e->getMessage();
        return false;
    }
}

function createDirectories() {
    global $errors, $success;
    
    $directories = ['uploads/pets', 'logs', 'cache'];
    
    foreach ($directories as $dir) {
        if (!file_exists($dir)) {
            if (mkdir($dir, 0755, true)) {
                $success[] = "Created directory: $dir ✓";
            } else {
                $errors[] = "Failed to create directory: $dir";
            }
        } else {
            $success[] = "Directory exists: $dir ✓";
        }
    }
    
    return empty($errors);
}

function setupDatabase() {
    global $errors, $success;
    
    try {
        require_once 'config/database.php';
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $schema = file_get_contents('database/schema.sql');
        $statements = explode(';', $schema);
        
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (!empty($statement)) {
                $pdo->exec($statement);
            }
        }
        
        $success[] = "Database schema imported successfully ✓";
        return true;
    } catch (PDOException $e) {
        $errors[] = "Database setup failed: " . $e->getMessage();
        return false;
    }
}

function createSecurityFiles() {
    global $errors, $success;
    
    // Create main .htaccess
    $htaccess_content = '# Money Paws Security Configuration
RewriteEngine On

# Protect config directory
<Files "config/*">
    Order Allow,Deny
    Deny from all
</Files>

# Protect includes directory  
<Files "includes/*">
    Order Allow,Deny
    Deny from all
</Files>

# Security headers
<IfModule mod_headers.c>
    Header always set X-Content-Type-Options nosniff
    Header always set X-Frame-Options DENY
    Header always set X-XSS-Protection "1; mode=block"
</IfModule>

# Prevent access to sensitive files
<FilesMatch "\.(htaccess|htpasswd|ini|log|sh|sql|conf)$">
    Order Allow,Deny
    Deny from all
</FilesMatch>';
    
    if (file_put_contents('.htaccess', $htaccess_content)) {
        $success[] = "Security configuration created ✓";
    } else {
        $errors[] = "Failed to create .htaccess file";
    }
    
    // Create uploads .htaccess
    $uploads_htaccess = '<Files *.php>
    Order Allow,Deny
    Deny from all
</Files>

<FilesMatch "\.(jpg|jpeg|png|gif|webp)$">
    Order Allow,Deny
    Allow from all
</FilesMatch>';
    
    if (file_put_contents('uploads/.htaccess', $uploads_htaccess)) {
        $success[] = "Upload security configuration created ✓";
    } else {
        $errors[] = "Failed to create uploads/.htaccess file";
    }
    
    return empty($errors);
}

function completeInstallation() {
    global $success;
    
    // Create completion marker
    file_put_contents('INSTALLATION_COMPLETE', date('Y-m-d H:i:s'));
    
    // Create error log
    if (!file_exists('logs/error.log')) {
        touch('logs/error.log');
        chmod('logs/error.log', 0644);
    }
    
    $success[] = "Installation completed successfully! 🎉";
    return true;
}

// Handle POST requests for each step
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($_POST['action']) {
        case 'check_requirements':
            if (checkRequirements()) {
                header('Location: install.php?step=2');
                exit;
            }
            break;
            
        case 'test_database':
            if (testDatabase()) {
                header('Location: install.php?step=3');
                exit;
            }
            break;
            
        case 'create_directories':
            if (createDirectories()) {
                header('Location: install.php?step=4');
                exit;
            }
            break;
            
        case 'setup_database':
            if (setupDatabase()) {
                header('Location: install.php?step=5');
                exit;
            }
            break;
            
        case 'create_security':
            if (createSecurityFiles()) {
                header('Location: install.php?step=6');
                exit;
            }
            break;
            
        case 'complete':
            if (completeInstallation()) {
                header('Location: install.php?step=7');
                exit;
            }
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Money Paws Installation</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            padding: 20px;
            min-height: 100vh;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        
        .content {
            padding: 2rem;
        }
        
        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2rem;
            padding: 0 1rem;
        }
        
        .step {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #e1e5e9;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: #666;
        }
        
        .step.active {
            background: #667eea;
            color: white;
        }
        
        .step.completed {
            background: #28a745;
            color: white;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        
        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        
        .alert-error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        
        .alert-warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
        }
        
        .btn {
            background: #667eea;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn:hover {
            background: #5a67d8;
        }
        
        .btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        
        ul {
            list-style: none;
            padding: 0;
        }
        
        li {
            padding: 0.5rem 0;
            border-bottom: 1px solid #eee;
        }
        
        .progress {
            width: 100%;
            height: 20px;
            background: #e1e5e9;
            border-radius: 10px;
            overflow: hidden;
            margin: 1rem 0;
        }
        
        .progress-bar {
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            transition: width 0.3s ease;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🐾 Money Paws Installation</h1>
            <p>Setting up your AI Pet Gallery with Crypto Gaming</p>
        </div>
        
        <div class="content">
            <div class="step-indicator">
                <?php for ($i = 1; $i <= 7; $i++): ?>
                    <div class="step <?php echo $i < $step ? 'completed' : ($i == $step ? 'active' : ''); ?>">
                        <?php echo $i; ?>
                    </div>
                <?php endfor; ?>
            </div>
            
            <div class="progress">
                <div class="progress-bar" style="width: <?php echo (($step - 1) / 6) * 100; ?>%"></div>
            </div>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <h4>❌ Errors Found:</h4>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($warnings)): ?>
                <div class="alert alert-warning">
                    <h4>⚠️ Warnings:</h4>
                    <ul>
                        <?php foreach ($warnings as $warning): ?>
                            <li><?php echo htmlspecialchars($warning); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <h4>✅ Success:</h4>
                    <ul>
                        <?php foreach ($success as $msg): ?>
                            <li><?php echo htmlspecialchars($msg); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <?php switch ($step): 
                case 1: ?>
                    <h2>Step 1: System Requirements</h2>
                    <p>Checking if your server meets the requirements for Money Paws...</p>
                    
                    <?php checkRequirements(); ?>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="check_requirements">
                        <button type="submit" class="btn" <?php echo !empty($errors) ? 'disabled' : ''; ?>>
                            Continue to Database Test
                        </button>
                    </form>
                    
                    <?php if (!empty($errors)): ?>
                        <p><strong>Please fix the errors above before continuing.</strong></p>
                    <?php endif; ?>
                    
                    <?php break;
                    
                case 2: ?>
                    <h2>Step 2: Database Connection</h2>
                    <p>Testing connection to your MySQL database...</p>
                    
                    <?php testDatabase(); ?>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="test_database">
                        <button type="submit" class="btn" <?php echo !empty($errors) ? 'disabled' : ''; ?>>
                            Continue to Directory Setup
                        </button>
                    </form>
                    
                    <?php if (!empty($errors)): ?>
                        <p><strong>Please check your database configuration in config/database.php</strong></p>
                    <?php endif; ?>
                    
                    <?php break;
                    
                case 3: ?>
                    <h2>Step 3: Create Directories</h2>
                    <p>Creating required directories for uploads and logs...</p>
                    
                    <?php createDirectories(); ?>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="create_directories">
                        <button type="submit" class="btn">Continue to Database Setup</button>
                    </form>
                    
                    <?php break;
                    
                case 4: ?>
                    <h2>Step 4: Database Schema</h2>
                    <p>Setting up database tables and structure...</p>
                    
                    <?php setupDatabase(); ?>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="setup_database">
                        <button type="submit" class="btn" <?php echo !empty($errors) ? 'disabled' : ''; ?>>
                            Continue to Security Setup
                        </button>
                    </form>
                    
                    <?php break;
                    
                case 5: ?>
                    <h2>Step 5: Security Configuration</h2>
                    <p>Creating security files and configurations...</p>
                    
                    <?php createSecurityFiles(); ?>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="create_security">
                        <button type="submit" class="btn">Complete Installation</button>
                    </form>
                    
                    <?php break;
                    
                case 6: ?>
                    <h2>Step 6: Final Setup</h2>
                    <p>Completing the installation...</p>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="complete">
                        <button type="submit" class="btn">Finish Installation</button>
                    </form>
                    
                    <?php break;
                    
                case 7: ?>
                    <h2>🎉 Installation Complete!</h2>
                    
                    <?php completeInstallation(); ?>
                    
                    <div class="alert alert-success">
                        <h4>Money Paws has been successfully installed!</h4>
                    </div>
                    
                    <h3>Next Steps:</h3>
                    <ol>
                        <li><strong>Delete this file:</strong> Remove <code>install.php</code> for security</li>
                        <li><strong>Configure API Keys:</strong> Update <code>config/database.php</code> with production keys:
                            <ul>
                                <li>OAuth2 credentials (Google, Facebook, Apple, Twitter)</li>
                                <li>Coinbase Commerce API keys</li>
                                <li>AI service API keys (OpenAI, Stability AI)</li>
                            </ul>
                        </li>
                        <li><strong>Test the site:</strong> <a href="index.php" class="btn">Visit Money Paws</a></li>
                        <li><strong>Create admin account:</strong> Register your first user account</li>
                    </ol>
                    
                    <h3>Security Reminders:</h3>
                    <ul>
                        <li>✅ Keep API keys secure and never commit to version control</li>
                        <li>✅ Set up SSL/HTTPS for production</li>
                        <li>✅ Monitor <code>logs/error.log</code> for issues</li>
                        <li>✅ Regularly update dependencies with <code>composer update</code></li>
                    </ul>
                    
                    <?php break;
                    
                default: ?>
                    <h2>Invalid Step</h2>
                    <p>Please start from <a href="install.php?step=1">Step 1</a></p>
                    <?php break;
            endswitch; ?>
        </div>
    </div>
</body>
</html>
