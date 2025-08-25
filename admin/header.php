<?php
// This file is included by other pages, which should have already started the session
// and included functions.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paws.money Admin</title>
        <link rel="stylesheet" href="/admin/assets/css/admin_style.css">
</head>
<body>
    <header class="admin-header">
        <div class="container">
            <a href="/admin" class="logo">🐾 Paws.money Admin</a>
            <nav>
                <a href="/admin">Dashboard</a>
                <a href="/admin/users.php">Users</a>
                <a href="/admin/pets.php">Pets</a>
                <a href="/">View Site</a>
                <a href="/logout.php">Logout</a>
            </nav>
        </div>
    </header>
