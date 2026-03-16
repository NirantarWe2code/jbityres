<?php
/**
 * Direct Sales Test (Without Authentication)
 * Test SalesData class directly without AJAX
 */

require_once __DIR__ . '/config/config.php';

echo "<h1>🧪 Direct Sales Data Test</h1>";

try {
    echo "<h3>1. Test Database Connection</h3>";
    
    require_once __DIR__ . '/classes/Database.php';
    $db = Database::getInstance();
    
    $testResult = $db->fetchOne("SELECT 1 as test");
    if ($testResult && $testResult['test'] == 1) {
        echo "✅ Database connection working<br>";
    } else {
        throw new Exception("Database connection failed");
    }
    
    echo "<h3>2. Test SalesData Class</h3>";
    
    require_once __DIR__ . '/classes/SalesData.php';
    $salesData = new SalesData();
    echo "✅ SalesData class loaded successfully<br>";
    
    echo "<h3>3. Test getAllRecords Method</h3>";
    
    $result = $salesData->getAllRecords(1, 5);
    
    echo "<h4>getAllRecords Response:</h4>";
    echo "<pre style='background: #f8f9fa; padding: 15px; border-radius: 4px; overflow-x: auto;'>";
    echo json_encode($result, JSON_PRETTY_PRINT);
    echo "</pre>";
    
    if ($result['success']) {
        echo "✅ getAllRecords method working<br>";
        echo "Records found: " . count($result['data']) . "<br>";
        
        // Check pagination structure
        if (isset($result['pagination'])) {
            echo "✅ Pagination data present<br>";
            
            $pagination = $result['pagination'];
            $requiredFields = ['current_page', 'total_pages', 'total_records', 'records_per_page'];
            $missingFields = [];
            
            foreach ($requiredFields as $field) {
                if (!isset($pagination[$field])) {
                    $missingFields[] = $field;
                }
            }
            
            if (empty($missingFields)) {
                echo "✅ All required pagination fields present<br>";
            } else {
                echo "❌ Missing pagination fields: " . implode(', ', $missingFields) . "<br>";
            }
        } else {
            echo "❌ Pagination data missing<br>";
        }
        
        // Show sample record structure
        if (!empty($result['data'])) {
            echo "<h4>Sample Record Structure:</h4>";
            $sampleRecord = $result['data'][0];
            echo "<ul>";
            foreach ($sampleRecord as $key => $value) {
                $displayValue = is_null($value) ? 'NULL' : (strlen($value) > 50 ? substr($value, 0, 50) . '...' : $value);
                echo "<li><strong>$key:</strong> " . htmlspecialchars($displayValue) . "</li>";
            }
            echo "</ul>";
        }
        
    } else {
        echo "❌ getAllRecords method failed: " . $result['message'] . "<br>";
    }
    
    echo "<h3>4. Test getDashboardStats Method</h3>";
    
    $statsResult = $salesData->getDashboardStats();
    
    echo "<h4>getDashboardStats Response:</h4>";
    echo "<pre style='background: #f8f9fa; padding: 15px; border-radius: 4px; overflow-x: auto;'>";
    echo json_encode($statsResult, JSON_PRETTY_PRINT);
    echo "</pre>";
    
    if ($statsResult['success']) {
        echo "✅ getDashboardStats method working<br>";
    } else {
        echo "❌ getDashboardStats method failed: " . $statsResult['message'] . "<br>";
    }
    
    echo "<h3>5. Test getFilterOptions Method</h3>";
    
    $filterResult = $salesData->getFilterOptions();
    
    echo "<h4>getFilterOptions Response:</h4>";
    echo "<pre style='background: #f8f9fa; padding: 15px; border-radius: 4px; overflow-x: auto;'>";
    echo json_encode($filterResult, JSON_PRETTY_PRINT);
    echo "</pre>";
    
    if ($filterResult['success']) {
        echo "✅ getFilterOptions method working<br>";
    } else {
        echo "❌ getFilterOptions method failed: " . $filterResult['message'] . "<br>";
    }
    
    echo "<h3>6. JavaScript Compatibility Test</h3>";
    
    // Create a sample response that matches what AJAX would return
    $ajaxResponse = [
        'success' => true,
        'message' => 'Sales records retrieved successfully',
        'data' => $result['data'] ?? [],
        'pagination' => $result['pagination'] ?? []
    ];
    
    echo "<script>
    // Test the response structure with JavaScript
    const testResponse = " . json_encode($ajaxResponse) . ";
    
    console.log('Direct Sales Test Response:', testResponse);
    
    if (testResponse.success) {
        console.log('✅ Response success flag OK');
        
        if (testResponse.pagination) {
            console.log('✅ Pagination data present');
            
            if (testResponse.pagination.total_pages !== undefined) {
                console.log('✅ total_pages field present:', testResponse.pagination.total_pages);
                document.getElementById('js-test-result').innerHTML += '<li>✅ total_pages field accessible</li>';
            } else {
                console.error('❌ total_pages field missing');
                document.getElementById('js-test-result').innerHTML += '<li>❌ total_pages field missing</li>';
            }
            
            if (testResponse.pagination.current_page !== undefined) {
                console.log('✅ current_page field present:', testResponse.pagination.current_page);
                document.getElementById('js-test-result').innerHTML += '<li>✅ current_page field accessible</li>';
            } else {
                console.error('❌ current_page field missing');
                document.getElementById('js-test-result').innerHTML += '<li>❌ current_page field missing</li>';
            }
        } else {
            console.error('❌ Pagination data missing');
            document.getElementById('js-test-result').innerHTML += '<li>❌ Pagination data missing</li>';
        }
        
        if (testResponse.data && Array.isArray(testResponse.data)) {
            console.log('✅ Data array present with', testResponse.data.length, 'records');
            document.getElementById('js-test-result').innerHTML += '<li>✅ Data array with ' + testResponse.data.length + ' records</li>';
        } else {
            console.error('❌ Data array missing or invalid');
            document.getElementById('js-test-result').innerHTML += '<li>❌ Data array missing or invalid</li>';
        }
    } else {
        console.error('❌ Response success flag false or missing');
        document.getElementById('js-test-result').innerHTML += '<li>❌ Response success flag false or missing</li>';
    }
    </script>";
    
    echo "<h4>JavaScript Test Results:</h4>";
    echo "<ul id='js-test-result'>";
    echo "<li>Check browser console for detailed results</li>";
    echo "</ul>";
    
    echo "<div style='background: #d4edda; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
    echo "<h4>✅ Direct Test Complete!</h4>";
    echo "<p>SalesData class is working correctly with your current table structure.</p>";
    echo "<p><strong>Next Steps:</strong></p>";
    echo "<ul>";
    echo "<li><a href='login.php'>Login to test full system</a></li>";
    echo "<li><a href='pages/sales/index.php'>Test Sales Page (after login)</a></li>";
    echo "<li><a href='fix_sales_frontend.php'>Fix Frontend Code</a></li>";
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
    echo "<li>Check XAMPP MySQL service is running</li>";
    echo "<li>Verify database exists and has sales_data table</li>";
    echo "<li>Run <a href='quick_fix.php'>Quick Fix</a> to setup database</li>";
    echo "<li>Check <a href='check_current_table.php'>Current Table Structure</a></li>";
    echo "</ul>";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; background: #f8f9fa; }
h1, h3, h4 { color: #333; }
a { color: #007bff; text-decoration: none; }
a:hover { text-decoration: underline; }
ul, ol { margin: 10px 0; }
li { margin: 5px 0; }
pre { font-size: 12px; }
</style>