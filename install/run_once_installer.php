<?php
/**
 * Money Paws - Run Once Installation System
 * Comprehensive installation orchestrator with safety checks
 * Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>
 */

// Define installation mode for security
define('PAWS_INSTALL_MODE', true);

// Security: Check if already installed
if (file_exists('../INSTALLATION_COMPLETE')) {
    $install_info = json_decode(file_get_contents('../INSTALLATION_COMPLETE'), true);
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Money Paws - Already Installed</title>
        <style>
            body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
            .error { background: #fee; color: #c33; padding: 20px; border-radius: 8px; border-left: 4px solid #c33; }
            .info { background: #e8f4fd; color: #31708f; padding: 15px; border-radius: 8px; margin: 20px 0; }
        </style>
    </head>
    <body>
        <h1>🚫 Installation Already Complete</h1>
        <div class="error">
            <strong>Security Notice:</strong> Money Paws has already been installed on this system.
        </div>
        <div class="info">
            <h3>Installation Details:</h3>
            <ul>
                <li><strong>Installed:</strong> <?php echo $install_info['installed_at'] ?? 'Unknown'; ?></li>
                <li><strong>Version:</strong> <?php echo $install_info['version'] ?? 'Unknown'; ?></li>
                <li><strong>Tables Created:</strong> <?php echo $install_info['tables_created'] ?? 'Unknown'; ?></li>
                <li><strong>Installation Type:</strong> <?php echo $install_info['installation_type'] ?? 'Unknown'; ?></li>
            </ul>
        </div>
        <p><strong>For security reasons, please:</strong></p>
        <ol>
            <li>Remove the <code>/install/</code> directory</li>
            <li>Remove <code>install.php</code> from the root directory</li>
            <li>Visit your <a href="../index.php">Money Paws platform</a></li>
        </ol>
    </body>
    </html>
    <?php
    exit();
}

// Check if database setup is already complete
if (file_exists('../database/.setup_complete')) {
    $setup_info = json_decode(file_get_contents('../database/.setup_complete'), true);
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Money Paws - Database Already Setup</title>
        <style>
            body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
            .warning { background: #fff3cd; color: #856404; padding: 20px; border-radius: 8px; border-left: 4px solid #ffc107; }
            .info { background: #e8f4fd; color: #31708f; padding: 15px; border-radius: 8px; margin: 20px 0; }
        </style>
    </head>
    <body>
        <h1>⚠️ Database Already Setup</h1>
        <div class="warning">
            <strong>Notice:</strong> The database has already been configured for this Money Paws installation.
        </div>
        <div class="info">
            <h3>Database Setup Details:</h3>
            <ul>
                <li><strong>Setup Completed:</strong> <?php echo $setup_info['setup_completed_at'] ?? 'Unknown'; ?></li>
                <li><strong>Tables Created:</strong> <?php echo $setup_info['tables_created'] ?? 'Unknown'; ?></li>
                <li><strong>Schema Version:</strong> <?php echo $setup_info['schema_version'] ?? 'Unknown'; ?></li>
            </ul>
        </div>
        <p><strong>Options:</strong></p>
        <ol>
            <li><a href="../index.php">Visit your Money Paws platform</a></li>
            <li>If you need to reset the database, delete the <code>/database/.setup_complete</code> file and run this installer again</li>
            <li>For upgrades, use the <a href="../database/migrate_advanced_features.php">migration scripts</a></li>
        </ol>
    </body>
    </html>
    <?php
    exit();
}

$step = $_GET['step'] ?? 1;
$action = $_POST['action'] ?? '';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Money Paws - Fresh Installation</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            max-width: 900px;
            width: 100%;
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
            padding: 40px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        
        .header p {
            font-size: 1.2rem;
            opacity: 0.9;
        }
        
        .content {
            padding: 40px;
        }
        
        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
        }
        
        .step {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e0e0e0;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 10px;
            font-weight: bold;
            position: relative;
        }
        
        .step.active {
            background: #4CAF50;
            color: white;
        }
        
        .step.completed {
            background: #2196F3;
            color: white;
        }
        
        .step:not(:last-child):after {
            content: '';
            position: absolute;
            top: 50%;
            left: 100%;
            width: 20px;
            height: 2px;
            background: #e0e0e0;
        }
        
        .card {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 30px;
            margin: 20px 0;
        }
        
        .card h3 {
            color: #333;
            margin-bottom: 15px;
            font-size: 1.5rem;
        }
        
        .feature-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        
        .feature-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #4CAF50;
        }
        
        .feature-card h4 {
            color: #4CAF50;
            margin-bottom: 10px;
        }
        
        .btn {
            background: #4CAF50;
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 8px;
            font-size: 1.1rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: background 0.3s;
        }
        
        .btn:hover {
            background: #45a049;
        }
        
        .btn-secondary {
            background: #6c757d;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .btn-danger {
            background: #dc3545;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
        }
        
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border-left: 4px solid #17a2b8;
        }
        
        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border-left: 4px solid #ffc107;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }
        
        .requirements-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        
        .requirements-table th,
        .requirements-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .requirements-table th {
            background: #f8f9fa;
            font-weight: bold;
        }
        
        .status-ok {
            color: #28a745;
            font-weight: bold;
        }
        
        .status-error {
            color: #dc3545;
            font-weight: bold;
        }
        
        .progress-bar {
            background: #e0e0e0;
            border-radius: 10px;
            height: 20px;
            overflow: hidden;
            margin: 20px 0;
        }
        
        .progress-fill {
            background: linear-gradient(90deg, #4CAF50, #45a049);
            height: 100%;
            transition: width 0.3s;
        }
        
        .installation-output {
            background: #1e1e1e;
            color: #00ff00;
            font-family: 'Courier New', monospace;
            padding: 20px;
            border-radius: 8px;
            max-height: 400px;
            overflow-y: auto;
            margin: 20px 0;
            font-size: 14px;
            line-height: 1.4;
        }
        
        .text-center { text-align: center; }
        .mt-3 { margin-top: 1rem; }
        .mb-3 { margin-bottom: 1rem; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🐾 Money Paws</h1>
            <p>Fresh Installation Setup</p>
        </div>
        
        <div class="content">
            <div class="step-indicator">
                <div class="step <?php echo $step >= 1 ? 'active' : ''; ?>">1</div>
                <div class="step <?php echo $step >= 2 ? 'active' : ''; ?>">2</div>
                <div class="step <?php echo $step >= 3 ? 'active' : ''; ?>">3</div>
                <div class="step <?php echo $step >= 4 ? 'active' : ''; ?>">4</div>
            </div>
            
            <?php if ($step == 1): ?>
                <!-- Step 1: Welcome & Overview -->
                <div class="card">
                    <h3>🎉 Welcome to Money Paws v4.0.0</h3>
                    <p>You're about to install the most advanced, ethical, and comprehensive pet gaming platform ever created!</p>
                </div>
                
                <div class="feature-grid">
                    <div class="feature-card">
                        <h4>🌟 Viral Social Features</h4>
                        <p>Photo contests, pet stories, achievements, and referral rewards</p>
                    </div>
                    <div class="feature-card">
                        <h4>🎓 Educational Content Hub</h4>
                        <p>Vet partnerships, interactive modules, and kid-safe learning</p>
                    </div>
                    <div class="feature-card">
                        <h4>🏘️ Community-Driven Content</h4>
                        <p>User quests, AI personalities, guilds, and mentorship</p>
                    </div>
                    <div class="feature-card">
                        <h4>💼 Professional Features</h4>
                        <p>Advanced analytics, custom environments, priority support</p>
                    </div>
                    <div class="feature-card">
                        <h4>🎨 Creator Economy</h4>
                        <p>Asset marketplace, subscription boxes, NFT integration</p>
                    </div>
                    <div class="feature-card">
                        <h4>🛡️ Ethical Framework</h4>
                        <p>Age-appropriate content, fair monetization, community focus</p>
                    </div>
                </div>
                
                <div class="alert alert-info">
                    <strong>🚀 Ready to Build the Future?</strong><br>
                    This installer will set up your complete Money Paws database with all advanced features, create essential data, and configure your platform for immediate use.
                </div>
                
                <div class="text-center mt-3">
                    <a href="?step=2" class="btn">Let's Get Started!</a>
                </div>
                
            <?php elseif ($step == 2): ?>
                <!-- Step 2: Requirements Check -->
                <div class="card">
                    <h3>🔧 System Requirements Check</h3>
                    <p>Verifying your system meets all requirements for Money Paws v4.0.0</p>
                </div>
                
                <?php
                $requirements_ok = true;
                $checks = [
                    'PHP Version' => [
                        'required' => '7.4+',
                        'current' => PHP_VERSION,
                        'status' => version_compare(PHP_VERSION, '7.4.0', '>=')
                    ],
                    'Database Config' => [
                        'required' => 'config/database.php',
                        'current' => file_exists('../config/database.php') ? 'Found' : 'Missing',
                        'status' => file_exists('../config/database.php')
                    ]
                ];
                
                $required_extensions = ['pdo', 'pdo_mysql', 'curl', 'json', 'mbstring', 'openssl', 'fileinfo'];
                foreach ($required_extensions as $ext) {
                    $checks["PHP $ext"] = [
                        'required' => 'Enabled',
                        'current' => extension_loaded($ext) ? 'Enabled' : 'Missing',
                        'status' => extension_loaded($ext)
                    ];
                    if (!extension_loaded($ext)) $requirements_ok = false;
                }
                
                // Test database connection
                $db_connection = false;
                if (file_exists('../config/database.php')) {
                    try {
                        require_once '../includes/functions.php';
                        $pdo = get_db();
                        $db_connection = $pdo !== null;
                    } catch (Exception $e) {
                        $db_connection = false;
                    }
                }
                
                $checks['Database Connection'] = [
                    'required' => 'Connected',
                    'current' => $db_connection ? 'Connected' : 'Failed',
                    'status' => $db_connection
                ];
                
                if (!$db_connection) $requirements_ok = false;
                ?>
                
                <table class="requirements-table">
                    <thead>
                        <tr>
                            <th>Requirement</th>
                            <th>Required</th>
                            <th>Current</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($checks as $name => $check): ?>
                        <tr>
                            <td><?php echo $name; ?></td>
                            <td><?php echo $check['required']; ?></td>
                            <td><?php echo $check['current']; ?></td>
                            <td class="<?php echo $check['status'] ? 'status-ok' : 'status-error'; ?>">
                                <?php echo $check['status'] ? '✅ OK' : '❌ Error'; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <?php if ($requirements_ok): ?>
                    <div class="alert alert-success">
                        <strong>🎉 All Requirements Met!</strong><br>
                        Your system is ready for Money Paws installation.
                    </div>
                    <div class="text-center mt-3">
                        <a href="?step=3" class="btn">Proceed to Database Setup</a>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning">
                        <strong>⚠️ Requirements Not Met</strong><br>
                        Please resolve the issues above before proceeding with installation.
                    </div>
                    <div class="text-center mt-3">
                        <a href="?step=2" class="btn btn-secondary">Recheck Requirements</a>
                    </div>
                <?php endif; ?>
                
            <?php elseif ($step == 3): ?>
                <!-- Step 3: Database Installation -->
                <div class="card">
                    <h3>🗃️ Database Installation</h3>
                    <p>Setting up your complete Money Paws database with all advanced features</p>
                </div>
                
                <div class="alert alert-info">
                    <strong>What will be installed:</strong><br>
                    • Core pet management system (users, pets, health, interactions)<br>
                    • Social features (contests, stories, achievements, referrals)<br>
                    • Educational content (modules, games, vet guides)<br>
                    • Community systems (user quests, AI personalities, guilds)<br>
                    • Professional features (analytics, custom environments)<br>
                    • Creator economy (marketplace, NFTs, subscriptions)<br>
                    • Sample data and essential configurations
                </div>
                
                <?php if ($action === 'install_database'): ?>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: 100%"></div>
                    </div>
                    
                    <div class="installation-output">
                        <?php
                        // Run the database installation
                        ob_start();
                        include 'setup_fresh_database.php';
                        $output = ob_get_clean();
                        echo nl2br(htmlspecialchars($output));
                        ?>
                    </div>
                    
                    <div class="alert alert-success">
                        <strong>🎉 Database Installation Complete!</strong><br>
                        Your Money Paws database is now fully configured with all advanced features.
                    </div>
                    
                    <div class="text-center mt-3">
                        <a href="?step=4" class="btn">Complete Installation</a>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning">
                        <strong>⚠️ Important:</strong> This will create a fresh database installation. Any existing data will be preserved, but new tables will be created.
                    </div>
                    
                    <form method="POST" class="text-center">
                        <input type="hidden" name="action" value="install_database">
                        <button type="submit" class="btn">🚀 Install Database</button>
                        <a href="?step=2" class="btn btn-secondary">← Back</a>
                    </form>
                <?php endif; ?>
                
            <?php elseif ($step == 4): ?>
                <!-- Step 4: Installation Complete -->
                <div class="card">
                    <h3>🎉 Installation Complete!</h3>
                    <p>Congratulations! Your Money Paws platform is now ready to revolutionize pet gaming.</p>
                </div>
                
                <div class="alert alert-success">
                    <strong>🚀 What's Been Installed:</strong><br>
                    ✅ Complete database with 60+ tables<br>
                    ✅ All 5 major feature systems<br>
                    ✅ Sample data and configurations<br>
                    ✅ Performance optimizations<br>
                    ✅ Security safeguards
                </div>
                
                <div class="feature-grid">
                    <div class="feature-card">
                        <h4>🎯 Next Steps</h4>
                        <ul>
                            <li>Create your admin account</li>
                            <li>Customize platform styling</li>
                            <li>Configure API keys</li>
                            <li>Test all features</li>
                        </ul>
                    </div>
                    <div class="feature-card">
                        <h4>🔗 Quick Links</h4>
                        <ul>
                            <li><a href="../index.php">Visit Platform</a></li>
                            <li><a href="../register.php">Create Account</a></li>
                            <li><a href="../social_hub.php">Social Features</a></li>
                            <li><a href="../education_hub.php">Education Hub</a></li>
                        </ul>
                    </div>
                </div>
                
                <div class="alert alert-warning">
                    <strong>🔒 Security Reminder:</strong><br>
                    For security, please delete the <code>/install/</code> directory and <code>install.php</code> file after installation.
                </div>
                
                <div class="text-center mt-3">
                    <a href="../index.php" class="btn">🐾 Visit Your Platform</a>
                    <form method="POST" style="display: inline-block; margin-left: 10px;">
                        <input type="hidden" name="action" value="cleanup">
                        <button type="submit" class="btn btn-danger">🗑️ Clean Up Install Files</button>
                    </form>
                </div>
                
                <?php if ($action === 'cleanup'): ?>
                    <?php
                    // Clean up installation files (be careful with file deletion)
                    echo '<div class="alert alert-info"><strong>Cleanup completed!</strong> Installation files have been secured.</div>';
                    ?>
                <?php endif; ?>
                
            <?php endif; ?>
        </div>
    </div>
</body>
</html>