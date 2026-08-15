<?php
/**
 * Money Paws - Get Store Items API
 * Returns all available store items with categories
 */
require_once '../includes/functions.php';
require_once '../includes/crypto.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

try {
    $items = getStoreItems();
    
    // Group items by category
    $categories = [];
    foreach ($items as $item) {
        $categories[$item['item_type']][] = $item;
    }
    
    echo json_encode([
        'success' => true,
        'items' => $items,
        'categories' => $categories,
        'total_items' => count($items)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch store items',
        'error' => $e->getMessage()
    ]);
}
?>
