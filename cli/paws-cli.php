#!/usr/bin/env php
<?php
/**
 * Money Paws CLI - Accessible Command Line Interface
 * Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>
 * 
 * Accessible gaming client for blind and low-vision users
 * Supports screen readers and large print displays
 */

// Configuration
define('CLI_VERSION', '3.0.0');
define('API_BASE_URL', 'https://paws.money/api/');
define('CONFIG_FILE', __DIR__ . '/config.json');
define('SESSION_FILE', __DIR__ . '/session.json');

// Color codes for accessibility
define('COLOR_RESET', "\033[0m");
define('COLOR_BOLD', "\033[1m");
define('COLOR_GREEN', "\033[32m");
define('COLOR_RED', "\033[31m");
define('COLOR_YELLOW', "\033[33m");
define('COLOR_BLUE', "\033[34m");
define('COLOR_CYAN', "\033[36m");

class PawsCLI {
    private $config;
    private $session;
    private $accessibilityMode = 'default';
    
    public function __construct() {
        $this->loadConfig();
        $this->loadSession();
        $this->detectAccessibilityMode();
    }
    
    private function loadConfig() {
        if (file_exists(CONFIG_FILE)) {
            $this->config = json_decode(file_get_contents(CONFIG_FILE), true);
        } else {
            $this->config = [
                'api_url' => API_BASE_URL,
                'accessibility_mode' => 'default',
                'large_print' => false,
                'high_contrast' => false,
                'sound_enabled' => true
            ];
            $this->saveConfig();
        }
    }
    
    private function saveConfig() {
        file_put_contents(CONFIG_FILE, json_encode($this->config, JSON_PRETTY_PRINT));
    }
    
    private function loadSession() {
        if (file_exists(SESSION_FILE)) {
            $this->session = json_decode(file_get_contents(SESSION_FILE), true);
        } else {
            $this->session = ['logged_in' => false];
        }
    }
    
    private function saveSession() {
        file_put_contents(SESSION_FILE, json_encode($this->session, JSON_PRETTY_PRINT));
    }
    
    private function detectAccessibilityMode() {
        // Check for screen reader environment variables
        if (getenv('NVDA_RUNNING') || getenv('JAWS_RUNNING') || getenv('ORCA_RUNNING')) {
            $this->accessibilityMode = 'screen_reader';
        }
        
        // Apply user preferences
        if ($this->config['large_print']) {
            $this->accessibilityMode = 'large_print';
        }
    }
    
    public function run() {
        $this->clearScreen();
        $this->showWelcome();
        
        while (true) {
            $this->showMainMenu();
            $choice = $this->getInput("Enter your choice: ");
            
            switch ($choice) {
                case '1':
                    $this->handleAuth();
                    break;
                case '2':
                    $this->playGame();
                    break;
                case '3':
                    $this->viewProfile();
                    break;
                case '4':
                    $this->viewBalances();
                    break;
                case '5':
                    $this->accessibilitySettings();
                    break;
                case '6':
                    $this->showHelp();
                    break;
                case '0':
                case 'quit':
                case 'exit':
                    $this->output("Thank you for playing Money Paws! Goodbye! 🐾", 'success');
                    exit(0);
                default:
                    $this->output("Invalid choice. Please try again.", 'error');
            }
        }
    }
    
    private function showWelcome() {
        $this->output("=" . str_repeat("=", 50) . "=", 'bold');
        $this->output("🐾 MONEY PAWS - ACCESSIBLE CLI CLIENT 🐾", 'bold');
        $this->output("Version " . CLI_VERSION, 'info');
        $this->output("Developed by Ryan Coleman", 'info');
        $this->output("=" . str_repeat("=", 50) . "=", 'bold');
        $this->output("");
        
        if ($this->accessibilityMode === 'screen_reader') {
            $this->output("Screen reader detected. Enhanced accessibility mode enabled.", 'success');
        }
        
        $this->output("Welcome to Money Paws CLI! This interface is designed for accessibility.");
        $this->output("All features support screen readers and keyboard navigation.");
        $this->output("");
    }
    
    private function showMainMenu() {
        $this->output("MAIN MENU", 'bold');
        $this->output("----------", 'bold');
        
        if (!$this->session['logged_in']) {
            $this->output("1. Login / Register");
        } else {
            $this->output("1. Logout (" . $this->session['user_name'] . ")");
        }
        
        $this->output("2. Play Crypto Game" . ($this->session['logged_in'] ? "" : " (Login Required)"));
        $this->output("3. View Profile" . ($this->session['logged_in'] ? "" : " (Login Required)"));
        $this->output("4. View Crypto Balances" . ($this->session['logged_in'] ? "" : " (Login Required)"));
        $this->output("5. Accessibility Settings");
        $this->output("6. Help & Instructions");
        $this->output("0. Exit");
        $this->output("");
    }
    
    private function handleAuth() {
        if ($this->session['logged_in']) {
            $this->logout();
        } else {
            $this->showAuthMenu();
        }
    }
    
    private function showAuthMenu() {
        $this->output("AUTHENTICATION", 'bold');
        $this->output("---------------", 'bold');
        $this->output("1. Login with Email/Password");
        $this->output("2. Register New Account");
        $this->output("3. Login with Google");
        $this->output("4. Login with Facebook");
        $this->output("5. Login with Apple");
        $this->output("6. Login with Twitter/X");
        $this->output("0. Back to Main Menu");
        $this->output("");
        
        $choice = $this->getInput("Choose authentication method: ");
        
        switch ($choice) {
            case '1':
                $this->loginWithEmail();
                break;
            case '2':
                $this->registerAccount();
                break;
            case '3':
                $this->loginWithOAuth('google');
                break;
            case '4':
                $this->loginWithOAuth('facebook');
                break;
            case '5':
                $this->loginWithOAuth('apple');
                break;
            case '6':
                $this->loginWithOAuth('twitter');
                break;
            case '0':
                return;
            default:
                $this->output("Invalid choice.", 'error');
        }
    }
    
    private function loginWithEmail() {
        $this->output("EMAIL LOGIN", 'bold');
        $this->output("-----------", 'bold');
        
        $email = $this->getInput("Email address: ");
        $password = $this->getSecureInput("Password: ");
        
        // Simulate API call for demo
        $this->output("Logging in...", 'info');
        sleep(1);
        
        // In real implementation, make API call to login endpoint
        $this->session = [
            'logged_in' => true,
            'user_id' => 1,
            'user_name' => 'Demo User',
            'email' => $email,
            'auth_token' => 'demo_token_' . time()
        ];
        $this->saveSession();
        
        $this->output("Login successful! Welcome back!", 'success');
        $this->playSound('success');
        $this->pause();
    }
    
    private function registerAccount() {
        $this->output("ACCOUNT REGISTRATION", 'bold');
        $this->output("-------------------", 'bold');
        
        $name = $this->getInput("Full name: ");
        $email = $this->getInput("Email address: ");
        $password = $this->getSecureInput("Password: ");
        $confirmPassword = $this->getSecureInput("Confirm password: ");
        
        if ($password !== $confirmPassword) {
            $this->output("Passwords do not match!", 'error');
            return;
        }
        
        $this->output("Creating account...", 'info');
        sleep(1);
        
        $this->output("Account created successfully!", 'success');
        $this->output("Please check your email for verification.", 'info');
        $this->playSound('success');
        $this->pause();
    }
    
    private function loginWithOAuth($provider) {
        $this->output("OAUTH LOGIN - " . strtoupper($provider), 'bold');
        $this->output(str_repeat("-", 20), 'bold');
        
        $this->output("Opening browser for $provider authentication...", 'info');
        $this->output("Please complete the login in your web browser.", 'info');
        $this->output("Press Enter when authentication is complete...");
        
        $this->getInput("");
        
        // Simulate successful OAuth
        $this->session = [
            'logged_in' => true,
            'user_id' => 1,
            'user_name' => 'OAuth User',
            'email' => 'user@example.com',
            'auth_token' => 'oauth_token_' . time(),
            'provider' => $provider
        ];
        $this->saveSession();
        
        $this->output("OAuth login successful!", 'success');
        $this->playSound('success');
        $this->pause();
    }
    
    private function logout() {
        $this->session = ['logged_in' => false];
        $this->saveSession();
        $this->output("Logged out successfully.", 'success');
        $this->pause();
    }
    
    private function playGame() {
        if (!$this->session['logged_in']) {
            $this->output("Please login first to play games.", 'error');
            $this->pause();
            return;
        }
        
        $this->output("CRYPTO GAMING", 'bold');
        $this->output("-------------", 'bold');
        $this->output("Welcome to the accessible crypto game!");
        $this->output("");
        
        // Get current balances
        $this->output("Checking your crypto balances...", 'info');
        $balances = $this->getBalances();
        
        if (empty($balances) || array_sum($balances) == 0) {
            $this->output("You need crypto funds to play. Please deposit first.", 'error');
            $this->pause();
            return;
        }
        
        $this->output("Available balances:", 'info');
        foreach ($balances as $crypto => $amount) {
            if ($amount > 0) {
                $this->output("  $crypto: " . number_format($amount, 8), 'info');
            }
        }
        $this->output("");
        
        $crypto = $this->getInput("Which crypto to play with (BTC/ETH/USDC/SOL/XRP): ");
        $crypto = strtoupper($crypto);
        
        if (!isset($balances[$crypto]) || $balances[$crypto] <= 0) {
            $this->output("Insufficient $crypto balance.", 'error');
            $this->pause();
            return;
        }
        
        $betAmount = floatval($this->getInput("Bet amount in $crypto: "));
        
        if ($betAmount <= 0 || $betAmount > $balances[$crypto]) {
            $this->output("Invalid bet amount.", 'error');
            $this->pause();
            return;
        }
        
        $this->playAccessibleGame($crypto, $betAmount);
    }
    
    private function playAccessibleGame($crypto, $betAmount) {
        $this->output("STARTING GAME", 'bold');
        $this->output("Bet: $betAmount $crypto", 'info');
        $this->output("");
        
        $this->output("Game: Crypto Coin Flip", 'bold');
        $this->output("Choose heads or tails. Win double your bet!", 'info');
        $this->output("");
        
        $choice = strtolower($this->getInput("Choose (heads/tails): "));
        
        if (!in_array($choice, ['heads', 'tails'])) {
            $this->output("Invalid choice. Please choose heads or tails.", 'error');
            return;
        }
        
        $this->output("Flipping coin...", 'info');
        $this->playSound('flip');
        
        // Animated countdown for accessibility
        for ($i = 3; $i > 0; $i--) {
            $this->output("$i...", 'info');
            sleep(1);
        }
        
        $result = rand(0, 1) ? 'heads' : 'tails';
        $won = ($choice === $result);
        
        $this->output("Result: " . strtoupper($result), 'bold');
        
        if ($won) {
            $winAmount = $betAmount * 2;
            $this->output("🎉 CONGRATULATIONS! YOU WON!", 'success');
            $this->output("You won $winAmount $crypto!", 'success');
            $this->playSound('win');
        } else {
            $this->output("😔 Sorry, you lost this round.", 'error');
            $this->output("You lost $betAmount $crypto.", 'error');
            $this->playSound('lose');
        }
        
        $this->output("");
        $this->pause();
    }
    
    private function viewProfile() {
        if (!$this->session['logged_in']) {
            $this->output("Please login first.", 'error');
            $this->pause();
            return;
        }
        
        $this->output("USER PROFILE", 'bold');
        $this->output("------------", 'bold');
        $this->output("Name: " . $this->session['user_name']);
        $this->output("Email: " . $this->session['email']);
        if (isset($this->session['provider'])) {
            $this->output("Login Method: " . ucfirst($this->session['provider']));
        }
        $this->output("User ID: " . $this->session['user_id']);
        $this->output("");
        $this->pause();
    }
    
    private function viewBalances() {
        if (!$this->session['logged_in']) {
            $this->output("Please login first.", 'error');
            $this->pause();
            return;
        }
        
        $this->output("CRYPTO BALANCES", 'bold');
        $this->output("---------------", 'bold');
        
        $balances = $this->getBalances();
        $totalUSD = 0;
        
        foreach ($balances as $crypto => $amount) {
            $usdValue = $this->convertToUSD($crypto, $amount);
            $totalUSD += $usdValue;
            
            $this->output(sprintf("%-6s: %12.8f ($%.2f USD)", 
                $crypto, $amount, $usdValue), 'info');
        }
        
        $this->output(str_repeat("-", 30));
        $this->output(sprintf("Total Portfolio Value: $%.2f USD", $totalUSD), 'bold');
        $this->output("");
        $this->pause();
    }
    
    private function getBalances() {
        // Mock balances for demo
        return [
            'BTC' => 0.001,
            'ETH' => 0.05,
            'USDC' => 100.0,
            'SOL' => 2.5,
            'XRP' => 200.0
        ];
    }
    
    private function convertToUSD($crypto, $amount) {
        $rates = [
            'BTC' => 45000,
            'ETH' => 3000,
            'USDC' => 1,
            'SOL' => 100,
            'XRP' => 0.5
        ];
        
        return $amount * ($rates[$crypto] ?? 1);
    }
    
    private function accessibilitySettings() {
        $this->output("ACCESSIBILITY SETTINGS", 'bold');
        $this->output("---------------------", 'bold');
        $this->output("1. Toggle Large Print Mode: " . ($this->config['large_print'] ? 'ON' : 'OFF'));
        $this->output("2. Toggle High Contrast: " . ($this->config['high_contrast'] ? 'ON' : 'OFF'));
        $this->output("3. Toggle Sound Effects: " . ($this->config['sound_enabled'] ? 'ON' : 'OFF'));
        $this->output("4. Test Screen Reader Compatibility");
        $this->output("0. Back to Main Menu");
        $this->output("");
        
        $choice = $this->getInput("Choose setting to modify: ");
        
        switch ($choice) {
            case '1':
                $this->config['large_print'] = !$this->config['large_print'];
                $this->saveConfig();
                $this->output("Large print mode " . ($this->config['large_print'] ? 'enabled' : 'disabled'), 'success');
                break;
            case '2':
                $this->config['high_contrast'] = !$this->config['high_contrast'];
                $this->saveConfig();
                $this->output("High contrast mode " . ($this->config['high_contrast'] ? 'enabled' : 'disabled'), 'success');
                break;
            case '3':
                $this->config['sound_enabled'] = !$this->config['sound_enabled'];
                $this->saveConfig();
                $this->output("Sound effects " . ($this->config['sound_enabled'] ? 'enabled' : 'disabled'), 'success');
                break;
            case '4':
                $this->testScreenReader();
                break;
            case '0':
                return;
        }
        
        $this->pause();
    }
    
    private function testScreenReader() {
        $this->output("SCREEN READER TEST", 'bold');
        $this->output("------------------", 'bold');
        $this->output("This is a test message for screen reader compatibility.");
        $this->output("If you can hear this clearly, the interface is working properly.");
        $this->output("All game elements include descriptive text for screen readers.");
        $this->output("Navigation uses standard keyboard shortcuts.");
        $this->output("");
    }
    
    private function showHelp() {
        $this->output("HELP & INSTRUCTIONS", 'bold');
        $this->output("-------------------", 'bold');
        $this->output("Money Paws CLI is designed for maximum accessibility:");
        $this->output("");
        $this->output("NAVIGATION:");
        $this->output("• Use number keys to select menu options");
        $this->output("• Type 'quit' or 'exit' at any time to exit");
        $this->output("• Press Enter to confirm selections");
        $this->output("");
        $this->output("ACCESSIBILITY FEATURES:");
        $this->output("• Full screen reader support");
        $this->output("• Large print mode for low vision users");
        $this->output("• High contrast display options");
        $this->output("• Audio feedback for game events");
        $this->output("• Keyboard-only navigation");
        $this->output("");
        $this->output("GAMING:");
        $this->output("• Login required to play games");
        $this->output("• Crypto balances needed for betting");
        $this->output("• All game results announced clearly");
        $this->output("");
        $this->output("SUPPORT:");
        $this->output("• Email: coleman.ryan@gmail.com");
        $this->output("• This client is free for accessibility users");
        $this->output("");
        $this->pause();
    }
    
    private function output($text, $style = 'normal') {
        $prefix = '';
        $suffix = COLOR_RESET;
        
        switch ($style) {
            case 'bold':
                $prefix = COLOR_BOLD;
                break;
            case 'success':
                $prefix = COLOR_GREEN;
                break;
            case 'error':
                $prefix = COLOR_RED;
                break;
            case 'info':
                $prefix = COLOR_CYAN;
                break;
            case 'warning':
                $prefix = COLOR_YELLOW;
                break;
        }
        
        // Large print mode
        if ($this->config['large_print']) {
            $text = strtoupper($text);
        }
        
        echo $prefix . $text . $suffix . "\n";
    }
    
    private function getInput($prompt) {
        echo $prompt;
        return trim(fgets(STDIN));
    }
    
    private function getSecureInput($prompt) {
        echo $prompt;
        
        // Hide password input on Unix systems
        if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
            system('stty -echo');
            $password = trim(fgets(STDIN));
            system('stty echo');
            echo "\n";
        } else {
            $password = trim(fgets(STDIN));
        }
        
        return $password;
    }
    
    private function clearScreen() {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            system('cls');
        } else {
            system('clear');
        }
    }
    
    private function pause() {
        $this->output("");
        $this->getInput("Press Enter to continue...");
    }
    
    private function playSound($type) {
        if (!$this->config['sound_enabled']) {
            return;
        }
        
        // Simple beep sounds for different events
        switch ($type) {
            case 'success':
                echo "\007\007"; // Double beep
                break;
            case 'error':
                echo "\007"; // Single beep
                break;
            case 'win':
                echo "\007\007\007"; // Triple beep
                break;
            case 'lose':
                echo "\007"; // Single beep
                break;
            case 'flip':
                // No sound for flip to avoid distraction
                break;
        }
    }
}

// Main execution
if (php_sapi_name() === 'cli') {
    $cli = new PawsCLI();
    $cli->run();
} else {
    echo "This script must be run from the command line.\n";
    exit(1);
}
?>
