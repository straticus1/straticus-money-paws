<?php
/**
 * Money Paws - Pet Adoption Facility
 * Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>
 */
require_once 'includes/functions.php';
require_once 'includes/pet_care.php';

requireLogin();

$currentUser = getUserById($_SESSION['user_id']);
$error = '';
$success = '';

// Handle adoption or purchase
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adopt_pet'])) {
    requireCSRFToken();
    $petId = intval($_POST['pet_id']);
    $fee = floatval($_POST['adoption_fee']);
    $cryptoType = sanitizeInput($_POST['crypto_type']);
    $listingType = sanitizeInput($_POST['listing_type']);
    
    if (!array_key_exists($cryptoType, SUPPORTED_CRYPTOS)) {
        $error = 'Invalid cryptocurrency selected.';
    } else {
        if ($listingType === 'sale') {
            $result = buyPet($_SESSION['user_id'], $petId, $fee, $cryptoType);
        } else {
            $result = adoptPet($_SESSION['user_id'], $petId, $fee, $cryptoType);
        }
        
        if ($result['success']) {
            $success = $result['message'];
        } else {
            $error = $result['message'];
        }
    }
}

// Get available pets for adoption
$adoptablePets = getAdoptablePets();

$pageTitle = 'Pet Adoption';
require_once 'includes/html_head.php';
?>
<?php
require_once 'includes/header.php';
?>
<main>
        <div class="container">
            <div class="hero hero-padding">
                <h1>🏠 Pet Adoption Center</h1>
                <p>Give a loving home to pets in need of families</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <div class="card">
                <h2>🌟 Available for Adoption</h2>
                <p>These pets are looking for their forever homes. Adoption fees help support our rescue operations.</p>
                
                <?php if (empty($adoptablePets)): ?>
                    <div class="no-pets-container">
                        <div class="no-pets-icon">🐕‍🦺</div>
                        <h3>No Pets Available</h3>
                        <p>All our pets have found loving homes! Check back soon for new arrivals.</p>
                        <a href="gallery.php" class="btn btn-primary">View Pet Gallery</a>
                    </div>
                <?php else: ?>
                    <div class="adoption-grid">
                        <?php foreach ($adoptablePets as $pet): ?>
                            <div class="adoption-card">
                                <?php if ($pet['listing_type'] === 'sale'): ?>
                                    <div class="adoption-badge for-sale">For Sale</div>
                                <?php else: ?>
                                    <div class="adoption-badge">For Adoption</div>
                                <?php endif; ?>

                                <img src="uploads/<?php echo htmlspecialchars($pet['filename']); ?>" 
                                     alt="<?php echo htmlspecialchars($pet['original_name']); ?>" 
                                     class="pet-image">
                                
                                <div class="adoption-info">
                                    <h3><?php echo htmlspecialchars($pet['original_name']); ?></h3>

                                    <?php if ($pet['listing_type'] === 'sale'): ?>
                                        <p class="pet-seller-info">
                                            Sold by: <strong><?php echo htmlspecialchars($pet['owner_name']); ?></strong>
                                        </p>
                                    <?php endif; ?>

                                    <p class="pet-description">
                                        <?php echo htmlspecialchars($pet['description'] ?: 'A wonderful pet looking for a loving home.'); ?>
                                    </p>
                                    
                                    <div class="pet-stats">
                                        <div class="stat-box">
                                            <div class="stat-icon"><?php echo getPetHungerStatus($pet['hunger_level'] ?? 75)['emoji']; ?></div>
                                            <div class="stat-label">Hunger: <?php echo $pet['hunger_level'] ?? 75; ?>%</div>
                                        </div>
                                        <div class="stat-box">
                                            <div class="stat-icon"><?php echo getPetHappinessStatus($pet['happiness_level'] ?? 85)['emoji']; ?></div>
                                            <div class="stat-label">Happiness: <?php echo $pet['happiness_level'] ?? 85; ?>%</div>
                                        </div>
                                    </div>
                                    
                                    <div class="adoption-fee">
                                        <?php if ($pet['listing_type'] === 'sale'): ?>
                                            Sale Price: $<?php echo number_format($pet['adoption_fee'], 2); ?>
                                        <?php else: ?>
                                            Adoption Fee: $<?php echo number_format($pet['adoption_fee'], 2); ?>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <button data-pet='<?php echo htmlspecialchars(json_encode($pet)); ?>' 
                                        class="btn btn-primary btn-block adopt-button">
                                        <?php if ($pet['listing_type'] === 'sale'): ?>
                                            💰 Buy Pet
                                        <?php else: ?>
                                            💝 Adopt Me
                                        <?php endif; ?>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Adoption Modal -->
    <div id="adoptionModal" class="modal">
        <div class="modal-content">
            <span class="modal-close">&times;</span>
            
            <div id="modalContent">
                <h2 id="modalTitle">Adopt This Pet</h2>
                                <form method="POST" id="adoptionForm">
                    <?php echo getCSRFTokenField(); ?>
                    <input type="hidden" name="pet_id" id="modalPetId">
                    <input type="hidden" name="adoption_fee" id="modalAdoptionFee">
                    <input type="hidden" name="listing_type" id="modalListingType">
                    
                    <div id="petDisplay" class="modal-pet-display">
                        <!-- Pet details will be populated here -->
                    </div>
                    
                    <div class="form-group">
                        <label>Payment Method</label>
                        <div class="crypto-options-grid">
                            <?php foreach (SUPPORTED_CRYPTOS as $crypto => $name): ?>
                                <div class="crypto-option" data-crypto="<?php echo $crypto; ?>">
                                    <div class="crypto-name"><?php echo $crypto; ?></div>
                                    <div class="crypto-amount" id="crypto-amount-<?php echo $crypto; ?>">-</div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <input type="hidden" name="crypto_type" id="selectedCrypto" value="">
                    </div>
                    
                    <div class="agreement-box">
                        <h4 id="agreementTitle">Adoption Agreement</h4>
                        <p id="agreementText" class="agreement-text">By adopting this pet, you agree to:</p>
                        <ul class="agreement-list">
                            <li>Provide a loving, safe home</li>
                            <li>Regular feeding and care</li>
                            <li>Veterinary care when needed</li>
                            <li>Never abandon or neglect this pet</li>
                        </ul>
                        <label class="agreement-checkbox-label">
                            <input type="checkbox" id="agreeTerms" class="agreement-checkbox">
                            <span class="agreement-checkbox-text">I agree to these terms</span>
                        </label>
                    </div>
                    
                                        <button type="submit" name="adopt_pet" class="btn btn-primary btn-block" disabled id="adoptBtn">
                        Complete Adoption
                    </button>
                </form>
            </div>
        </div>
    </div>

<?php require_once 'includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('adoptionModal');
    const adoptButtons = document.querySelectorAll('.adopt-button');
    const closeButton = document.querySelector('.modal-close');
    const cryptoOptions = document.querySelectorAll('.crypto-option');
    const agreeCheckbox = document.getElementById('agreeTerms');
    const adoptBtn = document.getElementById('adoptBtn');

    let currentPet = null;
    let selectedCryptoType = '';

    function updateCryptoAmounts() {
        if (!currentPet) return;
        const totalUSD = currentPet.adoption_fee;
        <?php foreach (SUPPORTED_CRYPTOS as $crypto => $name): ?>
            fetch(`/api/get-crypto-price.php?crypto=<?php echo $crypto; ?>&usd=${totalUSD}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const el = document.getElementById('crypto-amount-<?php echo $crypto; ?>');
                    if (el) el.textContent = data.crypto_amount.toFixed(8);
                }
            })
            .catch(error => console.error('Error fetching crypto price:', error));
        <?php endforeach; ?>
    }

    function checkCanAdopt() {
        const agreeTerms = agreeCheckbox.checked;
        const hasCrypto = selectedCryptoType !== '';
        adoptBtn.disabled = !(agreeTerms && hasCrypto);
    }

    function openAdoptionModal(pet) {
        currentPet = pet;
        modal.classList.add('is-visible');
        document.getElementById('modalPetId').value = pet.id;
        document.getElementById('modalAdoptionFee').value = pet.adoption_fee;
        document.getElementById('modalListingType').value = pet.listing_type;

        const isSale = pet.listing_type === 'sale';
        document.getElementById('modalTitle').textContent = isSale ? 'Buy This Pet' : 'Adopt This Pet';
        adoptBtn.textContent = isSale ? 'Complete Purchase' : 'Complete Adoption';
        document.getElementById('agreementTitle').textContent = isSale ? 'Purchase Agreement' : 'Adoption Agreement';
        document.getElementById('agreementText').textContent = isSale ? 'By purchasing this pet, you agree to the terms.' : 'By adopting this pet, you agree to:';

        document.getElementById('petDisplay').innerHTML = `
            <img src="uploads/${pet.filename}" class="modal-pet-image">
            <h3>${pet.original_name}</h3>
            <p>${pet.description || 'A wonderful pet looking for a loving home.'}</p>
            <div class="modal-pet-fee">${isSale ? 'Sale Price' : 'Adoption Fee'}: $${parseFloat(pet.adoption_fee).toFixed(2)}</div>
        `;
        
        updateCryptoAmounts();
        checkCanAdopt();
    }

    function closeAdoptionModal() {
        modal.classList.remove('is-visible');
        selectedCryptoType = '';
        agreeCheckbox.checked = false;
        document.querySelectorAll('.crypto-option.selected').forEach(o => o.classList.remove('selected'));
        checkCanAdopt();
    }

    adoptButtons.forEach(button => {
        button.addEventListener('click', function() {
            const petData = JSON.parse(this.dataset.pet);
            openAdoptionModal(petData);
        });
    });

    if(closeButton) {
        closeButton.addEventListener('click', closeAdoptionModal);
    }

    cryptoOptions.forEach(option => {
        option.addEventListener('click', function() {
            cryptoOptions.forEach(o => o.classList.remove('selected'));
            this.classList.add('selected');
            selectedCryptoType = this.dataset.crypto;
            document.getElementById('selectedCrypto').value = selectedCryptoType;
            checkCanAdopt();
        });
    });

    if(agreeCheckbox) {
        agreeCheckbox.addEventListener('change', checkCanAdopt);
    }

    window.addEventListener('click', function(event) {
        if (event.target == modal) {
            closeAdoptionModal();
        }
    });
});
</script>

<?php require_once 'includes/scripts.php'; ?>
