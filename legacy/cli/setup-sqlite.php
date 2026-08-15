<?php
/**
 * Money Paws - SQLite Database Setup Script
 * This script creates and populates the SQLite database for testing purposes.
 * Run from the command line: php cli/setup-sqlite.php
 *
 * Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>
 */

// Define paths
$baseDir = dirname(__DIR__);
$dbPath = $baseDir . '/database/paws.sqlite';
$schemaPath = $baseDir . '/database/schema.sqlite.sql';

// --- Safety checks ---
if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.\n");
}

if (!file_exists($schemaPath)) {
    die("Error: SQLite schema file not found at {$schemaPath}\n");
}

// --- Database Setup ---
try {
    // 1. Delete old database file if it exists
    if (file_exists($dbPath)) {
        unlink($dbPath);
        echo "Removed existing SQLite database file.\n";
    }

    // 2. Create a new SQLite database connection
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Successfully created and connected to the SQLite database at {$dbPath}\n";

    // 3. Read the schema file
    $sql = file_get_contents($schemaPath);
    if ($sql === false) {
        throw new Exception("Could not read the schema file.");
    }
    echo "Read schema file successfully.\n";

    // 4. Execute the SQL commands
    $pdo->exec($sql);
    echo "Successfully executed schema and inserted default data.\n";

    // 5. Verify tables were created
    $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // 5. Apply additional schemas for features (Breeding, Adventures, etc.)
    echo "Applying additional feature schemas...\n";

    // --- Breeding Schema ---
    $pdo->exec("
        ALTER TABLE pets ADD COLUMN dna TEXT(50);
        ALTER TABLE pets ADD COLUMN gender TEXT CHECK(gender IN ('male', 'female'));
        ALTER TABLE pets ADD COLUMN generation INTEGER DEFAULT 1;
        ALTER TABLE pets ADD COLUMN parent_1_id INTEGER REFERENCES pets(id) ON DELETE SET NULL;
        ALTER TABLE pets ADD COLUMN parent_2_id INTEGER REFERENCES pets(id) ON DELETE SET NULL;

        CREATE TABLE breeding_cooldowns (
            pet_id INTEGER PRIMARY KEY,
            cooldown_ends_at DATETIME NOT NULL,
            FOREIGN KEY (pet_id) REFERENCES pets(id) ON DELETE CASCADE
        );
    ");
    echo "  - Applied breeding schema.\n";

    // --- Adventures & Marketplace Schema ---
    $pdo->exec("
        ALTER TABLE pets ADD COLUMN experience INTEGER DEFAULT 0;
                ALTER TABLE pets ADD COLUMN level INTEGER DEFAULT 1;
        ALTER TABLE pets ADD COLUMN market_status TEXT CHECK(market_status IN ('none', 'listed')) DEFAULT 'none';

        CREATE TABLE adventure_quests (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            description TEXT,
            min_level INTEGER DEFAULT 1,
            duration_minutes INTEGER NOT NULL,
            energy_cost INTEGER NOT NULL,
            experience_reward INTEGER NOT NULL
        );

        CREATE TABLE quest_rewards (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            quest_id INTEGER NOT NULL,
            item_id INTEGER NOT NULL,
            drop_chance REAL NOT NULL,
            FOREIGN KEY (quest_id) REFERENCES adventure_quests(id) ON DELETE CASCADE,
            FOREIGN KEY (item_id) REFERENCES store_items(id) ON DELETE CASCADE
        );

        CREATE TABLE pet_adventures (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            pet_id INTEGER NOT NULL UNIQUE,
            quest_id INTEGER NOT NULL,
            start_time DATETIME NOT NULL,
            end_time DATETIME NOT NULL,
            FOREIGN KEY (pet_id) REFERENCES pets(id) ON DELETE CASCADE,
            FOREIGN KEY (quest_id) REFERENCES adventure_quests(id) ON DELETE CASCADE
        );

        CREATE TABLE marketplace_listings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            listing_type TEXT CHECK(listing_type IN ('item', 'pet')) NOT NULL,
            item_id INTEGER,
            pet_id INTEGER,
            quantity INTEGER,
            price REAL NOT NULL,
            status TEXT CHECK(status IN ('active', 'sold', 'cancelled')) DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (item_id) REFERENCES store_items(id) ON DELETE CASCADE,
            FOREIGN KEY (pet_id) REFERENCES pets(id) ON DELETE CASCADE
        );
    ");
    echo "  - Applied adventures & marketplace schema.\n";

        // --- Pet Personalities & Social Bonds Schema ---
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS pet_personalities (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          pet_id INTEGER NOT NULL,
          trait VARCHAR(50) NOT NULL,
          value INTEGER NOT NULL DEFAULT 50,
          updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY (pet_id) REFERENCES pets(id) ON DELETE CASCADE,
          UNIQUE (pet_id, trait)
        );

        CREATE TABLE IF NOT EXISTS pet_social_bonds (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          pet_one_id INTEGER NOT NULL,
          pet_two_id INTEGER NOT NULL,
          bond_type TEXT CHECK(bond_type IN ('friendship', 'rivalry', 'family')) NOT NULL,
          bond_strength INTEGER NOT NULL DEFAULT 0,
          created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
          updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY (pet_one_id) REFERENCES pets(id) ON DELETE CASCADE,
          FOREIGN KEY (pet_two_id) REFERENCES pets(id) ON DELETE CASCADE,
          UNIQUE (pet_one_id, pet_two_id)
        );

        CREATE TABLE IF NOT EXISTS pet_health (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          pet_id INTEGER NOT NULL,
          health_points INTEGER NOT NULL DEFAULT 100 CHECK(health_points >= 0 AND health_points <= 100),
          status VARCHAR(50) NOT NULL DEFAULT 'healthy',
          last_checkup_at TIMESTAMP,
          notes TEXT,
          FOREIGN KEY (pet_id) REFERENCES pets(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS illnesses (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          name VARCHAR(100) NOT NULL UNIQUE,
          description TEXT,
          severity INTEGER NOT NULL DEFAULT 1 CHECK(severity >= 1 AND severity <= 10),
          treatment_item_id INTEGER,
          treatment_cost DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
          FOREIGN KEY (treatment_item_id) REFERENCES store_items(id) ON DELETE SET NULL
        );

        CREATE TABLE IF NOT EXISTS pet_active_illnesses (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          pet_id INTEGER NOT NULL,
          illness_id INTEGER NOT NULL,
          diagnosed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY (pet_id) REFERENCES pets(id) ON DELETE CASCADE,
          FOREIGN KEY (illness_id) REFERENCES illnesses(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS pet_messages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            sender_pet_id INTEGER NOT NULL,
            recipient_user_id INTEGER NOT NULL,
            message_content TEXT NOT NULL,
            is_read BOOLEAN DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (sender_pet_id) REFERENCES pets(id) ON DELETE CASCADE,
            FOREIGN KEY (recipient_user_id) REFERENCES users(id) ON DELETE CASCADE
        );
    ");
    echo "  - Applied pet personalities and social bonds schema.\n";

    // --- Seed Illnesses Data ---
    $pdo->exec("\n        INSERT OR IGNORE INTO illnesses (name, description, severity, treatment_cost) VALUES ('Pet Pox', 'A mild illness causing small blue spots on the fur.', 2, 50.00);\n        INSERT OR IGNORE INTO illnesses (name, description, severity, treatment_cost) VALUES ('Canine Cough', 'A persistent, dry cough.', 3, 75.00);\n        INSERT OR IGNORE INTO illnesses (name, description, severity, treatment_cost) VALUES ('Scale Rot', 'A common issue for reptilian pets, causing scales to become discolored and weak.', 4, 120.00);\n        INSERT OR IGNORE INTO illnesses (name, description, severity, treatment_cost) VALUES ('Giggle Fits', 'A strange condition where a pet cannot stop giggling. Contagious.', 1, 20.00);\n    ");
    echo "  - Seeded illnesses data.\n";

    // --- Site Currency Schema (already integrated into main schema) ---

    // 6. Final verification
    $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (count($tables) > 0) {
        echo "Database setup complete. Found " . count($tables) . " tables.\n";
    } else {
        throw new Exception("Database setup failed. No tables were created.");
    }

} catch (Exception $e) {
    die("An error occurred: " . $e->getMessage() . "\n");
}
