#!/bin/bash
# Money Paws CLI Installation Script
# Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>
# Accessible gaming client installer

set -e

echo "🐾 Money Paws CLI - Accessibility Client Installer"
echo "=================================================="
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Check if PHP is installed
if ! command -v php &> /dev/null; then
    echo -e "${RED}Error: PHP is not installed or not in PATH${NC}"
    echo "Please install PHP 7.4 or higher to use Money Paws CLI"
    exit 1
fi

# Check PHP version
PHP_VERSION=$(php -r "echo PHP_VERSION;")
echo -e "${BLUE}Found PHP version: $PHP_VERSION${NC}"

# Make CLI executable
chmod +x paws-cli.php
echo -e "${GREEN}✓ Made paws-cli.php executable${NC}"

# Create desktop shortcut (Linux/macOS)
if [[ "$OSTYPE" == "linux-gnu"* ]]; then
    # Linux desktop entry
    DESKTOP_FILE="$HOME/Desktop/MoneyPaws-CLI.desktop"
    cat > "$DESKTOP_FILE" << EOF
[Desktop Entry]
Version=1.0
Type=Application
Name=Money Paws CLI
Comment=Accessible cryptocurrency gaming client
Exec=php $(pwd)/paws-cli.php
Icon=applications-games
Terminal=true
Categories=Game;Accessibility;
EOF
    chmod +x "$DESKTOP_FILE"
    echo -e "${GREEN}✓ Created desktop shortcut: $DESKTOP_FILE${NC}"

elif [[ "$OSTYPE" == "darwin"* ]]; then
    # macOS alias
    ALIAS_PATH="$HOME/Desktop/MoneyPaws-CLI"
    echo "#!/bin/bash" > "$ALIAS_PATH"
    echo "cd $(pwd)" >> "$ALIAS_PATH"
    echo "php paws-cli.php" >> "$ALIAS_PATH"
    chmod +x "$ALIAS_PATH"
    echo -e "${GREEN}✓ Created desktop shortcut: $ALIAS_PATH${NC}"
fi

# Create system-wide command (optional)
echo ""
echo -e "${YELLOW}Optional: Create system-wide 'paws' command?${NC}"
read -p "This will allow you to run 'paws' from anywhere (y/n): " -n 1 -r
echo ""

if [[ $REPLY =~ ^[Yy]$ ]]; then
    # Create wrapper script
    WRAPPER_SCRIPT="/usr/local/bin/paws"
    sudo tee "$WRAPPER_SCRIPT" > /dev/null << EOF
#!/bin/bash
# Money Paws CLI System Wrapper
cd $(pwd)
php paws-cli.php "\$@"
EOF
    sudo chmod +x "$WRAPPER_SCRIPT"
    echo -e "${GREEN}✓ Created system command: 'paws'${NC}"
    echo -e "${BLUE}You can now run 'paws' from anywhere in the terminal${NC}"
fi

echo ""
echo -e "${GREEN}🎉 Installation Complete!${NC}"
echo ""
echo "USAGE:"
echo "  Direct:     php $(pwd)/paws-cli.php"
if [[ -f "/usr/local/bin/paws" ]]; then
    echo "  System:     paws"
fi
echo ""
echo "ACCESSIBILITY FEATURES:"
echo "  ✓ Full screen reader support (NVDA, JAWS, ORCA)"
echo "  ✓ Large print mode for low vision users"
echo "  ✓ High contrast display options"
echo "  ✓ Audio feedback and sound effects"
echo "  ✓ 100% keyboard navigation"
echo ""
echo "FIRST RUN:"
echo "  1. Start the CLI client"
echo "  2. Go to 'Accessibility Settings' (option 5)"
echo "  3. Configure your preferences"
echo "  4. Test screen reader compatibility"
echo ""
echo -e "${BLUE}This client is FREE for accessibility users!${NC}"
echo -e "${BLUE}Contact: coleman.ryan@gmail.com${NC}"
echo ""
echo "Starting Money Paws CLI in 3 seconds..."
sleep 3
php paws-cli.php
