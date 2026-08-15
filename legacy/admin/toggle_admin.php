<?php
require_once '../includes/functions.php';

// Admin authentication check
if (!isAdmin()) {
    header('Location: /login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRFToken();
    $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : null;
    $isAdmin = $_POST['is_admin'] ?? null;

    if ($userId && $isAdmin !== null && $userId != $_SESSION['user_id']) { // Prevent admin from revoking their own status
        toggleUserAdminStatus($userId, (bool)$isAdmin);
    }
}

header('Location: users.php');
exit;
