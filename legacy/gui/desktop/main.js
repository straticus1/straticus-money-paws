/**
 * Money Paws Desktop - Main Process
 * Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>
 */

const { app, BrowserWindow, Menu, ipcMain, shell, dialog, Notification } = require('electron');
const { autoUpdater } = require('electron-updater');
const Store = require('electron-store');
const path = require('path');
const fs = require('fs');
const axios = require('axios');

// Initialize store for app settings
const store = new Store();

// Keep a global reference of the window object
let mainWindow;
let splashWindow;

// App configuration
const isDev = process.argv.includes('--dev');
const API_BASE_URL = isDev ? 'http://localhost' : 'https://your-domain.com';

function createSplashWindow() {
    splashWindow = new BrowserWindow({
        width: 400,
        height: 300,
        frame: false,
        alwaysOnTop: true,
        transparent: true,
        webPreferences: {
            nodeIntegration: false,
            contextIsolation: true
        }
    });

    splashWindow.loadFile('renderer/splash.html');

    splashWindow.on('closed', () => {
        splashWindow = null;
    });
}

function createMainWindow() {
    // Get saved window bounds or use defaults
    const windowBounds = store.get('windowBounds', {
        width: 1200,
        height: 800,
        x: undefined,
        y: undefined
    });

    mainWindow = new BrowserWindow({
        ...windowBounds,
        minWidth: 800,
        minHeight: 600,
        show: false,
        icon: path.join(__dirname, 'assets/icon.png'),
        webPreferences: {
            nodeIntegration: false,
            contextIsolation: true,
            enableRemoteModule: false,
            preload: path.join(__dirname, 'preload.js')
        }
    });

    // Load the app
    mainWindow.loadFile('renderer/index.html');

    // Show window when ready
    mainWindow.once('ready-to-show', () => {
        if (splashWindow) {
            splashWindow.close();
        }
        mainWindow.show();

        // Focus on the window
        if (isDev) {
            mainWindow.webContents.openDevTools();
        }
    });

    // Save window bounds on resize/move
    mainWindow.on('resize', saveWindowBounds);
    mainWindow.on('move', saveWindowBounds);

    // Handle window closed
    mainWindow.on('closed', () => {
        mainWindow = null;
    });

    // Handle external links
    mainWindow.webContents.setWindowOpenHandler(({ url }) => {
        shell.openExternal(url);
        return { action: 'deny' };
    });

    // Prevent navigation to external sites
    mainWindow.webContents.on('will-navigate', (event, navigationUrl) => {
        const parsedUrl = new URL(navigationUrl);
        
        if (parsedUrl.origin !== 'file://') {
            event.preventDefault();
        }
    });
}

function saveWindowBounds() {
    if (mainWindow) {
        store.set('windowBounds', mainWindow.getBounds());
    }
}

function createMenu() {
    const template = [
        {
            label: 'Money Paws',
            submenu: [
                {
                    label: 'About Money Paws',
                    click: () => {
                        dialog.showMessageBox(mainWindow, {
                            type: 'info',
                            title: 'About Money Paws',
                            message: 'Money Paws Desktop v1.0.0',
                            detail: 'Cryptocurrency-powered pet platform\nDeveloped and Designed by Ryan Coleman\ncoleman.ryan@gmail.com'
                        });
                    }
                },
                { type: 'separator' },
                {
                    label: 'Preferences...',
                    accelerator: 'CmdOrCtrl+,',
                    click: () => {
                        mainWindow.webContents.send('show-settings');
                    }
                },
                { type: 'separator' },
                {
                    label: 'Quit',
                    accelerator: process.platform === 'darwin' ? 'Cmd+Q' : 'Ctrl+Q',
                    click: () => {
                        app.quit();
                    }
                }
            ]
        },
        {
            label: 'Edit',
            submenu: [
                { label: 'Undo', accelerator: 'CmdOrCtrl+Z', role: 'undo' },
                { label: 'Redo', accelerator: 'Shift+CmdOrCtrl+Z', role: 'redo' },
                { type: 'separator' },
                { label: 'Cut', accelerator: 'CmdOrCtrl+X', role: 'cut' },
                { label: 'Copy', accelerator: 'CmdOrCtrl+C', role: 'copy' },
                { label: 'Paste', accelerator: 'CmdOrCtrl+V', role: 'paste' },
                { label: 'Select All', accelerator: 'CmdOrCtrl+A', role: 'selectall' }
            ]
        },
        {
            label: 'View',
            submenu: [
                {
                    label: 'Reload',
                    accelerator: 'CmdOrCtrl+R',
                    click: () => {
                        mainWindow.webContents.reload();
                    }
                },
                {
                    label: 'Force Reload',
                    accelerator: 'CmdOrCtrl+Shift+R',
                    click: () => {
                        mainWindow.webContents.reloadIgnoringCache();
                    }
                },
                {
                    label: 'Toggle Developer Tools',
                    accelerator: process.platform === 'darwin' ? 'Alt+Cmd+I' : 'Ctrl+Shift+I',
                    click: () => {
                        mainWindow.webContents.toggleDevTools();
                    }
                },
                { type: 'separator' },
                { label: 'Actual Size', accelerator: 'CmdOrCtrl+0', role: 'resetzoom' },
                { label: 'Zoom In', accelerator: 'CmdOrCtrl+Plus', role: 'zoomin' },
                { label: 'Zoom Out', accelerator: 'CmdOrCtrl+-', role: 'zoomout' },
                { type: 'separator' },
                { label: 'Toggle Fullscreen', accelerator: 'F11', role: 'togglefullscreen' }
            ]
        },
        {
            label: 'Window',
            submenu: [
                { label: 'Minimize', accelerator: 'CmdOrCtrl+M', role: 'minimize' },
                { label: 'Close', accelerator: 'CmdOrCtrl+W', role: 'close' }
            ]
        },
        {
            label: 'Help',
            submenu: [
                {
                    label: 'Learn More',
                    click: () => {
                        shell.openExternal('https://github.com/ryancoleman/money-paws');
                    }
                },
                {
                    label: 'Report Issue',
                    click: () => {
                        shell.openExternal('https://github.com/ryancoleman/money-paws/issues');
                    }
                },
                { type: 'separator' },
                {
                    label: 'Check for Updates',
                    click: () => {
                        autoUpdater.checkForUpdatesAndNotify();
                    }
                }
            ]
        }
    ];

    // macOS specific menu adjustments
    if (process.platform === 'darwin') {
        template[0].submenu.splice(1, 0, {
            label: 'Services',
            role: 'services',
            submenu: []
        });

        template[3].submenu = [
            { label: 'Close', accelerator: 'CmdOrCtrl+W', role: 'close' },
            { label: 'Minimize', accelerator: 'CmdOrCtrl+M', role: 'minimize' },
            { label: 'Zoom', role: 'zoom' },
            { type: 'separator' },
            { label: 'Bring All to Front', role: 'front' }
        ];
    }

    const menu = Menu.buildFromTemplate(template);
    Menu.setApplicationMenu(menu);
}

// IPC handlers
ipcMain.handle('get-app-version', () => {
    return app.getVersion();
});

ipcMain.handle('is-dev', () => {
    return isDev;
});

ipcMain.handle('get-store-value', (event, key) => {
    return store.get(key);
});

ipcMain.handle('set-store-value', (event, key, value) => {
    store.set(key, value);
});

ipcMain.handle('api-request', async (event, method, endpoint, data, headers) => {
    try {
        const config = {
            method,
            url: `${API_BASE_URL}${endpoint}`,
            headers: {
                'Content-Type': 'application/json',
                ...headers
            }
        };

        if (data) {
            config.data = data;
        }

        const response = await axios(config);
        return {
            success: true,
            data: response.data,
            status: response.status
        };
    } catch (error) {
        return {
            success: false,
            error: error.message,
            status: error.response?.status || 0,
            data: error.response?.data || null
        };
    }
});

ipcMain.handle('show-message-box', async (event, options) => {
    const result = await dialog.showMessageBox(mainWindow, options);
    return result;
});

ipcMain.handle('show-save-dialog', async (event, options) => {
    const result = await dialog.showSaveDialog(mainWindow, options);
    return result;
});

ipcMain.handle('show-open-dialog', async (event, options) => {
    const result = await dialog.showOpenDialog(mainWindow, options);
    return result;
});

ipcMain.on('show-image-context-menu', (event, imageUrl) => {
    const template = [
        {
            label: 'Save Image As...',
            click: async () => {
                const defaultFileName = path.basename(new URL(imageUrl, API_BASE_URL).pathname) || 'pet.png';
                const { canceled, filePath } = await dialog.showSaveDialog(mainWindow, {
                    title: 'Save Pet Image',
                    defaultPath: defaultFileName,
                    filters: [
                        { name: 'PNG Image', extensions: ['png'] },
                        { name: 'JPEG Image', extensions: ['jpg', 'jpeg'] },
                    ]
                });

                if (canceled || !filePath) {
                    return;
                }

                try {
                    const absoluteUrl = new URL(imageUrl, API_BASE_URL).href;
                    const response = await axios({ url: absoluteUrl, method: 'GET', responseType: 'stream' });
                    const writer = fs.createWriteStream(filePath);
                    response.data.pipe(writer);

                    writer.on('finish', () => {
                        dialog.showMessageBox(mainWindow, {
                            type: 'info',
                            title: 'Download Complete',
                            message: `Image saved to ${filePath}`
                        });
                    });

                    writer.on('error', (error) => {
                        fs.unlink(filePath, () => {}); // Clean up failed download
                        dialog.showErrorBox('Download Failed', `Could not save the image: ${error.message}`);
                    });
                } catch (error) {
                    console.error('Failed to download image:', error);
                    dialog.showErrorBox('Download Failed', 'Could not download the pet image. Please check the console for more details.');
                }
            }
        }
    ];
    const menu = Menu.buildFromTemplate(template);
    menu.popup(BrowserWindow.fromWebContents(event.sender));
});

ipcMain.handle('show-notification', async (event, options) => {
    if (Notification.isSupported()) {
        new Notification({
            title: options.title,
            body: options.body,
            icon: path.join(__dirname, 'renderer/assets/icon.png')
        }).show();
    }
});

// App event handlers
app.whenReady().then(() => {
    createSplashWindow();
    createMainWindow();
    createMenu();

    // Handle app activation (macOS)
    app.on('activate', () => {
        if (BrowserWindow.getAllWindows().length === 0) {
            createMainWindow();
        }
    });

    // Check for updates
    if (!isDev) {
        autoUpdater.checkForUpdatesAndNotify();
    }
});

app.on('window-all-closed', () => {
    if (process.platform !== 'darwin') {
        app.quit();
    }
});

app.on('before-quit', () => {
    saveWindowBounds();
});

// Auto updater events
autoUpdater.on('checking-for-update', () => {
    console.log('Checking for update...');
});

autoUpdater.on('update-available', (info) => {
    console.log('Update available.');
});

autoUpdater.on('update-not-available', (info) => {
    console.log('Update not available.');
});

autoUpdater.on('error', (err) => {
    console.log('Error in auto-updater. ' + err);
});

autoUpdater.on('download-progress', (progressObj) => {
    let log_message = "Download speed: " + progressObj.bytesPerSecond;
    log_message = log_message + ' - Downloaded ' + progressObj.percent + '%';
    log_message = log_message + ' (' + progressObj.transferred + "/" + progressObj.total + ')';
    console.log(log_message);
});

autoUpdater.on('update-downloaded', (info) => {
    console.log('Update downloaded');
    autoUpdater.quitAndInstall();
});

// Security: Prevent new window creation
app.on('web-contents-created', (event, contents) => {
    contents.on('new-window', (event, navigationUrl) => {
        event.preventDefault();
        shell.openExternal(navigationUrl);
    });
});

// Handle certificate errors
app.on('certificate-error', (event, webContents, url, error, certificate, callback) => {
    if (isDev) {
        // In development, ignore certificate errors
        event.preventDefault();
        callback(true);
    } else {
        // In production, use default behavior
        callback(false);
    }
});
