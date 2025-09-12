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

### ♿ Comprehensive Web Accessibility
- **WCAG 2.1 AA Compliance**: Professional accessibility standards for all users
- **Screen Reader Optimized**: Complete functionality via NVDA, JAWS, ORCA, and VoiceOver
- **Descriptive Alt Text**: Meaningful descriptions for all pet images and visual content
- **ARIA Labels**: Comprehensive labeling for forms, modals, and interactive elements
- **Semantic HTML**: Proper document structure for logical screen reader navigation
- **Keyboard Navigation**: Full platform access without mouse interaction
- **Focus Management**: Proper focus flow and visual indicators
- **Progress Announcements**: Screen reader accessible progress bars and status updates
- **Form Accessibility**: Clear labeling, error messages, and input validation feedback
- **Modal Accessibility**: Proper focus trapping and restoration for dialog boxes
- **Gaming for Blind Users**: Complete crypto gaming access via screen reader
- **Pet Care Interface**: Fully accessible virtual pet feeding and care system
- **Social Features**: Screen reader friendly messaging, friends, and community features

## 🎆 Technology Stack

### Backend
- **PHP 8.0+** with extensions:
  - PDO & PDO_MySQL for database operations
  - cURL for API communications
  - JSON for data exchange
  - mbstring for string handling
  - OpenSSL for security
  - fileinfo for file validation
- **PostgreSQL 13+** with Multi-AZ deployment for production
- **MySQL 8.0+** with optimized schema and indexing
- **SQLite Support**: Alternative database for development and testing

### Infrastructure & DevOps
- **AWS Cloud Infrastructure**: Production-ready multi-environment setup
  - **Amazon ECS**: Container orchestration with Fargate
  - **Amazon RDS**: Managed PostgreSQL with automated backups
  - **Amazon ElastiCache**: Redis caching layer
  - **Application Load Balancer**: SSL termination and health checks
  - **Amazon S3 + CloudFront**: Static asset storage and CDN
  - **Amazon VPC**: Secure networking with private/public subnets
- **Infrastructure as Code**: Terraform modules for all environments
- **Configuration Management**: Ansible playbooks for deployment automation
- **CI/CD Pipeline**: GitHub Actions with multi-environment deployment
- **Containerization**: Docker with multi-stage builds and health checks
- **Version Management**: Advanced version tracking and rollback capabilities

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
- **AWS Services**: SSM Parameter Store, CloudWatch, ECR

## 🔒 Security Update Notice

**SECURITY UPDATE v3.1.2**: Major security enhancements have been implemented to protect against XSS, CSRF, and input validation attacks. This update includes:

- Fixed critical CSRF token validation bug
- Secured 15+ unvalidated GET parameters across all pages
- Enhanced input validation and sanitization
- Strengthened API endpoint security
- Improved UI accessibility with better text contrast

**This is a mandatory security update for all installations. Please update immediately.**

## 📋 Prerequisites

- **PHP 8.0+** (recommended) or PHP 7.4+
- **MySQL 8.0+** or MariaDB 10.4+
- **Composer** for dependency management
- **Web Server** (Apache 2.4+ or Nginx 1.18+)
- **SSL Certificate** (required for OAuth2 and crypto payments)
- **Minimum 2GB RAM** and **10GB storage**

## ⚡ Installation System

### Quick Installation

```bash
# Clone the repository
git clone https://github.com/yourusername/money-paws.git
cd money-paws

# Install dependencies
composer install

# Run automated installer (recommended)
chmod +x install.sh
./install.sh
```

### Installation Methods

#### 1. CLI Installation (Recommended)
```bash
# Interactive installation
./install.sh --interactive

# Non-interactive with environment file
./install.sh --env-file=/path/to/.env

# Production installation
./install.sh --env=production --secure

# Development setup
./install.sh --env=development --with-sample-data
```

#### 2. Web-Based Installation
Navigate to `http://yourdomain.com/install.php` and follow the setup wizard:

1. Environment Selection
2. Dependency Validation
3. Database Configuration
4. Security Setup
5. Feature Configuration
6. Final Verification

### Installation Features

#### Environment Support
- **Development**: Quick setup with sample data
- **Staging**: Production-like environment for testing
- **Production**: Secure setup with enhanced validation

#### Automated Processes
- Dependency validation and installation
- Database schema setup and migration
- Security key generation
- SSL/TLS configuration
- File permission management
- Environment validation

#### Security Features
- Secure configuration generation
- Automated security header setup
- SSL/TLS configuration assistance
- Database credential management
- API key validation

#### Validation & Checks
- System requirement verification
- Database connection testing
- Security configuration validation
- Post-installation health checks
- Service availability testing

### Installation Requirements

#### Minimum Requirements
- PHP 8.0+ with required extensions
- MySQL 8.0+ or MariaDB 10.4+
- 2GB RAM
- 10GB storage

#### Recommended Requirements
- PHP 8.1+
- MySQL 8.0+ with InnoDB
- 4GB RAM
- 20GB SSD storage
- SSL certificate

### Post-Installation

#### Verification Steps
1. Run health check: `./install.sh --verify`
2. Test database connections
3. Verify API integrations
4. Check security configurations
5. Validate file permissions

#### Security Checklist
- [ ] SSL/TLS configuration
- [ ] API key validation
- [ ] Database credential security
- [ ] File permission verification
- [ ] Security header configuration

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

## 🚀 Deployment & Infrastructure

### Production-Ready AWS Infrastructure

Money Paws includes enterprise-grade infrastructure automation for seamless deployment to AWS.

#### Available Environments
- **Development**: Auto-deployment for rapid iteration
- **Staging**: Production-like environment for testing
- **Production**: High-availability with blue-green deployments

#### Infrastructure Components
```bash
infrastructure/
├── terraform/                   # Infrastructure as Code
│   ├── main.tf                 # Main Terraform configuration
│   ├── variables.tf            # Input variables
│   ├── outputs.tf              # Output values
│   ├── modules/                # Terraform modules
│   │   ├── vpc/                # Virtual Private Cloud
│   │   ├── ecs/                # Container orchestration
│   │   ├── rds/                # Managed database
│   │   ├── redis/              # Caching layer
│   │   ├── alb/                # Load balancer
│   │   ├── s3/                 # Static storage
│   │   ├── cloudfront/         # CDN
│   │   └── monitoring/         # CloudWatch setup
│   └── environments/           # Environment-specific configs
│       ├── dev.tfvars
│       ├── staging.tfvars
│       └── production.tfvars
│
├── ansible/                    # Configuration Management
│   ├── deploy.yml              # Main deployment playbook
│   ├── rollback.yml            # Rollback procedures
│   ├── tasks/                  # Ansible tasks
│   └── templates/              # Configuration templates
│
└── scripts/                    # Deployment automation
    ├── deploy.sh               # Master deployment script
    ├── rollback.sh             # Rollback management
    ├── version.sh              # Version management
    └── README.md               # Deployment documentation
```

#### Deployment Workflow

```bash
# 1. Create a new version
./infrastructure/scripts/version.sh create 3.2.1 -m "New feature release"

# 2. Deploy to development
./infrastructure/scripts/deploy.sh -e dev -v 3.2.1

# 3. Deploy to staging
./infrastructure/scripts/deploy.sh -e staging -v 3.2.1

# 4. Deploy to production
./infrastructure/scripts/deploy.sh -e production -v 3.2.1

# 5. Rollback if needed
./infrastructure/scripts/rollback.sh -e production
```

#### CI/CD Pipeline

Automated deployment via GitHub Actions:

```yaml
# .github/workflows/deploy.yml
name: Deploy Money Paws
on:
  push:
    branches: [ main, staging, development ]
  workflow_dispatch:
    inputs:
      environment:
        description: 'Deployment environment'
        required: true
        type: choice
        options:
        - development
        - staging
        - production
```

#### Infrastructure Features
- **Auto-Scaling**: ECS services scale based on CPU/memory usage
- **High Availability**: Multi-AZ deployment with automatic failover
- **Security**: Private subnets, security groups, and encrypted storage
- **Monitoring**: CloudWatch dashboards and automated alerting
- **Backup**: Automated database backups and point-in-time recovery
- **CDN**: CloudFront distribution for global content delivery
- **SSL/TLS**: Automatic SSL certificate management

#### Environment Specifications

**Development Environment**
- **Purpose**: Feature development and testing
- **Resources**: t3.medium instances, db.t3.micro RDS
- **Auto-deploy**: Enabled on push to development branch
- **Cost**: ~$50/month

**Staging Environment**
- **Purpose**: Pre-production testing and QA
- **Resources**: t3.large instances, db.t3.small RDS
- **Auto-deploy**: Manual approval required
- **Cost**: ~$150/month

**Production Environment**
- **Purpose**: Live production system
- **Resources**: c5.xlarge instances, db.r5.large RDS Multi-AZ
- **Auto-deploy**: Disabled, manual deployment only
- **Blue-Green**: Zero-downtime deployments
- **Cost**: ~$500/month (scales with usage)

#### Monitoring & Observability
- **Application Metrics**: Response time, error rate, throughput
- **Infrastructure Metrics**: CPU, memory, disk, network usage
- **Business Metrics**: User registrations, crypto transactions, game plays
- **Alerting**: Slack notifications for critical issues
- **Log Aggregation**: Centralized logging with search capabilities

#### Security & Compliance
- **Secrets Management**: AWS SSM Parameter Store
- **Access Control**: IAM roles with least-privilege access
- **Network Security**: Private subnets and security groups
- **Data Encryption**: Encryption at rest and in transit
- **Compliance Ready**: SOC2, PCI DSS preparation

For detailed deployment instructions, see **[infrastructure/scripts/README.md](infrastructure/scripts/README.md)**

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
