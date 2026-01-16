<?php
// logout.php
session_start();
include 'db.php';
include 'functions.php';

if (isset($_SESSION['user_id'])) {
    // Log the logout activity before destroying session
    $details = "User logged out from IP: " . $_SERVER['REMOTE_ADDR'];
    logActivity($db, $_SESSION['user_id'], 'User Logout', $details, $_SESSION['username'] ?? null);
}

// Destroy the session
session_destroy();

// Redirect to login page
header('Location: login.php');
exit();
?>