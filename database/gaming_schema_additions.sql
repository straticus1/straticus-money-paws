-- Additional schema for Adaptive Gaming System

-- Educational game progress tracking
CREATE TABLE IF NOT EXISTS educational_game_progress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    activity_type VARCHAR(50) NOT NULL,
    success BOOLEAN DEFAULT FALSE,
    reward_earned INT DEFAULT 0,
    played_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    total_plays INT DEFAULT 1,
    successes INT DEFAULT 0,
    total_rewards INT DEFAULT 0,
    INDEX idx_user_activity (user_id, activity_type),
    INDEX idx_played_at (played_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Gambling activity log for responsible gaming
CREATE TABLE IF NOT EXISTS gambling_activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    game_type VARCHAR(50) NOT NULL,
    bet_amount DECIMAL(20,8) NOT NULL,
    crypto_type VARCHAR(10) NOT NULL,
    result VARCHAR(20) DEFAULT NULL,
    win_amount DECIMAL(20,8) DEFAULT 0,
    played_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_date (user_id, played_at),
    INDEX idx_game_type (game_type),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Responsible gaming warnings/interventions
CREATE TABLE IF NOT EXISTS responsible_gaming_interventions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    intervention_type ENUM('warning', 'limit', 'break_suggestion', 'education_redirect') NOT NULL,
    trigger_reason VARCHAR(255) NOT NULL,
    user_response ENUM('acknowledged', 'dismissed', 'accepted_break', 'switched_to_educational') DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    responded_at TIMESTAMP NULL,
    INDEX idx_user_date (user_id, created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Update existing tables with new columns if they don't exist
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS gaming_preference ENUM('educational', 'mixed', 'traditional') DEFAULT 'educational',
ADD COLUMN IF NOT EXISTS birth_date DATE NULL,
ADD COLUMN IF NOT EXISTS care_coins INT DEFAULT 0;

-- Gaming session tracking
CREATE TABLE IF NOT EXISTS gaming_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    session_start TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    session_end TIMESTAMP NULL,
    games_played INT DEFAULT 0,
    educational_games INT DEFAULT 0,
    traditional_games INT DEFAULT 0,
    total_care_coins_earned INT DEFAULT 0,
    total_crypto_bet DECIMAL(20,8) DEFAULT 0,
    total_crypto_won DECIMAL(20,8) DEFAULT 0,
    session_duration_minutes INT DEFAULT 0,
    INDEX idx_user_date (user_id, session_start),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Daily quest completions (already created in enhanced_features_schema.sql)
-- Just add index for better performance
CREATE INDEX IF NOT EXISTS idx_quest_completion ON daily_quests (user_id, quest_date, completed);

-- Care coin transactions index for better performance  
CREATE INDEX IF NOT EXISTS idx_care_coins_user_date ON care_coin_transactions (user_id, created_at);
CREATE INDEX IF NOT EXISTS idx_care_coins_type ON care_coin_transactions (transaction_type);

-- Community care performance indexes
CREATE INDEX IF NOT EXISTS idx_community_care_date ON community_pet_care (cared_at);
CREATE INDEX IF NOT EXISTS idx_community_care_caregiver_date ON community_pet_care (caregiver_user_id, cared_at);

-- Insert some sample educational modules if they don't exist
INSERT IGNORE INTO educational_modules (id, title, description, category, content_type, content_data, care_coins_reward) VALUES
(1, 'Pet Nutrition Basics', 'Learn about proper nutrition for different types of pets', 'animal_care', 'interactive', 
 '{"lessons": ["Types of pet food", "Nutritional requirements", "Feeding schedules"], "quiz_questions": 5}', 15),
(2, 'Understanding Pet Behavior', 'Decode what your pet is trying to tell you', 'animal_care', 'video',
 '{"video_url": "/educational/pet-behavior.mp4", "duration": 480}', 20),
(3, 'Ecosystem Balance', 'How pets and wildlife interact in nature', 'ecology', 'article',
 '{"content_sections": ["Predator-prey relationships", "Habitat preservation", "Human impact"]}', 25),
(4, 'Responsible Pet Ownership', 'The commitments and joys of caring for animals', 'responsibility', 'interactive',
 '{"scenarios": ["Daily care routines", "Emergency situations", "Long-term planning"]}', 18),
(5, 'Pet Genetics Fundamentals', 'Basic understanding of how traits are inherited', 'biology', 'interactive',
 '{"concepts": ["Dominant vs recessive", "Breeding outcomes", "Genetic diversity"]}', 22);