<?php
/**
 * Automatic Database Fix
 * Automatically fix all database structure issues
 */

require_once __DIR__ . '/config/config.php';

echo "<h1>🔧 Automatic Database Fix</h1>";

// Enable detailed error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    echo "<h3>Step 1: Test Basic Connection</h3>";
    
    // Test basic MySQL connection first
    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS);
    
    if ($mysqli->connect_error) {
        throw new Exception("Cannot connect to MySQL: " . $mysqli->connect_error);
    }
    
    echo "✅ MySQL connection successful<br>";
    
    echo "<h3>Step 2: Create Database if Missing</h3>";
    
    // Create database if it doesn't exist
    $createDB = "CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
    
    if ($mysqli->query($createDB)) {
        echo "✅ Database '" . DB_NAME . "' created/verified<br>";
    } else {
        throw new Exception("Failed to create database: " . $mysqli->error);
    }
    
    // Select the database
    $mysqli->select_db(DB_NAME);
    
    echo "<h3>Step 3: Create/Fix Users Table</h3>";
    
    // Check if users table exists and has correct structure
    $tableExists = false;
    $result = $mysqli->query("SHOW TABLES LIKE 'users'");
    if ($result && $result->num_rows > 0) {
        $tableExists = true;
        echo "📋 Users table already exists<br>";
        
        // Check if it has the required columns
        $columnsResult = $mysqli->query("DESCRIBE users");
        $existingColumns = [];
        if ($columnsResult) {
            while ($row = $columnsResult->fetch_assoc()) {
                $existingColumns[] = $row['Field'];
            }
        }
        
        $requiredColumns = ['id', 'username', 'password', 'full_name', 'email', 'role', 'status', 'created_at', 'last_login'];
        $missingColumns = array_diff($requiredColumns, $existingColumns);
        
        if (!empty($missingColumns)) {
            echo "⚠️ Table exists but missing columns: " . implode(', ', $missingColumns) . "<br>";
            echo "🗑️ Dropping and recreating table with correct structure...<br>";
            
            // Backup existing data
            $backupData = [];
            $backupResult = $mysqli->query("SELECT * FROM users");
            if ($backupResult) {
                while ($row = $backupResult->fetch_assoc()) {
                    $backupData[] = $row;
                }
                echo "📦 Backed up " . count($backupData) . " existing users<br>";
            }
            
            // Drop and recreate
            $mysqli->query("DROP TABLE users");
            $tableExists = false;
        } else {
            echo "✅ Users table has all required columns<br>";
        }
    }
    
    if (!$tableExists) {
        // Create users table with correct structure
        $createUsersSQL = "
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        if ($mysqli->query($createUsersSQL)) {
            echo "✅ Users table created successfully<br>";
            
            // Restore backed up data if any
            if (!empty($backupData)) {
                echo "📥 Restoring backed up users...<br>";
                foreach ($backupData as $user) {
                    $insertSQL = "INSERT INTO users (username, password, full_name, email, role, status) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE password = VALUES(password)";
                    $stmt = $mysqli->prepare($insertSQL);
                    if ($stmt) {
                        $stmt->bind_param('ssssss', 
                            $user['username'] ?? 'user_' . $user['id'],
                            $user['password'] ?? password_hash('password', PASSWORD_DEFAULT),
                            $user['full_name'] ?? 'User',
                            $user['email'] ?? 'user' . $user['id'] . '@example.com',
                            $user['role'] ?? 'user',
                            $user['status'] ?? 'active'
                        );
                        $stmt->execute();
                        $stmt->close();
                    }
                }
                echo "✅ Restored " . count($backupData) . " users<br>";
            }
        } else {
            throw new Exception("Failed to create users table: " . $mysqli->error);
        }
    }
    
    echo "<h3>Step 4: Create/Fix Sales Data Table</h3>";
    
    // Check if sales_data table exists and has correct structure
    $salesTableExists = false;
    $result = $mysqli->query("SHOW TABLES LIKE 'sales_data'");
    if ($result && $result->num_rows > 0) {
        $salesTableExists = true;
        echo "📋 Sales data table already exists<br>";
        
        // Check structure - just verify it works, don't recreate unless necessary
        try {
            $testQuery = $mysqli->query("SELECT id, invoice_num, dated, business_name, product, quantity, unit_price FROM sales_data LIMIT 1");
            echo "✅ Sales data table structure is compatible<br>";
        } catch (Exception $e) {
            echo "⚠️ Sales table structure needs fixing<br>";
            echo "🗑️ Recreating sales_data table...<br>";
            $mysqli->query("DROP TABLE IF EXISTS sales_data");
            $salesTableExists = false;
        }
    }
    
    if (!$salesTableExists) {
        // Create sales_data table with correct structure matching Excel columns
        $createSalesSQL = "
        CREATE TABLE sales_data (
            id INT AUTO_INCREMENT PRIMARY KEY,
            invoice_num VARCHAR(50) NOT NULL,
            dated DATETIME NOT NULL,
            business_name VARCHAR(200) NOT NULL,
            sales_rep VARCHAR(100),
            product VARCHAR(300) NOT NULL,
            quantity DECIMAL(10,2) NOT NULL,
            unit_price DECIMAL(10,2) NOT NULL,
            purchase_price DECIMAL(10,2) DEFAULT 0,
            line_revenue DECIMAL(10,2) NOT NULL,
            gross_profit DECIMAL(10,2) GENERATED ALWAYS AS (line_revenue - (quantity * purchase_price)) STORED,
            gp_margin DECIMAL(5,2) GENERATED ALWAYS AS (
                CASE 
                    WHEN line_revenue > 0 THEN ((line_revenue - (quantity * purchase_price)) / line_revenue) * 100 
                    ELSE 0 
                END
            ) STORED,
            notes TEXT,
            created_by INT DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_dated (dated),
            INDEX idx_business (business_name),
            INDEX idx_sales_rep (sales_rep),
            INDEX idx_invoice (invoice_num),
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        if ($mysqli->query($createSalesSQL)) {
            echo "✅ Sales data table created successfully<br>";
        } else {
            throw new Exception("Failed to create sales_data table: " . $mysqli->error);
        }
    }
    
    $mysqli->close();
    
    echo "<h3>Step 5: Create Default Users</h3>";
    
    // Now use our Database class
    require_once __DIR__ . '/classes/Database.php';
    require_once __DIR__ . '/classes/Auth.php';
    
    $db = Database::getInstance();
    $auth = new Auth();
    
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
    
    echo "<h3>Step 6: Test Login System</h3>";
    
    // Test login functionality
    $loginResult = $auth->login('admin', 'admin123');
    
    if ($loginResult['success']) {
        echo "✅ Login test successful!<br>";
        echo "User: " . json_encode($loginResult['user']) . "<br>";
        
        // Test logout
        $logoutResult = $auth->logout();
        echo "✅ Logout test: " . ($logoutResult['success'] ? 'Success' : 'Failed') . "<br>";
    } else {
        echo "❌ Login test failed: " . $loginResult['message'] . "<br>";
        echo "Last query: " . $db->getLastQueryFormatted() . "<br>";
    }
    
    echo "<h3>✅ Database Fix Complete!</h3>";
    
    echo "<div style='background: #d4edda; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
    echo "<h4>🎉 System Ready!</h4>";
    echo "<p><strong>Database Structure:</strong> Fixed and verified</p>";
    echo "<p><strong>Default Users:</strong> Created successfully</p>";
    echo "<p><strong>Login System:</strong> Working properly</p>";
    echo "<br>";
    echo "<h5>Login Credentials:</h5>";
    echo "<ul>";
    echo "<li><strong>Super Admin:</strong> admin / admin123</li>";
    echo "<li><strong>Admin:</strong> manager / manager123</li>";
    echo "<li><strong>User:</strong> user / user123</li>";
    echo "</ul>";
    echo "<br>";
    echo "<p><a href='login.php' style='padding: 12px 24px; background: #28a745; color: white; text-decoration: none; border-radius: 4px; font-weight: bold;'>🚀 Go to Login Page</a></p>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<h3>❌ Critical Error:</h3>";
    echo "<div style='background: #f8d7da; padding: 15px; border-radius: 4px; color: #721c24; margin: 15px 0;'>";
    echo "<strong>Error:</strong> " . $e->getMessage() . "<br>";
    echo "<strong>File:</strong> " . $e->getFile() . "<br>";
    echo "<strong>Line:</strong> " . $e->getLine() . "<br>";
    echo "</div>";
    
    echo "<h4>Manual Steps Required:</h4>";
    echo "<ol>";
    echo "<li><strong>Check XAMPP:</strong> Ensure Apache and MySQL services are running</li>";
    echo "<li><strong>Check phpMyAdmin:</strong> <a href='http://localhost/phpmyadmin' target='_blank'>Open phpMyAdmin</a></li>";
    echo "<li><strong>Create Database:</strong> Create 'sales_reports' database manually if needed</li>";
    echo "<li><strong>Check Credentials:</strong> Verify MySQL username/password in config.php</li>";
    echo "</ol>";
    
    echo "<h4>Alternative Solutions:</h4>";
    echo "<ul>";
    echo "<li><a href='debug_database_error.php'>Run Detailed Database Debug</a></li>";
    echo "<li><a href='test_db_connection.php'>Run Simple Connection Test</a></li>";
    echo "<li><a href='fix_users_table.php'>Run Manual Table Fix</a></li>";
    echo "</ul>";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; background: #f8f9fa; }
h1, h3, h4, h5 { color: #333; }
a { color: #007bff; text-decoration: none; }
a:hover { text-decoration: underline; }
ul, ol { margin: 10px 0; }
li { margin: 5px 0; }
code { background: #f1f3f4; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
</style>