<?php
/**
 * Money Paws - Fresh Database Setup Script (Run Once)
 * Complete database initialization for new installations
 * Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>
 */

// Security check
if (!defined('PAWS_INSTALL_MODE')) {
    die('🚫 Direct access denied. Run this script through the main installer.');
}

echo "🗃️ FRESH DATABASE SETUP\n";
echo str_repeat("=", 50) . "\n\n";

$start_time = microtime(true);
$total_tables = 0;
$total_errors = 0;

try {
    // Include database functions
    require_once dirname(__DIR__) . '/includes/functions.php';
    require_once dirname(__DIR__) . '/config/database.php';
    
    $pdo = get_db();
    if (!$pdo) {
        throw new Exception("❌ Database connection failed! Check your config/database.php settings.");
    }
    
    echo "✅ Database connection established\n";
    echo "🏗️ Starting fresh database setup...\n\n";
    
    // Step 1: Create core tables (existing structure)
    echo "📋 Step 1: Creating core tables...\n";
    
    $core_tables = [
        'users' => "
            CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                email VARCHAR(150) UNIQUE NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                care_coins INT DEFAULT 50,
                birth_date DATE NULL,
                gaming_preference ENUM('educational', 'mixed', 'traditional') DEFAULT 'educational',
                profile_picture VARCHAR(255),
                bio TEXT,
                is_verified TINYINT(1) DEFAULT 0,
                last_login TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB",
        
        'pets' => "
            CREATE TABLE IF NOT EXISTS pets (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                original_name VARCHAR(100) NOT NULL,
                filename VARCHAR(255) NOT NULL,
                description TEXT,
                species VARCHAR(50),
                breed VARCHAR(100),
                gender ENUM('male', 'female') DEFAULT 'male',
                birth_date DATE DEFAULT (CURRENT_DATE),
                life_status ENUM('alive', 'deceased') DEFAULT 'alive',
                allows_community_care TINYINT(1) DEFAULT 1,
                is_nft TINYINT(1) DEFAULT 0,
                deceased_date TIMESTAMP NULL,
                is_memorial_enabled TINYINT(1) DEFAULT 0,
                memorial_message TEXT,
                donation_goal DECIMAL(10,2) DEFAULT 0,
                donations_received DECIMAL(10,2) DEFAULT 0,
                views INT DEFAULT 0,
                likes INT DEFAULT 0,
                featured TINYINT(1) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB",
        
        'pet_stats' => "
            CREATE TABLE IF NOT EXISTS pet_stats (
                id INT AUTO_INCREMENT PRIMARY KEY,
                pet_id INT NOT NULL,
                hunger_level INT DEFAULT 80,
                happiness_level INT DEFAULT 80,
                last_fed TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                last_treated TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                total_treats INT DEFAULT 0,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_pet_stats (pet_id),
                FOREIGN KEY (pet_id) REFERENCES pets(id) ON DELETE CASCADE
            ) ENGINE=InnoDB",
        
        'pet_health' => "
            CREATE TABLE IF NOT EXISTS pet_health (
                id INT AUTO_INCREMENT PRIMARY KEY,
                pet_id INT NOT NULL,
                health_points INT DEFAULT 100,
                status ENUM('healthy', 'sick') DEFAULT 'healthy',
                last_checkup TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_pet_health (pet_id),
                FOREIGN KEY (pet_id) REFERENCES pets(id) ON DELETE CASCADE
            ) ENGINE=InnoDB",
        
        'illnesses' => "
            CREATE TABLE IF NOT EXISTS illnesses (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                description TEXT,
                severity ENUM('mild', 'moderate', 'severe') DEFAULT 'mild',
                treatment_cost INT DEFAULT 20,
                is_active TINYINT(1) DEFAULT 1
            ) ENGINE=InnoDB",
        
        'pet_active_illnesses' => "
            CREATE TABLE IF NOT EXISTS pet_active_illnesses (
                id INT AUTO_INCREMENT PRIMARY KEY,
                pet_id INT NOT NULL,
                illness_id INT NOT NULL,
                contracted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (pet_id) REFERENCES pets(id) ON DELETE CASCADE,
                FOREIGN KEY (illness_id) REFERENCES illnesses(id) ON DELETE CASCADE
            ) ENGINE=InnoDB",
        
        'store_items' => "
            CREATE TABLE IF NOT EXISTS store_items (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                description TEXT,
                item_type ENUM('food', 'treat', 'medicine', 'accessory') DEFAULT 'food',
                emoji VARCHAR(10),
                price_usd DECIMAL(6,2) DEFAULT 0,
                price_coins INT DEFAULT 0,
                hunger_restore INT DEFAULT 0,
                happiness_boost INT DEFAULT 0,
                is_active TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB",
        
        'user_inventory' => "
            CREATE TABLE IF NOT EXISTS user_inventory (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                item_id INT NOT NULL,
                quantity INT DEFAULT 1,
                acquired_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_user_item (user_id, item_id),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (item_id) REFERENCES store_items(id) ON DELETE CASCADE
            ) ENGINE=InnoDB",
        
        'pet_interactions' => "
            CREATE TABLE IF NOT EXISTS pet_interactions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                pet_id INT NOT NULL,
                user_id INT NOT NULL,
                interaction_type ENUM('feed', 'treat', 'heal', 'play') DEFAULT 'feed',
                item_id INT NULL,
                happiness_gained INT DEFAULT 0,
                hunger_restored INT DEFAULT 0,
                cost_usd DECIMAL(6,2) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (pet_id) REFERENCES pets(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (item_id) REFERENCES store_items(id) ON DELETE SET NULL
            ) ENGINE=InnoDB",
        
        'care_coin_transactions' => "
            CREATE TABLE IF NOT EXISTS care_coin_transactions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                amount INT NOT NULL,
                transaction_type ENUM('earned', 'spent') NOT NULL,
                reason TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB",
        
        'daily_quests' => "
            CREATE TABLE IF NOT EXISTS daily_quests (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                quest_type VARCHAR(50) NOT NULL,
                quest_data JSON,
                completed TINYINT(1) DEFAULT 0,
                completed_at TIMESTAMP NULL,
                coins_awarded INT DEFAULT 0,
                quest_date DATE NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_daily_quest (user_id, quest_type, quest_date),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB",
        
        'notifications' => "
            CREATE TABLE IF NOT EXISTS notifications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                type VARCHAR(50) DEFAULT 'info',
                title VARCHAR(200),
                message TEXT,
                icon VARCHAR(20),
                is_read TINYINT(1) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB",
        
        'educational_modules' => "
            CREATE TABLE IF NOT EXISTS educational_modules (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(200) NOT NULL,
                description TEXT,
                category VARCHAR(50),
                difficulty_level INT DEFAULT 1,
                min_age INT DEFAULT 6,
                max_age INT DEFAULT 99,
                estimated_duration INT DEFAULT 15,
                total_steps INT DEFAULT 5,
                passing_score INT DEFAULT 70,
                icon VARCHAR(10) DEFAULT '📚',
                is_active TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB",
        
        'metaverse_worlds' => "
            CREATE TABLE IF NOT EXISTS metaverse_worlds (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                description TEXT,
                world_type ENUM('park', 'beach', 'forest', 'educational', 'social') DEFAULT 'park',
                max_users INT DEFAULT 20,
                environment_config JSON,
                weather_enabled TINYINT(1) DEFAULT 1,
                is_active TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB"
    ];
    
    foreach ($core_tables as $table_name => $sql) {
        try {
            $pdo->exec($sql);
            echo "  ✅ Created table: $table_name\n";
            $total_tables++;
        } catch (PDOException $e) {
            echo "  ❌ Error creating $table_name: " . $e->getMessage() . "\n";
            $total_errors++;
        }
    }
    
    // Step 2: Run advanced features migration
    echo "\n📋 Step 2: Creating advanced features tables...\n";
    
    // Include the advanced features migration
    define('PAWS_MIGRATION_MODE', true);
    ob_start();
    include dirname(__DIR__) . '/database/migrate_advanced_features.php';
    $migration_output = ob_get_clean();
    
    // Parse migration output for success count
    if (preg_match('/Tables created successfully: (\d+)/', $migration_output, $matches)) {
        $advanced_tables = intval($matches[1]);
        $total_tables += $advanced_tables;
        echo "  ✅ Created $advanced_tables advanced feature tables\n";
    }
    
    // Step 3: Insert essential data
    echo "\n📋 Step 3: Inserting essential data...\n";
    
    // Insert sample store items
    $pdo->exec("
        INSERT IGNORE INTO store_items (id, name, description, item_type, emoji, price_coins, hunger_restore, happiness_boost) VALUES
        (1, 'Basic Pet Food', 'Nutritious food for your pet', 'food', '🥘', 5, 20, 5),
        (2, 'Premium Pet Food', 'High-quality gourmet food', 'food', '🍖', 15, 35, 15),
        (3, 'Tasty Treat', 'A delicious snack your pet will love', 'treat', '🦴', 8, 5, 25),
        (4, 'Health Potion', 'Restores your pet to full health', 'medicine', '💊', 25, 0, 10),
        (5, 'Super Treat', 'The ultimate pet treat experience', 'treat', '⭐', 20, 10, 40)
    ");
    echo "  ✅ Inserted sample store items\n";
    
    // Insert sample illnesses
    $pdo->exec("
        INSERT IGNORE INTO illnesses (id, name, description, severity, treatment_cost) VALUES
        (1, 'Common Cold', 'A mild respiratory infection', 'mild', 15),
        (2, 'Upset Stomach', 'Digestive discomfort', 'mild', 20),
        (3, 'Anxiety', 'Stress-related behavioral issues', 'moderate', 35),
        (4, 'Joint Pain', 'Age-related mobility issues', 'moderate', 45),
        (5, 'Serious Infection', 'Requires immediate treatment', 'severe', 75)
    ");
    echo "  ✅ Inserted sample illnesses\n";
    
    // Insert sample educational modules
    $pdo->exec("
        INSERT IGNORE INTO educational_modules (id, title, description, category, difficulty_level, min_age, max_age, estimated_duration, icon) VALUES
        (1, 'Pet Care Basics', 'Learn the fundamentals of caring for your virtual pets', 'basic', 1, 6, 99, 10, '🐾'),
        (2, 'Understanding Pet Behavior', 'Explore how pets think and feel', 'behavior', 2, 10, 99, 15, '🧠'),
        (3, 'Pet Health & Wellness', 'Keep your pets healthy and happy', 'health', 2, 8, 99, 12, '⚕️'),
        (4, 'Responsible Pet Ownership', 'The ethics and responsibilities of pet care', 'ethics', 3, 13, 99, 20, '❤️')
    ");
    echo "  ✅ Inserted sample educational modules\n";
    
    // Insert sample metaverse worlds
    $pdo->exec("
        INSERT IGNORE INTO metaverse_worlds (id, name, description, world_type, environment_config, weather_enabled) VALUES
        (1, 'Sunny Pet Park', 'A beautiful park where pets can play and socialize', 'park', '{\"theme\":\"sunny\",\"objects\":[\"trees\",\"benches\",\"playground\"]}', 1),
        (2, 'Peaceful Beach', 'A serene beach environment for relaxation', 'beach', '{\"theme\":\"tropical\",\"objects\":[\"sand\",\"waves\",\"palm_trees\"]}', 1),
        (3, 'Enchanted Forest', 'A magical forest full of wonders to explore', 'forest', '{\"theme\":\"mystical\",\"objects\":[\"ancient_trees\",\"mushrooms\",\"fairy_lights\"]}', 1),
        (4, 'Learning Center', 'Educational zone with interactive learning stations', 'educational', '{\"theme\":\"academic\",\"objects\":[\"library\",\"lab\",\"presentation_area\"]}', 0)
    ");
    echo "  ✅ Inserted sample metaverse worlds\n";
    
    // Step 4: Create indexes for performance
    echo "\n📋 Step 4: Creating database indexes...\n";
    
    $indexes = [
        'pets' => [
            'idx_user_id' => 'CREATE INDEX IF NOT EXISTS idx_pets_user_id ON pets(user_id)',
            'idx_life_status' => 'CREATE INDEX IF NOT EXISTS idx_pets_life_status ON pets(life_status)',
            'idx_created_at' => 'CREATE INDEX IF NOT EXISTS idx_pets_created_at ON pets(created_at)'
        ],
        'care_coin_transactions' => [
            'idx_user_id' => 'CREATE INDEX IF NOT EXISTS idx_transactions_user_id ON care_coin_transactions(user_id)',
            'idx_created_at' => 'CREATE INDEX IF NOT EXISTS idx_transactions_created_at ON care_coin_transactions(created_at)'
        ],
        'daily_quests' => [
            'idx_user_date' => 'CREATE INDEX IF NOT EXISTS idx_daily_quests_user_date ON daily_quests(user_id, quest_date)',
            'idx_completed' => 'CREATE INDEX IF NOT EXISTS idx_daily_quests_completed ON daily_quests(completed)'
        ],
        'pet_interactions' => [
            'idx_pet_id' => 'CREATE INDEX IF NOT EXISTS idx_interactions_pet_id ON pet_interactions(pet_id)',
            'idx_user_id' => 'CREATE INDEX IF NOT EXISTS idx_interactions_user_id ON pet_interactions(user_id)',
            'idx_created_at' => 'CREATE INDEX IF NOT EXISTS idx_interactions_created_at ON pet_interactions(created_at)'
        ]
    ];
    
    foreach ($indexes as $table => $table_indexes) {
        foreach ($table_indexes as $index_name => $sql) {
            try {
                $pdo->exec($sql);
                echo "  ✅ Created index: $index_name on $table\n";
            } catch (PDOException $e) {
                // Index might already exist, that's okay
                if (strpos($e->getMessage(), 'Duplicate key name') === false) {
                    echo "  ⚠️ Index warning ($index_name): " . $e->getMessage() . "\n";
                }
            }
        }
    }
    
    // Step 5: Create installation marker
    echo "\n📋 Step 5: Finalizing installation...\n";
    
    $install_info = [
        'installed_at' => date('Y-m-d H:i:s'),
        'version' => '4.0.0',
        'database_schema_version' => '4.0.0',
        'tables_created' => $total_tables,
        'installation_type' => 'fresh_install',
        'features_enabled' => [
            'viral_social_features',
            'educational_content_hub', 
            'community_driven_content',
            'professional_tier_features',
            'creator_economy'
        ]
    ];
    
    file_put_contents(dirname(__DIR__) . '/INSTALLATION_COMPLETE', json_encode($install_info, JSON_PRETTY_PRINT));
    echo "  ✅ Created installation completion marker\n";
    
    // Create database setup marker
    file_put_contents(dirname(__DIR__) . '/database/.setup_complete', json_encode([
        'setup_completed_at' => date('Y-m-d H:i:s'),
        'tables_created' => $total_tables,
        'schema_version' => '4.0.0'
    ], JSON_PRETTY_PRINT));
    echo "  ✅ Created database setup completion marker\n";
    
    $end_time = microtime(true);
    $execution_time = round($end_time - $start_time, 2);
    
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "🎉 FRESH DATABASE SETUP COMPLETE!\n";
    echo str_repeat("=", 50) . "\n\n";
    
    echo "📊 SETUP SUMMARY:\n";
    echo "✅ Tables created: $total_tables\n";
    echo "❌ Errors: $total_errors\n";
    echo "⏱️ Execution time: {$execution_time} seconds\n\n";
    
    if ($total_errors === 0) {
        echo "🚀 SUCCESS! Your Money Paws database is ready!\n\n";
        echo "🎯 WHAT'S INCLUDED:\n";
        echo "• Core pet management system\n";
        echo "• Viral social features (contests, stories, achievements)\n";
        echo "• Educational content hub (modules, games, vet guides)\n";
        echo "• Community-driven content (user quests, AI personalities, guilds)\n";
        echo "• Professional tier features (analytics, custom environments)\n";
        echo "• Creator economy (marketplace, NFTs, subscription boxes)\n";
        echo "• Complete Care Coins economy\n";
        echo "• Advanced health and interaction systems\n\n";
        
        echo "🔧 NEXT STEPS:\n";
        echo "1. 🎨 Customize your platform styling\n";
        echo "2. ⚙️ Configure API keys in config/database.php\n";
        echo "3. 🧪 Test all features thoroughly\n";
        echo "4. 👥 Create your first admin user\n";
        echo "5. 🚀 Launch your revolutionary platform!\n\n";
        
    } else {
        echo "⚠️ PARTIAL SUCCESS with $total_errors errors.\n";
        echo "💡 Review the errors above and run the setup again if needed.\n\n";
    }
    
    echo "🌟 Welcome to the future of ethical pet gaming! 🐾\n";
    
} catch (Exception $e) {
    echo "\n❌ CRITICAL ERROR: " . $e->getMessage() . "\n";
    echo "💡 Please check your database configuration and try again.\n";
    exit(1);
}
?>