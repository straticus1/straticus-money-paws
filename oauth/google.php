<?php
/**
 * Money Paws - Cryptocurrency-Powered Pet Platform
 * Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>
 */
require_once '../includes/functions.php';

// Google OAuth2 implementation
// Note: You'll need to install Google Client Library via Composer
// composer require google/apiclient

if (!file_exists('../vendor/autoload.php')) {
    die('Please install Google Client Library: composer require google/apiclient');
}

require_once '../vendor/autoload.php';

$client = new Google_Client();
$client->setClientId(GOOGLE_CLIENT_ID);
$client->setClientSecret(GOOGLE_CLIENT_SECRET);
$client->setRedirectUri(SITE_URL . '/oauth/google.php');
$client->addScope('email');
$client->addScope('profile');

if (!isset($_GET['code'])) {
    // Generate state parameter for security
    $state = generateRandomString();
    $_SESSION['oauth_state'] = $state;
    $client->setState($state);
    
    // Redirect to Google OAuth
    $authUrl = $client->createAuthUrl();
    header('Location: ' . $authUrl);
    exit;
} else {
    // Verify state parameter
    if (!isset($_SESSION['oauth_state']) || $_GET['state'] !== $_SESSION['oauth_state']) {
        die('Invalid state parameter');
    }
    
    unset($_SESSION['oauth_state']);
    
    try {
        // Exchange authorization code for access token
        $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
        $client->setAccessToken($token);
        
        // Get user info
        $oauth = new Google_Service_Oauth2($client);
        $userInfo = $oauth->userinfo->get();
        
        $email = $userInfo->email;
        $name = $userInfo->name;
        $googleId = $userInfo->id;
        $avatar = $userInfo->picture;
        
        // Check if user exists
        $user = getUserByEmail($email);
        
        if ($user) {
            // User exists, log them in
            loginUser($user['id']);
        } else {
            // Create new user
            $stmt = $pdo->prepare("INSERT INTO users (email, name, provider, provider_id, avatar, email_verified, created_at) VALUES (?, ?, 'google', ?, ?, 1, NOW())");
            $stmt->execute([$email, $name, $googleId, $avatar]);
            
            $userId = $pdo->lastInsertId();
            loginUser($userId);
        }
        
        redirectTo('../index.php');
        
    } catch (Exception $e) {
        error_log('Google OAuth error: ' . $e->getMessage());
        redirectTo('../login.php?error=oauth_failed');
    }
}
?>
