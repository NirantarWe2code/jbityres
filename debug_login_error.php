<?php
/**
 * Debug Login Error - Comprehensive Diagnosis
 */

require_once __DIR__ . '/config/config.php';

echo "<h1>🔍 Debug Login Error</h1>";

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h3>1. Basic Configuration Check</h3>";

try {
    // Check if constants are defined
    $constants = ['DB_HOST', 'DB_USER', 'DB_PASS', 'DB_NAME', 'BASE_URL'];
    foreach ($constants as $const) {
        if (defined($const)) {
            echo "✅ $const: " . constant($const) . "<br>";
        } else {
            echo "❌ $const: Not defined<br>";
        }
    }
    
    echo "<h3>2. Database Connection Test</h3>";
    
    // Test database connection
    require_once __DIR__ . '/classes/Database.php';
    $db = Database::getInstance();
    
    if ($db->getConnection()) {
        echo "✅ Database connection successful<br>";
    } else {
        echo "❌ Database connection failed<br>";
        throw new Exception("Cannot connect to database");
    }
    
    echo "<h3>3. Users Table Check</h3>";
    
    // Check if users table exists and has data
    $usersCount = $db->fetchOne("SELECT COUNT(*) as count FROM users");
    echo "📊 Users in database: " . $usersCount['count'] . "<br>";
    
    if ($usersCount['count'] == 0) {
        echo "⚠️ No users found - creating default users...<br>";
        
        require_once __DIR__ . '/classes/Auth.php';
        $auth = new Auth();
        
        $defaultUsers = [
            [
                'username' => 'admin',
                'password' => 'admin123',
                'full_name' => 'System Administrator',
                'email' => 'admin@finalreport.com',
                'role' => ROLE_SUPER_ADMIN
            ]
        ];
        
        foreach ($defaultUsers as $userData) {
            $result = $auth->register($userData);
            if ($result['success']) {
                echo "✅ Created user: {$userData['username']} / {$userData['password']}<br>";
            } else {
                echo "❌ Failed to create user: {$result['message']}<br>";
            }
        }
    }
    
    echo "<h3>4. Auth Class Test</h3>";
    
    require_once __DIR__ . '/classes/Auth.php';
    $auth = new Auth();
    
    // Test login with admin credentials
    echo "<h4>Testing login with admin/admin123:</h4>";
    
    $loginResult = $auth->login('admin', 'admin123');
    
    if ($loginResult['success']) {
        echo "✅ Login test successful<br>";
        echo "User data: " . json_encode($loginResult['user']) . "<br>";
        
        // Test logout
        $logoutResult = $auth->logout();
        echo "✅ Logout test: " . ($logoutResult['success'] ? 'Success' : 'Failed') . "<br>";
    } else {
        echo "❌ Login test failed: " . $loginResult['message'] . "<br>";
        
        // Debug the user data
        $userCheck = $db->fetchOne("SELECT id, username, password, status FROM users WHERE username = ?", ['admin']);
        if ($userCheck) {
            echo "<strong>User found in database:</strong><br>";
            echo "- ID: " . $userCheck['id'] . "<br>";
            echo "- Username: " . $userCheck['username'] . "<br>";
            echo "- Status: " . $userCheck['status'] . "<br>";
            echo "- Password hash: " . substr($userCheck['password'], 0, 20) . "...<br>";
            
            // Test password verification
            $passwordValid = password_verify('admin123', $userCheck['password']);
            echo "- Password verification: " . ($passwordValid ? 'Valid' : 'Invalid') . "<br>";
        } else {
            echo "❌ Admin user not found in database<br>";
        }
    }
    
    echo "<h3>5. Session Functions Test</h3>";
    
    // Test session functions
    if (function_exists('generateCSRFToken')) {
        $token = generateCSRFToken();
        echo "✅ CSRF token generated: " . substr($token, 0, 10) . "...<br>";
    } else {
        echo "❌ generateCSRFToken function not found<br>";
    }
    
    if (function_exists('isLoggedIn')) {
        $loggedIn = isLoggedIn();
        echo "✅ isLoggedIn function works: " . ($loggedIn ? 'true' : 'false') . "<br>";
    } else {
        echo "❌ isLoggedIn function not found<br>";
    }
    
    echo "<h3>6. Login Form Test</h3>";
    
    if (isset($_POST['test_login'])) {
        echo "<h4>Processing Login Test...</h4>";
        
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        
        echo "Username: " . htmlspecialchars($username) . "<br>";
        echo "Password: " . str_repeat('*', strlen($password)) . "<br>";
        
        if (empty($username) || empty($password)) {
            echo "❌ Username or password is empty<br>";
        } else {
            // Test CSRF token
            if (isset($_POST['csrf_token'])) {
                $tokenValid = verifyCSRFToken($_POST['csrf_token']);
                echo "CSRF Token: " . ($tokenValid ? 'Valid' : 'Invalid') . "<br>";
            }
            
            // Test login
            $loginResult = $auth->login($username, $password);
            
            if ($loginResult['success']) {
                echo "✅ Login successful!<br>";
                echo "Redirecting to dashboard...<br>";
                
                // Show session data
                echo "<strong>Session data set:</strong><br>";
                echo "- user_id: " . ($_SESSION['user_id'] ?? 'Not set') . "<br>";
                echo "- username: " . ($_SESSION['username'] ?? 'Not set') . "<br>";
                echo "- role: " . ($_SESSION['role'] ?? 'Not set') . "<br>";
                
                // Logout for next test
                $auth->logout();
            } else {
                echo "❌ Login failed: " . $loginResult['message'] . "<br>";
                
                // Show last query for debugging
                echo "<strong>Last database query:</strong><br>";
                $db->debugLastQuery();
            }
        }
    }
    
    echo "<form method='POST' style='background: #f8f9fa; padding: 15px; border-radius: 4px; margin: 15px 0;'>";
    echo "<h4>Test Login Form:</h4>";
    echo "<input type='hidden' name='csrf_token' value='" . generateCSRFToken() . "'>";
    echo "<div style='margin: 10px 0;'>";
    echo "<label>Username:</label><br>";
    echo "<input type='text' name='username' value='admin' style='padding: 8px; width: 200px;'>";
    echo "</div>";
    echo "<div style='margin: 10px 0;'>";
    echo "<label>Password:</label><br>";
    echo "<input type='password' name='password' value='admin123' style='padding: 8px; width: 200px;'>";
    echo "</div>";
    echo "<button type='submit' name='test_login' style='padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 4px;'>Test Login</button>";
    echo "</form>";
    
    echo "<h3>7. File Permissions Check</h3>";
    
    $files = [
        'config/config.php',
        'classes/Database.php',
        'classes/Auth.php',
        'login.php'
    ];
    
    foreach ($files as $file) {
        $fullPath = __DIR__ . '/' . $file;
        if (file_exists($fullPath)) {
            $readable = is_readable($fullPath);
            echo "✅ $file: " . ($readable ? 'Readable' : 'Not readable') . "<br>";
        } else {
            echo "❌ $file: File not found<br>";
        }
    }
    
    echo "<h3>8. PHP Error Log Check</h3>";
    
    $errorLog = ini_get('error_log');
    if ($errorLog && file_exists($errorLog)) {
        echo "📄 PHP Error log location: $errorLog<br>";
        echo "Check this file for any PHP errors during login attempts.<br>";
    } else {
        echo "⚠️ PHP Error log not configured or not found<br>";
    }
    
    echo "<h3>9. Quick Fixes</h3>";
    
    echo "<div style='background: #fff3cd; padding: 15px; border-radius: 4px; margin: 15px 0;'>";
    echo "<h4>If login is still not working, try these:</h4>";
    echo "<ol>";
    echo "<li><a href='setup.php'>Run setup.php</a> to recreate users</li>";
    echo "<li><a href='fix_login_credentials.php'>Run fix_login_credentials.php</a> to fix user passwords</li>";
    echo "<li>Check Apache error logs in XAMPP control panel</li>";
    echo "<li>Restart Apache and MySQL services</li>";
    echo "<li>Clear browser cache and cookies</li>";
    echo "</ol>";
    echo "</div>";
    
    echo "<h3>10. Direct Login Page Test</h3>";
    
    echo "<p><a href='login.php' style='padding: 10px 20px; background: #28a745; color: white; text-decoration: none; border-radius: 4px;'>Go to Login Page</a></p>";
    
} catch (Exception $e) {
    echo "<h3>❌ Critical Error:</h3>";
    echo "<div style='background: #f8d7da; padding: 15px; border-radius: 4px; color: #721c24;'>";
    echo "<strong>Error:</strong> " . $e->getMessage() . "<br>";
    echo "<strong>File:</strong> " . $e->getFile() . "<br>";
    echo "<strong>Line:</strong> " . $e->getLine() . "<br>";
    echo "</div>";
    
    echo "<h4>Possible Solutions:</h4>";
    echo "<ul>";
    echo "<li>Check database connection settings in config/config.php</li>";
    echo "<li>Ensure MySQL service is running in XAMPP</li>";
    echo "<li>Verify database 'sales_reports' exists</li>";
    echo "<li>Check if users table exists with proper structure</li>";
    echo "</ul>";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; background: #f8f9fa; }
h1, h3, h4 { color: #333; }
code { background: #f1f3f4; padding: 2px 6px; border-radius: 3px; }
a { color: #007bff; text-decoration: none; }
a:hover { text-decoration: underline; }
ul, ol { margin: 10px 0; }
li { margin: 5px 0; }
form { max-width: 400px; }
input, button { margin: 5px 0; }
</style>