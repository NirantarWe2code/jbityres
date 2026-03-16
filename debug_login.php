<?php
/**
 * Debug Login Issues
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/classes/Database.php';
require_once __DIR__ . '/classes/Auth.php';

echo "<h1>🔍 Debug Login Issues</h1>";

try {
    $db = Database::getInstance();
    $auth = new Auth();
    
    echo "<h3>1. Database Connection Test:</h3>";
    if ($db->getConnection()) {
        echo "✅ Database connected successfully<br>";
    } else {
        echo "❌ Database connection failed<br>";
        exit;
    }
    
    echo "<h3>2. Users Table Check:</h3>";
    $sql = "SELECT id, username, full_name, email, role, status, created_at FROM users ORDER BY id";
    $users = $db->fetchAll($sql);
    
    if (empty($users)) {
        echo "❌ No users found in database<br>";
        echo "<p><strong>Solution:</strong> Run <a href='setup.php'>setup.php</a> to create default users.</p>";
    } else {
        echo "✅ Found " . count($users) . " users in database:<br>";
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr><th>ID</th><th>Username</th><th>Full Name</th><th>Email</th><th>Role</th><th>Status</th><th>Created</th></tr>";
        
        foreach ($users as $user) {
            echo "<tr>";
            echo "<td>{$user['id']}</td>";
            echo "<td><strong>{$user['username']}</strong></td>";
            echo "<td>{$user['full_name']}</td>";
            echo "<td>{$user['email']}</td>";
            echo "<td>{$user['role']}</td>";
            echo "<td>{$user['status']}</td>";
            echo "<td>{$user['created_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    echo "<h3>3. Password Hash Check:</h3>";
    $testUsers = ['admin', 'manager', 'user'];
    
    foreach ($testUsers as $username) {
        $sql = "SELECT id, username, password FROM users WHERE username = ?";
        $user = $db->fetchOne($sql, [$username]);
        
        if ($user) {
            echo "✅ User '$username' found<br>";
            echo "&nbsp;&nbsp;&nbsp;Password hash: " . substr($user['password'], 0, 20) . "...<br>";
            
            // Test password verification
            $testPasswords = [
                'admin123' => $username === 'admin',
                'manager123' => $username === 'manager', 
                'user123' => $username === 'user'
            ];
            
            foreach ($testPasswords as $testPass => $shouldMatch) {
                if ($shouldMatch) {
                    $isValid = password_verify($testPass, $user['password']);
                    if ($isValid) {
                        echo "&nbsp;&nbsp;&nbsp;✅ Password '$testPass' verified successfully<br>";
                    } else {
                        echo "&nbsp;&nbsp;&nbsp;❌ Password '$testPass' verification failed<br>";
                    }
                }
            }
        } else {
            echo "❌ User '$username' not found<br>";
        }
    }
    
    echo "<h3>4. Login Test:</h3>";
    
    // Test login with admin credentials
    $loginResult = $auth->login('admin', 'admin123');
    
    if ($loginResult['success']) {
        echo "✅ Login test successful for admin/admin123<br>";
        echo "&nbsp;&nbsp;&nbsp;User data: " . json_encode($loginResult['user']) . "<br>";
        
        // Logout to clean session
        $auth->logout();
    } else {
        echo "❌ Login test failed: " . $loginResult['message'] . "<br>";
    }
    
    echo "<h3>5. Session Test:</h3>";
    
    // Test session functions
    $csrfToken = generateCSRFToken();
    if ($csrfToken) {
        echo "✅ CSRF token generated: " . substr($csrfToken, 0, 10) . "...<br>";
    } else {
        echo "❌ CSRF token generation failed<br>";
    }
    
    if (function_exists('isLoggedIn')) {
        $loggedIn = isLoggedIn();
        echo "✅ isLoggedIn() function works: " . ($loggedIn ? 'true' : 'false') . "<br>";
    } else {
        echo "❌ isLoggedIn() function not found<br>";
    }
    
    echo "<h3>6. Manual User Creation (if needed):</h3>";
    echo "<p>If users are missing, you can create them manually:</p>";
    
    if (isset($_POST['create_users'])) {
        echo "<h4>Creating Users...</h4>";
        
        $defaultUsers = [
            [
                'username' => 'admin',
                'password' => 'admin123',
                'full_name' => 'System Administrator',
                'email' => 'admin@finalreport.com',
                'role' => ROLE_SUPER_ADMIN
            ],
            [
                'username' => 'manager',
                'password' => 'manager123',
                'full_name' => 'Sales Manager',
                'email' => 'manager@finalreport.com',
                'role' => ROLE_ADMIN
            ],
            [
                'username' => 'user',
                'password' => 'user123',
                'full_name' => 'Sales User',
                'email' => 'user@finalreport.com',
                'role' => ROLE_USER
            ]
        ];
        
        foreach ($defaultUsers as $userData) {
            $result = $auth->register($userData);
            if ($result['success']) {
                echo "✅ Created user: {$userData['username']} / {$userData['password']}<br>";
            } else {
                echo "⚠️ User {$userData['username']}: {$result['message']}<br>";
            }
        }
        
        echo "<p><a href='debug_login.php'>Refresh page</a> to see updated user list.</p>";
    } else {
        echo "<form method='POST'>";
        echo "<button type='submit' name='create_users' style='padding: 10px 20px; background: #28a745; color: white; border: none; border-radius: 4px;'>Create Default Users</button>";
        echo "</form>";
    }
    
    echo "<h3>7. Login Form Test:</h3>";
    
    if (isset($_POST['test_login'])) {
        $testUsername = $_POST['test_username'] ?? '';
        $testPassword = $_POST['test_password'] ?? '';
        
        echo "<h4>Testing Login: $testUsername</h4>";
        
        $loginResult = $auth->login($testUsername, $testPassword);
        
        if ($loginResult['success']) {
            echo "✅ Login successful!<br>";
            echo "User data: " . json_encode($loginResult['user']) . "<br>";
            
            // Logout immediately
            $auth->logout();
            echo "User logged out for testing purposes.<br>";
        } else {
            echo "❌ Login failed: " . $loginResult['message'] . "<br>";
        }
    }
    
    echo "<form method='POST' style='margin: 10px 0; padding: 15px; background: #f8f9fa; border-radius: 4px;'>";
    echo "<h4>Test Login Form:</h4>";
    echo "<input type='text' name='test_username' placeholder='Username' style='margin: 5px; padding: 8px;' value='admin'><br>";
    echo "<input type='password' name='test_password' placeholder='Password' style='margin: 5px; padding: 8px;' value='admin123'><br>";
    echo "<button type='submit' name='test_login' style='margin: 5px; padding: 8px 16px; background: #007bff; color: white; border: none; border-radius: 4px;'>Test Login</button>";
    echo "</form>";
    
    echo "<h3>8. Quick Links:</h3>";
    echo "<a href='setup.php' style='margin: 5px; padding: 10px 15px; background: #28a745; color: white; text-decoration: none; border-radius: 4px;'>Run Setup</a> ";
    echo "<a href='login.php' style='margin: 5px; padding: 10px 15px; background: #007bff; color: white; text-decoration: none; border-radius: 4px;'>Login Page</a> ";
    echo "<a href='test_system.php' style='margin: 5px; padding: 10px 15px; background: #17a2b8; color: white; text-decoration: none; border-radius: 4px;'>System Test</a>";
    
} catch (Exception $e) {
    echo "<h3>❌ Error:</h3>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
    echo "<p>Please check your database connection and configuration.</p>";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; background: #f8f9fa; }
h1, h3, h4 { color: #333; }
table { border-collapse: collapse; margin: 10px 0; background: white; }
th, td { padding: 8px 12px; border: 1px solid #ddd; text-align: left; }
th { background: #f8f9fa; font-weight: bold; }
a { color: #007bff; }
a:hover { text-decoration: underline; }
</style>