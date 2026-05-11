<?php
/**
 * Final Report System - Main Entry Point
 */

require_once __DIR__ . '/config/config.php';

// Redirect to appropriate page based on login status
if (isLoggedIn()) {
    redirect_logged_in_user_home();
} else {
    header('Location: ' . BASE_URL . '/login.php');
}

exit;
?>