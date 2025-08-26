# Money Paws 🐾
*Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>*

**AI Pet Gallery with Crypto Gaming Platform**

Money Paws is a revolutionary cryptocurrency-powered pet platform where users can upload, share, and interact with AI-generated and real pet images while earning and spending digital currency. Features a complete pet care system, crypto-powered gaming, and social interactions.

## 🌟 Key Features

### 🐕 Pet Management & Social
- **Pet Gallery**: Browse and interact with pet images from the community
- **Pet Upload**: Upload your own pet photos with descriptions and privacy controls
- **AI Pet Generation**: Create unique AI-generated pet images using OpenAI and Stability AI
- **Pet Care System**: Feed, treat, and care for pets with hunger/happiness mechanics
- **Privacy Controls**: Toggle pet visibility on public profiles
- **Social Features**: Like, view, and interact with community pets
- **User-to-User Messaging**: Secure, private conversations between users
- **Real-time Notifications**: Instant alerts for new messages and interactions
- **Abandoned Pet Adoption**: Find and adopt pets that haven't been cared for in 30+ days
- **Vacation Mode**: Delegate pet care to trusted users while away
- **Pet Breeding & Genetics**: Advanced breeding system with DNA inheritance and genetic mutations
- **Mating Request System**: Send and respond to breeding requests between compatible pets
- **Pet Memorials & Donations**: Honor deceased pets with memorial pages and community donations
- **Leaderboards**: Community rankings for top pets, owners, and most active users

### 💰 Cryptocurrency Integration
- **Multi-Crypto Support**: BTC, ETH, USDC, SOL, XRP with real-time pricing
- **Coinbase Commerce**: Secure crypto payments and deposits
- **Pet Store**: Buy food, treats, and accessories with cryptocurrency
- **Gaming Rewards**: Earn crypto through games and activities
- **Balance Management**: Track and manage multiple cryptocurrency balances

### 🎮 Gaming & Entertainment
- **Paw Match Game**: Crypto-powered matching game with rewards
- **Recent Winners**: Live feed of game winners and payouts
- **Leaderboards**: Competitive gaming with crypto prizes
- **Future Games**: Expandable gaming system for new game types

### 🔐 Authentication & Security
- **Multi-Provider OAuth2**: Google, Facebook, Apple, Twitter/X login
- **Secure Sessions**: Advanced session management and CSRF protection
- **Password Security**: bcrypt hashing with secure password policies
- **Two-Factor Authentication (2FA)**: Email and Google Authenticator for secure account protection and withdrawals
- **Withdrawal Verification**: Mandatory 2FA for all crypto withdrawals
- **Security Logging**: Comprehensive security event tracking and audit trails
- **SQL Injection Protection**: Prepared statements throughout
- **File Upload Security**: Validated uploads with secure storage

### 🛒 Pet Care & Store System
- **Virtual Pet Stats**: Hunger and happiness levels that change over time
- **Interactive Feeding**: Use food items to restore pet hunger
- **Treat System**: Give treats to boost pet happiness
- **Store Inventory**: Purchase and manage food, treats, toys, and accessories
- **Cross-Pet Interactions**: Care for other users' pets to build community
- **Vacation Delegation**: Assign pet care to trusted users with reserved funds
- **Abandoned Pet Rescue**: Community adoption system for neglected pets

## 📱 Multi-Platform Support

### 🌐 Web Application (Primary)
- **Responsive Design**: Works on all devices and screen sizes
- **Progressive Web App**: Installable on mobile devices
- **Cross-Browser Compatible**: Chrome, Firefox, Safari, Edge

### 💻 Desktop Application
- **Electron-Based**: Native desktop app for Windows, macOS, and Linux.
- **Secure Local Storage**: Securely stores session and user data.
- **Native Notifications**: System-level alerts for in-game events and messages.
- **Local Image Saving**: Save pet images directly to your computer.
- **Application Menu**: Standard application menu with settings and shortcuts.
- **Dark/Light Themes**: User-selectable themes for accessibility.
- **Full Keyboard Navigation**: Complete control via keyboard shortcuts.

### 🦶 CLI Client (Accessibility-First)
- **Screen Reader Support**: Full compatibility with NVDA, JAWS, ORCA
- **Large Print Mode**: High contrast and large text options
- **Audio Feedback**: Sound effects for game events
- **100% Keyboard Navigation**: No mouse required
- **Free for Accessibility Users**: Complete access at no cost

## 🎆 Technology Stack

### Backend
- **PHP 8.0+** with extensions:
  - PDO & PDO_MySQL for database operations
  - cURL for API communications
  - JSON for data exchange
  - mbstring for string handling
  - OpenSSL for security
  - fileinfo for file validation
- **MySQL 8.0+** with optimized schema and indexing
- **SQLite Support**: Alternative database for development and testing

### Frontend
- **Modern HTML5/CSS3** with responsive design
- **Vanilla JavaScript** with ES6+ features
- **CSS Grid & Flexbox** for advanced layouts
- **Progressive Enhancement** for accessibility
- **Electron Framework**: Cross-platform desktop application

### External Integrations
- **Coinbase Commerce API** for cryptocurrency payments
- **OpenAI GPT-4 Vision** for AI pet generation
- **Stability AI SDXL** for advanced image generation
- **OAuth2 APIs** for social authentication
- **Real-time Crypto Pricing** via multiple exchanges

## 🚨 Critical Bug Fix Notice

**IMPORTANT**: If you experienced site loading issues after the recent documentation updates, this has been resolved. Missing CSRF token functions (`getCSRFTokenField()`, `getPetById()`, `getDonationsForPet()`) have been added to `includes/functions.php`. Please pull the latest changes or manually add these functions if your site is not loading.

## 📋 Prerequisites

- **PHP 8.0+** (recommended) or PHP 7.4+
- **MySQL 8.0+** or MariaDB 10.4+
- **Composer** for dependency management
- **Web Server** (Apache 2.4+ or Nginx 1.18+)
- **SSL Certificate** (required for OAuth2 and crypto payments)
- **Minimum 2GB RAM** and **10GB storage**

## ⚡ Quick Installation

```bash
# Clone the repository
git clone https://github.com/yourusername/money-paws.git
cd money-paws

# Install dependencies
composer install

# Run automated installer (recommended)
chmod +x install.sh
./install.sh

# Or use web-based installer
# Navigate to http://yourdomain.com/install.php
```

For detailed installation instructions, see **[INSTALL.md](INSTALL.md)**

## 🔧 Configuration

### Environment Setup
Copy and configure your environment settings:

```php
// config/database.php - Main configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'money_paws');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');

// Coinbase Commerce (Required)
define('COINBASE_API_KEY', 'your_api_key');
define('COINBASE_WEBHOOK_SECRET', 'your_webhook_secret');

// OAuth2 Providers (Optional but recommended)
define('GOOGLE_CLIENT_ID', 'your_google_client_id');
define('GOOGLE_CLIENT_SECRET', 'your_google_client_secret');
// ... other OAuth providers

// AI Services (Optional - for AI pet generation)
define('OPENAI_API_KEY', 'your_openai_key');
define('STABILITY_API_KEY', 'your_stability_key');
```

### Required API Keys & Setup
1. **Coinbase Commerce** - [Get API Key](https://commerce.coinbase.com/dashboard/api-keys)
2. **Google OAuth2** - [Google Cloud Console](https://console.cloud.google.com/)
3. **Facebook Login** - [Facebook Developers](https://developers.facebook.com/)
4. **Apple Sign-In** - [Apple Developer](https://developer.apple.com/)
5. **Twitter API** - [Twitter Developer Portal](https://developer.twitter.com/)
6. **OpenAI API** - [OpenAI Platform](https://platform.openai.com/)
7. **Stability AI** - [Stability AI Platform](https://platform.stability.ai/)

## 🏢 Project Structure

```
money-paws/
├── Core Pages
│   ├── index.php                    # Homepage and dashboard
│   ├── gallery.php                  # Community pet gallery
│   ├── profile.php                  # User profiles and pet management
│   ├── upload.php                   # Pet photo upload interface
│   ├── store.php                    # Cryptocurrency pet store
│   ├── game.php                     # Crypto gaming interface
│   ├── messages.php                 # User messaging inbox
│   ├── conversation.php             # Individual conversations
│   ├── notifications.php            # User notifications center
│   ├── security.php                 # 2FA and security settings
│   ├── vacation_mode.php            # Pet care delegation
│   ├── abandoned_pets.php           # Pet adoption center
│   ├── leaderboards.php             # Community rankings
│   ├── breeding.php                 # Pet breeding interface
│   └── ai-generator.php             # AI pet creation
│
├── Authentication
│   ├── login.php                    # User login
│   ├── register.php                 # User registration
│   ├── logout.php                   # Session termination
│   └── oauth/                       # OAuth2 providers
│       ├── google.php               # Google Sign-In
│       ├── facebook.php             # Facebook Login
│       ├── apple.php                # Apple Sign-In
│       └── twitter.php              # Twitter/X OAuth
│
├── Financial
│   ├── deposit.php                  # Crypto deposits
│   ├── withdrawal.php               # Crypto withdrawals with 2FA
│   ├── adoption.php                 # Pet adoption payments
│   └── sell_pet.php                 # Pet marketplace
│
├── API Endpoints                    # RESTful API
│   ├── get-balances.php             # User crypto balances
│   ├── get-crypto-price.php         # Real-time crypto prices
│   ├── toggle-like.php              # Pet like system
│   ├── feed-pet.php                 # Pet feeding API
│   ├── treat-pet.php                # Pet treat system
│   ├── get-notifications.php        # User notifications
│   ├── get-unread-notification-count.php  # Notification count
│   ├── breed-pets.php               # Pet breeding endpoint
│   └── purchase-item.php            # Store purchases
│
├── Administration
│   └── admin/                       # Admin panel
│       ├── index.php                # Admin dashboard
│       ├── users.php                # User management
│       ├── pets.php                 # Pet moderation
│       └── toggle_admin.php         # Admin permissions
│
├── Backend Systems
│   ├── includes/                    # Core PHP libraries
│   │   ├── functions.php            # Main functions library
│   │   ├── security.php             # Security utilities
│   │   ├── pet_care.php             # Pet care mechanics
│   │   ├── coinbase_commerce.php    # Crypto payment processing
│   │   ├── crypto.php               # Cryptocurrency utilities
│   │   ├── genetics.php             # Pet breeding and genetics engine
│   │   ├── ai_generation.php        # AI integration
│   │   └── header.php, footer.php   # UI components
│   │
│   ├── config/                      # Configuration
│   │   └── database.php             # Database and API settings
│   │
│   └── database/                    # Database management
│       ├── schema.sql               # MySQL schema
│       └── schema.sqlite.sql        # SQLite schema
│
├── Cross-Platform Clients
│   ├── cli/                         # Accessibility CLI client
│   │   ├── paws-cli.php             # Main CLI application
│   │   ├── setup-sqlite.php         # SQLite setup utility
│   │   ├── install.sh               # CLI installer
│   │   └── README.md                # CLI documentation
│   └── gui/                         # Desktop & Web GUI
│       ├── desktop/                 # Electron desktop app
│       │   ├── main.js              # Main Electron process
│       │   ├── preload.js           # Electron context bridge
│       │   ├── package.json         # Node.js dependencies
│       │   └── renderer/            # Frontend code (HTML, CSS, JS)
│       └── web/                     # Shared web components
│
├── Static Assets
│   ├── assets/                      # CSS, JS, images
│   └── uploads/                     # User-generated content
│
├── Installation & Setup
│   ├── install.php                  # Web-based installer
│   ├── install.sh                   # Shell installer script
│   └── process_ai_generation.php    # AI setup processor
│
└── Documentation
    ├── README.md                    # This file
    ├── INSTALL.md                   # Installation guide
    ├── CHANGELOG.md                 # Version history
    ├── ABOUT.md                     # Project philosophy
    ├── CREDITS.md                   # Contributors
    └── DOCUMENTATION.txt            # Technical docs
```

**Supported Animals**:
- 🐕 Dogs (all breeds)
- 🐱 Cats (all breeds)  
- 🐰 Rabbits
- 🦊 Foxes
- 🐻 Bears
- 🐾 Any animal with paws

## Development

### Running Tests
```bash
composer test
```

### Code Style
Follow PSR-12 coding standards for PHP.

### Contributing
1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Submit a pull request

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Support

For support, email info@paws.money or visit our community Discord.

## Roadmap

- [ ] Mobile app development
- [ ] NFT marketplace integration
- [x] Advanced AI pet breeding system
- [ ] Multiplayer gaming tournaments
- [ ] Social features and pet communities
- [ ] Integration with more crypto exchanges

---

**Money Paws** - Where AI meets cryptocurrency in the most adorable way possible! 🐾💰
