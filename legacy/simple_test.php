<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html><html><head><title>Test</title></head><body>";
echo "<h1>PHP Server is working!</h1>";
echo "<p>Current time: " . date('Y-m-d H:i:s') . "</p>";

// Test database connection
try {
    $sqlite_path = __DIR__ . '/database/paws.sqlite';
    $pdo = new PDO('sqlite:' . $sqlite_path);
    echo "<p>✅ Database connection successful</p>";
} catch (Exception $e) {
    echo "<p>❌ Database error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Test includes
try {
    require_once 'includes/functions.php';
    echo "<p>✅ functions.php loaded</p>";
} catch (Exception $e) {
    echo "<p>❌ functions.php error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "</body></html>";
?>
