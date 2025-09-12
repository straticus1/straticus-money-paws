<?php
/**
 * Money Paws - Daily Quests & Earned Currency System
 * Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>
 */

require_once 'functions.php';
require_once 'security.php';

// Earned currency constants
define('CARE_COIN_DAILY_LIMIT', 100);
define('CARE_COIN_PER_QUEST', 10);
define('CARE_COIN_CONVERSION_RATE', 0.01); // 100 care coins = 1 USD equivalent

/**
 * Get available daily quests for a user
 */
function getDailyQuests($user_id) {
    $pdo = get_db();
    if (!$pdo) return [];
    
    $today = date('Y-m-d');
    
    // Check if user already has quests for today
    $stmt = $pdo->prepare("
        SELECT * FROM daily_quests 
        WHERE user_id = ? AND quest_date = ?
        ORDER BY quest_type
    ");
    $stmt->execute([$user_id, $today]);
    $existing_quests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($existing_quests)) {
        // Generate new daily quests
        $quest_templates = getQuestTemplates();
        $daily_quests = [];
        
        // Select 5 random quest types for today
        $selected_quests = array_rand($quest_templates, min(5, count($quest_templates)));
        if (!is_array($selected_quests)) $selected_quests = [$selected_quests];
        
        foreach ($selected_quests as $quest_key) {
            $template = $quest_templates[$quest_key];
            
            $stmt = $pdo->prepare("
                INSERT INTO daily_quests 
                (user_id, quest_date, quest_type, title, description, target_value, reward_care_coins, reward_type)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $user_id,
                $today,
                $quest_key,
                $template['title'],
                $template['description'],
                $template['target'],
                $template['reward_coins'],
                $template['reward_type']
            ]);
            
            $daily_quests[] = [
                'id' => $pdo->lastInsertId(),
                'quest_type' => $quest_key,
                'title' => $template['title'],
                'description' => $template['description'],
                'target_value' => $template['target'],
                'current_progress' => 0,
                'completed' => false,
                'reward_care_coins' => $template['reward_coins'],
                'reward_type' => $template['reward_type']
            ];
        }
        
        return $daily_quests;
    }
    
    return $existing_quests;
}

/**
 * Quest templates for different activities
 */
function getQuestTemplates() {
    return [
        'feed_pets' => [
            'title' => 'Caring Caretaker',
            'description' => 'Feed your pets 3 times today',
            'target' => 3,
            'reward_coins' => 15,
            'reward_type' => 'care_coins'
        ],
        'visit_friends' => [
            'title' => 'Social Butterfly',
            'description' => 'Visit 2 friends\' pet galleries',
            'target' => 2,
            'reward_coins' => 10,
            'reward_type' => 'care_coins'
        ],
        'help_community' => [
            'title' => 'Community Helper',
            'description' => 'Help care for 1 abandoned pet',
            'target' => 1,
            'reward_coins' => 20,
            'reward_type' => 'care_coins'
        ],
        'learn_something' => [
            'title' => 'Knowledge Seeker',
            'description' => 'Complete 1 educational module',
            'target' => 1,
            'reward_coins' => 25,
            'reward_type' => 'care_coins'
        ],
        'creative_time' => [
            'title' => 'Creative Spirit',
            'description' => 'Upload or customize 1 pet image',
            'target' => 1,
            'reward_coins' => 12,
            'reward_type' => 'care_coins'
        ],
        'social_interaction' => [
            'title' => 'Friend Maker',
            'description' => 'Send 3 positive messages to other users',
            'target' => 3,
            'reward_coins' => 15,
            'reward_type' => 'care_coins'
        ],
        'pet_training' => [
            'title' => 'Pet Trainer',
            'description' => 'Play skill-building games with your pets',
            'target' => 2,
            'reward_coins' => 18,
            'reward_type' => 'care_coins'
        ]
    ];
}

/**
 * Update quest progress
 */
function updateQuestProgress($user_id, $quest_type, $increment = 1) {
    $pdo = get_db();
    if (!$pdo) return false;
    
    $today = date('Y-m-d');
    
    $stmt = $pdo->prepare("
        SELECT * FROM daily_quests 
        WHERE user_id = ? AND quest_date = ? AND quest_type = ? AND completed = 0
    ");
    $stmt->execute([$user_id, $today, $quest_type]);
    $quest = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($quest) {
        $new_progress = $quest['current_progress'] + $increment;
        $completed = ($new_progress >= $quest['target_value']);
        
        $stmt = $pdo->prepare("
            UPDATE daily_quests 
            SET current_progress = ?, completed = ?, completed_at = ?
            WHERE id = ?
        ");
        
        $stmt->execute([
            $new_progress,
            $completed ? 1 : 0,
            $completed ? date('Y-m-d H:i:s') : null,
            $quest['id']
        ]);
        
        if ($completed) {
            // Award care coins
            awardCareCoins($user_id, $quest['reward_care_coins'], "Daily quest: {$quest['title']}");
            
            // Create notification
            createNotification($user_id, 'quest_completed', [
                'title' => 'Quest Completed!',
                'message' => "You earned {$quest['reward_care_coins']} Care Coins for completing: {$quest['title']}",
                'icon' => '🎯'
            ]);
            
            return ['completed' => true, 'reward' => $quest['reward_care_coins']];
        }
        
        return ['completed' => false, 'progress' => $new_progress];
    }
    
    return false;
}

/**
 * Award care coins to user
 */
function awardCareCoins($user_id, $amount, $reason) {
    $pdo = get_db();
    if (!$pdo) return false;
    
    // Check daily limit
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(amount), 0) as today_earned
        FROM care_coin_transactions 
        WHERE user_id = ? AND DATE(created_at) = ? AND transaction_type = 'earned'
    ");
    $stmt->execute([$user_id, $today]);
    $today_earned = $stmt->fetchColumn();
    
    $remaining_limit = CARE_COIN_DAILY_LIMIT - $today_earned;
    $actual_award = min($amount, $remaining_limit);
    
    if ($actual_award > 0) {
        // Record transaction
        $stmt = $pdo->prepare("
            INSERT INTO care_coin_transactions 
            (user_id, amount, transaction_type, description, created_at)
            VALUES (?, ?, 'earned', ?, NOW())
        ");
        $stmt->execute([$user_id, $actual_award, $reason]);
        
        // Update user's care coin balance
        $stmt = $pdo->prepare("
            UPDATE users 
            SET care_coins = COALESCE(care_coins, 0) + ?
            WHERE id = ?
        ");
        $stmt->execute([$actual_award, $user_id]);
        
        return $actual_award;
    }
    
    return 0;
}

/**
 * Get user's care coin balance
 */
function getUserCareCoins($user_id) {
    $pdo = get_db();
    if (!$pdo) return 0;
    
    $stmt = $pdo->prepare("SELECT COALESCE(care_coins, 0) FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetchColumn() ?: 0;
}

/**
 * Spend care coins
 */
function spendCareCoins($user_id, $amount, $reason) {
    $pdo = get_db();
    if (!$pdo) return false;
    
    $current_balance = getUserCareCoins($user_id);
    
    if ($current_balance >= $amount) {
        // Record transaction
        $stmt = $pdo->prepare("
            INSERT INTO care_coin_transactions 
            (user_id, amount, transaction_type, description, created_at)
            VALUES (?, ?, 'spent', ?, NOW())
        ");
        $stmt->execute([$user_id, $amount, $reason]);
        
        // Update balance
        $stmt = $pdo->prepare("
            UPDATE users 
            SET care_coins = care_coins - ?
            WHERE id = ?
        ");
        $stmt->execute([$amount, $user_id]);
        
        return true;
    }
    
    return false;
}

/**
 * Get care coin store items
 */
function getCareCoinsStoreItems() {
    return [
        'pet_food' => [
            'name' => 'Premium Pet Food',
            'description' => 'High-quality food that restores 50 hunger points',
            'cost' => 15,
            'type' => 'consumable',
            'effect' => 'hunger_restore',
            'value' => 50
        ],
        'happiness_treat' => [
            'name' => 'Joy Treat',
            'description' => 'Special treat that boosts happiness by 30 points',
            'cost' => 12,
            'type' => 'consumable', 
            'effect' => 'happiness_boost',
            'value' => 30
        ],
        'pet_toy' => [
            'name' => 'Interactive Toy',
            'description' => 'Keeps pets entertained and happy',
            'cost' => 25,
            'type' => 'permanent',
            'effect' => 'happiness_multiplier',
            'value' => 1.1
        ],
        'care_package' => [
            'name' => 'Care Package',
            'description' => 'Bundle of food and treats for your pet',
            'cost' => 35,
            'type' => 'bundle',
            'contents' => ['pet_food' => 3, 'happiness_treat' => 2]
        ],
        'skill_lesson' => [
            'name' => 'Pet Training Session',
            'description' => 'Unlock new skills for your pet',
            'cost' => 40,
            'type' => 'upgrade',
            'effect' => 'skill_unlock'
        ]
    ];
}

/**
 * Create notification for user
 */
function createNotification($user_id, $type, $data) {
    $pdo = get_db();
    if (!$pdo) return false;
    
    $stmt = $pdo->prepare("
        INSERT INTO notifications 
        (user_id, type, title, message, icon, created_at, is_read)
        VALUES (?, ?, ?, ?, ?, NOW(), 0)
    ");
    
    return $stmt->execute([
        $user_id,
        $type,
        $data['title'],
        $data['message'],
        $data['icon'] ?? '📢'
    ]);
}

/**
 * Apply Care Coins item effects to user's pets
 */
function applyCareCoinsItemToPets($user_id, $item_id, $quantity) {
    $pdo = get_db();
    if (!$pdo) return false;
    
    $items = getCareCoinsStoreItems();
    if (!isset($items[$item_id])) return false;
    
    $item = $items[$item_id];
    
    // Get user's pets
    $stmt = $pdo->prepare("SELECT id FROM pets WHERE user_id = ? ORDER BY RAND() LIMIT 3");
    $stmt->execute([$user_id]);
    $pets = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($pets)) return false;
    
    foreach ($pets as $pet_id) {
        // Ensure pet stats exist
        $stmt = $pdo->prepare("INSERT IGNORE INTO pet_stats (pet_id) VALUES (?)");
        $stmt->execute([$pet_id]);
        
        // Apply effects based on item type
        switch ($item_id) {
            case 'pet_food':
                $stmt = $pdo->prepare("
                    UPDATE pet_stats 
                    SET hunger_level = LEAST(100, hunger_level + ?), 
                        last_fed = NOW(),
                        updated_at = NOW()
                    WHERE pet_id = ?
                ");
                $stmt->execute([$item['value'] * $quantity, $pet_id]);
                break;
                
            case 'happiness_treat':
                $stmt = $pdo->prepare("
                    UPDATE pet_stats 
                    SET happiness_level = LEAST(100, happiness_level + ?),
                        last_played = NOW(),
                        updated_at = NOW()
                    WHERE pet_id = ?
                ");
                $stmt->execute([$item['value'] * $quantity, $pet_id]);
                break;
                
            case 'pet_toy':
                // Permanent happiness multiplier effect
                $stmt = $pdo->prepare("
                    UPDATE pet_stats 
                    SET happiness_level = LEAST(100, happiness_level + 10),
                        updated_at = NOW()
                    WHERE pet_id = ?
                ");
                $stmt->execute([$pet_id]);
                break;
                
            case 'care_package':
                // Bundle: Apply food and treats
                $stmt = $pdo->prepare("
                    UPDATE pet_stats 
                    SET hunger_level = LEAST(100, hunger_level + 75),
                        happiness_level = LEAST(100, happiness_level + 60),
                        last_fed = NOW(),
                        last_played = NOW(),
                        updated_at = NOW()
                    WHERE pet_id = ?
                ");
                $stmt->execute([$pet_id]);
                break;
                
            case 'skill_lesson':
                // Unlock training achievements
                $stmt = $pdo->prepare("
                    UPDATE pet_stats 
                    SET happiness_level = LEAST(100, happiness_level + 15),
                        intelligence = LEAST(100, COALESCE(intelligence, 50) + 5),
                        updated_at = NOW()
                    WHERE pet_id = ?
                ");
                $stmt->execute([$pet_id]);
                break;
        }
    }
    
    // Record the purchase in inventory (simplified)
    $stmt = $pdo->prepare("
        INSERT INTO care_coin_transactions 
        (user_id, amount, transaction_type, description, created_at)
        VALUES (?, ?, 'spent', ?, NOW())
    ");
    
    $total_cost = $items[$item_id]['cost'] * $quantity;
    $stmt->execute([$user_id, -$total_cost, "Used {$quantity}x {$items[$item_id]['name']} on pets"]);
    
    return true;
}