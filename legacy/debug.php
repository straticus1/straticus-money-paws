<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Starting debug...\n";

try {
    require_once 'includes/functions.php';
    echo "functions.php loaded successfully\n";
} catch (Exception $e) {
    echo "Error loading functions.php: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

try {
    $pageTitle = 'Home';
    require_once 'includes/html_head.php';
    echo "html_head.php loaded successfully\n";
} catch (Exception $e) {
    echo "Error loading html_head.php: " . $e->getMessage() . "\n";
}

try {
    require_once 'includes/header.php';
    echo "header.php loaded successfully\n";
} catch (Exception $e) {
    echo "Error loading header.php: " . $e->getMessage() . "\n";
}
?>
