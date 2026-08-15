-- Enhanced Features Schema for Money Paws
-- Daily Quests, Care Coins, Community Economy, and Metaverse Integration

-- Daily Quests System
CREATE TABLE IF NOT EXISTS daily_quests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    quest_date DATE NOT NULL,
    quest_type VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    target_value INT NOT NULL DEFAULT 1,
    current_progress INT NOT NULL DEFAULT 0,
    completed BOOLEAN DEFAULT FALSE,
    completed_at TIMESTAMP NULL,
    reward_care_coins INT NOT NULL DEFAULT 0,
    reward_type VARCHAR(50) DEFAULT 'care_coins',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_date (user_id, quest_date),
    INDEX idx_quest_type (quest_type),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Care Coins Transactions
CREATE TABLE IF NOT EXISTS care_coin_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount INT NOT NULL,
    transaction_type ENUM('earned', 'spent', 'converted') NOT NULL,
    description TEXT,
    related_item_id INT NULL,
    related_quest_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_date (user_id, created_at),
    INDEX idx_transaction_type (transaction_type),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Add care_coins column to users table
ALTER TABLE users ADD COLUMN IF NOT EXISTS care_coins INT DEFAULT 0;
ALTER TABLE users ADD COLUMN IF NOT EXISTS birth_date DATE NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS gaming_preference ENUM('educational', 'mixed', 'traditional') DEFAULT 'educational';

-- Community Pet Care System
CREATE TABLE IF NOT EXISTS community_pet_care (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pet_id INT NOT NULL,
    caregiver_user_id INT NOT NULL,
    pet_owner_id INT NOT NULL,
    care_type ENUM('feeding', 'playing', 'grooming', 'training') NOT NULL,
    care_coins_earned INT DEFAULT 5,
    care_message TEXT,
    cared_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    appreciation_given BOOLEAN DEFAULT FALSE,
    INDEX idx_pet_id (pet_id),
    INDEX idx_caregiver (caregiver_user_id),
    INDEX idx_owner (pet_owner_id),
    FOREIGN KEY (pet_id) REFERENCES pets(id) ON DELETE CASCADE,
    FOREIGN KEY (caregiver_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (pet_owner_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Peer-to-Peer Pet Services
CREATE TABLE IF NOT EXISTS pet_services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    provider_user_id INT NOT NULL,
    service_type ENUM('pet_sitting', 'training', 'grooming', 'breeding_assistance') NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    cost_care_coins INT NOT NULL,
    cost_crypto_amount DECIMAL(20,8) NULL,
    cost_crypto_type VARCHAR(10) NULL,
    availability_schedule JSON,
    rating DECIMAL(2,1) DEFAULT 0.0,
    total_reviews INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_provider (provider_user_id),
    INDEX idx_service_type (service_type),
    INDEX idx_active (is_active),
    FOREIGN KEY (provider_user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Pet Service Bookings
CREATE TABLE IF NOT EXISTS pet_service_bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    service_id INT NOT NULL,
    client_user_id INT NOT NULL,
    pet_id INT NOT NULL,
    booking_date DATE NOT NULL,
    booking_time TIME NOT NULL,
    duration_hours INT DEFAULT 1,
    status ENUM('pending', 'confirmed', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
    payment_type ENUM('care_coins', 'crypto') NOT NULL,
    payment_amount_coins INT NULL,
    payment_amount_crypto DECIMAL(20,8) NULL,
    payment_crypto_type VARCHAR(10) NULL,
    special_instructions TEXT,
    completed_at TIMESTAMP NULL,
    rating INT NULL CHECK (rating >= 1 AND rating <= 5),
    review_text TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_service_id (service_id),
    INDEX idx_client (client_user_id),
    INDEX idx_pet_id (pet_id),
    INDEX idx_booking_date (booking_date),
    INDEX idx_status (status),
    FOREIGN KEY (service_id) REFERENCES pet_services(id) ON DELETE CASCADE,
    FOREIGN KEY (client_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (pet_id) REFERENCES pets(id) ON DELETE CASCADE
);

-- Educational Content System
CREATE TABLE IF NOT EXISTS educational_modules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    category ENUM('animal_care', 'biology', 'ecology', 'responsibility', 'empathy') NOT NULL,
    difficulty_level ENUM('beginner', 'intermediate', 'advanced') DEFAULT 'beginner',
    content_type ENUM('article', 'video', 'interactive', 'quiz') NOT NULL,
    content_data JSON,
    care_coins_reward INT DEFAULT 10,
    estimated_duration_minutes INT DEFAULT 5,
    prerequisites JSON,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_category (category),
    INDEX idx_difficulty (difficulty_level),
    INDEX idx_active (is_active)
);

-- User Educational Progress
CREATE TABLE IF NOT EXISTS user_educational_progress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    module_id INT NOT NULL,
    progress_percentage INT DEFAULT 0 CHECK (progress_percentage >= 0 AND progress_percentage <= 100),
    completed BOOLEAN DEFAULT FALSE,
    completed_at TIMESTAMP NULL,
    quiz_score INT NULL,
    time_spent_minutes INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_module (user_id, module_id),
    INDEX idx_user_id (user_id),
    INDEX idx_completed (completed),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (module_id) REFERENCES educational_modules(id) ON DELETE CASCADE
);

-- Metaverse Integration Tables
CREATE TABLE IF NOT EXISTS metaverse_worlds (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    world_type ENUM('park', 'beach', 'forest', 'city', 'space_station', 'educational_zone') NOT NULL,
    environment_config JSON,
    weather_enabled BOOLEAN DEFAULT TRUE,
    max_concurrent_users INT DEFAULT 50,
    educational_content_ids JSON,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_world_type (world_type),
    INDEX idx_active (is_active)
);

-- User World Sessions
CREATE TABLE IF NOT EXISTS user_world_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    world_id INT NOT NULL,
    pet_ids JSON,
    session_start TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    session_end TIMESTAMP NULL,
    activities_completed JSON,
    care_coins_earned INT DEFAULT 0,
    social_interactions_count INT DEFAULT 0,
    educational_modules_accessed JSON,
    INDEX idx_user_id (user_id),
    INDEX idx_world_id (world_id),
    INDEX idx_session_date (session_start),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (world_id) REFERENCES metaverse_worlds(id) ON DELETE CASCADE
);

-- Pet Avatar Customizations
CREATE TABLE IF NOT EXISTS pet_avatar_customizations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pet_id INT NOT NULL,
    avatar_data JSON NOT NULL,
    accessories JSON,
    animation_preferences JSON,
    environment_preferences JSON,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_pet_avatar (pet_id),
    FOREIGN KEY (pet_id) REFERENCES pets(id) ON DELETE CASCADE
);

-- Social Interactions in Metaverse
CREATE TABLE IF NOT EXISTS metaverse_social_interactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    target_user_id INT NOT NULL,
    world_id INT NOT NULL,
    interaction_type ENUM('wave', 'chat', 'pet_play', 'collaborative_game', 'help_request', 'help_given') NOT NULL,
    interaction_data JSON,
    care_coins_earned INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_target_user (target_user_id),
    INDEX idx_world_id (world_id),
    INDEX idx_interaction_type (interaction_type),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (target_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (world_id) REFERENCES metaverse_worlds(id) ON DELETE CASCADE
);

-- Insert sample educational modules
INSERT INTO educational_modules (title, description, category, content_type, content_data, care_coins_reward) VALUES
('Pet Nutrition Basics', 'Learn about proper nutrition for different types of pets', 'animal_care', 'interactive', 
 '{"lessons": ["Types of pet food", "Nutritional requirements", "Feeding schedules"], "quiz_questions": 5}', 15),
('Understanding Pet Behavior', 'Decode what your pet is trying to tell you', 'animal_care', 'video',
 '{"video_url": "/educational/pet-behavior.mp4", "duration": 480}', 20),
('Ecosystem Balance', 'How pets and wildlife interact in nature', 'ecology', 'article',
 '{"content_sections": ["Predator-prey relationships", "Habitat preservation", "Human impact"]}', 25),
('Responsible Pet Ownership', 'The commitments and joys of caring for animals', 'responsibility', 'interactive',
 '{"scenarios": ["Daily care routines", "Emergency situations", "Long-term planning"]}', 18);

-- Insert sample metaverse worlds
INSERT INTO metaverse_worlds (name, description, world_type, environment_config) VALUES
('Sunny Meadow Park', 'A peaceful park with rolling hills and flower fields', 'park',
 '{"terrain": "grassy_hills", "weather": ["sunny", "partly_cloudy"], "flora": "wildflowers", "fauna": "butterflies"}'),
('Crystal Cove Beach', 'A pristine beach with gentle waves and sandy shores', 'beach',
 '{"terrain": "sandy_beach", "weather": ["sunny", "breezy"], "water": "crystal_clear", "activities": "swimming"}'),
('Whispering Woods', 'A magical forest filled with ancient trees and hidden paths', 'forest',
 '{"terrain": "forest_paths", "weather": ["dappled_sunlight", "misty"], "trees": "oak_birch_pine", "secrets": "hidden_groves"}'),
('Learning Laboratory', 'An interactive educational space for discovering animal science', 'educational_zone',
 '{"facilities": ["biology_lab", "ecology_center", "care_training"], "interactive_elements": true}');