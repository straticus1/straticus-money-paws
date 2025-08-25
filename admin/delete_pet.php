<?php
require_once '../includes/functions.php';

// Admin authentication check
if (!isAdmin()) {
    header('Location: /login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRFToken();
    $petId = isset($_POST['pet_id']) ? (int)$_POST['pet_id'] : null;

    if ($petId) {
        deletePet($petId);
    }
}

header('Location: pets.php');
exit;
