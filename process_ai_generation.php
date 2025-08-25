<?php
/**
 * Money Paws - AI Generation Background Processor
 * Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>
 */
require_once 'includes/functions.php';
require_once 'includes/ai_generation.php';

// This script processes pending AI generation requests
// Can be run via cron job or called directly

if (!$pdo && (!defined('DEVELOPER_MODE') || !DEVELOPER_MODE)) {
    die("Database connection required for AI generation processing.\n");
}

// Get pending generations
if ($pdo) {
    $stmt = $pdo->prepare("SELECT id FROM ai_generations WHERE status = 'pending' ORDER BY created_at ASC LIMIT 5");
    $stmt->execute();
    $pendingGenerations = $stmt->fetchAll(PDO::FETCH_COLUMN);
} else {
    $pendingGenerations = [];
}

if (empty($pendingGenerations)) {
    echo "No pending AI generations to process.\n";
    exit(0);
}

echo "Processing " . count($pendingGenerations) . " AI generation requests...\n";

foreach ($pendingGenerations as $generationId) {
    echo "Processing generation ID: $generationId\n";
    
    if (defined('DEVELOPER_MODE') && DEVELOPER_MODE) {
        $result = processAIGenerationDemo($generationId);
    } else {
        $result = processAIGeneration($generationId);
    }
    
    if ($result['success']) {
        echo "✓ Successfully generated image: " . $result['url'] . "\n";
    } else {
        echo "✗ Failed to generate image: " . $result['error'] . "\n";
    }
    
    // Small delay between generations to avoid rate limits
    sleep(1);
}

echo "AI generation processing complete.\n";
?>
