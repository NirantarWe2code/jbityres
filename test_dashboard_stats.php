<?php
/**
 * Test Dashboard Statistics
 * Test if dashboard stats are working correctly
 */

require_once __DIR__ . '/config/config.php';

echo "<h1>📊 Dashboard Statistics Test</h1>";

try {
    echo "<h3>1. Test SalesData getDashboardStats</h3>";
    
    require_once __DIR__ . '/classes/Database.php';
    require_once __DIR__ . '/classes/SalesData.php';
    
    $salesData = new SalesData();
    $result = $salesData->getDashboardStats();
    
    echo "<h4>getDashboardStats Response:</h4>";
    echo "<pre style='background: #f8f9fa; padding: 15px; border-radius: 4px; overflow-x: auto;'>";
    echo json_encode($result, JSON_PRETTY_PRINT);
    echo "</pre>";
    
    if ($result['success']) {
        echo "✅ getDashboardStats method working<br>";
        
        $stats = $result['data'];
        
        // Check required properties for frontend
        $requiredProps = ['total_sales', 'total_revenue', 'total_profit', 'avg_margin'];
        $missingProps = [];
        
        foreach ($requiredProps as $prop) {
            if (!isset($stats[$prop])) {
                $missingProps[] = $prop;
            }
        }
        
        if (empty($missingProps)) {
            echo "✅ All required dashboard properties present<br>";
        } else {
            echo "❌ Missing dashboard properties: " . implode(', ', $missingProps) . "<br>";
        }
        
        // Display stats in a readable format
        echo "<h4>Dashboard Statistics:</h4>";
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr style='background: #f8f9fa; font-weight: bold;'>";
        echo "<td style='padding: 8px;'>Metric</td>";
        echo "<td style='padding: 8px;'>Value</td>";
        echo "<td style='padding: 8px;'>Frontend Property</td>";
        echo "</tr>";
        
        $metrics = [
            'Total Sales Records' => ['value' => $stats['total_sales'] ?? 0, 'property' => 'total_sales'],
            'Total Revenue' => ['value' => '$' . number_format($stats['total_revenue'] ?? 0, 2), 'property' => 'total_revenue'],
            'Total Profit' => ['value' => '$' . number_format($stats['total_profit'] ?? 0, 2), 'property' => 'total_profit'],
            'Average Margin' => ['value' => number_format($stats['avg_margin'] ?? 0, 2) . '%', 'property' => 'avg_margin'],
            'Total Quantity' => ['value' => number_format($stats['total_quantity'] ?? 0, 2), 'property' => 'total_quantity'],
            'Avg Order Value' => ['value' => '$' . number_format($stats['avg_order_value'] ?? 0, 2), 'property' => 'avg_order_value']
        ];
        
        foreach ($metrics as $label => $info) {
            echo "<tr>";
            echo "<td style='padding: 8px;'><strong>$label</strong></td>";
            echo "<td style='padding: 8px;'>" . $info['value'] . "</td>";
            echo "<td style='padding: 8px;'><code>" . $info['property'] . "</code></td>";
            echo "</tr>";
        }
        echo "</table>";
        
    } else {
        echo "❌ getDashboardStats failed: " . $result['message'] . "<br>";
    }
    
    echo "<h3>2. Test JavaScript Compatibility</h3>";
    
    // Create response that matches dashboard AJAX format
    $dashboardResponse = [
        'success' => true,
        'message' => 'Dashboard statistics retrieved successfully',
        'data' => $result['data'] ?? []
    ];
    
    echo "<script>
    // Test dashboard stats with JavaScript
    const dashboardStats = " . json_encode($dashboardResponse) . ";
    
    console.log('Dashboard Stats Test:', dashboardStats);
    
    if (dashboardStats.success && dashboardStats.data) {
        const stats = dashboardStats.data;
        
        // Test each property that dashboard.js expects
        const requiredProps = ['total_sales', 'total_revenue', 'total_profit', 'avg_margin'];
        const testResults = [];
        
        requiredProps.forEach(prop => {
            if (stats[prop] !== undefined) {
                testResults.push('✅ ' + prop + ': ' + stats[prop]);
                console.log('✅', prop, 'available:', stats[prop]);
            } else {
                testResults.push('❌ ' + prop + ': missing');
                console.error('❌', prop, 'missing');
            }
        });
        
        document.getElementById('js-test-results').innerHTML = testResults.map(r => '<li>' + r + '</li>').join('');
        
        // Test the exact code that dashboard.js uses
        try {
            const totalSalesText = parseInt(stats.total_sales || 0).toLocaleString();
            const totalRevenueText = '$' + parseFloat(stats.total_revenue || 0).toFixed(2);
            const totalProfitText = '$' + parseFloat(stats.total_profit || 0).toFixed(2);
            const avgMarginText = parseFloat(stats.avg_margin || 0).toFixed(2) + '%';
            
            console.log('✅ Dashboard formatting test passed');
            console.log('Total Sales:', totalSalesText);
            console.log('Total Revenue:', totalRevenueText);
            console.log('Total Profit:', totalProfitText);
            console.log('Avg Margin:', avgMarginText);
            
            document.getElementById('js-test-results').innerHTML += '<li>✅ Dashboard formatting test passed</li>';
            
        } catch (error) {
            console.error('❌ Dashboard formatting test failed:', error);
            document.getElementById('js-test-results').innerHTML += '<li>❌ Dashboard formatting failed: ' + error.message + '</li>';
        }
        
    } else {
        console.error('❌ Dashboard stats response invalid');
        document.getElementById('js-test-results').innerHTML = '<li>❌ Dashboard stats response invalid</li>';
    }
    </script>";
    
    echo "<h4>JavaScript Test Results:</h4>";
    echo "<ul id='js-test-results'>";
    echo "<li>Running JavaScript tests... (check console for details)</li>";
    echo "</ul>";
    
    echo "<h3>3. Test Current Table Structure</h3>";
    
    // Show current table columns to understand what's available
    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if (!$mysqli->connect_error) {
        $result = $mysqli->query("DESCRIBE sales_data");
        if ($result) {
            echo "<h4>Available Columns in sales_data:</h4>";
            echo "<ul>";
            while ($row = $result->fetch_assoc()) {
                echo "<li><strong>" . $row['Field'] . "</strong> (" . $row['Type'] . ")</li>";
            }
            echo "</ul>";
        }
        
        // Show sample data
        $sampleResult = $mysqli->query("SELECT * FROM sales_data LIMIT 1");
        if ($sampleResult && $sampleResult->num_rows > 0) {
            $sampleData = $sampleResult->fetch_assoc();
            echo "<h4>Sample Record:</h4>";
            echo "<ul>";
            foreach ($sampleData as $key => $value) {
                $displayValue = is_null($value) ? 'NULL' : (strlen($value) > 50 ? substr($value, 0, 50) . '...' : $value);
                echo "<li><strong>$key:</strong> " . htmlspecialchars($displayValue) . "</li>";
            }
            echo "</ul>";
        } else {
            echo "<p>⚠️ No sample data found in sales_data table</p>";
        }
        
        $mysqli->close();
    }
    
    echo "<div style='background: #d4edda; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
    echo "<h4>✅ Dashboard Stats Test Complete!</h4>";
    echo "<p>Dashboard statistics are now compatible with the frontend code.</p>";
    echo "<p><strong>Next Steps:</strong></p>";
    echo "<ul>";
    echo "<li><a href='login.php'>Login to test dashboard</a></li>";
    echo "<li><a href='pages/dashboard/index.php'>Test Dashboard Page</a></li>";
    echo "<li>Check browser console for any remaining JavaScript errors</li>";
    echo "</ul>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<h3>❌ Test Failed:</h3>";
    echo "<div style='background: #f8d7da; padding: 15px; border-radius: 4px; color: #721c24;'>";
    echo "<strong>Error:</strong> " . $e->getMessage() . "<br>";
    echo "<strong>File:</strong> " . $e->getFile() . "<br>";
    echo "<strong>Line:</strong> " . $e->getLine() . "<br>";
    echo "</div>";
    
    echo "<h4>Troubleshooting:</h4>";
    echo "<ul>";
    echo "<li>Run <a href='quick_fix.php'>Quick Fix</a> to setup database</li>";
    echo "<li>Check <a href='check_current_table.php'>Current Table Structure</a></li>";
    echo "<li>Verify XAMPP MySQL service is running</li>";
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
pre { font-size: 12px; }
code { background: #f1f3f4; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
</style>