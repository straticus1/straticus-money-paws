<?php
header('Content-Type: application/json');
require_once '../includes/functions.php';
require_once '../includes/personalities.php';

if (!is_logged_in() || !is_admin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$pet_id = $_POST['pet_id'] ?? null;
$bravery = $_POST['bravery'] ?? null;
$friendliness = $_POST['friendliness'] ?? null;
$curiosity = $_POST['curiosity'] ?? null;
$laziness = $_POST['laziness'] ?? null;
$greed = $_POST['greed'] ?? null;

if (!$pet_id) {
    header('Location: /admin/personalities.php?error=1');
    exit;
}

try {
    $pdo = get_db();
    $pdo->beginTransaction();

    $traits = [
        'bravery' => $bravery,
        'friendliness' => $friendliness,
        'curiosity' => $curiosity,
        'laziness' => $laziness,
        'greed' => $greed
    ];

    foreach ($traits as $trait => $value) {
        if ($value !== null) {
            $stmt = $pdo->prepare('UPDATE pet_personalities SET value = :value WHERE pet_id = :pet_id AND trait = :trait');
            $stmt->execute(['value' => (int)$value, 'pet_id' => $pet_id, 'trait' => $trait]);
        }
    }

    $pdo->commit();
    header('Location: /admin/personalities.php?success=1');
    exit;
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Error updating personality: ' . $e->getMessage());
    header('Location: /admin/personalities.php?error=1');
    exit;
}
