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
$dbPath = $baseDir . '/database/paws_money_testing.sqlite';
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
    
    if (count($tables) > 0) {
        echo "Database setup complete. Found " . count($tables) . " tables.\n";
    } else {
        throw new Exception("Database setup failed. No tables were created.");
    }

} catch (Exception $e) {
    die("An error occurred: " . $e->getMessage() . "\n");
}
