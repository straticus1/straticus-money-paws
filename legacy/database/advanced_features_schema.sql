-- Money Paws - Advanced AI Breeding & Tournament System Schema
-- Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>

-- Advanced Pet Genetics & Traits System
CREATE TABLE `pet_traits` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `trait_name` VARCHAR(100) NOT NULL,
    `trait_category` ENUM('physical', 'behavioral', 'special', 'rare') NOT NULL,
    `trait_description` TEXT,
    `rarity` ENUM('common', 'uncommon', 'rare', 'epic', 'legendary') NOT NULL DEFAULT 'common',
    `genetic_dominance` ENUM('dominant', 'recessive', 'codominant') NOT NULL DEFAULT 'recessive',
    `breeding_bonus` INT DEFAULT 0 COMMENT 'Bonus breeding success rate',
    `market_value_multiplier` DECIMAL(3,2) DEFAULT 1.00,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Pet-Trait Relationships
CREATE TABLE `pet_trait_assignments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `pet_id` INT NOT NULL,
    `trait_id` INT NOT NULL,
    `expression_strength` ENUM('weak', 'moderate', 'strong') DEFAULT 'moderate',
    `inherited_from` ENUM('mother', 'father', 'mutation', 'ai_generated') DEFAULT 'ai_generated',
    `assigned_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`pet_id`) REFERENCES `pets`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`trait_id`) REFERENCES `pet_traits`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_pet_trait` (`pet_id`, `trait_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Advanced Breeding Records
CREATE TABLE `breeding_records` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `mother_id` INT NOT NULL,
    `father_id` INT NOT NULL,
    `offspring_id` INT NOT NULL,
    `breeding_method` ENUM('natural', 'ai_assisted', 'genetic_enhancement') DEFAULT 'natural',
    `success_probability` DECIMAL(5,2) NOT NULL,
    `genetic_diversity_score` DECIMAL(5,2) NOT NULL,
    `mutation_events` INT DEFAULT 0,
    `breeding_cost_usd` DECIMAL(10,2) DEFAULT 0,
    `ai_enhancement_used` BOOLEAN DEFAULT FALSE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`mother_id`) REFERENCES `pets`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`father_id`) REFERENCES `pets`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`offspring_id`) REFERENCES `pets`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Breeding Compatibility Matrix
CREATE TABLE `breeding_compatibility` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `species_a` VARCHAR(50) NOT NULL,
    `species_b` VARCHAR(50) NOT NULL,
    `compatibility_score` DECIMAL(3,2) NOT NULL COMMENT '0.0 to 1.0',
    `success_rate_modifier` DECIMAL(3,2) DEFAULT 1.00,
    `special_requirements` TEXT NULL,
    `offspring_species` VARCHAR(50) NOT NULL,
    `is_hybrid` BOOLEAN DEFAULT FALSE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_species_pair` (`species_a`, `species_b`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tournament System Tables
CREATE TABLE `tournaments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `tournament_name` VARCHAR(255) NOT NULL,
    `tournament_type` ENUM('single_elimination', 'double_elimination', 'round_robin', 'swiss') NOT NULL,
    `game_mode` ENUM('pet_battle', 'racing', 'agility', 'beauty_contest', 'intelligence') NOT NULL,
    `entry_fee_usd` DECIMAL(10,2) NOT NULL,
    `max_participants` INT NOT NULL DEFAULT 32,
    `current_participants` INT DEFAULT 0,
    `prize_pool_usd` DECIMAL(10,2) DEFAULT 0,
    `status` ENUM('registration', 'in_progress', 'completed', 'cancelled') DEFAULT 'registration',
    `registration_deadline` TIMESTAMP NOT NULL,
    `start_time` TIMESTAMP NOT NULL,
    `created_by_user_id` INT NOT NULL,
    `winner_user_id` INT NULL,
    `winner_pet_id` INT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `completed_at` TIMESTAMP NULL,
    FOREIGN KEY (`created_by_user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`winner_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`winner_pet_id`) REFERENCES `pets`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tournament Participants
CREATE TABLE `tournament_participants` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `tournament_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `pet_id` INT NOT NULL,
    `entry_fee_paid` DECIMAL(10,2) NOT NULL,
    `crypto_type` VARCHAR(10) NOT NULL,
    `crypto_amount` DECIMAL(20,8) NOT NULL,
    `seed_number` INT NULL COMMENT 'Tournament seeding position',
    `current_round` INT DEFAULT 1,
    `is_eliminated` BOOLEAN DEFAULT FALSE,
    `elimination_round` INT NULL,
    `total_wins` INT DEFAULT 0,
    `total_losses` INT DEFAULT 0,
    `registered_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`tournament_id`) REFERENCES `tournaments`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`pet_id`) REFERENCES `pets`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_tournament_user` (`tournament_id`, `user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tournament Matches
CREATE TABLE `tournament_matches` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `tournament_id` INT NOT NULL,
    `round_number` INT NOT NULL,
    `match_number` INT NOT NULL,
    `participant_a_id` INT NOT NULL,
    `participant_b_id` INT NOT NULL,
    `winner_participant_id` INT NULL,
    `loser_participant_id` INT NULL,
    `match_status` ENUM('scheduled', 'in_progress', 'completed', 'forfeit') DEFAULT 'scheduled',
    `score_a` INT DEFAULT 0,
    `score_b` INT DEFAULT 0,
    `match_data` JSON NULL COMMENT 'Game-specific match details',
    `scheduled_time` TIMESTAMP NULL,
    `started_at` TIMESTAMP NULL,
    `completed_at` TIMESTAMP NULL,
    FOREIGN KEY (`tournament_id`) REFERENCES `tournaments`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`participant_a_id`) REFERENCES `tournament_participants`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`participant_b_id`) REFERENCES `tournament_participants`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`winner_participant_id`) REFERENCES `tournament_participants`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`loser_participant_id`) REFERENCES `tournament_participants`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tournament Prize Distribution
CREATE TABLE `tournament_prizes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `tournament_id` INT NOT NULL,
    `placement` INT NOT NULL COMMENT '1st, 2nd, 3rd place etc',
    `participant_id` INT NOT NULL,
    `prize_amount_usd` DECIMAL(10,2) NOT NULL,
    `crypto_type` VARCHAR(10) NOT NULL,
    `crypto_amount` DECIMAL(20,8) NOT NULL,
    `prize_distributed` BOOLEAN DEFAULT FALSE,
    `distributed_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`tournament_id`) REFERENCES `tournaments`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`participant_id`) REFERENCES `tournament_participants`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Real-time Tournament Events
CREATE TABLE `tournament_events` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `tournament_id` INT NOT NULL,
    `event_type` ENUM('registration', 'match_start', 'match_end', 'round_complete', 'tournament_complete') NOT NULL,
    `event_data` JSON NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`tournament_id`) REFERENCES `tournaments`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Pet Battle Stats (for tournament battles)
CREATE TABLE `pet_battle_stats` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `pet_id` INT NOT NULL,
    `attack_power` INT DEFAULT 50,
    `defense_power` INT DEFAULT 50,
    `speed` INT DEFAULT 50,
    `intelligence` INT DEFAULT 50,
    `agility` INT DEFAULT 50,
    `beauty_score` INT DEFAULT 50,
    `total_battles` INT DEFAULT 0,
    `wins` INT DEFAULT 0,
    `losses` INT DEFAULT 0,
    `tournament_wins` INT DEFAULT 0,
    `elo_rating` INT DEFAULT 1200,
    `last_battle_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`pet_id`) REFERENCES `pets`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_pet_battle_stats` (`pet_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert Default Pet Traits
INSERT INTO `pet_traits` (`trait_name`, `trait_category`, `trait_description`, `rarity`, `genetic_dominance`, `breeding_bonus`, `market_value_multiplier`) VALUES
-- Physical Traits
('Golden Coat', 'physical', 'Shimmering golden fur that catches light beautifully', 'uncommon', 'dominant', 5, 1.25),
('Heterochromia', 'physical', 'Two different colored eyes - mesmerizing and rare', 'rare', 'recessive', 10, 1.50),
('Extra Fluffy', 'physical', 'Exceptionally thick and soft fur', 'common', 'dominant', 2, 1.10),
('Spotted Pattern', 'physical', 'Unique spotted markings across the body', 'uncommon', 'codominant', 3, 1.15),
('Albino', 'physical', 'Pure white coloration with pink eyes', 'epic', 'recessive', 15, 2.00),

-- Behavioral Traits
('Loyal Companion', 'behavioral', 'Forms exceptionally strong bonds with owners', 'common', 'dominant', 5, 1.20),
('Playful Spirit', 'behavioral', 'Always ready for games and adventures', 'common', 'dominant', 3, 1.10),
('Gentle Giant', 'behavioral', 'Large size but incredibly gentle nature', 'uncommon', 'recessive', 8, 1.30),
('Alpha Leader', 'behavioral', 'Natural leadership qualities among other pets', 'rare', 'dominant', 12, 1.40),
('Zen Master', 'behavioral', 'Exceptionally calm and meditative personality', 'rare', 'recessive', 10, 1.35),

-- Special Traits
('Night Vision', 'special', 'Enhanced vision in low-light conditions', 'rare', 'recessive', 15, 1.60),
('Healing Aura', 'special', 'Presence helps other pets recover faster', 'epic', 'recessive', 20, 2.50),
('Empathic Bond', 'special', 'Can sense and respond to owner emotions', 'rare', 'codominant', 18, 1.80),
('Weather Sense', 'special', 'Can predict weather changes accurately', 'uncommon', 'recessive', 8, 1.25),
('Lucky Charm', 'special', 'Brings good fortune to their family', 'epic', 'recessive', 25, 3.00),

-- Legendary Traits
('Phoenix Heart', 'rare', 'Legendary resilience and regenerative abilities', 'legendary', 'recessive', 50, 5.00),
('Cosmic Eyes', 'rare', 'Eyes that seem to hold the mysteries of the universe', 'legendary', 'recessive', 45, 4.50),
('Time Walker', 'rare', 'Seems to age slower than normal pets', 'legendary', 'recessive', 40, 4.00);

-- Insert Breeding Compatibility Data
INSERT INTO `breeding_compatibility` (`species_a`, `species_b`, `compatibility_score`, `success_rate_modifier`, `offspring_species`, `is_hybrid`) VALUES
('dog', 'dog', 1.00, 1.00, 'dog', FALSE),
('cat', 'cat', 1.00, 1.00, 'cat', FALSE),
('bird', 'bird', 1.00, 1.00, 'bird', FALSE),
('rabbit', 'rabbit', 1.00, 1.00, 'rabbit', FALSE),
('dog', 'cat', 0.15, 0.30, 'hybrid_dogcat', TRUE),
('bird', 'rabbit', 0.05, 0.10, 'hybrid_birdrabbit', TRUE);

-- Create Indexes for Performance
CREATE INDEX idx_pet_traits_category ON pet_traits(trait_category);
CREATE INDEX idx_pet_traits_rarity ON pet_traits(rarity);
CREATE INDEX idx_pet_trait_assignments_pet_id ON pet_trait_assignments(pet_id);
CREATE INDEX idx_breeding_records_parents ON breeding_records(mother_id, father_id);
CREATE INDEX idx_tournaments_status ON tournaments(status);
CREATE INDEX idx_tournaments_game_mode ON tournaments(game_mode);
CREATE INDEX idx_tournament_participants_tournament_id ON tournament_participants(tournament_id);
CREATE INDEX idx_tournament_matches_tournament_round ON tournament_matches(tournament_id, round_number);
CREATE INDEX idx_pet_battle_stats_elo ON pet_battle_stats(elo_rating);
CREATE INDEX idx_tournament_events_tournament_id ON tournament_events(tournament_id);
