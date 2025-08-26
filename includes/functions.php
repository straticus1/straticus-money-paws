<?php
/**
 * Money Paws - Cryptocurrency-Powered Pet Platform
 * Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>
 */
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/pet_care.php';
require_once __DIR__ . '/personalities.php';
require_once __DIR__ . '/security.php';

function get_db() {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $sqlite_path = __DIR__ . '/../database/paws.sqlite';

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    if (file_exists($sqlite_path)) {
        // Use SQLite if the database file exists
        try {
            $pdo = new PDO('sqlite:' . $sqlite_path, null, null, $options);
        } catch (\PDOException $e) {
            throw new \PDOException($e->getMessage(), (int)$e->getCode());
        }
    } else {
        // Fallback to MySQL
        try {
            $db_host = (php_sapi_name() === 'cli' && DB_HOST === 'localhost') ? '127.0.0.1' : DB_HOST;
            $dsn = "mysql:host=" . $db_host . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (\PDOException $e) {
            // Do not throw error if MySQL is not available, as we might be setting up SQLite
            if (php_sapi_name() === 'cli') {
                // Silently fail for CLI, allows setup scripts to run without MySQL
                return null; 
            } 
            throw new \PDOException($e->getMessage(), (int)$e->getCode());
        }
    }
    
    return $pdo;
}

// Demo account functions (only work in developer mode)
function getDemoUserByEmail($email) {
    if (!defined('DEVELOPER_MODE') || !DEVELOPER_MODE) return null;
    
    // Check demo admin account
    if (defined('DEMO_ADMIN_ENABLED') && DEMO_ADMIN_ENABLED && 
        defined('DEMO_ADMIN_EMAIL') && $email === DEMO_ADMIN_EMAIL) {
        return [
            'id' => 999999, // Special ID for demo admin
            'email' => DEMO_ADMIN_EMAIL,
            'password' => password_hash(DEMO_ADMIN_PASSWORD, PASSWORD_DEFAULT),
            'name' => DEMO_ADMIN_NAME,
            'provider' => 'demo',
            'is_admin' => true,
            'created_at' => '2024-01-01 00:00:00'
        ];
    }
    
    // Check demo user account
    if (defined('DEMO_USER_ENABLED') && DEMO_USER_ENABLED && 
        defined('DEMO_USER_EMAIL') && $email === DEMO_USER_EMAIL) {
        return [
            'id' => 999998, // Special ID for demo user
            'email' => DEMO_USER_EMAIL,
            'password' => password_hash(DEMO_USER_PASSWORD, PASSWORD_DEFAULT),
            'name' => DEMO_USER_NAME,
            'provider' => 'demo',
            'is_admin' => false,
            'created_at' => '2024-01-01 00:00:00'
        ];
    }
    
    return null;
}

function getDemoUserById($id) {
    if (!defined('DEVELOPER_MODE') || !DEVELOPER_MODE) return null;
    
    // Check demo admin account
    if ($id == 999999 && defined('DEMO_ADMIN_ENABLED') && DEMO_ADMIN_ENABLED) {
        return [
            'id' => 999999,
            'email' => DEMO_ADMIN_EMAIL,
            'password' => password_hash(DEMO_ADMIN_PASSWORD, PASSWORD_DEFAULT),
            'name' => DEMO_ADMIN_NAME,
            'provider' => 'demo',
            'is_admin' => true,
            'created_at' => '2024-01-01 00:00:00'
        ];
    }
    
    // Check demo user account
    if ($id == 999998 && defined('DEMO_USER_ENABLED') && DEMO_USER_ENABLED) {
        return [
            'id' => 999998,
            'email' => DEMO_USER_EMAIL,
            'password' => password_hash(DEMO_USER_PASSWORD, PASSWORD_DEFAULT),
            'name' => DEMO_USER_NAME,
            'provider' => 'demo',
            'is_admin' => false,
            'created_at' => '2024-01-01 00:00:00'
        ];
    }
    
    return null;
}

function authenticateDemoUser($email, $password) {
    if (!defined('DEVELOPER_MODE') || !DEVELOPER_MODE) return false;
    
    // Check demo admin
    if (defined('DEMO_ADMIN_ENABLED') && DEMO_ADMIN_ENABLED && 
        defined('DEMO_ADMIN_EMAIL') && $email === DEMO_ADMIN_EMAIL &&
        defined('DEMO_ADMIN_PASSWORD') && $password === DEMO_ADMIN_PASSWORD) {
        return 999999; // Return demo admin ID
    }
    
    // Check demo user
    if (defined('DEMO_USER_ENABLED') && DEMO_USER_ENABLED && 
        defined('DEMO_USER_EMAIL') && $email === DEMO_USER_EMAIL &&
        defined('DEMO_USER_PASSWORD') && $password === DEMO_USER_PASSWORD) {
        return 999998; // Return demo user ID
    }
    
    return false;
}

// User authentication functions
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
}

function toggleUserAdminStatus($userId, $isAdmin) {
        $pdo = get_db();
    
    $stmt = $pdo->prepare("UPDATE users SET is_admin = ? WHERE id = ?");
    return $stmt->execute([$isAdmin, $userId]);
}

function getUserById($userId) {
        $pdo = get_db();
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getUser2FASettings($userId) {
        $pdo = get_db();
    
    $stmt = $pdo->prepare("SELECT * FROM user_2fa_settings WHERE user_id = ?");
    $stmt->execute([$userId]);
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$settings) {
        return [
            'mfa_enabled' => false,
            'mfa_method' => null,
            'phone_number' => null,
            'totp_secret' => null
        ];
    }

    return $settings;
}

function updateUser2FASettings($userId, $mfa_enabled, $mfa_method = null, $phone_number = null, $totp_secret = null) {
        $pdo = get_db();
    
    $stmt = $pdo->prepare("SELECT user_id FROM user_2fa_settings WHERE user_id = ?");
    $stmt->execute([$userId]);
    $exists = $stmt->fetch();

    if ($exists) {
        $stmt = $pdo->prepare(
            "UPDATE user_2fa_settings SET mfa_enabled = ?, mfa_method = ?, phone_number = ?, totp_secret = ? WHERE user_id = ?"
        );
        return $stmt->execute([$mfa_enabled, $mfa_method, $phone_number, $totp_secret, $userId]);
    } else {
        $stmt = $pdo->prepare(
            "INSERT INTO user_2fa_settings (user_id, mfa_enabled, mfa_method, phone_number, totp_secret) VALUES (?, ?, ?, ?, ?)"
        );
        return $stmt->execute([$userId, $mfa_enabled, $mfa_method, $phone_number, $totp_secret]);
    }
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}


function getUserByEmail($email) {
        $pdo = get_db();
    
    // Check demo accounts first (only in developer mode)
    if (defined('DEVELOPER_MODE') && DEVELOPER_MODE) {
        $demoUser = getDemoUserByEmail($email);
        if ($demoUser) return $demoUser;
    }
    
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getUserByName($name) {
        $pdo = get_db();
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE name = ?");
    $stmt->execute([$name]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function createUser($email, $password, $name, $provider = 'local') {
    $pdo = get_db();
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $db_type = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $now_function = $db_type == 'sqlite' ? "datetime('now')" : "NOW()";

    $stmt = $pdo->prepare("INSERT INTO users (email, password, name, provider, created_at) VALUES (?, ?, ?, ?, $now_function)");
    return $stmt->execute([$email, $hashedPassword, $name, $provider]);
}

function loginUser($userId) {
    $_SESSION['user_id'] = $userId;
    $_SESSION['logged_in'] = true;
}

function logoutUser() {
    session_destroy();
    header('Location: index.php');
    exit;
}

// Pet functions
function uploadPet($userId, $filename, $originalName, $description = '') {
    $pdo = get_db();
    $db_type = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $now_function = $db_type == 'sqlite' ? "datetime('now')" : "NOW()";
    $stmt = $pdo->prepare("INSERT INTO pets (user_id, filename, original_name, description, uploaded_at) VALUES (?, ?, ?, ?, $now_function)");
    return $stmt->execute([$userId, $filename, $originalName, $description]);
}

function getAllPets($limit = 20, $offset = 0) {
        $pdo = get_db();
        $stmt = $pdo->prepare("SELECT p.*, u.name as owner_name FROM pets p JOIN users u ON p.user_id = u.id WHERE p.is_public = 1 ORDER BY p.uploaded_at DESC LIMIT ? OFFSET ?");
    $stmt->execute([$limit, $offset]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getUserPets($userId) {
        $pdo = get_db();
        $stmt = $pdo->prepare("SELECT * FROM pets WHERE user_id = ? ORDER BY uploaded_at DESC");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getNotifications($userId) {
    $pdo = get_db();

    $stmt = $pdo->prepare("
        SELECT 
            n.*, 
            s.username as sender_username, 
            p.name as pet_name, 
            mr.id as request_id, 
            mr.status as request_status
        FROM notifications n 
        JOIN users s ON n.sender_user_id = s.id 
        LEFT JOIN pets p ON n.pet_id = p.id
        LEFT JOIN mating_requests mr ON n.request_id = mr.id
        WHERE n.recipient_user_id = ? 
        ORDER BY n.created_at DESC
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getUnreadNotifications($userId) {
        $pdo = get_db();
    
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE recipient_user_id = ? AND is_read = 0 ORDER BY created_at DESC");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getUnreadNotificationCount($userId) {
        $pdo = get_db();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE recipient_user_id = ? AND is_read = 0");
    $stmt->execute([$userId]);
    return $stmt->fetchColumn();
}

function markNotificationsAsRead($userId) {
        $pdo = get_db();
    
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE recipient_user_id = ?");
    return $stmt->execute([$userId]);
}

function createNotification($recipient_user_id, $sender_user_id, $pet_id, $notification_type, $interaction_id = null, $request_id = null) {
    $pdo = get_db();

    // Don't create a notification if the user is interacting with their own pet
    // and it's not a system message like a mating request response.
    if ($recipient_user_id == $sender_user_id && $notification_type !== 'mating_response') {
        return false;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO notifications (recipient_user_id, sender_user_id, pet_id, notification_type, interaction_id, request_id) VALUES (?, ?, ?, ?, ?, ?)'
    );
    return $stmt->execute([$recipient_user_id, $sender_user_id, $pet_id, $notification_type, $interaction_id, $request_id]);
}

function time_ago($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $weeks = floor($diff->d / 7);
    $days = $diff->d - ($weeks * 7);

    $time_parts = [
        'y' => $diff->y,
        'm' => $diff->m,
        'w' => $weeks,
        'd' => $days,
        'h' => $diff->h,
        'i' => $diff->i,
        's' => $diff->s,
    ];

    $string_map = [
        'y' => 'year',
        'm' => 'month',
        'w' => 'week',
        'd' => 'day',
        'h' => 'hour',
        'i' => 'minute',
        's' => 'second',
    ];

    $string = [];
    foreach ($time_parts as $k => $v) {
        if ($v) {
            $string[$k] = $v . ' ' . $string_map[$k] . ($v > 1 ? 's' : '');
        }
    }

    if (empty($string)) {
        return 'just now';
    }

    if (!$full) {
        $string = array_slice($string, 0, 1);
    }
    
    return implode(', ', $string) . ' ago';
}

function getTopPets($limit = 10) {
        $pdo = get_db();
    
    $stmt = $pdo->prepare("SELECT p.*, u.username FROM pets p JOIN users u ON p.user_id = u.id ORDER BY p.likes_count DESC LIMIT ?");
    $stmt->execute([$limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getTopUsersByLikes($limit = 10) {
        $pdo = get_db();
    
    $stmt = $pdo->prepare("SELECT u.id, u.username, SUM(p.likes_count) as total_likes FROM users u JOIN pets p ON u.id = p.user_id GROUP BY u.id, u.username ORDER BY total_likes DESC LIMIT ?");
    $stmt->execute([$limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getMostActiveUsers($limit = 10) {
        $pdo = get_db();
    
    $stmt = $pdo->prepare("SELECT u.id, u.username, COUNT(pi.id) as interaction_count FROM users u JOIN pet_interactions pi ON u.id = pi.user_id GROUP BY u.id, u.username ORDER BY interaction_count DESC LIMIT ?");
    $stmt->execute([$limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function searchSite($query) {
        $pdo = get_db();
    
    $searchTerm = '%' . $query . '%';

    // Search for users
    $stmt = $pdo->prepare("SELECT id, username FROM users WHERE username LIKE ? LIMIT 10");
    $stmt->execute([$searchTerm]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Search for pets
    $stmt = $pdo->prepare("SELECT p.id, p.name, p.user_id, u.username FROM pets p JOIN users u ON p.user_id = u.id WHERE p.name LIKE ? AND p.is_public = 1 LIMIT 10");
    $stmt->execute([$searchTerm]);
    $pets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return ['users' => $users, 'pets' => $pets];
}

function getPetByIdAndOwner($petId, $userId) {
    $pdo = get_db();
    $stmt = $pdo->prepare("SELECT * FROM pets WHERE id = ? AND user_id = ?");
    $stmt->execute([$petId, $userId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getDonationsForPet($pet_id) {
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT d.*, u.name as donor_name FROM pet_donations d JOIN users u ON d.donor_user_id = u.id WHERE d.pet_id = ? ORDER BY d.created_at DESC');
    $stmt->execute([$pet_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getBreedingCooldown($petId) {
    $pdo = get_db();
    $db_type = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $now_function = $db_type == 'sqlite' ? "datetime('now')" : "NOW()";

    $stmt = $pdo->prepare("SELECT * FROM breeding_cooldowns WHERE pet_id = ? AND cooldown_expires > $now_function");
    $stmt->execute([$petId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function setBreedingCooldown($petId, $cooldownSeconds) {
    $pdo = get_db();
    $db_type = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

    if ($db_type == 'sqlite') {
        $stmt = $pdo->prepare("
            INSERT INTO breeding_cooldowns (pet_id, cooldown_expires) 
            VALUES (?, datetime('now', '+' || ? || ' seconds'))
            ON CONFLICT(pet_id) DO UPDATE SET cooldown_expires = datetime('now', '+' || ? || ' seconds');
        ");
    } else { // MySQL
        $stmt = $pdo->prepare("
            INSERT INTO breeding_cooldowns (pet_id, cooldown_expires) 
            VALUES (?, DATE_ADD(NOW(), INTERVAL ? SECOND)) 
            ON DUPLICATE KEY UPDATE cooldown_expires = DATE_ADD(NOW(), INTERVAL ? SECOND);
        ");
    }
    
    return $stmt->execute([$petId, $cooldownSeconds, $cooldownSeconds]);
}

function createBredPet($userId, $name, $dna, $motherId, $fatherId) {
    $pdo = get_db();
    $db_type = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $now_function = $db_type == 'sqlite' ? "datetime('now')" : "NOW()";

    // A default filename or image needs to be decided for bred pets.
    // For now, let's use a placeholder.
    $filename = 'bred_pet_placeholder.png';
    $original_name = $name;
    $description = 'A newly bred pet.';

    $stmt = $pdo->prepare(
        "INSERT INTO pets (user_id, name, original_name, description, filename, dna, mother_id, father_id, uploaded_at, is_public, birth_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, $now_function, 1, $now_function)"
    );
    $stmt->execute([$userId, $name, $original_name, $description, $filename, $dna, $motherId, $fatherId]);
    return $pdo->lastInsertId();
}

function getPublicUserPets($userId) {
        $pdo = get_db();
        $stmt = $pdo->prepare("SELECT * FROM pets WHERE user_id = ? AND is_public = 1 ORDER BY uploaded_at DESC");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function setVacationMode($userId, $delegateId, $reservedFunds) {
    $pdo = get_db();
    $db_type = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $now_function = $db_type == 'sqlite' ? "datetime('now')" : "NOW()";

    $stmt = $pdo->prepare("
        UPDATE users 
        SET is_on_vacation = 1, 
            vacation_delegate_id = ?, 
            vacation_reserved_funds = ?, 
            vacation_start_date = $now_function
        WHERE id = ?
    ");
    return $stmt->execute([$delegateId, $reservedFunds, $userId]);
}

function disableVacationMode($userId) {
        $pdo = get_db();
    
    // Here you might want to handle the return of reserved funds.
    // For now, we'll just clear the vacation status.
    $stmt = $pdo->prepare("
        UPDATE users
        SET is_on_vacation = 0,
            vacation_delegate_id = NULL,
            vacation_reserved_funds = 0.00,
            vacation_start_date = NULL
        WHERE id = ?
    ");
    return $stmt->execute([$userId]);
}

function getAbandonedPets($limit = 12, $offset = 0) {
    $pdo = get_db();
    $db_type = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $date_function = $db_type == 'sqlite' ? "datetime('now', '-30 days')" : "DATE_SUB(NOW(), INTERVAL 30 DAY)";

    $stmt = $pdo->prepare("
        SELECT p.*, u.name as owner_name, ps.last_cared_for
        FROM pets p
        JOIN users u ON p.user_id = u.id
        JOIN pet_stats ps ON p.id = ps.pet_id
        WHERE ps.last_cared_for < $date_function
        ORDER BY ps.last_cared_for ASC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$limit, $offset]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getAllPetsForAdmin() {
        $pdo = get_db();
    
    $stmt = $pdo->query("
        SELECT p.id, p.original_name, p.description, p.uploaded_at, u.name as owner_name, p.is_public
        FROM pets p
        JOIN users u ON p.user_id = u.id
        ORDER BY p.uploaded_at DESC
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getSiteStatistics() {
        $pdo = get_db();
        $pdo = get_db();

    $stats = [];

    $stats['total_users'] = $pdo->query('SELECT count(*) FROM users')->fetchColumn();
    $stats['total_pets'] = $pdo->query('SELECT count(*) FROM pets')->fetchColumn();
    $stats['total_interactions'] = $pdo->query('SELECT count(*) FROM pet_interactions')->fetchColumn();

    return $stats;
}

function deletePet($petId) {
        $pdo = get_db();
    
    $stmt = $pdo->prepare("SELECT filename FROM pets WHERE id = ?");
    $stmt->execute([$petId]);
    $pet = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($pet) {
        $filePath = __DIR__ . '/../uploads/' . $pet['filename'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        $deleteStmt = $pdo->prepare("DELETE FROM pets WHERE id = ?");
        return $deleteStmt->execute([$petId]);
    }

    return false;
}

function getAllUsers() {
        $pdo = get_db();
    
    $stmt = $pdo->query("SELECT id, email, name, created_at, last_login, is_admin FROM users ORDER BY created_at DESC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getUserCryptoBalance($userId, $cryptoType) {
        $pdo = get_db();
    
    // In developer mode, return large balance for testing
    if (defined('DEVELOPER_MODE') && DEVELOPER_MODE) {
        return 1000.0; // Return 1000 units of any crypto for testing
    }
    
        $pdo = get_db();
    $stmt = $pdo->prepare("SELECT balance FROM user_balances WHERE user_id = ? AND crypto_type = ?");
    $stmt->execute([$userId, $cryptoType]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result ? floatval($result['balance']) : 0.0;
}

function getUserTotalUSDBalance($userId) {
    require_once 'crypto.php';
    $pdo = get_db();
    
    // In developer mode, return large balance for testing
    if (defined('DEVELOPER_MODE') && DEVELOPER_MODE) {
        return 50000.0; // Return $50,000 for testing
    }
    
    $stmt = $pdo->prepare("SELECT crypto_type, balance FROM user_balances WHERE user_id = ?");
    $stmt->execute([$userId]);
    $balances = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $totalUSD = 0.0;
    foreach ($balances as $balance) {
        $usdValue = convertCryptoToUSD($balance['balance'], $balance['crypto_type']);
        $totalUSD += $usdValue ?? 0.0;
    }
    
    return $totalUSD;
}

// Messaging Functions

function getOrCreateConversation($userOneId, $userTwoId) {
        $pdo = get_db();
    
    // Ensure consistent ordering of user IDs to avoid duplicate conversations
    $u1 = min($userOneId, $userTwoId);
    $u2 = max($userOneId, $userTwoId);

    $stmt = $pdo->prepare("SELECT id FROM conversations WHERE user_one_id = ? AND user_two_id = ?");
    $stmt->execute([$u1, $u2]);
    $conversation = $stmt->fetch();

    if ($conversation) {
        return $conversation['id'];
    } else {
        $stmt = $pdo->prepare("INSERT INTO conversations (user_one_id, user_two_id) VALUES (?, ?)");
        $stmt->execute([$u1, $u2]);
        return $pdo->lastInsertId();
    }
}

function getConversationsForUser($userId) {
        $pdo = get_db();
    
    $stmt = $pdo->prepare("
        SELECT c.id, 
               u.id as other_user_id, 
               u.name as other_user_name, 
               u.avatar as other_user_avatar,
               (SELECT body FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message,
               (SELECT created_at FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message_time,
               (SELECT COUNT(*) FROM messages WHERE conversation_id = c.id AND recipient_id = ? AND is_read = 0) as unread_count
        FROM conversations c
        JOIN users u ON u.id = IF(c.user_one_id = ?, c.user_two_id, c.user_one_id)
        WHERE c.user_one_id = ? OR c.user_two_id = ?
        ORDER BY last_message_time DESC
    ");
    $stmt->execute([$userId, $userId, $userId, $userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getMessagesForConversation($conversationId, $userId) {
        $pdo = get_db();
    
    // First, verify the user is part of this conversation
    $verifyStmt = $pdo->prepare("SELECT id FROM conversations WHERE id = ? AND (user_one_id = ? OR user_two_id = ?)");
    $verifyStmt->execute([$conversationId, $userId, $userId]);
    if ($verifyStmt->fetchColumn() === false) {
        // Instead of returning empty, redirect to a safe page like messages.php
        // This provides better user feedback than a blank page.
        redirectTo('messages.php');
    }

    // Mark messages as read for the current user
    $updateStmt = $pdo->prepare("UPDATE messages SET is_read = 1 WHERE conversation_id = ? AND recipient_id = ? AND is_read = 0");
    $updateStmt->execute([$conversationId, $userId]);

    // Fetch all messages for the conversation
    $stmt = $pdo->prepare("SELECT * FROM messages WHERE conversation_id = ? ORDER BY created_at ASC");
    $stmt->execute([$conversationId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function sendMessage($conversationId, $senderId, $recipientId, $body) {
        $pdo = get_db();
    
    // Verify the sender is part of this conversation
    $verifyStmt = $pdo->prepare("SELECT id FROM conversations WHERE id = ? AND (user_one_id = ? OR user_two_id = ?)");
    $verifyStmt->execute([$conversationId, $senderId, $senderId]);
    if ($verifyStmt->fetchColumn() === false) {
        return false; // Sender is not part of this conversation
    }

    $stmt = $pdo->prepare("INSERT INTO messages (conversation_id, sender_id, recipient_id, body) VALUES (?, ?, ?, ?)");
    $success = $stmt->execute([$conversationId, $senderId, $recipientId, $body]);

    if ($success) {
        // Touch the conversation to update its updated_at timestamp
        $updateStmt = $pdo->prepare("UPDATE conversations SET updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $updateStmt->execute([$conversationId]);

        // Create a notification for the recipient
        $senderName = getUserById($senderId)['name'] ?? 'Someone';
        $notificationMessage = "You have a new message from " . $senderName . ".";
        createNotification($recipientId, $senderId, 'new_message', $notificationMessage, 'conversation.php?id=' . $conversationId);
    }

    return $success;
}


// Utility functions
function sanitizeInput($input) {
    return htmlspecialchars(strip_tags(trim($input)));
}

function generateRandomString($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function redirectTo($url) {
    header("Location: $url");
    exit;
}

function getAdoptablePets() {
        $pdo = get_db();

    $user_id = $_SESSION['user_id'] ?? 0;

    $stmt = $pdo->prepare("
        SELECT 
            p.*, 
            ps.hunger_level, 
            ps.happiness_level, 
            u.name as owner_name,
            CASE 
                WHEN p.is_for_sale = 1 THEN p.sale_price_usd
                ELSE 
                    CASE 
                        WHEN p.id % 10 = 0 THEN 25.00
                        WHEN p.id % 7 = 0 THEN 35.00
                        WHEN p.id % 5 = 0 THEN 15.00
                        WHEN p.id % 3 = 0 THEN 20.00
                        ELSE 12.50
                    END
            END as adoption_fee,
            CASE
                WHEN p.is_for_sale = 1 THEN 'sale'
                ELSE 'adoption'
            END as listing_type
        FROM pets p
        LEFT JOIN pet_stats ps ON p.id = ps.pet_id
        JOIN users u ON p.user_id = u.id
        WHERE (p.is_public = 1 OR p.is_for_sale = 1)
        AND p.user_id != ? 
        ORDER BY p.is_for_sale DESC, RANDOM()
        LIMIT 12
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function buyPet($buyerId, $petId, $salePrice, $cryptoType) {
    $pdo = get_db();

    try {
        $pdo->beginTransaction();

        // 1. Get pet and seller details
        $stmt = $pdo->prepare("SELECT * FROM pets WHERE id = ? AND is_for_sale = 1");
        $stmt->execute([$petId]);
        $pet = $stmt->fetch();

        if (!$pet) {
            throw new Exception('This pet is not available for sale.');
        }

        $sellerId = $pet['user_id'];
        if ($sellerId == $buyerId) {
            throw new Exception('You cannot buy your own pet.');
        }

        // 2. Check buyer's balance
        $cryptoPrice = getCryptoPrice($cryptoType);
        if ($cryptoPrice <= 0) {
            throw new Exception('Could not retrieve a valid price for the selected cryptocurrency.');
        }
        $requiredCrypto = $salePrice / $cryptoPrice;
        $buyerBalance = getUserCryptoBalance($buyerId, $cryptoType);

        if ($buyerBalance < $requiredCrypto) {
            throw new Exception('Insufficient funds to purchase this pet.');
        }

        // 3. Deduct from buyer
        $stmt = $pdo->prepare("UPDATE user_balances SET balance = balance - ? WHERE user_id = ? AND crypto_type = ?");
        $stmt->execute([$requiredCrypto, $buyerId, $cryptoType]);

        // 4. Add to seller's balance
        // First, ensure the seller has a balance record for this crypto type
        $db_type = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($db_type == 'sqlite') {
            $stmt = $pdo->prepare("INSERT OR IGNORE INTO user_balances (user_id, crypto_type, balance) VALUES (?, ?, 0)");
        } else {
            $stmt = $pdo->prepare("INSERT INTO user_balances (user_id, crypto_type, balance) VALUES (?, ?, 0) ON DUPLICATE KEY UPDATE user_id=user_id");
        }
        $stmt->execute([$sellerId, $cryptoType]);
        // Now, add the funds
        $stmt = $pdo->prepare("UPDATE user_balances SET balance = balance + ? WHERE user_id = ? AND crypto_type = ?");
        $stmt->execute([$requiredCrypto, $sellerId, $cryptoType]);

        // 5. Transfer pet ownership and update sale status
        $stmt = $pdo->prepare("UPDATE pets SET user_id = ?, is_for_sale = 0, sale_price_usd = NULL WHERE id = ?");
        $stmt->execute([$buyerId, $petId]);

        // 6. Record transactions for both buyer and seller
        // Buyer's transaction
        $stmt = $pdo->prepare("INSERT INTO crypto_transactions (user_id, transaction_type, crypto_type, crypto_amount, usd_amount, status, notes) VALUES (?, 'purchase', ?, ?, ?, 'confirmed', ?)");
        $stmt->execute([$buyerId, $cryptoType, $requiredCrypto, $salePrice, 'Purchased pet ' . $pet['original_name']]);
        
        // Seller's transaction
        $stmt = $pdo->prepare("INSERT INTO crypto_transactions (user_id, transaction_type, crypto_type, crypto_amount, usd_amount, status, notes) VALUES (?, 'sale', ?, ?, ?, 'confirmed', ?)");
        $stmt->execute([$sellerId, $cryptoType, $requiredCrypto, $salePrice, 'Sold pet ' . $pet['original_name']]);

        $pdo->commit();

        return [
            'success' => true,
            'pet' => $pet,
            'message' => 'Congratulations! You have successfully purchased ' . htmlspecialchars($pet['original_name']) . '.'
        ];

    } catch (Exception $e) {
        $pdo->rollBack();
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

function adoptPet($userId, $petId, $adoptionFee, $cryptoType) {
        $pdo = get_db();
    
    try {
        $pdo->beginTransaction();
        
        // Get pet details
        $stmt = $pdo->prepare("SELECT * FROM pets WHERE id = ? AND user_id != ?");
        $stmt->execute([$petId, $userId]);
        $pet = $stmt->fetch();
        
        if (!$pet) {
            throw new Exception('Pet not available for adoption');
        }
        
        // Check user balance
        $balance = getUserCryptoBalance($userId, $cryptoType);
        $cryptoPrice = getCryptoPrice($cryptoType);
        $requiredCrypto = $adoptionFee / $cryptoPrice;
        
        if ($balance < $requiredCrypto) {
            throw new Exception('Insufficient balance for adoption fee');
        }
        
        // Deduct adoption fee
        $stmt = $pdo->prepare("
            UPDATE user_balances 
            SET balance = balance - ? 
            WHERE user_id = ? AND crypto_type = ?
        ");
        $stmt->execute([$requiredCrypto, $userId, $cryptoType]);
        
        // Transfer pet ownership
        $stmt = $pdo->prepare("UPDATE pets SET user_id = ? WHERE id = ?");
        $stmt->execute([$userId, $petId]);
        
        // Record transaction
        $stmt = $pdo->prepare("
            INSERT INTO crypto_transactions 
            (user_id, transaction_type, crypto_type, crypto_amount, usd_amount, status) 
            VALUES (?, 'adoption', ?, ?, ?, 'confirmed')
        ");
        $stmt->execute([$userId, $cryptoType, $requiredCrypto, $adoptionFee]);
        
        // Create pet stats if they don't exist
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO pet_stats (pet_id, hunger_level, happiness_level) 
            VALUES (?, 75, 85)
        ");
        $stmt->execute([$petId]);
        
        $pdo->commit();
        
        return [
            'success' => true,
            'pet' => $pet,
            'crypto_amount' => $requiredCrypto,
            'message' => 'Pet adopted successfully!'
        ];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}
/**
 * Calculates a pet's age in "pet days", where one pet day is 12 real-world hours.
 *
 * @param string $birth_date The pet's birth date in a format compatible with DateTime.
 * @return int The pet's age in pet days.
 */
function getPetAgeInPetDays($birth_date) {
    if (empty($birth_date)) {
        return 0;
    }
    try {
        $birth = new DateTime($birth_date);
        $now = new DateTime();
        $diff_hours = ($now->getTimestamp() - $birth->getTimestamp()) / 3600;
        return floor($diff_hours / 12); // 1 pet day = 12 real hours
    } catch (Exception $e) {
        // You might want to log this error
        return 0; // Return 0 if date is invalid
    }
}

/**
 * Updates a pet's happiness level, ensuring it stays between 0 and 100.
 *
 * @param int $pet_id The pet's ID.
 * @param int $happiness_change The amount to change happiness by (can be negative).
 * @return bool True on success, false on failure.
 */
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validate_csrf_token($token) {
    if (isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token)) {
        // Token is valid, unset it to prevent reuse
        unset($_SESSION['csrf_token']);
        return true;
    }
    return false;
}

function updatePetHappiness($pet_id, $happiness_change) {
    $pdo = get_db();
    $db_type = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

    if ($db_type == 'sqlite') {
        // SQLite doesn't have GREATEST/LEAST, so use MIN/MAX
        $stmt = $pdo->prepare("UPDATE pet_stats SET happiness_level = MAX(0, MIN(100, happiness_level + ?)) WHERE pet_id = ?");
    } else {
        // MySQL can use GREATEST/LEAST
        $stmt = $pdo->prepare("UPDATE pet_stats SET happiness_level = GREATEST(0, LEAST(100, happiness_level + ?)) WHERE pet_id = ?");
    }
    
    return $stmt->execute([$happiness_change, $pet_id]);
}

/**
 * Get CSRF token field for forms
 */
function getCSRFTokenField() {
    $token = generate_csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
}

/**
 * Get pet by ID with error handling
 */
function getPetById($pet_id) {
    $pdo = get_db();
    
    // Check demo accounts first (only in developer mode)
    if (defined('DEVELOPER_MODE') && DEVELOPER_MODE && $pet_id < 100) {
        // Return mock pet data for demo
        return [
            'id' => $pet_id,
            'user_id' => 999999,
            'original_name' => 'Demo Pet',
            'name' => 'Demo Pet',
            'description' => 'This is a demo pet for testing purposes.',
            'filename' => 'demo-pet.jpg',
            'gender' => 'Male',
            'birth_date' => date('Y-m-d H:i:s', strtotime('-30 days')),
            'life_status' => 'alive',
            'is_memorial_enabled' => 0,
            'donation_goal' => 0,
            'donations_received' => 0,
            'dna' => str_repeat('A', 50),
            'is_public' => 1
        ];
    }
    
    $stmt = $pdo->prepare("SELECT * FROM pets WHERE id = ?");
    $stmt->execute([$pet_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Get donations for a pet memorial
 */
function getDonationsForPet($pet_id) {
    $pdo = get_db();
    
    if (defined('DEVELOPER_MODE') && DEVELOPER_MODE) {
        return []; // Return empty array in demo mode
    }
    
    $stmt = $pdo->prepare("
        SELECT pd.*, u.name as donor_name 
        FROM pet_donations pd 
        JOIN users u ON pd.donor_user_id = u.id 
        WHERE pd.pet_id = ? 
        ORDER BY pd.created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$pet_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
