<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

session_start();

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit;
}

$current_user_id = $_SESSION['user_id'];
$search_term = isset($_GET['q']) ? trim($_GET['q']) : '';

if (empty($search_term)) {
    echo json_encode(['success' => true, 'users' => []]);
    exit;
}

$pdo = get_db();

// Find users matching the search term, excluding the current user
// and those with an existing relationship (friend, pending, etc.)
$sql = "
    SELECT id, username, profile_pic FROM users
    WHERE (username LIKE ? OR email LIKE ?) 
    AND id != ?
    AND id NOT IN (
        SELECT user_id_2 FROM user_friends WHERE user_id_1 = ?
        UNION
        SELECT user_id_1 FROM user_friends WHERE user_id_2 = ?
    )
    LIMIT 10
";

$stmt = $pdo->prepare($sql);
$stmt->execute(['%' . $search_term . '%', '%' . $search_term . '%', $current_user_id, $current_user_id, $current_user_id]);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['success' => true, 'users' => $users]);
?>
