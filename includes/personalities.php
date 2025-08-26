<?php
require_once 'db.php';

const DEFAULT_PERSONALITY_TRAITS = ['bravery', 'friendliness', 'curiosity', 'laziness', 'greed'];


/**
 * Sends a message from a pet to a user.
 *
 * @param int $sender_pet_id The ID of the pet sending the message.
 * @param int $recipient_user_id The ID of the user receiving the message.
 * @param string $message_content The content of the message.
 * @return bool True on success, false on failure.
 */
function sendPetMessageToUser($sender_pet_id, $recipient_user_id, $message_content)
{
    $pdo = get_db();
    $stmt = $pdo->prepare(
        'INSERT INTO pet_messages (sender_pet_id, recipient_user_id, message_content) VALUES (?, ?, ?)'
    );
    return $stmt->execute([$sender_pet_id, $recipient_user_id, $message_content]);
}

/**
 * Retrieves all messages for a specific user, including pet details.
 *
 * @param int $user_id The ID of the user.
 * @return array An array of messages.
 */
function getPetMessagesForUser($user_id)
{
    $pdo = get_db();
    $stmt = $pdo->prepare(
        'SELECT pm.id, pm.message_content, pm.is_read, pm.created_at, p.original_name as pet_name, p.filename as pet_avatar
         FROM pet_messages pm
         JOIN pets p ON pm.sender_pet_id = p.id
         WHERE pm.recipient_user_id = ?
         ORDER BY pm.created_at DESC'
    );
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Marks a specific pet message as read.
 *
 * @param int $message_id The ID of the message.
 * @param int $user_id The ID of the user who owns the message.
 * @return bool True on success, false on failure.
 */
function markPetMessageAsRead($message_id, $user_id)
{
    $pdo = get_db();
    // Ensure the message belongs to the user before marking as read
    $stmt = $pdo->prepare(
        'UPDATE pet_messages SET is_read = 1 WHERE id = ? AND recipient_user_id = ?'
    );
    return $stmt->execute([$message_id, $user_id]);
}

/**
 * Assigns a default set of personality traits to a new pet.
 *
 * @param int $pet_id The ID of the new pet.
 */
function assignInitialPersonalities($pet_id)
{
    $pdo = get_db();
    $stmt = $pdo->prepare('INSERT INTO pet_personalities (pet_id, trait, value) VALUES (?, ?, ?)');
    foreach (DEFAULT_PERSONALITY_TRAITS as $trait) {
        $stmt->execute([$pet_id, $trait, rand(30, 70)]); // Assign a random starting value
    }
}

/**
 * Updates a specific personality trait for a pet.
 *
 * @param int $pet_id The ID of the pet.
 * @param string $trait The personality trait to update.
 * @param int $change The amount to change the trait by (can be negative).
 */
function updatePetPersonality($pet_id, $trait, $change)
{
    $pdo = get_db();
    $stmt = $pdo->prepare(
        'INSERT INTO pet_personalities (pet_id, trait, value) VALUES (?, ?, ?)
         ON CONFLICT(pet_id, trait) DO UPDATE SET value = max(0, min(100, value + ?))'
    );
    // The initial value is set to a baseline of 50 if it doesn't exist.
    $stmt->execute([$pet_id, $trait, 50 + $change, $change]);
}

/**
 * Generates a message from a pet based on its personality.
 *
 * @param int $pet_id The ID of the pet.
 * @param int $user_id The ID of the pet's owner.
 */
function generatePersonalityMessage($pet_id, $user_id)
{
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT trait, value FROM pet_personalities WHERE pet_id = ? ORDER BY value DESC LIMIT 1');
    $stmt->execute([$pet_id]);
    $dominant_trait = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$dominant_trait) return;

    $message = '';
    switch ($dominant_trait['trait']) {
        case 'friendliness':
            $message = 'I was just thinking about how much I appreciate you! Hope you\'re having a great day.';
            break;
        case 'curiosity':
            $message = 'I saw a strange-looking bug today. I tried to follow it, but it got away. The world is so full of mysteries!';
            break;
        case 'bravery':
            $message = 'I heard a loud noise outside, but I wasn\'t even scared! I protected our home.';
            break;
        case 'laziness':
            $message = 'Just woke up from the best nap. The sunbeam in the living room is just perfect. Time for another snooze.';
            break;
        case 'greed':
            $message = 'I\'m feeling a bit peckish. Are there any snacks available? A little treat would be amazing right now!';
            break;
    }

        if (!empty($message)) {
        sendPetMessageToUser($pet_id, $user_id, $message);
    }
}

/**
 * Retrieves all personality traits for all pets, for admin use.
 *
 * @return array An array of pets with their personality traits.
 */
function getAllPetPersonalities()
{
    $pdo = get_db();
    $stmt = $pdo->query(
        'SELECT p.id as pet_id, p.original_name as pet_name, u.name as owner_name, GROUP_CONCAT(ps.trait || \':\' || ps.value) as personalities
         FROM pets p
         LEFT JOIN pet_personalities ps ON p.id = ps.pet_id
         JOIN users u ON p.user_id = u.id
         GROUP BY p.id
         ORDER BY p.original_name ASC'
    );
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Parse the concatenated string into an associative array
    foreach ($results as &$row) {
        $personality_pairs = explode(',', $row['personalities']);
        $row['personalities'] = [];
        foreach ($personality_pairs as $pair) {
            list($trait, $value) = explode(':', $pair);
            $row['personalities'][$trait] = $value;
        }
    }

    return $results;
}
