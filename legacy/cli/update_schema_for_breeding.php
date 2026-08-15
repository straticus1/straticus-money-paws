<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

function apply_schema_update() {
    try {
        $pdo = get_db();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        echo "Applying schema updates for breeding feature...\n";

        // Check if columns already exist to make the script runnable multiple times
        $stmt = $pdo->query("SHOW COLUMNS FROM `pets` LIKE 'dna'");
        if ($stmt->rowCount() == 0) {
            echo "- Adding 'dna', 'mother_id', 'father_id' to 'pets' table.\n";
            $pdo->exec("ALTER TABLE `pets` 
                ADD COLUMN `dna` TEXT DEFAULT NULL AFTER `sale_price_usd`,
                ADD COLUMN `mother_id` INT DEFAULT NULL AFTER `dna`,
                ADD COLUMN `father_id` INT DEFAULT NULL AFTER `mother_id`;");
        } else {
            echo "- 'pets' table already updated.\n";
        }

        // Check if breeding_cooldowns table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'breeding_cooldowns'");
        if ($stmt->rowCount() == 0) {
            echo "- Creating 'breeding_cooldowns' table.\n";
            $pdo->exec("CREATE TABLE `breeding_cooldowns` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `pet_id` INT UNSIGNED NOT NULL,
                `cooldown_ends_at` TIMESTAMP NOT NULL,
                FOREIGN KEY (`pet_id`) REFERENCES `pets`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        } else {
            echo "- 'breeding_cooldowns' table already exists.\n";
        }

        echo "\nSchema update applied successfully!\n";

    } catch (PDOException $e) {
        die("Database error: " . $e->getMessage() . "\n");
    }
}

apply_schema_update();
