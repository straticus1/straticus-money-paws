/**
 * Money Paws Web GUI - Main Application Module
 * Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>
 */

class MoneyPawsApp {
    constructor() {
        this.authManager = window.authManager;
        this.apiClient = window.apiClient;
        this.gamingManager = window.gamingManager;
        this.currentView = 'dashboard';
        this.balances = {};
        this.prices = {};
        this.refreshInterval = null;
        
        this.bindEvents();
    }

    async initialize() {
        if (!this.authManager.requireAuth()) return;

        try {
            // Load initial data
            await this.loadBalances();
            await this.loadPrices();
            await this.loadProfile();
            
            // Initialize components
            this.gamingManager.initialize();
            
            // Start auto-refresh
            this.startAutoRefresh();
            
            // Show dashboard by default
            this.showView('dashboard');
            
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
        }
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

        // Update recent activity (mock data for now)
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
                <span class="crypto-price">${this.formatCurrency(price)}</span>
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

    updateProfileData(profile) {
        // Update profile display elements
        if (profile.avatar) {
            document.getElementById('profile-avatar').style.backgroundImage = `url(${profile.avatar})`;
        }
        
        if (profile.joinDate) {
            document.getElementById('profile-join-date').textContent = new Date(profile.joinDate).toLocaleDateString();
        }
    }

    updateRecentActivity() {
        const container = document.getElementById('recent-activity');
        if (!container) return;

        // Mock recent activity data
        const activities = [
            { type: 'game', description: 'Won 0.001 BTC in coin flip', time: '2 minutes ago', positive: true },
            { type: 'deposit', description: 'Deposited 0.05 ETH', time: '1 hour ago', positive: true },
            { type: 'game', description: 'Lost 0.0005 BTC in coin flip', time: '3 hours ago', positive: false }
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

            ctx.fillStyle = '#333';
            ctx.font = '12px Arial';
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
        refreshBtn.textContent = 'Refreshing...';

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
            refreshBtn.textContent = 'Refresh';
        }
    }

    startAutoRefresh() {
        // Refresh data every 30 seconds
        this.refreshInterval = setInterval(() => {
            this.loadBalances();
            this.loadPrices();
        }, 30000);
    }

    stopAutoRefresh() {
        if (this.refreshInterval) {
            clearInterval(this.refreshInterval);
            this.refreshInterval = null;
        }
    }

    // Settings
    toggleTheme(isDark) {
        document.body.classList.toggle('dark-theme', isDark);
        localStorage.setItem('darkTheme', isDark);
    }

    toggleSound(enabled) {
        localStorage.setItem('soundEnabled', enabled);
        // Update gaming manager sound setting
        if (this.gamingManager) {
            this.gamingManager.soundEnabled = enabled;
        }
    }

    toggleAnimations(enabled) {
        document.body.classList.toggle('reduced-motion', !enabled);
        localStorage.setItem('animationsEnabled', enabled);
    }

    loadSettings() {
        // Load saved settings
        const darkTheme = localStorage.getItem('darkTheme') === 'true';
        const soundEnabled = localStorage.getItem('soundEnabled') !== 'false';
        const animationsEnabled = localStorage.getItem('animationsEnabled') !== 'false';

        document.getElementById('theme-toggle').checked = darkTheme;
        document.getElementById('sound-toggle').checked = soundEnabled;
        document.getElementById('animations-toggle').checked = animationsEnabled;

        this.toggleTheme(darkTheme);
        this.toggleSound(soundEnabled);
        this.toggleAnimations(animationsEnabled);
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

    // Cleanup
    destroy() {
        this.stopAutoRefresh();
    }
}

// Initialize app when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.app = new MoneyPawsApp();
    window.app.loadSettings();
});
