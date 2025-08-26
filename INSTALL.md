# Money Paws Installation Guide 🐾
*Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>*

This guide provides comprehensive installation instructions for Money Paws, a cryptocurrency-powered pet platform.

## 📋 System Requirements

### Minimum Requirements
- **PHP**: 7.4+ (PHP 8.0+ recommended)
- **MySQL**: 5.7+ (MySQL 8.0+ recommended)
- **Web Server**: Apache 2.4+ or Nginx 1.18+
- **Memory**: 2GB RAM minimum
- **Storage**: 10GB available space
- **SSL Certificate**: Required for OAuth2 and crypto payments

### PHP Extensions Required
- `pdo` and `pdo_mysql` - Database operations
- `pdo_sqlite` - SQLite support (for CLI and testing)
- `curl` - API communications
- `json` - Data processing
- `mbstring` - String handling
- `openssl` - Security operations
- `fileinfo` - File validation
- `gd` or `imagick` - Image processing

### Additional Software
- **Composer** - PHP dependency manager
- **Git** - Version control (for cloning repository)
- **Node.js 16+** - For desktop application (optional)
- **npm/yarn** - For desktop app dependencies (optional)

## 🚀 Installation Methods

### Method 1: Automated Installation (Recommended)

#### Step 1: Download and Setup
```bash
# Clone the repository
git clone https://github.com/yourusername/money-paws.git
cd money-paws

# Install PHP dependencies
composer install

# Make installer executable
chmod +x install.sh

# Run automated installer
./install.sh
```

The automated installer will:
- Check system requirements
- Create database and user
- Import database schema
- Set up directory permissions
- Create security files
- Configure basic settings

#### Step 2: Web-Based Configuration
After running the shell installer, navigate to:
```
http://yourdomain.com/install.php
```

Complete the web-based setup for:
- API key configuration
- OAuth2 provider setup
- Admin account creation
- Final system verification

### Method 2: Manual Installation

#### Step 1: Download Source Code
```bash
git clone https://github.com/yourusername/money-paws.git
cd money-paws
composer install
```

#### Step 2: Database Setup
Create MySQL database and user:
```sql
CREATE DATABASE money_paws CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'money_paws_user'@'localhost' IDENTIFIED BY 'secure_password';
GRANT ALL PRIVILEGES ON money_paws.* TO 'money_paws_user'@'localhost';
FLUSH PRIVILEGES;
```

Import the database schema:
```bash
mysql -u money_paws_user -p money_paws < database/schema.sql
```

#### Step 3: Configuration
Copy and edit the configuration file:
```bash
cp config/database.php.example config/database.php
```

Edit `config/database.php` with your settings:
```php
<?php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'money_paws');
define('DB_USER', 'money_paws_user');
define('DB_PASS', 'your_secure_password');

// Coinbase Commerce (Required for crypto features)
define('COINBASE_API_KEY', 'your_coinbase_api_key');
define('COINBASE_WEBHOOK_SECRET', 'your_webhook_secret');

// OAuth2 Configuration (Optional)
define('GOOGLE_CLIENT_ID', 'your_google_client_id');
define('GOOGLE_CLIENT_SECRET', 'your_google_client_secret');
define('FACEBOOK_APP_ID', 'your_facebook_app_id');
define('FACEBOOK_APP_SECRET', 'your_facebook_app_secret');
define('APPLE_CLIENT_ID', 'your_apple_client_id');
define('APPLE_TEAM_ID', 'your_apple_team_id');
define('APPLE_KEY_ID', 'your_apple_key_id');
define('TWITTER_CLIENT_ID', 'your_twitter_client_id');
define('TWITTER_CLIENT_SECRET', 'your_twitter_client_secret');

// AI Services (Optional)
define('OPENAI_API_KEY', 'your_openai_api_key');
define('STABILITY_API_KEY', 'your_stability_api_key');

// Admin Pricing Configuration
define('AI_GENERATION_PRICE', 5.00);
define('GAME_ENTRY_FEE', 1.00);
define('PREMIUM_UPLOAD_PRICE', 2.00);
define('MONTHLY_SUBSCRIPTION_PRICE', 10.00);

// Supported Cryptocurrencies
define('SUPPORTED_CRYPTOS', [
    'BTC' => 'Bitcoin',
    'ETH' => 'Ethereum',
    'USDC' => 'USD Coin',
    'SOL' => 'Solana',
    'XRP' => 'Ripple'
]);
?>
```

#### Step 4: Directory Permissions
Set proper permissions for upload directories:
```bash
# Create upload directories
mkdir -p uploads/pets uploads/ai-generated uploads/temp

# Set permissions
chmod 755 uploads/
chmod 755 uploads/pets/
chmod 755 uploads/ai-generated/
chmod 755 uploads/temp/

# Set ownership (adjust user/group as needed)
chown -R www-data:www-data uploads/
```

#### Step 5: Security Files
Create `.htaccess` files for security:

**Root `.htaccess`:**
```apache
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# Security headers
Header always set X-Content-Type-Options nosniff
Header always set X-Frame-Options DENY
Header always set X-XSS-Protection "1; mode=block"
Header always set Strict-Transport-Security "max-age=63072000; includeSubDomains; preload"
Header always set Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' https:; connect-src 'self' https:;"

# Hide sensitive files
<Files "composer.json">
    Require all denied
</Files>
<Files "composer.lock">
    Require all denied
</Files>
<Files "*.md">
    Require all denied
</Files>
```

**Config directory `.htaccess`:**
```apache
Require all denied
```

**Uploads directory `.htaccess`:**
```apache
# Prevent PHP execution in uploads
<Files "*.php">
    Require all denied
</Files>
<Files "*.phtml">
    Require all denied
</Files>
<Files "*.php3">
    Require all denied
</Files>
<Files "*.php4">
    Require all denied
</Files>
<Files "*.php5">
    Require all denied
</Files>

# Allow only specific file types
<FilesMatch "\.(jpg|jpeg|png|gif|webp)$">
    Require all granted
</FilesMatch>
```

#### Step 6: Web Server Configuration

**Apache Virtual Host Example:**
```apache
<VirtualHost *:443>
    ServerName paws.money
    DocumentRoot /var/www/money-paws
    
    SSLEngine on
    SSLCertificateFile /path/to/certificate.crt
    SSLCertificateKeyFile /path/to/private.key
    
    <Directory /var/www/money-paws>
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/money-paws_error.log
    CustomLog ${APACHE_LOG_DIR}/money-paws_access.log combined
</VirtualHost>
```

**Nginx Configuration Example:**
```nginx
server {
    listen 443 ssl http2;
    server_name paws.money;
    root /var/www/money-paws;
    index index.php index.html;
    
    ssl_certificate /path/to/certificate.crt;
    ssl_certificate_key /path/to/private.key;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.0-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
    
    location /config/ {
        deny all;
    }
    
    location /uploads/ {
        location ~ \.php$ {
            deny all;
        }
    }
}
```

## 🔑 API Keys Setup

### 1. Coinbase Commerce (Required)
1. Visit [Coinbase Commerce](https://commerce.coinbase.com/)
2. Create account and verify business
3. Go to Settings → API Keys
4. Create new API key with required permissions
5. Copy API key and webhook secret to config

### 2. Google OAuth2 (Optional)
1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create new project or select existing
3. Enable Google+ API
4. Create OAuth2 credentials
5. Add authorized redirect URIs:
   - `https://yourdomain.com/oauth/google.php`
6. Copy Client ID and Secret to config

### 3. Facebook Login (Optional)
1. Visit [Facebook Developers](https://developers.facebook.com/)
2. Create new app
3. Add Facebook Login product
4. Configure OAuth redirect URIs:
   - `https://yourdomain.com/oauth/facebook.php`
5. Copy App ID and Secret to config

### 4. Apple Sign-In (Optional)
1. Go to [Apple Developer](https://developer.apple.com/)
2. Create App ID with Sign In with Apple capability
3. Create Service ID for web authentication
4. Configure return URLs:
   - `https://yourdomain.com/oauth/apple.php`
5. Generate private key and copy credentials

### 5. Twitter API (Optional)
1. Visit [Twitter Developer Portal](https://developer.twitter.com/)
2. Create new app
3. Enable OAuth 2.0
4. Add callback URL:
   - `https://yourdomain.com/oauth/twitter.php`
5. Copy Client ID and Secret

### 6. OpenAI API (Optional)
1. Go to [OpenAI Platform](https://platform.openai.com/)
2. Create account and add payment method
3. Generate API key
4. Copy key to config

### 7. Stability AI (Optional)
1. Visit [Stability AI Platform](https://platform.stability.ai/)
2. Create account
3. Generate API key
4. Copy key to config

## 🧪 Testing Installation

### 1. Basic Functionality Test
```bash
# Test database connection
php -r "
require_once 'config/database.php';
try {
    \$pdo = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME, DB_USER, DB_PASS);
    echo 'Database connection: SUCCESS\n';
} catch (Exception \$e) {
    echo 'Database connection: FAILED - ' . \$e->getMessage() . '\n';
}
"

# Test file permissions
touch uploads/test.txt && rm uploads/test.txt && echo "Upload permissions: SUCCESS" || echo "Upload permissions: FAILED"
```

### 2. Web Interface Test
1. Navigate to your domain
2. Test user registration
3. Test file upload
4. Test crypto balance display
5. Test OAuth2 login (if configured)

### 3. API Endpoints Test
```bash
# Test API endpoints
curl -X GET "https://yourdomain.com/api/get-balances.php"
curl -X POST "https://yourdomain.com/api/toggle-like.php" -d "pet_id=1"
```

## 🔧 Post-Installation Configuration

### 1. Email Configuration (Required for 2FA & Notifications)

The platform uses PHP's built-in `mail()` function to send emails for features like two-factor authentication and user notifications. For this to work, your server must have a properly configured Mail Transfer Agent (MTA), such as `sendmail` or `Postfix`.

Alternatively, you can configure PHP to use an external SMTP server by editing your `php.ini` file. Example configuration:

```ini
[mail function]
; For Win32 only.
; http://php.net/smtp
SMTP = smtp.example.com
smtp_port = 587

; For Win32 only.
; http://php.net/sendmail-from
sendmail_from = no-reply@paws.money

; Add your SMTP username and password if required
username = your_smtp_username
password = your_smtp_password
```

**Ensure your server's email system is functional before enabling email-dependent features.**


### 1. Admin Account Setup
1. Register first user account (becomes admin)
2. Or manually set admin flag in database:
```sql
UPDATE users SET is_admin = 1 WHERE email = 'admin@yourdomain.com';
```

### 2. Cron Jobs (Optional)
Set up automated tasks:
```bash
# Add to crontab
crontab -e

# Update crypto prices every 5 minutes
*/5 * * * * /usr/bin/php /var/www/money-paws/scripts/update-crypto-prices.php

# Clean temporary files daily
0 2 * * * /usr/bin/php /var/www/money-paws/scripts/cleanup-temp.php

# Update pet stats hourly
0 * * * * /usr/bin/php /var/www/money-paws/scripts/update-pet-stats.php
```

### 3. Backup Configuration
```bash
# Database backup script
#!/bin/bash
mysqldump -u money_paws_user -p money_paws > /backups/money_paws_$(date +%Y%m%d_%H%M%S).sql

# File backup
tar -czf /backups/money_paws_files_$(date +%Y%m%d_%H%M%S).tar.gz /var/www/money-paws/uploads/
```

## 📱 Platform-Specific Installation

### 💻 Desktop Application Setup

#### Prerequisites
- Node.js 16.0 or higher
- npm or yarn package manager
- A running instance of the Money Paws web application (for the API).

#### 1. Configuration

Before building the desktop application, you must configure it to connect to your web server's API.

1.  **Open the main process file**: `gui/desktop/main.js`
2.  **Locate the `API_BASE_URL` constant** (around line 22):
    ```javascript
    const API_BASE_URL = isDev ? 'http://localhost' : 'https://your-domain.com';
    ```
3.  **Update the production URL**: Change `'https://your-domain.com'` to the actual domain where your Money Paws web application is hosted. For example:
    ```javascript
    const API_BASE_URL = isDev ? 'http://localhost' : 'https://paws.money';
    ```
    The `isDev` flag is automatically handled when you run the development server (`npm run dev`), so you only need to set the production URL.

#### 2. Installation & Running

```bash
# Navigate to the desktop app directory
cd gui/desktop

# Install Node.js dependencies
npm install

# Start the app in development mode
# This connects to your local web server (e.g., http://localhost)
npm run dev
```

The `npm run dev` command launches the application with developer tools enabled, making it easy to debug.

#### 3. Building for Production

To create distributable installers, use the following commands. The output files will be saved in the `gui/desktop/dist/` directory.

```bash
# Build for all platforms defined in package.json
npm run build

# Or, build for a specific platform:
npm run build-mac    # macOS (.dmg)
npm run build-win    # Windows (.exe installer)
npm run build-linux  # Linux (.AppImage)
```

### 🦶 CLI Client Setup (Accessibility)

#### Prerequisites
- PHP 8.0+ with CLI SAPI
- SQLite extension (for offline mode)
- Screen reader software (optional)

#### Installation
```bash
# Navigate to CLI directory
cd cli

# Make CLI executable
chmod +x paws-cli.php
chmod +x install.sh

# Run CLI installer
./install.sh

# Start the CLI client
php paws-cli.php
```

#### Accessibility Configuration
1. Run the CLI client
2. Choose "5. Accessibility Settings"
3. Configure preferences:
   - Enable large print mode
   - Enable high contrast
   - Configure sound preferences
   - Test screen reader compatibility

#### CLI Features
- **Authentication**: Email/password and OAuth2 support
- **Gaming**: Full crypto gaming with audio feedback
- **Balances**: Real-time portfolio management
- **Accessibility**: Screen reader and large print support
- **Offline Mode**: SQLite-based local storage

### 🌐 Web App Additional Setup

#### Progressive Web App (PWA)
The web application can be installed as a PWA on mobile devices:

1. Visit the website on a mobile device
2. Browser will prompt to "Add to Home Screen"
3. Accept to install as a native-like app

#### Service Worker (Optional)
For offline capabilities and push notifications:

```javascript
// Register service worker in your main template
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/sw.js');
}
```

## 🚨 Troubleshooting

### Common Issues

**Database Connection Failed**
- Check MySQL service is running
- Verify credentials in config/database.php
- Ensure database exists and user has permissions

**File Upload Errors**
- Check directory permissions (755 for directories, 644 for files)
- Verify PHP upload_max_filesize and post_max_size settings
- Ensure uploads directory is writable

**OAuth2 Login Issues**
- Verify SSL certificate is valid
- Check redirect URIs match exactly
- Ensure API keys are correct and active

**Crypto Features Not Working**
- Verify Coinbase Commerce API key is valid
- Check webhook URL is accessible
- Ensure SSL is properly configured

**Performance Issues**
- Enable PHP OPcache
- Configure MySQL query cache
- Use CDN for static assets
- Enable gzip compression

### Log Files
Check these locations for error information:
- `/var/log/apache2/error.log` (Apache)
- `/var/log/nginx/error.log` (Nginx)
- `/var/log/mysql/error.log` (MySQL)
- PHP error logs (location varies by configuration)

### Support
For additional help:
- Email: support@paws.money
- GitHub Issues: https://github.com/yourusername/money-paws/issues
- Documentation: https://docs.paws.money

## 🔄 Updates and Maintenance

### Updating Money Paws
```bash
# Backup current installation
cp -r /var/www/money-paws /var/www/money-paws-backup

# Pull latest changes
cd /var/www/money-paws
git pull origin main

# Update dependencies
composer update

# Run database migrations (if any)
php scripts/migrate.php

# Clear any caches
php scripts/clear-cache.php
```

### Security Updates
- Regularly update PHP, MySQL, and web server
- Monitor security advisories for dependencies
- Keep API keys secure and rotate periodically
- Review access logs for suspicious activity

---

**Installation complete! Welcome to Money Paws! 🐾**
