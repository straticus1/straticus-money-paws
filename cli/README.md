# Money Paws CLI - Accessible Gaming Client 🐾
*Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>*

**Accessible command-line interface for blind and low-vision users**

## 🌟 Accessibility Features

### **Screen Reader Support**
- Full compatibility with NVDA, JAWS, ORCA, and other screen readers
- Descriptive text for all interface elements
- Clear navigation announcements
- Structured menu system with logical flow

### **Low Vision Support**
- Large print mode with uppercase text
- High contrast display options
- Configurable text size and spacing
- Clear visual hierarchy

### **Audio Feedback**
- Sound effects for game events (win/lose/actions)
- Audio confirmation of important actions
- Configurable sound settings
- Non-intrusive notification sounds

### **Keyboard Navigation**
- 100% keyboard accessible
- No mouse required
- Standard navigation patterns
- Quick exit commands (quit/exit)

## 🚀 Quick Start

### Installation
```bash
# Navigate to Money Paws directory
cd /path/to/paws.money

# Make CLI executable
chmod +x cli/paws-cli.php

# Run the client
php cli/paws-cli.php
```

### First Time Setup
1. Run the CLI client
2. Choose "5. Accessibility Settings" 
3. Configure your preferences:
   - Enable large print mode if needed
   - Enable high contrast if needed
   - Configure sound preferences
4. Test screen reader compatibility

## 🎮 Features

### **Authentication**
- Email/password login
- OAuth2 support (Google, Facebook, Apple, Twitter)
- Secure password input (hidden typing)
- Session management

### **Gaming**
- Crypto coin flip game
- Real-time balance checking
- Multiple cryptocurrency support (BTC, ETH, USDC, SOL, XRP)
- Accessible game announcements
- Win/loss audio feedback

### **Account Management**
- View crypto balances
- Portfolio value in USD
- User profile information
- Transaction history

## 🔧 Configuration

The CLI creates two configuration files:
- `cli/config.json` - User preferences and settings
- `cli/session.json` - Login session data

### Accessibility Settings
```json
{
    "accessibility_mode": "screen_reader",
    "large_print": true,
    "high_contrast": true,
    "sound_enabled": true
}
```

## 🎯 Usage Examples

### Basic Navigation
```
MAIN MENU
----------
1. Login / Register
2. Play Crypto Game
3. View Profile  
4. View Crypto Balances
5. Accessibility Settings
6. Help & Instructions
0. Exit

Enter your choice: 2
```

### Playing Games
```
CRYPTO GAMING
-------------
Available balances:
  BTC: 0.00100000
  ETH: 0.05000000
  USDC: 100.00000000

Which crypto to play with (BTC/ETH/USDC/SOL/XRP): USDC
Bet amount in USDC: 5

STARTING GAME
Bet: 5 USDC

Game: Crypto Coin Flip
Choose heads or tails. Win double your bet!

Choose (heads/tails): heads
Flipping coin...
3...
2...
1...
Result: HEADS
🎉 CONGRATULATIONS! YOU WON!
You won 10 USDC!
```

## 🔐 Security Features

- Secure password input (hidden from display)
- Session token management
- Automatic logout on exit
- No sensitive data stored in plain text

## 🆘 Support & Help

### Built-in Help
Type `6` from the main menu to access comprehensive help documentation.

### Keyboard Shortcuts
- `quit` or `exit` - Exit at any time
- `0` - Go back/exit from submenus
- Numbers `1-9` - Select menu options
- `Enter` - Confirm selections

### Screen Reader Tips
- All menus are announced with clear headings
- Game results include detailed descriptions
- Balance information is formatted for easy reading
- Error messages are clearly distinguished

### Troubleshooting

**Screen Reader Not Detected:**
- Manually enable accessibility mode in settings
- Ensure your screen reader is running before starting CLI

**Sound Not Working:**
- Check sound settings in accessibility menu
- Verify system audio is enabled
- Some sounds are simple terminal beeps

**Login Issues:**
- Verify internet connection for OAuth
- Check API endpoint configuration
- Ensure web server is running

## 🌐 API Integration

The CLI connects to Money Paws web APIs:
- `/api/get-balances.php` - Crypto balances
- `/api/get-crypto-price.php` - Price conversion
- Game APIs for real-time gaming
- OAuth endpoints for authentication

## 📱 Future Development

This CLI client serves as the foundation for:
- iOS accessibility app
- Android accessibility app  
- Voice-controlled interface
- Braille display support

## 💝 Free for Accessibility Users

This CLI client is provided **completely free** for blind and low-vision users. No registration fees, no premium features - full access to all gaming functionality.

## 📧 Contact & Support

**Developer:** Ryan Coleman  
**Email:** coleman.ryan@gmail.com  
**Purpose:** Making crypto gaming accessible to everyone

---

*"Technology should be accessible to all. This CLI ensures that visual impairments never prevent anyone from enjoying Money Paws gaming."* - Ryan Coleman
