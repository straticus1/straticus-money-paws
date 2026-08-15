document.addEventListener('DOMContentLoaded', function () {
    let selectedCryptoType = '';

    const usdAmountInput = document.getElementById('usd_amount');
    const selectedCryptoInput = document.getElementById('selectedCrypto');
    const depositBtn = document.getElementById('depositBtn');

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
        depositBtn.disabled = false;

        updateCryptoAmount();
    }

    function updateCryptoAmount() {
        const usdAmount = parseFloat(usdAmountInput.value) || 0;

        if (usdAmount > 0 && selectedCryptoType) {
            fetch(`api/get-crypto-price.php?crypto=${selectedCryptoType}&usd=${usdAmount}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const el = document.getElementById(`crypto-amount-${selectedCryptoType}`);
                        if (el) {
                            el.textContent = `≈ ${data.crypto_amount.toFixed(8)} ${selectedCryptoType}`;
                        }
                    }
                })
                .catch(error => console.error('Error calculating crypto amount:', error));
        }
    }

    usdAmountInput.addEventListener('input', updateCryptoAmount);

    document.querySelectorAll('.crypto-option').forEach(option => {
        option.addEventListener('click', function() {
            selectCrypto(this.dataset.crypto);
        });
    });
});
