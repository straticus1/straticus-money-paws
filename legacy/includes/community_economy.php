<?php
/**
 * Money Paws - Community Economy & Peer-to-Peer Pet Care System
 * Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>
 */

require_once 'functions.php';
require_once 'daily_quests.php';
require_once 'security.php';

/**
 * Get available pets for community care
 */
function getAvailableCommunityPets($user_id, $limit = 20) {
    $pdo = get_db();
    if (!$pdo) return [];
    
    $stmt = $pdo->prepare("
        SELECT p.*, u.name as owner_name, u.id as owner_id,
               ps.hunger_level, ps.happiness_level, ps.last_fed, ps.last_played,
               (SELECT COUNT(*) FROM community_pet_care cpc 
                WHERE cpc.pet_id = p.id AND cpc.caregiver_user_id = ? 
                AND DATE(cpc.cared_at) = CURDATE()) as today_care_count
        FROM pets p
        JOIN users u ON p.user_id = u.id
        LEFT JOIN pet_stats ps ON p.id = ps.pet_id
        WHERE p.user_id != ? 
        AND p.allows_community_care = 1
        AND (ps.hunger_level < 80 OR ps.happiness_level < 80 OR ps.last_fed < DATE_SUB(NOW(), INTERVAL 4 HOUR))
        HAVING today_care_count < 3
        ORDER BY ps.hunger_level ASC, ps.happiness_level ASC
        LIMIT ?
    ");
    
    $stmt->execute([$user_id, $user_id, $limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Provide care to a community pet
 */
function provideCommunityPetCare($caregiver_id, $pet_id, $care_type, $care_message = '') {
    $pdo = get_db();
    if (!$pdo) return ['success' => false, 'message' => 'Database unavailable'];
    
    // Validate care type
    $valid_care_types = ['feeding', 'playing', 'grooming', 'training'];
    if (!in_array($care_type, $valid_care_types)) {
        return ['success' => false, 'message' => 'Invalid care type'];
    }
    
    // Get pet and owner information
    $stmt = $pdo->prepare("
        SELECT p.*, u.name as owner_name, u.id as owner_id,
               ps.hunger_level, ps.happiness_level
        FROM pets p
        JOIN users u ON p.user_id = u.id
        LEFT JOIN pet_stats ps ON p.id = ps.pet_id
        WHERE p.id = ? AND p.allows_community_care = 1 AND p.user_id != ?
    ");
    $stmt->execute([$pet_id, $caregiver_id]);
    $pet = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$pet) {
        return ['success' => false, 'message' => 'Pet not available for community care'];
    }
    
    // Check daily care limit for this caregiver
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM community_pet_care 
        WHERE caregiver_user_id = ? AND pet_id = ? AND DATE(cared_at) = CURDATE()
    ");
    $stmt->execute([$caregiver_id, $pet_id]);
    $today_care_count = $stmt->fetchColumn();
    
    if ($today_care_count >= 3) {
        return ['success' => false, 'message' => 'You have already cared for this pet 3 times today'];
    }
    
    // Check global daily care limit for caregiver
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM community_pet_care 
        WHERE caregiver_user_id = ? AND DATE(cared_at) = CURDATE()
    ");
    $stmt->execute([$caregiver_id]);
    $global_care_count = $stmt->fetchColumn();
    
    if ($global_care_count >= 15) {
        return ['success' => false, 'message' => 'You have reached the daily community care limit'];
    }
    
    // Calculate care coins reward based on care type and pet need
    $care_coins_reward = calculateCommunityCareReward($care_type, $pet);
    
    try {
        $pdo->beginTransaction();
        
        // Record the community care
        $stmt = $pdo->prepare("
            INSERT INTO community_pet_care 
            (pet_id, caregiver_user_id, pet_owner_id, care_type, care_coins_earned, care_message, cared_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $pet_id,
            $caregiver_id,
            $pet['owner_id'],
            $care_type,
            $care_coins_reward,
            $care_message
        ]);
        
        // Apply care effects to pet
        applyCareEffectToPet($pet_id, $care_type);
        
        // Award care coins to caregiver
        awardCareCoins($caregiver_id, $care_coins_reward, "Community pet care: {$care_type}");
        
        // Update quest progress
        updateQuestProgress($caregiver_id, 'help_community');
        
        // Create notification for pet owner
        createNotification($pet['owner_id'], 'community_care', [
            'title' => 'Your Pet Received Care!',
            'message' => "A kind community member provided {$care_type} for {$pet['name']}. Your pet is happier!",
            'icon' => '🤝'
        ]);
        
        // Create notification for caregiver
        createNotification($caregiver_id, 'care_coins_earned', [
            'title' => 'Care Coins Earned!',
            'message' => "You earned {$care_coins_reward} Care Coins for helping {$pet['name']}!",
            'icon' => '💝'
        ]);
        
        $pdo->commit();
        
        return [
            'success' => true,
            'message' => "Thank you for caring for {$pet['name']}! You earned {$care_coins_reward} Care Coins.",
            'care_coins_earned' => $care_coins_reward,
            'pet_name' => $pet['name']
        ];
        
    } catch (Exception $e) {
        $pdo->rollback();
        return ['success' => false, 'message' => 'Failed to record community care'];
    }
}

/**
 * Calculate care coins reward based on care type and pet needs
 */
function calculateCommunityCareReward($care_type, $pet) {
    $base_rewards = [
        'feeding' => 8,
        'playing' => 6,
        'grooming' => 5,
        'training' => 10
    ];
    
    $base_reward = $base_rewards[$care_type] ?? 5;
    
    // Bonus for pets in greater need
    $bonus = 0;
    if ($pet['hunger_level'] < 30) $bonus += 3;
    if ($pet['happiness_level'] < 30) $bonus += 3;
    
    // Time since last care bonus
    $last_fed_time = strtotime($pet['last_fed'] ?? '1 week ago');
    $hours_since_care = (time() - $last_fed_time) / 3600;
    if ($hours_since_care > 12) $bonus += 2;
    if ($hours_since_care > 24) $bonus += 3;
    
    return $base_reward + $bonus;
}

/**
 * Apply care effects to pet stats
 */
function applyCareEffectToPet($pet_id, $care_type) {
    $pdo = get_db();
    if (!$pdo) return false;
    
    $effects = [
        'feeding' => ['hunger_level' => 25, 'happiness_level' => 5],
        'playing' => ['happiness_level' => 20, 'hunger_level' => -5],
        'grooming' => ['happiness_level' => 15],
        'training' => ['happiness_level' => 10, 'intelligence' => 1]
    ];
    
    $effect = $effects[$care_type] ?? [];
    
    if (empty($effect)) return false;
    
    // Build dynamic update query
    $updates = [];
    $values = [$pet_id];
    
    foreach ($effect as $stat => $change) {
        if ($change > 0) {
            $updates[] = "{$stat} = LEAST(100, COALESCE({$stat}, 50) + ?)";
        } else {
            $updates[] = "{$stat} = GREATEST(0, COALESCE({$stat}, 50) + ?)";
        }
        $values[] = abs($change);
    }
    
    if ($care_type === 'feeding') {
        $updates[] = "last_fed = NOW()";
    } else if ($care_type === 'playing') {
        $updates[] = "last_played = NOW()";
    }
    
    $updates[] = "updated_at = NOW()";
    
    $sql = "UPDATE pet_stats SET " . implode(', ', $updates) . " WHERE pet_id = ?";
    
    // Ensure pet stats record exists
    $stmt = $pdo->prepare("INSERT IGNORE INTO pet_stats (pet_id) VALUES (?)");
    $stmt->execute([$pet_id]);
    
    $stmt = $pdo->prepare($sql);
    return $stmt->execute($values);
}

/**
 * Get pet services offered by community members
 */
function getCommunityPetServices($location = null, $service_type = null, $limit = 20) {
    $pdo = get_db();
    if (!$pdo) return [];
    
    $where_conditions = ["ps.is_active = 1"];
    $params = [];
    
    if ($service_type) {
        $where_conditions[] = "ps.service_type = ?";
        $params[] = $service_type;
    }
    
    $where_sql = implode(' AND ', $where_conditions);
    
    $stmt = $pdo->prepare("
        SELECT ps.*, u.name as provider_name, u.profile_image,
               COALESCE(u.location, 'Location not specified') as provider_location,
               (SELECT COUNT(*) FROM pet_service_bookings psb 
                WHERE psb.service_id = ps.id AND psb.status = 'completed') as completed_bookings
        FROM pet_services ps
        JOIN users u ON ps.provider_user_id = u.id
        WHERE {$where_sql}
        ORDER BY ps.rating DESC, completed_bookings DESC, ps.created_at DESC
        LIMIT ?
    ");
    
    $params[] = $limit;
    $stmt->execute($params);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Create a new pet service offering
 */
function createPetService($provider_id, $service_data) {
    $pdo = get_db();
    if (!$pdo) return ['success' => false, 'message' => 'Database unavailable'];
    
    // Validate required fields
    $required_fields = ['service_type', 'title', 'description', 'cost_care_coins'];
    foreach ($required_fields as $field) {
        if (empty($service_data[$field])) {
            return ['success' => false, 'message' => "Missing required field: {$field}"];
        }
    }
    
    // Validate service type
    $valid_service_types = ['pet_sitting', 'training', 'grooming', 'breeding_assistance'];
    if (!in_array($service_data['service_type'], $valid_service_types)) {
        return ['success' => false, 'message' => 'Invalid service type'];
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO pet_services 
            (provider_user_id, service_type, title, description, cost_care_coins, 
             cost_crypto_amount, cost_crypto_type, availability_schedule, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $provider_id,
            $service_data['service_type'],
            $service_data['title'],
            $service_data['description'],
            $service_data['cost_care_coins'],
            $service_data['cost_crypto_amount'] ?? null,
            $service_data['cost_crypto_type'] ?? null,
            json_encode($service_data['availability_schedule'] ?? [])
        ]);
        
        $service_id = $pdo->lastInsertId();
        
        return [
            'success' => true,
            'message' => 'Pet service created successfully!',
            'service_id' => $service_id
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Failed to create service'];
    }
}

/**
 * Book a pet service
 */
function bookPetService($client_id, $service_id, $pet_id, $booking_data) {
    $pdo = get_db();
    if (!$pdo) return ['success' => false, 'message' => 'Database unavailable'];
    
    // Get service details
    $stmt = $pdo->prepare("
        SELECT ps.*, u.name as provider_name
        FROM pet_services ps
        JOIN users u ON ps.provider_user_id = u.id
        WHERE ps.id = ? AND ps.is_active = 1 AND ps.provider_user_id != ?
    ");
    $stmt->execute([$service_id, $client_id]);
    $service = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$service) {
        return ['success' => false, 'message' => 'Service not available'];
    }
    
    // Verify client owns the pet
    $stmt = $pdo->prepare("SELECT id, name FROM pets WHERE id = ? AND user_id = ?");
    $stmt->execute([$pet_id, $client_id]);
    $pet = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$pet) {
        return ['success' => false, 'message' => 'Pet not found or not owned by you'];
    }
    
    // Check payment method and availability
    $payment_type = $booking_data['payment_type'] ?? 'care_coins';
    
    if ($payment_type === 'care_coins') {
        $required_coins = $service['cost_care_coins'];
        $available_coins = getUserCareCoins($client_id);
        
        if ($available_coins < $required_coins) {
            return ['success' => false, 'message' => 'Insufficient Care Coins'];
        }
    }
    
    try {
        $pdo->beginTransaction();
        
        // Create booking
        $stmt = $pdo->prepare("
            INSERT INTO pet_service_bookings
            (service_id, client_user_id, pet_id, booking_date, booking_time, 
             duration_hours, payment_type, payment_amount_coins, payment_amount_crypto, 
             payment_crypto_type, special_instructions, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
        ");
        
        $stmt->execute([
            $service_id,
            $client_id,
            $pet_id,
            $booking_data['booking_date'],
            $booking_data['booking_time'],
            $booking_data['duration_hours'] ?? 1,
            $payment_type,
            $payment_type === 'care_coins' ? $service['cost_care_coins'] : null,
            $payment_type === 'crypto' ? $service['cost_crypto_amount'] : null,
            $payment_type === 'crypto' ? $service['cost_crypto_type'] : null,
            $booking_data['special_instructions'] ?? ''
        ]);
        
        $booking_id = $pdo->lastInsertId();
        
        // Hold payment (for care coins, deduct immediately; for crypto, handle separately)
        if ($payment_type === 'care_coins') {
            spendCareCoins($client_id, $service['cost_care_coins'], "Pet service booking: {$service['title']}");
        }
        
        // Notify service provider
        createNotification($service['provider_user_id'], 'service_booking', [
            'title' => 'New Service Booking!',
            'message' => "Someone booked your {$service['title']} service for {$pet['name']}",
            'icon' => '📅'
        ]);
        
        // Notify client
        createNotification($client_id, 'booking_confirmation', [
            'title' => 'Booking Confirmed!',
            'message' => "Your booking for {$service['title']} has been submitted",
            'icon' => '✅'
        ]);
        
        $pdo->commit();
        
        return [
            'success' => true,
            'message' => 'Service booked successfully!',
            'booking_id' => $booking_id
        ];
        
    } catch (Exception $e) {
        $pdo->rollback();
        return ['success' => false, 'message' => 'Failed to book service'];
    }
}

/**
 * Get community care history for a user (given or received)
 */
function getCommunityHistory($user_id, $type = 'both', $limit = 50) {
    $pdo = get_db();
    if (!$pdo) return [];
    
    $where_conditions = [];
    $params = [];
    
    if ($type === 'given') {
        $where_conditions[] = "cpc.caregiver_user_id = ?";
        $params[] = $user_id;
    } elseif ($type === 'received') {
        $where_conditions[] = "cpc.pet_owner_id = ?";
        $params[] = $user_id;
    } else {
        $where_conditions[] = "(cpc.caregiver_user_id = ? OR cpc.pet_owner_id = ?)";
        $params[] = $user_id;
        $params[] = $user_id;
    }
    
    $where_sql = implode(' AND ', $where_conditions);
    
    $stmt = $pdo->prepare("
        SELECT cpc.*, 
               p.name as pet_name, p.image_url as pet_image,
               u1.name as caregiver_name,
               u2.name as owner_name,
               CASE WHEN cpc.caregiver_user_id = ? THEN 'given' ELSE 'received' END as action_type
        FROM community_pet_care cpc
        JOIN pets p ON cpc.pet_id = p.id
        JOIN users u1 ON cpc.caregiver_user_id = u1.id
        JOIN users u2 ON cpc.pet_owner_id = u2.id
        WHERE {$where_sql}
        ORDER BY cpc.cared_at DESC
        LIMIT ?
    ");
    
    array_unshift($params, $user_id); // Add to beginning for the CASE statement
    $params[] = $limit;
    
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get community care statistics for dashboard
 */
function getCommunityStats($user_id) {
    $pdo = get_db();
    if (!$pdo) return [];
    
    // Care given stats
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_care_given,
            SUM(care_coins_earned) as total_coins_earned,
            COUNT(DISTINCT pet_id) as unique_pets_helped,
            COUNT(DISTINCT DATE(cared_at)) as active_days
        FROM community_pet_care 
        WHERE caregiver_user_id = ?
    ");
    $stmt->execute([$user_id]);
    $care_given = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Care received stats
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_care_received,
            COUNT(DISTINCT caregiver_user_id) as unique_helpers
        FROM community_pet_care 
        WHERE pet_owner_id = ?
    ");
    $stmt->execute([$user_id]);
    $care_received = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Community rank
    $stmt = $pdo->prepare("
        SELECT COUNT(*) + 1 as community_rank
        FROM (
            SELECT caregiver_user_id, COUNT(*) as care_count
            FROM community_pet_care 
            GROUP BY caregiver_user_id
            HAVING care_count > (
                SELECT COUNT(*) FROM community_pet_care WHERE caregiver_user_id = ?
            )
        ) ranking
    ");
    $stmt->execute([$user_id]);
    $rank_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return [
        'care_given' => $care_given,
        'care_received' => $care_received,
        'community_rank' => $rank_data['community_rank'] ?? 1,
        'care_coins_balance' => getUserCareCoins($user_id)
    ];
}

/**
 * Express appreciation for community care
 */
function expressAppreciation($pet_owner_id, $care_id, $appreciation_message = '') {
    $pdo = get_db();
    if (!$pdo) return ['success' => false, 'message' => 'Database unavailable'];
    
    // Verify the care record belongs to the owner's pet
    $stmt = $pdo->prepare("
        SELECT cpc.*, u.name as caregiver_name, p.name as pet_name
        FROM community_pet_care cpc
        JOIN users u ON cpc.caregiver_user_id = u.id
        JOIN pets p ON cpc.pet_id = p.id
        WHERE cpc.id = ? AND cpc.pet_owner_id = ? AND cpc.appreciation_given = 0
    ");
    $stmt->execute([$care_id, $pet_owner_id]);
    $care_record = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$care_record) {
        return ['success' => false, 'message' => 'Care record not found or appreciation already given'];
    }
    
    try {
        // Mark appreciation as given
        $stmt = $pdo->prepare("UPDATE community_pet_care SET appreciation_given = 1 WHERE id = ?");
        $stmt->execute([$care_id]);
        
        // Award bonus care coins to the caregiver
        $bonus_coins = 3;
        awardCareCoins($care_record['caregiver_user_id'], $bonus_coins, "Appreciation bonus from {$care_record['pet_name']}'s owner");
        
        // Create appreciation notification
        createNotification($care_record['caregiver_user_id'], 'appreciation', [
            'title' => 'Appreciation Received! 💝',
            'message' => "The owner of {$care_record['pet_name']} appreciated your care! You earned {$bonus_coins} bonus Care Coins.",
            'icon' => '💝'
        ]);
        
        return [
            'success' => true,
            'message' => "Thank you for expressing appreciation! {$care_record['caregiver_name']} received bonus Care Coins.",
            'bonus_awarded' => $bonus_coins
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Failed to record appreciation'];
    }
}