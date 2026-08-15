document.addEventListener('DOMContentLoaded', function () {
    let currentItem = null;
    let selectedCryptoType = '';

    const purchaseModal = document.getElementById('purchaseModal');
    const modalItemId = document.getElementById('modalItemId');
    const itemDisplay = document.getElementById('itemDisplay');
    const quantityInput = document.getElementById('quantity');
    const totalCostEl = document.getElementById('totalCost');
    const purchaseBtn = document.getElementById('purchaseBtn');
    const selectedCryptoInput = document.getElementById('selectedCrypto');

    function openPurchaseModal(item) {
        currentItem = item;
        purchaseModal.style.display = 'block';
        modalItemId.value = item.id;

        itemDisplay.innerHTML = `
            <div class="modal-item-emoji">${item.emoji}</div>
            <h3>${item.name}</h3>
            <p class="text-muted">${item.description}</p>
            <div class="modal-item-price">$${parseFloat(item.price_usd).toFixed(2)} each</div>
        `;

        updateTotalCost();
    }

    function closePurchaseModal() {
        purchaseModal.style.display = 'none';
        selectedCryptoType = '';
        purchaseBtn.disabled = true;
    }

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
        purchaseBtn.disabled = false;

        updateCryptoAmounts();
    }

    function updateTotalCost() {
        if (!currentItem) return;

        const quantity = parseInt(quantityInput.value) || 1;
        const totalUSD = currentItem.price_usd * quantity;

        totalCostEl.innerHTML = `Total: $${totalUSD.toFixed(2)}`;

        updateCryptoAmounts();
    }

    function updateCryptoAmounts() {
        if (!currentItem || typeof SUPPORTED_CRYPTOS === 'undefined') return;

        const quantity = parseInt(quantityInput.value) || 1;
        const totalUSD = currentItem.price_usd * quantity;

        SUPPORTED_CRYPTOS.forEach(crypto => {
            fetch(`api/get-crypto-price.php?crypto=${crypto}&usd=${totalUSD}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const el = document.getElementById(`crypto-amount-${crypto}`);
                        if (el) {
                            el.textContent = data.crypto_amount.toFixed(8);
                        }
                    }
                })
                .catch(error => console.error('Error:', error));
        });
    }

    // Event Listeners
    document.querySelectorAll('.purchase-btn').forEach(button => {
        button.addEventListener('click', function() {
            const itemData = JSON.parse(this.dataset.item);
            openPurchaseModal(itemData);
        });
    });

    document.getElementById('closeModalBtn').addEventListener('click', closePurchaseModal);

    quantityInput.addEventListener('input', updateTotalCost);

    document.querySelectorAll('.crypto-option').forEach(option => {
        option.addEventListener('click', function() {
            selectCrypto(this.dataset.crypto);
        });
    });

    window.addEventListener('click', function(event) {
        if (event.target == purchaseModal) {
            closePurchaseModal();
        }
    });
});
