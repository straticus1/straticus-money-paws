<?php
/**
 * Money Paws - Enhanced Features Setup Script
 * Run this script to set up all the new enhanced features
 * Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>
 */

require_once 'includes/functions.php';

// Check if user is admin or in developer mode
if (!defined('DEVELOPER_MODE') || !DEVELOPER_MODE) {
    if (!isLoggedIn() || !isAdmin($_SESSION['user_id'])) {
        die("Access denied. Admin privileges required.\n");
    }
}

echo "🚀 Money Paws Enhanced Features Setup\n";
echo "=====================================\n\n";

// Step 1: Run database migration
echo "📦 Step 1: Running database migration...\n";
include 'database/migrate_enhanced_features.php';

echo "\n✅ Database migration completed!\n\n";

// Step 2: Initialize sample data
echo "📝 Step 2: Creating sample quests for existing users...\n";

$pdo = get_db();
if ($pdo) {
    // Give existing users some Care Coins to start
    $stmt = $pdo->prepare("
        UPDATE users 
        SET care_coins = COALESCE(care_coins, 0) + 50 
        WHERE care_coins IS NULL OR care_coins = 0
    ");
    $stmt->execute();
    $updated_users = $stmt->rowCount();
    echo "💰 Gave 50 Care Coins to $updated_users existing users\n";
    
    // Create notifications for existing users
    $stmt = $pdo->prepare("
        INSERT INTO notifications (user_id, type, title, message, icon, created_at, is_read)
        SELECT id, 'system_update', 'New Features Available!', 
               'Money Paws now includes daily quests, community care, smart gaming, and metaverse worlds! Earn Care Coins and explore new experiences.', 
               '🎉', NOW(), 0
        FROM users 
        WHERE id NOT IN (SELECT user_id FROM notifications WHERE type = 'system_update')
    ");
    $stmt->execute();
    $notification_count = $stmt->rowCount();
    echo "📢 Created update notifications for $notification_count users\n";
    
    // Set community care permission for existing pets
    $stmt = $pdo->prepare("
        UPDATE pets 
        SET allows_community_care = 1 
        WHERE allows_community_care IS NULL
    ");
    $stmt->execute();
    $pet_count = $stmt->rowCount();
    echo "🐾 Enabled community care for $pet_count existing pets\n";
}

echo "\n✅ Sample data initialization completed!\n\n";

// Step 3: File permissions and directories
echo "📁 Step 3: Setting up directories and permissions...\n";

$directories = [
    'metaverse',
    'metaverse/environments',
    'metaverse/avatar-system',
    'metaverse/interaction-engine',
    'metaverse/integration'
];

foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
        echo "📂 Created directory: $dir\n";
    }
}

echo "✅ Directories setup completed!\n\n";

// Step 4: Configuration checks
echo "⚙️ Step 4: Configuration validation...\n";

$checks = [
    'Database connection' => get_db() !== null,
    'Sessions working' => session_status() === PHP_SESSION_ACTIVE,
    'Users table has new columns' => checkTableColumn('users', 'care_coins'),
    'Daily quests table exists' => checkTableExists('daily_quests'),
    'Community care table exists' => checkTableExists('community_pet_care'),
    'Metaverse tables exist' => checkTableExists('metaverse_worlds'),
    'Educational tables exist' => checkTableExists('educational_modules')
];

foreach ($checks as $check => $result) {
    echo ($result ? "✅" : "❌") . " $check: " . ($result ? "OK" : "FAILED") . "\n";
}

echo "\n";

// Step 5: Feature availability summary
echo "🎯 Step 5: Enhanced Features Summary\n";
echo "====================================\n\n";

echo "🎯 Daily Quests & Care Coins System\n";
echo "   - URL: /daily_quests.php\n";
echo "   - Users can earn up to 100 Care Coins daily\n";
echo "   - 7 different quest types for engagement\n\n";

echo "🤝 Community Pet Care\n";
echo "   - URL: /community_care.php\n";
echo "   - Peer-to-peer pet helping system\n";
echo "   - Earn Care Coins by helping others\n\n";

echo "🎮 Smart Gaming Hub\n";
echo "   - URL: /adaptive_gaming.php\n";
echo "   - Age-appropriate gaming experiences\n";
echo "   - Educational games for all users\n";
echo "   - Traditional games for 18+ with safeguards\n\n";

echo "🌍 Metaverse Pet Worlds\n";
echo "   - URL: /metaverse.php\n";
echo "   - 3D interactive environments\n";
echo "   - Educational zones and social spaces\n";
echo "   - Dynamic weather and day/night cycles\n\n";

echo "🛒 Enhanced Store\n";
echo "   - URL: /store.php\n";
echo "   - Care Coins store alongside crypto store\n";
echo "   - Free items earned through positive actions\n\n";

echo "👤 Smart Registration\n";
echo "   - URL: /register.php\n";
echo "   - Age-based gaming mode selection\n";
echo "   - Automatic protection for minors\n\n";

// Final recommendations
echo "📋 Recommended Next Steps:\n";
echo "==========================\n";
echo "1. 🧪 Test all new features thoroughly\n";
echo "2. 📚 Update your documentation\n";
echo "3. 🎨 Customize the UI colors and styles\n";
echo "4. 📱 Test mobile responsiveness\n";
echo "5. 🔒 Review security settings\n";
echo "6. 📊 Monitor user engagement metrics\n";
echo "7. 🎓 Create educational content for modules\n\n";

echo "🎉 Enhanced Features Setup Complete!\n";
echo "====================================\n";
echo "Your Money Paws platform now includes:\n";
echo "✅ Ethical monetization through Care Coins\n";
echo "✅ Age-appropriate gaming experiences\n";
echo "✅ Community-driven economy\n";
echo "✅ Educational content integration\n";
echo "✅ Immersive 3D metaverse worlds\n";
echo "✅ Responsible gaming safeguards\n\n";

echo "🌟 Welcome to the future of ethical pet gaming platforms!\n";

// Helper functions
function checkTableExists($table_name) {
    $pdo = get_db();
    if (!$pdo) return false;
    
    try {
        $stmt = $pdo->query("SELECT 1 FROM $table_name LIMIT 1");
        return $stmt !== false;
    } catch (Exception $e) {
        return false;
    }
}

function checkTableColumn($table_name, $column_name) {
    $pdo = get_db();
    if (!$pdo) return false;
    
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM $table_name LIKE '$column_name'");
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        return false;
    }
}

if (php_sapi_name() !== 'cli') {
    echo "<pre>\n";
    // If running from web browser, show in formatted text
}
?>