<?php
/**
 * Money Paws - Viral Social Features System
 * Photo contests, stories, achievements, and referral rewards
 * Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>
 */

require_once 'functions.php';
require_once 'daily_quests.php';

// Photo Contests System
function getCurrentPhotoContest() {
    $pdo = get_db();
    $stmt = $pdo->prepare("
        SELECT * FROM photo_contests 
        WHERE start_date <= NOW() AND end_date >= NOW() 
        AND is_active = 1
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getPhotoContests($limit = 10) {
    $pdo = get_db();
    $stmt = $pdo->prepare("
        SELECT pc.*, COUNT(pce.id) as entry_count
        FROM photo_contests pc
        LEFT JOIN photo_contest_entries pce ON pc.id = pce.contest_id
        GROUP BY pc.id
        ORDER BY pc.start_date DESC
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function submitPhotoContestEntry($user_id, $contest_id, $pet_id, $description = '') {
    $pdo = get_db();
    
    try {
        $pdo->beginTransaction();
        
        // Check if contest exists and is active
        $contest = getCurrentPhotoContest();
        if (!$contest || $contest['id'] != $contest_id) {
            throw new Exception('Contest not active or not found');
        }
        
        // Check if user already entered this contest
        $stmt = $pdo->prepare("
            SELECT id FROM photo_contest_entries 
            WHERE user_id = ? AND contest_id = ?
        ");
        $stmt->execute([$user_id, $contest_id]);
        if ($stmt->fetch()) {
            throw new Exception('You have already entered this contest');
        }
        
        // Verify pet ownership
        $pet = getPetById($pet_id);
        if (!$pet || $pet['user_id'] != $user_id) {
            throw new Exception('Pet not found or not owned by user');
        }
        
        // Submit entry
        $stmt = $pdo->prepare("
            INSERT INTO photo_contest_entries 
            (contest_id, user_id, pet_id, description, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$contest_id, $user_id, $pet_id, $description]);
        
        // Award participation Care Coins
        awardCareCoins($user_id, 10, "Photo contest participation: {$contest['title']}");
        
        $pdo->commit();
        return ['success' => true, 'message' => 'Contest entry submitted! You earned 10 Care Coins.'];
        
    } catch (Exception $e) {
        $pdo->rollback();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function getPhotoContestEntries($contest_id, $limit = 50) {
    $pdo = get_db();
    $stmt = $pdo->prepare("
        SELECT pce.*, u.name as user_name, p.original_name, p.filename,
               COUNT(pcv.id) as vote_count
        FROM photo_contest_entries pce
        JOIN users u ON pce.user_id = u.id
        JOIN pets p ON pce.pet_id = p.id
        LEFT JOIN photo_contest_votes pcv ON pce.id = pcv.entry_id
        WHERE pce.contest_id = ?
        GROUP BY pce.id
        ORDER BY vote_count DESC, pce.created_at ASC
        LIMIT ?
    ");
    $stmt->execute([$contest_id, $limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function votePhotoContestEntry($user_id, $entry_id) {
    $pdo = get_db();
    
    try {
        $pdo->beginTransaction();
        
        // Check if user already voted for this entry
        $stmt = $pdo->prepare("
            SELECT id FROM photo_contest_votes 
            WHERE user_id = ? AND entry_id = ?
        ");
        $stmt->execute([$user_id, $entry_id]);
        if ($stmt->fetch()) {
            throw new Exception('You have already voted for this entry');
        }
        
        // Get entry details
        $stmt = $pdo->prepare("
            SELECT pce.*, pc.end_date 
            FROM photo_contest_entries pce
            JOIN photo_contests pc ON pce.contest_id = pc.id
            WHERE pce.id = ?
        ");
        $stmt->execute([$entry_id]);
        $entry = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$entry) {
            throw new Exception('Entry not found');
        }
        
        if (strtotime($entry['end_date']) < time()) {
            throw new Exception('Contest voting has ended');
        }
        
        // Prevent self-voting
        if ($entry['user_id'] == $user_id) {
            throw new Exception('Cannot vote for your own entry');
        }
        
        // Cast vote
        $stmt = $pdo->prepare("
            INSERT INTO photo_contest_votes (entry_id, user_id, created_at)
            VALUES (?, ?, NOW())
        ");
        $stmt->execute([$entry_id, $user_id]);
        
        // Award Care Coins for voting
        awardCareCoins($user_id, 2, "Photo contest voting participation");
        
        $pdo->commit();
        return ['success' => true, 'message' => 'Vote cast! You earned 2 Care Coins.'];
        
    } catch (Exception $e) {
        $pdo->rollback();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// Pet Stories System
function createPetStory($user_id, $pet_id, $content, $media_type = 'text', $media_url = null) {
    $pdo = get_db();
    
    try {
        $pdo->beginTransaction();
        
        // Verify pet ownership
        $pet = getPetById($pet_id);
        if (!$pet || $pet['user_id'] != $user_id) {
            throw new Exception('Pet not found or not owned by user');
        }
        
        // Create story
        $stmt = $pdo->prepare("
            INSERT INTO pet_stories 
            (user_id, pet_id, content, media_type, media_url, created_at, expires_at)
            VALUES (?, ?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 24 HOUR))
        ");
        $stmt->execute([$user_id, $pet_id, $content, $media_type, $media_url]);
        $story_id = $pdo->lastInsertId();
        
        // Award Care Coins for content creation
        awardCareCoins($user_id, 5, "Created pet story");
        
        $pdo->commit();
        return ['success' => true, 'story_id' => $story_id, 'message' => 'Story created! You earned 5 Care Coins.'];
        
    } catch (Exception $e) {
        $pdo->rollback();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function getPetStories($user_id = null, $limit = 20) {
    $pdo = get_db();
    
    $sql = "
        SELECT ps.*, u.name as user_name, p.original_name, p.filename,
               COUNT(psl.id) as like_count
        FROM pet_stories ps
        JOIN users u ON ps.user_id = u.id
        JOIN pets p ON ps.pet_id = p.id
        LEFT JOIN pet_story_likes psl ON ps.id = psl.story_id
        WHERE ps.expires_at > NOW()
    ";
    
    $params = [];
    if ($user_id) {
        $sql .= " AND ps.user_id = ?";
        $params[] = $user_id;
    }
    
    $sql .= "
        GROUP BY ps.id
        ORDER BY ps.created_at DESC
        LIMIT ?
    ";
    $params[] = $limit;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function likePetStory($user_id, $story_id) {
    $pdo = get_db();
    
    try {
        $pdo->beginTransaction();
        
        // Check if already liked
        $stmt = $pdo->prepare("
            SELECT id FROM pet_story_likes 
            WHERE user_id = ? AND story_id = ?
        ");
        $stmt->execute([$user_id, $story_id]);
        if ($stmt->fetch()) {
            // Unlike
            $stmt = $pdo->prepare("
                DELETE FROM pet_story_likes 
                WHERE user_id = ? AND story_id = ?
            ");
            $stmt->execute([$user_id, $story_id]);
            $action = 'unliked';
        } else {
            // Like
            $stmt = $pdo->prepare("
                INSERT INTO pet_story_likes (story_id, user_id, created_at)
                VALUES (?, ?, NOW())
            ");
            $stmt->execute([$story_id, $user_id]);
            $action = 'liked';
            
            // Award Care Coins for engagement
            awardCareCoins($user_id, 1, "Liked a pet story");
        }
        
        $pdo->commit();
        return ['success' => true, 'action' => $action];
        
    } catch (Exception $e) {
        $pdo->rollback();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// Achievement System
function checkAndAwardAchievements($user_id, $trigger_type, $data = []) {
    $achievements = getAvailableAchievements($trigger_type);
    
    foreach ($achievements as $achievement) {
        if (!hasUserEarnedAchievement($user_id, $achievement['id'])) {
            if (checkAchievementCriteria($user_id, $achievement, $data)) {
                awardAchievement($user_id, $achievement['id']);
            }
        }
    }
}

function getAvailableAchievements($trigger_type = null) {
    $pdo = get_db();
    
    $sql = "SELECT * FROM achievements WHERE is_active = 1";
    $params = [];
    
    if ($trigger_type) {
        $sql .= " AND trigger_type = ?";
        $params[] = $trigger_type;
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function hasUserEarnedAchievement($user_id, $achievement_id) {
    $pdo = get_db();
    $stmt = $pdo->prepare("
        SELECT id FROM user_achievements 
        WHERE user_id = ? AND achievement_id = ?
    ");
    $stmt->execute([$user_id, $achievement_id]);
    return $stmt->fetch() !== false;
}

function checkAchievementCriteria($user_id, $achievement, $data) {
    switch ($achievement['criteria_type']) {
        case 'pet_count':
            $pet_count = getUserPetCount($user_id);
            return $pet_count >= $achievement['criteria_value'];
            
        case 'care_coins_earned':
            $total_earned = getUserTotalCareCoinsEarned($user_id);
            return $total_earned >= $achievement['criteria_value'];
            
        case 'community_help':
            $help_count = getUserCommunityHelpCount($user_id);
            return $help_count >= $achievement['criteria_value'];
            
        case 'contest_wins':
            $win_count = getUserContestWinCount($user_id);
            return $win_count >= $achievement['criteria_value'];
            
        case 'story_creation':
            $story_count = getUserStoryCount($user_id);
            return $story_count >= $achievement['criteria_value'];
            
        case 'referral_success':
            $referral_count = getUserSuccessfulReferrals($user_id);
            return $referral_count >= $achievement['criteria_value'];
    }
    
    return false;
}

function awardAchievement($user_id, $achievement_id) {
    $pdo = get_db();
    
    try {
        $pdo->beginTransaction();
        
        // Record achievement
        $stmt = $pdo->prepare("
            INSERT INTO user_achievements (user_id, achievement_id, earned_at)
            VALUES (?, ?, NOW())
        ");
        $stmt->execute([$user_id, $achievement_id]);
        
        // Get achievement details
        $stmt = $pdo->prepare("SELECT * FROM achievements WHERE id = ?");
        $stmt->execute([$achievement_id]);
        $achievement = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Award Care Coins
        if ($achievement['reward_coins'] > 0) {
            awardCareCoins($user_id, $achievement['reward_coins'], "Achievement: {$achievement['name']}");
        }
        
        // Create notification
        createNotification($user_id, null, null, null, 'achievement', [
            'title' => '🏆 Achievement Unlocked!',
            'message' => "You earned '{$achievement['name']}' and {$achievement['reward_coins']} Care Coins!",
            'achievement_id' => $achievement_id
        ]);
        
        $pdo->commit();
        return true;
        
    } catch (Exception $e) {
        $pdo->rollback();
        return false;
    }
}

// Referral System
function createReferralCode($user_id) {
    $code = strtoupper(substr(md5($user_id . time()), 0, 8));
    $pdo = get_db();
    
    $stmt = $pdo->prepare("
        INSERT INTO referral_codes (user_id, code, created_at)
        VALUES (?, ?, NOW())
        ON DUPLICATE KEY UPDATE code = VALUES(code)
    ");
    $stmt->execute([$user_id, $code]);
    
    return $code;
}

function getUserReferralCode($user_id) {
    $pdo = get_db();
    $stmt = $pdo->prepare("SELECT code FROM referral_codes WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result ? $result['code'] : createReferralCode($user_id);
}

function processReferral($referral_code, $new_user_id) {
    $pdo = get_db();
    
    try {
        $pdo->beginTransaction();
        
        // Find referrer
        $stmt = $pdo->prepare("SELECT user_id FROM referral_codes WHERE code = ?");
        $stmt->execute([$referral_code]);
        $referrer = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$referrer) {
            throw new Exception('Invalid referral code');
        }
        
        // Record referral
        $stmt = $pdo->prepare("
            INSERT INTO user_referrals (referrer_id, referred_id, created_at)
            VALUES (?, ?, NOW())
        ");
        $stmt->execute([$referrer['user_id'], $new_user_id]);
        
        // Award bonus Care Coins to both users
        awardCareCoins($referrer['user_id'], 50, "Successful referral bonus");
        awardCareCoins($new_user_id, 25, "Welcome referral bonus");
        
        // Check for referral achievements
        checkAndAwardAchievements($referrer['user_id'], 'referral_success');
        
        $pdo->commit();
        return true;
        
    } catch (Exception $e) {
        $pdo->rollback();
        return false;
    }
}

function getUserReferralStats($user_id) {
    $pdo = get_db();
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as referral_count,
               SUM(CASE WHEN ur.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as recent_referrals
        FROM user_referrals ur
        WHERE ur.referrer_id = ?
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Social sharing functions
function generateShareContent($type, $data) {
    switch ($type) {
        case 'pet_achievement':
            return [
                'title' => "Check out my pet {$data['pet_name']} on Money Paws!",
                'description' => "My pet just achieved something amazing! Join me in this fun pet care community.",
                'image' => $data['pet_image'],
                'url' => "https://paws.money/pet.php?id={$data['pet_id']}"
            ];
            
        case 'contest_win':
            return [
                'title' => "I won a photo contest on Money Paws!",
                'description' => "My pet {$data['pet_name']} won the {$data['contest_name']} contest!",
                'image' => $data['pet_image'],
                'url' => "https://paws.money/contests.php?id={$data['contest_id']}"
            ];
            
        case 'care_milestone':
            return [
                'title' => "I've earned {$data['care_coins']} Care Coins on Money Paws!",
                'description' => "Join me in caring for virtual pets and building a caring community!",
                'image' => "/images/care-coins-social.png",
                'url' => "https://paws.money/"
            ];
    }
}

// Helper functions for achievement criteria
function getUserPetCount($user_id) {
    $pdo = get_db();
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM pets WHERE user_id = ? AND life_status = 'alive'");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['count'];
}

function getUserTotalCareCoinsEarned($user_id) {
    $pdo = get_db();
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(amount), 0) as total 
        FROM care_coin_transactions 
        WHERE user_id = ? AND transaction_type = 'earned'
    ");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['total'];
}

function getUserCommunityHelpCount($user_id) {
    $pdo = get_db();
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM community_pet_care WHERE caregiver_id = ?");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['count'];
}

function getUserContestWinCount($user_id) {
    $pdo = get_db();
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM photo_contest_entries pce
        JOIN photo_contests pc ON pce.contest_id = pc.id
        WHERE pce.user_id = ? AND pce.is_winner = 1
    ");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['count'];
}

function getUserStoryCount($user_id) {
    $pdo = get_db();
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM pet_stories WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['count'];
}

function getUserSuccessfulReferrals($user_id) {
    $pdo = get_db();
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM user_referrals WHERE referrer_id = ?");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['count'];
}

?>