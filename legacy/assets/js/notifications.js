document.addEventListener('DOMContentLoaded', () => {
    const notificationContainer = document.getElementById('notification-toast-container');
    if (!notificationContainer) return;

    function showToast(message, link) {
        const toast = document.createElement('div');
        toast.className = 'notification-toast';
        
        const toastLink = document.createElement('a');
        toastLink.href = link;
        toastLink.textContent = message;
        toast.appendChild(toastLink);

        notificationContainer.appendChild(toast);

        setTimeout(() => {
            toast.classList.add('show');
        }, 100);

        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => {
                toast.remove();
            }, 500);
        }, 5000);
    }

    function updateNotificationCount(count) {
        const countElement = document.querySelector('.notification-count');
        if (countElement) {
            countElement.textContent = count;
            countElement.style.display = count > 0 ? 'inline-block' : 'none';
        } else if (count > 0) {
            const link = document.querySelector('.notifications-link');
            if (link) {
                const newCountElement = document.createElement('span');
                newCountElement.className = 'notification-count';
                newCountElement.textContent = count;
                link.appendChild(newCountElement);
            }
        }
    }

    async function fetchNotificationCount() {
        try {
            const response = await fetch('/api/get-unread-notification-count.php');
            if (response.ok) {
                const data = await response.json();
                if (data.success) {
                    updateNotificationCount(data.count);
                }
            }
        } catch (error) {
            console.error('Error fetching notification count:', error);
        }
    }

    async function pollForNotifications() {
        try {
            const response = await fetch('/api/get-notifications.php');
            if (response.ok) {
                const data = await response.json();
                if (data.success && data.notifications.length > 0) {
                    data.notifications.forEach(notif => {
                        showToast(notif.message, notif.link);
                    });
                    // After showing new notifications, refresh the total count from the server
                    fetchNotificationCount();
                }
            }
        } catch (error) {
            console.error('Notification poll error:', error);
            // Wait longer before retrying if there was an error
            await new Promise(resolve => setTimeout(resolve, 5000));
        }
        
        // Immediately start the next poll
        pollForNotifications();
    }

    // Initial setup
    pollForNotifications();
    fetchNotificationCount(); // Get initial count on page load
    setInterval(fetchNotificationCount, 30000); // Periodically refresh count every 30 seconds
});
