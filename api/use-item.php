<?php
/**
 * Money Paws - Use Item on Pet API
 * Allows users to use inventory items on pets
 */
require_once '../includes/functions.php';
require_once '../includes/pet_care.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $petId = intval($input['pet_id'] ?? 0);
    $itemId = intval($input['item_id'] ?? 0);
    $targetType = sanitizeInput($input['target_type'] ?? 'owned'); // owned, other, stray, adoption
    
    if (!$petId || !$itemId) {
        throw new Exception('Missing required parameters');
    }
    
    // Check if user has the item in inventory
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT ui.quantity, si.* 
        FROM user_inventory ui 
        JOIN store_items si ON ui.item_id = si.id 
        WHERE ui.user_id = ? AND ui.item_id = ? AND ui.quantity > 0
    ");
    $stmt->execute([$_SESSION['user_id'], $itemId]);
    $inventoryItem = $stmt->fetch();
    
    if (!$inventoryItem) {
        throw new Exception('Item not found in inventory or insufficient quantity');
    }
    
    // Get pet information
    $stmt = $pdo->prepare("SELECT * FROM pets WHERE id = ?");
    $stmt->execute([$petId]);
    $pet = $stmt->fetch();
    
    if (!$pet) {
        throw new Exception('Pet not found');
    }
    
    // Check permissions based on target type
    switch ($targetType) {
        case 'owned':
            if ($pet['user_id'] != $_SESSION['user_id']) {
                throw new Exception('You can only use items on your own pets');
            }
            break;
        case 'other':
        case 'stray':
        case 'adoption':
            // Allow using items on any pet (community feature)
            break;
        default:
            throw new Exception('Invalid target type');
    }
    
    // Use the item on the pet
    $result = useItemOnPet($_SESSION['user_id'], $petId, $itemId, $targetType);
    
    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'message' => $result['message'],
            'pet_stats' => $result['pet_stats'],
            'remaining_quantity' => $result['remaining_quantity']
        ]);
    } else {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $result['message']
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to use item',
        'error' => $e->getMessage()
    ]);
}
?>
