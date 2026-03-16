<?php
/**
 * Test Dashboard AJAX Response
 * Test the exact AJAX call that dashboard.js makes
 */

require_once __DIR__ . '/config/config.php';

echo "<h1>🧪 Dashboard AJAX Test</h1>";

try {
    // Check if user needs to login
    session_start();
    if (!isLoggedIn()) {
        echo "<div style='background: #fff3cd; padding: 15px; border-radius: 4px; margin: 15px 0;'>";
        echo "<h4>⚠️ Authentication Required</h4>";
        echo "<p>Dashboard AJAX requires authentication. Please login first.</p>";
        echo "<p><a href='login.php' style='padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 4px;'>Login First</a></p>";
        echo "<p><strong>Alternative:</strong> <a href='test_dashboard_stats.php'>Test Without Authentication</a></p>";
        echo "</div>";
        exit;
    }
    
    echo "<h3>✅ User Authenticated: " . getCurrentUser()['full_name'] . "</h3>";
    
    echo "<h3>1. Test Dashboard AJAX Handler</h3>";
    
    // Simulate the exact AJAX request that dashboard.js makes
    $_GET['action'] = 'stats';
    
    // Capture output
    ob_start();
    
    try {
        include __DIR__ . '/ajax/dashboard.php';
        $output = ob_get_clean();
    } catch (Exception $e) {
        $output = ob_get_clean();
        echo "<div style='background: #f8d7da; padding: 15px; border-radius: 4px; color: #721c24;'>";
        echo "<strong>AJAX Error:</strong> " . $e->getMessage() . "<br>";
        echo "</div>";
    }
    
    echo "<h4>Raw AJAX Response:</h4>";
    echo "<pre style='background: #f8f9fa; padding: 15px; border-radius: 4px; overflow-x: auto; font-size: 12px;'>";
    echo htmlspecialchars($output);
    echo "</pre>";
    
    // Parse JSON response
    $response = json_decode($output, true);
    
    if ($response) {
        echo "<h4>Parsed Response Structure:</h4>";
        echo "<ul>";
        echo "<li><strong>success:</strong> " . ($response['success'] ? 'true' : 'false') . "</li>";
        echo "<li><strong>message:</strong> " . ($response['message'] ?? 'N/A') . "</li>";
        echo "<li><strong>data:</strong> " . (isset($response['data']) ? 'Present' : 'Missing') . "</li>";
        echo "</ul>";
        
        if (isset($response['data'])) {
            echo "<h4>Dashboard Statistics:</h4>";
            $stats = $response['data'];
            
            echo "<table border='1' style='border-collapse: collapse; margin: 10px 0; width: 100%;'>";
            echo "<tr style='background: #f8f9fa; font-weight: bold;'>";
            echo "<td style='padding: 8px;'>Property</td>";
            echo "<td style='padding: 8px;'>Value</td>";
            echo "<td style='padding: 8px;'>Status</td>";
            echo "</tr>";
            
            $expectedProps = [
                'total_sales' => 'Total Sales Records',
                'total_revenue' => 'Total Revenue',
                'total_profit' => 'Total Profit', 
                'avg_margin' => 'Average Margin'
            ];
            
            foreach ($expectedProps as $prop => $label) {
                $exists = isset($stats[$prop]);
                $value = $exists ? $stats[$prop] : 'N/A';
                
                if ($prop === 'total_revenue' || $prop === 'total_profit') {
                    $displayValue = $exists ? '$' . number_format($value, 2) : 'N/A';
                } elseif ($prop === 'avg_margin') {
                    $displayValue = $exists ? number_format($value, 2) . '%' : 'N/A';
                } else {
                    $displayValue = $exists ? number_format($value) : 'N/A';
                }
                
                echo "<tr>";
                echo "<td style='padding: 8px;'><strong>$label</strong> ($prop)</td>";
                echo "<td style='padding: 8px;'>$displayValue</td>";
                echo "<td style='padding: 8px;'>" . ($exists ? "✅ Present" : "❌ Missing") . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
        
    } else {
        echo "❌ Failed to parse JSON response<br>";
        echo "JSON Error: " . json_last_error_msg() . "<br>";
    }
    
    echo "<h3>2. JavaScript Compatibility Test</h3>";
    
    echo "<script>
    // Test the exact response structure that dashboard.js expects
    const dashboardResponse = " . ($output ?? '{}') . ";
    
    console.log('Dashboard AJAX Test Response:', dashboardResponse);
    
    // Simulate the exact code from dashboard.js
    if (dashboardResponse.success) {
        console.log('✅ Response success flag OK');
        
        // Test the exact logic from dashboard.js loadDashboardData method
        const stats = dashboardResponse.data.stats || dashboardResponse.data;
        
        if (stats) {
            console.log('✅ Stats data extracted successfully:', stats);
            
            // Test the exact code from updateStatistics method
            try {
                const totalSales = parseInt(stats.total_sales || 0).toLocaleString();
                const totalRevenue = '$' + parseFloat(stats.total_revenue || 0).toFixed(2);
                const totalProfit = '$' + parseFloat(stats.total_profit || 0).toFixed(2);
                const avgMargin = parseFloat(stats.avg_margin || 0).toFixed(2) + '%';
                
                console.log('✅ Dashboard statistics formatting successful');
                console.log('Total Sales:', totalSales);
                console.log('Total Revenue:', totalRevenue);
                console.log('Total Profit:', totalProfit);
                console.log('Average Margin:', avgMargin);
                
                document.getElementById('js-results').innerHTML = `
                    <li>✅ Response parsing successful</li>
                    <li>✅ Stats extraction successful</li>
                    <li>✅ Formatting successful</li>
                    <li><strong>Total Sales:</strong> \${totalSales}</li>
                    <li><strong>Total Revenue:</strong> \${totalRevenue}</li>
                    <li><strong>Total Profit:</strong> \${totalProfit}</li>
                    <li><strong>Average Margin:</strong> \${avgMargin}</li>
                `;
                
            } catch (error) {
                console.error('❌ Dashboard formatting failed:', error);
                document.getElementById('js-results').innerHTML = '<li>❌ Dashboard formatting failed: ' + error.message + '</li>';
            }
            
        } else {
            console.error('❌ Stats data extraction failed');
            document.getElementById('js-results').innerHTML = '<li>❌ Stats data extraction failed</li>';
        }
        
    } else {
        console.error('❌ Response success flag false or missing');
        document.getElementById('js-results').innerHTML = '<li>❌ Response success flag false or missing</li>';
    }
    </script>";
    
    echo "<h4>JavaScript Test Results:</h4>";
    echo "<ul id='js-results'>";
    echo "<li>Running JavaScript tests... (check console for details)</li>";
    echo "</ul>";
    
    echo "<h3>3. Manual Test Links</h3>";
    
    echo "<div style='margin: 20px 0;'>";
    echo "<a href='ajax/dashboard.php?action=stats' target='_blank' style='padding: 10px 20px; background: #17a2b8; color: white; text-decoration: none; border-radius: 4px; margin-right: 10px;'>🔗 Test Direct AJAX</a>";
    echo "<a href='pages/dashboard/index.php' target='_blank' style='padding: 10px 20px; background: #28a745; color: white; text-decoration: none; border-radius: 4px; margin-right: 10px;'>📊 Test Dashboard Page</a>";
    echo "<a href='test_dashboard_stats.php' style='padding: 10px 20px; background: #6c757d; color: white; text-decoration: none; border-radius: 4px;'>🧪 Test Stats Method</a>";
    echo "</div>";
    
    echo "<div style='background: #d4edda; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
    echo "<h4>✅ Dashboard AJAX Test Complete!</h4>";
    echo "<p>The dashboard AJAX response should now be compatible with dashboard.js</p>";
    echo "<p><strong>If dashboard page still shows errors:</strong></p>";
    echo "<ul>";
    echo "<li>Check browser console for detailed error messages</li>";
    echo "<li>Verify all HTML elements (totalSales, totalRevenue, etc.) exist on the page</li>";
    echo "<li>Check if Utils.formatCurrency and Utils.formatPercentage functions are available</li>";
    echo "</ul>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<h3>❌ Test Failed:</h3>";
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
table { background: white; border-collapse: collapse; }
th, td { text-align: left; border: 1px solid #ddd; }
a { color: #007bff; text-decoration: none; }
a:hover { text-decoration: underline; }
ul, ol { margin: 10px 0; }
li { margin: 5px 0; }
pre { font-size: 11px; }
</style>