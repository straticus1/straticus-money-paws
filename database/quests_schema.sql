-- Paws.money - Quests and Achievements Schema
-- Version 1.0

-- Table to store all available quests
CREATE TABLE `quests` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `quest_name` VARCHAR(255) NOT NULL,
  `quest_description` TEXT NOT NULL,
  `quest_type` ENUM('daily', 'weekly', 'event', 'main') NOT NULL DEFAULT 'daily',
  `action_type` VARCHAR(50) NOT NULL, -- e.g., 'feed_pet', 'send_gift', 'add_friend'
  `goal_value` INT NOT NULL, -- e.g., feed 5 times, send 3 gifts
  `reward_currency` VARCHAR(10) NOT NULL DEFAULT 'paw_coins',
  `reward_amount` INT NOT NULL,
  `is_active` BOOLEAN NOT NULL DEFAULT TRUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table to track user progress on active quests
CREATE TABLE `user_quests` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `quest_id` INT NOT NULL,
  `progress` INT NOT NULL DEFAULT 0,
  `status` ENUM('in_progress', 'completed', 'claimed') NOT NULL DEFAULT 'in_progress',
  `assigned_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `completed_at` TIMESTAMP NULL,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`quest_id`) REFERENCES `quests`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table to store all available achievements
CREATE TABLE `achievements` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `achievement_name` VARCHAR(255) NOT NULL,
  `achievement_description` TEXT NOT NULL,
  `action_type` VARCHAR(50) NOT NULL, -- e.g., 'total_pets_owned', 'total_gifts_sent'
  `goal_value` INT NOT NULL,
  `reward_currency` VARCHAR(10) NOT NULL DEFAULT 'gems',
  `reward_amount` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table to track achievements unlocked by users
CREATE TABLE `user_achievements` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `achievement_id` INT NOT NULL,
  `unlocked_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`achievement_id`) REFERENCES `achievements`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `user_achievement_unique` (`user_id`, `achievement_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
