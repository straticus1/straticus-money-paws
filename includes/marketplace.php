<?php
/**
 * Money Paws - Marketplace Functions
 * Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>
 */

require_once 'functions.php';

/**
 * Lists an item on the marketplace.
 *
 * @param int $user_id The ID of the user listing the item.
 * @param int $item_id The ID of the item to list.
 * @param int $quantity The number of items to list.
 * @param float $price The price for the entire stack or single item.
 * @return array An array with success status and a message.
 */
function listItemOnMarketplace($user_id, $item_id, $quantity, $price)
{
    $pdo = get_db();

    // 1. Check if user has enough of the item
    $stmt = $pdo->prepare('SELECT quantity FROM user_inventory WHERE user_id = ? AND item_id = ?');
    $stmt->execute([$user_id, $item_id]);
    $inventory = $stmt->fetch();

    if (!$inventory || $inventory['quantity'] < $quantity) {
        return ['success' => false, 'message' => 'You do not have enough of this item to sell.'];
    }

    // 2. Deduct item from inventory and create listing in a transaction
    try {
        $pdo->beginTransaction();

        // Deduct from inventory
        $new_quantity = $inventory['quantity'] - $quantity;
        if ($new_quantity > 0) {
            $update_stmt = $pdo->prepare('UPDATE user_inventory SET quantity = ? WHERE user_id = ? AND item_id = ?');
            $update_stmt->execute([$new_quantity, $user_id, $item_id]);
        } else {
            $delete_stmt = $pdo->prepare('DELETE FROM user_inventory WHERE user_id = ? AND item_id = ?');
            $delete_stmt->execute([$user_id, $item_id]);
        }

        // Create listing
        $insert_stmt = $pdo->prepare(
            'INSERT INTO marketplace_listings (user_id, listing_type, item_id, quantity, price) VALUES (?, ?, ?, ?, ?)'
        );
        $insert_stmt->execute([$user_id, 'item', $item_id, $quantity, $price]);

        $pdo->commit();
        return ['success' => true, 'message' => 'Your item has been listed on the marketplace.'];
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log('Marketplace listing error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'An error occurred while listing your item.'];
    }
}

/**
 * Lists a pet on the marketplace.
 *
 * @param int $user_id The ID of the user listing the pet.
 * @param int $pet_id The ID of the pet to list.
 * @param float $price The price for the pet.
 * @return array An array with success status and a message.
 */
function listPetOnMarketplace($user_id, $pet_id, $price)
{
    $pdo = get_db();

    // 1. Validate pet ownership and status
    $pet = getPetByIdAndOwner($pet_id, $user_id);
    if (!$pet) {
        return ['success' => false, 'message' => 'You do not own this pet.'];
    }

    if ($pet['market_status'] === 'listed') {
        return ['success' => false, 'message' => 'This pet is already listed on the marketplace.'];
    }

    // Check if pet is on an adventure
    $stmt = $pdo->prepare('SELECT id FROM pet_adventures WHERE pet_id = ?');
    $stmt->execute([$pet_id]);
    if ($stmt->fetch()) {
        return ['success' => false, 'message' => 'This pet is currently on an adventure and cannot be sold.'];
    }

    // 2. Create listing and update pet status in a transaction
    try {
        $pdo->beginTransaction();

        // Create listing
        $insert_stmt = $pdo->prepare(
            'INSERT INTO marketplace_listings (user_id, listing_type, pet_id, price) VALUES (?, ?, ?, ?)'
        );
        $insert_stmt->execute([$user_id, 'pet', $pet_id, $price]);

        // Update pet status
        $update_stmt = $pdo->prepare('UPDATE pets SET market_status = ? WHERE id = ?');
        $update_stmt->execute(['listed', $pet_id]);

        $pdo->commit();
        return ['success' => true, 'message' => 'Your pet has been listed on the marketplace.'];
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log('Marketplace pet listing error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'An error occurred while listing your pet.'];
    }
}

/**
 * Retrieves active marketplace listings.
 *
 * @return array An array of active listings with details.
 */
function getMarketplaceListings()
{
    $pdo = get_db();
    $stmt = $pdo->query("
        SELECT 
            ml.id, ml.user_id, ml.listing_type, ml.item_id, ml.pet_id, ml.quantity, ml.price, ml.created_at,
            u.username AS seller_username,
            si.name AS item_name, si.description AS item_description,
            p.name AS pet_name, p.level AS pet_level, p.experience AS pet_experience, p.dna AS pet_dna
        FROM marketplace_listings ml
        JOIN users u ON ml.user_id = u.id
        LEFT JOIN store_items si ON ml.item_id = si.id AND ml.listing_type = 'item'
        LEFT JOIN pets p ON ml.pet_id = p.id AND ml.listing_type = 'pet'
        WHERE ml.status = 'active'
        ORDER BY ml.created_at DESC
    ");

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Purchases a listing from the marketplace.
 *
 * @param int $buyer_id The ID of the user purchasing the listing.
 * @param int $listing_id The ID of the marketplace listing.
 * @return array An array with success status and a message.
 */
function purchaseMarketplaceListing($buyer_id, $listing_id)
{
    $pdo = get_db();

    try {
        $pdo->beginTransaction();

        // 1. Get listing and lock the row for update
        $stmt = $pdo->prepare('SELECT * FROM marketplace_listings WHERE id = ? AND status = \'active\' FOR UPDATE');
        $stmt->execute([$listing_id]);
        $listing = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$listing) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'This listing is no longer available.'];
        }

        $seller_id = $listing['user_id'];
        $price = $listing['price'];

        if ($buyer_id == $seller_id) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'You cannot purchase your own listing.'];
        }

        // 2. Check buyer's balance
        $stmt = $pdo->prepare('SELECT balance FROM users WHERE id = ?');
        $stmt->execute([$buyer_id]);
        $buyer_balance = $stmt->fetchColumn();

        if ($buyer_balance < $price) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'You do not have enough funds to make this purchase.'];
        }

        // 3. Process the transaction
        // Deduct from buyer
        $stmt = $pdo->prepare('UPDATE users SET balance = balance - ? WHERE id = ?');
        $stmt->execute([$price, $buyer_id]);

        // Add to seller
        $stmt = $pdo->prepare('UPDATE users SET balance = balance + ? WHERE id = ?');
        $stmt->execute([$price, $seller_id]);

        // 4. Handle asset transfer
        if ($listing['listing_type'] === 'item') {
            // Add item to buyer's inventory
            $stmt = $pdo->prepare('INSERT INTO user_inventory (user_id, item_id, quantity) VALUES (?, ?, ?) ON CONFLICT(user_id, item_id) DO UPDATE SET quantity = quantity + excluded.quantity');
            $stmt->execute([$buyer_id, $listing['item_id'], $listing['quantity']]);
        } else if ($listing['listing_type'] === 'pet') {
            // Transfer pet ownership
            $stmt = $pdo->prepare('UPDATE pets SET user_id = ?, market_status = \'none\' WHERE id = ?');
            $stmt->execute([$buyer_id, $listing['pet_id']]);
        }

        // 5. Mark listing as sold
        $stmt = $pdo->prepare('UPDATE marketplace_listings SET status = \'sold\' WHERE id = ?');
        $stmt->execute([$listing_id]);

        $pdo->commit();
        return ['success' => true, 'message' => 'Purchase successful!'];

    } catch (Exception $e) {
        $pdo->rollBack();
        error_log('Marketplace purchase error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'An error occurred during the purchase. Please try again.'];
    }
}

