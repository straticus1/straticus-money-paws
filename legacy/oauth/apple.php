<?php
/**
 * Money Paws - Cryptocurrency-Powered Pet Platform
 * Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>
 */
require_once __DIR__ . '/../vendor/autoload.php';
require_once '../includes/functions.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * Converts a JWK to a PEM formatted key.
 *
 * @param array $jwk The JSON Web Key.
 * @return string The PEM formatted key.
 * @throws Exception
 */
function jwkToPem(array $jwk): string
{
    $n = base64_decode(strtr($jwk['n'], '-_', '+/'), true);
    $e = base64_decode(strtr($jwk['e'], '-_', '+/'), true);

    if ($n === false || $e === false) {
        throw new Exception('Failed to decode n or e from JWK');
    }

    $components = [
        'modulus' => new \phpseclib3\Math\BigInteger($n, 256),
        'publicExponent' => new \phpseclib3\Math\BigInteger($e, 256)
    ];

    $publicKey = \phpseclib3\Crypt\PublicKeyLoader::load($components);
    return $publicKey->toString('PKCS8');
}

// Apple Sign In implementation
// Note: Apple Sign In requires JWT handling and is more complex than other OAuth providers

if (!isset($_POST['id_token'])) {
    // Display Apple Sign In button (this would typically be handled by JavaScript)
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Apple Sign In</title>
        <script type="text/javascript" src="https://appleid.cdn-apple.com/appleauth/static/jsapi/appleid/1/en_US/appleid.auth.js"></script>
    </head>
    <body>
        <div id="appleid-signin" data-color="black" data-border="true" data-type="sign in"></div>
        <script type="text/javascript">
            AppleID.auth.init({
                clientId: '<?php echo APPLE_CLIENT_ID; ?>',
                scope: 'name email',
                redirectURI: '<?php echo SITE_URL; ?>/oauth/apple.php',
                state: '<?php echo generateRandomString(); ?>',
                usePopup: true
            });
        </script>
    </body>
    </html>
    <?php
    exit;
} else {
    try {
        // Verify the JWT token from Apple
        $idToken = $_POST['id_token'];
        
        // Fetch Apple's public keys
        $applePublicKeysResponse = file_get_contents('https://appleid.apple.com/auth/keys');
        if ($applePublicKeysResponse === false) {
            throw new Exception('Failed to fetch Apple public keys.');
        }
        $applePublicKeys = json_decode($applePublicKeysResponse, true);

        // Get the key identifier from the token header
        $tks = explode('.', $idToken);
        if (count($tks) !== 3) {
            throw new Exception('Wrong number of segments in token');
        }
        $header = json_decode(JWT::urlsafeB64Decode($tks[0]), true);
        $kid = $header['kid'];

        // Find the matching public key
        $publicKeyData = null;
        foreach ($applePublicKeys['keys'] as $key) {
            if ($key['kid'] === $kid) {
                $publicKeyData = $key;
                break;
            }
        }

        if (!$publicKeyData) {
            throw new Exception('Matching public key not found.');
        }

        // Convert the JWK to PEM
        $publicKey = jwkToPem($publicKeyData);

        // Decode and verify the JWT token
        $payload = JWT::decode($idToken, new Key($publicKey, 'RS256'));

        // Verify the issuer and audience
        if ($payload->iss !== 'https://appleid.apple.com' || $payload->aud !== APPLE_CLIENT_ID) {
            throw new Exception('Invalid token issuer or audience.');
        }
        
        $email = $payload->email ?? '';
        $appleId = $payload->sub ?? '';
        
        // Apple doesn't always provide name in subsequent logins
        $name = $_POST['user']['name']['firstName'] . ' ' . $_POST['user']['name']['lastName'] ?? 'Apple User';
        
        if (empty($email)) {
            throw new Exception('Email not provided by Apple');
        }
        
        // Check if user exists
        $user = getUserByEmail($email);
        
        if ($user) {
            // User exists, log them in
            loginUser($user['id']);
        } else {
            // Create new user
            $stmt = $pdo->prepare("INSERT INTO users (email, name, provider, provider_id, email_verified, created_at) VALUES (?, ?, 'apple', ?, 1, NOW())");
            $stmt->execute([$email, $name, $appleId]);
            
            $userId = $pdo->lastInsertId();
            loginUser($userId);
        }
        
        redirectTo('../index.php');
        
    } catch (Exception $e) {
        error_log('Apple OAuth error: ' . $e->getMessage());
        redirectTo('../login.php?error=oauth_failed');
    }
}
?>
