/**
 * Money Paws Web GUI - API Client
 * Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>
 */

class APIClient {
    constructor() {
        this.baseURL = window.location.origin;
        this.token = localStorage.getItem('auth_token');
        this.user = JSON.parse(localStorage.getItem('user_data') || 'null');
    }

    // Set authentication token
    setAuth(token, userData) {
        this.token = token;
        this.user = userData;
        localStorage.setItem('auth_token', token);
        localStorage.setItem('user_data', JSON.stringify(userData));
    }

    // Clear authentication
    clearAuth() {
        this.token = null;
        this.user = null;
        localStorage.removeItem('auth_token');
        localStorage.removeItem('user_data');
    }

    // Check if user is authenticated
    isAuthenticated() {
        return !!this.token && !!this.user;
    }

    // Get current user data
    getCurrentUser() {
        return this.user;
    }

    // Make HTTP request with error handling
    async request(endpoint, options = {}) {
        const url = `${this.baseURL}${endpoint}`;
        
        const config = {
            headers: {
                'Content-Type': 'application/json',
                ...options.headers
            },
            ...options
        };

        // Add auth token if available
        if (this.token) {
            config.headers['Authorization'] = `Bearer ${this.token}`;
        }

        try {
            const response = await fetch(url, config);
            
            // Handle non-JSON responses
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                throw new Error('Server returned non-JSON response');
            }

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || `HTTP ${response.status}`);
            }

            return data;
        } catch (error) {
            console.error('API Request failed:', error);
            throw error;
        }
    }

    // Authentication methods
    async login(email, password) {
        try {
            // Simulate login for demo - replace with actual API call
            const mockResponse = {
                success: true,
                token: 'demo_token_' + Date.now(),
                user: {
                    id: 1,
                    name: 'Demo User',
                    email: email,
                    provider: 'local'
                }
            };

            if (mockResponse.success) {
                this.setAuth(mockResponse.token, mockResponse.user);
                return mockResponse;
            } else {
                throw new Error(mockResponse.message || 'Login failed');
            }
        } catch (error) {
            throw new Error('Login failed: ' + error.message);
        }
    }

    async register(name, email, password) {
        try {
            // Simulate registration for demo
            const mockResponse = {
                success: true,
                message: 'Account created successfully'
            };

            return mockResponse;
        } catch (error) {
            throw new Error('Registration failed: ' + error.message);
        }
    }

    async loginWithOAuth(provider) {
        try {
            // Simulate OAuth login
            const mockResponse = {
                success: true,
                token: 'oauth_token_' + Date.now(),
                user: {
                    id: 1,
                    name: `${provider} User`,
                    email: `user@${provider}.com`,
                    provider: provider
                }
            };

            if (mockResponse.success) {
                this.setAuth(mockResponse.token, mockResponse.user);
                return mockResponse;
            }
        } catch (error) {
            throw new Error(`${provider} login failed: ` + error.message);
        }
    }

    // Crypto balance methods
    async getBalances() {
        try {
            // Mock balances for demo
            return {
                success: true,
                balances: {
                    BTC: 0.001,
                    ETH: 0.05,
                    USDC: 100.0,
                    SOL: 2.5,
                    XRP: 200.0
                }
            };
        } catch (error) {
            throw new Error('Failed to fetch balances: ' + error.message);
        }
    }

    async getCryptoPrices() {
        try {
            // Mock prices for demo
            return {
                success: true,
                prices: {
                    BTC: 45000.00,
                    ETH: 3000.00,
                    USDC: 1.00,
                    SOL: 100.00,
                    XRP: 0.50
                }
            };
        } catch (error) {
            throw new Error('Failed to fetch prices: ' + error.message);
        }
    }

    // Gaming methods
    async playGame(gameType, crypto, amount, choice) {
        try {
            // Simulate game play
            const result = Math.random() > 0.5 ? choice : (choice === 'heads' ? 'tails' : 'heads');
            const won = result === choice;
            const winAmount = won ? amount * 2 : 0;

            return {
                success: true,
                result: result,
                won: won,
                amount: amount,
                winAmount: winAmount,
                crypto: crypto
            };
        } catch (error) {
            throw new Error('Game failed: ' + error.message);
        }
    }

    // Pet interaction methods
    async feedPet(petId, itemId) {
        try {
            return await this.request('/api/feed-pet.php', {
                method: 'POST',
                body: JSON.stringify({ pet_id: petId, item_id: itemId })
            });
        } catch (error) {
            throw new Error('Failed to feed pet: ' + error.message);
        }
    }

    async treatPet(petId, itemId) {
        try {
            return await this.request('/api/treat-pet.php', {
                method: 'POST',
                body: JSON.stringify({ pet_id: petId, item_id: itemId })
            });
        } catch (error) {
            throw new Error('Failed to treat pet: ' + error.message);
        }
    }

    // Statistics methods
    async getRecentWinners() {
        try {
            return await this.request('/api/get-recent-winners.php');
        } catch (error) {
            throw new Error('Failed to fetch recent winners: ' + error.message);
        }
    }

    async getUserStats() {
        try {
            // Mock user stats for demo
            return {
                success: true,
                stats: {
                    gamesPlayed: 15,
                    totalWinnings: 125.50,
                    winRate: 60,
                    favoriteGame: 'Coin Flip'
                }
            };
        } catch (error) {
            throw new Error('Failed to fetch user stats: ' + error.message);
        }
    }

    // Portfolio methods
    async getPortfolioHistory() {
        try {
            // Mock portfolio history for charts
            const now = Date.now();
            const data = [];
            
            for (let i = 30; i >= 0; i--) {
                const date = new Date(now - (i * 24 * 60 * 60 * 1000));
                const value = 1000 + Math.random() * 500 - 250; // Random walk around $1000
                data.push({
                    date: date.toISOString().split('T')[0],
                    value: Math.max(0, value)
                });
            }

            return {
                success: true,
                history: data
            };
        } catch (error) {
            throw new Error('Failed to fetch portfolio history: ' + error.message);
        }
    }

    // Activity methods
    async getRecentActivity() {
        try {
            // Mock recent activity
            return {
                success: true,
                activities: [
                    {
                        type: 'game',
                        description: 'Won 10 USDC in Coin Flip',
                        timestamp: new Date(Date.now() - 1000 * 60 * 30).toISOString(),
                        icon: '🎮'
                    },
                    {
                        type: 'deposit',
                        description: 'Deposited 0.001 BTC',
                        timestamp: new Date(Date.now() - 1000 * 60 * 60 * 2).toISOString(),
                        icon: '💰'
                    },
                    {
                        type: 'login',
                        description: 'Logged in successfully',
                        timestamp: new Date(Date.now() - 1000 * 60 * 60 * 3).toISOString(),
                        icon: '🔐'
                    }
                ]
            };
        } catch (error) {
            throw new Error('Failed to fetch recent activity: ' + error.message);
        }
    }

    // Utility methods
    formatCurrency(amount, currency = 'USD') {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: currency,
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(amount);
    }

    formatCrypto(amount, crypto) {
        const decimals = crypto === 'BTC' ? 8 : crypto === 'ETH' ? 6 : 2;
        return amount.toFixed(decimals) + ' ' + crypto;
    }

    formatTimeAgo(timestamp) {
        const now = new Date();
        const time = new Date(timestamp);
        const diffInSeconds = Math.floor((now - time) / 1000);

        if (diffInSeconds < 60) {
            return 'Just now';
        } else if (diffInSeconds < 3600) {
            const minutes = Math.floor(diffInSeconds / 60);
            return `${minutes} minute${minutes > 1 ? 's' : ''} ago`;
        } else if (diffInSeconds < 86400) {
            const hours = Math.floor(diffInSeconds / 3600);
            return `${hours} hour${hours > 1 ? 's' : ''} ago`;
        } else {
            const days = Math.floor(diffInSeconds / 86400);
            return `${days} day${days > 1 ? 's' : ''} ago`;
        }
    }

    // Error handling helper
    handleError(error, context = '') {
        console.error(`API Error ${context}:`, error);
        
        // Show user-friendly error messages
        let message = 'An unexpected error occurred';
        
        if (error.message.includes('network') || error.message.includes('fetch')) {
            message = 'Network error. Please check your connection.';
        } else if (error.message.includes('401') || error.message.includes('unauthorized')) {
            message = 'Session expired. Please log in again.';
            this.clearAuth();
        } else if (error.message.includes('403') || error.message.includes('forbidden')) {
            message = 'Access denied. You do not have permission.';
        } else if (error.message.includes('404')) {
            message = 'Requested resource not found.';
        } else if (error.message.includes('500')) {
            message = 'Server error. Please try again later.';
        } else if (error.message) {
            message = error.message;
        }

        return message;
    }
}

// Create global API client instance
window.apiClient = new APIClient();
