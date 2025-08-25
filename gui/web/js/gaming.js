/**
 * Money Paws Web GUI - Gaming Module
 * Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>
 */

class GamingManager {
    constructor() {
        this.apiClient = window.apiClient;
        this.authManager = window.authManager;
        this.currentGame = 'coinflip';
        this.selectedChoice = null;
        this.isPlaying = false;
        this.balances = {};
        this.prices = {};
        
        this.bindEvents();
    }

    bindEvents() {
        // Game selection
        document.querySelectorAll('.game-card').forEach(card => {
            card.addEventListener('click', (e) => {
                if (!card.classList.contains('disabled')) {
                    this.selectGame(card.dataset.game);
                }
            });
        });

        // Crypto selection
        document.getElementById('game-crypto').addEventListener('change', (e) => {
            this.updateAvailableBalance(e.target.value);
        });

        // Choice buttons
        document.querySelectorAll('.btn-choice').forEach(btn => {
            btn.addEventListener('click', (e) => {
                this.selectChoice(e.target.dataset.choice);
            });
        });

        // Play game button
        document.getElementById('play-game').addEventListener('click', () => {
            this.playGame();
        });

        // Bet amount input
        document.getElementById('bet-amount').addEventListener('input', (e) => {
            this.validateBetAmount();
        });
    }

    async initialize() {
        if (!this.authManager.requireAuth()) return;

        try {
            await this.loadBalances();
            await this.loadPrices();
            this.populateCryptoOptions();
        } catch (error) {
            this.showNotification('Failed to load gaming data', 'error');
        }
    }

    async loadBalances() {
        try {
            const response = await this.apiClient.getBalances();
            if (response.success) {
                this.balances = response.balances;
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
            }
        } catch (error) {
            console.error('Failed to load prices:', error);
        }
    }

    populateCryptoOptions() {
        const select = document.getElementById('game-crypto');
        const currentValue = select.value;
        
        // Clear existing options except the first one
        while (select.children.length > 1) {
            select.removeChild(select.lastChild);
        }

        // Add crypto options with balances
        Object.keys(this.balances).forEach(crypto => {
            const balance = this.balances[crypto];
            if (balance > 0) {
                const option = document.createElement('option');
                option.value = crypto;
                option.textContent = `${crypto} (${this.formatCrypto(balance, crypto)})`;
                select.appendChild(option);
            }
        });

        // Restore selection if still valid
        if (currentValue && this.balances[currentValue] > 0) {
            select.value = currentValue;
            this.updateAvailableBalance(currentValue);
        }
    }

    selectGame(gameType) {
        // Update game selection UI
        document.querySelectorAll('.game-card').forEach(card => {
            card.classList.toggle('active', card.dataset.game === gameType);
        });

        // Show corresponding game content
        document.querySelectorAll('.game-content').forEach(content => {
            content.classList.toggle('active', content.id === `${gameType}-game`);
        });

        this.currentGame = gameType;
        this.resetGameState();
    }

    selectChoice(choice) {
        this.selectedChoice = choice;
        
        // Update choice buttons UI
        document.querySelectorAll('.btn-choice').forEach(btn => {
            btn.classList.toggle('selected', btn.dataset.choice === choice);
        });

        this.validateGameSetup();
    }

    updateAvailableBalance(crypto) {
        const balanceElement = document.getElementById('available-balance');
        if (crypto && this.balances[crypto]) {
            balanceElement.textContent = this.formatCrypto(this.balances[crypto], crypto);
        } else {
            balanceElement.textContent = '0';
        }
        
        this.validateGameSetup();
    }

    validateBetAmount() {
        const crypto = document.getElementById('game-crypto').value;
        const amount = parseFloat(document.getElementById('bet-amount').value) || 0;
        const available = this.balances[crypto] || 0;

        const betInput = document.getElementById('bet-amount');
        
        if (amount > available) {
            betInput.style.borderColor = 'var(--error-color)';
            this.showNotification('Bet amount exceeds available balance', 'warning');
        } else {
            betInput.style.borderColor = 'var(--border-color)';
        }

        this.validateGameSetup();
    }

    validateGameSetup() {
        const crypto = document.getElementById('game-crypto').value;
        const amount = parseFloat(document.getElementById('bet-amount').value) || 0;
        const available = this.balances[crypto] || 0;
        const playButton = document.getElementById('play-game');

        const isValid = crypto && 
                       amount > 0 && 
                       amount <= available && 
                       this.selectedChoice && 
                       !this.isPlaying;

        playButton.disabled = !isValid;
    }

    async playGame() {
        if (!this.authManager.requireAuth()) return;

        const crypto = document.getElementById('game-crypto').value;
        const amount = parseFloat(document.getElementById('bet-amount').value);

        if (!crypto || !amount || !this.selectedChoice) {
            this.showNotification('Please complete all game settings', 'warning');
            return;
        }

        if (amount > this.balances[crypto]) {
            this.showNotification('Insufficient balance', 'error');
            return;
        }

        this.isPlaying = true;
        this.setGameLoading(true);

        try {
            // Show game animation
            this.startGameAnimation();

            // Play the game
            const result = await this.apiClient.playGame(this.currentGame, crypto, amount, this.selectedChoice);

            // Show result after animation
            setTimeout(() => {
                this.showGameResult(result);
                this.updateBalancesAfterGame(result);
            }, 2500);

        } catch (error) {
            this.showNotification(this.apiClient.handleError(error, 'game'), 'error');
            this.setGameLoading(false);
            this.isPlaying = false;
        }
    }

    startGameAnimation() {
        const coin = document.getElementById('coin');
        const resultElement = document.getElementById('game-result');
        
        // Reset previous state
        coin.classList.remove('flipping');
        resultElement.classList.remove('show', 'win', 'lose');
        resultElement.textContent = '';

        // Start flipping animation
        setTimeout(() => {
            coin.classList.add('flipping');
        }, 100);
    }

    showGameResult(result) {
        const resultElement = document.getElementById('game-result');
        const coin = document.getElementById('coin');
        
        // Stop animation
        coin.classList.remove('flipping');
        
        // Show result
        if (result.won) {
            resultElement.textContent = `🎉 ${result.result.toUpperCase()}! You won ${this.formatCrypto(result.winAmount, result.crypto)}!`;
            resultElement.classList.add('show', 'win');
            this.showNotification(`Congratulations! You won ${this.formatCrypto(result.winAmount, result.crypto)}!`, 'success');
            this.playSound('win');
        } else {
            resultElement.textContent = `😔 ${result.result.toUpperCase()}! You lost ${this.formatCrypto(result.amount, result.crypto)}.`;
            resultElement.classList.add('show', 'lose');
            this.showNotification(`Sorry, you lost ${this.formatCrypto(result.amount, result.crypto)}.`, 'error');
            this.playSound('lose');
        }

        // Reset game state after showing result
        setTimeout(() => {
            this.resetGameState();
        }, 3000);
    }

    updateBalancesAfterGame(result) {
        // Update local balance
        if (result.won) {
            this.balances[result.crypto] += result.winAmount - result.amount;
        } else {
            this.balances[result.crypto] -= result.amount;
        }

        // Update UI
        this.populateCryptoOptions();
        this.updateAvailableBalance(document.getElementById('game-crypto').value);

        // Refresh main app balances
        if (window.app && window.app.refreshBalances) {
            window.app.refreshBalances();
        }
    }

    resetGameState() {
        this.isPlaying = false;
        this.selectedChoice = null;
        this.setGameLoading(false);

        // Reset UI
        document.querySelectorAll('.btn-choice').forEach(btn => {
            btn.classList.remove('selected');
        });

        document.getElementById('bet-amount').value = '';
        document.getElementById('bet-amount').style.borderColor = 'var(--border-color)';

        const resultElement = document.getElementById('game-result');
        resultElement.classList.remove('show', 'win', 'lose');
        resultElement.textContent = '';

        this.validateGameSetup();
    }

    setGameLoading(loading) {
        const playButton = document.getElementById('play-game');
        const gameControls = document.querySelectorAll('#game-crypto, #bet-amount, .btn-choice');

        if (loading) {
            playButton.disabled = true;
            playButton.textContent = 'Playing...';
            gameControls.forEach(control => {
                control.disabled = true;
                control.style.opacity = '0.6';
            });
        } else {
            playButton.textContent = 'Play Game';
            gameControls.forEach(control => {
                control.disabled = false;
                control.style.opacity = '1';
            });
            this.validateGameSetup();
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

    playSound(type) {
        // Simple audio feedback using Web Audio API or HTML5 audio
        try {
            const audioContext = new (window.AudioContext || window.webkitAudioContext)();
            const oscillator = audioContext.createOscillator();
            const gainNode = audioContext.createGain();

            oscillator.connect(gainNode);
            gainNode.connect(audioContext.destination);

            if (type === 'win') {
                // Happy ascending notes
                oscillator.frequency.setValueAtTime(523.25, audioContext.currentTime); // C5
                oscillator.frequency.setValueAtTime(659.25, audioContext.currentTime + 0.1); // E5
                oscillator.frequency.setValueAtTime(783.99, audioContext.currentTime + 0.2); // G5
            } else if (type === 'lose') {
                // Sad descending note
                oscillator.frequency.setValueAtTime(523.25, audioContext.currentTime); // C5
                oscillator.frequency.setValueAtTime(415.30, audioContext.currentTime + 0.1); // G#4
            }

            oscillator.type = 'sine';
            gainNode.gain.setValueAtTime(0.1, audioContext.currentTime);
            gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.3);

            oscillator.start(audioContext.currentTime);
            oscillator.stop(audioContext.currentTime + 0.3);
        } catch (error) {
            // Audio not supported or blocked, fail silently
            console.log('Audio feedback not available');
        }
    }

    // Public methods for external access
    refreshBalances() {
        this.loadBalances().then(() => {
            this.populateCryptoOptions();
            this.updateAvailableBalance(document.getElementById('game-crypto').value);
        });
    }

    getCurrentGame() {
        return this.currentGame;
    }

    isGameInProgress() {
        return this.isPlaying;
    }
}

// Initialize gaming manager
document.addEventListener('DOMContentLoaded', () => {
    window.gamingManager = new GamingManager();
});
