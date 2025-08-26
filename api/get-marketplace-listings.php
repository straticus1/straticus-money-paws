<?php
/**
 * Money Paws - Get Marketplace Listings API
 * Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>
 */

header('Content-Type: application/json');
require_once '../includes/functions.php';
require_once '../includes/marketplace.php';

// This endpoint can be public, but we'll keep the session start for potential future use
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$listings = getMarketplaceListings();

echo json_encode(['success' => true, 'listings' => $listings]);
