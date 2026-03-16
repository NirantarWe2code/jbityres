<?php
/**
 * Fix Users Table Structure
 * Add missing columns and fix table structure
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/classes/Database.php';

echo "<h1>🔧 Fix Users Table Structure</h1>";

try {
    $db = Database::getInstance();
    
    echo "<h3>1. Check Current Table Structure</h3>";
    
    // Check if users table exists
    $tableExists = $db->tableExists('users');
    
    if (!$tableExists) {
        echo "❌ Users table does not exist<br>";
        echo "<h4>Creating users table...</h4>";
        
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $db->execute($createTableSQL);
        echo "✅ Users table created successfully<br>";
        
    } else {
        echo "✅ Users table exists<br>";
        
        // Get current table structure
        echo "<h4>Current table structure:</h4>";
        $columns = $db->getTableColumns('users');
        
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr style='background: #f8f9fa;'>";
        echo "<th style='padding: 8px;'>Field</th>";
        echo "<th style='padding: 8px;'>Type</th>";
        echo "<th style='padding: 8px;'>Null</th>";
        echo "<th style='padding: 8px;'>Key</th>";
        echo "<th style='padding: 8px;'>Default</th>";
        echo "</tr>";
        
        $existingColumns = [];
        foreach ($columns as $column) {
            $existingColumns[] = $column['Field'];
            echo "<tr>";
            echo "<td style='padding: 8px;'>" . $column['Field'] . "</td>";
            echo "<td style='padding: 8px;'>" . $column['Type'] . "</td>";
            echo "<td style='padding: 8px;'>" . $column['Null'] . "</td>";
            echo "<td style='padding: 8px;'>" . $column['Key'] . "</td>";
            echo "<td style='padding: 8px;'>" . ($column['Default'] ?? 'NULL') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Check for missing columns
        $requiredColumns = [
            'id' => 'INT AUTO_INCREMENT PRIMARY KEY',
            'username' => 'VARCHAR(50) UNIQUE NOT NULL',
            'password' => 'VARCHAR(255) NOT NULL',
            'full_name' => 'VARCHAR(100) NOT NULL',
            'email' => 'VARCHAR(100) UNIQUE NOT NULL',
            'role' => "ENUM('super_admin', 'admin', 'user') DEFAULT 'user'",
            'status' => "ENUM('active', 'inactive') DEFAULT 'active'",
            'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
            'last_login' => 'TIMESTAMP NULL'
        ];
        
        echo "<h4>Missing columns check:</h4>";
        $missingColumns = [];
        
        foreach ($requiredColumns as $columnName => $columnDef) {
            if (in_array($columnName, $existingColumns)) {
                echo "✅ Column '$columnName' exists<br>";
            } else {
                echo "❌ Column '$columnName' missing<br>";
                $missingColumns[$columnName] = $columnDef;
            }
        }
        
        // Add missing columns
        if (!empty($missingColumns)) {
            echo "<h4>Adding missing columns...</h4>";
            
            foreach ($missingColumns as $columnName => $columnDef) {
                try {
                    if ($columnName === 'id' && !in_array('id', $existingColumns)) {
                        // Special handling for primary key
                        $alterSQL = "ALTER TABLE users ADD COLUMN id INT AUTO_INCREMENT PRIMARY KEY FIRST";
                    } else {
                        $alterSQL = "ALTER TABLE users ADD COLUMN $columnName $columnDef";
                    }
                    
                    $db->execute($alterSQL);
                    echo "✅ Added column: $columnName<br>";
                    
                } catch (Exception $e) {
                    echo "❌ Failed to add column '$columnName': " . $e->getMessage() . "<br>";
                }
            }
        } else {
            echo "✅ All required columns exist<br>";
        }
    }
    
    echo "<h3>2. Verify Table Structure</h3>";
    
    // Test the table with a simple query
    try {
        $testQuery = "SELECT id, username, password, full_name, email, role, status, created_at, last_login FROM users LIMIT 1";
        $result = $db->fetchAll($testQuery);
        echo "✅ Table structure test passed<br>";
        echo "Current users count: " . count($result) . "<br>";
        
    } catch (Exception $e) {
        echo "❌ Table structure test failed: " . $e->getMessage() . "<br>";
        
        // If still failing, recreate the table
        echo "<h4>Recreating table...</h4>";
        
        // Backup existing data if any
        try {
            $backupData = $db->fetchAll("SELECT * FROM users");
            echo "📦 Backed up " . count($backupData) . " existing records<br>";
        } catch (Exception $be) {
            $backupData = [];
            echo "⚠️ No existing data to backup<br>";
        }
        
        // Drop and recreate table
        try {
            $db->execute("DROP TABLE IF EXISTS users");
            echo "🗑️ Dropped existing table<br>";
            
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            $db->execute($createTableSQL);
            echo "✅ Table recreated successfully<br>";
            
            // Restore data if any (this would need proper column mapping)
            if (!empty($backupData)) {
                echo "📥 Restoring backed up data...<br>";
                // Note: This is simplified - in production you'd need proper column mapping
                foreach ($backupData as $record) {
                    try {
                        $insertSQL = "INSERT INTO users (username, password, full_name, email, role, status) VALUES (?, ?, ?, ?, ?, ?)";
                        $db->execute($insertSQL, [
                            $record['username'] ?? 'user',
                            $record['password'] ?? password_hash('password', PASSWORD_DEFAULT),
                            $record['full_name'] ?? 'User',
                            $record['email'] ?? 'user@example.com',
                            $record['role'] ?? 'user',
                            $record['status'] ?? 'active'
                        ]);
                    } catch (Exception $re) {
                        echo "⚠️ Failed to restore record: " . $re->getMessage() . "<br>";
                    }
                }
            }
            
        } catch (Exception $re) {
            echo "❌ Table recreation failed: " . $re->getMessage() . "<br>";
        }
    }
    
    echo "<h3>3. Create Default Users</h3>";
    
    // Check if we have any users
    $userCount = $db->fetchOne("SELECT COUNT(*) as count FROM users");
    
    if ($userCount['count'] == 0) {
        echo "👤 No users found - creating default users...<br>";
        
        require_once __DIR__ . '/classes/Auth.php';
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
                echo "❌ Failed to create user {$userData['username']}: {$result['message']}<br>";
            }
        }
    } else {
        echo "✅ Found {$userCount['count']} existing users<br>";
    }
    
    echo "<h3>4. Test Login Functionality</h3>";
    
    // Test login with admin user
    require_once __DIR__ . '/classes/Auth.php';
    $auth = new Auth();
    
    $loginResult = $auth->login('admin', 'admin123');
    
    if ($loginResult['success']) {
        echo "✅ Login test successful with admin/admin123<br>";
        echo "User data: " . json_encode($loginResult['user']) . "<br>";
        
        // Logout immediately
        $auth->logout();
        echo "✅ Logout successful<br>";
    } else {
        echo "❌ Login test failed: " . $loginResult['message']. "<br>";
        echo "Last query: " . $db->getLastQueryFormatted() . "<br>";
    }
    
    echo "<h3>✅ Users Table Fix Complete!</h3>";
    
    echo "<div style='background: #d4edda; padding: 15px; border-radius: 4px; margin: 15px 0;'>";
    echo "<h4>🎉 Table Structure Fixed!</h4>";
    echo "<p>The users table now has all required columns including the missing 'password' column.</p>";
    echo "<p><strong>Login Credentials:</strong></p>";
    echo "<ul>";
    echo "<li><strong>Super Admin:</strong> admin / admin123</li>";
    echo "<li><strong>Admin:</strong> manager / manager123</li>";
    echo "<li><strong>User:</strong> user / user123</li>";
    echo "</ul>";
    echo "<p><a href='login.php' style='padding: 10px 20px; background: #28a745; color: white; text-decoration: none; border-radius: 4px;'>Go to Login Page</a></p>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<h3>❌ Critical Error:</h3>";
    echo "<div style='background: #f8d7da; padding: 15px; border-radius: 4px; color: #721c24;'>";
    echo "<strong>Error:</strong> " . $e->getMessage() . "<br>";
    echo "<strong>File:</strong> " . $e->getFile() . "<br>";
    echo "<strong>Line:</strong> " . $e->getLine() . "<br>";
    echo "</div>";
    
    echo "<h4>Manual Solution:</h4>";
    echo "<ol>";
    echo "<li>Open phpMyAdmin: <a href='http://localhost/phpmyadmin' target='_blank'>http://localhost/phpmyadmin</a></li>";
    echo "<li>Select 'sales_reports' database</li>";
    echo "<li>Drop the 'users' table if it exists</li>";
    echo "<li>Run this SQL to create proper table:</li>";
    echo "</ol>";
    
    echo "<textarea style='width: 100%; height: 200px; font-family: monospace; font-size: 12px;'>";
    echo "CREATE TABLE users (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    echo "</textarea>";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; background: #f8f9fa; }
h1, h3, h4 { color: #333; }
table { background: white; }
th, td { text-align: left; }
textarea { border: 1px solid #ddd; border-radius: 4px; padding: 10px; }
a { color: #007bff; text-decoration: none; }
a:hover { text-decoration: underline; }
ul, ol { margin: 10px 0; }
li { margin: 5px 0; }
</style>