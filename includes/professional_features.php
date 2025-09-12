<?php
/**
 * Money Paws - Professional Tier Features
 * Advanced analytics, custom environments, priority support, and premium tools
 * Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>
 */

require_once 'functions.php';

// Professional Tier Management
function getUserTierLevel($user_id) {
    $pdo = get_db();
    $stmt = $pdo->prepare("
        SELECT tier_level, tier_expires_at, features_enabled
        FROM user_professional_tiers 
        WHERE user_id = ? AND (tier_expires_at IS NULL OR tier_expires_at > NOW())
    ");
    $stmt->execute([$user_id]);
    $tier = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $tier ? [
        'level' => $tier['tier_level'],
        'expires_at' => $tier['tier_expires_at'],
        'features' => json_decode($tier['features_enabled'], true) ?? []
    ] : [
        'level' => 'free',
        'expires_at' => null,
        'features' => []
    ];
}

function upgradeToProfessionalTier($user_id, $tier_level, $duration_months = 1) {
    $pdo = get_db();
    
    try {
        $pdo->beginTransaction();
        
        // Validate tier level
        $valid_tiers = ['professional', 'premium', 'enterprise'];
        if (!in_array($tier_level, $valid_tiers)) {
            throw new Exception('Invalid tier level');
        }
        
        // Calculate pricing
        $pricing = getTierPricing($tier_level, $duration_months);
        
        // Get enabled features for tier
        $features = getTierFeatures($tier_level);
        
        // Set expiration date
        $expires_at = date('Y-m-d H:i:s', strtotime("+{$duration_months} months"));
        
        // Update or insert tier record
        $stmt = $pdo->prepare("
            INSERT INTO user_professional_tiers 
            (user_id, tier_level, tier_expires_at, features_enabled, created_at)
            VALUES (?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
            tier_level = VALUES(tier_level),
            tier_expires_at = VALUES(tier_expires_at),
            features_enabled = VALUES(features_enabled)
        ");
        
        $stmt->execute([
            $user_id,
            $tier_level,
            $expires_at,
            json_encode($features)
        ]);
        
        // Create analytics profile if professional+
        if ($tier_level !== 'free') {
            createAdvancedAnalyticsProfile($user_id);
        }
        
        // Enable custom environment tools if premium+
        if (in_array($tier_level, ['premium', 'enterprise'])) {
            enableCustomEnvironmentTools($user_id);
        }
        
        // Create transaction record
        createTierTransaction($user_id, $tier_level, $pricing['total'], $duration_months);
        
        $pdo->commit();
        return ['success' => true, 'tier' => $tier_level, 'expires_at' => $expires_at];
        
    } catch (Exception $e) {
        $pdo->rollback();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function getTierPricing($tier_level, $duration_months = 1) {
    $monthly_prices = [
        'professional' => 9.99,
        'premium' => 19.99,
        'enterprise' => 49.99
    ];
    
    $monthly_price = $monthly_prices[$tier_level] ?? 0;
    
    // Discount for longer subscriptions
    $discount_multipliers = [
        1 => 1.0,      // No discount for 1 month
        3 => 0.95,     // 5% discount for 3 months
        6 => 0.90,     // 10% discount for 6 months
        12 => 0.80     // 20% discount for 12 months
    ];
    
    $multiplier = $discount_multipliers[$duration_months] ?? 1.0;
    $total = $monthly_price * $duration_months * $multiplier;
    
    return [
        'monthly_price' => $monthly_price,
        'duration_months' => $duration_months,
        'discount_multiplier' => $multiplier,
        'total' => round($total, 2)
    ];
}

function getTierFeatures($tier_level) {
    $features = [
        'free' => [],
        'professional' => [
            'advanced_analytics',
            'priority_support',
            'extended_storage',
            'custom_pet_backgrounds'
        ],
        'premium' => [
            'advanced_analytics',
            'priority_support',
            'extended_storage',
            'custom_pet_backgrounds',
            'custom_3d_environments',
            'breeding_analytics',
            'market_insights',
            'white_label_options'
        ],
        'enterprise' => [
            'advanced_analytics',
            'priority_support',
            'extended_storage',
            'custom_pet_backgrounds',
            'custom_3d_environments',
            'breeding_analytics',
            'market_insights',
            'white_label_options',
            'dedicated_support',
            'custom_integrations',
            'advanced_api_access',
            'team_management'
        ]
    ];
    
    return $features[$tier_level] ?? [];
}

// Advanced Analytics System
function createAdvancedAnalyticsProfile($user_id) {
    $pdo = get_db();
    
    // Initialize analytics tracking
    $stmt = $pdo->prepare("
        INSERT INTO user_analytics_profiles (user_id, created_at)
        VALUES (?, NOW())
        ON DUPLICATE KEY UPDATE updated_at = NOW()
    ");
    $stmt->execute([$user_id]);
    
    // Start collecting detailed metrics
    initializeAdvancedMetrics($user_id);
}

function initializeAdvancedMetrics($user_id) {
    $pdo = get_db();
    
    // Initialize metrics that require professional tier
    $advanced_metrics = [
        'pet_happiness_trends',
        'feeding_optimization',
        'community_impact_score',
        'earning_efficiency',
        'social_engagement_rate',
        'educational_progress_rate'
    ];
    
    foreach ($advanced_metrics as $metric) {
        $stmt = $pdo->prepare("
            INSERT INTO user_advanced_metrics 
            (user_id, metric_name, enabled_at)
            VALUES (?, ?, NOW())
            ON DUPLICATE KEY UPDATE enabled_at = NOW()
        ");
        $stmt->execute([$user_id, $metric]);
    }
}

function getAdvancedAnalytics($user_id, $date_range = '30_days') {
    $tier = getUserTierLevel($user_id);
    if ($tier['level'] === 'free') {
        return ['error' => 'Advanced analytics requires Professional tier or higher'];
    }
    
    $pdo = get_db();
    
    // Date range calculation
    $date_filter = match($date_range) {
        '7_days' => 'DATE_SUB(NOW(), INTERVAL 7 DAY)',
        '30_days' => 'DATE_SUB(NOW(), INTERVAL 30 DAY)',
        '90_days' => 'DATE_SUB(NOW(), INTERVAL 90 DAY)',
        '1_year' => 'DATE_SUB(NOW(), INTERVAL 1 YEAR)',
        default => 'DATE_SUB(NOW(), INTERVAL 30 DAY)'
    };
    
    $analytics = [];
    
    // Pet Health Trends
    $analytics['pet_health_trends'] = getPetHealthTrends($user_id, $date_filter);
    
    // Activity Patterns
    $analytics['activity_patterns'] = getUserActivityPatterns($user_id, $date_filter);
    
    // Earning Optimization
    $analytics['earning_insights'] = getEarningOptimizationInsights($user_id, $date_filter);
    
    // Community Impact
    $analytics['community_impact'] = getCommunityImpactAnalytics($user_id, $date_filter);
    
    // Social Engagement
    $analytics['social_engagement'] = getSocialEngagementMetrics($user_id, $date_filter);
    
    // Predictive Insights (Premium+ feature)
    if (in_array($tier['level'], ['premium', 'enterprise'])) {
        $analytics['predictive_insights'] = getPredictiveInsights($user_id, $date_filter);
        $analytics['market_trends'] = getMarketTrendAnalysis($user_id, $date_filter);
    }
    
    return $analytics;
}

function getPetHealthTrends($user_id, $date_filter) {
    $pdo = get_db();
    
    $stmt = $pdo->prepare("
        SELECT 
            p.original_name,
            p.id as pet_id,
            AVG(ph.health_points) as avg_health,
            AVG(ps.hunger_level) as avg_hunger,
            AVG(ps.happiness_level) as avg_happiness,
            COUNT(pi.id) as interaction_count,
            DATE(pi.created_at) as date
        FROM pets p
        JOIN pet_health ph ON p.id = ph.pet_id
        JOIN pet_stats ps ON p.id = ps.pet_id
        LEFT JOIN pet_interactions pi ON p.id = pi.pet_id AND pi.created_at >= $date_filter
        WHERE p.user_id = ?
        GROUP BY p.id, DATE(pi.created_at)
        ORDER BY date DESC
        LIMIT 100
    ");
    
    $stmt->execute([$user_id]);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process data for trend analysis
    $trends = [];
    foreach ($data as $row) {
        $pet_id = $row['pet_id'];
        if (!isset($trends[$pet_id])) {
            $trends[$pet_id] = [
                'name' => $row['original_name'],
                'health_trend' => [],
                'happiness_trend' => [],
                'interaction_trend' => []
            ];
        }
        
        $trends[$pet_id]['health_trend'][] = [
            'date' => $row['date'],
            'value' => floatval($row['avg_health'])
        ];
        $trends[$pet_id]['happiness_trend'][] = [
            'date' => $row['date'],
            'value' => floatval($row['avg_happiness'])
        ];
        $trends[$pet_id]['interaction_trend'][] = [
            'date' => $row['date'],
            'value' => intval($row['interaction_count'])
        ];
    }
    
    return $trends;
}

function getUserActivityPatterns($user_id, $date_filter) {
    $pdo = get_db();
    
    // Activity by hour of day
    $stmt = $pdo->prepare("
        SELECT 
            HOUR(created_at) as hour,
            COUNT(*) as activity_count,
            AVG(CASE WHEN interaction_type = 'feed' THEN 1 ELSE 0 END) as feed_rate,
            AVG(CASE WHEN interaction_type = 'play' THEN 1 ELSE 0 END) as play_rate
        FROM pet_interactions
        WHERE user_id = ? AND created_at >= $date_filter
        GROUP BY HOUR(created_at)
        ORDER BY hour
    ");
    
    $stmt->execute([$user_id]);
    $hourly_patterns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Activity by day of week
    $stmt = $pdo->prepare("
        SELECT 
            DAYOFWEEK(created_at) as day_of_week,
            DAYNAME(created_at) as day_name,
            COUNT(*) as activity_count,
            AVG(happiness_gained) as avg_happiness_impact
        FROM pet_interactions
        WHERE user_id = ? AND created_at >= $date_filter
        GROUP BY DAYOFWEEK(created_at), DAYNAME(created_at)
        ORDER BY day_of_week
    ");
    
    $stmt->execute([$user_id]);
    $daily_patterns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return [
        'hourly_patterns' => $hourly_patterns,
        'daily_patterns' => $daily_patterns,
        'insights' => generateActivityInsights($hourly_patterns, $daily_patterns)
    ];
}

function getEarningOptimizationInsights($user_id, $date_filter) {
    $pdo = get_db();
    
    // Care Coins earning breakdown
    $stmt = $pdo->prepare("
        SELECT 
            reason,
            SUM(amount) as total_earned,
            COUNT(*) as transaction_count,
            AVG(amount) as avg_per_transaction
        FROM care_coin_transactions
        WHERE user_id = ? AND transaction_type = 'earned' AND created_at >= $date_filter
        GROUP BY reason
        ORDER BY total_earned DESC
    ");
    
    $stmt->execute([$user_id]);
    $earning_breakdown = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Earning efficiency by activity
    $stmt = $pdo->prepare("
        SELECT 
            DATE(created_at) as date,
            SUM(amount) as daily_earnings,
            COUNT(DISTINCT reason) as earning_sources
        FROM care_coin_transactions
        WHERE user_id = ? AND transaction_type = 'earned' AND created_at >= $date_filter
        GROUP BY DATE(created_at)
        ORDER BY date DESC
    ");
    
    $stmt->execute([$user_id]);
    $daily_earnings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return [
        'earning_breakdown' => $earning_breakdown,
        'daily_earnings' => $daily_earnings,
        'optimization_tips' => generateEarningOptimizationTips($earning_breakdown)
    ];
}

function getCommunityImpactAnalytics($user_id, $date_filter) {
    $pdo = get_db();
    
    // Community contributions
    $stmt = $pdo->prepare("
        SELECT 
            'pet_care_help' as activity_type,
            COUNT(*) as count,
            SUM(care_coins_earned) as coins_earned
        FROM community_pet_care
        WHERE caregiver_id = ? AND created_at >= $date_filter
        
        UNION ALL
        
        SELECT 
            'quest_creation' as activity_type,
            COUNT(*) as count,
            0 as coins_earned
        FROM user_generated_quests
        WHERE creator_id = ? AND created_at >= $date_filter
        
        UNION ALL
        
        SELECT 
            'mentorship' as activity_type,
            COUNT(*) as count,
            0 as coins_earned
        FROM mentorship_relationships
        WHERE mentor_id = ? AND created_at >= $date_filter
    ");
    
    $stmt->execute([$user_id, $user_id, $user_id]);
    $contributions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Impact score calculation
    $impact_score = 0;
    foreach ($contributions as $contrib) {
        switch ($contrib['activity_type']) {
            case 'pet_care_help':
                $impact_score += $contrib['count'] * 5;
                break;
            case 'quest_creation':
                $impact_score += $contrib['count'] * 15;
                break;
            case 'mentorship':
                $impact_score += $contrib['count'] * 25;
                break;
        }
    }
    
    return [
        'contributions' => $contributions,
        'impact_score' => $impact_score,
        'community_rank' => getCommunityRank($user_id, $impact_score)
    ];
}

function getSocialEngagementMetrics($user_id, $date_filter) {
    $pdo = get_db();
    
    // Social activity metrics
    $stmt = $pdo->prepare("
        SELECT 
            'stories_created' as metric,
            COUNT(*) as value
        FROM pet_stories
        WHERE user_id = ? AND created_at >= $date_filter
        
        UNION ALL
        
        SELECT 
            'stories_liked' as metric,
            COUNT(*) as value
        FROM pet_story_likes psl
        JOIN pet_stories ps ON psl.story_id = ps.id
        WHERE psl.user_id = ? AND psl.created_at >= $date_filter
        
        UNION ALL
        
        SELECT 
            'contest_entries' as metric,
            COUNT(*) as value
        FROM photo_contest_entries
        WHERE user_id = ? AND created_at >= $date_filter
    ");
    
    $stmt->execute([$user_id, $user_id, $user_id]);
    $metrics = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Convert to associative array
    $engagement_data = [];
    foreach ($metrics as $metric) {
        $engagement_data[$metric['metric']] = $metric['value'];
    }
    
    // Calculate engagement score
    $engagement_score = 
        ($engagement_data['stories_created'] ?? 0) * 10 +
        ($engagement_data['stories_liked'] ?? 0) * 2 +
        ($engagement_data['contest_entries'] ?? 0) * 15;
    
    return [
        'metrics' => $engagement_data,
        'engagement_score' => $engagement_score,
        'engagement_level' => getEngagementLevel($engagement_score)
    ];
}

// Custom 3D Environments System
function enableCustomEnvironmentTools($user_id) {
    $pdo = get_db();
    
    // Create environment builder profile
    $stmt = $pdo->prepare("
        INSERT INTO custom_environment_profiles (user_id, max_environments, storage_limit_mb, created_at)
        VALUES (?, 10, 500, NOW())
        ON DUPLICATE KEY UPDATE 
        max_environments = VALUES(max_environments),
        storage_limit_mb = VALUES(storage_limit_mb)
    ");
    $stmt->execute([$user_id]);
    
    // Initialize with default environment templates
    initializeEnvironmentTemplates($user_id);
}

function createCustomEnvironment($user_id, $environment_data) {
    $tier = getUserTierLevel($user_id);
    if (!in_array('custom_3d_environments', $tier['features'])) {
        return ['success' => false, 'message' => 'Custom environments require Premium tier or higher'];
    }
    
    $pdo = get_db();
    
    try {
        $pdo->beginTransaction();
        
        // Check environment limits
        $current_count = getUserEnvironmentCount($user_id);
        $max_allowed = getMaxEnvironmentsForTier($tier['level']);
        
        if ($current_count >= $max_allowed) {
            throw new Exception("Environment limit reached. Max: $max_allowed");
        }
        
        // Validate environment data
        $required_fields = ['name', 'description', 'environment_type', 'theme'];
        foreach ($required_fields as $field) {
            if (empty($environment_data[$field])) {
                throw new Exception("Missing required field: $field");
            }
        }
        
        // Create environment
        $stmt = $pdo->prepare("
            INSERT INTO custom_environments 
            (user_id, name, description, environment_type, theme, 
             lighting_config, weather_config, objects_config, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $user_id,
            $environment_data['name'],
            $environment_data['description'],
            $environment_data['environment_type'],
            $environment_data['theme'],
            json_encode($environment_data['lighting'] ?? []),
            json_encode($environment_data['weather'] ?? []),
            json_encode($environment_data['objects'] ?? [])
        ]);
        
        $environment_id = $pdo->lastInsertId();
        
        // Award creation bonus
        awardCareCoins($user_id, 25, "Created custom environment: {$environment_data['name']}");
        
        $pdo->commit();
        return ['success' => true, 'environment_id' => $environment_id];
        
    } catch (Exception $e) {
        $pdo->rollback();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function getUserCustomEnvironments($user_id) {
    $pdo = get_db();
    
    $stmt = $pdo->prepare("
        SELECT ce.*, 
               COUNT(pev.id) as visit_count,
               MAX(pev.visited_at) as last_visited
        FROM custom_environments ce
        LEFT JOIN pet_environment_visits pev ON ce.id = pev.environment_id
        WHERE ce.user_id = ? AND ce.is_active = 1
        GROUP BY ce.id
        ORDER BY ce.created_at DESC
    ");
    
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Priority Support System
function createSupportTicket($user_id, $ticket_data) {
    $pdo = get_db();
    
    $tier = getUserTierLevel($user_id);
    $priority = getPriorityLevel($tier['level']);
    
    try {
        $pdo->beginTransaction();
        
        // Create ticket
        $stmt = $pdo->prepare("
            INSERT INTO support_tickets 
            (user_id, subject, description, priority, category, created_at, status)
            VALUES (?, ?, ?, ?, ?, NOW(), 'open')
        ");
        
        $stmt->execute([
            $user_id,
            $ticket_data['subject'],
            $ticket_data['description'],
            $priority,
            $ticket_data['category'] ?? 'general'
        ]);
        
        $ticket_id = $pdo->lastInsertId();
        
        // Add initial message
        $stmt = $pdo->prepare("
            INSERT INTO support_ticket_messages 
            (ticket_id, user_id, message, is_from_user, created_at)
            VALUES (?, ?, ?, 1, NOW())
        ");
        
        $stmt->execute([$ticket_id, $user_id, $ticket_data['description']]);
        
        // Send notification to support team
        notifySupportTeam($ticket_id, $priority);
        
        $pdo->commit();
        
        $response_time = getExpectedResponseTime($priority);
        return [
            'success' => true, 
            'ticket_id' => $ticket_id,
            'expected_response' => $response_time
        ];
        
    } catch (Exception $e) {
        $pdo->rollback();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function getPriorityLevel($tier_level) {
    $priority_map = [
        'enterprise' => 'critical',
        'premium' => 'high',
        'professional' => 'medium',
        'free' => 'low'
    ];
    
    return $priority_map[$tier_level] ?? 'low';
}

function getExpectedResponseTime($priority) {
    $response_times = [
        'critical' => '1 hour',
        'high' => '4 hours',
        'medium' => '12 hours',
        'low' => '48 hours'
    ];
    
    return $response_times[$priority] ?? '48 hours';
}

// Market Insights (Premium Feature)
function getMarketTrendAnalysis($user_id, $date_filter) {
    $tier = getUserTierLevel($user_id);
    if (!in_array($tier['level'], ['premium', 'enterprise'])) {
        return ['error' => 'Market insights require Premium tier or higher'];
    }
    
    $pdo = get_db();
    
    // Pet market trends
    $stmt = $pdo->prepare("
        SELECT 
            p.species,
            p.breed,
            COUNT(*) as adoption_count,
            AVG(CASE WHEN ps.id IS NOT NULL THEN ps.sale_price ELSE NULL END) as avg_sale_price,
            COUNT(ps.id) as sale_count
        FROM pets p
        LEFT JOIN pet_sales ps ON p.id = ps.pet_id AND ps.created_at >= $date_filter
        WHERE p.created_at >= $date_filter
        GROUP BY p.species, p.breed
        HAVING adoption_count > 5
        ORDER BY adoption_count DESC
        LIMIT 20
    ");
    
    $stmt->execute();
    $pet_trends = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Care Coins economy trends
    $stmt = $pdo->prepare("
        SELECT 
            DATE(created_at) as date,
            SUM(CASE WHEN transaction_type = 'earned' THEN amount ELSE 0 END) as total_earned,
            SUM(CASE WHEN transaction_type = 'spent' THEN amount ELSE 0 END) as total_spent,
            COUNT(DISTINCT user_id) as active_users
        FROM care_coin_transactions
        WHERE created_at >= $date_filter
        GROUP BY DATE(created_at)
        ORDER BY date DESC
        LIMIT 30
    ");
    
    $stmt->execute();
    $economy_trends = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return [
        'pet_trends' => $pet_trends,
        'economy_trends' => $economy_trends,
        'recommendations' => generateMarketRecommendations($pet_trends, $economy_trends)
    ];
}

// Helper Functions
function generateActivityInsights($hourly, $daily) {
    $insights = [];
    
    // Find peak activity hour
    $max_hour = 0;
    $max_activity = 0;
    foreach ($hourly as $hour_data) {
        if ($hour_data['activity_count'] > $max_activity) {
            $max_activity = $hour_data['activity_count'];
            $max_hour = $hour_data['hour'];
        }
    }
    
    $insights[] = "Your peak activity hour is " . date('g A', mktime($max_hour, 0, 0)) . " with {$max_activity} interactions.";
    
    // Find most active day
    $max_day = '';
    $max_day_activity = 0;
    foreach ($daily as $day_data) {
        if ($day_data['activity_count'] > $max_day_activity) {
            $max_day_activity = $day_data['activity_count'];
            $max_day = $day_data['day_name'];
        }
    }
    
    $insights[] = "You're most active on {$max_day}s with {$max_day_activity} interactions.";
    
    return $insights;
}

function generateEarningOptimizationTips($earning_breakdown) {
    $tips = [];
    
    if (empty($earning_breakdown)) {
        $tips[] = "Start earning Care Coins by completing daily quests and helping the community!";
        return $tips;
    }
    
    $top_earner = $earning_breakdown[0];
    $tips[] = "Your best earning activity is '{$top_earner['reason']}' - focus on this for maximum efficiency!";
    
    // Check for missed opportunities
    $daily_quests_found = false;
    $community_help_found = false;
    
    foreach ($earning_breakdown as $earning) {
        if (stripos($earning['reason'], 'daily quest') !== false) {
            $daily_quests_found = true;
        }
        if (stripos($earning['reason'], 'community') !== false) {
            $community_help_found = true;
        }
    }
    
    if (!$daily_quests_found) {
        $tips[] = "Try completing daily quests for consistent earning opportunities!";
    }
    
    if (!$community_help_found) {
        $tips[] = "Help care for community pets to earn extra Care Coins and build reputation!";
    }
    
    return $tips;
}

function getCommunityRank($user_id, $impact_score) {
    $pdo = get_db();
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) + 1 as rank
        FROM (
            SELECT user_id, SUM(impact_points) as total_impact
            FROM user_community_activities
            GROUP BY user_id
            HAVING total_impact > ?
        ) as higher_impact_users
    ");
    
    $stmt->execute([$impact_score]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result['rank'] ?? 1;
}

function getEngagementLevel($score) {
    if ($score >= 100) return 'Very High';
    if ($score >= 50) return 'High';
    if ($score >= 20) return 'Medium';
    if ($score >= 5) return 'Low';
    return 'Getting Started';
}

function getUserEnvironmentCount($user_id) {
    $pdo = get_db();
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM custom_environments WHERE user_id = ? AND is_active = 1");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['count'];
}

function getMaxEnvironmentsForTier($tier_level) {
    $limits = [
        'professional' => 3,
        'premium' => 10,
        'enterprise' => 50
    ];
    
    return $limits[$tier_level] ?? 0;
}

function createTierTransaction($user_id, $tier_level, $amount, $duration_months) {
    $pdo = get_db();
    
    $stmt = $pdo->prepare("
        INSERT INTO professional_tier_transactions 
        (user_id, tier_level, amount_usd, duration_months, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([$user_id, $tier_level, $amount, $duration_months]);
}

function notifySupportTeam($ticket_id, $priority) {
    // Implementation would notify support team via email, Slack, etc.
    // For now, we'll just log it
    error_log("New support ticket #$ticket_id with priority: $priority");
}

function initializeEnvironmentTemplates($user_id) {
    $pdo = get_db();
    
    $templates = [
        [
            'name' => 'Sunny Meadow',
            'description' => 'A peaceful meadow with gentle sunshine',
            'environment_type' => 'outdoor',
            'theme' => 'nature',
            'lighting' => ['type' => 'sunny', 'intensity' => 0.8],
            'weather' => ['type' => 'clear', 'wind' => 'gentle'],
            'objects' => ['trees', 'flowers', 'grass']
        ]
    ];
    
    foreach ($templates as $template) {
        createCustomEnvironment($user_id, $template);
    }
}

function generateMarketRecommendations($pet_trends, $economy_trends) {
    $recommendations = [];
    
    if (!empty($pet_trends)) {
        $trending_pet = $pet_trends[0];
        $recommendations[] = "📈 Trending: {$trending_pet['species']} {$trending_pet['breed']} pets are popular with {$trending_pet['adoption_count']} recent adoptions.";
    }
    
    if (!empty($economy_trends)) {
        $latest_trend = $economy_trends[0];
        $earning_vs_spending = $latest_trend['total_earned'] - $latest_trend['total_spent'];
        
        if ($earning_vs_spending > 0) {
            $recommendations[] = "💰 The community is earning more than spending - great economic health!";
        } else {
            $recommendations[] = "💸 More Care Coins are being spent than earned - consider creating earning opportunities.";
        }
    }
    
    return $recommendations;
}

function getPredictiveInsights($user_id, $date_filter) {
    // Advanced predictive analytics using historical data
    $pdo = get_db();
    
    // Predict optimal activity times
    $stmt = $pdo->prepare("
        SELECT 
            HOUR(created_at) as hour,
            AVG(happiness_gained) as avg_happiness,
            COUNT(*) as interaction_count
        FROM pet_interactions
        WHERE user_id = ? AND created_at >= $date_filter
        GROUP BY HOUR(created_at)
        HAVING interaction_count >= 3
        ORDER BY avg_happiness DESC
        LIMIT 3
    ");
    
    $stmt->execute([$user_id]);
    $optimal_times = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $insights = [
        'optimal_activity_times' => $optimal_times,
        'predicted_earnings' => predictMonthlyEarnings($user_id),
        'pet_care_recommendations' => generatePetCareRecommendations($user_id)
    ];
    
    return $insights;
}

function predictMonthlyEarnings($user_id) {
    $pdo = get_db();
    
    // Calculate average daily earnings over last 30 days
    $stmt = $pdo->prepare("
        SELECT AVG(daily_earnings) as avg_daily
        FROM (
            SELECT DATE(created_at) as date, SUM(amount) as daily_earnings
            FROM care_coin_transactions
            WHERE user_id = ? AND transaction_type = 'earned' 
            AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(created_at)
        ) as daily_totals
    ");
    
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $avg_daily = $result['avg_daily'] ?? 0;
    $predicted_monthly = $avg_daily * 30;
    
    return [
        'current_daily_average' => round($avg_daily, 2),
        'predicted_monthly' => round($predicted_monthly, 2)
    ];
}

function generatePetCareRecommendations($user_id) {
    // Generate personalized care recommendations based on pet data
    $recommendations = [
        "Consider feeding your pets during their most active hours for maximum happiness boost.",
        "Regular play sessions can significantly improve your pets' long-term wellbeing scores.",
        "Participating in community care activities boosts both your reputation and earning potential."
    ];
    
    return $recommendations;
}

?>