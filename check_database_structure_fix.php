<?php
/**
 * Check Database Structure vs Code Requirements
 * Compare actual database with what code expects
 */

require_once __DIR__ . '/config/config.php';

echo "<h1>🔍 Database Structure Analysis</h1>";

try {
    // Direct MySQLi connection to check raw structure
    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($mysqli->connect_error) {
        throw new Exception("Connection failed: " . $mysqli->connect_error);
    }
    
    echo "<h3>1. Current Database: " . DB_NAME . "</h3>";
    
    // Check if database exists
    $dbExists = $mysqli->select_db(DB_NAME);
    if (!$dbExists) {
        echo "❌ Database '" . DB_NAME . "' does not exist<br>";
        echo "<p>Please create the database first or run <a href='auto_fix_database.php'>Auto Fix Database</a></p>";
        exit;
    }
    
    echo "✅ Database exists<br>";
    
    echo "<h3>2. Tables in Database</h3>";
    
    // Get all tables
    $result = $mysqli->query("SHOW TABLES");
    $tables = [];
    
    if ($result) {
        echo "<ul>";
        while ($row = $result->fetch_array()) {
            $tableName = $row[0];
            $tables[] = $tableName;
            echo "<li><strong>$tableName</strong></li>";
        }
        echo "</ul>";
    } else {
        echo "❌ No tables found or error: " . $mysqli->error . "<br>";
    }
    
    echo "<h3>3. Users Table Analysis</h3>";
    
    if (in_array('users', $tables)) {
        echo "<h4>Current Users Table Structure:</h4>";
        
        $result = $mysqli->query("DESCRIBE users");
        if ($result) {
            echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 10px 0;'>";
            echo "<tr style='background: #f8f9fa; font-weight: bold;'>";
            echo "<td style='padding: 8px;'>Field</td>";
            echo "<td style='padding: 8px;'>Type</td>";
            echo "<td style='padding: 8px;'>Null</td>";
            echo "<td style='padding: 8px;'>Key</td>";
            echo "<td style='padding: 8px;'>Default</td>";
            echo "<td style='padding: 8px;'>Extra</td>";
            echo "</tr>";
            
            $actualColumns = [];
            while ($row = $result->fetch_assoc()) {
                $actualColumns[] = $row['Field'];
                echo "<tr>";
                echo "<td style='padding: 8px;'>" . $row['Field'] . "</td>";
                echo "<td style='padding: 8px;'>" . $row['Type'] . "</td>";
                echo "<td style='padding: 8px;'>" . $row['Null'] . "</td>";
                echo "<td style='padding: 8px;'>" . $row['Key'] . "</td>";
                echo "<td style='padding: 8px;'>" . ($row['Default'] ?? 'NULL') . "</td>";
                echo "<td style='padding: 8px;'>" . $row['Extra'] . "</td>";
                echo "</tr>";
            }
            echo "</table>";
            
            // Check what code expects vs what exists
            echo "<h4>Code Requirements vs Actual Structure:</h4>";
            
            $expectedColumns = [
                'id' => 'Primary key',
                'username' => 'Login username',
                'password' => 'Hashed password',
                'full_name' => 'User full name',
                'email' => 'User email',
                'role' => 'User role (super_admin, admin, user)',
                'status' => 'User status (active, inactive)',
                'created_at' => 'Creation timestamp',
                'updated_at' => 'Update timestamp',
                'last_login' => 'Last login timestamp'
            ];
            
            echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 10px 0;'>";
            echo "<tr style='background: #f8f9fa; font-weight: bold;'>";
            echo "<td style='padding: 8px;'>Expected Column</td>";
            echo "<td style='padding: 8px;'>Purpose</td>";
            echo "<td style='padding: 8px;'>Status</td>";
            echo "</tr>";
            
            foreach ($expectedColumns as $column => $purpose) {
                $exists = in_array($column, $actualColumns);
                echo "<tr>";
                echo "<td style='padding: 8px;'><strong>$column</strong></td>";
                echo "<td style='padding: 8px;'>$purpose</td>";
                echo "<td style='padding: 8px;'>" . ($exists ? "✅ EXISTS" : "❌ MISSING") . "</td>";
                echo "</tr>";
            }
            echo "</table>";
            
        } else {
            echo "❌ Cannot describe users table: " . $mysqli->error . "<br>";
        }
        
        // Show sample data
        echo "<h4>Sample Users Data:</h4>";
        $result = $mysqli->query("SELECT * FROM users LIMIT 5");
        if ($result && $result->num_rows > 0) {
            echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 10px 0; font-size: 12px;'>";
            
            // Header
            $firstRow = true;
            while ($row = $result->fetch_assoc()) {
                if ($firstRow) {
                    echo "<tr style='background: #f8f9fa; font-weight: bold;'>";
                    foreach (array_keys($row) as $column) {
                        echo "<td style='padding: 6px;'>$column</td>";
                    }
                    echo "</tr>";
                    $firstRow = false;
                }
                
                echo "<tr>";
                foreach ($row as $value) {
                    $displayValue = is_null($value) ? 'NULL' : htmlspecialchars(substr($value, 0, 50));
                    echo "<td style='padding: 6px;'>$displayValue</td>";
                }
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "⚠️ No users found in table<br>";
        }
        
    } else {
        echo "❌ Users table does not exist<br>";
    }
    
    echo "<h3>4. Sales Data Table Analysis</h3>";
    
    if (in_array('sales_data', $tables)) {
        echo "<h4>Current Sales Data Table Structure:</h4>";
        
        $result = $mysqli->query("DESCRIBE sales_data");
        if ($result) {
            echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 10px 0;'>";
            echo "<tr style='background: #f8f9fa; font-weight: bold;'>";
            echo "<td style='padding: 8px;'>Field</td>";
            echo "<td style='padding: 8px;'>Type</td>";
            echo "<td style='padding: 8px;'>Null</td>";
            echo "<td style='padding: 8px;'>Key</td>";
            echo "<td style='padding: 8px;'>Default</td>";
            echo "<td style='padding: 8px;'>Extra</td>";
            echo "</tr>";
            
            $actualSalesColumns = [];
            while ($row = $result->fetch_assoc()) {
                $actualSalesColumns[] = $row['Field'];
                echo "<tr>";
                echo "<td style='padding: 8px;'>" . $row['Field'] . "</td>";
                echo "<td style='padding: 8px;'>" . $row['Type'] . "</td>";
                echo "<td style='padding: 8px;'>" . $row['Null'] . "</td>";
                echo "<td style='padding: 8px;'>" . $row['Key'] . "</td>";
                echo "<td style='padding: 8px;'>" . ($row['Default'] ?? 'NULL') . "</td>";
                echo "<td style='padding: 8px;'>" . $row['Extra'] . "</td>";
                echo "</tr>";
            }
            echo "</table>";
            
            // Show sample data
            echo "<h4>Sample Sales Data:</h4>";
            $result = $mysqli->query("SELECT * FROM sales_data LIMIT 3");
            if ($result && $result->num_rows > 0) {
                echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 10px 0; font-size: 11px;'>";
                
                // Header
                $firstRow = true;
                while ($row = $result->fetch_assoc()) {
                    if ($firstRow) {
                        echo "<tr style='background: #f8f9fa; font-weight: bold;'>";
                        foreach (array_keys($row) as $column) {
                            echo "<td style='padding: 4px;'>$column</td>";
                        }
                        echo "</tr>";
                        $firstRow = false;
                    }
                    
                    echo "<tr>";
                    foreach ($row as $value) {
                        $displayValue = is_null($value) ? 'NULL' : htmlspecialchars(substr($value, 0, 30));
                        echo "<td style='padding: 4px;'>$displayValue</td>";
                    }
                    echo "</tr>";
                }
                echo "</table>";
            } else {
                echo "⚠️ No sales data found in table<br>";
            }
            
        } else {
            echo "❌ Cannot describe sales_data table: " . $mysqli->error . "<br>";
        }
        
    } else {
        echo "❌ Sales_data table does not exist<br>";
    }
    
    echo "<h3>5. Code Analysis</h3>";
    
    echo "<h4>Auth Class Expected Columns:</h4>";
    echo "<p>Based on the Auth class code, these columns are expected in users table:</p>";
    echo "<ul>";
    echo "<li><strong>id</strong> - User ID (primary key)</li>";
    echo "<li><strong>username</strong> - Login username</li>";
    echo "<li><strong>password</strong> - Hashed password</li>";
    echo "<li><strong>full_name</strong> - User's full name</li>";
    echo "<li><strong>email</strong> - User's email address</li>";
    echo "<li><strong>role</strong> - User role (super_admin, admin, user)</li>";
    echo "<li><strong>status</strong> - User status (active, inactive)</li>";
    echo "<li><strong>created_at</strong> - Creation timestamp</li>";
    echo "<li><strong>last_login</strong> - Last login timestamp</li>";
    echo "</ul>";
    
    echo "<h4>SalesData Class Expected Columns:</h4>";
    echo "<p>Based on the SalesData class code, these columns are expected in sales_data table:</p>";
    echo "<ul>";
    echo "<li><strong>id</strong> - Record ID (primary key)</li>";
    echo "<li><strong>invoice_num</strong> - Invoice number</li>";
    echo "<li><strong>dated</strong> - Sale date</li>";
    echo "<li><strong>business_name</strong> - Business/customer name</li>";
    echo "<li><strong>sales_rep</strong> - Sales representative</li>";
    echo "<li><strong>product</strong> - Product name</li>";
    echo "<li><strong>quantity</strong> - Quantity sold</li>";
    echo "<li><strong>unit_price</strong> - Unit price</li>";
    echo "<li><strong>line_revenue</strong> - Line revenue (calculated)</li>";
    echo "<li><strong>gross_profit</strong> - Gross profit</li>";
    echo "<li><strong>gp_margin</strong> - GP margin percentage (calculated)</li>";
    echo "<li><strong>notes</strong> - Additional notes</li>";
    echo "<li><strong>created_by</strong> - User who created record</li>";
    echo "<li><strong>created_at</strong> - Creation timestamp</li>";
    echo "<li><strong>updated_at</strong> - Update timestamp</li>";
    echo "</ul>";
    
    echo "<h3>6. Recommended Actions</h3>";
    
    echo "<div style='background: #fff3cd; padding: 15px; border-radius: 4px; margin: 15px 0;'>";
    echo "<h4>⚠️ Next Steps:</h4>";
    echo "<ol>";
    echo "<li><strong>Review Structure:</strong> Compare the actual table structure above with what the code expects</li>";
    echo "<li><strong>Fix Mismatches:</strong> Either update the database structure or modify the code to match</li>";
    echo "<li><strong>Run Auto Fix:</strong> <a href='auto_fix_database.php'>Run Auto Database Fix</a> to create proper structure</li>";
    echo "<li><strong>Test Login:</strong> After fixing, test the login system</li>";
    echo "</ol>";
    echo "</div>";
    
    $mysqli->close();
    
} catch (Exception $e) {
    echo "<h3>❌ Error:</h3>";
    echo "<div style='background: #f8d7da; padding: 15px; border-radius: 4px; color: #721c24;'>";
    echo "<strong>Error:</strong> " . $e->getMessage() . "<br>";
    echo "</div>";
    
    echo "<h4>Troubleshooting:</h4>";
    echo "<ul>";
    echo "<li>Check if XAMPP MySQL service is running</li>";
    echo "<li>Verify database credentials in config.php</li>";
    echo "<li>Check if database '" . DB_NAME . "' exists in phpMyAdmin</li>";
    echo "<li><a href='http://localhost/phpmyadmin' target='_blank'>Open phpMyAdmin</a></li>";
    echo "</ul>";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; background: #f8f9fa; }
h1, h3, h4 { color: #333; }
table { background: white; border-collapse: collapse; }
th, td { text-align: left; border: 1px solid #ddd; }
a { color: #007bff; text-decoration: none; }
a:hover { text-decoration: underline; }
ul, ol { margin: 10px 0; }
li { margin: 5px 0; }
code { background: #f1f3f4; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
</style>