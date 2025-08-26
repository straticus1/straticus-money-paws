-- Money Paws Database Schema (SQLite Version)
-- This schema is adapted for SQLite for testing and development purposes.
-- Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>

PRAGMA foreign_keys = ON;

-- Users table
CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255),
    name VARCHAR(100) NOT NULL,
    provider TEXT CHECK(provider IN ('local', 'google', 'facebook', 'apple', 'twitter')) DEFAULT 'local',
    provider_id VARCHAR(255),
    avatar VARCHAR(255),
    email_verified BOOLEAN DEFAULT 0,
    birth_date DATE NULL,
    age_verified BOOLEAN DEFAULT 0,
    balance REAL DEFAULT 100.00 NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    is_admin BOOLEAN DEFAULT 0,
    is_on_vacation BOOLEAN DEFAULT 0,
    vacation_delegate_id INTEGER NULL,
    vacation_reserved_funds REAL DEFAULT 0.00,
    vacation_start_date DATETIME NULL,
    FOREIGN KEY (vacation_delegate_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Pets table
CREATE TABLE pets (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    filename VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    description TEXT,
    file_size INTEGER,
    mime_type VARCHAR(100),
    likes_count INTEGER DEFAULT 0,
    views_count INTEGER DEFAULT 0,
    is_public BOOLEAN DEFAULT 1,
    uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    is_for_sale BOOLEAN DEFAULT 0,
    sale_price_usd REAL NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Pet Health Table
CREATE TABLE IF NOT EXISTS pet_health (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    pet_id INTEGER NOT NULL,
    health_points INTEGER NOT NULL DEFAULT 100 CHECK(health_points >= 0 AND health_points <= 100),
    status VARCHAR(50) NOT NULL DEFAULT 'healthy', -- e.g., healthy, sick, injured
    last_checkup_at TIMESTAMP,
    notes TEXT,
    FOREIGN KEY (pet_id) REFERENCES pets(id) ON DELETE CASCADE
);

-- Illnesses Catalog Table
CREATE TABLE IF NOT EXISTS illnesses (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    severity INTEGER NOT NULL DEFAULT 1 CHECK(severity >= 1 AND severity <= 10),
    treatment_item_id INTEGER, -- FK to store_items for specific medicine
    treatment_cost DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    FOREIGN KEY (treatment_item_id) REFERENCES store_items(id) ON DELETE SET NULL
);

-- Pet Active Illnesses Table (join table)
CREATE TABLE IF NOT EXISTS pet_active_illnesses (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    pet_id INTEGER NOT NULL,
    illness_id INTEGER NOT NULL,
    diagnosed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pet_id) REFERENCES pets(id) ON DELETE CASCADE,
    FOREIGN KEY (illness_id) REFERENCES illnesses(id) ON DELETE CASCADE
);

-- Pet likes table
CREATE TABLE pet_likes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    pet_id INTEGER NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (pet_id) REFERENCES pets(id) ON DELETE CASCADE,
    UNIQUE (user_id, pet_id)
);

-- Sessions table for better session management
CREATE TABLE user_sessions (
    id VARCHAR(128) PRIMARY KEY,
    user_id INTEGER NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- OAuth states table for security
CREATE TABLE oauth_states (
    state VARCHAR(255) PRIMARY KEY,
    provider VARCHAR(50) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL
);

-- Game tables
CREATE TABLE games (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    game_type TEXT CHECK(game_type IN ('paw_match', 'pet_battle', 'treasure_hunt')) DEFAULT 'paw_match',
    entry_fee_usd REAL NOT NULL,
    crypto_type VARCHAR(10) NOT NULL,
    crypto_amount REAL NOT NULL,
    score INTEGER DEFAULT 0,
    status TEXT CHECK(status IN ('pending', 'playing', 'completed', 'cancelled')) DEFAULT 'pending',
    reward_usd REAL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Crypto transactions
CREATE TABLE crypto_transactions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    transaction_type TEXT CHECK(transaction_type IN ('deposit', 'withdrawal', 'game_entry', 'ai_generation', 'subscription')) NOT NULL,
    crypto_type VARCHAR(10) NOT NULL,
    crypto_amount REAL NOT NULL,
    usd_amount REAL NOT NULL,
    coinbase_transaction_id VARCHAR(255),
    status TEXT CHECK(status IN ('pending', 'confirmed', 'failed', 'cancelled')) DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    confirmed_at DATETIME NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- AI pet generation requests
CREATE TABLE ai_generations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    description TEXT NOT NULL,
    animal_type VARCHAR(50) DEFAULT 'dog',
    style VARCHAR(50) DEFAULT 'realistic',
    generated_image_url VARCHAR(500),
    cost_usd REAL NOT NULL,
    crypto_type VARCHAR(10) NOT NULL,
    crypto_amount REAL NOT NULL,
    status TEXT CHECK(status IN ('pending', 'processing', 'completed', 'failed')) DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- User crypto balances
CREATE TABLE user_balances (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    crypto_type VARCHAR(10) NOT NULL,
    balance REAL DEFAULT 0,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE (user_id, crypto_type)
);

-- Pet care system tables
CREATE TABLE pet_stats (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    pet_id INTEGER NOT NULL,
    hunger_level INTEGER DEFAULT 50,
    happiness_level INTEGER DEFAULT 50,
    last_fed DATETIME NULL,
    last_treated DATETIME NULL,
    total_feeds INTEGER DEFAULT 0,
    total_treats INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pet_id) REFERENCES pets(id) ON DELETE CASCADE,
    UNIQUE (pet_id)
);

-- Store items (food, treats, toys)
CREATE TABLE store_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    item_type TEXT CHECK(item_type IN ('food', 'treat', 'toy', 'accessory')) NOT NULL,
    price_usd REAL NOT NULL,
    hunger_restore INTEGER DEFAULT 0,
    happiness_boost INTEGER DEFAULT 0,
    duration_hours INTEGER DEFAULT 0,
    emoji VARCHAR(10) DEFAULT '🍖',
    age_restricted BOOLEAN DEFAULT 0,
    is_active BOOLEAN DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- User inventory
CREATE TABLE user_inventory (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    item_id INTEGER NOT NULL,
    quantity INTEGER DEFAULT 0,
    purchased_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES store_items(id) ON DELETE CASCADE,
    UNIQUE (user_id, item_id)
);

-- Pet interactions (feeding, treating by other users)
CREATE TABLE notifications (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    recipient_user_id INTEGER NOT NULL,
    sender_user_id INTEGER NOT NULL,
    pet_id INTEGER,
    interaction_id INTEGER,
    notification_type TEXT CHECK(notification_type IN ('feed', 'treat', 'like', 'adoption', 'new_follower')) NOT NULL,
    is_read INTEGER DEFAULT 0,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (recipient_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (pet_id) REFERENCES pets(id) ON DELETE CASCADE,
    FOREIGN KEY (interaction_id) REFERENCES pet_interactions(id) ON DELETE SET NULL
);

CREATE TABLE pet_interactions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    pet_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    interaction_type TEXT CHECK(interaction_type IN ('feed', 'treat', 'play', 'pet')) NOT NULL,
    item_id INTEGER NULL,
    happiness_gained INTEGER DEFAULT 0,
    hunger_restored INTEGER DEFAULT 0,
    cost_usd REAL DEFAULT 0,
    crypto_type VARCHAR(10) NULL,
    crypto_amount REAL NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pet_id) REFERENCES pets(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES store_items(id) ON DELETE SET NULL
);

-- Insert default store items
INSERT INTO store_items (name, description, item_type, price_usd, hunger_restore, happiness_boost, duration_hours, emoji, age_restricted) VALUES
-- Dog Food
('Basic Dog Food', 'Nutritious kibble for hungry pups', 'food', 0.25, 30, 5, 6, '🍖', 0),
('Premium Dog Food', 'High-quality nutrition for active dogs', 'food', 0.45, 35, 8, 8, '🥩', 0),
('Grain-Free Dog Food', 'Natural ingredients for sensitive stomachs', 'food', 0.65, 40, 10, 10, '🌾', 0),

-- Cat Food
('Basic Cat Food', 'Standard nutrition for felines', 'food', 0.30, 25, 5, 6, '🐟', 0),
('Premium Cat Food', 'Gourmet meal for discerning felines', 'food', 0.50, 40, 10, 8, '🍤', 0),
('Wet Cat Food', 'Delicious pâté with extra moisture', 'food', 0.75, 45, 15, 6, '🥫', 0),

-- Bird Food
('Bird Seed Mix', 'Nutritious blend for all bird types', 'food', 0.20, 30, 8, 8, '🌻', 0),
('Premium Pellets', 'Complete nutrition for exotic birds', 'food', 0.40, 35, 12, 10, '🦜', 0),

-- Rabbit Food
('Timothy Hay', 'Essential fiber for rabbits', 'food', 0.35, 25, 5, 12, '🌾', 0),
('Rabbit Pellets', 'Balanced nutrition for bunnies', 'food', 0.45, 30, 8, 8, '🐰', 0),

-- Dog Treats
('Bacon Treats', 'Crispy bacon strips that pets love', 'treat', 0.75, 10, 25, 4, '🥓', 0),
('Peanut Butter Bone', 'Long-lasting chew toy with PB filling', 'treat', 1.00, 15, 30, 12, '🦴', 0),
('Training Treats', 'Small rewards for good behavior', 'treat', 0.50, 5, 20, 2, '🍪', 0),

-- Cat Treats
('Tuna Treats', 'Freeze-dried tuna for cats', 'treat', 0.85, 8, 30, 3, '🐟', 0),
('Chicken Jerky', 'High-protein strips for felines', 'treat', 0.95, 12, 25, 4, '🍗', 0),
('Catnip Treats', 'Infused with premium catnip', 'treat', 0.60, 5, 35, 6, '🌿', 0),

-- Special Items
('Pure Catnip', 'Premium dried catnip for cats only', 'treat', 1.25, 0, 40, 8, '🌿', 0),
('CBD Dog Treats', 'Calming treats for anxious dogs (18+ only)', 'treat', 3.50, 5, 45, 12, '🌱', 1),
('CBD Cat Oil', 'Natural wellness drops for cats (18+ only)', 'treat', 4.25, 0, 50, 24, '💧', 1),

-- Water & Beverages
('Fresh Water Bowl', 'Clean, refreshing water for dogs', 'food', 0.10, 20, 5, 3, '💧', 0),
('Filtered Water', 'Premium filtered water for all pets', 'food', 0.15, 25, 8, 4, '🚰', 0),
('Cat Milk', 'Lactose-free milk specially for cats', 'food', 0.35, 15, 20, 2, '🥛', 0),
('Electrolyte Water', 'Hydrating solution for active pets', 'food', 0.25, 30, 10, 6, '⚡', 0),

-- Toys & Accessories
('Catnip Mouse', 'Interactive toy that drives cats wild', 'toy', 0.50, 0, 20, 6, '🐭', 0),
('Tennis Ball', 'Classic fetch toy for active dogs', 'toy', 0.30, 0, 15, 4, '🎾', 0),
('Rope Toy', 'Durable braided rope for chewing', 'toy', 0.45, 0, 18, 8, '🪢', 0),
('Feather Wand', 'Interactive play wand for cats', 'toy', 0.65, 0, 25, 5, '🪶', 0),
('Puzzle Toy', 'Mental stimulation for smart pets', 'toy', 1.20, 0, 30, 10, '🧩', 0),

-- Premium Services
('Luxury Spa Treatment', 'Premium pampering session', 'treat', 2.50, 5, 50, 24, '✨', 0),
('Professional Grooming', 'Full service grooming package', 'accessory', 3.75, 0, 40, 48, '✂️', 0),
('Pet Massage', 'Relaxing therapeutic massage', 'treat', 2.00, 0, 35, 12, '💆', 0);

-- Indexes for performance
CREATE INDEX idx_pets_user_id ON pets(user_id);
CREATE INDEX idx_notifications_recipient ON notifications(recipient_user_id);

-- Messaging Tables
CREATE TABLE conversations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_one_id INTEGER NOT NULL,
    user_two_id INTEGER NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (user_one_id, user_two_id),
    FOREIGN KEY (user_one_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (user_two_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE messages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    conversation_id INTEGER NOT NULL,
    sender_id INTEGER NOT NULL,
    recipient_id INTEGER NOT NULL,
    body TEXT NOT NULL,
    is_read BOOLEAN DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (recipient_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX idx_messages_conversation ON messages(conversation_id);
CREATE INDEX idx_messages_sender ON messages(sender_id);
CREATE INDEX idx_messages_recipient ON messages(recipient_id);
CREATE INDEX idx_notifications_is_read ON notifications(is_read);
CREATE INDEX idx_pets_is_public ON pets(is_public);
CREATE INDEX idx_crypto_transactions_user_id ON crypto_transactions(user_id);
CREATE INDEX idx_crypto_transactions_type ON crypto_transactions(transaction_type);
CREATE INDEX idx_crypto_transactions_date ON crypto_transactions(created_at);
CREATE INDEX idx_user_inventory_user_id ON user_inventory(user_id);
CREATE INDEX idx_pet_interactions_pet_id ON pet_interactions(pet_id);
CREATE INDEX idx_pet_interactions_user_id ON pet_interactions(user_id);

-- Two-Factor Authentication
CREATE TABLE user_2fa_settings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    mfa_enabled BOOLEAN DEFAULT 0,
    mfa_method TEXT CHECK(mfa_method IN ('sms', 'email', 'authenticator')) NULL,
    phone_number VARCHAR(20) NULL,
    totp_secret VARCHAR(255) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE (user_id)
);

CREATE TABLE verification_codes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    code_type TEXT CHECK(code_type IN ('email_verify', 'password_reset', '2fa_sms', '2fa_email')) NOT NULL,
    code VARCHAR(255) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE security_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    event_type VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    details TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE withdrawal_requests (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    crypto_type VARCHAR(10) NOT NULL,
    crypto_amount REAL NOT NULL,
    recipient_address VARCHAR(255) NOT NULL,
    status TEXT CHECK(status IN ('pending_verification', 'pending_processing', 'completed', 'failed', 'cancelled')) DEFAULT 'pending_verification',
    verification_code VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX idx_verification_codes_user_type ON verification_codes(user_id, code_type);
CREATE INDEX idx_verification_codes_expires ON verification_codes(expires_at);
CREATE INDEX idx_security_logs_user_id ON security_logs(user_id);
CREATE INDEX idx_security_logs_event ON security_logs(event_type);
CREATE INDEX idx_withdrawal_requests_user_id ON withdrawal_requests(user_id);
CREATE INDEX idx_withdrawal_requests_status ON withdrawal_requests(status);

-- Quests and Achievements Schema (SQLite)
CREATE TABLE quests (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  quest_name VARCHAR(255) NOT NULL,
  quest_description TEXT NOT NULL,
  quest_type TEXT CHECK(quest_type IN ('daily', 'weekly', 'event', 'main')) NOT NULL DEFAULT 'daily',
  action_type VARCHAR(50) NOT NULL,
  goal_value INTEGER NOT NULL,
  reward_currency VARCHAR(10) NOT NULL DEFAULT 'paw_coins',
  reward_amount INTEGER NOT NULL,
  is_active BOOLEAN NOT NULL DEFAULT 1,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE user_quests (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL,
  quest_id INTEGER NOT NULL,
  progress INTEGER NOT NULL DEFAULT 0,
  status TEXT CHECK(status IN ('in_progress', 'completed', 'claimed')) NOT NULL DEFAULT 'in_progress',
  assigned_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  completed_at DATETIME NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (quest_id) REFERENCES quests(id) ON DELETE CASCADE
);

CREATE TABLE achievements (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  achievement_name VARCHAR(255) NOT NULL,
  achievement_description TEXT NOT NULL,
  action_type VARCHAR(50) NOT NULL,
  goal_value INTEGER NOT NULL,
  reward_currency VARCHAR(10) NOT NULL DEFAULT 'gems',
  reward_amount INTEGER NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE user_achievements (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL,
  achievement_id INTEGER NOT NULL,
  unlocked_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (achievement_id) REFERENCES achievements(id) ON DELETE CASCADE,
  UNIQUE (user_id, achievement_id)
);

-- Insert Default Quests
INSERT INTO quests (quest_name, quest_description, quest_type, action_type, goal_value, reward_currency, reward_amount, is_active) VALUES
('Feed a Friend', 'Feed any pet 5 times.', 'daily', 'feed_pet', 5, 'paw_coins', 50, 1),
('Social Butterfly', 'Add 2 new friends.', 'daily', 'add_friend', 2, 'paw_coins', 100, 1),
('Generous Gifter', 'Send a gift to a friend.', 'daily', 'send_gift', 1, 'paw_coins', 75, 1),
('Window Shopper', 'Visit the store page.', 'daily', 'visit_store', 1, 'paw_coins', 20, 1),
('Play Time', 'Use a toy on any pet 3 times.', 'daily', 'use_toy', 3, 'paw_coins', 60, 1);

-- Pet Personalities and Social Bonds
CREATE TABLE pet_personalities (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  pet_id INTEGER NOT NULL,
  trait VARCHAR(50) NOT NULL, -- e.g., 'bravery', 'friendliness', 'curiosity'
  value INTEGER NOT NULL DEFAULT 50, -- A score from 0-100
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (pet_id) REFERENCES pets(id) ON DELETE CASCADE,
  UNIQUE (pet_id, trait)
);

CREATE TABLE pet_social_bonds (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  pet_one_id INTEGER NOT NULL,
  pet_two_id INTEGER NOT NULL,
  bond_type TEXT CHECK(bond_type IN ('friendship', 'rivalry', 'family')) NOT NULL,
  bond_strength INTEGER NOT NULL DEFAULT 0, -- A score representing the strength of the bond
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (pet_one_id) REFERENCES pets(id) ON DELETE CASCADE,
  FOREIGN KEY (pet_two_id) REFERENCES pets(id) ON DELETE CASCADE,
  UNIQUE (pet_one_id, pet_two_id)
);

CREATE TABLE pet_messages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    sender_pet_id INTEGER NOT NULL,
    recipient_user_id INTEGER NOT NULL,
    message_content TEXT NOT NULL,
    is_read BOOLEAN DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_pet_id) REFERENCES pets(id) ON DELETE CASCADE,
    FOREIGN KEY (recipient_user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Insert Default Achievements
INSERT INTO achievements (achievement_name, achievement_description, action_type, goal_value, reward_currency, reward_amount) VALUES
('First Friend', 'Make your first friend.', 'add_friend', 1, 'gems', 10),
('Pet Owner', 'Own your first pet.', 'own_pet', 1, 'gems', 10),
('Kind Soul', 'Feed another user''s pet for the first time.', 'feed_pet_other', 1, 'gems', 5),
('Top Dog', 'Own 10 pets.', 'own_pet', 10, 'gems', 50),
('Gifting Guru', 'Send a total of 50 gifts.', 'send_gift', 50, 'gems', 100);
