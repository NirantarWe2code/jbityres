<?php
/**
 * Final Report System - Main Entry Point
 */

require_once __DIR__ . '/config/config.php';

// Redirect to appropriate page based on login status
if (isLoggedIn()) {
    // User is logged in, redirect to dashboard
    header('Location: ' . BASE_URL . '/pages/dashboard/index.php');
} else {
    // User is not logged in, redirect to login page
    header('Location: ' . BASE_URL . '/login.php');
}

exit;
?>