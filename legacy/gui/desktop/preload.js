/**
 * Money Paws Desktop - Preload Script
 * Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>
 */

const { contextBridge, ipcRenderer } = require('electron');

// Expose protected methods that allow the renderer process to use
// the ipcRenderer without exposing the entire object
contextBridge.exposeInMainWorld('electronAPI', {
    // App info
    getAppVersion: () => ipcRenderer.invoke('get-app-version'),
    isDev: () => ipcRenderer.invoke('is-dev'),
    
    // Store operations
    getStoreValue: (key) => ipcRenderer.invoke('get-store-value', key),
    setStoreValue: (key, value) => ipcRenderer.invoke('set-store-value', key, value),
    
    // API requests
    apiRequest: (method, endpoint, data, headers) => 
        ipcRenderer.invoke('api-request', method, endpoint, data, headers),
    
    // Notifications
    showNotification: (options) => ipcRenderer.invoke('show-notification', options),

    // Dialog operations
    showMessageBox: (options) => ipcRenderer.invoke('show-message-box', options),
    showSaveDialog: (options) => ipcRenderer.invoke('show-save-dialog', options),
    showOpenDialog: (options) => ipcRenderer.invoke('show-open-dialog', options),
    
    // Event listeners
    showImageContextMenu: (imageUrl) => ipcRenderer.send('show-image-context-menu', imageUrl),
    onShowSettings: (callback) => ipcRenderer.on('show-settings', callback),
    
    // Remove listeners
    removeAllListeners: (channel) => {
        ipcRenderer.removeAllListeners(channel);
    }
});

// Platform detection
contextBridge.exposeInMainWorld('platform', {
    isMac: process.platform === 'darwin',
    isWindows: process.platform === 'win32',
    isLinux: process.platform === 'linux'
});

// Security: Remove any node globals in the renderer
delete window.require;
delete window.exports;
delete window.module;
