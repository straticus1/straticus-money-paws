<?php
/**
 * Money Paws - AI Image Generation Functions
 * Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>
 */

/**
 * Generate AI pet image using OpenAI DALL-E 3
 */
function generateWithOpenAI($description, $animalType, $style) {
    $apiKey = OPENAI_API_KEY;
    
    if ($apiKey === 'your_openai_api_key') {
        return ['success' => false, 'error' => 'OpenAI API key not configured'];
    }
    
    // Enhanced prompt for better pet generation
    $enhancedPrompt = "A beautiful, high-quality photograph of a {$animalType}. {$description}. Style: {$style}. The image should be cute, detailed, and suitable for a pet gallery. Professional photography lighting, sharp focus, adorable expression.";
    
    $data = [
        'model' => 'dall-e-3',
        'prompt' => $enhancedPrompt,
        'n' => 1,
        'size' => '1024x1024',
        'quality' => 'standard',
        'response_format' => 'url'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.openai.com/v1/images/generations');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        $errorData = json_decode($response, true);
        return ['success' => false, 'error' => $errorData['error']['message'] ?? 'OpenAI API error'];
    }
    
    $result = json_decode($response, true);
    
    if (isset($result['data'][0]['url'])) {
        return ['success' => true, 'url' => $result['data'][0]['url']];
    }
    
    return ['success' => false, 'error' => 'No image generated'];
}

/**
 * Generate AI pet image using Stability AI
 */
function generateWithStabilityAI($description, $animalType, $style) {
    $apiKey = STABILITY_AI_API_KEY;
    
    if ($apiKey === 'your_stability_ai_api_key') {
        return ['success' => false, 'error' => 'Stability AI API key not configured'];
    }
    
    // Enhanced prompt for better pet generation
    $enhancedPrompt = "A beautiful, high-quality image of a {$animalType}. {$description}. Art style: {$style}. Cute, detailed, adorable, professional quality, sharp focus, good lighting.";
    
    $data = [
        'text_prompts' => [
            [
                'text' => $enhancedPrompt,
                'weight' => 1
            ]
        ],
        'cfg_scale' => 7,
        'height' => 1024,
        'width' => 1024,
        'samples' => 1,
        'steps' => 30,
        'style_preset' => $style === 'realistic' ? 'photographic' : 'digital-art'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.stability.ai/v1/generation/stable-diffusion-xl-1024-v1-0/text-to-image');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey,
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        $errorData = json_decode($response, true);
        return ['success' => false, 'error' => $errorData['message'] ?? 'Stability AI API error'];
    }
    
    $result = json_decode($response, true);
    
    if (isset($result['artifacts'][0]['base64'])) {
        // Save base64 image to file
        $imageData = base64_decode($result['artifacts'][0]['base64']);
        $filename = 'ai_pet_' . time() . '_' . uniqid() . '.png';
        $filepath = 'uploads/ai_generated/' . $filename;
        
        // Create directory if it doesn't exist
        if (!file_exists('uploads/ai_generated/')) {
            mkdir('uploads/ai_generated/', 0755, true);
        }
        
        if (file_put_contents($filepath, $imageData)) {
            return ['success' => true, 'url' => $filepath];
        }
    }
    
    return ['success' => false, 'error' => 'Failed to save generated image'];
}

/**
 * Process AI generation request
 */
function processAIGeneration($generationId) {
    global $pdo;
    
    if (!$pdo) {
        return ['success' => false, 'error' => 'Database not available'];
    }
    
    // Get generation request
    $stmt = $pdo->prepare("SELECT * FROM ai_generations WHERE id = ? AND status = 'pending'");
    $stmt->execute([$generationId]);
    $generation = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$generation) {
        return ['success' => false, 'error' => 'Generation request not found'];
    }
    
    // Update status to processing
    $stmt = $pdo->prepare("UPDATE ai_generations SET status = 'processing', updated_at = NOW() WHERE id = ?");
    $stmt->execute([$generationId]);
    
    // Try OpenAI first, fallback to Stability AI
    $result = generateWithOpenAI($generation['description'], $generation['animal_type'], $generation['style']);
    
    if (!$result['success']) {
        $result = generateWithStabilityAI($generation['description'], $generation['animal_type'], $generation['style']);
    }
    
    if ($result['success']) {
        // Update generation with success
        $stmt = $pdo->prepare("UPDATE ai_generations SET status = 'completed', generated_image_url = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$result['url'], $generationId]);
        
        return ['success' => true, 'url' => $result['url']];
    } else {
        // Update generation with failure
        $stmt = $pdo->prepare("UPDATE ai_generations SET status = 'failed', error_message = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$result['error'], $generationId]);
        
        return ['success' => false, 'error' => $result['error']];
    }
}

/**
 * Process AI generation in developer mode (mock generation)
 */
function processAIGenerationDemo($generationId) {
    global $pdo;
    
    if (!$pdo) {
        return ['success' => false, 'error' => 'Database not available'];
    }
    
    // Get generation request
    $stmt = $pdo->prepare("SELECT * FROM ai_generations WHERE id = ? AND status = 'pending'");
    $stmt->execute([$generationId]);
    $generation = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$generation) {
        return ['success' => false, 'error' => 'Generation request not found'];
    }
    
    // Update status to processing
    $stmt = $pdo->prepare("UPDATE ai_generations SET status = 'processing', updated_at = NOW() WHERE id = ?");
    $stmt->execute([$generationId]);
    
    // In demo mode, use a placeholder image service
    $animalType = $generation['animal_type'];
    $demoImageUrl = "https://picsum.photos/1024/1024?random=" . $generationId;
    
    // Simulate processing time
    sleep(2);
    
    // Update generation with demo result
    $stmt = $pdo->prepare("UPDATE ai_generations SET status = 'completed', generated_image_url = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$demoImageUrl, $generationId]);
    
    return ['success' => true, 'url' => $demoImageUrl];
}
?>
