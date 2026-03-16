<?php
/**
 * Real-time Dashboard Debug
 * Debug dashboard issues in real-time
 */

require_once __DIR__ . '/config/config.php';

echo "<h1>🔍 Real-time Dashboard Debug</h1>";

// Check authentication
session_start();
if (!isLoggedIn()) {
    echo "<div style='background: #f8d7da; padding: 15px; border-radius: 4px; color: #721c24;'>";
    echo "<h4>❌ Not Authenticated</h4>";
    echo "<p><a href='login.php'>Please login first</a></p>";
    echo "</div>";
    exit;
}

echo "<h3>✅ Authenticated as: " . getCurrentUser()['full_name'] . "</h3>";

try {
    echo "<h3>1. Direct SalesData Test</h3>";
    
    require_once __DIR__ . '/classes/Database.php';
    require_once __DIR__ . '/classes/SalesData.php';
    
    $salesData = new SalesData();
    $statsResult = $salesData->getDashboardStats();
    
    echo "<h4>SalesData->getDashboardStats() Result:</h4>";
    echo "<pre style='background: #f8f9fa; padding: 15px; border-radius: 4px; font-size: 12px;'>";
    echo json_encode($statsResult, JSON_PRETTY_PRINT);
    echo "</pre>";
    
    echo "<h3>2. AJAX Handler Simulation</h3>";
    
    // Simulate AJAX call
    $_GET['action'] = 'stats';
    
    ob_start();
    include __DIR__ . '/ajax/dashboard.php';
    $ajaxOutput = ob_get_clean();
    
    echo "<h4>AJAX Response:</h4>";
    echo "<pre style='background: #f8f9fa; padding: 15px; border-radius: 4px; font-size: 12px;'>";
    echo htmlspecialchars($ajaxOutput);
    echo "</pre>";
    
    $ajaxResponse = json_decode($ajaxOutput, true);
    
    echo "<h3>3. JavaScript Debug Test</h3>";
    
    echo "<div id='debug-results'></div>";
    
    echo "<script>
    console.log('=== Dashboard Debug Test ===');
    
    // Test the exact AJAX response
    const ajaxResponse = " . json_encode($ajaxResponse) . ";
    console.log('AJAX Response:', ajaxResponse);
    
    // Test the exact logic from dashboard.js
    if (ajaxResponse && ajaxResponse.success) {
        console.log('✅ AJAX success flag OK');
        
        // This is the exact line from dashboard.js line 69
        const stats = ajaxResponse.data.stats || ajaxResponse.data;
        console.log('Stats extracted:', stats);
        
        if (stats) {
            console.log('✅ Stats object exists');
            console.log('stats.total_sales:', stats.total_sales);
            console.log('typeof stats.total_sales:', typeof stats.total_sales);
            
            // Test the exact line that's failing (line 91 in dashboard.js)
            try {
                const totalSalesValue = parseInt(stats.total_sales || 0).toLocaleString();
                console.log('✅ Line 91 equivalent successful:', totalSalesValue);
                
                document.getElementById('debug-results').innerHTML = `
                    <div style='background: #d4edda; padding: 15px; border-radius: 4px;'>
                        <h4>✅ JavaScript Test Successful</h4>
                        <ul>
                            <li><strong>AJAX Response:</strong> Valid</li>
                            <li><strong>Stats Object:</strong> Present</li>
                            <li><strong>total_sales:</strong> \${stats.total_sales}</li>
                            <li><strong>Formatted:</strong> \${totalSalesValue}</li>
                        </ul>
                    </div>
                `;
                
            } catch (error) {
                console.error('❌ Line 91 equivalent failed:', error);
                document.getElementById('debug-results').innerHTML = `
                    <div style='background: #f8d7da; padding: 15px; border-radius: 4px; color: #721c24;'>
                        <h4>❌ JavaScript Test Failed</h4>
                        <p><strong>Error:</strong> \${error.message}</p>
                        <p><strong>stats object:</strong> \${JSON.stringify(stats)}</p>
                    </div>
                `;
            }
            
        } else {
            console.error('❌ Stats object is null/undefined');
            document.getElementById('debug-results').innerHTML = `
                <div style='background: #f8d7da; padding: 15px; border-radius: 4px; color: #721c24;'>
                    <h4>❌ Stats Object Missing</h4>
                    <p>ajaxResponse.data: \${JSON.stringify(ajaxResponse.data)}</p>
                </div>
            `;
        }
        
    } else {
        console.error('❌ AJAX response invalid');
        document.getElementById('debug-results').innerHTML = `
            <div style='background: #f8d7da; padding: 15px; border-radius: 4px; color: #721c24;'>
                <h4>❌ AJAX Response Invalid</h4>
                <p>\${JSON.stringify(ajaxResponse)}</p>
            </div>
        `;
    }
    </script>";
    
    echo "<h3>4. Cache Busting Test</h3>";
    
    $dashboardJsPath = __DIR__ . '/assets/js/dashboard.js';
    $dashboardJsModTime = file_exists($dashboardJsPath) ? filemtime($dashboardJsPath) : 0;
    
    echo "<p><strong>dashboard.js last modified:</strong> " . date('Y-m-d H:i:s', $dashboardJsModTime) . "</p>";
    
    echo "<div style='background: #fff3cd; padding: 15px; border-radius: 4px; margin: 15px 0;'>";
    echo "<h4>🔄 Cache Busting Solutions:</h4>";
    echo "<ol>";
    echo "<li><strong>Hard Refresh:</strong> Press Ctrl+F5 or Ctrl+Shift+R in browser</li>";
    echo "<li><strong>Clear Browser Cache:</strong> Go to browser settings and clear cache</li>";
    echo "<li><strong>Incognito Mode:</strong> Open dashboard in incognito/private window</li>";
    echo "<li><strong>Add Cache Buster:</strong> Add ?v=" . time() . " to JS file URLs</li>";
    echo "</ol>";
    echo "</div>";
    
    echo "<h3>5. Live Dashboard Test</h3>";
    
    echo "<div style='margin: 20px 0;'>";
    echo "<a href='pages/dashboard/index.php?v=" . time() . "' target='_blank' style='padding: 10px 20px; background: #28a745; color: white; text-decoration: none; border-radius: 4px; margin-right: 10px;'>📊 Test Dashboard (Cache Busted)</a>";
    echo "<a href='pages/dashboard/index.php' target='_blank' style='padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 4px;'>📊 Test Dashboard (Normal)</a>";
    echo "</div>";
    
    echo "<div style='background: #d1ecf1; padding: 15px; border-radius: 4px; margin: 15px 0;'>";
    echo "<h4>📋 Debug Instructions:</h4>";
    echo "<ol>";
    echo "<li>Check the JavaScript test results above</li>";
    echo "<li>Open browser developer tools (F12)</li>";
    echo "<li>Go to Console tab</li>";
    echo "<li>Look for '=== Dashboard Debug Test ===' messages</li>";
    echo "<li>If test passes here but dashboard page fails, it's a caching issue</li>";
    echo "</ol>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<h3>❌ Debug Failed:</h3>";
    echo "<div style='background: #f8d7da; padding: 15px; border-radius: 4px; color: #721c24;'>";
    echo "<strong>Error:</strong> " . $e->getMessage() . "<br>";
    echo "<strong>File:</strong> " . $e->getFile() . "<br>";
    echo "<strong>Line:</strong> " . $e->getLine() . "<br>";
    echo "</div>";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; background: #f8f9fa; }
h1, h3, h4 { color: #333; }
a { color: #007bff; text-decoration: none; }
a:hover { text-decoration: underline; }
ul, ol { margin: 10px 0; }
li { margin: 5px 0; }
pre { font-size: 11px; }
</style>