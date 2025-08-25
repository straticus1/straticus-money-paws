document.addEventListener('DOMContentLoaded', () => {
    // Pet management functions
    function editPet(petId) {
        window.location.href = 'edit-pet.php?id=' + petId;
    }

    function deletePet(petId) {
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        if (confirm('Are you sure you want to delete this pet? This action cannot be undone.')) {
            fetch('api/delete-pet.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ pet_id: petId, csrf_token: csrfToken })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to delete pet');
            });
        }
    }

    document.querySelectorAll('.edit-pet-btn').forEach(button => {
        button.addEventListener('click', function() {
            editPet(this.dataset.petId);
        });
    });

    document.querySelectorAll('.delete-pet-btn').forEach(button => {
        button.addEventListener('click', function() {
            deletePet(this.dataset.petId);
        });
    });

    // Wallet connection functions
    const ethButton = document.getElementById('connectEthWallet');
    const solButton = document.getElementById('connectSolWallet');
    const ethStatus = document.getElementById('ethWalletStatus');
    const solStatus = document.getElementById('solWalletStatus');

    if (ethButton && solButton) {
        async function connectEth() {
            if (typeof window.ethereum === 'undefined') {
                ethStatus.innerHTML = 'MetaMask not detected. <a href="https://metamask.io/" target="_blank">Install MetaMask</a>';
                return;
            }
            try {
                ethButton.textContent = 'Connecting...';
                ethButton.disabled = true;
                const accounts = await window.ethereum.request({ method: 'eth_requestAccounts' });
                const wallet = accounts[0];
                ethButton.textContent = 'Connected ✓';
                ethButton.classList.add('btn-success');
                ethStatus.innerHTML = `Connected: ${wallet.substring(0, 6)}...${wallet.substring(38)}`;
                localStorage.setItem('ethWallet', wallet);
            } catch (error) {
                console.error('ETH connect error:', error);
                ethButton.textContent = 'Connection Failed';
                ethButton.disabled = false;
                ethStatus.textContent = 'Failed to connect.';
            }
        }

        async function connectSol() {
            const { solana } = window;
            if (!solana || !solana.isPhantom) {
                solStatus.innerHTML = 'Phantom not detected. <a href="https://phantom.app/" target="_blank">Install Phantom</a>';
                return;
            }
            try {
                solButton.textContent = 'Connecting...';
                solButton.disabled = true;
                const response = await solana.connect();
                const wallet = response.publicKey.toString();
                solButton.textContent = 'Connected ✓';
                solButton.classList.add('btn-success');
                solStatus.innerHTML = `Connected: ${wallet.substring(0, 6)}...${wallet.substring(38)}`;
                localStorage.setItem('solWallet', wallet);
            } catch (error) {
                console.error('SOL connect error:', error);
                solButton.textContent = 'Connection Failed';
                solButton.disabled = false;
                solStatus.textContent = 'Failed to connect.';
            }
        }

        function checkConnections() {
            const storedEth = localStorage.getItem('ethWallet');
            if (storedEth) {
                ethButton.textContent = 'Connected ✓';
                ethButton.classList.add('btn-success');
                ethStatus.innerHTML = `Connected: ${storedEth.substring(0, 6)}...${storedEth.substring(38)}`;
            }

            const storedSol = localStorage.getItem('solWallet');
            if (storedSol) {
                solButton.textContent = 'Connected ✓';
                solButton.classList.add('btn-success');
                solStatus.innerHTML = `Connected: ${storedSol.substring(0, 6)}...${storedSol.substring(38)}`;
            }
        }

        ethButton.addEventListener('click', connectEth);
        solButton.addEventListener('click', connectSol);
        checkConnections();
    }
});
