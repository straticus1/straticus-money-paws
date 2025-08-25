<?php
// This file should be included at the very top of any user-facing page.
// It requires a $pageTitle variable to be set before inclusion.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo generateCSRFToken(); ?>">
    <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) . ' - Money Paws' : 'Money Paws'; ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="/assets/css/dark-theme.css">
    <link rel="icon" href="/assets/images/favicon.ico" type="image/x-icon">
    <script>
        // Apply theme immediately to prevent flash of unstyled content
        (function() {
            const theme = localStorage.getItem('theme');
            const html = document.documentElement;
            if (theme === 'dark') {
                html.classList.add('dark-theme');
            } else if (theme === 'light') {
                html.classList.remove('dark-theme');
            } else if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                html.classList.add('dark-theme');
            }
        })();
    </script>
</head>
<body>
