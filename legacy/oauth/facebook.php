<?php
/**
 * Money Paws - Cryptocurrency-Powered Pet Platform
 * Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>
 */
require_once '../includes/functions.php';

// Facebook OAuth2 implementation
require_once '../vendor/autoload.php';

use GuzzleHttp\Client;

$client = new Client();
$redirectUri = SITE_URL . '/oauth/facebook.php';

// Step 1: Redirect user to Facebook's authorization endpoint
if (!isset($_GET['code'])) {
    $authUrl = 'https://www.facebook.com/v18.0/dialog/oauth?' . http_build_query([
        'client_id' => FACEBOOK_APP_ID,
        'redirect_uri' => $redirectUri,
        'scope' => 'email,public_profile',
        'response_type' => 'code',
    ]);
    header('Location: ' . $authUrl);
    exit;
}

// Step 2: Exchange authorization code for an access token
try {
    $response = $client->request('GET', 'https://graph.facebook.com/v18.0/oauth/access_token', [
        'query' => [
            'client_id' => FACEBOOK_APP_ID,
            'client_secret' => FACEBOOK_APP_SECRET,
            'redirect_uri' => $redirectUri,
            'code' => $_GET['code'],
        ]
    ]);

    $data = json_decode($response->getBody()->getContents(), true);
    if (empty($data['access_token'])) {
        throw new Exception('Failed to get access token.');
    }
    $accessToken = $data['access_token'];

    // Step 3: Use the access token to get user details
    $response = $client->request('GET', 'https://graph.facebook.com/me', [
        'query' => [
            'fields' => 'id,name,email,picture.type(large)',
            'access_token' => $accessToken,
        ]
    ]);

    $userData = json_decode($response->getBody()->getContents(), true);

    $email = $userData['email'] ?? null;
    $name = $userData['name'] ?? null;
    $facebookId = $userData['id'] ?? null;
    $avatar = $userData['picture']['data']['url'] ?? '';

    if (!$email || !$name || !$facebookId) {
        throw new Exception('Could not retrieve user information from Facebook.');
    }

    // Step 4: Check if user exists, then log in or register
    $existingUser = getUserByEmail($email);

    if ($existingUser) {
        loginUser($existingUser['id']);
    } else {
        $stmt = $pdo->prepare("INSERT INTO users (email, name, provider, provider_id, avatar, email_verified, created_at) VALUES (?, ?, 'facebook', ?, ?, 1, NOW())");
        $stmt->execute([$email, $name, $facebookId, $avatar]);
        $userId = $pdo->lastInsertId();
        loginUser($userId);
    }

    redirectTo('../index.php');

} catch (Exception $e) {
    error_log('Facebook OAuth error: ' . $e->getMessage());
    redirectTo('../login.php?error=oauth_failed');
}
?>
