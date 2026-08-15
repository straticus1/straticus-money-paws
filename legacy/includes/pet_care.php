<?php
/**
 * Money Paws - Cryptocurrency-Powered Pet Platform
 * Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>
 */
require_once 'functions.php';
require_once 'crypto.php';

// Pet care and stats functions
function getPetStats($petId) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT * FROM pet_stats WHERE pet_id = ?");
    $stmt->execute([$petId]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$stats) {
        // Create default stats for pet
        $stmt = $pdo->prepare("INSERT INTO pet_stats (pet_id) VALUES (?)");
        $stmt->execute([$petId]);
        
        $stmt = $pdo->prepare("SELECT * FROM pet_stats WHERE pet_id = ?");
        $stmt->execute([$petId]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Update stats based on time passed
    updatePetStatsOverTime($stats);
    
    return $stats;
}

function updatePetStatsOverTime($stats) {
    global $pdo;
    
    $petId = $stats['pet_id'];
    $now = time();
    $lastUpdate = strtotime($stats['updated_at']);
    $hoursPassed = ($now - $lastUpdate) / 3600;
    
    // Decrease hunger over time (1 point per hour)
    $newHunger = max(0, $stats['hunger_level'] - floor($hoursPassed));
    
    // Decrease happiness if pet is hungry (2 points per hour if hunger < 20)
    $newHappiness = $stats['happiness_level'];
    if ($newHunger < 20) {
        $newHappiness = max(0, $newHappiness - floor($hoursPassed * 2));
    }
    
    if ($newHunger != $stats['hunger_level'] || $newHappiness != $stats['happiness_level']) {
        $stmt = $pdo->prepare("
            UPDATE pet_stats 
            SET hunger_level = ?, happiness_level = ?, updated_at = NOW() 
            WHERE pet_id = ?
        ");
        $stmt->execute([$newHunger, $newHappiness, $petId]);
    }
}

function feedPet($userId, $petId) {
    // Basic feeding without specific items - could be expanded
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Update pet stats
        $stmt = $pdo->prepare("UPDATE pet_stats SET hunger_level = LEAST(100, hunger_level + 20), last_fed = NOW() WHERE pet_id = ?");
        $stmt->execute([$petId]);
        
        // Record interaction
        $interactionId = recordPetInteraction($userId, $petId, 'feed');

        // Create notification
        $pet = getPetById($petId);
        if ($pet) {
            createNotification($pet['user_id'], $userId, $petId, $interactionId, 'feed');
        }
        
        $pdo->commit();
        return ['success' => true, 'message' => 'Pet fed successfully!'];
    } catch (Exception $e) {
        $pdo->rollback();
        return ['success' => false, 'message' => 'Failed to feed pet.'];
    }
}

function feedPetWithItem($userId, $petId, $itemId) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Get item details
        $stmt = $pdo->prepare("SELECT * FROM store_items WHERE id = ?");
        $stmt->execute([$itemId]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$item || $item['item_type'] !== 'food') {
            throw new Exception('Invalid food item.');
        }
        
        // Check user has the item
        $stmt = $pdo->prepare("SELECT quantity FROM user_inventory WHERE user_id = ? AND item_id = ?");
        $stmt->execute([$userId, $itemId]);
        $inventory = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$inventory || $inventory['quantity'] <= 0) {
            throw new Exception('You don\'t have this item.');
        }
        
        // Update pet stats
        $hungerIncrease = $item['hunger_restore'];
        $happinessIncrease = $item['happiness_boost'];
        
        $stmt = $pdo->prepare("UPDATE pet_stats SET 
            hunger_level = LEAST(100, hunger_level + ?), 
            happiness_level = LEAST(100, happiness_level + ?),
            last_fed = NOW() 
            WHERE pet_id = ?");
        $stmt->execute([$hungerIncrease, $happinessIncrease, $petId]);
        
        // Reduce item quantity
        $stmt = $pdo->prepare("UPDATE user_inventory SET quantity = quantity - 1 WHERE user_id = ? AND item_id = ?");
        $stmt->execute([$userId, $itemId]);
        
        // Remove item if quantity is 0
        $stmt = $pdo->prepare("DELETE FROM user_inventory WHERE user_id = ? AND item_id = ? AND quantity <= 0");
        $stmt->execute([$userId, $itemId]);
        
        // Record interaction
        $interactionId = recordPetInteraction($userId, $petId, 'feed', $itemId);

        // Create notification for pet owner
        $pet = getPetById($petId);
        if ($pet) {
            createNotification($pet['user_id'], $userId, $petId, $interactionId, 'feed');
        }
        
        $pdo->commit();
        
        return [
            'success' => true, 
            'message' => "Fed pet with {$item['name']}! Hunger +{$hungerIncrease}, Happiness +{$happinessIncrease}",
            'remaining_quantity' => max(0, $inventory['quantity'] - 1)
        ];
    } catch (Exception $e) {
        $pdo->rollback();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function treatPetWithItem($userId, $petId, $itemId) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Get item details
        $stmt = $pdo->prepare("SELECT * FROM store_items WHERE id = ?");
        $stmt->execute([$itemId]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$item || $item['item_type'] !== 'treat') {
            throw new Exception('Invalid treat item.');
        }
        
        // Check user has the item
        $stmt = $pdo->prepare("SELECT quantity FROM user_inventory WHERE user_id = ? AND item_id = ?");
        $stmt->execute([$userId, $itemId]);
        $inventory = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$inventory || $inventory['quantity'] <= 0) {
            throw new Exception('You don\'t have this item.');
        }
        
        // Update pet stats
        $hungerIncrease = $item['hunger_restore'];
        $happinessIncrease = $item['happiness_boost'];
        
        $stmt = $pdo->prepare("UPDATE pet_stats SET 
            hunger_level = LEAST(100, hunger_level + ?), 
            happiness_level = LEAST(100, happiness_level + ?),
            last_treated = NOW() 
            WHERE pet_id = ?");
        $stmt->execute([$hungerIncrease, $happinessIncrease, $petId]);
        
        // Reduce item quantity
        $stmt = $pdo->prepare("UPDATE user_inventory SET quantity = quantity - 1 WHERE user_id = ? AND item_id = ?");
        $stmt->execute([$userId, $itemId]);
        
        // Remove item if quantity is 0
        $stmt = $pdo->prepare("DELETE FROM user_inventory WHERE user_id = ? AND item_id = ? AND quantity <= 0");
        $stmt->execute([$userId, $itemId]);
        
        // Record interaction
        $interactionId = recordPetInteraction($userId, $petId, 'treat', $itemId);

        // Create notification for pet owner
        $pet = getPetById($petId);
        if ($pet) {
            createNotification($pet['user_id'], $userId, $petId, $interactionId, 'treat');
        }
        
        $pdo->commit();
        
        return [
            'success' => true, 
            'message' => "Gave pet a {$item['name']}! Hunger +{$hungerIncrease}, Happiness +{$happinessIncrease}",
            'remaining_quantity' => max(0, $inventory['quantity'] - 1)
        ];
    } catch (Exception $e) {
        $pdo->rollback();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function getUserInventoryItem($userId, $itemId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT ui.*, si.name, si.description, si.item_type, si.emoji, si.hunger_restore, si.happiness_boost
        FROM user_inventory ui 
        JOIN store_items si ON ui.item_id = si.id 
        WHERE ui.user_id = ? AND ui.item_id = ?
    ");
    $stmt->execute([$userId, $itemId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function recordPetInteraction($userId, $petId, $type, $itemId = null) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        INSERT INTO pet_interactions (pet_id, user_id, interaction_type, item_id, created_at) 
        VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$petId, $userId, $type, $itemId]);
    return $pdo->lastInsertId();
}

function needsFood($petStats) {
    return $petStats['hunger_level'] < 80;
}

function needsTreat($petStats) {
    return $petStats['happiness_level'] < 80;
}

function giveTreat($petId, $userId, $itemId) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Get item details
        $stmt = $pdo->prepare("SELECT * FROM store_items WHERE id = ? AND item_type = 'treat'");
        $stmt->execute([$itemId]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$item) {
            throw new Exception('Invalid treat');
        }
        
        // Check if user owns the item
        $ownsItem = false;
        if ($userId) {
            $stmt = $pdo->prepare("SELECT quantity FROM user_inventory WHERE user_id = ? AND item_id = ?");
            $stmt->execute([$userId, $itemId]);
            $inventory = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($inventory && $inventory['quantity'] > 0) {
                $ownsItem = true;
                // Decrease inventory
                $stmt = $pdo->prepare("UPDATE user_inventory SET quantity = quantity - 1 WHERE user_id = ? AND item_id = ?");
                $stmt->execute([$userId, $itemId]);
            }
        }
        
        // Get current pet stats
        $stats = getPetStats($petId);
        
        // Calculate new stats
        $newHunger = min(100, $stats['hunger_level'] + $item['hunger_restore']);
        $newHappiness = min(100, $stats['happiness_level'] + $item['happiness_boost']);
        
        // Update pet stats
        $stmt = $pdo->prepare("
            UPDATE pet_stats 
            SET hunger_level = ?, happiness_level = ?, last_treated = NOW(), total_treats = total_treats + 1 
            WHERE pet_id = ?
        ");
        $stmt->execute([$newHunger, $newHappiness, $petId]);
        
        // Record interaction
        $stmt = $pdo->prepare("
            INSERT INTO pet_interactions 
            (pet_id, user_id, interaction_type, item_id, happiness_gained, hunger_restored, cost_usd) 
            VALUES (?, ?, 'treat', ?, ?, ?, ?)
        ");
        $stmt->execute([
            $petId, 
            $userId, 
            $itemId, 
            $item['happiness_boost'], 
            $item['hunger_restore'], 
            $ownsItem ? 0 : $item['price_usd']
        ]);
        
        $pdo->commit();
        
        return [
            'success' => true,
            'hunger_gained' => $item['hunger_restore'],
            'happiness_gained' => $item['happiness_boost'],
            'new_hunger' => $newHunger,
            'new_happiness' => $newHappiness,
            'cost' => $ownsItem ? 0 : $item['price_usd']
        ];
        
    } catch (Exception $e) {
        $pdo->rollback();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function getStoreItems($type = null) {
    global $pdo;
    
    $sql = "SELECT * FROM store_items WHERE is_active = 1";
    $params = [];
    
    if ($type) {
        $sql .= " AND item_type = ?";
        $params[] = $type;
    }
    
    $sql .= " ORDER BY item_type, price_usd";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getUserInventory($userId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT ui.*, si.name, si.description, si.item_type, si.emoji, si.price_usd
        FROM user_inventory ui
        JOIN store_items si ON ui.item_id = si.id
        WHERE ui.user_id = ? AND ui.quantity > 0
        ORDER BY si.item_type, si.name
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function purchaseStoreItem($userId, $itemId, $quantity, $cryptoType) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Get item details
        $stmt = $pdo->prepare("SELECT * FROM store_items WHERE id = ? AND is_active = 1");
        $stmt->execute([$itemId]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$item) {
            throw new Exception('Item not found');
        }
        
        $totalCostUSD = $item['price_usd'] * $quantity;
        
        // In developer mode, skip crypto conversion and balance checks
        if (defined('DEVELOPER_MODE') && DEVELOPER_MODE) {
            $cryptoAmount = 0; // Free in developer mode
        } else {
            $cryptoAmount = convertUSDToCrypto($totalCostUSD, $cryptoType);
            
            if ($cryptoAmount === null) {
                throw new Exception('Unable to get crypto price');
            }
            
            $userBalance = getUserCryptoBalance($userId, $cryptoType);
            
            if ($userBalance < $cryptoAmount) {
                throw new Exception('Insufficient balance');
            }
            
            // Deduct crypto balance
            updateUserBalance($userId, $cryptoType, $cryptoAmount, 'subtract');
            
            // Record transaction
            createCryptoTransaction($userId, 'store_purchase', $cryptoType, $cryptoAmount, $totalCostUSD);
        }
        
        // Add to inventory
        $stmt = $pdo->prepare("
            INSERT INTO user_inventory (user_id, item_id, quantity) 
            VALUES (?, ?, ?) 
            ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)
        ");
        $stmt->execute([$userId, $itemId, $quantity]);
        
        $pdo->commit();
        
        return [
            'success' => true,
            'item' => $item,
            'quantity' => $quantity,
            'total_cost_usd' => $totalCostUSD,
            'crypto_amount' => $cryptoAmount,
            'crypto_type' => $cryptoType
        ];
        
    } catch (Exception $e) {
        $pdo->rollback();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function getPetHungerStatus($hungerLevel) {
    if ($hungerLevel >= 80) return ['status' => 'full', 'emoji' => '😊', 'message' => 'Very satisfied'];
    if ($hungerLevel >= 60) return ['status' => 'content', 'emoji' => '🙂', 'message' => 'Content'];
    if ($hungerLevel >= 40) return ['status' => 'neutral', 'emoji' => '😐', 'message' => 'Getting hungry'];
    if ($hungerLevel >= 20) return ['status' => 'hungry', 'emoji' => '😟', 'message' => 'Hungry'];
    return ['status' => 'starving', 'emoji' => '😭', 'message' => 'Starving!'];
}

function getPetHappinessStatus($happinessLevel) {
    if ($happinessLevel >= 80) return ['status' => 'ecstatic', 'emoji' => '🤩', 'message' => 'Ecstatic'];
    if ($happinessLevel >= 60) return ['status' => 'happy', 'emoji' => '😄', 'message' => 'Happy'];
    if ($happinessLevel >= 40) return ['status' => 'neutral', 'emoji' => '😐', 'message' => 'Neutral'];
    if ($happinessLevel >= 20) return ['status' => 'sad', 'emoji' => '😢', 'message' => 'Sad'];
    return ['status' => 'depressed', 'emoji' => '😭', 'message' => 'Very sad'];
}


function useItemOnPet($userId, $petId, $itemId, $targetType = 'owned') {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Get item details
        $stmt = $pdo->prepare("SELECT * FROM store_items WHERE id = ?");
        $stmt->execute([$itemId]);
        $item = $stmt->fetch();
        
        if (!$item) {
            throw new Exception('Item not found');
        }
        
        // Check user has item in inventory
        $stmt = $pdo->prepare("SELECT quantity FROM user_inventory WHERE user_id = ? AND item_id = ? AND quantity > 0");
        $stmt->execute([$userId, $itemId]);
        $inventory = $stmt->fetch();
        
        if (!$inventory) {
            throw new Exception('Item not available in inventory');
        }
        
        // Get current pet stats
        $stats = getPetStats($petId);
        
        // Apply item effects
        $newHunger = min(100, $stats['hunger_level'] + $item['hunger_restore']);
        $newHappiness = min(100, $stats['happiness_level'] + $item['happiness_boost']);
        
        // Update pet stats
        $stmt = $pdo->prepare("
            UPDATE pet_stats 
            SET hunger_level = ?, happiness_level = ?, updated_at = NOW()
            WHERE pet_id = ?
        ");
        $stmt->execute([$newHunger, $newHappiness, $petId]);
        
        // Record interaction
        $stmt = $pdo->prepare("
            INSERT INTO pet_interactions 
            (pet_id, user_id, interaction_type, item_id, happiness_gained, hunger_restored) 
            VALUES (?, ?, 'feed', ?, ?, ?)
        ");
        $stmt->execute([
            $petId, 
            $userId, 
            $itemId, 
            $item['happiness_boost'], 
            $item['hunger_restore']
        ]);
        
        // Decrease item quantity in inventory
        $newQuantity = $inventory['quantity'] - 1;
        if ($newQuantity > 0) {
            $stmt = $pdo->prepare("UPDATE user_inventory SET quantity = ? WHERE user_id = ? AND item_id = ?");
            $stmt->execute([$newQuantity, $userId, $itemId]);
        } else {
            $stmt = $pdo->prepare("DELETE FROM user_inventory WHERE user_id = ? AND item_id = ?");
            $stmt->execute([$userId, $itemId]);
        }
        
        $pdo->commit();
        
        return [
            'success' => true,
            'message' => "Used {$item['name']} on pet successfully!",
            'pet_stats' => [
                'hunger_level' => $newHunger,
                'happiness_level' => $newHappiness,
                'hunger_gained' => $item['hunger_restore'],
                'happiness_gained' => $item['happiness_boost']
            ],
            'remaining_quantity' => $newQuantity
        ];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}
?>
