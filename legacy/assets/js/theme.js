document.addEventListener('DOMContentLoaded', function() {
    const themeSwitcher = document.getElementById('theme-switcher');
    const body = document.body;
    const html = document.documentElement;

    // Function to apply the theme
    const applyTheme = (theme) => {
        if (theme === 'dark') {
            html.classList.add('dark-theme');
            body.classList.add('dark-theme');
            themeSwitcher.textContent = '☀️'; // Sun icon for light mode
        } else {
            html.classList.remove('dark-theme');
            body.classList.remove('dark-theme');
            themeSwitcher.textContent = '🌙'; // Moon icon for dark mode
        }
    };

    // Set initial theme based on localStorage or system preference
    const currentTheme = localStorage.getItem('theme');
    if (currentTheme) {
        applyTheme(currentTheme);
    } else {
        // If no theme is stored, use system preference
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            applyTheme('dark');
        } else {
            applyTheme('light');
        }
    }

    // Event listener for the theme switcher button
    themeSwitcher.addEventListener('click', () => {
        let newTheme;
        if (body.classList.contains('dark-theme')) {
            newTheme = 'light';
        } else {
            newTheme = 'dark';
        }
        localStorage.setItem('theme', newTheme);
        applyTheme(newTheme);
    });
});
