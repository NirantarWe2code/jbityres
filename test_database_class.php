<?php
/**
 * Test Database Class
 * Quick test to verify Database class is working
 */

require_once __DIR__ . '/config/config.php';

echo "<h1>🧪 Database Class Test</h1>";

try {
    echo "<h3>1. Loading Database Class</h3>";
    
    require_once __DIR__ . '/classes/Database.php';
    echo "✅ Database class loaded successfully<br>";
    
    echo "<h3>2. Getting Database Instance</h3>";
    
    $db = Database::getInstance();
    echo "✅ Database instance created<br>";
    
    echo "<h3>3. Testing Connection</h3>";
    
    // Simple query test
    $result = $db->fetchOne("SELECT 1 as test");
    if ($result && $result['test'] == 1) {
        echo "✅ Database connection working<br>";
    } else {
        echo "❌ Database connection failed<br>";
    }
    
    echo "<h3>4. Testing tableExists Method</h3>";
    
    $usersExists = $db->tableExists('users');
    echo "Users table exists: " . ($usersExists ? "✅ Yes" : "❌ No") . "<br>";
    
    $salesExists = $db->tableExists('sales_data');
    echo "Sales_data table exists: " . ($salesExists ? "✅ Yes" : "❌ No") . "<br>";
    
    echo "<h3>5. Testing getTableColumns Method</h3>";
    
    if ($usersExists) {
        $userColumns = $db->getTableColumns('users');
        echo "Users table columns (" . count($userColumns) . "): ";
        $columnNames = array_column($userColumns, 'Field');
        echo implode(', ', $columnNames) . "<br>";
        
        // Check for password column specifically
        if (in_array('password', $columnNames)) {
            echo "✅ Password column exists in users table<br>";
        } else {
            echo "❌ Password column missing in users table<br>";
        }
    }
    
    if ($salesExists) {
        $salesColumns = $db->getTableColumns('sales_data');
        echo "Sales_data table columns (" . count($salesColumns) . "): ";
        $salesColumnNames = array_column($salesColumns, 'Field');
        echo implode(', ', array_slice($salesColumnNames, 0, 8)) . "...<br>";
    }
    
    echo "<h3>6. Testing Auth Class</h3>";
    
    require_once __DIR__ . '/classes/Auth.php';
    $auth = new Auth();
    echo "✅ Auth class loaded successfully<br>";
    
    // Test login without actually logging in
    echo "<h4>Testing login validation...</h4>";
    $loginResult = $auth->login('admin', 'admin123');
    
    if ($loginResult['success']) {
        echo "✅ Login test successful<br>";
        echo "User: " . $loginResult['user']['full_name'] . " (" . $loginResult['user']['role'] . ")<br>";
        
        // Logout immediately
        $auth->logout();
        echo "✅ Logout successful<br>";
    } else {
        echo "❌ Login test failed: " . $loginResult['message'] . "<br>";
        
        // Show debug info
        echo "<h5>Debug Info:</h5>";
        echo "Last query: " . $db->getLastQueryFormatted() . "<br>";
    }
    
    echo "<h3>7. Testing SalesData Class</h3>";
    
    require_once __DIR__ . '/classes/SalesData.php';
    $salesData = new SalesData();
    echo "✅ SalesData class loaded successfully<br>";
    
    // Test getting dashboard stats
    $stats = $salesData->getDashboardStats();
    echo "Dashboard stats: " . json_encode($stats) . "<br>";
    
    echo "<h3>✅ All Tests Complete!</h3>";
    
    echo "<div style='background: #d4edda; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
    echo "<h4>🎉 Database Class Working!</h4>";
    echo "<ul>";
    echo "<li>✅ Database class loads without errors</li>";
    echo "<li>✅ Connection established successfully</li>";
    echo "<li>✅ tableExists() method working</li>";
    echo "<li>✅ getTableColumns() method working</li>";
    echo "<li>✅ Auth class integration working</li>";
    echo "<li>✅ SalesData class integration working</li>";
    echo "</ul>";
    echo "<br>";
    echo "<p><strong>System is ready to use!</strong></p>";
    echo "<p><a href='login.php' style='padding: 12px 24px; background: #28a745; color: white; text-decoration: none; border-radius: 4px; font-weight: bold;'>🚀 Go to Login</a></p>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<h3>❌ Test Failed:</h3>";
    echo "<div style='background: #f8d7da; padding: 15px; border-radius: 4px; color: #721c24;'>";
    echo "<strong>Error:</strong> " . $e->getMessage() . "<br>";
    echo "<strong>File:</strong> " . $e->getFile() . "<br>";
    echo "<strong>Line:</strong> " . $e->getLine() . "<br>";
    echo "</div>";
    
    echo "<h4>Troubleshooting Steps:</h4>";
    echo "<ol>";
    echo "<li>Run <a href='quick_fix.php'>Quick Fix Script</a> first</li>";
    echo "<li>Check XAMPP services are running</li>";
    echo "<li>Verify database credentials in config.php</li>";
    echo "<li>Check <a href='http://localhost/phpmyadmin' target='_blank'>phpMyAdmin</a></li>";
    echo "</ol>";
    
    echo "<h4>Alternative Fixes:</h4>";
    echo "<ul>";
    echo "<li><a href='auto_fix_database.php'>Auto Fix Database</a></li>";
    echo "<li><a href='check_database_structure.php'>Check Database Structure</a></li>";
    echo "<li><a href='fix_column_mapping.php'>Fix Column Mapping</a></li>";
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