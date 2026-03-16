<?php
/**
 * Debug Database Error
 * Comprehensive database connectivity and query testing
 */

require_once __DIR__ . '/config/config.php';

echo "<h1>🔍 Debug Database Error</h1>";

// Enable detailed error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h3>1. Configuration Check</h3>";

// Check database configuration
echo "<strong>Database Configuration:</strong><br>";
echo "Host: " . DB_HOST . "<br>";
echo "User: " . DB_USER . "<br>";
echo "Database: " . DB_NAME . "<br>";
echo "Password: " . (empty(DB_PASS) ? 'Empty' : 'Set (****)') . "<br><br>";

echo "<h3>2. Direct MySQLi Connection Test</h3>";

try {
    // Test direct MySQLi connection
    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($mysqli->connect_error) {
        echo "❌ MySQLi Connection failed: " . $mysqli->connect_error . "<br>";
        echo "<strong>Error Code:</strong> " . $mysqli->connect_errno . "<br>";
    } else {
        echo "✅ MySQLi Connection successful<br>";
        echo "Server version: " . $mysqli->server_info . "<br>";
        echo "Character set: " . $mysqli->character_set_name() . "<br>";
        
        // Test simple query
        $result = $mysqli->query("SELECT 1 as test");
        if ($result) {
            echo "✅ Simple query test passed<br>";
            $result->close();
        } else {
            echo "❌ Simple query failed: " . $mysqli->error . "<br>";
        }
        
        $mysqli->close();
    }
} catch (Exception $e) {
    echo "❌ MySQLi Exception: " . $e->getMessage() . "<br>";
}

echo "<h3>3. Database Class Connection Test</h3>";

try {
    require_once __DIR__ . '/classes/Database.php';
    
    echo "✅ Database class loaded<br>";
    
    $db = Database::getInstance();
    echo "✅ Database instance created<br>";
    
    $connection = $db->getConnection();
    if ($connection) {
        echo "✅ Database connection obtained<br>";
        echo "Connection ID: " . $connection->thread_id . "<br>";
    } else {
        echo "❌ Database connection is null<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Database class error: " . $e->getMessage() . "<br>";
    echo "File: " . $e->getFile() . "<br>";
    echo "Line: " . $e->getLine() . "<br>";
}

echo "<h3>4. Database Existence Check</h3>";

try {
    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS);
    
    if (!$mysqli->connect_error) {
        // Check if database exists
        $result = $mysqli->query("SHOW DATABASES LIKE '" . DB_NAME . "'");
        
        if ($result && $result->num_rows > 0) {
            echo "✅ Database '" . DB_NAME . "' exists<br>";
            $result->close();
            
            // Select the database
            $mysqli->select_db(DB_NAME);
            
            // Check tables
            $tablesResult = $mysqli->query("SHOW TABLES");
            if ($tablesResult) {
                echo "✅ Can access database tables<br>";
                echo "Tables found: " . $tablesResult->num_rows . "<br>";
                
                echo "<strong>Available tables:</strong><br>";
                while ($row = $tablesResult->fetch_array()) {
                    echo "- " . $row[0] . "<br>";
                }
                $tablesResult->close();
            } else {
                echo "❌ Cannot access tables: " . $mysqli->error . "<br>";
            }
            
        } else {
            echo "❌ Database '" . DB_NAME . "' does not exist<br>";
            echo "<strong>Available databases:</strong><br>";
            
            $dbResult = $mysqli->query("SHOW DATABASES");
            if ($dbResult) {
                while ($row = $dbResult->fetch_array()) {
                    echo "- " . $row[0] . "<br>";
                }
                $dbResult->close();
            }
        }
    }
    
    $mysqli->close();
    
} catch (Exception $e) {
    echo "❌ Database check error: " . $e->getMessage() . "<br>";
}

echo "<h3>5. Query Testing with Database Class</h3>";

try {
    $db = Database::getInstance();
    
    echo "<h4>Test 1: Simple SELECT query</h4>";
    try {
        $result = $db->fetchOne("SELECT 1 as test_value, NOW() as current_time");
        if ($result) {
            echo "✅ Simple query successful<br>";
            echo "Result: " . json_encode($result) . "<br>";
        } else {
            echo "❌ Simple query returned null<br>";
        }
    } catch (Exception $e) {
        echo "❌ Simple query failed: " . $e->getMessage() . "<br>";
        echo "Last query: " . $db->getLastQueryFormatted() . "<br>";
    }
    
    echo "<h4>Test 2: Check users table</h4>";
    try {
        $result = $db->fetchOne("SELECT COUNT(*) as count FROM users");
        if ($result) {
            echo "✅ Users table query successful<br>";
            echo "Users count: " . $result['count'] . "<br>";
        } else {
            echo "❌ Users table query returned null<br>";
        }
    } catch (Exception $e) {
        echo "❌ Users table query failed: " . $e->getMessage() . "<br>";
        echo "Last query: " . $db->getLastQueryFormatted() . "<br>";
        
        // Check if table exists
        try {
            $tableExists = $db->tableExists('users');
            echo "Users table exists: " . ($tableExists ? 'Yes' : 'No') . "<br>";
        } catch (Exception $te) {
            echo "Table existence check failed: " . $te->getMessage() . "<br>";
        }
    }
    
    echo "<h4>Test 3: Prepared statement with parameters</h4>";
    try {
        $result = $db->fetchOne("SELECT ? as test_param, ? as test_number", ['hello', 123]);
        if ($result) {
            echo "✅ Prepared statement successful<br>";
            echo "Result: " . json_encode($result) . "<br>";
        } else {
            echo "❌ Prepared statement returned null<br>";
        }
    } catch (Exception $e) {
        echo "❌ Prepared statement failed: " . $e->getMessage() . "<br>";
        echo "Last query: " . $db->getLastQueryFormatted() . "<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Database class testing error: " . $e->getMessage() . "<br>";
}

echo "<h3>6. MySQL Server Information</h3>";

try {
    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if (!$mysqli->connect_error) {
        echo "<strong>MySQL Server Info:</strong><br>";
        echo "Version: " . $mysqli->server_info . "<br>";
        echo "Protocol: " . $mysqli->protocol_version . "<br>";
        echo "Host info: " . $mysqli->host_info . "<br>";
        
        // Check MySQL variables
        $result = $mysqli->query("SHOW VARIABLES LIKE 'max_connections'");
        if ($result) {
            $row = $result->fetch_assoc();
            echo "Max connections: " . $row['Value'] . "<br>";
            $result->close();
        }
        
        $result = $mysqli->query("SHOW VARIABLES LIKE 'wait_timeout'");
        if ($result) {
            $row = $result->fetch_assoc();
            echo "Wait timeout: " . $row['Value'] . " seconds<br>";
            $result->close();
        }
        
        $mysqli->close();
    }
    
} catch (Exception $e) {
    echo "❌ MySQL info error: " . $e->getMessage() . "<br>";
}

echo "<h3>7. Create Missing Database/Tables</h3>";

if (isset($_POST['create_database'])) {
    try {
        $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS);
        
        if (!$mysqli->connect_error) {
            // Create database
            $createDB = "CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
            
            if ($mysqli->query($createDB)) {
                echo "✅ Database created/verified: " . DB_NAME . "<br>";
                
                // Select database
                $mysqli->select_db(DB_NAME);
                
                // Create users table
                $createUsersTable = "
                CREATE TABLE IF NOT EXISTS users (
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
                
                if ($mysqli->query($createUsersTable)) {
                    echo "✅ Users table created/verified<br>";
                } else {
                    echo "❌ Users table creation failed: " . $mysqli->error . "<br>";
                }
                
                // Create sales_data table
                $createSalesTable = "
                CREATE TABLE IF NOT EXISTS sales_data (
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
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
                
                if ($mysqli->query($createSalesTable)) {
                    echo "✅ Sales data table created/verified<br>";
                } else {
                    echo "❌ Sales data table creation failed: " . $mysqli->error . "<br>";
                }
                
            } else {
                echo "❌ Database creation failed: " . $mysqli->error . "<br>";
            }
        }
        
        $mysqli->close();
        
    } catch (Exception $e) {
        echo "❌ Database creation error: " . $e->getMessage() . "<br>";
    }
}

echo "<form method='POST' style='background: #f8f9fa; padding: 15px; border-radius: 4px; margin: 15px 0;'>";
echo "<h4>Create Missing Database/Tables:</h4>";
echo "<button type='submit' name='create_database' style='padding: 10px 20px; background: #28a745; color: white; border: none; border-radius: 4px;'>Create Database & Tables</button>";
echo "</form>";

echo "<h3>8. Troubleshooting Steps</h3>";

echo "<div style='background: #fff3cd; padding: 15px; border-radius: 4px;'>";
echo "<h4>If errors persist:</h4>";
echo "<ol>";
echo "<li><strong>Check XAMPP:</strong> Ensure Apache and MySQL services are running</li>";
echo "<li><strong>Check MySQL:</strong> Try accessing phpMyAdmin at <a href='http://localhost/phpmyadmin' target='_blank'>http://localhost/phpmyadmin</a></li>";
echo "<li><strong>Check Config:</strong> Verify database settings in config/config.php</li>";
echo "<li><strong>Check Permissions:</strong> Ensure MySQL user has proper permissions</li>";
echo "<li><strong>Restart Services:</strong> Restart Apache and MySQL in XAMPP</li>";
echo "</ol>";
echo "</div>";

echo "<h3>9. Quick Links</h3>";
echo "<a href='quick_login_fix.php' style='margin: 5px; padding: 10px 15px; background: #007bff; color: white; text-decoration: none; border-radius: 4px;'>Quick Login Fix</a> ";
echo "<a href='test_system.php' style='margin: 5px; padding: 10px 15px; background: #17a2b8; color: white; text-decoration: none; border-radius: 4px;'>System Test</a> ";
echo "<a href='http://localhost/phpmyadmin' target='_blank' style='margin: 5px; padding: 10px 15px; background: #6c757d; color: white; text-decoration: none; border-radius: 4px;'>phpMyAdmin</a>";
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; background: #f8f9fa; }
h1, h3, h4 { color: #333; }
code { background: #f1f3f4; padding: 2px 6px; border-radius: 3px; }
a { color: #007bff; text-decoration: none; }
a:hover { text-decoration: underline; }
ul, ol { margin: 10px 0; }
li { margin: 5px 0; }
</style>