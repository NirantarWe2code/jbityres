<?php
/**
 * Quick Fix for Login Credentials
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/classes/Database.php';
require_once __DIR__ . '/classes/Auth.php';

echo "<h1>🔧 Fix Login Credentials</h1>";

try {
    $db = Database::getInstance();
    $auth = new Auth();
    
    echo "<h3>Step 1: Check Current Users</h3>";
    
    $sql = "SELECT username, role, status FROM users";
    $existingUsers = $db->fetchAll($sql);
    
    if (empty($existingUsers)) {
        echo "❌ No users found in database<br>";
    } else {
        echo "✅ Found " . count($existingUsers) . " existing users:<br>";
        foreach ($existingUsers as $user) {
            echo "&nbsp;&nbsp;- {$user['username']} ({$user['role']}) - {$user['status']}<br>";
        }
    }
    
    echo "<h3>Step 2: Create/Update Default Users</h3>";
    
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
        // Check if user already exists
        $checkSql = "SELECT id FROM users WHERE username = ?";
        $existingUser = $db->fetchOne($checkSql, [$userData['username']]);
        
        if ($existingUser) {
            // Update existing user password
            $hashedPassword = password_hash($userData['password'], PASSWORD_DEFAULT);
            $updateSql = "UPDATE users SET password = ?, full_name = ?, email = ?, role = ?, status = 'active' WHERE username = ?";
            
            $result = $db->execute($updateSql, [
                $hashedPassword,
                $userData['full_name'],
                $userData['email'],
                $userData['role'],
                $userData['username']
            ]);
            
            if ($result['affected_rows'] > 0) {
                echo "✅ Updated user: {$userData['username']} / {$userData['password']}<br>";
            } else {
                echo "⚠️ User {$userData['username']} update failed<br>";
            }
        } else {
            // Create new user
            $result = $auth->register($userData);
            if ($result['success']) {
                echo "✅ Created user: {$userData['username']} / {$userData['password']}<br>";
            } else {
                echo "⚠️ User {$userData['username']}: {$result['message']}<br>";
            }
        }
    }
    
    echo "<h3>Step 3: Test Login</h3>";
    
    // Test login with each user
    $testCredentials = [
        ['admin', 'admin123'],
        ['manager', 'manager123'],
        ['user', 'user123']
    ];
    
    foreach ($testCredentials as [$username, $password]) {
        $loginResult = $auth->login($username, $password);
        
        if ($loginResult['success']) {
            echo "✅ Login test successful: $username / $password<br>";
            // Logout immediately for next test
            $auth->logout();
        } else {
            echo "❌ Login test failed: $username / $password - {$loginResult['message']}<br>";
        }
    }
    
    echo "<h3>✅ Fix Complete!</h3>";
    echo "<div style='background: #d4edda; padding: 15px; border-radius: 4px; margin: 15px 0;'>";
    echo "<h4>Login Credentials:</h4>";
    echo "<ul>";
    echo "<li><strong>Super Admin:</strong> admin / admin123</li>";
    echo "<li><strong>Admin:</strong> manager / manager123</li>";
    echo "<li><strong>User:</strong> user / user123</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<h3>Next Steps:</h3>";
    echo "<ol>";
    echo "<li><a href='login.php'>Go to Login Page</a></li>";
    echo "<li>Use any of the credentials above</li>";
    echo "<li>Access the dashboard and start using the system</li>";
    echo "</ol>";
    
} catch (Exception $e) {
    echo "<h3>❌ Error occurred:</h3>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
    echo "<p>Please check:</p>";
    echo "<ul>";
    echo "<li>Database connection settings in config/config.php</li>";
    echo "<li>MySQL service is running</li>";
    echo "<li>Database 'sales_reports' exists</li>";
    echo "<li>Tables 'users' and 'sales_data' exist</li>";
    echo "</ul>";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; background: #f8f9fa; }
h1, h3, h4 { color: #333; }
a { color: #007bff; text-decoration: none; }
a:hover { text-decoration: underline; }
ul, ol { margin: 10px 0; }
li { margin: 5px 0; }
</style>