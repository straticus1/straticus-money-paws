#!/bin/bash
# Money Paws Installation Script
# Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>
# Run this script after updating config/database.php with your credentials

set -e  # Exit on any error

echo "🐾 Money Paws Installation Script"
echo "================================="

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

print_step() {
    echo -e "${BLUE}[STEP]${NC} $1"
}

# Check if running as root (not recommended for web directory)
if [[ $EUID -eq 0 ]]; then
   print_warning "Running as root. Consider running as web server user instead."
fi

# Check PHP version
print_step "Checking PHP version..."
PHP_VERSION=$(php -v | head -n 1 | cut -d " " -f 2 | cut -d "." -f 1,2)
REQUIRED_VERSION="7.4"

if php -v | head -n 1 | grep -q "PHP [7-9]"; then
    print_status "PHP version: $PHP_VERSION ✓"
else
    print_error "PHP 7.4 or higher required. Current: $PHP_VERSION"
    exit 1
fi

# Check required PHP extensions
print_step "Checking PHP extensions..."
REQUIRED_EXTENSIONS=("pdo" "pdo_mysql" "curl" "json" "mbstring" "openssl" "fileinfo")

for ext in "${REQUIRED_EXTENSIONS[@]}"; do
    if php -m | grep -q "^$ext$"; then
        print_status "PHP extension $ext: ✓"
    else
        print_error "Required PHP extension missing: $ext"
        exit 1
    fi
done

# Check if Composer is installed
print_step "Checking Composer..."
if command -v composer &> /dev/null; then
    print_status "Composer found: $(composer --version)"
else
    print_error "Composer not found. Please install Composer first."
    echo "Visit: https://getcomposer.org/download/"
    exit 1
fi

# Check if config file exists and has been modified
print_step "Checking configuration..."
CONFIG_FILE="config/database.php"

if [[ ! -f "$CONFIG_FILE" ]]; then
    print_error "Configuration file not found: $CONFIG_FILE"
    exit 1
fi

# Check if default values are still present
if grep -q "your_google_client_id\|your_coinbase_api_key\|localhost" "$CONFIG_FILE"; then
    print_warning "Default configuration values detected in $CONFIG_FILE"
    print_warning "Please update your API keys and database credentials before continuing."
    read -p "Continue anyway? (y/N): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        exit 1
    fi
fi

# Install Composer dependencies
print_step "Installing Composer dependencies..."
if composer install --no-dev --optimize-autoloader; then
    print_status "Composer dependencies installed ✓"
else
    print_error "Failed to install Composer dependencies"
    exit 1
fi

# Create required directories
print_step "Creating required directories..."
DIRECTORIES=("uploads/pets" "logs" "cache")

for dir in "${DIRECTORIES[@]}"; do
    if mkdir -p "$dir"; then
        print_status "Created directory: $dir ✓"
    else
        print_error "Failed to create directory: $dir"
        exit 1
    fi
done

# Set proper permissions
print_step "Setting file permissions..."
chmod 755 uploads/pets
chmod 755 logs
chmod 755 cache
chmod 644 config/database.php

print_status "File permissions set ✓"

# Test database connection
print_step "Testing database connection..."
php -r "
require_once 'config/database.php';
try {
    \$pdo = new PDO(\"mysql:host=\" . DB_HOST . \";dbname=\" . DB_NAME, DB_USER, DB_PASS);
    \$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo \"Database connection successful\n\";
} catch(PDOException \$e) {
    echo \"Database connection failed: \" . \$e->getMessage() . \"\n\";
    exit(1);
}
"

if [[ $? -eq 0 ]]; then
    print_status "Database connection test passed ✓"
else
    print_error "Database connection test failed"
    print_error "Please check your database credentials in config/database.php"
    exit 1
fi

# Import database schema
print_step "Setting up database schema..."
DB_HOST=$(php -r "require 'config/database.php'; echo DB_HOST;")
DB_NAME=$(php -r "require 'config/database.php'; echo DB_NAME;")
DB_USER=$(php -r "require 'config/database.php'; echo DB_USER;")
DB_PASS=$(php -r "require 'config/database.php'; echo DB_PASS;")

if mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" < database/schema.sql; then
    print_status "Database schema imported ✓"
else
    print_error "Failed to import database schema"
    print_error "Please ensure MySQL is running and credentials are correct"
    exit 1
fi

# Create .htaccess for security
print_step "Creating security configurations..."
cat > .htaccess << 'EOF'
# Money Paws Security Configuration
RewriteEngine On

# Protect config directory
<Files "config/*">
    Order Allow,Deny
    Deny from all
</Files>

# Protect includes directory
<Files "includes/*">
    Order Allow,Deny
    Deny from all
</Files>

# Protect database directory
<Files "database/*">
    Order Allow,Deny
    Deny from all
</Files>

# Hide .git directory
RedirectMatch 404 /\.git

# Security headers
<IfModule mod_headers.c>
    Header always set X-Content-Type-Options nosniff
    Header always set X-Frame-Options DENY
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
</IfModule>

# Prevent access to sensitive files
<FilesMatch "\.(htaccess|htpasswd|ini|log|sh|sql|conf)$">
    Order Allow,Deny
    Deny from all
</FilesMatch>
EOF

print_status "Security configuration created ✓"

# Create uploads .htaccess
cat > uploads/.htaccess << 'EOF'
# Prevent PHP execution in uploads directory
<Files *.php>
    Order Allow,Deny
    Deny from all
</Files>

# Only allow image files
<FilesMatch "\.(jpg|jpeg|png|gif|webp)$">
    Order Allow,Deny
    Allow from all
</FilesMatch>
EOF

print_status "Upload security configuration created ✓"

# Create basic error log
touch logs/error.log
chmod 644 logs/error.log

# Final checks
print_step "Running final checks..."

# Check web server write permissions
if [[ -w "uploads/pets" && -w "logs" ]]; then
    print_status "Write permissions verified ✓"
else
    print_warning "Write permissions may need adjustment for web server"
fi

# Display installation summary
echo
echo "🎉 Installation Complete!"
echo "========================"
echo
print_status "✅ PHP version and extensions verified"
print_status "✅ Composer dependencies installed"
print_status "✅ Required directories created"
print_status "✅ File permissions set"
print_status "✅ Database connection tested"
print_status "✅ Database schema imported"
print_status "✅ Security configurations applied"
echo

print_step "Next Steps:"
echo "1. Configure your web server to point to this directory"
echo "2. Update config/database.php with production API keys:"
echo "   - OAuth2 credentials (Google, Facebook, Apple, Twitter)"
echo "   - Coinbase Commerce API keys"
echo "   - AI service API keys (OpenAI, Stability AI)"
echo "3. Test the installation by visiting your domain"
echo "4. Create your first admin user account"
echo

print_step "Important Security Notes:"
echo "- Keep config/database.php secure and never commit API keys to version control"
echo "- Regularly update Composer dependencies"
echo "- Monitor logs/error.log for any issues"
echo "- Consider setting up SSL/HTTPS for production"
echo

print_status "Money Paws is ready to launch! 🚀🐾"

exit 0
