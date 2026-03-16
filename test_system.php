<?php
/**
 * System Test Script
 * Comprehensive testing of the Final Report System
 */

require_once __DIR__ . '/config/config.php';

echo "<h1>🧪 Final Report System - Comprehensive Test</h1>";

$testResults = [];
$overallSuccess = true;

// Test 1: Configuration
echo "<h2>1. Configuration Test</h2>";
try {
    if (defined('DB_HOST') && defined('DB_USER') && defined('DB_NAME')) {
        echo "✅ Configuration constants defined<br>";
        $testResults['config'] = true;
    } else {
        throw new Exception("Missing configuration constants");
    }
} catch (Exception $e) {
    echo "❌ Configuration error: " . $e->getMessage() . "<br>";
    $testResults['config'] = false;
    $overallSuccess = false;
}

// Test 2: Database Connection
echo "<h2>2. Database Connection Test</h2>";
try {
    require_once __DIR__ . '/classes/Database.php';
    $db = Database::getInstance();
    
    if ($db->getConnection()) {
        echo "✅ Database connection successful<br>";
        $testResults['database'] = true;
    } else {
        throw new Exception("Database connection failed");
    }
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "<br>";
    $testResults['database'] = false;
    $overallSuccess = false;
}

// Test 3: Required Tables
echo "<h2>3. Database Tables Test</h2>";
try {
    $requiredTables = ['users', 'sales_data'];
    $db = Database::getInstance();
    
    foreach ($requiredTables as $table) {
        if ($db->tableExists($table)) {
            echo "✅ Table '$table' exists<br>";
        } else {
            throw new Exception("Table '$table' not found");
        }
    }
    $testResults['tables'] = true;
} catch (Exception $e) {
    echo "❌ Tables error: " . $e->getMessage() . "<br>";
    $testResults['tables'] = false;
    $overallSuccess = false;
}

// Test 4: PHP Classes
echo "<h2>4. PHP Classes Test</h2>";
try {
    $classes = [
        'Database' => __DIR__ . '/classes/Database.php',
        'Auth' => __DIR__ . '/classes/Auth.php',
        'SalesData' => __DIR__ . '/classes/SalesData.php'
    ];
    
    foreach ($classes as $className => $file) {
        if (file_exists($file)) {
            require_once $file;
            if (class_exists($className)) {
                echo "✅ Class '$className' loaded successfully<br>";
            } else {
                throw new Exception("Class '$className' not found in file");
            }
        } else {
            throw new Exception("File '$file' not found");
        }
    }
    $testResults['classes'] = true;
} catch (Exception $e) {
    echo "❌ Classes error: " . $e->getMessage() . "<br>";
    $testResults['classes'] = false;
    $overallSuccess = false;
}

// Test 5: Authentication System
echo "<h2>5. Authentication System Test</h2>";
try {
    $auth = new Auth();
    
    // Test user count
    $userCountResult = $auth->getAllUsers(1, 1);
    if ($userCountResult['success']) {
        echo "✅ Authentication system working<br>";
        echo "📊 Total users in system: " . $userCountResult['pagination']['total_records'] . "<br>";
        $testResults['auth'] = true;
    } else {
        throw new Exception("Authentication system error");
    }
} catch (Exception $e) {
    echo "❌ Authentication error: " . $e->getMessage() . "<br>";
    $testResults['auth'] = false;
    $overallSuccess = false;
}

// Test 6: Sales Data System
echo "<h2>6. Sales Data System Test</h2>";
try {
    $salesData = new SalesData();
    
    // Test sales data retrieval
    $salesResult = $salesData->getAllRecords(1, 1);
    if ($salesResult['success']) {
        echo "✅ Sales data system working<br>";
        echo "📊 Total sales records: " . $salesResult['pagination']['total_records'] . "<br>";
        $testResults['sales'] = true;
    } else {
        throw new Exception("Sales data system error");
    }
} catch (Exception $e) {
    echo "❌ Sales data error: " . $e->getMessage() . "<br>";
    $testResults['sales'] = false;
    $overallSuccess = false;
}

// Test 7: File Structure
echo "<h2>7. File Structure Test</h2>";
try {
    $requiredFiles = [
        'login.php',
        'logout.php',
        'index.php',
        'pages/dashboard/index.php',
        'pages/sales/index.php',
        'pages/users/index.php',
        'ajax/dashboard.php',
        'ajax/sales.php',
        'ajax/users.php',
        'assets/css/style.css',
        'assets/js/common.js',
        'assets/js/dashboard.js',
        'assets/js/sales.js',
        'assets/js/users.js',
        'includes/header.php',
        'includes/footer.php'
    ];
    
    $missingFiles = [];
    foreach ($requiredFiles as $file) {
        if (!file_exists(__DIR__ . '/' . $file)) {
            $missingFiles[] = $file;
        }
    }
    
    if (empty($missingFiles)) {
        echo "✅ All required files present (" . count($requiredFiles) . " files)<br>";
        $testResults['files'] = true;
    } else {
        throw new Exception("Missing files: " . implode(', ', $missingFiles));
    }
} catch (Exception $e) {
    echo "❌ File structure error: " . $e->getMessage() . "<br>";
    $testResults['files'] = false;
    $overallSuccess = false;
}

// Test 8: AJAX Endpoints
echo "<h2>8. AJAX Endpoints Test</h2>";
try {
    $ajaxFiles = [
        'ajax/dashboard.php',
        'ajax/sales.php',
        'ajax/users.php'
    ];
    
    foreach ($ajaxFiles as $file) {
        if (file_exists(__DIR__ . '/' . $file)) {
            echo "✅ AJAX endpoint '$file' exists<br>";
        } else {
            throw new Exception("AJAX endpoint '$file' not found");
        }
    }
    $testResults['ajax'] = true;
} catch (Exception $e) {
    echo "❌ AJAX endpoints error: " . $e->getMessage() . "<br>";
    $testResults['ajax'] = false;
    $overallSuccess = false;
}

// Test 9: Session Functions
echo "<h2>9. Session Functions Test</h2>";
try {
    // Test session functions
    if (function_exists('generateCSRFToken') && 
        function_exists('verifyCSRFToken') && 
        function_exists('isLoggedIn') && 
        function_exists('getCurrentUser')) {
        
        $token = generateCSRFToken();
        if (!empty($token)) {
            echo "✅ Session functions working<br>";
            echo "🔐 CSRF token generated: " . substr($token, 0, 10) . "...<br>";
            $testResults['session'] = true;
        } else {
            throw new Exception("CSRF token generation failed");
        }
    } else {
        throw new Exception("Session functions not found");
    }
} catch (Exception $e) {
    echo "❌ Session functions error: " . $e->getMessage() . "<br>";
    $testResults['session'] = false;
    $overallSuccess = false;
}

// Test 10: Permissions System
echo "<h2>10. Permissions System Test</h2>";
try {
    if (function_exists('hasPermission') && 
        function_exists('requireAuth') && 
        function_exists('requirePermission')) {
        
        echo "✅ Permission functions available<br>";
        
        // Test role constants
        if (defined('ROLE_SUPER_ADMIN') && 
            defined('ROLE_ADMIN') && 
            defined('ROLE_USER')) {
            echo "✅ Role constants defined<br>";
            $testResults['permissions'] = true;
        } else {
            throw new Exception("Role constants not defined");
        }
    } else {
        throw new Exception("Permission functions not found");
    }
} catch (Exception $e) {
    echo "❌ Permissions system error: " . $e->getMessage() . "<br>";
    $testResults['permissions'] = false;
    $overallSuccess = false;
}

// Summary
echo "<h2>📋 Test Summary</h2>";
echo "<div class='test-summary'>";

$passedTests = array_sum($testResults);
$totalTests = count($testResults);

echo "<p><strong>Tests Passed:</strong> $passedTests / $totalTests</p>";

if ($overallSuccess) {
    echo "<div class='alert alert-success'>";
    echo "<h3>🎉 All Tests Passed!</h3>";
    echo "<p>Your Final Report System is ready to use.</p>";
    echo "<p><strong>Next Steps:</strong></p>";
    echo "<ol>";
    echo "<li>Run <a href='setup.php'>setup.php</a> to create default users (if not done already)</li>";
    echo "<li>Access the system at <a href='index.php'>index.php</a></li>";
    echo "<li>Login with default credentials (admin/admin123)</li>";
    echo "</ol>";
    echo "</div>";
} else {
    echo "<div class='alert alert-danger'>";
    echo "<h3>❌ Some Tests Failed</h3>";
    echo "<p>Please fix the issues above before using the system.</p>";
    echo "<p><strong>Common Solutions:</strong></p>";
    echo "<ul>";
    echo "<li>Check database connection settings in config/config.php</li>";
    echo "<li>Ensure all required files are uploaded</li>";
    echo "<li>Verify MySQL service is running</li>";
    echo "<li>Check file permissions</li>";
    echo "</ul>";
    echo "</div>";
}

echo "</div>";

// Test Results Details
echo "<h3>📊 Detailed Results</h3>";
echo "<table class='table table-striped'>";
echo "<thead><tr><th>Test</th><th>Status</th><th>Description</th></tr></thead>";
echo "<tbody>";

$testDescriptions = [
    'config' => 'Configuration constants and settings',
    'database' => 'Database connection and MySQLi',
    'tables' => 'Required database tables exist',
    'classes' => 'PHP classes load correctly',
    'auth' => 'Authentication system functionality',
    'sales' => 'Sales data management system',
    'files' => 'All required files present',
    'ajax' => 'AJAX endpoint files exist',
    'session' => 'Session management functions',
    'permissions' => 'Role-based permission system'
];

foreach ($testResults as $test => $result) {
    $status = $result ? '✅ Pass' : '❌ Fail';
    $class = $result ? 'text-success' : 'text-danger';
    $description = $testDescriptions[$test] ?? 'Unknown test';
    
    echo "<tr>";
    echo "<td><strong>" . ucfirst($test) . "</strong></td>";
    echo "<td class='$class'>$status</td>";
    echo "<td>$description</td>";
    echo "</tr>";
}

echo "</tbody></table>";

echo "<h3>🔗 Quick Links</h3>";
echo "<div class='quick-links'>";
echo "<a href='setup.php' class='btn btn-primary'>Run Setup</a> ";
echo "<a href='index.php' class='btn btn-success'>Access System</a> ";
echo "<a href='login.php' class='btn btn-info'>Login Page</a> ";
echo "</div>";
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; background: #f8f9fa; }
h1, h2, h3 { color: #333; }
.alert { padding: 15px; margin: 15px 0; border-radius: 4px; }
.alert-success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
.alert-danger { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
.table { width: 100%; border-collapse: collapse; margin: 15px 0; background: white; }
.table th, .table td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
.table th { background: #f8f9fa; font-weight: bold; }
.table-striped tbody tr:nth-child(odd) { background: #f9f9f9; }
.text-success { color: #28a745; }
.text-danger { color: #dc3545; }
.btn { padding: 10px 20px; margin: 5px; text-decoration: none; border-radius: 4px; display: inline-block; }
.btn-primary { background: #007bff; color: white; }
.btn-success { background: #28a745; color: white; }
.btn-info { background: #17a2b8; color: white; }
.btn:hover { opacity: 0.8; }
.quick-links { margin: 20px 0; }
.test-summary { background: white; padding: 20px; border-radius: 8px; margin: 15px 0; }
</style>