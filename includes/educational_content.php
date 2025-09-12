<?php
/**
 * Money Paws - Educational Content Hub System
 * Real pet care guides, interactive learning, and veterinary partnerships
 * Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>
 */

require_once 'functions.php';
require_once 'daily_quests.php';

// Educational Modules System
function getEducationalModules($category = null, $age_group = null) {
    $pdo = get_db();
    
    $sql = "
        SELECT em.*, COUNT(ema.id) as attempt_count
        FROM educational_modules em
        LEFT JOIN educational_module_attempts ema ON em.id = ema.module_id
        WHERE em.is_active = 1
    ";
    
    $params = [];
    
    if ($category) {
        $sql .= " AND em.category = ?";
        $params[] = $category;
    }
    
    if ($age_group) {
        $sql .= " AND (em.min_age <= ? AND em.max_age >= ?)";
        $params[] = $age_group;
        $params[] = $age_group;
    }
    
    $sql .= " GROUP BY em.id ORDER BY em.difficulty_level, em.title";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getEducationalModule($module_id) {
    $pdo = get_db();
    $stmt = $pdo->prepare("
        SELECT em.*, 
               vc.name as vet_name, vc.credentials, vc.bio,
               COUNT(ema.id) as completion_count
        FROM educational_modules em
        LEFT JOIN vet_contributors vc ON em.vet_contributor_id = vc.id
        LEFT JOIN educational_module_attempts ema ON em.id = ema.module_id AND ema.completed = 1
        WHERE em.id = ? AND em.is_active = 1
        GROUP BY em.id
    ");
    $stmt->execute([$module_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function startEducationalModule($user_id, $module_id) {
    $pdo = get_db();
    
    try {
        $pdo->beginTransaction();
        
        // Check if module exists
        $module = getEducationalModule($module_id);
        if (!$module) {
            throw new Exception('Educational module not found');
        }
        
        // Check age restrictions
        $user = getUserById($user_id);
        if ($user['birth_date']) {
            $age = calculateAge($user['birth_date']);
            if ($age < $module['min_age'] || $age > $module['max_age']) {
                throw new Exception('This module is not appropriate for your age group');
            }
        }
        
        // Check if already started recently (prevent spam)
        $stmt = $pdo->prepare("
            SELECT id FROM educational_module_attempts 
            WHERE user_id = ? AND module_id = ? 
            AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
            AND completed = 0
        ");
        $stmt->execute([$user_id, $module_id]);
        if ($stmt->fetch()) {
            throw new Exception('You recently started this module. Please complete it first.');
        }
        
        // Create new attempt
        $stmt = $pdo->prepare("
            INSERT INTO educational_module_attempts 
            (user_id, module_id, started_at, current_step)
            VALUES (?, ?, NOW(), 0)
        ");
        $stmt->execute([$user_id, $module_id]);
        $attempt_id = $pdo->lastInsertId();
        
        $pdo->commit();
        return ['success' => true, 'attempt_id' => $attempt_id];
        
    } catch (Exception $e) {
        $pdo->rollback();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function getModuleContent($module_id, $step = 0) {
    $pdo = get_db();
    $stmt = $pdo->prepare("
        SELECT * FROM educational_module_content 
        WHERE module_id = ? AND step_number = ? 
        ORDER BY content_order
    ");
    $stmt->execute([$module_id, $step]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function submitModuleAnswer($user_id, $attempt_id, $step, $answers) {
    $pdo = get_db();
    
    try {
        $pdo->beginTransaction();
        
        // Get attempt details
        $stmt = $pdo->prepare("
            SELECT ema.*, em.total_steps, em.passing_score
            FROM educational_module_attempts ema
            JOIN educational_modules em ON ema.module_id = em.id
            WHERE ema.id = ? AND ema.user_id = ?
        ");
        $stmt->execute([$attempt_id, $user_id]);
        $attempt = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$attempt) {
            throw new Exception('Invalid attempt');
        }
        
        if ($attempt['completed']) {
            throw new Exception('Module already completed');
        }
        
        // Get correct answers for this step
        $stmt = $pdo->prepare("
            SELECT eq.*, eqa.answer_text, eqa.is_correct
            FROM educational_questions eq
            LEFT JOIN educational_question_answers eqa ON eq.id = eqa.question_id
            WHERE eq.module_id = ? AND eq.step_number = ?
            ORDER BY eq.question_order, eqa.answer_order
        ");
        $stmt->execute([$attempt['module_id'], $step]);
        $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate score for this step
        $step_score = calculateStepScore($questions, $answers);
        
        // Record step completion
        $stmt = $pdo->prepare("
            INSERT INTO educational_step_completions 
            (attempt_id, step_number, answers_given, score_achieved, completed_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$attempt_id, $step, json_encode($answers), $step_score]);
        
        // Update attempt progress
        $next_step = $step + 1;
        $is_final_step = ($next_step >= $attempt['total_steps']);
        
        if ($is_final_step) {
            // Calculate final score
            $final_score = calculateFinalScore($attempt_id);
            $passed = ($final_score >= $attempt['passing_score']);
            
            // Complete the module
            $stmt = $pdo->prepare("
                UPDATE educational_module_attempts 
                SET current_step = ?, completed = 1, completed_at = NOW(), 
                    final_score = ?, passed = ?
                WHERE id = ?
            ");
            $stmt->execute([$next_step, $final_score, $passed ? 1 : 0, $attempt_id]);
            
            if ($passed) {
                // Award Care Coins for completion
                $coin_reward = 20 + ($attempt['difficulty_level'] * 10);
                awardCareCoins($user_id, $coin_reward, "Completed educational module: {$attempt['title']}");
                
                // Check for educational achievements
                checkAndAwardAchievements($user_id, 'education_completion');
                
                $pdo->commit();
                return [
                    'success' => true, 
                    'completed' => true, 
                    'passed' => $passed,
                    'final_score' => $final_score,
                    'coins_earned' => $coin_reward,
                    'message' => $passed ? 'Congratulations! Module completed successfully!' : 'Module completed, but you may want to retry for a better score.'
                ];
            } else {
                $pdo->commit();
                return [
                    'success' => true, 
                    'completed' => true, 
                    'passed' => false,
                    'final_score' => $final_score,
                    'message' => 'Keep learning! You can retry this module anytime.'
                ];
            }
        } else {
            // Move to next step
            $stmt = $pdo->prepare("
                UPDATE educational_module_attempts 
                SET current_step = ? 
                WHERE id = ?
            ");
            $stmt->execute([$next_step, $attempt_id]);
            
            $pdo->commit();
            return [
                'success' => true, 
                'completed' => false, 
                'next_step' => $next_step,
                'step_score' => $step_score
            ];
        }
        
    } catch (Exception $e) {
        $pdo->rollback();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function calculateStepScore($questions, $user_answers) {
    $total_questions = 0;
    $correct_answers = 0;
    
    $questions_grouped = [];
    foreach ($questions as $q) {
        if (!isset($questions_grouped[$q['id']])) {
            $questions_grouped[$q['id']] = [
                'question' => $q,
                'answers' => []
            ];
        }
        if ($q['answer_text']) {
            $questions_grouped[$q['id']]['answers'][] = $q;
        }
    }
    
    foreach ($questions_grouped as $question_id => $question_data) {
        $total_questions++;
        $user_answer = $user_answers[$question_id] ?? '';
        
        // Find correct answer
        foreach ($question_data['answers'] as $answer) {
            if ($answer['is_correct'] && $answer['answer_text'] === $user_answer) {
                $correct_answers++;
                break;
            }
        }
    }
    
    return $total_questions > 0 ? round(($correct_answers / $total_questions) * 100) : 0;
}

function calculateFinalScore($attempt_id) {
    $pdo = get_db();
    $stmt = $pdo->prepare("
        SELECT AVG(score_achieved) as avg_score
        FROM educational_step_completions
        WHERE attempt_id = ?
    ");
    $stmt->execute([$attempt_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return round($result['avg_score'] ?? 0);
}

// Pet Care Guides System
function getPetCareGuides($category = null, $difficulty = null) {
    $pdo = get_db();
    
    $sql = "
        SELECT pcg.*, vc.name as vet_name, vc.credentials,
               AVG(pcgr.rating) as avg_rating,
               COUNT(pcgr.id) as review_count
        FROM pet_care_guides pcg
        LEFT JOIN vet_contributors vc ON pcg.vet_contributor_id = vc.id
        LEFT JOIN pet_care_guide_reviews pcgr ON pcg.id = pcgr.guide_id
        WHERE pcg.is_published = 1
    ";
    
    $params = [];
    
    if ($category) {
        $sql .= " AND pcg.category = ?";
        $params[] = $category;
    }
    
    if ($difficulty) {
        $sql .= " AND pcg.difficulty_level = ?";
        $params[] = $difficulty;
    }
    
    $sql .= " GROUP BY pcg.id ORDER BY pcg.featured DESC, avg_rating DESC, pcg.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getPetCareGuide($guide_id, $user_id = null) {
    $pdo = get_db();
    $stmt = $pdo->prepare("
        SELECT pcg.*, vc.name as vet_name, vc.credentials, vc.bio, vc.photo,
               AVG(pcgr.rating) as avg_rating,
               COUNT(pcgr.id) as review_count
        FROM pet_care_guides pcg
        LEFT JOIN vet_contributors vc ON pcg.vet_contributor_id = vc.id
        LEFT JOIN pet_care_guide_reviews pcgr ON pcg.id = pcgr.guide_id
        WHERE pcg.id = ? AND pcg.is_published = 1
        GROUP BY pcg.id
    ");
    $stmt->execute([$guide_id]);
    $guide = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($guide && $user_id) {
        // Track view
        trackGuideView($user_id, $guide_id);
        
        // Get user's bookmark status
        $stmt = $pdo->prepare("
            SELECT id FROM pet_care_guide_bookmarks 
            WHERE user_id = ? AND guide_id = ?
        ");
        $stmt->execute([$user_id, $guide_id]);
        $guide['is_bookmarked'] = $stmt->fetch() !== false;
        
        // Get user's rating
        $stmt = $pdo->prepare("
            SELECT rating FROM pet_care_guide_reviews 
            WHERE user_id = ? AND guide_id = ?
        ");
        $stmt->execute([$user_id, $guide_id]);
        $user_rating = $stmt->fetch();
        $guide['user_rating'] = $user_rating ? $user_rating['rating'] : 0;
    }
    
    return $guide;
}

function trackGuideView($user_id, $guide_id) {
    $pdo = get_db();
    
    // Check if already viewed today
    $stmt = $pdo->prepare("
        SELECT id FROM pet_care_guide_views 
        WHERE user_id = ? AND guide_id = ? AND DATE(viewed_at) = CURDATE()
    ");
    $stmt->execute([$user_id, $guide_id]);
    
    if (!$stmt->fetch()) {
        // Record new view
        $stmt = $pdo->prepare("
            INSERT INTO pet_care_guide_views (user_id, guide_id, viewed_at)
            VALUES (?, ?, NOW())
        ");
        $stmt->execute([$user_id, $guide_id]);
        
        // Award Care Coins for learning (limited per day)
        $today_views = getDailyGuideViewCount($user_id);
        if ($today_views < 5) { // Max 5 guide views per day for coins
            awardCareCoins($user_id, 3, "Reading pet care guide");
        }
    }
}

function getDailyGuideViewCount($user_id) {
    $pdo = get_db();
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM pet_care_guide_views 
        WHERE user_id = ? AND DATE(viewed_at) = CURDATE()
    ");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['count'];
}

function bookmarkGuide($user_id, $guide_id) {
    $pdo = get_db();
    
    try {
        $pdo->beginTransaction();
        
        // Check if already bookmarked
        $stmt = $pdo->prepare("
            SELECT id FROM pet_care_guide_bookmarks 
            WHERE user_id = ? AND guide_id = ?
        ");
        $stmt->execute([$user_id, $guide_id]);
        
        if ($stmt->fetch()) {
            // Remove bookmark
            $stmt = $pdo->prepare("
                DELETE FROM pet_care_guide_bookmarks 
                WHERE user_id = ? AND guide_id = ?
            ");
            $stmt->execute([$user_id, $guide_id]);
            $action = 'removed';
        } else {
            // Add bookmark
            $stmt = $pdo->prepare("
                INSERT INTO pet_care_guide_bookmarks (user_id, guide_id, created_at)
                VALUES (?, ?, NOW())
            ");
            $stmt->execute([$user_id, $guide_id]);
            $action = 'added';
        }
        
        $pdo->commit();
        return ['success' => true, 'action' => $action];
        
    } catch (Exception $e) {
        $pdo->rollback();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function rateGuide($user_id, $guide_id, $rating, $review_text = '') {
    $pdo = get_db();
    
    if ($rating < 1 || $rating > 5) {
        return ['success' => false, 'message' => 'Rating must be between 1 and 5 stars'];
    }
    
    try {
        $pdo->beginTransaction();
        
        // Check if user already reviewed
        $stmt = $pdo->prepare("
            SELECT id FROM pet_care_guide_reviews 
            WHERE user_id = ? AND guide_id = ?
        ");
        $stmt->execute([$user_id, $guide_id]);
        
        if ($stmt->fetch()) {
            // Update existing review
            $stmt = $pdo->prepare("
                UPDATE pet_care_guide_reviews 
                SET rating = ?, review_text = ?, updated_at = NOW()
                WHERE user_id = ? AND guide_id = ?
            ");
            $stmt->execute([$rating, $review_text, $user_id, $guide_id]);
        } else {
            // Create new review
            $stmt = $pdo->prepare("
                INSERT INTO pet_care_guide_reviews 
                (user_id, guide_id, rating, review_text, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$user_id, $guide_id, $rating, $review_text]);
            
            // Award Care Coins for first-time review
            awardCareCoins($user_id, 5, "Reviewed pet care guide");
        }
        
        $pdo->commit();
        return ['success' => true, 'message' => 'Thank you for your review!'];
        
    } catch (Exception $e) {
        $pdo->rollback();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// Interactive Learning Games
function getEducationalGames($age_group = null, $skill_level = null) {
    $pdo = get_db();
    
    $sql = "
        SELECT eg.*, COUNT(egp.id) as play_count
        FROM educational_games eg
        LEFT JOIN educational_game_plays egp ON eg.id = egp.game_id
        WHERE eg.is_active = 1
    ";
    
    $params = [];
    
    if ($age_group) {
        $sql .= " AND (eg.min_age <= ? AND eg.max_age >= ?)";
        $params[] = $age_group;
        $params[] = $age_group;
    }
    
    if ($skill_level) {
        $sql .= " AND eg.skill_level = ?";
        $params[] = $skill_level;
    }
    
    $sql .= " GROUP BY eg.id ORDER BY eg.featured DESC, play_count DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function playEducationalGame($user_id, $game_id) {
    $pdo = get_db();
    
    try {
        $pdo->beginTransaction();
        
        // Get game details
        $stmt = $pdo->prepare("SELECT * FROM educational_games WHERE id = ? AND is_active = 1");
        $stmt->execute([$game_id]);
        $game = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$game) {
            throw new Exception('Game not found');
        }
        
        // Check daily play limit
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as play_count 
            FROM educational_game_plays 
            WHERE user_id = ? AND game_id = ? AND DATE(played_at) = CURDATE()
        ");
        $stmt->execute([$user_id, $game_id]);
        $daily_plays = $stmt->fetch(PDO::FETCH_ASSOC)['play_count'];
        
        if ($daily_plays >= $game['daily_play_limit']) {
            throw new Exception('Daily play limit reached for this game');
        }
        
        // Record game play
        $stmt = $pdo->prepare("
            INSERT INTO educational_game_plays 
            (user_id, game_id, played_at, session_id)
            VALUES (?, ?, NOW(), ?)
        ");
        $session_id = uniqid();
        $stmt->execute([$user_id, $game_id, $session_id]);
        
        $pdo->commit();
        return ['success' => true, 'session_id' => $session_id, 'game' => $game];
        
    } catch (Exception $e) {
        $pdo->rollback();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function submitGameScore($user_id, $session_id, $score, $completed = false) {
    $pdo = get_db();
    
    try {
        $pdo->beginTransaction();
        
        // Verify session
        $stmt = $pdo->prepare("
            SELECT egp.*, eg.coin_reward_per_play, eg.skill_level
            FROM educational_game_plays egp
            JOIN educational_games eg ON egp.game_id = eg.id
            WHERE egp.user_id = ? AND egp.session_id = ? AND egp.score IS NULL
        ");
        $stmt->execute([$user_id, $session_id]);
        $play = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$play) {
            throw new Exception('Invalid game session');
        }
        
        // Update score
        $stmt = $pdo->prepare("
            UPDATE educational_game_plays 
            SET score = ?, completed = ?, finished_at = NOW()
            WHERE user_id = ? AND session_id = ?
        ");
        $stmt->execute([$score, $completed ? 1 : 0, $user_id, $session_id]);
        
        // Award Care Coins based on completion and skill level
        $coin_reward = 0;
        if ($completed) {
            $base_reward = $play['coin_reward_per_play'];
            $skill_bonus = $play['skill_level'] * 2;
            $score_bonus = floor($score / 100); // 1 coin per 100 points
            $coin_reward = $base_reward + $skill_bonus + $score_bonus;
            
            awardCareCoins($user_id, $coin_reward, "Completed educational game");
        }
        
        $pdo->commit();
        return [
            'success' => true, 
            'coins_earned' => $coin_reward,
            'score' => $score,
            'completed' => $completed
        ];
        
    } catch (Exception $e) {
        $pdo->rollback();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// User Progress Tracking
function getUserEducationProgress($user_id) {
    $pdo = get_db();
    
    // Modules completed
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as completed_modules,
               SUM(CASE WHEN passed = 1 THEN 1 ELSE 0 END) as passed_modules
        FROM educational_module_attempts
        WHERE user_id = ? AND completed = 1
    ");
    $stmt->execute([$user_id]);
    $modules = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Guides read
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT guide_id) as guides_read
        FROM pet_care_guide_views
        WHERE user_id = ?
    ");
    $stmt->execute([$user_id]);
    $guides = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Games played
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as games_played,
               AVG(score) as avg_score
        FROM educational_game_plays
        WHERE user_id = ? AND completed = 1
    ");
    $stmt->execute([$user_id]);
    $games = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Total educational coins earned
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(amount), 0) as education_coins
        FROM care_coin_transactions
        WHERE user_id = ? AND reason LIKE '%educational%' OR reason LIKE '%guide%' OR reason LIKE '%module%' OR reason LIKE '%game%'
    ");
    $stmt->execute([$user_id]);
    $coins = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return [
        'completed_modules' => $modules['completed_modules'] ?? 0,
        'passed_modules' => $modules['passed_modules'] ?? 0,
        'guides_read' => $guides['guides_read'] ?? 0,
        'games_played' => $games['games_played'] ?? 0,
        'avg_game_score' => round($games['avg_score'] ?? 0),
        'education_coins_earned' => $coins['education_coins'] ?? 0
    ];
}

// Kid-Safe Mode Functions
function isKidSafeMode($user_id) {
    $user = getUserById($user_id);
    if (!$user || !$user['birth_date']) {
        return false; // Default to adult mode if no birth date
    }
    
    $age = calculateAge($user['birth_date']);
    return $age < 13;
}

function getKidSafeContent($content_type = 'all') {
    $age_filter = 12; // Maximum age for kid-safe content
    
    switch ($content_type) {
        case 'modules':
            return getEducationalModules(null, $age_filter);
        case 'guides':
            return getPetCareGuides('basic');
        case 'games':
            return getEducationalGames($age_filter, 'beginner');
        default:
            return [
                'modules' => getEducationalModules(null, $age_filter),
                'guides' => getPetCareGuides('basic'),
                'games' => getEducationalGames($age_filter, 'beginner')
            ];
    }
}

// Helper Functions
function calculateAge($birth_date) {
    $birth = new DateTime($birth_date);
    $today = new DateTime();
    return $today->diff($birth)->y;
}

function getUserEducationalAchievements($user_id) {
    $pdo = get_db();
    $stmt = $pdo->prepare("
        SELECT a.*, ua.earned_at
        FROM achievements a
        JOIN user_achievements ua ON a.id = ua.achievement_id
        WHERE ua.user_id = ? AND a.category = 'education'
        ORDER BY ua.earned_at DESC
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getEducationalLeaderboard($period = 'monthly') {
    $pdo = get_db();
    
    $date_filter = match($period) {
        'weekly' => 'DATE_SUB(NOW(), INTERVAL 7 DAY)',
        'monthly' => 'DATE_SUB(NOW(), INTERVAL 30 DAY)',
        'yearly' => 'DATE_SUB(NOW(), INTERVAL 1 YEAR)',
        default => 'DATE_SUB(NOW(), INTERVAL 30 DAY)'
    };
    
    $stmt = $pdo->prepare("
        SELECT u.id, u.name,
               COUNT(DISTINCT ema.id) as modules_completed,
               COUNT(DISTINCT pcgv.guide_id) as guides_read,
               COUNT(DISTINCT egp.id) as games_played,
               (COUNT(DISTINCT ema.id) * 3 + COUNT(DISTINCT pcgv.guide_id) + COUNT(DISTINCT egp.id) * 2) as education_score
        FROM users u
        LEFT JOIN educational_module_attempts ema ON u.id = ema.user_id AND ema.completed = 1 AND ema.completed_at >= $date_filter
        LEFT JOIN pet_care_guide_views pcgv ON u.id = pcgv.user_id AND pcgv.viewed_at >= $date_filter
        LEFT JOIN educational_game_plays egp ON u.id = egp.user_id AND egp.completed = 1 AND egp.finished_at >= $date_filter
        GROUP BY u.id, u.name
        HAVING education_score > 0
        ORDER BY education_score DESC
        LIMIT 50
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

?>