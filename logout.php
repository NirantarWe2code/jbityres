<?php
/**
 * Logout Handler
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/classes/Auth.php';

// Perform logout
$auth = new Auth();
$result = $auth->logout();

// Redirect to login with success message
header('Location: ' . BASE_URL . '/login.php?message=' . urlencode($result['message']));
exit;
?>