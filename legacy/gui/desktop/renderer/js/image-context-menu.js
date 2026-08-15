/**
 * Money Paws Desktop - Image Context Menu
 * This script adds a custom context menu to all images for saving them locally.
 */
document.addEventListener('contextmenu', (event) => {
    const target = event.target;

    if (target.tagName === 'IMG' && target.src) {
        event.preventDefault();
        window.electronAPI.showImageContextMenu(target.src);
    }
});
