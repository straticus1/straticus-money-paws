<?php
/**
 * Money Paws - Creator Economy System  
 * Asset marketplace, subscription boxes, NFT integration
 * Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>
 */

require_once 'functions.php';
require_once 'professional_features.php';

// Asset Marketplace System
function createMarketplaceAsset($creator_id, $asset_data) {
    $pdo = get_db();
    
    try {
        $pdo->beginTransaction();
        
        // Validate creator eligibility
        if (!canCreateMarketplaceAssets($creator_id)) {
            throw new Exception('You need more reputation to sell assets in the marketplace');
        }
        
        // Validate asset data
        $required_fields = ['title', 'description', 'asset_type', 'price_coins', 'category'];
        foreach ($required_fields as $field) {
            if (empty($asset_data[$field])) {
                throw new Exception("Missing required field: $field");
            }
        }
        
        // Asset type validation
        $valid_types = ['pet_accessory', 'environment_object', 'texture', 'animation', 'sound', 'background'];
        if (!in_array($asset_data['asset_type'], $valid_types)) {
            throw new Exception('Invalid asset type');
        }
        
        // Create asset
        $stmt = $pdo->prepare("
            INSERT INTO marketplace_assets 
            (creator_id, title, description, asset_type, category, 
             price_coins, file_path, preview_image, tags, license_type,
             created_at, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'pending_review')
        ");
        
        $stmt->execute([
            $creator_id,
            $asset_data['title'],
            $asset_data['description'],
            $asset_data['asset_type'],
            $asset_data['category'],
            min($asset_data['price_coins'], 500), // Max 500 coins
            $asset_data['file_path'] ?? '',
            $asset_data['preview_image'] ?? '',
            json_encode($asset_data['tags'] ?? []),
            $asset_data['license_type'] ?? 'personal_use'
        ]);
        
        $asset_id = $pdo->lastInsertId();
        
        // Award creation bonus
        awardCareCoins($creator_id, 20, "Listed asset in marketplace: {$asset_data['title']}");
        
        $pdo->commit();
        return ['success' => true, 'asset_id' => $asset_id, 'message' => 'Asset submitted for review!'];
        
    } catch (Exception $e) {
        $pdo->rollback();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function canCreateMarketplaceAssets($user_id) {
    $reputation = getUserReputationScore($user_id);
    $min_reputation = 150; // Minimum reputation for marketplace selling
    
    // Check if user has completed creative modules
    $creative_progress = getUserCreativeProgress($user_id);
    
    return $reputation >= $min_reputation && $creative_progress >= 3;
}

function getMarketplaceAssets($category = null, $asset_type = null, $sort = 'popularity', $limit = 20) {
    $pdo = get_db();
    
    $sql = "
        SELECT ma.*, u.name as creator_name,
               COUNT(mas.id) as sales_count,
               AVG(mar.rating) as avg_rating,
               COUNT(mar.id) as review_count
        FROM marketplace_assets ma
        JOIN users u ON ma.creator_id = u.id
        LEFT JOIN marketplace_asset_sales mas ON ma.id = mas.asset_id
        LEFT JOIN marketplace_asset_reviews mar ON ma.id = mar.asset_id
        WHERE ma.status = 'approved' AND ma.is_active = 1
    ";
    
    $params = [];
    
    if ($category) {
        $sql .= " AND ma.category = ?";
        $params[] = $category;
    }
    
    if ($asset_type) {
        $sql .= " AND ma.asset_type = ?";
        $params[] = $asset_type;
    }
    
    $sql .= " GROUP BY ma.id";
    
    // Sort options
    switch ($sort) {
        case 'popularity':
            $sql .= " ORDER BY sales_count DESC, avg_rating DESC";
            break;
        case 'newest':
            $sql .= " ORDER BY ma.created_at DESC";
            break;
        case 'price_low':
            $sql .= " ORDER BY ma.price_coins ASC";
            break;
        case 'price_high':
            $sql .= " ORDER BY ma.price_coins DESC";
            break;
        case 'rating':
            $sql .= " ORDER BY avg_rating DESC, review_count DESC";
            break;
        default:
            $sql .= " ORDER BY sales_count DESC";
    }
    
    $sql .= " LIMIT ?";
    $params[] = $limit;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function purchaseMarketplaceAsset($buyer_id, $asset_id) {
    $pdo = get_db();
    
    try {
        $pdo->beginTransaction();
        
        // Get asset details
        $stmt = $pdo->prepare("
            SELECT ma.*, u.name as creator_name
            FROM marketplace_assets ma
            JOIN users u ON ma.creator_id = u.id
            WHERE ma.id = ? AND ma.status = 'approved' AND ma.is_active = 1
        ");
        $stmt->execute([$asset_id]);
        $asset = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$asset) {
            throw new Exception('Asset not found or not available');
        }
        
        // Check if user already owns this asset
        $stmt = $pdo->prepare("
            SELECT id FROM marketplace_asset_sales 
            WHERE buyer_id = ? AND asset_id = ?
        ");
        $stmt->execute([$buyer_id, $asset_id]);
        if ($stmt->fetch()) {
            throw new Exception('You already own this asset');
        }
        
        // Check buyer has enough Care Coins
        $user = getUserById($buyer_id);
        if ($user['care_coins'] < $asset['price_coins']) {
            throw new Exception('Insufficient Care Coins');
        }
        
        // Process purchase
        // Deduct coins from buyer
        deductCareCoins($buyer_id, $asset['price_coins'], "Purchased marketplace asset: {$asset['title']}");
        
        // Pay creator (85% revenue share)
        $creator_earnings = floor($asset['price_coins'] * 0.85);
        $platform_fee = $asset['price_coins'] - $creator_earnings;
        
        awardCareCoins($asset['creator_id'], $creator_earnings, "Asset sale: {$asset['title']}");
        
        // Record sale
        $stmt = $pdo->prepare("
            INSERT INTO marketplace_asset_sales 
            (asset_id, buyer_id, creator_id, price_paid, creator_earnings, platform_fee, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $asset_id, $buyer_id, $asset['creator_id'], 
            $asset['price_coins'], $creator_earnings, $platform_fee
        ]);
        
        // Add asset to buyer's inventory
        addAssetToUserInventory($buyer_id, $asset_id);
        
        // Update asset popularity
        updateAssetPopularity($asset_id);
        
        $pdo->commit();
        return [
            'success' => true, 
            'message' => "Asset purchased! Check your inventory to use it.",
            'coins_spent' => $asset['price_coins']
        ];
        
    } catch (Exception $e) {
        $pdo->rollback();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// Subscription Box System
function createSubscriptionBox($creator_id, $box_data) {
    $pdo = get_db();
    
    try {
        $pdo->beginTransaction();
        
        // Validate creator eligibility
        if (!canCreateSubscriptionBoxes($creator_id)) {
            throw new Exception('You need premium tier to create subscription boxes');
        }
        
        // Create subscription box
        $stmt = $pdo->prepare("
            INSERT INTO subscription_boxes 
            (creator_id, title, description, theme, price_coins, 
             items_per_box, delivery_schedule, created_at, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), 1)
        ");
        
        $stmt->execute([
            $creator_id,
            $box_data['title'],
            $box_data['description'],
            $box_data['theme'],
            $box_data['price_coins'],
            $box_data['items_per_box'] ?? 5,
            $box_data['delivery_schedule'] ?? 'monthly'
        ]);
        
        $box_id = $pdo->lastInsertId();
        
        // Add initial items to the box
        if (!empty($box_data['initial_items'])) {
            foreach ($box_data['initial_items'] as $item) {
                addItemToSubscriptionBox($box_id, $item);
            }
        }
        
        $pdo->commit();
        return ['success' => true, 'box_id' => $box_id];
        
    } catch (Exception $e) {
        $pdo->rollback();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function canCreateSubscriptionBoxes($user_id) {
    $tier = getUserTierLevel($user_id);
    return in_array($tier['level'], ['premium', 'enterprise']);
}

function subscribeToBox($user_id, $box_id) {
    $pdo = get_db();
    
    try {
        $pdo->beginTransaction();
        
        // Get box details
        $box = getSubscriptionBox($box_id);
        if (!$box || !$box['is_active']) {
            throw new Exception('Subscription box not available');
        }
        
        // Check if already subscribed
        $stmt = $pdo->prepare("
            SELECT id FROM subscription_box_subscriptions 
            WHERE user_id = ? AND box_id = ? AND is_active = 1
        ");
        $stmt->execute([$user_id, $box_id]);
        if ($stmt->fetch()) {
            throw new Exception('Already subscribed to this box');
        }
        
        // Check Care Coins balance
        $user = getUserById($user_id);
        if ($user['care_coins'] < $box['price_coins']) {
            throw new Exception('Insufficient Care Coins for subscription');
        }
        
        // Create subscription
        $stmt = $pdo->prepare("
            INSERT INTO subscription_box_subscriptions 
            (box_id, user_id, subscription_date, next_delivery_date, is_active)
            VALUES (?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 1 MONTH), 1)
        ");
        $stmt->execute([$box_id, $user_id]);
        
        // Process first payment
        deductCareCoins($user_id, $box['price_coins'], "Subscription to: {$box['title']}");
        
        // Pay creator (70% for subscriptions due to ongoing curation)
        $creator_earnings = floor($box['price_coins'] * 0.70);
        awardCareCoins($box['creator_id'], $creator_earnings, "Subscription sale: {$box['title']}");
        
        // Schedule first delivery
        scheduleSubscriptionDelivery($user_id, $box_id);
        
        $pdo->commit();
        return ['success' => true, 'message' => "Subscribed! Your first box will arrive soon."];
        
    } catch (Exception $e) {
        $pdo->rollback();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// NFT Integration System
function mintPetNFT($user_id, $pet_id, $nft_data) {
    $tier = getUserTierLevel($user_id);
    if (!in_array('nft_minting', $tier['features'] ?? [])) {
        return ['success' => false, 'message' => 'NFT minting requires Premium tier or higher'];
    }
    
    $pdo = get_db();
    
    try {
        $pdo->beginTransaction();
        
        // Verify pet ownership
        $pet = getPetById($pet_id);
        if (!$pet || $pet['user_id'] != $user_id) {
            throw new Exception('Pet not found or not owned by user');
        }
        
        // Check if pet is already minted as NFT
        $stmt = $pdo->prepare("SELECT id FROM pet_nfts WHERE pet_id = ?");
        $stmt->execute([$pet_id]);
        if ($stmt->fetch()) {
            throw new Exception('This pet is already minted as an NFT');
        }
        
        // Mint cost (premium feature)
        $mint_cost = 100; // 100 Care Coins to mint
        $user_data = getUserById($user_id);
        if ($user_data['care_coins'] < $mint_cost) {
            throw new Exception('Insufficient Care Coins for minting');
        }
        
        // Generate unique NFT metadata
        $nft_metadata = generateNFTMetadata($pet, $nft_data);
        
        // Create NFT record
        $stmt = $pdo->prepare("
            INSERT INTO pet_nfts 
            (pet_id, owner_id, token_id, metadata_json, blockchain_address, 
             mint_transaction_hash, minted_at, is_tradeable)
            VALUES (?, ?, ?, ?, ?, ?, NOW(), 1)
        ");
        
        $token_id = generateUniqueTokenId();
        
        $stmt->execute([
            $pet_id,
            $user_id,
            $token_id,
            json_encode($nft_metadata),
            '', // Placeholder for blockchain integration
            '', // Placeholder for transaction hash
        ]);
        
        $nft_id = $pdo->lastInsertId();
        
        // Deduct minting cost
        deductCareCoins($user_id, $mint_cost, "Minted NFT for pet: {$pet['original_name']}");
        
        // Mark pet as NFT
        $stmt = $pdo->prepare("UPDATE pets SET is_nft = 1 WHERE id = ?");
        $stmt->execute([$pet_id]);
        
        $pdo->commit();
        return [
            'success' => true,
            'nft_id' => $nft_id,
            'token_id' => $token_id,
            'message' => 'Pet successfully minted as NFT!'
        ];
        
    } catch (Exception $e) {
        $pdo->rollback();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function listNFTForSale($user_id, $nft_id, $price_coins) {
    $pdo = get_db();
    
    try {
        $pdo->beginTransaction();
        
        // Verify NFT ownership
        $stmt = $pdo->prepare("
            SELECT pn.*, p.original_name 
            FROM pet_nfts pn
            JOIN pets p ON pn.pet_id = p.id
            WHERE pn.id = ? AND pn.owner_id = ?
        ");
        $stmt->execute([$nft_id, $user_id]);
        $nft = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$nft) {
            throw new Exception('NFT not found or not owned by user');
        }
        
        if (!$nft['is_tradeable']) {
            throw new Exception('This NFT is not tradeable');
        }
        
        // Check if already listed
        $stmt = $pdo->prepare("
            SELECT id FROM nft_marketplace_listings 
            WHERE nft_id = ? AND is_active = 1
        ");
        $stmt->execute([$nft_id]);
        if ($stmt->fetch()) {
            throw new Exception('NFT is already listed for sale');
        }
        
        // Create marketplace listing
        $stmt = $pdo->prepare("
            INSERT INTO nft_marketplace_listings 
            (nft_id, seller_id, price_coins, listed_at, is_active)
            VALUES (?, ?, ?, NOW(), 1)
        ");
        $stmt->execute([$nft_id, $user_id, $price_coins]);
        
        $pdo->commit();
        return ['success' => true, 'message' => "NFT listed for {$price_coins} Care Coins!"];
        
    } catch (Exception $e) {
        $pdo->rollback();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// Creator Revenue Analytics
function getCreatorEarnings($creator_id, $period = '30_days') {
    $pdo = get_db();
    
    $date_filter = match($period) {
        '7_days' => 'DATE_SUB(NOW(), INTERVAL 7 DAY)',
        '30_days' => 'DATE_SUB(NOW(), INTERVAL 30 DAY)',
        '90_days' => 'DATE_SUB(NOW(), INTERVAL 90 DAY)',
        '1_year' => 'DATE_SUB(NOW(), INTERVAL 1 YEAR)',
        default => 'DATE_SUB(NOW(), INTERVAL 30 DAY)'
    };
    
    // Asset sales earnings
    $stmt = $pdo->prepare("
        SELECT 
            'asset_sales' as revenue_type,
            SUM(creator_earnings) as earnings,
            COUNT(*) as transaction_count
        FROM marketplace_asset_sales
        WHERE creator_id = ? AND created_at >= $date_filter
        
        UNION ALL
        
        SELECT 
            'subscription_boxes' as revenue_type,
            SUM(creator_earnings) as earnings,
            COUNT(*) as transaction_count
        FROM subscription_box_payments
        WHERE creator_id = ? AND created_at >= $date_filter
        
        UNION ALL
        
        SELECT 
            'nft_sales' as revenue_type,
            SUM(seller_earnings) as earnings,
            COUNT(*) as transaction_count
        FROM nft_sales_transactions
        WHERE seller_id = ? AND created_at >= $date_filter
    ");
    
    $stmt->execute([$creator_id, $creator_id, $creator_id]);
    $revenue_breakdown = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate totals
    $total_earnings = 0;
    $total_transactions = 0;
    
    foreach ($revenue_breakdown as $revenue) {
        $total_earnings += $revenue['earnings'] ?? 0;
        $total_transactions += $revenue['transaction_count'] ?? 0;
    }
    
    return [
        'total_earnings' => $total_earnings,
        'total_transactions' => $total_transactions,
        'revenue_breakdown' => $revenue_breakdown,
        'period' => $period
    ];
}

function getCreatorStats($creator_id) {
    $pdo = get_db();
    
    // Assets created
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total_assets, 
               SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_assets
        FROM marketplace_assets 
        WHERE creator_id = ?
    ");
    $stmt->execute([$creator_id]);
    $asset_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Subscription boxes
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total_boxes,
               SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_boxes
        FROM subscription_boxes
        WHERE creator_id = ?
    ");
    $stmt->execute([$creator_id]);
    $box_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Total sales
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total_sales, SUM(creator_earnings) as total_earnings
        FROM marketplace_asset_sales
        WHERE creator_id = ?
    ");
    $stmt->execute([$creator_id]);
    $sales_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return [
        'assets' => $asset_stats,
        'subscription_boxes' => $box_stats,
        'sales' => $sales_stats,
        'creator_level' => getCreatorLevel($creator_id)
    ];
}

// Helper Functions
function getUserCreativeProgress($user_id) {
    $pdo = get_db();
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM educational_module_attempts 
        WHERE user_id = ? AND completed = 1 AND passed = 1
        AND module_id IN (SELECT id FROM educational_modules WHERE category = 'creative')
    ");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['count'];
}

function addAssetToUserInventory($user_id, $asset_id) {
    $pdo = get_db();
    $stmt = $pdo->prepare("
        INSERT INTO user_marketplace_inventory (user_id, asset_id, acquired_at)
        VALUES (?, ?, NOW())
    ");
    $stmt->execute([$user_id, $asset_id]);
}

function updateAssetPopularity($asset_id) {
    $pdo = get_db();
    $stmt = $pdo->prepare("
        UPDATE marketplace_assets 
        SET popularity_score = popularity_score + 1
        WHERE id = ?
    ");
    $stmt->execute([$asset_id]);
}

function getSubscriptionBox($box_id) {
    $pdo = get_db();
    $stmt = $pdo->prepare("
        SELECT sb.*, u.name as creator_name,
               COUNT(sbs.id) as subscriber_count
        FROM subscription_boxes sb
        JOIN users u ON sb.creator_id = u.id
        LEFT JOIN subscription_box_subscriptions sbs ON sb.id = sbs.box_id AND sbs.is_active = 1
        WHERE sb.id = ?
        GROUP BY sb.id
    ");
    $stmt->execute([$box_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function addItemToSubscriptionBox($box_id, $item_data) {
    $pdo = get_db();
    $stmt = $pdo->prepare("
        INSERT INTO subscription_box_items 
        (box_id, item_type, item_reference_id, rarity, added_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $box_id,
        $item_data['type'],
        $item_data['reference_id'],
        $item_data['rarity'] ?? 'common'
    ]);
}

function scheduleSubscriptionDelivery($user_id, $box_id) {
    // This would integrate with a background job system
    // For now, we'll just log it
    error_log("Scheduled subscription delivery for user $user_id, box $box_id");
}

function generateNFTMetadata($pet, $nft_data) {
    return [
        'name' => $pet['original_name'],
        'description' => $nft_data['description'] ?? "Unique digital pet from Money Paws",
        'image' => "/uploads/{$pet['filename']}",
        'attributes' => [
            ['trait_type' => 'Species', 'value' => $pet['species'] ?? 'Unknown'],
            ['trait_type' => 'Breed', 'value' => $pet['breed'] ?? 'Mixed'],
            ['trait_type' => 'Gender', 'value' => ucfirst($pet['gender'])],
            ['trait_type' => 'Birth Date', 'value' => $pet['birth_date']],
            ['trait_type' => 'Life Status', 'value' => ucfirst($pet['life_status'])],
        ],
        'external_url' => "https://paws.money/pet.php?id={$pet['id']}",
        'minted_by' => 'Money Paws Platform',
        'minting_date' => date('Y-m-d H:i:s')
    ];
}

function generateUniqueTokenId() {
    return 'PAWS_' . strtoupper(uniqid()) . '_' . random_int(1000, 9999);
}

function getCreatorLevel($creator_id) {
    $stats = getCreatorStats($creator_id);
    $earnings = $stats['sales']['total_earnings'] ?? 0;
    
    if ($earnings >= 10000) return 'Master Creator';
    if ($earnings >= 5000) return 'Expert Creator';
    if ($earnings >= 1000) return 'Experienced Creator';
    if ($earnings >= 100) return 'Creator';
    return 'New Creator';
}

function deductCareCoins($user_id, $amount, $reason) {
    $pdo = get_db();
    
    // Update user balance
    $stmt = $pdo->prepare("UPDATE users SET care_coins = care_coins - ? WHERE id = ?");
    $stmt->execute([$amount, $user_id]);
    
    // Record transaction
    $stmt = $pdo->prepare("
        INSERT INTO care_coin_transactions (user_id, amount, transaction_type, reason, created_at)
        VALUES (?, ?, 'spent', ?, NOW())
    ");
    $stmt->execute([$user_id, $amount, $reason]);
}

?>