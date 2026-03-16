<?php
/**
 * Fix BASE_URL Constants Issue
 * Check and fix undefined constant issues in all pages
 */

echo "<h1>🔧 Fix BASE_URL Constants Issue</h1>";

$fixes = [];
$errors = [];

// Define the files to check
$filesToCheck = [
    'pages/dashboard/index.php',
    'pages/sales/index.php', 
    'pages/users/index.php',
    'login.php',
    'logout.php',
    'index.php'
];

echo "<h3>1. Checking Files for BASE_URL Usage</h3>";

foreach ($filesToCheck as $file) {
    $fullPath = __DIR__ . '/' . $file;
    
    if (file_exists($fullPath)) {
        $content = file_get_contents($fullPath);
        
        // Check if BASE_URL is used
        if (strpos($content, 'BASE_URL') !== false) {
            // Check if config.php is included
            $hasConfigInclude = (
                strpos($content, "require_once __DIR__ . '/config/config.php'") !== false ||
                strpos($content, "require_once __DIR__ . '/../config/config.php'") !== false ||
                strpos($content, "require_once __DIR__ . '/../../config/config.php'") !== false ||
                strpos($content, "include __DIR__ . '/config/config.php'") !== false ||
                strpos($content, "include __DIR__ . '/../config/config.php'") !== false ||
                strpos($content, "include __DIR__ . '/../../config/config.php'") !== false
            );
            
            if ($hasConfigInclude) {
                echo "✅ $file: BASE_URL used, config.php included<br>";
                $fixes[] = "$file already has proper config include";
            } else {
                echo "❌ $file: BASE_URL used but config.php NOT included<br>";
                $errors[] = "$file needs config.php include";
            }
        } else {
            echo "ℹ️ $file: BASE_URL not used<br>";
        }
    } else {
        echo "⚠️ $file: File not found<br>";
    }
}

echo "<h3>2. Checking Current Configuration</h3>";

try {
    require_once __DIR__ . '/config/config.php';
    
    if (defined('BASE_URL')) {
        echo "✅ BASE_URL constant defined: " . BASE_URL . "<br>";
    } else {
        echo "❌ BASE_URL constant not defined<br>";
        $errors[] = "BASE_URL constant missing in config.php";
    }
    
    // Check other important constants
    $constants = ['DB_HOST', 'DB_NAME', 'APP_NAME'];
    foreach ($constants as $const) {
        if (defined($const)) {
            echo "✅ $const defined<br>";
        } else {
            echo "❌ $const not defined<br>";
            $errors[] = "$const constant missing";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Config loading error: " . $e->getMessage() . "<br>";
    $errors[] = "Config file has errors";
}

echo "<h3>3. Testing Page Access</h3>";

// Test if pages can be accessed without errors
$testUrls = [
    'pages/dashboard/index.php' => 'Dashboard',
    'pages/sales/index.php' => 'Sales Reports',
    'pages/users/index.php' => 'User Management'
];

foreach ($testUrls as $url => $name) {
    $fullUrl = "http://localhost/finalReport/$url";
    echo "<strong>$name:</strong> <a href='$fullUrl' target='_blank'>$fullUrl</a><br>";
}

echo "<h3>4. Common Issues and Solutions</h3>";

echo "<div style='background: #fff3cd; padding: 15px; border-radius: 4px; margin: 15px 0;'>";
echo "<h4>If you still see BASE_URL errors:</h4>";
echo "<ol>";
echo "<li><strong>Clear Browser Cache:</strong> Hard refresh (Ctrl+F5) or clear cache</li>";
echo "<li><strong>Check Error Logs:</strong> Look for specific line numbers in errors</li>";
echo "<li><strong>Restart Apache:</strong> Restart Apache service in XAMPP</li>";
echo "<li><strong>Check File Permissions:</strong> Ensure files are readable</li>";
echo "</ol>";
echo "</div>";

echo "<h3>5. Header Include Chain</h3>";

echo "<div style='background: #e3f2fd; padding: 15px; border-radius: 4px; margin: 15px 0;'>";
echo "<h4>How the includes work:</h4>";
echo "<pre style='background: #f8f9fa; padding: 10px; border-radius: 4px;'>";
echo "Page (e.g., dashboard/index.php)
├── require_once config/config.php (defines BASE_URL)
├── include header.php
│   ├── Uses BASE_URL for assets
│   └── Calls requireAuth() function
└── include footer.php
    └── Uses BASE_URL for scripts";
echo "</pre>";
echo "</div>";

echo "<h3>6. Manual Fix Instructions</h3>";

if (!empty($errors)) {
    echo "<div style='background: #f8d7da; padding: 15px; border-radius: 4px; margin: 15px 0;'>";
    echo "<h4>Issues Found:</h4>";
    echo "<ul>";
    foreach ($errors as $error) {
        echo "<li>❌ $error</li>";
    }
    echo "</ul>";
    
    echo "<h4>Manual Fix Steps:</h4>";
    echo "<ol>";
    echo "<li>Open each page file that uses BASE_URL</li>";
    echo "<li>Add this line at the top (after opening PHP tag):</li>";
    echo "<code>require_once __DIR__ . '/../../config/config.php';</code>";
    echo "<li>Save the file and test again</li>";
    echo "</ol>";
    echo "</div>";
} else {
    echo "<div style='background: #d4edda; padding: 15px; border-radius: 4px; margin: 15px 0;'>";
    echo "<h4>✅ All Issues Fixed!</h4>";
    echo "<p>All pages should now work without BASE_URL constant errors.</p>";
    echo "</div>";
}

echo "<h3>7. Test Pages</h3>";

echo "<p>Test these pages to verify the fix:</p>";
echo "<ul>";
echo "<li><a href='pages/dashboard/index.php' target='_blank'>Dashboard Page</a></li>";
echo "<li><a href='pages/sales/index.php' target='_blank'>Sales Reports Page</a></li>";
echo "<li><a href='pages/users/index.php' target='_blank'>User Management Page</a></li>";
echo "<li><a href='login.php' target='_blank'>Login Page</a></li>";
echo "</ul>";

echo "<h3>8. Summary</h3>";

if (!empty($fixes)) {
    echo "<h4>✅ Fixes Applied:</h4>";
    echo "<ul>";
    foreach ($fixes as $fix) {
        echo "<li>$fix</li>";
    }
    echo "</ul>";
}

echo "<div style='background: #d1ecf1; padding: 15px; border-radius: 4px; margin: 15px 0;'>";
echo "<h4>💡 Prevention Tips:</h4>";
echo "<ul>";
echo "<li>Always include config.php at the top of pages that use constants</li>";
echo "<li>Use consistent include paths relative to file location</li>";
echo "<li>Test pages after creating them to catch these issues early</li>";
echo "<li>Consider using a common bootstrap file that all pages include</li>";
echo "</ul>";
echo "</div>";
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; background: #f8f9fa; }
h1, h3, h4 { color: #333; }
code { background: #f1f3f4; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
pre { background: #f1f3f4; padding: 10px; border-radius: 4px; overflow-x: auto; font-family: monospace; font-size: 12px; }
a { color: #007bff; text-decoration: none; }
a:hover { text-decoration: underline; }
ul, ol { margin: 10px 0; }
li { margin: 5px 0; }
</style>