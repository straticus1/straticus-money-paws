/**
 * Money Paws Desktop - API Client
 * Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>
 */

class DesktopAPIClient {
    constructor() {
        this.baseURL = 'http://localhost'; // This will be configured based on environment
        this.authToken = null;
        this.currentUser = null;
        this.isDemo = true; // Default to true until properly initialized
    }

    async initialize() {
        this.isDemo = await window.electronAPI.isDev();
        console.log(`API Client initialized. Demo mode: ${this.isDemo}`);
        await this.loadStoredAuth();
    }

    async loadStoredAuth() {
        try {
            this.authToken = await window.electronAPI.getStoreValue('authToken');
            this.currentUser = await window.electronAPI.getStoreValue('currentUser');
        } catch (error) {
            console.log('No stored auth found');
        }
    }

    async saveAuth(token, user) {
        this.authToken = token;
        this.currentUser = user;
        
        try {
            await window.electronAPI.setStoreValue('authToken', token);
            await window.electronAPI.setStoreValue('currentUser', user);
        } catch (error) {
            console.error('Failed to save auth:', error);
        }
    }

    async clearAuth() {
        this.authToken = null;
        this.currentUser = null;
        
        try {
            await window.electronAPI.setStoreValue('authToken', null);
            await window.electronAPI.setStoreValue('currentUser', null);
        } catch (error) {
            console.error('Failed to clear auth:', error);
        }
    }

    getAuthHeaders() {
        return this.authToken ? {
            'Authorization': `Bearer ${this.authToken}`
        } : {};
    }

    async apiRequest(method, endpoint, data = null) {
        try {
            const headers = {
                'Content-Type': 'application/json',
                ...this.getAuthHeaders()
            };

            const response = await window.electronAPI.apiRequest(method, endpoint, data, headers);
            
            if (!response.success && response.status === 401) {
                // Token expired, clear auth
                await this.clearAuth();
                throw new Error('Authentication expired');
            }

            return response;
        } catch (error) {
            console.error('API Request failed:', error);
            throw error;
        }
    }

    // Authentication methods
    async login(email, password) {
        if (this.isDemo) {
            // Demo mode - simulate successful login
            await new Promise(resolve => setTimeout(resolve, 1000));
            
            const mockUser = {
                id: 1,
                name: 'Demo User',
                email: email,
                provider: 'local',
                joinDate: '2024-01-01'
            };
            
            const mockToken = 'demo_token_' + Date.now();
            await this.saveAuth(mockToken, mockUser);
            
            return {
                success: true,
                user: mockUser,
                token: mockToken
            };
        }

        try {
            const response = await this.apiRequest('POST', '/api/auth/login', {
                email,
                password
            });

            if (response.success && response.data.token) {
                await this.saveAuth(response.data.token, response.data.user);
            }

            return response.data;
        } catch (error) {
            return {
                success: false,
                message: this.handleError(error, 'login')
            };
        }
    }

    async register(name, email, password) {
        if (this.isDemo) {
            // Demo mode - simulate successful registration
            await new Promise(resolve => setTimeout(resolve, 1500));
            
            return {
                success: true,
                message: 'Account created successfully'
            };
        }

        try {
            const response = await this.apiRequest('POST', '/api/auth/register', {
                name,
                email,
                password
            });

            return response.data;
        } catch (error) {
            return {
                success: false,
                message: this.handleError(error, 'registration')
            };
        }
    }

    async loginWithOAuth(provider) {
        if (this.isDemo) {
            // Demo mode - simulate OAuth login
            await new Promise(resolve => setTimeout(resolve, 2000));
            
            const mockUser = {
                id: 2,
                name: `${provider.charAt(0).toUpperCase() + provider.slice(1)} User`,
                email: `user@${provider}.com`,
                provider: provider,
                joinDate: '2024-01-01'
            };
            
            const mockToken = `${provider}_token_` + Date.now();
            await this.saveAuth(mockToken, mockUser);
            
            return {
                success: true,
                user: mockUser,
                token: mockToken
            };
        }

        try {
            // In a real implementation, this would handle OAuth flow
            const response = await this.apiRequest('POST', `/api/auth/oauth/${provider}`);
            
            if (response.success && response.data.token) {
                await this.saveAuth(response.data.token, response.data.user);
            }

            return response.data;
        } catch (error) {
            return {
                success: false,
                message: this.handleError(error, `${provider} login`)
            };
        }
    }

    // Balance and crypto methods
    async getBalances() {
        if (this.isDemo) {
            // Demo mode - return mock balances
            await new Promise(resolve => setTimeout(resolve, 500));
            
            return {
                success: true,
                balances: {
                    'BTC': 0.05432100,
                    'ETH': 2.34567890,
                    'USDC': 1250.00,
                    'SOL': 45.67890123,
                    'XRP': 1000.50000000
                }
            };
        }

        try {
            const response = await this.apiRequest('GET', '/api/get-balances.php');
            return response.data;
        } catch (error) {
            return {
                success: false,
                message: this.handleError(error, 'balance retrieval')
            };
        }
    }

    async getCryptoPrices() {
        if (this.isDemo) {
            // Demo mode - return mock prices
            await new Promise(resolve => setTimeout(resolve, 300));
            
            return {
                success: true,
                prices: {
                    'BTC': 43250.75,
                    'ETH': 2650.30,
                    'USDC': 1.00,
                    'SOL': 98.45,
                    'XRP': 0.62
                }
            };
        }

        try {
            const response = await this.apiRequest('GET', '/api/get-crypto-price.php');
            return response.data;
        } catch (error) {
            return {
                success: false,
                message: this.handleError(error, 'price retrieval')
            };
        }
    }

    // Gaming methods
    async playGame(gameType, crypto, amount, choice) {
        if (this.isDemo) {
            // Demo mode - simulate game play
            await new Promise(resolve => setTimeout(resolve, 2500));
            
            const won = Math.random() > 0.5;
            const result = choice === 'heads' ? 
                (Math.random() > 0.5 ? 'heads' : 'tails') :
                (Math.random() > 0.5 ? 'tails' : 'heads');
            
            const actualWon = result === choice;
            
            return {
                success: true,
                won: actualWon,
                result: result,
                crypto: crypto,
                amount: amount,
                winAmount: actualWon ? amount * 2 : 0,
                gameType: gameType
            };
        }

        try {
            const response = await this.apiRequest('POST', '/api/play-game.php', {
                game_type: gameType,
                crypto: crypto,
                amount: amount,
                choice: choice
            });

            return response.data;
        } catch (error) {
            return {
                success: false,
                message: this.handleError(error, 'game play')
            };
        }
    }

    // Profile methods
    async getProfile() {
        if (this.isDemo) {
            // Demo mode - return mock profile
            await new Promise(resolve => setTimeout(resolve, 400));
            
            return {
                success: true,
                profile: {
                    name: this.currentUser?.name || 'Demo User',
                    email: this.currentUser?.email || 'demo@example.com',
                    joinDate: '2024-01-01',
                    totalGames: 42,
                    totalWinnings: 1250.75,
                    provider: this.currentUser?.provider || 'local'
                }
            };
        }

        try {
            const response = await this.apiRequest('GET', '/api/profile.php');
            return response.data;
        } catch (error) {
            return {
                success: false,
                message: this.handleError(error, 'profile retrieval')
            };
        }
    }

    async updateProfile(profileData) {
        if (this.isDemo) {
            // Demo mode - simulate profile update
            await new Promise(resolve => setTimeout(resolve, 800));
            
            // Update stored user data
            const updatedUser = { ...this.currentUser, ...profileData };
            await this.saveAuth(this.authToken, updatedUser);
            
            return {
                success: true,
                message: 'Profile updated successfully'
            };
        }

        try {
            const response = await this.apiRequest('POST', '/api/update-profile.php', profileData);
            
            if (response.success) {
                // Update stored user data
                const updatedUser = { ...this.currentUser, ...profileData };
                await this.saveAuth(this.authToken, updatedUser);
            }

            return response.data;
        } catch (error) {
            return {
                success: false,
                message: this.handleError(error, 'profile update')
            };
        }
    }

    // Utility methods
    isAuthenticated() {
        return !!this.authToken && !!this.currentUser;
    }

    getCurrentUser() {
        return this.currentUser;
    }

    handleError(error, context) {
        console.error(`Error in ${context}:`, error);
        
        if (error.message === 'Authentication expired') {
            return 'Your session has expired. Please log in again.';
        }
        
        if (error.message === 'Network Error') {
            return 'Unable to connect to the server. Please check your internet connection.';
        }
        
        if (error.response?.status === 404) {
            return 'The requested service is not available.';
        }
        
        if (error.response?.status === 500) {
            return 'Server error. Please try again later.';
        }
        
        return error.message || `An error occurred during ${context}`;
    }

    // Demo mode controls
    setDemoMode(enabled) {
        this.isDemo = enabled;
    }

    isDemoMode() {
        return this.isDemo;
    }

    // App-specific methods
    async getAppStats() {
        if (this.isDemo) {
            return {
                success: true,
                stats: {
                    totalUsers: 15420,
                    totalGames: 234567,
                    totalVolume: 12345678.90,
                    uptime: '99.9%'
                }
            };
        }

        try {
            const response = await this.apiRequest('GET', '/api/stats.php');
            return response.data;
        } catch (error) {
            return {
                success: false,
                message: this.handleError(error, 'stats retrieval')
            };
        }
    }

    async exportUserData() {
        if (this.isDemo) {
            // Demo mode - return mock export data
            return {
                success: true,
                data: {
                    user: this.currentUser,
                    balances: await this.getBalances(),
                    gameHistory: [
                        { date: '2024-01-15', game: 'coinflip', result: 'win', amount: 0.001 },
                        { date: '2024-01-14', game: 'coinflip', result: 'loss', amount: 0.0005 }
                    ]
                }
            };
        }

        try {
            const response = await this.apiRequest('GET', '/api/export-data.php');
            return response.data;
        } catch (error) {
            return {
                success: false,
                message: this.handleError(error, 'data export')
            };
        }
    }

    // Settings management
    async getSettings() {
        try {
            const settings = await window.electronAPI.getStoreValue('appSettings') || {
                theme: 'light',
                soundEnabled: true,
                animationsEnabled: true,
                autoRefresh: true,
                refreshInterval: 30000
            };
            
            return { success: true, settings };
        } catch (error) {
            return {
                success: false,
                message: 'Failed to load settings'
            };
        }
    }

    async saveSettings(settings) {
        try {
            await window.electronAPI.setStoreValue('appSettings', settings);
            return { success: true };
        } catch (error) {
            return {
                success: false,
                message: 'Failed to save settings'
            };
        }
    }
}

// Initialize API client when DOM is loaded
