<?php
/**
 * Money Paws - Cryptocurrency-Powered Pet Platform
 * Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>
 */
require_once '../includes/functions.php';

// Twitter/X OAuth2 implementation
// Note: You'll need to install Abraham's TwitterOAuth library via Composer
// composer require abraham/twitteroauth

if (!file_exists('../vendor/autoload.php')) {
    die('Please install TwitterOAuth: composer require abraham/twitteroauth');
}

require_once '../vendor/autoload.php';

use Abraham\TwitterOAuth\TwitterOAuth;

$connection = new TwitterOAuth(TWITTER_API_KEY, TWITTER_API_SECRET);

if (!isset($_GET['oauth_token']) || !isset($_GET['oauth_verifier'])) {
    // Step 1: Get request token
    $requestToken = $connection->oauth('oauth/request_token', array('oauth_callback' => SITE_URL . '/oauth/twitter.php'));
    
    $_SESSION['oauth_token'] = $requestToken['oauth_token'];
    $_SESSION['oauth_token_secret'] = $requestToken['oauth_token_secret'];
    
    // Step 2: Redirect to Twitter
    $url = $connection->url('oauth/authorize', array('oauth_token' => $requestToken['oauth_token']));
    header('Location: ' . $url);
    exit;
} else {
    // Step 3: Handle callback
    if (!isset($_SESSION['oauth_token']) || $_GET['oauth_token'] !== $_SESSION['oauth_token']) {
        die('Invalid OAuth token');
    }
    
    try {
        // Step 4: Get access token
        $connection = new TwitterOAuth(
            TWITTER_API_KEY,
            TWITTER_API_SECRET,
            $_SESSION['oauth_token'],
            $_SESSION['oauth_token_secret']
        );
        
        $accessToken = $connection->oauth('oauth/access_token', array('oauth_verifier' => $_GET['oauth_verifier']));
        
        // Step 5: Get user info
        $connection = new TwitterOAuth(
            TWITTER_API_KEY,
            TWITTER_API_SECRET,
            $accessToken['oauth_token'],
            $accessToken['oauth_token_secret']
        );
        
        $user = $connection->get('account/verify_credentials', array('include_email' => 'true'));
        
        if ($connection->getLastHttpCode() !== 200) {
            throw new Exception('Failed to get user info from Twitter');
        }
        
        $email = $user->email ?? '';
        $name = $user->name;
        $twitterId = $user->id_str;
        $avatar = $user->profile_image_url_https;
        
        // Twitter might not provide email
        if (empty($email)) {
            // Use Twitter ID as fallback email
            $email = 'twitter_' . $twitterId . '@twitter.local';
        }
        
        // Check if user exists
        $existingUser = getUserByEmail($email);
        
        if ($existingUser) {
            // User exists, log them in
            loginUser($existingUser['id']);
        } else {
            // Create new user
            $stmt = $pdo->prepare("INSERT INTO users (email, name, provider, provider_id, avatar, email_verified, created_at) VALUES (?, ?, 'twitter', ?, ?, 1, NOW())");
            $stmt->execute([$email, $name, $twitterId, $avatar]);
            
            $userId = $pdo->lastInsertId();
            loginUser($userId);
        }
        
        // Clean up session
        unset($_SESSION['oauth_token']);
        unset($_SESSION['oauth_token_secret']);
        
        redirectTo('../index.php');
        
    } catch (Exception $e) {
        error_log('Twitter OAuth error: ' . $e->getMessage());
        redirectTo('../login.php?error=oauth_failed');
    }
}
?>
