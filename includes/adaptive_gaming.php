<?php
/**
 * Money Paws - Age-Based Adaptive Gaming System
 * Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>
 */

require_once 'functions.php';
require_once 'daily_quests.php';
require_once 'security.php';

/**
 * Determine appropriate gaming mode based on user age and preferences
 */
function determineGamingMode($user_id) {
    $pdo = get_db();
    if (!$pdo) return 'educational';
    
    $stmt = $pdo->prepare("SELECT birth_date, gaming_preference FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) return 'educational';
    
    // Calculate age if birth_date is available
    $age = null;
    if ($user['birth_date']) {
        $birth_date = new DateTime($user['birth_date']);
        $today = new DateTime();
        $age = $today->diff($birth_date)->y;
    }
    
    // Age-based automatic assignment
    if ($age !== null) {
        if ($age < 18) {
            return 'educational'; // Under 18 = educational only
        } elseif ($age < 25) {
            return $user['gaming_preference'] ?? 'mixed'; // 18-24 = default to mixed
        } else {
            return $user['gaming_preference'] ?? 'educational'; // 25+ = default to educational
        }
    }
    
    // If no age provided, use preference or default to educational
    return $user['gaming_preference'] ?? 'educational';
}

/**
 * Get available games for user based on their gaming mode
 */
function getAvailableGames($user_id) {
    $gaming_mode = determineGamingMode($user_id);
    
    $games = [
        'educational' => [
            'pet_care_challenge' => [
                'name' => 'Pet Care Challenge',
                'description' => 'Learn proper pet care through timed challenges',
                'icon' => '🏥',
                'type' => 'skill_based',
                'educational_value' => 'Animal care knowledge',
                'reward_type' => 'care_coins',
                'base_reward' => 15,
                'entry_cost' => 0
            ],
            'genetics_puzzle' => [
                'name' => 'Genetics Puzzle',
                'description' => 'Solve breeding puzzles to understand genetics',
                'icon' => '🧬',
                'type' => 'puzzle',
                'educational_value' => 'Basic genetics understanding',
                'reward_type' => 'care_coins',
                'base_reward' => 20,
                'entry_cost' => 0
            ],
            'ecosystem_builder' => [
                'name' => 'Ecosystem Builder',
                'description' => 'Create balanced ecosystems for pets',
                'icon' => '🌱',
                'type' => 'strategy',
                'educational_value' => 'Environmental science',
                'reward_type' => 'care_coins',
                'base_reward' => 25,
                'entry_cost' => 0
            ],
            'nutrition_master' => [
                'name' => 'Nutrition Master',
                'description' => 'Learn about proper pet nutrition',
                'icon' => '🥗',
                'type' => 'quiz',
                'educational_value' => 'Pet nutrition science',
                'reward_type' => 'care_coins',
                'base_reward' => 12,
                'entry_cost' => 0
            ]
        ],
        'traditional' => [
            'coin_flip' => [
                'name' => 'Coin Flip',
                'description' => 'Classic heads or tails betting game',
                'icon' => '🪙',
                'type' => 'chance',
                'educational_value' => null,
                'reward_type' => 'crypto',
                'win_multiplier' => 1.95,
                'entry_cost' => 'variable'
            ],
            'dice_roll' => [
                'name' => 'Lucky Dice',
                'description' => 'Roll dice and predict the outcome',
                'icon' => '🎲',
                'type' => 'chance',
                'educational_value' => null,
                'reward_type' => 'crypto',
                'win_multiplier' => 5.8,
                'entry_cost' => 'variable'
            ],
            'card_draw' => [
                'name' => 'Card Draw',
                'description' => 'Draw a card and predict the suit',
                'icon' => '🃏',
                'type' => 'chance',
                'educational_value' => null,
                'reward_type' => 'crypto',
                'win_multiplier' => 3.8,
                'entry_cost' => 'variable'
            ]
        ]
    ];
    
    switch ($gaming_mode) {
        case 'educational':
            return $games['educational'];
        case 'traditional':
            return array_merge($games['educational'], $games['traditional']);
        case 'mixed':
        default:
            return array_merge($games['educational'], $games['traditional']);
    }
}

/**
 * Update user gaming preference
 */
function updateGamingPreference($user_id, $preference) {
    $pdo = get_db();
    if (!$pdo) return false;
    
    $valid_preferences = ['educational', 'mixed', 'traditional'];
    if (!in_array($preference, $valid_preferences)) {
        return false;
    }
    
    // Check if user can access this preference (age restriction)
    $stmt = $pdo->prepare("SELECT birth_date FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && $user['birth_date']) {
        $birth_date = new DateTime($user['birth_date']);
        $today = new DateTime();
        $age = $today->diff($birth_date)->y;
        
        // Under 18 can only use educational
        if ($age < 18 && $preference !== 'educational') {
            return false;
        }
    }
    
    $stmt = $pdo->prepare("UPDATE users SET gaming_preference = ? WHERE id = ?");
    return $stmt->execute([$preference, $user_id]);
}

/**
 * Play educational game
 */
function playEducationalGame($user_id, $game_type, $game_data = []) {
    $available_games = getAvailableGames($user_id);
    
    if (!isset($available_games[$game_type])) {
        return ['success' => false, 'message' => 'Game not available'];
    }
    
    $game = $available_games[$game_type];
    
    switch ($game_type) {
        case 'pet_care_challenge':
            return playPetCareChallenge($user_id, $game_data);
        case 'genetics_puzzle':
            return playGeneticsPuzzle($user_id, $game_data);
        case 'ecosystem_builder':
            return playEcosystemBuilder($user_id, $game_data);
        case 'nutrition_master':
            return playNutritionMaster($user_id, $game_data);
        default:
            return ['success' => false, 'message' => 'Unknown educational game'];
    }
}

/**
 * Pet Care Challenge Game
 */
function playPetCareChallenge($user_id, $game_data) {
    $scenarios = [
        [
            'situation' => 'Your pet hasnt eaten in 8 hours and looks lethargic',
            'options' => [
                'Give it a large meal immediately',
                'Provide small amounts of food frequently',
                'Wait until regular feeding time',
                'Give it treats to cheer it up'
            ],
            'correct' => 1, // Index of correct answer
            'explanation' => 'Small frequent meals are better for a pet that hasnt eaten, to avoid stomach upset.',
            'care_coins_reward' => 15
        ],
        [
            'situation' => 'Your pet is showing signs of dehydration',
            'options' => [
                'Encourage small sips of water frequently',
                'Force it to drink a large amount',
                'Give it milk instead',
                'Wait for it to drink on its own'
            ],
            'correct' => 0,
            'explanation' => 'Small, frequent water intake prevents overwhelming a dehydrated pet.',
            'care_coins_reward' => 18
        ],
        [
            'situation' => 'Your pet seems bored and destructive',
            'options' => [
                'Confine it to a small space',
                'Ignore the behavior',
                'Provide mental stimulation and exercise',
                'Give it food as distraction'
            ],
            'correct' => 2,
            'explanation' => 'Boredom-related destructive behavior is best addressed with mental and physical stimulation.',
            'care_coins_reward' => 20
        ]
    ];
    
    $scenario = $scenarios[array_rand($scenarios)];
    $user_answer = intval($game_data['answer'] ?? -1);
    
    if ($user_answer === $scenario['correct']) {
        // Correct answer
        awardCareCoins($user_id, $scenario['care_coins_reward'], 'Pet Care Challenge victory');
        updateQuestProgress($user_id, 'pet_training');
        
        // Record educational progress
        recordEducationalProgress($user_id, 'pet_care_challenge', true, $scenario['care_coins_reward']);
        
        return [
            'success' => true,
            'correct' => true,
            'explanation' => $scenario['explanation'],
            'care_coins_earned' => $scenario['care_coins_reward'],
            'message' => 'Excellent! You earned ' . $scenario['care_coins_reward'] . ' Care Coins!'
        ];
    } else {
        // Wrong answer - still give participation reward
        $participation_reward = 3;
        awardCareCoins($user_id, $participation_reward, 'Pet Care Challenge participation');
        
        recordEducationalProgress($user_id, 'pet_care_challenge', false, $participation_reward);
        
        return [
            'success' => true,
            'correct' => false,
            'correct_answer' => $scenario['correct'],
            'explanation' => $scenario['explanation'],
            'care_coins_earned' => $participation_reward,
            'message' => 'Good try! You earned ' . $participation_reward . ' Care Coins for learning.'
        ];
    }
}

/**
 * Genetics Puzzle Game
 */
function playGeneticsPuzzle($user_id, $game_data) {
    $puzzles = [
        [
            'question' => 'If you breed a brown-eyed pet (Bb) with a blue-eyed pet (bb), what percentage of offspring will have brown eyes?',
            'options' => ['25%', '50%', '75%', '100%'],
            'correct' => 1,
            'explanation' => 'Brown eyes (B) are dominant. Bb × bb gives 50% Bb (brown) and 50% bb (blue).',
            'reward' => 22
        ],
        [
            'question' => 'Which trait is more likely to be passed to offspring?',
            'options' => ['Dominant traits', 'Recessive traits', 'Both equally', 'Neither'],
            'correct' => 0,
            'explanation' => 'Dominant traits are expressed when present, making them more likely to appear in offspring.',
            'reward' => 18
        ],
        [
            'question' => 'What determines the genetic diversity in offspring?',
            'options' => ['Only the mother', 'Only the father', 'Both parents equally', 'Environmental factors only'],
            'correct' => 2,
            'explanation' => 'Both parents contribute genetic material, creating diverse combinations in offspring.',
            'reward' => 25
        ]
    ];
    
    $puzzle = $puzzles[array_rand($puzzles)];
    $user_answer = intval($game_data['answer'] ?? -1);
    
    if ($user_answer === $puzzle['correct']) {
        awardCareCoins($user_id, $puzzle['reward'], 'Genetics Puzzle success');
        recordEducationalProgress($user_id, 'genetics_puzzle', true, $puzzle['reward']);
        
        return [
            'success' => true,
            'correct' => true,
            'explanation' => $puzzle['explanation'],
            'care_coins_earned' => $puzzle['reward'],
            'message' => 'Outstanding! You understand genetics well!'
        ];
    } else {
        $participation_reward = 5;
        awardCareCoins($user_id, $participation_reward, 'Genetics Puzzle participation');
        recordEducationalProgress($user_id, 'genetics_puzzle', false, $participation_reward);
        
        return [
            'success' => true,
            'correct' => false,
            'correct_answer' => $puzzle['correct'],
            'explanation' => $puzzle['explanation'],
            'care_coins_earned' => $participation_reward,
            'message' => 'Learning genetics takes practice! Keep it up!'
        ];
    }
}

/**
 * Traditional gambling games (18+ only)
 */
function playTraditionalGame($user_id, $game_type, $bet_amount, $crypto_type, $game_data = []) {
    // Verify age restriction
    $gaming_mode = determineGamingMode($user_id);
    if ($gaming_mode === 'educational') {
        return ['success' => false, 'message' => 'Traditional games require age verification'];
    }
    
    $available_games = getAvailableGames($user_id);
    if (!isset($available_games[$game_type])) {
        return ['success' => false, 'message' => 'Game not available'];
    }
    
    // Check balance
    $user_balance = getUserCryptoBalance($user_id, $crypto_type);
    if ($user_balance < $bet_amount) {
        return ['success' => false, 'message' => 'Insufficient balance'];
    }
    
    // Record gambling activity for responsible gaming tracking
    recordGamblingActivity($user_id, $game_type, $bet_amount, $crypto_type);
    
    switch ($game_type) {
        case 'coin_flip':
            return playCoinFlip($user_id, $bet_amount, $crypto_type, $game_data);
        case 'dice_roll':
            return playDiceRoll($user_id, $bet_amount, $crypto_type, $game_data);
        case 'card_draw':
            return playCardDraw($user_id, $bet_amount, $crypto_type, $game_data);
        default:
            return ['success' => false, 'message' => 'Unknown traditional game'];
    }
}

/**
 * Enhanced coin flip with responsible gaming features
 */
function playCoinFlip($user_id, $bet_amount, $crypto_type, $game_data) {
    $user_choice = $game_data['choice'] ?? 'heads';
    $result = (mt_rand(0, 1) === 0) ? 'heads' : 'tails';
    $won = ($result === $user_choice);
    
    // Deduct bet
    updateUserBalance($user_id, $crypto_type, $bet_amount, 'subtract');
    
    if ($won) {
        $win_amount = $bet_amount * 1.95; // 95% return rate
        updateUserBalance($user_id, $crypto_type, $win_amount, 'add');
        
        return [
            'success' => true,
            'won' => true,
            'result' => $result,
            'win_amount' => $win_amount,
            'message' => "🎉 You won! The coin landed on {$result}!"
        ];
    } else {
        return [
            'success' => true,
            'won' => false,
            'result' => $result,
            'message' => "😔 You lost. The coin landed on {$result}."
        ];
    }
}

/**
 * Record educational progress
 */
function recordEducationalProgress($user_id, $activity_type, $success, $reward) {
    $pdo = get_db();
    if (!$pdo) return false;
    
    $stmt = $pdo->prepare("
        INSERT INTO educational_game_progress 
        (user_id, activity_type, success, reward_earned, played_at)
        VALUES (?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
        total_plays = total_plays + 1,
        successes = successes + ?,
        total_rewards = total_rewards + ?
    ");
    
    return $stmt->execute([
        $user_id, 
        $activity_type, 
        $success ? 1 : 0, 
        $reward,
        $success ? 1 : 0,
        $reward
    ]);
}

/**
 * Record gambling activity for responsible gaming
 */
function recordGamblingActivity($user_id, $game_type, $bet_amount, $crypto_type) {
    $pdo = get_db();
    if (!$pdo) return false;
    
    $stmt = $pdo->prepare("
        INSERT INTO gambling_activity_log 
        (user_id, game_type, bet_amount, crypto_type, played_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    
    return $stmt->execute([$user_id, $game_type, $bet_amount, $crypto_type]);
}

/**
 * Check gambling activity for responsible gaming warnings
 */
function checkGamblingLimits($user_id) {
    $pdo = get_db();
    if (!$pdo) return ['needs_warning' => false];
    
    // Check activity in last 24 hours
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as game_count,
            SUM(bet_amount) as total_bet,
            MIN(played_at) as first_game,
            MAX(played_at) as last_game
        FROM gambling_activity_log 
        WHERE user_id = ? AND played_at > DATE_SUB(NOW(), INTERVAL 1 DAY)
    ");
    $stmt->execute([$user_id]);
    $activity = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $warnings = [];
    
    if ($activity['game_count'] > 20) {
        $warnings[] = 'You have played more than 20 games today. Consider taking a break.';
    }
    
    if ($activity['total_bet'] > 1000) { // Adjust threshold as needed
        $warnings[] = 'Your betting amount today is quite high. Please gamble responsibly.';
    }
    
    // Check for rapid playing (more than 10 games in an hour)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as rapid_game_count
        FROM gambling_activity_log 
        WHERE user_id = ? AND played_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ");
    $stmt->execute([$user_id]);
    $rapid_count = $stmt->fetchColumn();
    
    if ($rapid_count > 10) {
        $warnings[] = 'You are playing very frequently. Consider the educational games for a healthier gaming experience.';
    }
    
    return [
        'needs_warning' => !empty($warnings),
        'warnings' => $warnings,
        'activity_summary' => $activity,
        'educational_alternative' => 'Try our educational games - they are free and help you learn while earning Care Coins!'
    ];
}

/**
 * Get gaming statistics for user dashboard
 */
function getGamingStats($user_id) {
    $pdo = get_db();
    if (!$pdo) return [];
    
    // Educational gaming stats
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_educational_games,
            SUM(success) as educational_successes,
            SUM(reward_earned) as total_educational_rewards
        FROM educational_game_progress 
        WHERE user_id = ?
    ");
    $stmt->execute([$user_id]);
    $educational_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Traditional gaming stats (if applicable)
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_traditional_games,
            SUM(bet_amount) as total_bet_amount
        FROM gambling_activity_log 
        WHERE user_id = ?
    ");
    $stmt->execute([$user_id]);
    $traditional_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return [
        'educational' => $educational_stats,
        'traditional' => $traditional_stats,
        'gaming_mode' => determineGamingMode($user_id)
    ];
}