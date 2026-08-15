<?php
require_once 'includes/functions.php';
require_once 'includes/health.php';

requireLogin();

$user_id = $_SESSION['user_id'];
$pets = getPetsWithHealthAndIllnesses($user_id);

$pageTitle = 'Vet Clinic';
require_once 'includes/html_head.php';
require_once 'includes/header.php';
?>
<main>
    <div class="container">
        <div class="hero hero-padding">
            <h1>⚕️ Vet Clinic</h1>
            <p>Keep your beloved pets healthy and happy.</p>
        </div>

        <div class="card">
            <h2>Your Pets' Health Status</h2>
            <?php if (empty($pets)): ?>
                <p>You don't have any pets to display.</p>
            <?php else: ?>
                <div class="adoption-grid">
                    <?php foreach ($pets as $pet): ?>
                        <div class="adoption-card">
                            <img src="uploads/<?php echo htmlspecialchars($pet['filename']); ?>" alt="<?php echo htmlspecialchars($pet['original_name']); ?>" class="pet-image">
                            <div class="adoption-info">
                                <h3><?php echo htmlspecialchars($pet['original_name']); ?></h3>
                                <div class="pet-stats">
                                    <div class="stat-box">
                                        <div class="stat-label">Health: <?php echo $pet['health_points']; ?>/100</div>
                                    </div>
                                    <div class="stat-box">
                                        <div class="stat-label">Status: <span class="status-<?php echo strtolower($pet['status']); ?>"><?php echo htmlspecialchars(ucfirst($pet['status'])); ?></span></div>
                                    </div>
                                </div>
                                <?php if (!empty($pet['illnesses'])): ?>
                                    <div class="illnesses">
                                        <strong>Illnesses:</strong>
                                        <ul>
                                            <?php foreach ($pet['illnesses'] as $illness): ?>
                                                <li><?php echo htmlspecialchars($illness['name']); ?> (Severity: <?php echo $illness['severity']; ?>)</li>
                                            <?php endforeach; ?>
                                        </ul>
                                        <button class="btn btn-primary btn-block heal-button" data-pet-id="<?php echo $pet['id']; ?>">Heal Pet</button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<div id="healModal" class="modal">
    <div class="modal-content">
        <span class="modal-close">&times;</span>
        <h2>Confirm Treatment</h2>
        <form id="healForm" method="POST" action="/api/heal-pet.php">
            <input type="hidden" name="pet_id" id="modalPetId">
            <p>The total cost to heal your pet is <strong id="treatmentCost"></strong> coins.</p>
            <button type="submit" class="btn btn-primary">Confirm and Pay</button>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('healModal');
    const closeButton = modal.querySelector('.modal-close');
    const healButtons = document.querySelectorAll('.heal-button');

    healButtons.forEach(button => {
        button.addEventListener('click', function() {
            const petId = this.dataset.petId;
            document.getElementById('modalPetId').value = petId;
            
            fetch(`/api/get-treatment-cost.php?pet_id=${petId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('treatmentCost').textContent = data.cost;
                        modal.classList.add('is-visible');
                    }
                });
        });
    });

    closeButton.addEventListener('click', () => modal.classList.remove('is-visible'));
    window.addEventListener('click', (event) => {
        if (event.target == modal) {
            modal.classList.remove('is-visible');
        }
    });
});
</script>
