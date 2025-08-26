document.addEventListener('DOMContentLoaded', function() {
    // Set the default tab
    document.querySelector('.tab-link').click();
    loadQuests();
});

function openTab(evt, tabName) {
    let i, tabcontent, tablinks;
    tabcontent = document.getElementsByClassName("tab-content");
    for (i = 0; i < tabcontent.length; i++) {
        tabcontent[i].style.display = "none";
        tabcontent[i].classList.remove('active');
    }
    tablinks = document.getElementsByClassName("tab-link");
    for (i = 0; i < tablinks.length; i++) {
        tablinks[i].className = tablinks[i].className.replace(" active", "");
    }
    document.getElementById(tabName).style.display = "block";
    document.getElementById(tabName).classList.add('active');
    evt.currentTarget.className += " active";

    if (tabName === 'Quests') {
        loadQuests();
    } else if (tabName === 'Achievements') {
        loadAchievements();
    }
}

async function loadQuests() {
    const questList = document.getElementById('quest-list');
    await loadData(questList, '/api/get-user-quests.php', 'quest');
}

async function loadAchievements() {
    const achievementList = document.getElementById('achievement-list');
    await loadData(achievementList, '/api/get-user-achievements.php', 'achievement');
}

async function loadData(container, apiUrl, type) {
    if (!container) return;
    container.innerHTML = '<div class="loader"></div>';

    try {
        const response = await fetch(apiUrl);
        if (!response.ok) {
            throw new Error(`Failed to fetch ${type}s.`);
        }
        const items = await response.json();

        container.innerHTML = ''; // Clear loader

        if (items.length === 0) {
            container.innerHTML = `<p>No active ${type}s right now.</p>`;
            return;
        }

        items.forEach(item => {
            const progressPercent = item.goal_value > 0 ? Math.min(100, (item.progress / item.goal_value) * 100) : 0;
            const name = item.quest_name || item.achievement_name;
            const description = item.quest_description || item.achievement_description;
            const userItemId = item.user_quest_id || item.user_achievement_id;

            const itemCard = `
                <div class="quest-card status-${item.status}">
                    <h3>${name}</h3>
                    <p class="quest-description">${description}</p>
                    <div class="progress-bar-container">
                        <div class="progress-bar" style="width: ${progressPercent}%;"></div>
                    </div>
                    <p class="progress-text">${item.progress} / ${item.goal_value}</p>
                    <div class="reward">
                        Reward: ${item.reward_amount} ${item.reward_currency}
                    </div>
                    ${item.status === 'completed' ? `<button class="btn btn-primary claim-reward-btn" data-id="${userItemId}" data-type="${type}">Claim</button>` : ''}
                </div>
            `;
            container.insertAdjacentHTML('beforeend', itemCard);
        });

    } catch (error) {
        container.innerHTML = `<p class="text-error">Could not load ${type}s. Please try again later.</p>`;
        console.error(error);
    }

    addClaimButtonListeners();
}

function addClaimButtonListeners() {
    const claimButtons = document.querySelectorAll('.claim-reward-btn');
    claimButtons.forEach(button => {
        // Remove existing listener to prevent duplicates
        const newButton = button.cloneNode(true);
        button.parentNode.replaceChild(newButton, button);

        newButton.addEventListener('click', async (event) => {
            const itemId = event.target.dataset.id;
            const itemType = event.target.dataset.type;
            try {
                const apiUrl = itemType === 'quest' ? '/api/claim-quest-reward.php' : '/api/claim-achievement-reward.php';
                const body = itemType === 'quest' ? { user_quest_id: itemId } : { user_achievement_id: itemId };

                const response = await fetch(apiUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(body)
                });

                const result = await response.json();

                if (result.success) {
                    event.target.textContent = 'Claimed!';
                    event.target.disabled = true;
                    event.target.closest('.quest-card').classList.remove('status-completed');
                    event.target.closest('.quest-card').classList.add('status-claimed');
                } else {
                    alert('Failed to claim reward: ' + result.message);
                }
            } catch (error) {
                console.error('Error claiming reward:', error);
                alert('An error occurred while claiming the reward.');
            }
        });
    });
}
