document.addEventListener('DOMContentLoaded', function () {
    let selectedCryptoType = '';

    const selectedCryptoInput = document.getElementById('selectedCrypto');
    const playBtn = document.getElementById('playBtn');

    function selectCrypto(crypto) {
        document.querySelectorAll('.crypto-option').forEach(option => {
            option.classList.remove('selected');
        });

        const selectedOption = document.querySelector(`.crypto-option[data-crypto="${crypto}"]`);
        if (selectedOption) {
            selectedOption.classList.add('selected');
        }

        selectedCryptoType = crypto;
        selectedCryptoInput.value = crypto;
        playBtn.disabled = false;
    }

    document.querySelectorAll('.crypto-option').forEach(option => {
        option.addEventListener('click', function() {
            selectCrypto(this.dataset.crypto);
        });
    });

    // Auto-refresh balances every 30 seconds
    setInterval(function() {
        fetch('api/get-balances.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Object.keys(data.balances).forEach(crypto => {
                    const optionElement = document.querySelector(`.crypto-option[data-crypto="${crypto}"]`);
                    if (optionElement) {
                        const balanceElement = optionElement.querySelector('.balance');
                        if (balanceElement) {
                            balanceElement.textContent = `Balance: ${parseFloat(data.balances[crypto]).toFixed(8)}`;
                        }
                    }
                });
            }
        })
        .catch(error => console.error('Error updating balances:', error));
    }, 30000);

    // Load recent winners
    fetch('api/get-recent-winners.php')
    .then(response => response.json())
    .then(data => {
        if (data.success && data.winners.length > 0) {
            const winnersHtml = data.winners.map(winner => `
                <div class="winner-list-item">
                    <div>
                        <strong class="winner-name">${winner.name}</strong>
                        <small class="winner-game">Paw Match</small>
                    </div>
                    <div class="winner-reward-info">
                        <span class="winner-reward">$${winner.reward_usd}</span>
                        <small class="winner-timestamp">${winner.time_ago}</small>
                    </div>
                </div>
            `).join('');
            
            document.getElementById('recentWinners').innerHTML = winnersHtml;
        }
    })
    .catch(error => console.error('Error loading winners:', error));
});
