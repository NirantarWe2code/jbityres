<?php
/**
 * Setup Script - Create Default Users
 * Run this once to create default users for the system
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/classes/Auth.php';

echo "<h1>Final Report System - Setup</h1>";

try {
    $auth = new Auth();
    
    echo "<h3>Creating Default Users...</h3>";
    
    // Create Super Admin
    $superAdminData = [
        'username' => 'admin',
        'password' => 'admin123',
        'full_name' => 'System Administrator',
        'email' => 'admin@finalreport.com',
        'role' => ROLE_SUPER_ADMIN
    ];
    
    $result = $auth->register($superAdminData);
    if ($result['success']) {
        echo "✅ Super Admin created: admin / admin123<br>";
    } else {
        echo "⚠️ Super Admin: " . $result['message'] . "<br>";
    }
    
    // Create Admin
    $adminData = [
        'username' => 'manager',
        'password' => 'manager123',
        'full_name' => 'Sales Manager',
        'email' => 'manager@finalreport.com',
        'role' => ROLE_ADMIN
    ];
    
    $result = $auth->register($adminData);
    if ($result['success']) {
        echo "✅ Admin created: manager / manager123<br>";
    } else {
        echo "⚠️ Admin: " . $result['message'] . "<br>";
    }
    
    // Create User
    $userData = [
        'username' => 'user',
        'password' => 'user123',
        'full_name' => 'Sales User',
        'email' => 'user@finalreport.com',
        'role' => ROLE_USER
    ];
    
    $result = $auth->register($userData);
    if ($result['success']) {
        echo "✅ User created: user / user123<br>";
    } else {
        echo "⚠️ User: " . $result['message'] . "<br>";
    }
    
    echo "<h3>✅ Setup Complete!</h3>";
    echo "<p>You can now login to the system using any of the created accounts.</p>";
    echo "<p><a href='login.php' class='btn btn-primary'>Go to Login</a></p>";
    
} catch (Exception $e) {
    echo "<h3>❌ Setup Failed</h3>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
    echo "<p>Please check your database connection and try again.</p>";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; background: #f8f9fa; }
h1, h3 { color: #333; }
.btn { padding: 12px 24px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; display: inline-block; margin-top: 10px; }
.btn:hover { background: #0056b3; }
</style>