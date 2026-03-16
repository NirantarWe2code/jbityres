<?php
/**
 * Quick Login Fix - Automated Solution
 */

require_once __DIR__ . '/config/config.php';

echo "<h1>🔧 Quick Login Fix</h1>";

$fixes = [];
$errors = [];

try {
    echo "<h3>Step 1: Database Connection</h3>";
    
    require_once __DIR__ . '/classes/Database.php';
    $db = Database::getInstance();
    
    if ($db->getConnection()) {
        echo "✅ Database connected<br>";
        $fixes[] = "Database connection working";
    } else {
        throw new Exception("Database connection failed");
    }
    
    echo "<h3>Step 2: Check/Create Users Table</h3>";
    
    // Check if users table exists
    $tableExists = $db->tableExists('users');
    
    if (!$tableExists) {
        echo "❌ Users table not found - creating...<br>";
        
        // Create users table
        $createTableSQL = "
        CREATE TABLE users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            full_name VARCHAR(100) NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            role ENUM('super_admin', 'admin', 'user') DEFAULT 'user',
            status ENUM('active', 'inactive') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            last_login TIMESTAMP NULL
        )";
        
        $db->execute($createTableSQL);
        echo "✅ Users table created<br>";
        $fixes[] = "Created users table";
    } else {
        echo "✅ Users table exists<br>";
        $fixes[] = "Users table exists";
    }
    
    echo "<h3>Step 3: Create/Fix Admin User</h3>";
    
    require_once __DIR__ . '/classes/Auth.php';
    $auth = new Auth();
    
    // Check if admin user exists
    $adminUser = $db->fetchOne("SELECT id, username FROM users WHERE username = ?", ['admin']);
    
    if (!$adminUser) {
        echo "❌ Admin user not found - creating...<br>";
        
        $adminData = [
            'username' => 'admin',
            'password' => 'admin123',
            'full_name' => 'System Administrator',
            'email' => 'admin@finalreport.com',
            'role' => ROLE_SUPER_ADMIN
        ];
        
        $result = $auth->register($adminData);
        
        if ($result['success']) {
            echo "✅ Admin user created: admin / admin123<br>";
            $fixes[] = "Created admin user";
        } else {
            throw new Exception("Failed to create admin user: " . $result['message']);
        }
    } else {
        echo "✅ Admin user exists<br>";
        
        // Update password to ensure it's correct
        $hashedPassword = password_hash('admin123', PASSWORD_DEFAULT);
        $updateResult = $db->execute(
            "UPDATE users SET password = ?, status = 'active' WHERE username = ?",
            [$hashedPassword, 'admin']
        );
        
        if ($updateResult['affected_rows'] > 0) {
            echo "✅ Admin password updated<br>";
            $fixes[] = "Updated admin password";
        }
    }
    
    echo "<h3>Step 4: Test Login</h3>";
    
    $loginResult = $auth->login('admin', 'admin123');
    
    if ($loginResult['success']) {
        echo "✅ Login test successful<br>";
        $fixes[] = "Login functionality working";
        
        // Logout immediately
        $auth->logout();
        echo "✅ Logout test successful<br>";
    } else {
        throw new Exception("Login test failed: " . $loginResult['message']);
    }
    
    echo "<h3>Step 5: Check Sales Data Table</h3>";
    
    $salesTableExists = $db->tableExists('sales_data');
    
    if (!$salesTableExists) {
        echo "⚠️ Sales data table not found - creating basic structure...<br>";
        
        $createSalesTableSQL = "
        CREATE TABLE sales_data (
            id INT AUTO_INCREMENT PRIMARY KEY,
            invoice_num VARCHAR(50) NOT NULL,
            dated DATE NOT NULL,
            business_name VARCHAR(100) NOT NULL,
            sales_rep VARCHAR(100),
            product VARCHAR(100) NOT NULL,
            quantity DECIMAL(10,2) NOT NULL,
            unit_price DECIMAL(10,2) NOT NULL,
            line_revenue DECIMAL(10,2) GENERATED ALWAYS AS (quantity * unit_price) STORED,
            gross_profit DECIMAL(10,2) DEFAULT 0,
            gp_margin DECIMAL(5,2) GENERATED ALWAYS AS (
                CASE 
                    WHEN line_revenue > 0 THEN (gross_profit / line_revenue) * 100 
                    ELSE 0 
                END
            ) STORED,
            notes TEXT,
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        )";
        
        $db->execute($createSalesTableSQL);
        echo "✅ Sales data table created<br>";
        $fixes[] = "Created sales_data table";
    } else {
        echo "✅ Sales data table exists<br>";
        $fixes[] = "Sales data table exists";
    }
    
    echo "<h3>✅ All Fixes Applied Successfully!</h3>";
    
    echo "<div style='background: #d4edda; padding: 15px; border-radius: 4px; margin: 15px 0;'>";
    echo "<h4>🎉 System Ready!</h4>";
    echo "<p><strong>Login Credentials:</strong></p>";
    echo "<ul>";
    echo "<li><strong>Username:</strong> admin</li>";
    echo "<li><strong>Password:</strong> admin123</li>";
    echo "</ul>";
    echo "<p><a href='login.php' style='padding: 10px 20px; background: #28a745; color: white; text-decoration: none; border-radius: 4px;'>Go to Login Page</a></p>";
    echo "</div>";
    
    echo "<h4>Fixes Applied:</h4>";
    echo "<ul>";
    foreach ($fixes as $fix) {
        echo "<li>✅ $fix</li>";
    }
    echo "</ul>";
    
} catch (Exception $e) {
    echo "<h3>❌ Error Occurred:</h3>";
    echo "<div style='background: #f8d7da; padding: 15px; border-radius: 4px; color: #721c24;'>";
    echo "<strong>Error:</strong> " . $e->getMessage() . "<br>";
    echo "</div>";
    
    $errors[] = $e->getMessage();
    
    echo "<h4>Manual Steps Required:</h4>";
    echo "<ol>";
    echo "<li>Check XAMPP - ensure Apache and MySQL are running</li>";
    echo "<li>Verify database 'sales_reports' exists in phpMyAdmin</li>";
    echo "<li>Check config/config.php database settings</li>";
    echo "<li>Run <a href='debug_login_error.php'>debug_login_error.php</a> for detailed diagnosis</li>";
    echo "</ol>";
}

if (!empty($errors)) {
    echo "<h4>Errors Encountered:</h4>";
    echo "<ul>";
    foreach ($errors as $error) {
        echo "<li>❌ $error</li>";
    }
    echo "</ul>";
}

echo "<h3>Additional Resources:</h3>";
echo "<ul>";
echo "<li><a href='debug_login_error.php'>Detailed Login Debug</a></li>";
echo "<li><a href='test_system.php'>Complete System Test</a></li>";
echo "<li><a href='setup.php'>Initial Setup Script</a></li>";
echo "</ul>";
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; background: #f8f9fa; }
h1, h3, h4 { color: #333; }
a { color: #007bff; text-decoration: none; }
a:hover { text-decoration: underline; }
ul, ol { margin: 10px 0; }
li { margin: 5px 0; }
</style>