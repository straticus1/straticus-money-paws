-- Money Paws Database Schema
-- Complete database structure for cryptocurrency-powered pet platform
-- Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>

-- Users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255),
    name VARCHAR(100) NOT NULL,
    provider ENUM('local', 'google', 'facebook', 'apple', 'twitter') DEFAULT 'local',
    provider_id VARCHAR(255),
    avatar VARCHAR(255),
    email_verified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_admin BOOLEAN DEFAULT FALSE
);

-- Pets table
CREATE TABLE pets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    description TEXT,
    file_size INT,
    mime_type VARCHAR(100),
    likes_count INT DEFAULT 0,
    views_count INT DEFAULT 0,
    is_public BOOLEAN DEFAULT TRUE,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_for_sale BOOLEAN DEFAULT FALSE,
    sale_price_usd DECIMAL(10, 2) NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Pet likes table
CREATE TABLE pet_likes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    pet_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (pet_id) REFERENCES pets(id) ON DELETE CASCADE,
    UNIQUE KEY unique_like (user_id, pet_id)
);

-- Sessions table for better session management
CREATE TABLE user_sessions (
    id VARCHAR(128) PRIMARY KEY,
    user_id INT NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- OAuth states table for security
CREATE TABLE oauth_states (
    state VARCHAR(255) PRIMARY KEY,
    provider VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL
);

-- Game tables
CREATE TABLE games (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    game_type ENUM('paw_match', 'pet_battle', 'treasure_hunt') DEFAULT 'paw_match',
    entry_fee_usd DECIMAL(10,2) NOT NULL,
    crypto_type VARCHAR(10) NOT NULL,
    crypto_amount DECIMAL(20,8) NOT NULL,
    score INT DEFAULT 0,
    status ENUM('pending', 'playing', 'completed', 'cancelled') DEFAULT 'pending',
    reward_usd DECIMAL(10,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Crypto transactions
CREATE TABLE crypto_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    transaction_type ENUM('deposit', 'withdrawal', 'game_entry', 'ai_generation', 'subscription') NOT NULL,
    crypto_type VARCHAR(10) NOT NULL,
    crypto_amount DECIMAL(20,8) NOT NULL,
    usd_amount DECIMAL(10,2) NOT NULL,
    coinbase_transaction_id VARCHAR(255),
    status ENUM('pending', 'confirmed', 'failed', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    confirmed_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- AI pet generation requests
CREATE TABLE ai_generations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    description TEXT NOT NULL,
    animal_type VARCHAR(50) DEFAULT 'dog',
    style VARCHAR(50) DEFAULT 'realistic',
    generated_image_url VARCHAR(500),
    cost_usd DECIMAL(10,2) NOT NULL,
    crypto_type VARCHAR(10) NOT NULL,
    crypto_amount DECIMAL(20,8) NOT NULL,
    status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- User crypto balances
CREATE TABLE user_balances (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    crypto_type VARCHAR(10) NOT NULL,
    balance DECIMAL(20,8) DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_crypto (user_id, crypto_type)
);

-- Pet care system tables
CREATE TABLE pet_stats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pet_id INT NOT NULL,
    hunger_level INT DEFAULT 50 COMMENT 'Range 0-100, 0=starving, 100=full',
    happiness_level INT DEFAULT 50 COMMENT 'Range 0-100, 0=sad, 100=ecstatic',
    last_fed TIMESTAMP NULL,
    last_treated TIMESTAMP NULL,
    total_feeds INT DEFAULT 0,
    total_treats INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (pet_id) REFERENCES pets(id) ON DELETE CASCADE,
    UNIQUE KEY unique_pet_stats (pet_id)
);

-- Store items (food, treats, toys)
CREATE TABLE store_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    item_type ENUM('food', 'treat', 'toy', 'accessory') NOT NULL,
    price_usd DECIMAL(10,2) NOT NULL,
    hunger_restore INT DEFAULT 0 COMMENT 'How much hunger this item restores',
    happiness_boost INT DEFAULT 0 COMMENT 'How much happiness this item adds',
    duration_hours INT DEFAULT 0 COMMENT 'How long the effect lasts',
    emoji VARCHAR(10) DEFAULT '🍖',
    age_restricted BOOLEAN DEFAULT FALSE COMMENT 'Requires 18+ verification',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- User inventory
CREATE TABLE user_inventory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    item_id INT NOT NULL,
    quantity INT DEFAULT 0,
    purchased_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES store_items(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_item (user_id, item_id)
);

-- Notifications for user interactions
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recipient_user_id INT NOT NULL,
    sender_user_id INT NOT NULL,
    pet_id INT NULL,
    interaction_id INT NULL,
    notification_type ENUM('feed', 'treat', 'like', 'adoption', 'new_follower') NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (recipient_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (pet_id) REFERENCES pets(id) ON DELETE CASCADE,
    FOREIGN KEY (interaction_id) REFERENCES pet_interactions(id) ON DELETE SET NULL
);

-- Pet interactions (feeding, treating by other users)
CREATE TABLE pet_interactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pet_id INT NOT NULL,
    user_id INT NOT NULL COMMENT 'User who performed the interaction',
    interaction_type ENUM('feed', 'treat', 'play', 'pet') NOT NULL,
    item_id INT NULL COMMENT 'Item used in interaction',
    happiness_gained INT DEFAULT 0,
    hunger_restored INT DEFAULT 0,
    cost_usd DECIMAL(10,2) DEFAULT 0,
    crypto_type VARCHAR(10) NULL,
    crypto_amount DECIMAL(20,8) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pet_id) REFERENCES pets(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES store_items(id) ON DELETE SET NULL
);

-- Add age verification field to users table
ALTER TABLE users ADD COLUMN age_verified BOOLEAN DEFAULT FALSE;
ALTER TABLE users ADD COLUMN birth_date DATE NULL;

-- Add vacation mode fields to users table
ALTER TABLE users ADD COLUMN is_on_vacation BOOLEAN DEFAULT FALSE;
ALTER TABLE users ADD COLUMN vacation_delegate_id INT NULL;
ALTER TABLE users ADD COLUMN vacation_reserved_funds DECIMAL(10, 2) DEFAULT 0.00;
ALTER TABLE users ADD COLUMN vacation_start_date TIMESTAMP NULL;
ALTER TABLE users ADD CONSTRAINT fk_vacation_delegate FOREIGN KEY (vacation_delegate_id) REFERENCES users(id) ON DELETE SET NULL;

-- Insert default store items
INSERT INTO store_items (name, description, item_type, price_usd, hunger_restore, happiness_boost, duration_hours, emoji, age_restricted) VALUES
-- Dog Food
('Basic Dog Food', 'Nutritious kibble for hungry pups', 'food', 0.25, 30, 5, 6, '🍖', FALSE),
('Premium Dog Food', 'High-quality nutrition for active dogs', 'food', 0.45, 35, 8, 8, '🥩', FALSE),
('Grain-Free Dog Food', 'Natural ingredients for sensitive stomachs', 'food', 0.65, 40, 10, 10, '🌾', FALSE),

-- Cat Food
('Basic Cat Food', 'Standard nutrition for felines', 'food', 0.30, 25, 5, 6, '🐟', FALSE),
('Premium Cat Food', 'Gourmet meal for discerning felines', 'food', 0.50, 40, 10, 8, '🍤', FALSE),
('Wet Cat Food', 'Delicious pâté with extra moisture', 'food', 0.75, 45, 15, 6, '🥫', FALSE),

-- Bird Food
('Bird Seed Mix', 'Nutritious blend for all bird types', 'food', 0.20, 30, 8, 8, '🌻', FALSE),
('Premium Pellets', 'Complete nutrition for exotic birds', 'food', 0.40, 35, 12, 10, '🦜', FALSE),

-- Rabbit Food
('Timothy Hay', 'Essential fiber for rabbits', 'food', 0.35, 25, 5, 12, '🌾', FALSE),
('Rabbit Pellets', 'Balanced nutrition for bunnies', 'food', 0.45, 30, 8, 8, '🐰', FALSE),

-- Dog Treats
('Bacon Treats', 'Crispy bacon strips that pets love', 'treat', 0.75, 10, 25, 4, '🥓', FALSE),
('Peanut Butter Bone', 'Long-lasting chew toy with PB filling', 'treat', 1.00, 15, 30, 12, '🦴', FALSE),
('Training Treats', 'Small rewards for good behavior', 'treat', 0.50, 5, 20, 2, '🍪', FALSE),

-- Cat Treats
('Tuna Treats', 'Freeze-dried tuna for cats', 'treat', 0.85, 8, 30, 3, '🐟', FALSE),
('Chicken Jerky', 'High-protein strips for felines', 'treat', 0.95, 12, 25, 4, '🍗', FALSE),
('Catnip Treats', 'Infused with premium catnip', 'treat', 0.60, 5, 35, 6, '🌿', FALSE),

-- Special Items
('Pure Catnip', 'Premium dried catnip for cats only', 'treat', 1.25, 0, 40, 8, '🌿', FALSE),
('CBD Dog Treats', 'Calming treats for anxious dogs (18+ only)', 'treat', 3.50, 5, 45, 12, '🌱', TRUE),
('CBD Cat Oil', 'Natural wellness drops for cats (18+ only)', 'treat', 4.25, 0, 50, 24, '💧', TRUE),

-- Water & Beverages
('Fresh Water Bowl', 'Clean, refreshing water for dogs', 'food', 0.10, 20, 5, 3, '💧', FALSE),
('Filtered Water', 'Premium filtered water for all pets', 'food', 0.15, 25, 8, 4, '🚰', FALSE),
('Cat Milk', 'Lactose-free milk specially for cats', 'food', 0.35, 15, 20, 2, '🥛', FALSE),
('Electrolyte Water', 'Hydrating solution for active pets', 'food', 0.25, 30, 10, 6, '⚡', FALSE),

-- Toys & Accessories
('Catnip Mouse', 'Interactive toy that drives cats wild', 'toy', 0.50, 0, 20, 6, '🐭', FALSE),
('Tennis Ball', 'Classic fetch toy for active dogs', 'toy', 0.30, 0, 15, 4, '🎾', FALSE),
('Rope Toy', 'Durable braided rope for chewing', 'toy', 0.45, 0, 18, 8, '🪢', FALSE),
('Feather Wand', 'Interactive play wand for cats', 'toy', 0.65, 0, 25, 5, '🪶', FALSE),
('Puzzle Toy', 'Mental stimulation for smart pets', 'toy', 1.20, 0, 30, 10, '🧩', FALSE),

-- Premium Services
('Luxury Spa Treatment', 'Premium pampering session', 'treat', 2.50, 5, 50, 24, '✨', FALSE),
('Professional Grooming', 'Full service grooming package', 'accessory', 3.75, 0, 40, 48, '✂️', FALSE),
('Pet Massage', 'Relaxing therapeutic massage', 'treat', 2.00, 0, 35, 12, '💆', FALSE);

-- Indexes for performance
CREATE INDEX idx_pets_user_id ON pets(user_id);
CREATE INDEX idx_pets_uploaded_at ON pets(uploaded_at);
CREATE INDEX idx_pets_is_public ON pets(is_public);
CREATE INDEX idx_crypto_transactions_user_id ON crypto_transactions(user_id);
CREATE INDEX idx_crypto_transactions_type ON crypto_transactions(transaction_type);
CREATE INDEX idx_crypto_transactions_date ON crypto_transactions(created_at);
CREATE INDEX idx_pet_stats_pet_id ON pet_stats(pet_id);
CREATE INDEX idx_user_inventory_user_id ON user_inventory(user_id);
CREATE INDEX idx_pet_interactions_pet_id ON pet_interactions(pet_id);
CREATE INDEX idx_pet_interactions_user_id ON pet_interactions(user_id);

-- Two-Factor Authentication
CREATE TABLE `user_2fa_settings` (
  `user_id` int(11) NOT NULL,
  `mfa_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `mfa_method` varchar(255) DEFAULT NULL,
  `phone_number` varchar(255) DEFAULT NULL,
  `totp_secret` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `verification_codes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL COMMENT 'e.g., email, sms, withdrawal',
  `code` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE security_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    event_type VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    details TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE withdrawal_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    crypto_type VARCHAR(10) NOT NULL,
    crypto_amount DECIMAL(20, 8) NOT NULL,
    recipient_address VARCHAR(255) NOT NULL,
    status ENUM('pending_verification', 'pending_processing', 'completed', 'failed', 'cancelled') DEFAULT 'pending_verification',
    verification_code VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
CREATE INDEX idx_notifications_recipient_id ON notifications(recipient_user_id);
CREATE INDEX idx_notifications_is_read ON notifications(is_read);

-- Messaging Tables
CREATE TABLE conversations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_one_id INT NOT NULL,
    user_two_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_conversation (user_one_id, user_two_id),
    FOREIGN KEY (user_one_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (user_two_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT NOT NULL,
    sender_id INT NOT NULL,
    recipient_id INT NOT NULL,
    body TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (recipient_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX idx_messages_conversation ON messages(conversation_id);
CREATE INDEX idx_messages_sender ON messages(sender_id);
CREATE INDEX idx_messages_recipient ON messages(recipient_id);
CREATE INDEX idx_verification_codes_user_type ON verification_codes(user_id, code_type);
CREATE INDEX idx_verification_codes_expires ON verification_codes(expires_at);
CREATE INDEX idx_security_logs_user_id ON security_logs(user_id);
CREATE INDEX idx_security_logs_event ON security_logs(event_type);
CREATE INDEX idx_withdrawal_requests_user_id ON withdrawal_requests(user_id);
CREATE INDEX idx_withdrawal_requests_status ON withdrawal_requests(status);
CREATE INDEX idx_notifications_is_read ON notifications(is_read);
