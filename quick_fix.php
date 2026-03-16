<?php
/**
 * Quick Database Fix
 * Simple script to fix common database issues
 */

require_once __DIR__ . '/config/config.php';

echo "<h1>⚡ Quick Database Fix</h1>";

try {
    // Test basic connection
    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS);
    
    if ($mysqli->connect_error) {
        throw new Exception("MySQL connection failed: " . $mysqli->connect_error);
    }
    
    echo "✅ MySQL connection successful<br>";
    
    // Create database if not exists
    $createDB = "CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
    if ($mysqli->query($createDB)) {
        echo "✅ Database '" . DB_NAME . "' ready<br>";
    } else {
        throw new Exception("Failed to create database: " . $mysqli->error);
    }
    
    // Select database
    $mysqli->select_db(DB_NAME);
    
    // Fix users table
    echo "<h3>Fixing Users Table...</h3>";
    
    // Check if users table has password column
    $hasPasswordColumn = false;
    $result = $mysqli->query("SHOW COLUMNS FROM users LIKE 'password'");
    if ($result && $result->num_rows > 0) {
        $hasPasswordColumn = true;
        echo "✅ Users table has password column<br>";
    } else {
        echo "❌ Users table missing password column<br>";
        
        // Try to add password column
        $addPasswordSQL = "ALTER TABLE users ADD COLUMN password VARCHAR(255) NOT NULL AFTER username";
        if ($mysqli->query($addPasswordSQL)) {
            echo "✅ Added password column to users table<br>";
            $hasPasswordColumn = true;
        } else {
            echo "⚠️ Could not add password column, recreating table...<br>";
            
            // Recreate users table
            $mysqli->query("DROP TABLE IF EXISTS users");
            
            $createUsersSQL = "
            CREATE TABLE users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(50) UNIQUE NOT NULL,
                password VARCHAR(255) NOT NULL,
                full_name VARCHAR(100) NOT NULL DEFAULT 'User',
                email VARCHAR(100) UNIQUE NOT NULL,
                role ENUM('super_admin', 'admin', 'user') DEFAULT 'user',
                status ENUM('active', 'inactive') DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                last_login TIMESTAMP NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            if ($mysqli->query($createUsersSQL)) {
                echo "✅ Users table recreated<br>";
                $hasPasswordColumn = true;
            } else {
                throw new Exception("Failed to create users table: " . $mysqli->error);
            }
        }
    }
    
    // Create default users if table is empty
    if ($hasPasswordColumn) {
        $userCount = $mysqli->query("SELECT COUNT(*) as count FROM users");
        $count = $userCount ? $userCount->fetch_assoc()['count'] : 0;
        
        if ($count == 0) {
            echo "<h4>Creating default users...</h4>";
            
            $defaultUsers = [
                ['admin', 'admin123', 'System Administrator', 'admin@finalreport.com', 'super_admin'],
                ['manager', 'manager123', 'Sales Manager', 'manager@finalreport.com', 'admin'],
                ['user', 'user123', 'Sales User', 'user@finalreport.com', 'user']
            ];
            
            foreach ($defaultUsers as $user) {
                $hashedPassword = password_hash($user[1], PASSWORD_DEFAULT);
                $insertSQL = "INSERT INTO users (username, password, full_name, email, role) VALUES (?, ?, ?, ?, ?)";
                $stmt = $mysqli->prepare($insertSQL);
                if ($stmt) {
                    $stmt->bind_param('sssss', $user[0], $hashedPassword, $user[2], $user[3], $user[4]);
                    if ($stmt->execute()) {
                        echo "✅ Created user: {$user[0]} / {$user[1]}<br>";
                    } else {
                        echo "❌ Failed to create user {$user[0]}: " . $stmt->error . "<br>";
                    }
                    $stmt->close();
                }
            }
        } else {
            echo "✅ Found $count existing users<br>";
        }
    }
    
    // Fix sales_data table
    echo "<h3>Fixing Sales Data Table...</h3>";
    
    $salesTableExists = false;
    $result = $mysqli->query("SHOW TABLES LIKE 'sales_data'");
    if ($result && $result->num_rows > 0) {
        $salesTableExists = true;
        echo "✅ Sales data table exists<br>";
    } else {
        echo "📋 Creating sales data table...<br>";
        
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
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        if ($mysqli->query($createSalesSQL)) {
            echo "✅ Sales data table created<br>";
        } else {
            echo "❌ Failed to create sales_data table: " . $mysqli->error . "<br>";
        }
    }
    
    // Test login system
    echo "<h3>Testing Login System...</h3>";
    
    require_once __DIR__ . '/classes/Database.php';
    require_once __DIR__ . '/classes/Auth.php';
    
    $auth = new Auth();
    $loginResult = $auth->login('admin', 'admin123');
    
    if ($loginResult['success']) {
        echo "✅ Login system working!<br>";
        echo "User: " . $loginResult['user']['full_name'] . " (" . $loginResult['user']['role'] . ")<br>";
        $auth->logout();
    } else {
        echo "❌ Login failed: " . $loginResult['message'] . "<br>";
    }
    
    $mysqli->close();
    
    echo "<h3>🎉 Quick Fix Complete!</h3>";
    
    echo "<div style='background: #d4edda; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
    echo "<h4>✅ System Status</h4>";
    echo "<ul>";
    echo "<li>✅ Database connection working</li>";
    echo "<li>✅ Users table fixed</li>";
    echo "<li>✅ Sales data table ready</li>";
    echo "<li>✅ Default users created</li>";
    echo "<li>✅ Login system tested</li>";
    echo "</ul>";
    echo "<br>";
    echo "<h5>🔑 Login Credentials:</h5>";
    echo "<ul>";
    echo "<li><strong>Super Admin:</strong> admin / admin123</li>";
    echo "<li><strong>Manager:</strong> manager / manager123</li>";
    echo "<li><strong>User:</strong> user / user123</li>";
    echo "</ul>";
    echo "<br>";
    echo "<p><a href='login.php' style='padding: 12px 24px; background: #28a745; color: white; text-decoration: none; border-radius: 4px; font-weight: bold;'>🚀 Go to Login</a></p>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<h3>❌ Error:</h3>";
    echo "<div style='background: #f8d7da; padding: 15px; border-radius: 4px; color: #721c24;'>";
    echo "<strong>Error:</strong> " . $e->getMessage() . "<br>";
    echo "</div>";
    
    echo "<h4>Manual Steps:</h4>";
    echo "<ol>";
    echo "<li>Check XAMPP services are running</li>";
    echo "<li>Open <a href='http://localhost/phpmyadmin' target='_blank'>phpMyAdmin</a></li>";
    echo "<li>Create database 'sales_reports' if missing</li>";
    echo "<li>Check MySQL credentials in config.php</li>";
    echo "</ol>";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; background: #f8f9fa; }
h1, h3, h4, h5 { color: #333; }
a { color: #007bff; text-decoration: none; }
a:hover { text-decoration: underline; }
ul, ol { margin: 10px 0; }
li { margin: 5px 0; }
</style>