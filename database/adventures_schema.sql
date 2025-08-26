-- Paws.money - Pet Adventures & Player-Driven Economy Schema
-- Version 1.0

-- This schema introduces tables for pet adventures, quests, rewards, and a player-driven item marketplace.

-- Add experience and level to the pets table
-- We will alter the table in a separate script, but the change is documented here.
-- ALTER TABLE `pets` ADD COLUMN `experience` INT UNSIGNED NOT NULL DEFAULT 0;
-- ALTER TABLE `pets` ADD COLUMN `level` INT UNSIGNED NOT NULL DEFAULT 1;

-- Table to store available quests
CREATE TABLE IF NOT EXISTS `quests` (
  `quest_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `description` TEXT NOT NULL,
  `min_level` INT UNSIGNED NOT NULL DEFAULT 1,
  `duration_seconds` INT UNSIGNED NOT NULL COMMENT 'Time to complete the quest in seconds',
  `energy_cost` INT UNSIGNED NOT NULL DEFAULT 10,
  `reward_experience` INT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`quest_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table to store potential rewards for each quest
CREATE TABLE IF NOT EXISTS `quest_rewards` (
  `reward_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `quest_id` INT UNSIGNED NOT NULL,
  `item_id` INT UNSIGNED NOT NULL COMMENT 'FK to store_items',
  `drop_chance` DECIMAL(5, 2) NOT NULL COMMENT 'Drop chance percentage, e.g., 25.50 for 25.5%',
  `quantity` INT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (`reward_id`),
  FOREIGN KEY (`quest_id`) REFERENCES `quests`(`quest_id`) ON DELETE CASCADE,
  FOREIGN KEY (`item_id`) REFERENCES `store_items`(`item_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table to track active pet adventures (when a pet is on a quest)
CREATE TABLE IF NOT EXISTS `pet_adventures` (
  `adventure_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pet_id` INT UNSIGNED NOT NULL,
  `quest_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `start_time` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `end_time` TIMESTAMP NOT NULL,
  `status` ENUM('in_progress', 'completed') NOT NULL DEFAULT 'in_progress',
  PRIMARY KEY (`adventure_id`),
  FOREIGN KEY (`pet_id`) REFERENCES `pets`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`quest_id`) REFERENCES `quests`(`quest_id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table for the player-driven marketplace
CREATE TABLE IF NOT EXISTS `marketplace_listings` (
  `listing_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `seller_id` INT UNSIGNED NOT NULL,
  `item_id` INT UNSIGNED NOT NULL,
  `user_inventory_id` INT UNSIGNED NOT NULL UNIQUE COMMENT 'Ensures an inventory item is listed only once',
  `quantity` INT UNSIGNED NOT NULL,
  `price` DECIMAL(10, 2) NOT NULL COMMENT 'Price in Paws Coins or other in-game currency',
  `currency` VARCHAR(10) NOT NULL DEFAULT 'PAWS',
  `listed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` TIMESTAMP NULL DEFAULT NULL,
  `status` ENUM('active', 'sold', 'expired', 'cancelled') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`listing_id`),
  FOREIGN KEY (`seller_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`item_id`) REFERENCES `store_items`(`item_id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_inventory_id`) REFERENCES `user_inventory`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
