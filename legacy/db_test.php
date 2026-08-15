<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Testing database connection...\n";

try {
    // Test the database connection directly
    $sqlite_path = __DIR__ . '/database/paws.sqlite';
    echo "SQLite path: $sqlite_path\n";
    echo "File exists: " . (file_exists($sqlite_path) ? 'Yes' : 'No') . "\n";
    
    if (file_exists($sqlite_path)) {
        $pdo = new PDO('sqlite:' . $sqlite_path);
        echo "SQLite connection successful!\n";
        
        // Test a simple query
        $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo "Tables found: " . implode(', ', $tables) . "\n";
    }
    
} catch (Exception $e) {
    echo "Database error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

echo "Test complete.\n";
?>
