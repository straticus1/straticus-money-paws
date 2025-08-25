/**
 * Money Paws Desktop - Authentication Module
 * Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>
 */

class DesktopAuthManager {
    constructor() {
        this.apiClient = window.apiClient;
        this.currentUser = null;
        this.isLoggedIn = false;
        
        this.initializeAuth();
        this.bindEvents();
    }

    async initializeAuth() {
        // Check if user is already logged in
        if (this.apiClient.isAuthenticated()) {
            this.currentUser = this.apiClient.getCurrentUser();
            this.isLoggedIn = true;
            this.showAppScreen();
        } else {
            this.showLoginScreen();
        }
    }

    bindEvents() {
        // Tab switching
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                this.switchTab(e.target.dataset.tab);
            });
        });

        // Form submissions
        document.getElementById('login-form').addEventListener('submit', (e) => {
            e.preventDefault();
            this.handleLogin();
        });

        document.getElementById('register-form').addEventListener('submit', (e) => {
            e.preventDefault();
            this.handleRegister();
        });

        // OAuth buttons
        document.querySelectorAll('.btn-oauth').forEach(btn => {
            btn.addEventListener('click', (e) => {
                this.handleOAuthLogin(e.target.dataset.provider);
            });
        });

        // Logout button
        document.getElementById('logout-btn').addEventListener('click', () => {
            this.handleLogout();
        });

        // Listen for settings show event from main process
        window.electronAPI.onShowSettings(() => {
            if (window.app) {
                window.app.showView('settings');
            }
        });
    }

    switchTab(tab) {
        // Update tab buttons
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.tab === tab);
        });

        // Update forms
        document.querySelectorAll('.auth-form').forEach(form => {
            form.classList.toggle('active', form.id === `${tab}-form`);
        });
    }

    async handleLogin() {
        const email = document.getElementById('login-email').value;
        const password = document.getElementById('login-password').value;

        if (!email || !password) {
            this.showNotification('Please fill in all fields', 'error');
            return;
        }

        this.setLoading(true);

        try {
            const response = await this.apiClient.login(email, password);
            
            if (response.success) {
                this.currentUser = response.user;
                this.isLoggedIn = true;
                this.showNotification('Login successful!', 'success');
                this.showAppScreen();
            } else {
                this.showNotification(response.message || 'Login failed', 'error');
            }
        } catch (error) {
            this.showNotification(this.apiClient.handleError(error, 'login'), 'error');
        } finally {
            this.setLoading(false);
        }
    }

    async handleRegister() {
        const name = document.getElementById('register-name').value;
        const email = document.getElementById('register-email').value;
        const password = document.getElementById('register-password').value;
        const confirmPassword = document.getElementById('register-confirm').value;

        if (!name || !email || !password || !confirmPassword) {
            this.showNotification('Please fill in all fields', 'error');
            return;
        }

        if (password !== confirmPassword) {
            this.showNotification('Passwords do not match', 'error');
            return;
        }

        if (password.length < 6) {
            this.showNotification('Password must be at least 6 characters', 'error');
            return;
        }

        this.setLoading(true);

        try {
            const response = await this.apiClient.register(name, email, password);
            
            if (response.success) {
                this.showNotification('Account created successfully! Please log in.', 'success');
                this.switchTab('login');
                
                // Pre-fill login form
                document.getElementById('login-email').value = email;
            } else {
                this.showNotification(response.message || 'Registration failed', 'error');
            }
        } catch (error) {
            this.showNotification(this.apiClient.handleError(error, 'registration'), 'error');
        } finally {
            this.setLoading(false);
        }
    }

    async handleOAuthLogin(provider) {
        this.setLoading(true);
        
        try {
            // Show loading message
            this.showNotification(`Connecting to ${provider}...`, 'info');
            
            const response = await this.apiClient.loginWithOAuth(provider);
            
            if (response.success) {
                this.currentUser = response.user;
                this.isLoggedIn = true;
                this.showNotification(`${provider} login successful!`, 'success');
                this.showAppScreen();
            } else {
                this.showNotification(`${provider} login failed`, 'error');
            }
        } catch (error) {
            this.showNotification(this.apiClient.handleError(error, `${provider} login`), 'error');
        } finally {
            this.setLoading(false);
        }
    }

    async handleLogout() {
        try {
            // Show confirmation dialog
            const result = await window.electronAPI.showMessageBox({
                type: 'question',
                buttons: ['Logout', 'Cancel'],
                defaultId: 0,
                title: 'Confirm Logout',
                message: 'Are you sure you want to logout?',
                detail: 'You will need to login again to access your account.'
            });

            if (result.response === 0) {
                // Clear authentication
                await this.apiClient.clearAuth();
                this.currentUser = null;
                this.isLoggedIn = false;
                
                // Clear forms
                document.getElementById('login-form').reset();
                document.getElementById('register-form').reset();
                
                // Show login screen
                this.showLoginScreen();
                this.showNotification('Logged out successfully', 'success');
            }
        } catch (error) {
            console.error('Logout error:', error);
            this.showNotification('Error during logout', 'error');
        }
    }

    showLoginScreen() {
        document.getElementById('login-screen').classList.add('active');
        document.getElementById('app-screen').classList.remove('active');
        
        // Hide loading screen if visible
        const loadingScreen = document.getElementById('loading-screen');
        if (loadingScreen) {
            loadingScreen.classList.add('hidden');
        }

        // Update window title
        document.title = 'Money Paws Desktop - Login';
    }

    showAppScreen() {
        document.getElementById('login-screen').classList.remove('active');
        document.getElementById('app-screen').classList.add('active');
        
        // Update user info in the app
        this.updateUserInfo();
        
        // Initialize app components
        if (window.app) {
            window.app.initialize();
        }

        // Update window title
        document.title = 'Money Paws Desktop';
    }

    updateUserInfo() {
        if (this.currentUser) {
            // Update user name in navigation and profile
            const userNameElements = document.querySelectorAll('#user-name, #profile-name');
            userNameElements.forEach(el => {
                el.textContent = this.currentUser.name;
            });

            // Update email in profile
            const emailElement = document.getElementById('profile-email');
            if (emailElement) {
                emailElement.textContent = this.currentUser.email;
            }

            // Update provider badge
            const providerElement = document.getElementById('profile-provider');
            if (providerElement) {
                const providerNames = {
                    'local': 'Local Account',
                    'google': 'Google Account',
                    'facebook': 'Facebook Account',
                    'apple': 'Apple Account',
                    'twitter': 'Twitter Account'
                };
                providerElement.textContent = providerNames[this.currentUser.provider] || 'Unknown';
            }

            // Update avatar
            const avatarElements = document.querySelectorAll('#profile-avatar, #profile-avatar-large');
            avatarElements.forEach(el => {
                // Use first letter of name as avatar
                el.textContent = this.currentUser.name.charAt(0).toUpperCase();
            });
        }
    }

    setLoading(loading) {
        const loginBtn = document.querySelector('#login-form .btn-primary');
        const registerBtn = document.querySelector('#register-form .btn-primary');
        const oauthBtns = document.querySelectorAll('.btn-oauth');

        if (loading) {
            loginBtn.disabled = true;
            loginBtn.textContent = 'Logging in...';
            registerBtn.disabled = true;
            registerBtn.textContent = 'Creating account...';
            oauthBtns.forEach(btn => {
                btn.disabled = true;
                btn.style.opacity = '0.6';
            });
        } else {
            loginBtn.disabled = false;
            loginBtn.textContent = 'Login';
            registerBtn.disabled = false;
            registerBtn.textContent = 'Register';
            oauthBtns.forEach(btn => {
                btn.disabled = false;
                btn.style.opacity = '1';
            });
        }
    }

    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        notification.textContent = message;

        const container = document.getElementById('notifications');
        container.appendChild(notification);

        // Auto remove after 5 seconds
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 5000);

        // Add click to dismiss
        notification.addEventListener('click', () => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        });

        // Play notification sound if enabled
        this.playNotificationSound(type);
    }

    playNotificationSound(type) {
        try {
            const audioContext = new (window.AudioContext || window.webkitAudioContext)();
            const oscillator = audioContext.createOscillator();
            const gainNode = audioContext.createGain();

            oscillator.connect(gainNode);
            gainNode.connect(audioContext.destination);

            // Different sounds for different notification types
            switch (type) {
                case 'success':
                    oscillator.frequency.setValueAtTime(523.25, audioContext.currentTime); // C5
                    oscillator.frequency.setValueAtTime(659.25, audioContext.currentTime + 0.1); // E5
                    break;
                case 'error':
                    oscillator.frequency.setValueAtTime(415.30, audioContext.currentTime); // G#4
                    oscillator.frequency.setValueAtTime(369.99, audioContext.currentTime + 0.1); // F#4
                    break;
                case 'warning':
                    oscillator.frequency.setValueAtTime(493.88, audioContext.currentTime); // B4
                    break;
                default:
                    oscillator.frequency.setValueAtTime(440.00, audioContext.currentTime); // A4
            }

            oscillator.type = 'sine';
            gainNode.gain.setValueAtTime(0.1, audioContext.currentTime);
            gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.2);

            oscillator.start(audioContext.currentTime);
            oscillator.stop(audioContext.currentTime + 0.2);
        } catch (error) {
            // Audio not supported or blocked, fail silently
            console.log('Audio feedback not available');
        }
    }

    // Utility methods
    getCurrentUser() {
        return this.currentUser;
    }

    isUserLoggedIn() {
        return this.isLoggedIn;
    }

    requireAuth() {
        if (!this.isLoggedIn) {
            this.showNotification('Please log in to access this feature', 'warning');
            this.showLoginScreen();
            return false;
        }
        return true;
    }

    // Session management
    async refreshSession() {
        // Check if session is still valid
        if (this.isLoggedIn && this.apiClient.isAuthenticated()) {
            try {
                // Try to get profile to verify session
                const response = await this.apiClient.getProfile();
                if (response.success) {
                    this.updateUserInfo();
                    return true;
                } else {
                    throw new Error('Session invalid');
                }
            } catch (error) {
                // Session expired, logout
                await this.handleLogout();
                return false;
            }
        } else {
            // Not logged in
            this.showLoginScreen();
            return false;
        }
    }

    // Auto-logout on token expiration
    setupSessionMonitoring() {
        // Check session every 5 minutes
        setInterval(() => {
            if (this.isLoggedIn) {
                this.refreshSession();
            }
        }, 5 * 60 * 1000);
    }

    // Keyboard shortcuts
    setupKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            // Ctrl/Cmd + L for logout
            if ((e.ctrlKey || e.metaKey) && e.key === 'l' && this.isLoggedIn) {
                e.preventDefault();
                this.handleLogout();
            }
            
            // Enter to submit forms
            if (e.key === 'Enter') {
                const activeForm = document.querySelector('.auth-form.active');
                if (activeForm) {
                    e.preventDefault();
                    activeForm.dispatchEvent(new Event('submit'));
                }
            }
        });
    }

    // Accessibility helpers
    announceToScreenReader(message) {
        const announcement = document.createElement('div');
        announcement.setAttribute('aria-live', 'polite');
        announcement.setAttribute('aria-atomic', 'true');
        announcement.className = 'sr-only';
        announcement.textContent = message;
        
        document.body.appendChild(announcement);
        
        setTimeout(() => {
            document.body.removeChild(announcement);
        }, 1000);
    }

    // Demo mode helpers
    isDemoMode() {
        return this.apiClient.isDemoMode();
    }

    showDemoNotification() {
        if (this.isDemoMode()) {
            this.showNotification('Running in demo mode - no real transactions', 'info');
        }
    }
}

// Initialize authentication when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.authManager = new DesktopAuthManager();
    window.authManager.setupSessionMonitoring();
    window.authManager.setupKeyboardShortcuts();
    
    // Show demo notification if in demo mode
    setTimeout(() => {
        window.authManager.showDemoNotification();
    }, 2000);
});
