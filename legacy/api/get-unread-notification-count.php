<?php
require_once '../includes/functions.php';
requireLogin();

header('Content-Type: application/json');

$userId = $_SESSION['user_id'];
$count = getUnreadNotificationCount($userId);

echo json_encode(['success' => true, 'count' => $count]);
?>
