# Money Paws Desktop Application

**Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>**

A modern Electron-based desktop application for the Money Paws cryptocurrency-powered pet platform.

## Features

- **Cross-Platform**: Runs on macOS, Windows, and Linux
- **Secure Authentication**: Local accounts and OAuth (Google, Facebook, Apple, Twitter)
- **Cryptocurrency Gaming**: Interactive coin flip with real-time balance updates
- **Portfolio Management**: Live balance tracking and visualization
- **Modern UI**: Dark/light themes with accessibility features
- **Keyboard Navigation**: Full keyboard support with shortcuts
- **Auto-Updates**: Built-in update mechanism
- **Data Export**: Export user data and game history

## Installation

### Prerequisites

- Node.js 16.0 or higher
- npm or yarn package manager

### Development Setup

1. Navigate to the desktop GUI directory:
```bash
cd gui/desktop
```

2. Install dependencies:
```bash
npm install
```

3. Start in development mode:
```bash
npm run dev
```

### Building for Production

Build for current platform:
```bash
npm run build
```

Build for specific platforms:
```bash
npm run build-mac    # macOS
npm run build-win    # Windows
npm run build-linux  # Linux
```

## Project Structure

```
gui/desktop/
├── main.js              # Main Electron process
├── preload.js           # Preload script for security
├── package.json         # Dependencies and build config
├── renderer/            # Frontend application
│   ├── index.html       # Main application window
│   ├── splash.html      # Splash screen
│   ├── css/            # Stylesheets
│   │   ├── main.css     # Core styles
│   │   ├── components.css # Component styles
│   │   └── themes.css   # Theme and accessibility styles
│   └── js/             # JavaScript modules
│       ├── api-client.js # API communication
│       ├── auth.js      # Authentication management
│       ├── gaming.js    # Gaming functionality
│       └── app.js       # Main application logic
└── assets/             # Application icons and resources
```

## Features Overview

### Authentication
- Multi-provider OAuth support
- Secure token storage using Electron Store
- Session management with auto-refresh
- Demo mode for testing

### Gaming
- Interactive coin flip game
- Real-time balance validation
- Keyboard shortcuts (H/T for choice, Space to play, 1-9 for bet percentages)
- Audio feedback with win/lose sounds
- Screen reader announcements

### Portfolio Management
- Live cryptocurrency balance tracking
- Interactive pie chart visualization
- Price updates every 30 seconds
- Multi-currency support (BTC, ETH, USDC, SOL, XRP)

### User Interface
- Modern, responsive design
- Dark/light theme switching
- High contrast mode support
- Reduced motion preferences
- Platform-specific styling (macOS, Windows, Linux)

### Accessibility
- Full keyboard navigation
- Screen reader support
- ARIA labels and announcements
- High contrast themes
- Reduced motion options
- Large text support

## Keyboard Shortcuts

### Global
- `Ctrl/Cmd + 1-5`: Navigate between views
- `Ctrl/Cmd + R` or `F5`: Refresh data
- `Ctrl/Cmd + L`: Logout
- `Ctrl/Cmd + ,`: Open settings (macOS menu)

### Gaming
- `H`: Select heads
- `T`: Select tails
- `Space`: Play game
- `1-9`: Set bet percentage (10%-90%)

## Configuration

### API Endpoint
The application connects to the Money Paws API. Update the `API_BASE_URL` in `main.js` for production deployment.

### Demo Mode
By default, the application runs in demo mode with mock data. Disable demo mode in `api-client.js` to connect to real API endpoints.

### Settings Storage
Application settings are stored using Electron Store:
- Authentication tokens
- User preferences (theme, sound, animations)
- Window bounds and state

## Security Features

- Context isolation enabled
- Node integration disabled in renderer
- Preload script for secure IPC communication
- External link protection
- Navigation restrictions
- Certificate error handling

## Development

### Adding New Features

1. **API Methods**: Add new API calls in `api-client.js`
2. **UI Components**: Add styles in `components.css`
3. **Views**: Add new views in `index.html` and handle in `app.js`
4. **Themes**: Add theme variants in `themes.css`

### Testing

Run the application in development mode:
```bash
npm run dev
```

Enable developer tools for debugging:
- The application automatically opens DevTools in development mode
- Use `Ctrl/Cmd + Shift + I` to toggle DevTools in production builds

### Building

The application uses electron-builder for packaging:
- Automatic code signing (configure in package.json)
- DMG creation for macOS
- NSIS installer for Windows
- AppImage for Linux

## Deployment

### Auto-Updates
The application includes auto-update functionality using electron-updater. Configure your update server in the build configuration.

### Distribution
Built applications are output to the `dist/` directory and ready for distribution through:
- Direct download
- App stores (Mac App Store, Microsoft Store)
- Package managers (Homebrew, Chocolatey, Snap)

## Troubleshooting

### Common Issues

1. **White screen on startup**: Check console for JavaScript errors
2. **API connection failed**: Verify API endpoint configuration
3. **Authentication issues**: Clear stored data in settings
4. **Performance issues**: Disable animations in accessibility settings

### Logs
Application logs are available in:
- macOS: `~/Library/Logs/Money Paws/`
- Windows: `%USERPROFILE%\AppData\Roaming\Money Paws\logs\`
- Linux: `~/.config/Money Paws/logs/`

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a pull request

## License

MIT License - see the main project LICENSE file for details.

## Support

For support, please contact:
- Email: coleman.ryan@gmail.com
- GitHub Issues: [money-paws/issues](https://github.com/ryancoleman/money-paws/issues)

---

**Money Paws Desktop** - Bringing cryptocurrency gaming to your desktop with accessibility and security in mind.
