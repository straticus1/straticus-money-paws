<?php

// CLI script to update the database schema for the Pet Adventures & Marketplace features

require_once __DIR__ . '/../includes/functions.php';

function column_exists($pdo, $table, $column) {
    try {
        $result = $pdo->query("DESCRIBE `$table` `$column`");
        return $result->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

function apply_schema_updates() {
    try {
        // Add diagnostic output to debug connection issues
        $db_host_cli = (php_sapi_name() === 'cli' && DB_HOST === 'localhost') ? '127.0.0.1' : DB_HOST;
        echo "Attempting to connect to database...\n";
        echo "  - Host: " . $db_host_cli . "\n";
        echo "  - Database: " . DB_NAME . "\n";

        $pdo = get_db();
        echo "Database connection successful.\n";

        // 1. Add 'experience' and 'level' columns to 'pets' table
        echo "Updating 'pets' table...\n";
        if (!column_exists($pdo, 'pets', 'experience')) {
            $pdo->exec("ALTER TABLE `pets` ADD COLUMN `experience` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `happiness`;");
            echo "  - Added 'experience' column.\n";
        } else {
            echo "  - 'experience' column already exists.\n";
        }

        if (!column_exists($pdo, 'pets', 'level')) {
            $pdo->exec("ALTER TABLE `pets` ADD COLUMN `level` INT UNSIGNED NOT NULL DEFAULT 1 AFTER `experience`;");
            echo "  - Added 'level' column.\n";
        } else {
            echo "  - 'level' column already exists.\n";
        }

        // 2. Apply the new schema from the SQL file
        $schema_file = __DIR__ . '/../database/adventures_schema.sql';
        if (!file_exists($schema_file)) {
            throw new Exception("Schema file not found: $schema_file");
        }

        $sql = file_get_contents($schema_file);
        // Remove comments and split into individual statements
        $sql = preg_replace('/--.*$/m', '', $sql);
        $sql_statements = explode(';', $sql);

        echo "Applying adventures_schema.sql...\n";
        foreach ($sql_statements as $statement) {
            $statement = trim($statement);
            if (!empty($statement)) {
                $pdo->exec($statement . ';');
            }
        }
        echo "Schema from adventures_schema.sql applied successfully.\n";

        echo "\nSchema update complete!\n";

    } catch (Exception $e) {
        echo "An error occurred: " . $e->getMessage() . "\n";
        exit(1);
    }
}

apply_schema_updates();
