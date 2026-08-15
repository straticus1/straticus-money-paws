<?php
/**
 * Money Paws - Community-Driven Content System
 * User-generated quests, AI pet personalities, guild system, and mentorship
 * Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>
 */

require_once 'functions.php';
require_once 'daily_quests.php';
require_once 'social_features.php';

// User-Generated Quest System
function createUserQuest($creator_id, $quest_data) {
    $pdo = get_db();
    
    try {
        $pdo->beginTransaction();
        
        // Validate quest data
        $required_fields = ['title', 'description', 'category', 'difficulty', 'reward_coins'];
        foreach ($required_fields as $field) {
            if (empty($quest_data[$field])) {
                throw new Exception("Missing required field: $field");
            }
        }
        
        // Check user's quest creation privileges
        if (!canCreateUserQuests($creator_id)) {
            throw new Exception('You need more community reputation to create quests');
        }
        
        // Create quest
        $stmt = $pdo->prepare("
            INSERT INTO user_generated_quests 
            (creator_id, title, description, category, difficulty, reward_coins, 
             requirements, validation_method, is_repeatable, created_at, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'pending_review')
        ");
        
        $stmt->execute([
            $creator_id,
            $quest_data['title'],
            $quest_data['description'],
            $quest_data['category'],
            $quest_data['difficulty'],
            min($quest_data['reward_coins'], 50), // Cap at 50 coins
            json_encode($quest_data['requirements'] ?? []),
            $quest_data['validation_method'] ?? 'manual',
            $quest_data['is_repeatable'] ? 1 : 0
        ]);
        
        $quest_id = $pdo->lastInsertId();
        
        // Award creation coins to creator
        awardCareCoins($creator_id, 15, "Created community quest: {$quest_data['title']}");
        
        // Update creator's quest creation stats
        updateUserQuestStats($creator_id, 'quests_created', 1);
        
        $pdo->commit();
        return ['success' => true, 'quest_id' => $quest_id, 'message' => 'Quest submitted for community review!'];
        
    } catch (Exception $e) {
        $pdo->rollback();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function canCreateUserQuests($user_id) {
    // Check reputation requirements
    $reputation = getUserReputationScore($user_id);
    $min_reputation = 100; // Minimum reputation to create quests
    
    // Check if user has completed basic tutorial quests
    $completed_tutorials = getUserCompletedTutorialCount($user_id);
    $min_tutorials = 3;
    
    // Check account age (prevent spam accounts)
    $user = getUserById($user_id);
    $account_age_days = (time() - strtotime($user['created_at'])) / (60 * 60 * 24);
    $min_age_days = 7;
    
    return $reputation >= $min_reputation && 
           $completed_tutorials >= $min_tutorials && 
           $account_age_days >= $min_age_days;
}

function getUserGeneratedQuests($status = 'active', $category = null, $limit = 20) {
    $pdo = get_db();
    
    $sql = "
        SELECT ugq.*, u.name as creator_name,
               COUNT(ugqa.id) as attempt_count,
               COUNT(CASE WHEN ugqa.completed = 1 THEN 1 END) as completion_count,
               AVG(ugqr.rating) as avg_rating
        FROM user_generated_quests ugq
        JOIN users u ON ugq.creator_id = u.id
        LEFT JOIN user_generated_quest_attempts ugqa ON ugq.id = ugqa.quest_id
        LEFT JOIN user_generated_quest_reviews ugqr ON ugq.id = ugqr.quest_id
        WHERE ugq.status = ?
    ";
    
    $params = [$status];
    
    if ($category) {
        $sql .= " AND ugq.category = ?";
        $params[] = $category;
    }
    
    $sql .= "
        GROUP BY ugq.id
        ORDER BY ugq.featured DESC, avg_rating DESC, ugq.created_at DESC
        LIMIT ?
    ";
    $params[] = $limit;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function attemptUserQuest($user_id, $quest_id) {
    $pdo = get_db();
    
    try {
        $pdo->beginTransaction();
        
        // Get quest details
        $quest = getUserGeneratedQuest($quest_id);
        if (!$quest || $quest['status'] !== 'active') {
            throw new Exception('Quest not available');
        }
        
        // Check if already completed (for non-repeatable quests)
        if (!$quest['is_repeatable']) {
            $stmt = $pdo->prepare("
                SELECT id FROM user_generated_quest_attempts 
                WHERE user_id = ? AND quest_id = ? AND completed = 1
            ");
            $stmt->execute([$user_id, $quest_id]);
            if ($stmt->fetch()) {
                throw new Exception('Quest already completed');
            }
        }
        
        // Check daily attempt limit
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as attempts_today
            FROM user_generated_quest_attempts
            WHERE user_id = ? AND quest_id = ? AND DATE(created_at) = CURDATE()
        ");
        $stmt->execute([$user_id, $quest_id]);
        $attempts_today = $stmt->fetch(PDO::FETCH_ASSOC)['attempts_today'];
        
        if ($attempts_today >= 3) { // Max 3 attempts per quest per day
            throw new Exception('Daily attempt limit reached for this quest');
        }
        
        // Create attempt record
        $stmt = $pdo->prepare("
            INSERT INTO user_generated_quest_attempts 
            (user_id, quest_id, created_at, status)
            VALUES (?, ?, NOW(), 'in_progress')
        ");
        $stmt->execute([$user_id, $quest_id]);
        $attempt_id = $pdo->lastInsertId();
        
        $pdo->commit();
        return ['success' => true, 'attempt_id' => $attempt_id, 'quest' => $quest];
        
    } catch (Exception $e) {
        $pdo->rollback();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function submitUserQuestCompletion($user_id, $attempt_id, $evidence = []) {
    $pdo = get_db();
    
    try {
        $pdo->beginTransaction();
        
        // Get attempt and quest details
        $stmt = $pdo->prepare("
            SELECT ugqa.*, ugq.title, ugq.reward_coins, ugq.validation_method, ugq.creator_id
            FROM user_generated_quest_attempts ugqa
            JOIN user_generated_quests ugq ON ugqa.quest_id = ugq.id
            WHERE ugqa.id = ? AND ugqa.user_id = ?
        ");
        $stmt->execute([$attempt_id, $user_id]);
        $attempt = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$attempt || $attempt['status'] !== 'in_progress') {
            throw new Exception('Invalid quest attempt');
        }
        
        // Store evidence
        if (!empty($evidence)) {
            $stmt = $pdo->prepare("
                UPDATE user_generated_quest_attempts 
                SET evidence = ?, submitted_at = NOW(), status = 'pending_validation'
                WHERE id = ?
            ");
            $stmt->execute([json_encode($evidence), $attempt_id]);
        }
        
        // Auto-validate simple quests, require manual review for complex ones
        if ($attempt['validation_method'] === 'automatic') {
            // Validate automatically based on evidence
            $validated = validateQuestEvidence($attempt, $evidence);
            
            if ($validated) {
                completeUserQuest($attempt_id, $attempt['reward_coins'], $attempt['title']);
                $message = 'Quest completed! You earned ' . $attempt['reward_coins'] . ' Care Coins.';
            } else {
                $message = 'Quest evidence needs review. You\'ll be notified of the result.';
            }
        } else {
            // Queue for manual validation
            $message = 'Quest submitted for review. You\'ll be notified when it\'s validated!';
        }
        
        $pdo->commit();
        return ['success' => true, 'message' => $message];
        
    } catch (Exception $e) {
        $pdo->rollback();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function completeUserQuest($attempt_id, $reward_coins, $quest_title) {
    $pdo = get_db();
    
    // Get attempt details
    $stmt = $pdo->prepare("
        SELECT ugqa.user_id, ugqa.quest_id, ugq.creator_id
        FROM user_generated_quest_attempts ugqa
        JOIN user_generated_quests ugq ON ugqa.quest_id = ugq.id
        WHERE ugqa.id = ?
    ");
    $stmt->execute([$attempt_id]);
    $attempt = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($attempt) {
        // Mark as completed
        $stmt = $pdo->prepare("
            UPDATE user_generated_quest_attempts 
            SET completed = 1, completed_at = NOW(), status = 'completed'
            WHERE id = ?
        ");
        $stmt->execute([$attempt_id]);
        
        // Award coins to completer
        awardCareCoins($attempt['user_id'], $reward_coins, "Completed community quest: $quest_title");
        
        // Award bonus coins to quest creator
        $creator_bonus = ceil($reward_coins * 0.3); // 30% of reward to creator
        awardCareCoins($attempt['creator_id'], $creator_bonus, "Your quest '$quest_title' was completed");
        
        // Update stats
        updateUserQuestStats($attempt['user_id'], 'quests_completed', 1);
        updateUserQuestStats($attempt['creator_id'], 'quest_completions_received', 1);
        
        // Check for achievements
        checkAndAwardAchievements($attempt['user_id'], 'community_quest_completion');
        checkAndAwardAchievements($attempt['creator_id'], 'quest_creation_success');
    }
}

// AI Pet Personality System
function updatePetPersonality($pet_id, $interaction_data) {
    $pdo = get_db();
    
    try {
        // Get current personality
        $stmt = $pdo->prepare("SELECT * FROM pet_ai_personalities WHERE pet_id = ?");
        $stmt->execute([$pet_id]);
        $personality = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$personality) {
            // Create initial personality
            $personality = createInitialPetPersonality($pet_id);
        }
        
        // Update personality traits based on interactions
        $trait_changes = calculatePersonalityChanges($personality, $interaction_data);
        
        if (!empty($trait_changes)) {
            // Update personality in database
            $update_fields = [];
            $params = [];
            
            foreach ($trait_changes as $trait => $change) {
                $new_value = max(0, min(100, $personality[$trait] + $change));
                $update_fields[] = "$trait = ?";
                $params[] = $new_value;
            }
            
            $params[] = $pet_id;
            
            $stmt = $pdo->prepare("
                UPDATE pet_ai_personalities 
                SET " . implode(', ', $update_fields) . ", last_updated = NOW()
                WHERE pet_id = ?
            ");
            $stmt->execute($params);
            
            // Log personality change
            logPersonalityChange($pet_id, $trait_changes, $interaction_data['type']);
        }
        
        return ['success' => true, 'changes' => $trait_changes];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function createInitialPetPersonality($pet_id) {
    $pdo = get_db();
    
    // Get pet details for personality seeding
    $pet = getPetById($pet_id);
    
    // Generate initial traits (randomized but influenced by pet characteristics)
    $traits = [
        'playfulness' => rand(40, 80),
        'friendliness' => rand(30, 90),
        'curiosity' => rand(50, 95),
        'energy_level' => rand(40, 85),
        'affection' => rand(35, 80),
        'independence' => rand(20, 70),
        'intelligence' => rand(50, 90),
        'trainability' => rand(40, 85)
    ];
    
    // Insert personality
    $stmt = $pdo->prepare("
        INSERT INTO pet_ai_personalities 
        (pet_id, playfulness, friendliness, curiosity, energy_level, 
         affection, independence, intelligence, trainability, created_at, last_updated)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");
    
    $stmt->execute([
        $pet_id,
        $traits['playfulness'],
        $traits['friendliness'],
        $traits['curiosity'],
        $traits['energy_level'],
        $traits['affection'],
        $traits['independence'],
        $traits['intelligence'],
        $traits['trainability']
    ]);
    
    return $traits;
}

function calculatePersonalityChanges($current_personality, $interaction_data) {
    $changes = [];
    $interaction_type = $interaction_data['type'];
    $frequency = $interaction_data['frequency'] ?? 1;
    
    // Define how different interactions affect personality traits
    $interaction_effects = [
        'feed' => ['affection' => 1, 'friendliness' => 0.5],
        'play' => ['playfulness' => 2, 'energy_level' => 1, 'friendliness' => 1],
        'train' => ['intelligence' => 1.5, 'trainability' => 2, 'independence' => -0.5],
        'social_visit' => ['friendliness' => 1.5, 'curiosity' => 1],
        'explore' => ['curiosity' => 2, 'independence' => 1, 'energy_level' => 1],
        'rest' => ['energy_level' => -1, 'independence' => 0.5],
        'neglect' => ['affection' => -2, 'friendliness' => -1, 'energy_level' => -1]
    ];
    
    if (isset($interaction_effects[$interaction_type])) {
        foreach ($interaction_effects[$interaction_type] as $trait => $base_change) {
            $change = $base_change * $frequency;
            
            // Apply diminishing returns for extreme values
            $current_value = $current_personality[$trait];
            if (($change > 0 && $current_value > 80) || ($change < 0 && $current_value < 20)) {
                $change *= 0.5;
            }
            
            $changes[$trait] = $change;
        }
    }
    
    return $changes;
}

function getPetPersonalityResponse($pet_id, $interaction_type) {
    $pdo = get_db();
    
    // Get pet personality
    $stmt = $pdo->prepare("
        SELECT pap.*, p.original_name 
        FROM pet_ai_personalities pap
        JOIN pets p ON pap.pet_id = p.id
        WHERE pap.pet_id = ?
    ");
    $stmt->execute([$pet_id]);
    $personality = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$personality) {
        return "Your pet seems happy!"; // Default response
    }
    
    // Generate contextual response based on personality and interaction
    $responses = generatePersonalityResponses($personality, $interaction_type);
    
    // Select random response from appropriate category
    return $responses[array_rand($responses)];
}

function generatePersonalityResponses($personality, $interaction_type) {
    $pet_name = $personality['original_name'];
    $responses = [];
    
    switch ($interaction_type) {
        case 'feed':
            if ($personality['affection'] > 70) {
                $responses[] = "$pet_name nuzzles against you gratefully after eating.";
                $responses[] = "$pet_name's eyes light up with love as they enjoy their meal.";
            } elseif ($personality['independence'] > 60) {
                $responses[] = "$pet_name eats calmly, maintaining their dignified composure.";
                $responses[] = "$pet_name appreciates the meal but doesn't seem overly excited.";
            } else {
                $responses[] = "$pet_name eagerly devours their food.";
                $responses[] = "$pet_name seems satisfied with their meal.";
            }
            break;
            
        case 'play':
            if ($personality['playfulness'] > 80) {
                $responses[] = "$pet_name bounces with excitement and wants to play more!";
                $responses[] = "$pet_name's playful energy is absolutely contagious!";
            } elseif ($personality['energy_level'] < 40) {
                $responses[] = "$pet_name enjoys a gentle play session but seems a bit tired.";
                $responses[] = "$pet_name participates halfheartedly in the play.";
            } else {
                $responses[] = "$pet_name has fun playing with you.";
                $responses[] = "$pet_name seems to enjoy the playtime.";
            }
            break;
            
        case 'social_visit':
            if ($personality['friendliness'] > 75) {
                $responses[] = "$pet_name warmly greets the visitor and seems delighted by the company.";
                $responses[] = "$pet_name's social nature shines as they welcome the guest.";
            } elseif ($personality['independence'] > 70) {
                $responses[] = "$pet_name acknowledges the visitor politely but keeps some distance.";
                $responses[] = "$pet_name observes the newcomer with cautious curiosity.";
            } else {
                $responses[] = "$pet_name seems neutral about the visitor.";
                $responses[] = "$pet_name watches the social interaction with mild interest.";
            }
            break;
    }
    
    return !empty($responses) ? $responses : ["$pet_name responds to your interaction."];
}

// Guild System
function createGuild($founder_id, $guild_data) {
    $pdo = get_db();
    
    try {
        $pdo->beginTransaction();
        
        // Validate guild creation requirements
        if (!canCreateGuild($founder_id)) {
            throw new Exception('You need more reputation to create a guild');
        }
        
        // Check for duplicate guild names
        $stmt = $pdo->prepare("SELECT id FROM guilds WHERE name = ?");
        $stmt->execute([$guild_data['name']]);
        if ($stmt->fetch()) {
            throw new Exception('Guild name already exists');
        }
        
        // Create guild
        $stmt = $pdo->prepare("
            INSERT INTO guilds 
            (founder_id, name, description, focus_area, max_members, join_type, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $founder_id,
            $guild_data['name'],
            $guild_data['description'],
            $guild_data['focus_area'],
            $guild_data['max_members'] ?? 50,
            $guild_data['join_type'] ?? 'open'
        ]);
        
        $guild_id = $pdo->lastInsertId();
        
        // Add founder as guild leader
        $stmt = $pdo->prepare("
            INSERT INTO guild_members 
            (guild_id, user_id, role, joined_at, contribution_score)
            VALUES (?, ?, 'leader', NOW(), 0)
        ");
        $stmt->execute([$guild_id, $founder_id]);
        
        // Award guild creation achievement
        awardCareCoins($founder_id, 100, "Created guild: {$guild_data['name']}");
        
        $pdo->commit();
        return ['success' => true, 'guild_id' => $guild_id];
        
    } catch (Exception $e) {
        $pdo->rollback();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function canCreateGuild($user_id) {
    $reputation = getUserReputationScore($user_id);
    $min_reputation = 250; // Higher requirement for guild creation
    
    // Check if user is already in a guild as leader
    $pdo = get_db();
    $stmt = $pdo->prepare("
        SELECT id FROM guild_members 
        WHERE user_id = ? AND role = 'leader'
    ");
    $stmt->execute([$user_id]);
    
    return $reputation >= $min_reputation && !$stmt->fetch();
}

function joinGuild($user_id, $guild_id, $application_message = '') {
    $pdo = get_db();
    
    try {
        $pdo->beginTransaction();
        
        // Get guild details
        $guild = getGuildById($guild_id);
        if (!$guild) {
            throw new Exception('Guild not found');
        }
        
        // Check if already a member
        $stmt = $pdo->prepare("
            SELECT id FROM guild_members WHERE user_id = ? AND guild_id = ?
        ");
        $stmt->execute([$user_id, $guild_id]);
        if ($stmt->fetch()) {
            throw new Exception('Already a member of this guild');
        }
        
        // Check if user is in another guild
        $stmt = $pdo->prepare("SELECT guild_id FROM guild_members WHERE user_id = ?");
        $stmt->execute([$user_id]);
        if ($stmt->fetch()) {
            throw new Exception('You must leave your current guild first');
        }
        
        // Check guild capacity
        $current_members = getGuildMemberCount($guild_id);
        if ($current_members >= $guild['max_members']) {
            throw new Exception('Guild is at maximum capacity');
        }
        
        if ($guild['join_type'] === 'open') {
            // Join immediately
            $stmt = $pdo->prepare("
                INSERT INTO guild_members 
                (guild_id, user_id, role, joined_at, contribution_score)
                VALUES (?, ?, 'member', NOW(), 0)
            ");
            $stmt->execute([$guild_id, $user_id]);
            
            $message = "Welcome to {$guild['name']}!";
        } else {
            // Create application
            $stmt = $pdo->prepare("
                INSERT INTO guild_applications 
                (guild_id, user_id, message, created_at)
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$guild_id, $user_id, $application_message]);
            
            $message = "Application submitted to {$guild['name']}!";
        }
        
        $pdo->commit();
        return ['success' => true, 'message' => $message];
        
    } catch (Exception $e) {
        $pdo->rollback();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function getGuilds($focus_area = null, $sort = 'activity') {
    $pdo = get_db();
    
    $sql = "
        SELECT g.*, u.name as founder_name,
               COUNT(gm.id) as member_count,
               SUM(gm.contribution_score) as total_contribution,
               MAX(gm.last_active) as last_activity
        FROM guilds g
        JOIN users u ON g.founder_id = u.id
        LEFT JOIN guild_members gm ON g.id = gm.guild_id
        WHERE g.is_active = 1
    ";
    
    $params = [];
    
    if ($focus_area) {
        $sql .= " AND g.focus_area = ?";
        $params[] = $focus_area;
    }
    
    $sql .= " GROUP BY g.id";
    
    // Sort options
    switch ($sort) {
        case 'activity':
            $sql .= " ORDER BY last_activity DESC, total_contribution DESC";
            break;
        case 'size':
            $sql .= " ORDER BY member_count DESC";
            break;
        case 'contribution':
            $sql .= " ORDER BY total_contribution DESC";
            break;
        case 'newest':
            $sql .= " ORDER BY g.created_at DESC";
            break;
        default:
            $sql .= " ORDER BY total_contribution DESC";
    }
    
    $sql .= " LIMIT 50";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Mentorship System
function createMentorshipOffer($mentor_id, $offer_data) {
    $pdo = get_db();
    
    try {
        // Validate mentor qualifications
        if (!canBecomeMentor($mentor_id)) {
            throw new Exception('You need more experience to become a mentor');
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO mentorship_offers 
            (mentor_id, title, description, experience_areas, max_mentees, 
             time_commitment, requirements, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $mentor_id,
            $offer_data['title'],
            $offer_data['description'],
            json_encode($offer_data['experience_areas']),
            $offer_data['max_mentees'] ?? 3,
            $offer_data['time_commitment'],
            $offer_data['requirements'] ?? ''
        ]);
        
        $offer_id = $pdo->lastInsertId();
        
        // Award mentor badge
        awardCareCoins($mentor_id, 50, "Became a community mentor");
        
        return ['success' => true, 'offer_id' => $offer_id];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function canBecomeMentor($user_id) {
    // Check experience requirements
    $progress = getUserEducationProgress($user_id);
    $reputation = getUserReputationScore($user_id);
    
    // Requirements: 10+ completed modules, 500+ reputation, account > 30 days
    $user = getUserById($user_id);
    $account_age_days = (time() - strtotime($user['created_at'])) / (60 * 60 * 24);
    
    return $progress['completed_modules'] >= 10 && 
           $reputation >= 500 && 
           $account_age_days >= 30;
}

function requestMentorship($mentee_id, $offer_id, $request_message) {
    $pdo = get_db();
    
    try {
        $pdo->beginTransaction();
        
        // Get offer details
        $stmt = $pdo->prepare("
            SELECT mo.*, u.name as mentor_name,
                   COUNT(mr.id) as current_mentees
            FROM mentorship_offers mo
            JOIN users u ON mo.mentor_id = u.id
            LEFT JOIN mentorship_relationships mr ON mo.id = mr.offer_id AND mr.status = 'active'
            WHERE mo.id = ? AND mo.is_active = 1
            GROUP BY mo.id
        ");
        $stmt->execute([$offer_id]);
        $offer = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$offer) {
            throw new Exception('Mentorship offer not found');
        }
        
        if ($offer['current_mentees'] >= $offer['max_mentees']) {
            throw new Exception('Mentor has reached maximum capacity');
        }
        
        // Check if already requested
        $stmt = $pdo->prepare("
            SELECT id FROM mentorship_requests 
            WHERE mentee_id = ? AND offer_id = ? AND status = 'pending'
        ");
        $stmt->execute([$mentee_id, $offer_id]);
        if ($stmt->fetch()) {
            throw new Exception('Request already pending');
        }
        
        // Create request
        $stmt = $pdo->prepare("
            INSERT INTO mentorship_requests 
            (offer_id, mentee_id, message, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$offer_id, $mentee_id, $request_message]);
        
        $pdo->commit();
        return ['success' => true, 'message' => "Mentorship request sent to {$offer['mentor_name']}!"];
        
    } catch (Exception $e) {
        $pdo->rollback();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// Helper Functions
function getUserReputationScore($user_id) {
    $pdo = get_db();
    
    // Calculate reputation based on various activities
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(SUM(
                CASE 
                    WHEN activity_type = 'quest_completion' THEN 5
                    WHEN activity_type = 'quest_creation' THEN 10
                    WHEN activity_type = 'helpful_review' THEN 3
                    WHEN activity_type = 'mentorship_success' THEN 20
                    WHEN activity_type = 'community_contribution' THEN 8
                    ELSE 1
                END
            ), 0) as reputation_score
        FROM user_reputation_activities
        WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    ");
    
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['reputation_score'];
}

function updateUserQuestStats($user_id, $stat_type, $increment) {
    $pdo = get_db();
    
    $stmt = $pdo->prepare("
        INSERT INTO user_quest_stats (user_id, $stat_type, updated_at)
        VALUES (?, ?, NOW())
        ON DUPLICATE KEY UPDATE 
        $stat_type = $stat_type + ?, updated_at = NOW()
    ");
    
    $stmt->execute([$user_id, $increment, $increment]);
}

function getUserCompletedTutorialCount($user_id) {
    $pdo = get_db();
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM educational_module_attempts 
        WHERE user_id = ? AND completed = 1 AND passed = 1
    ");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['count'];
}

function getGuildMemberCount($guild_id) {
    $pdo = get_db();
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM guild_members WHERE guild_id = ?");
    $stmt->execute([$guild_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['count'];
}

function getUserGeneratedQuest($quest_id) {
    $pdo = get_db();
    $stmt = $pdo->prepare("
        SELECT ugq.*, u.name as creator_name
        FROM user_generated_quests ugq
        JOIN users u ON ugq.creator_id = u.id
        WHERE ugq.id = ?
    ");
    $stmt->execute([$quest_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getGuildById($guild_id) {
    $pdo = get_db();
    $stmt = $pdo->prepare("SELECT * FROM guilds WHERE id = ?");
    $stmt->execute([$guild_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function logPersonalityChange($pet_id, $changes, $interaction_type) {
    $pdo = get_db();
    
    $stmt = $pdo->prepare("
        INSERT INTO pet_personality_logs 
        (pet_id, interaction_type, trait_changes, created_at)
        VALUES (?, ?, ?, NOW())
    ");
    
    $stmt->execute([$pet_id, $interaction_type, json_encode($changes)]);
}

function validateQuestEvidence($attempt, $evidence) {
    // Simple validation logic - can be expanded with ML/AI
    $validation_score = 0;
    
    // Check for required evidence fields
    $required_fields = json_decode($attempt['requirements'], true) ?? [];
    
    foreach ($required_fields as $field) {
        if (isset($evidence[$field]) && !empty($evidence[$field])) {
            $validation_score += 25;
        }
    }
    
    // Auto-approve if validation score is high enough
    return $validation_score >= 75;
}

?>