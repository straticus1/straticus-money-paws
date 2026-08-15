<?php
/**
 * Money Paws - Cryptocurrency-Powered Pet Platform
 * Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>
 */
require_once '../includes/functions.php';

header('Content-Type: application/json');

try {
    $stmt = $pdo->prepare("
        SELECT g.reward_usd, u.name, g.completed_at 
        FROM games g 
        JOIN users u ON g.user_id = u.id 
        WHERE g.status = 'completed' AND g.reward_usd > 0 
        ORDER BY g.completed_at DESC 
        LIMIT 10
    ");
    $stmt->execute();
    $winners = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Add time ago calculation
    foreach ($winners as &$winner) {
        $winner['time_ago'] = timeAgo($winner['completed_at']);
    }
    
    echo json_encode([
        'success' => true,
        'winners' => $winners
    ]);
    
} catch (Exception $e) {
    error_log('Get recent winners error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}

function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . 'm ago';
    if ($time < 86400) return floor($time/3600) . 'h ago';
    if ($time < 2592000) return floor($time/86400) . 'd ago';
    return date('M j', strtotime($datetime));
}
?>
