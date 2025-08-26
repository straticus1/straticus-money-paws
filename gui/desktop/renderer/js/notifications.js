/**
 * Money Paws Desktop - Notification Manager
 * This script handles triggering native desktop notifications for in-game events.
 */

const CHECK_INTERVAL = 60000; // 60 seconds

async function areNotificationsEnabled() {
    const settings = await window.electronAPI.getStoreValue('desktopSettings');
    // Default to true if not set
    return settings ? settings.notificationsEnabled !== false : true;
}

async function checkUnreadMessages() {
    if (!await areNotificationsEnabled()) return;

    try {
        const response = await window.electronAPI.apiRequest('GET', '/api/get-unread-message-count.php');
        if (response.success && response.data.unread_count > 0) {
            window.electronAPI.showNotification({
                title: 'New Pet Messages',
                body: `You have ${response.data.unread_count} unread messages from your pets.`
            });
        }
    } catch (error) {
        console.error('Failed to check for unread messages:', error);
    }
}

async function checkLowHealthPets() {
    if (!await areNotificationsEnabled()) return;

    try {
        const response = await window.electronAPI.apiRequest('GET', '/api/get-low-health-pets.php');
        if (response.success && response.data.length > 0) {
            const petNames = response.data.map(p => p.name).join(', ');
            window.electronAPI.showNotification({
                title: 'Low Pet Health',
                body: `Your pets are getting hungry: ${petNames}.`
            });
        }
    } catch (error) {
        console.error('Failed to check for low health pets:', error);
    }
}

function startNotificationChecks() {
    checkUnreadMessages();
    checkLowHealthPets();

    setInterval(() => {
        checkUnreadMessages();
        checkLowHealthPets();
    }, CHECK_INTERVAL);
}

document.addEventListener('DOMContentLoaded', () => {
    // Delay start to ensure API client is ready
    setTimeout(startNotificationChecks, 5000);
});
