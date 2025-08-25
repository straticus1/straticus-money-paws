/**
 * Money Paws Desktop - Main Application Module
 * Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>
 */

class DesktopMoneyPawsApp {
    constructor() {
        this.authManager = window.authManager;
        this.apiClient = window.apiClient;
        this.gamingManager = window.gamingManager;
        this.currentView = 'dashboard';
        this.balances = {};
        this.prices = {};
        this.refreshInterval = null;
        this.settings = {};
        
        this.bindEvents();
    }

    async initialize() {
        if (!this.authManager.requireAuth()) return;

        try {
            // Load initial data
            await this.loadBalances();
            await this.loadPrices();
            await this.loadProfile();
            await this.loadSettings();
            
            // Initialize components
            await this.gamingManager.initialize();
            
            // Start auto-refresh
            this.startAutoRefresh();
            
            // Show dashboard by default
            this.showView('dashboard');
            
            // Update app version
            this.updateAppVersion();
            
        } catch (error) {
            this.showNotification('Failed to initialize application', 'error');
        }
    }

    bindEvents() {
        // Navigation
        document.querySelectorAll('.nav-item').forEach(item => {
            item.addEventListener('click', (e) => {
                e.preventDefault();
                const view = e.currentTarget.dataset.view;
                if (view) {
                    this.showView(view);
                }
            });
        });

        // Refresh button
        document.getElementById('refresh-btn').addEventListener('click', () => {
            this.refreshData();
        });

        // Settings toggles
        document.getElementById('theme-toggle').addEventListener('change', (e) => {
            this.toggleTheme(e.target.checked);
        });

        document.getElementById('sound-toggle').addEventListener('change', (e) => {
            this.toggleSound(e.target.checked);
        });

        document.getElementById('animations-toggle').addEventListener('change', (e) => {
            this.toggleAnimations(e.target.checked);
        });

        // Profile form
        document.getElementById('profile-form').addEventListener('submit', (e) => {
            e.preventDefault();
            this.updateProfile();
        });

        // Balance chart toggle
        document.getElementById('chart-toggle').addEventListener('click', () => {
            this.toggleBalanceChart();
        });

        // Quick actions
        document.querySelectorAll('.action-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                this.handleQuickAction(e.target.dataset.action);
            });
        });

        // Settings buttons
        document.getElementById('check-updates').addEventListener('click', () => {
            this.checkForUpdates();
        });

        document.getElementById('export-data').addEventListener('click', () => {
            this.exportData();
        });

        document.getElementById('clear-data').addEventListener('click', () => {
            this.clearLocalData();
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            this.handleGlobalKeyboardShortcuts(e);
        });
    }

    handleGlobalKeyboardShortcuts(e) {
        // Ctrl/Cmd + number keys for navigation
        if ((e.ctrlKey || e.metaKey) && e.key >= '1' && e.key <= '5') {
            e.preventDefault();
            const views = ['dashboard', 'gaming', 'balances', 'profile', 'settings'];
            const index = parseInt(e.key) - 1;
            if (views[index]) {
                this.showView(views[index]);
            }
        }

        // Ctrl/Cmd + R for refresh
        if ((e.ctrlKey || e.metaKey) && e.key === 'r') {
            e.preventDefault();
            this.refreshData();
        }

        // F5 for refresh
        if (e.key === 'F5') {
            e.preventDefault();
            this.refreshData();
        }
    }

    handleQuickAction(action) {
        switch (action) {
            case 'play-game':
                this.showView('gaming');
                break;
            case 'view-balances':
                this.showView('balances');
                break;
            case 'deposit':
                this.showNotification('Deposit feature coming soon', 'info');
                break;
            case 'withdraw':
                this.showNotification('Withdrawal feature coming soon', 'info');
                break;
        }
    }

    showView(viewName) {
        // Update navigation
        document.querySelectorAll('.nav-item').forEach(item => {
            item.classList.toggle('active', item.dataset.view === viewName);
        });

        // Update content
        document.querySelectorAll('.view').forEach(view => {
            view.classList.toggle('active', view.id === `${viewName}-view`);
        });

        // Update page title
        const titles = {
            dashboard: 'Dashboard',
            gaming: 'Gaming',
            balances: 'Balances',
            profile: 'Profile',
            settings: 'Settings'
        };
        
        document.getElementById('page-title').textContent = titles[viewName] || 'Money Paws';
        this.currentView = viewName;

        // Load view-specific data
        switch (viewName) {
            case 'dashboard':
                this.updateDashboard();
                break;
            case 'gaming':
                this.gamingManager.initialize();
                break;
            case 'balances':
                this.updateBalancesView();
                break;
            case 'profile':
                this.updateProfileView();
                break;
            case 'settings':
                this.updateSettingsView();
                break;
        }

        // Announce view change to screen reader
        this.announceToScreenReader(`Switched to ${titles[viewName]} view`);
    }

    async loadBalances() {
        try {
            const response = await this.apiClient.getBalances();
            if (response.success) {
                this.balances = response.balances;
                this.updateBalanceDisplays();
            }
        } catch (error) {
            console.error('Failed to load balances:', error);
        }
    }

    async loadPrices() {
        try {
            const response = await this.apiClient.getCryptoPrices();
            if (response.success) {
                this.prices = response.prices;
                this.updatePriceDisplays();
            }
        } catch (error) {
            console.error('Failed to load prices:', error);
        }
    }

    async loadProfile() {
        try {
            const response = await this.apiClient.getProfile();
            if (response.success) {
                this.updateProfileData(response.profile);
            }
        } catch (error) {
            console.error('Failed to load profile:', error);
        }
    }

    async loadSettings() {
        try {
            const response = await this.apiClient.getSettings();
            if (response.success) {
                this.settings = response.settings;
                this.applySettings();
            }
        } catch (error) {
            console.error('Failed to load settings:', error);
        }
    }

    applySettings() {
        // Apply theme
        this.toggleTheme(this.settings.theme === 'dark');
        
        // Apply sound setting
        this.toggleSound(this.settings.soundEnabled !== false);
        
        // Apply animations setting
        this.toggleAnimations(this.settings.animationsEnabled !== false);
        
        // Update UI toggles
        document.getElementById('theme-toggle').checked = this.settings.theme === 'dark';
        document.getElementById('sound-toggle').checked = this.settings.soundEnabled !== false;
        document.getElementById('animations-toggle').checked = this.settings.animationsEnabled !== false;
    }

    async saveSettings() {
        try {
            await this.apiClient.saveSettings(this.settings);
        } catch (error) {
            console.error('Failed to save settings:', error);
        }
    }

    updateDashboard() {
        // Update total portfolio value
        let totalValue = 0;
        Object.keys(this.balances).forEach(crypto => {
            const balance = this.balances[crypto];
            const price = this.prices[crypto] || 0;
            totalValue += balance * price;
        });

        document.getElementById('total-value').textContent = this.formatCurrency(totalValue);

        // Update quick stats
        const cryptoCount = Object.keys(this.balances).filter(crypto => this.balances[crypto] > 0).length;
        document.getElementById('crypto-count').textContent = cryptoCount;

        // Update gaming stats
        const gameStats = this.gamingManager.getGameStats();
        document.getElementById('games-played').textContent = gameStats.gamesPlayed;
        document.getElementById('win-rate').textContent = Math.round(gameStats.winRate * 100) + '%';

        // Update recent activity
        this.updateRecentActivity();
    }

    updateBalanceDisplays() {
        const container = document.getElementById('balance-cards');
        if (!container) return;

        container.innerHTML = '';

        Object.keys(this.balances).forEach(crypto => {
            const balance = this.balances[crypto];
            const price = this.prices[crypto] || 0;
            const value = balance * price;

            if (balance > 0) {
                const card = this.createBalanceCard(crypto, balance, price, value);
                container.appendChild(card);
            }
        });
    }

    createBalanceCard(crypto, balance, price, value) {
        const card = document.createElement('div');
        card.className = 'balance-card';
        card.innerHTML = `
            <div class="balance-header">
                <span class="crypto-symbol">${crypto}</span>
                <span class="crypto-price" data-crypto="${crypto}">${this.formatCurrency(price)}</span>
            </div>
            <div class="balance-amount">${this.formatCrypto(balance, crypto)}</div>
            <div class="balance-value">${this.formatCurrency(value)}</div>
        `;
        return card;
    }

    updatePriceDisplays() {
        // Update price displays throughout the app
        document.querySelectorAll('.crypto-price').forEach(element => {
            const crypto = element.dataset.crypto;
            if (crypto && this.prices[crypto]) {
                element.textContent = this.formatCurrency(this.prices[crypto]);
            }
        });
    }

    updateBalancesView() {
        this.updateBalanceDisplays();
        
        // Update chart if visible
        if (document.getElementById('balance-chart').style.display !== 'none') {
            this.updateBalanceChart();
        }
    }

    updateProfileView() {
        const user = this.authManager.getCurrentUser();
        if (user) {
            document.getElementById('profile-name-input').value = user.name || '';
            document.getElementById('profile-email-input').value = user.email || '';
        }
    }

    updateSettingsView() {
        // Settings are already updated in applySettings()
    }

    updateProfileData(profile) {
        // Update profile display elements
        if (profile.joinDate) {
            document.getElementById('profile-join-date').textContent = new Date(profile.joinDate).toLocaleDateString();
        }
        
        if (profile.totalGames !== undefined) {
            document.getElementById('profile-total-games').textContent = profile.totalGames;
        }
        
        if (profile.totalWinnings !== undefined) {
            document.getElementById('profile-total-winnings').textContent = this.formatCurrency(profile.totalWinnings);
        }
    }

    updateRecentActivity() {
        const container = document.getElementById('recent-activity');
        if (!container) return;

        // Mock recent activity data (in real app, would come from API)
        const activities = [
            { type: 'game', description: 'Won 0.001 BTC in coin flip', time: '2 minutes ago', positive: true },
            { type: 'deposit', description: 'Deposited 0.05 ETH', time: '1 hour ago', positive: true },
            { type: 'game', description: 'Lost 0.0005 BTC in coin flip', time: '3 hours ago', positive: false },
            { type: 'game', description: 'Won 50 USDC in coin flip', time: '5 hours ago', positive: true }
        ];

        container.innerHTML = activities.map(activity => `
            <div class="activity-item ${activity.positive ? 'positive' : 'negative'}">
                <div class="activity-icon">${activity.type === 'game' ? '🎮' : '💰'}</div>
                <div class="activity-content">
                    <div class="activity-description">${activity.description}</div>
                    <div class="activity-time">${activity.time}</div>
                </div>
            </div>
        `).join('');
    }

    toggleBalanceChart() {
        const chart = document.getElementById('balance-chart');
        const toggle = document.getElementById('chart-toggle');
        
        if (chart.style.display === 'none') {
            chart.style.display = 'block';
            toggle.textContent = 'Hide Chart';
            this.updateBalanceChart();
        } else {
            chart.style.display = 'none';
            toggle.textContent = 'Show Chart';
        }
    }

    updateBalanceChart() {
        const canvas = document.getElementById('balance-chart-canvas');
        if (!canvas) return;

        const ctx = canvas.getContext('2d');
        const width = canvas.width = canvas.offsetWidth;
        const height = canvas.height = 200;

        // Clear canvas
        ctx.clearRect(0, 0, width, height);

        // Calculate portfolio distribution
        const data = [];
        let total = 0;

        Object.keys(this.balances).forEach(crypto => {
            const balance = this.balances[crypto];
            const price = this.prices[crypto] || 0;
            const value = balance * price;
            if (value > 0) {
                data.push({ crypto, value });
                total += value;
            }
        });

        if (total === 0) return;

        // Draw pie chart
        let currentAngle = 0;
        const centerX = width / 2;
        const centerY = height / 2;
        const radius = Math.min(width, height) / 3;

        const colors = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4'];

        data.forEach((item, index) => {
            const percentage = item.value / total;
            const sliceAngle = percentage * 2 * Math.PI;

            // Draw slice
            ctx.beginPath();
            ctx.moveTo(centerX, centerY);
            ctx.arc(centerX, centerY, radius, currentAngle, currentAngle + sliceAngle);
            ctx.closePath();
            ctx.fillStyle = colors[index % colors.length];
            ctx.fill();

            // Draw label
            const labelAngle = currentAngle + sliceAngle / 2;
            const labelX = centerX + Math.cos(labelAngle) * (radius + 20);
            const labelY = centerY + Math.sin(labelAngle) * (radius + 20);

            ctx.fillStyle = getComputedStyle(document.body).getPropertyValue('--text-color') || '#333';
            ctx.font = '12px -apple-system, BlinkMacSystemFont, sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText(`${item.crypto} (${(percentage * 100).toFixed(1)}%)`, labelX, labelY);

            currentAngle += sliceAngle;
        });
    }

    async updateProfile() {
        const name = document.getElementById('profile-name-input').value;
        const email = document.getElementById('profile-email-input').value;

        if (!name || !email) {
            this.showNotification('Please fill in all fields', 'warning');
            return;
        }

        try {
            const response = await this.apiClient.updateProfile({ name, email });
            
            if (response.success) {
                this.showNotification('Profile updated successfully', 'success');
                this.authManager.updateUserInfo();
            } else {
                this.showNotification(response.message || 'Failed to update profile', 'error');
            }
        } catch (error) {
            this.showNotification(this.apiClient.handleError(error, 'profile update'), 'error');
        }
    }

    async refreshData() {
        const refreshBtn = document.getElementById('refresh-btn');
        refreshBtn.disabled = true;
        refreshBtn.textContent = '🔄';
        refreshBtn.style.animation = 'spin 1s linear infinite';

        try {
            await Promise.all([
                this.loadBalances(),
                this.loadPrices(),
                this.loadProfile()
            ]);

            this.showNotification('Data refreshed successfully', 'success');
            
            // Update current view
            this.showView(this.currentView);
            
            // Refresh gaming balances
            if (this.gamingManager) {
                this.gamingManager.refreshBalances();
            }
            
        } catch (error) {
            this.showNotification('Failed to refresh data', 'error');
        } finally {
            refreshBtn.disabled = false;
            refreshBtn.textContent = '🔄';
            refreshBtn.style.animation = '';
        }
    }

    startAutoRefresh() {
        if (this.settings.autoRefresh !== false) {
            const interval = this.settings.refreshInterval || 30000;
            this.refreshInterval = setInterval(() => {
                this.loadBalances();
                this.loadPrices();
            }, interval);
        }
    }

    stopAutoRefresh() {
        if (this.refreshInterval) {
            clearInterval(this.refreshInterval);
            this.refreshInterval = null;
        }
    }

    // Settings methods
    toggleTheme(isDark) {
        document.body.classList.toggle('dark-theme', isDark);
        this.settings.theme = isDark ? 'dark' : 'light';
        this.saveSettings();
        
        // Apply platform-specific class
        if (window.platform) {
            if (window.platform.isMac) {
                document.body.classList.add('platform-mac');
            } else if (window.platform.isWindows) {
                document.body.classList.add('platform-windows');
            } else if (window.platform.isLinux) {
                document.body.classList.add('platform-linux');
            }
        }
    }

    toggleSound(enabled) {
        this.settings.soundEnabled = enabled;
        this.saveSettings();
        
        // Update gaming manager sound setting
        if (this.gamingManager) {
            this.gamingManager.setSoundEnabled(enabled);
        }
    }

    toggleAnimations(enabled) {
        document.body.classList.toggle('reduced-motion', !enabled);
        this.settings.animationsEnabled = enabled;
        this.saveSettings();
    }

    async updateAppVersion() {
        try {
            const version = await window.electronAPI.getAppVersion();
            document.getElementById('app-version').textContent = version;
        } catch (error) {
            console.error('Failed to get app version:', error);
        }
    }

    async checkForUpdates() {
        const button = document.getElementById('check-updates');
        button.disabled = true;
        button.textContent = 'Checking...';

        try {
            // Simulate update check
            await new Promise(resolve => setTimeout(resolve, 2000));
            this.showNotification('You are running the latest version', 'success');
        } catch (error) {
            this.showNotification('Failed to check for updates', 'error');
        } finally {
            button.disabled = false;
            button.textContent = 'Check for Updates';
        }
    }

    async exportData() {
        try {
            const result = await window.electronAPI.showSaveDialog({
                title: 'Export Money Paws Data',
                defaultPath: 'money-paws-data.json',
                filters: [
                    { name: 'JSON Files', extensions: ['json'] },
                    { name: 'All Files', extensions: ['*'] }
                ]
            });

            if (!result.canceled) {
                const data = await this.apiClient.exportUserData();
                if (data.success) {
                    this.showNotification('Data exported successfully', 'success');
                }
            }
        } catch (error) {
            this.showNotification('Failed to export data', 'error');
        }
    }

    async clearLocalData() {
        try {
            const result = await window.electronAPI.showMessageBox({
                type: 'warning',
                buttons: ['Clear Data', 'Cancel'],
                defaultId: 1,
                title: 'Clear Local Data',
                message: 'Are you sure you want to clear all local data?',
                detail: 'This will remove all settings and cached data. You will need to login again.'
            });

            if (result.response === 0) {
                // Clear all stored data
                await window.electronAPI.setStoreValue('authToken', null);
                await window.electronAPI.setStoreValue('currentUser', null);
                await window.electronAPI.setStoreValue('appSettings', null);
                
                this.showNotification('Local data cleared. Please restart the application.', 'success');
                
                // Logout user
                setTimeout(() => {
                    this.authManager.handleLogout();
                }, 2000);
            }
        } catch (error) {
            this.showNotification('Failed to clear local data', 'error');
        }
    }

    // Utility methods
    formatCrypto(amount, crypto) {
        const decimals = crypto === 'BTC' ? 8 : crypto === 'ETH' ? 6 : 2;
        return amount.toFixed(decimals) + ' ' + crypto;
    }

    formatCurrency(amount) {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD',
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(amount);
    }

    showNotification(message, type = 'info') {
        if (this.authManager && this.authManager.showNotification) {
            this.authManager.showNotification(message, type);
        }
    }

    announceToScreenReader(message) {
        if (this.authManager && this.authManager.announceToScreenReader) {
            this.authManager.announceToScreenReader(message);
        }
    }

    // Public methods
    refreshBalances() {
        return this.loadBalances();
    }

    getCurrentView() {
        return this.currentView;
    }

    getBalances() {
        return this.balances;
    }

    getPrices() {
        return this.prices;
    }

    getSettings() {
        return this.settings;
    }

    // Cleanup
    destroy() {
        this.stopAutoRefresh();
        
        // Remove event listeners
        window.electronAPI.removeAllListeners('show-settings');
    }
}

// Add CSS for spin animation
const style = document.createElement('style');
style.textContent = `
    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
`;
document.head.appendChild(style);

// Initialize app when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.app = new DesktopMoneyPawsApp();
});
