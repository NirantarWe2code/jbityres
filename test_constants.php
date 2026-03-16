<?php
/**
 * Quick Constants Test
 */

echo "<h1>🧪 Quick Constants Test</h1>";

echo "<h3>1. Before Config Include</h3>";

$constants = ['BASE_URL', 'DB_HOST', 'DB_NAME', 'APP_NAME'];

foreach ($constants as $const) {
    if (defined($const)) {
        echo "✅ $const: " . constant($const) . "<br>";
    } else {
        echo "❌ $const: Not defined<br>";
    }
}

echo "<h3>2. Loading Config</h3>";

try {
    require_once __DIR__ . '/config/config.php';
    echo "✅ Config loaded successfully<br>";
} catch (Exception $e) {
    echo "❌ Config loading error: " . $e->getMessage() . "<br>";
}

echo "<h3>3. After Config Include</h3>";

foreach ($constants as $const) {
    if (defined($const)) {
        echo "✅ $const: " . constant($const) . "<br>";
    } else {
        echo "❌ $const: Still not defined<br>";
    }
}

echo "<h3>4. Test BASE_URL Usage</h3>";

if (defined('BASE_URL')) {
    $testUrls = [
        BASE_URL . '/pages/dashboard/index.php',
        BASE_URL . '/assets/js/common.js',
        BASE_URL . '/login.php'
    ];
    
    echo "<strong>Generated URLs:</strong><br>";
    foreach ($testUrls as $url) {
        echo "- <a href='$url' target='_blank'>$url</a><br>";
    }
} else {
    echo "❌ Cannot test BASE_URL - constant not defined<br>";
}

echo "<h3>5. Page Test Links</h3>";

echo "<p>Test these pages (they should work without BASE_URL errors now):</p>";
echo "<ul>";
echo "<li><a href='pages/dashboard/index.php' target='_blank'>Dashboard</a></li>";
echo "<li><a href='pages/sales/index.php' target='_blank'>Sales Reports</a></li>";
echo "<li><a href='pages/users/index.php' target='_blank'>User Management</a></li>";
echo "</ul>";

echo "<h3>6. Debug Info</h3>";

echo "<strong>PHP Version:</strong> " . PHP_VERSION . "<br>";
echo "<strong>Current Directory:</strong> " . __DIR__ . "<br>";
echo "<strong>Config File Path:</strong> " . __DIR__ . '/config/config.php' . "<br>";
echo "<strong>Config File Exists:</strong> " . (file_exists(__DIR__ . '/config/config.php') ? 'Yes' : 'No') . "<br>";

if (function_exists('getBaseUrl')) {
    echo "<strong>getBaseUrl() function:</strong> " . getBaseUrl() . "<br>";
} else {
    echo "<strong>getBaseUrl() function:</strong> Not defined<br>";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; background: #f8f9fa; }
h1, h3 { color: #333; }
a { color: #007bff; text-decoration: none; }
a:hover { text-decoration: underline; }
ul { margin: 10px 0; }
li { margin: 5px 0; }
</style>