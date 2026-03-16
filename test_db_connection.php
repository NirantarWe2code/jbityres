<?php
/**
 * Simple Database Connection Test
 */

echo "<h1>🔍 Simple Database Connection Test</h1>";

// Test 1: Check if constants are defined
echo "<h3>1. Configuration Check</h3>";

if (file_exists(__DIR__ . '/config/config.php')) {
    require_once __DIR__ . '/config/config.php';
    echo "✅ Config file loaded<br>";
    
    $constants = ['DB_HOST', 'DB_USER', 'DB_PASS', 'DB_NAME'];
    foreach ($constants as $const) {
        if (defined($const)) {
            $value = constant($const);
            if ($const === 'DB_PASS') {
                $value = empty($value) ? 'Empty' : '****';
            }
            echo "✅ $const: $value<br>";
        } else {
            echo "❌ $const: Not defined<br>";
        }
    }
} else {
    echo "❌ Config file not found<br>";
    exit;
}

// Test 2: Direct MySQLi connection
echo "<h3>2. Direct MySQLi Connection</h3>";

try {
    $connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($connection->connect_error) {
        echo "❌ Connection failed: " . $connection->connect_error . "<br>";
        echo "Error number: " . $connection->connect_errno . "<br>";
        
        // Try without database name
        echo "<h4>Trying connection without database name...</h4>";
        $connection2 = new mysqli(DB_HOST, DB_USER, DB_PASS);
        
        if ($connection2->connect_error) {
            echo "❌ Basic connection also failed: " . $connection2->connect_error . "<br>";
        } else {
            echo "✅ Basic connection successful<br>";
            
            // Check if database exists
            $result = $connection2->query("SHOW DATABASES LIKE '" . DB_NAME . "'");
            if ($result && $result->num_rows > 0) {
                echo "✅ Database '" . DB_NAME . "' exists<br>";
            } else {
                echo "❌ Database '" . DB_NAME . "' does not exist<br>";
                echo "<strong>Available databases:</strong><br>";
                
                $dbResult = $connection2->query("SHOW DATABASES");
                if ($dbResult) {
                    while ($row = $dbResult->fetch_array()) {
                        echo "- " . $row[0] . "<br>";
                    }
                }
            }
        }
        $connection2->close();
        
    } else {
        echo "✅ Connection successful<br>";
        echo "Server version: " . $connection->server_info . "<br>";
        
        // Test simple query
        $result = $connection->query("SELECT 1 as test");
        if ($result) {
            echo "✅ Simple query successful<br>";
        } else {
            echo "❌ Simple query failed: " . $connection->error . "<br>";
        }
        
        $connection->close();
    }
    
} catch (Exception $e) {
    echo "❌ Exception: " . $e->getMessage() . "<br>";
}

// Test 3: Database class
echo "<h3>3. Database Class Test</h3>";

try {
    if (file_exists(__DIR__ . '/classes/Database.php')) {
        require_once __DIR__ . '/classes/Database.php';
        echo "✅ Database class file loaded<br>";
        
        $db = Database::getInstance();
        echo "✅ Database instance created<br>";
        
        $connection = $db->getConnection();
        if ($connection) {
            echo "✅ Database connection obtained<br>";
        } else {
            echo "❌ Database connection is null<br>";
        }
        
    } else {
        echo "❌ Database class file not found<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Database class error: " . $e->getMessage() . "<br>";
    echo "File: " . $e->getFile() . "<br>";
    echo "Line: " . $e->getLine() . "<br>";
}

echo "<h3>4. Solutions</h3>";

echo "<div style='background: #fff3cd; padding: 15px; border-radius: 4px; margin: 15px 0;'>";
echo "<h4>If you see connection errors:</h4>";
echo "<ol>";
echo "<li><strong>Check XAMPP:</strong> Make sure Apache and MySQL are running (green in XAMPP Control Panel)</li>";
echo "<li><strong>Check Database:</strong> Go to <a href='http://localhost/phpmyadmin' target='_blank'>phpMyAdmin</a> and verify 'sales_reports' database exists</li>";
echo "<li><strong>Create Database:</strong> If database doesn't exist, create it manually in phpMyAdmin</li>";
echo "<li><strong>Check Credentials:</strong> Default XAMPP MySQL credentials are usually root/empty password</li>";
echo "<li><strong>Restart Services:</strong> Stop and start MySQL service in XAMPP</li>";
echo "</ol>";
echo "</div>";

echo "<h3>5. Quick Actions</h3>";
echo "<a href='debug_database_error.php' style='margin: 5px; padding: 10px 15px; background: #007bff; color: white; text-decoration: none; border-radius: 4px;'>Detailed Database Debug</a> ";
echo "<a href='http://localhost/phpmyadmin' target='_blank' style='margin: 5px; padding: 10px 15px; background: #28a745; color: white; text-decoration: none; border-radius: 4px;'>Open phpMyAdmin</a> ";

if (isset($_GET['create_db'])) {
    echo "<h3>6. Creating Database</h3>";
    
    try {
        $connection = new mysqli(DB_HOST, DB_USER, DB_PASS);
        
        if (!$connection->connect_error) {
            $createDB = "CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "`";
            
            if ($connection->query($createDB)) {
                echo "✅ Database '" . DB_NAME . "' created successfully<br>";
                echo "<a href='test_db_connection.php'>Test Connection Again</a><br>";
            } else {
                echo "❌ Failed to create database: " . $connection->error . "<br>";
            }
        } else {
            echo "❌ Cannot connect to create database: " . $connection->connect_error . "<br>";
        }
        
        $connection->close();
        
    } catch (Exception $e) {
        echo "❌ Database creation error: " . $e->getMessage() . "<br>";
    }
} else {
    echo "<a href='test_db_connection.php?create_db=1' style='margin: 5px; padding: 10px 15px; background: #dc3545; color: white; text-decoration: none; border-radius: 4px;'>Create Database</a>";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; background: #f8f9fa; }
h1, h3, h4 { color: #333; }
a { color: #007bff; text-decoration: none; }
a:hover { text-decoration: underline; }
ol, ul { margin: 10px 0; }
li { margin: 5px 0; }
</style>