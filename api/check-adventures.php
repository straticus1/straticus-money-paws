<?php
/**
 * Money Paws - Check Adventures API
 *
 * This API endpoint checks for and completes any finished adventures for the logged-in user.
 *
 * Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>
 */

session_start();
require_once '../includes/adventures.php';
header('Content-Type: application/json');

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'You must be logged in to check adventures.']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    $report = check_and_complete_user_adventures($user_id);
    echo json_encode(['success' => true, 'report' => $report]);
} catch (Exception $e) {
    // Log error properly in a real application
    echo json_encode(['success' => false, 'message' => 'An error occurred while checking adventures.']);
}
