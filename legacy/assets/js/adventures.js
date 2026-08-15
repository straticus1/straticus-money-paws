document.addEventListener('DOMContentLoaded', function() {
    const petSelectionList = document.getElementById('pet-selection-list');
    const questListContainer = document.getElementById('quest-list-container');
    const questListHeader = document.getElementById('quest-list-header');
    const notificationsContainer = document.getElementById('adventure-notifications');
    const questModal = new bootstrap.Modal(document.getElementById('questModal'));
    const questModalBody = document.getElementById('questModalBody');
    const startAdventureBtn = document.getElementById('start-adventure-btn');

    let selectedPetId = null;
    let selectedQuestId = null;

    // --- Initial Load Functions ---

    /**
     * Checks for any completed adventures when the page loads.
     */
    function checkCompletedAdventures() {
        fetch('api/check-adventures.php')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.report.completed_count > 0) {
                    displayCompletionReport(data.report);
                }
                // After checking, update the status of all pets.
                updateAllPetStatuses();
            })
            .catch(error => console.error('Error checking adventures:', error));
    }

    /**
     * Fetches and displays the current adventure status for all pets.
     */
    function updateAllPetStatuses() {
        // This is a simplified approach. A real app might have a dedicated endpoint.
        // For now, we assume pets with active adventures are stored or can be fetched.
        // This function would ideally get a list of all pets on adventures for the user.
    }

    /**
     * Displays a summary of completed adventures and rewards.
     */
    function displayCompletionReport(report) {
        let itemsList = report.items.length > 0 ? `<li>Found Items: ${report.items.join(', ')}</li>` : '';
        const reportHTML = `
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <h4 class="alert-heading">Adventures Complete!</h4>
                <p>We found and completed ${report.completed_count} adventure(s) for you.</p>
                <hr>
                <ul>
                    <li>Total Experience Gained: ${report.total_exp}</li>
                    <li>Pets Leveled Up: ${report.level_ups}</li>
                    ${itemsList}
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `;
        notificationsContainer.innerHTML = reportHTML;
    }

    // --- Event Handlers ---

    petSelectionList.addEventListener('click', function(e) {
        e.preventDefault();
        const petLink = e.target.closest('.list-group-item-action');
        if (petLink) {
            selectedPetId = petLink.dataset.petId;
            // Highlight selected pet
            document.querySelectorAll('#pet-selection-list .list-group-item-action').forEach(link => {
                link.classList.remove('active');
            });
            petLink.classList.add('active');

            loadQuestsForPet(selectedPetId);
        }
    });

    startAdventureBtn.addEventListener('click', function() {
        if (selectedPetId && selectedQuestId) {
            fetch('api/start-adventure.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ pet_id: selectedPetId, quest_id: selectedQuestId })
            })
            .then(response => response.json())
            .then(data => {
                questModal.hide();
                if (data.success) {
                    alert('Adventure started successfully!');
                    // Reset the view
                    questListContainer.innerHTML = '<p class=\"text-muted\">Please select a pet from the list on the left.</p>';
                    questListHeader.textContent = 'Select a Pet to View Quests';
                    document.querySelector(`[data-pet-id='${selectedPetId}']`).classList.remove('active');
                } else {
                    alert(`Error: ${data.message}`);
                }
            })
            .catch(error => console.error('Error starting adventure:', error));
        }
    });

    // --- UI Update Functions ---

    /**
     * Loads and displays available quests for the selected pet.
     */
    function loadQuestsForPet(petId) {
        questListHeader.textContent = 'Loading Quests...';
        questListContainer.innerHTML = '<div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div>';

        fetch(`api/get-adventure-quests.php?pet_id=${petId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const petName = document.querySelector(`[data-pet-id='${petId}'] strong`).textContent;
                    questListHeader.textContent = `Available Quests for ${petName}`;
                    renderQuestList(data.quests);
                } else {
                    questListContainer.innerHTML = `<p class="text-danger">Error: ${data.message}</p>`;
                }
            })
            .catch(error => {
                console.error('Error fetching quests:', error);
                questListContainer.innerHTML = '<p class="text-danger">Could not load quests.</p>';
            });
    }

    /**
     * Renders the list of quests into the container.
     */
    function renderQuestList(quests) {
        if (quests.length === 0) {
            questListContainer.innerHTML = '<p class="text-muted">No quests available for this pet\'s level.</p>';
            return;
        }

        let questsHTML = '<div class="list-group">';
        quests.forEach(quest => {
            questsHTML += `
                <a href="#" class="list-group-item list-group-item-action quest-item" data-quest-id="${quest.id}">
                    <h5>${quest.name}</h5>
                    <p>${quest.description}</p>
                    <small>Duration: ${quest.duration_minutes} mins | Level Req: ${quest.min_level}</small>
                </a>
            `;
        });
        questsHTML += '</div>';
        questListContainer.innerHTML = questsHTML;

        // Add event listeners to new quest items
        document.querySelectorAll('.quest-item').forEach(item => {
            item.addEventListener('click', function(e) {
                e.preventDefault();
                selectedQuestId = this.dataset.questId;
                const questData = quests.find(q => q.id == selectedQuestId);
                showQuestModal(questData);
            });
        });
    }

    /**
     * Shows the quest details in a modal.
     */
    function showQuestModal(quest) {
        document.getElementById('questModalLabel').textContent = quest.name;
        questModalBody.innerHTML = `
            <p>${quest.description}</p>
            <ul>
                <li><strong>Minimum Level:</strong> ${quest.min_level}</li>
                <li><strong>Duration:</strong> ${quest.duration_minutes} minutes</li>
                <li><strong>Energy Cost:</strong> ${quest.energy_cost}</li>
                <li><strong>Experience Reward:</strong> ${quest.experience_reward}</li>
            </ul>
            <p class="text-muted">Rewards are calculated upon completion. There is a chance to find rare items!</p>
        `;
        questModal.show();
    }

    // --- Initializer ---
    checkCompletedAdventures();
});
