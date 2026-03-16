<?php
/**
 * Test Sales AJAX Response
 * Check if sales AJAX is returning correct response structure
 */

require_once __DIR__ . '/config/config.php';

echo "<h1>🧪 Sales AJAX Response Test</h1>";

try {
    // Check if we need to login first
    session_start();
    if (!isLoggedIn()) {
        echo "<div style='background: #fff3cd; padding: 15px; border-radius: 4px; margin: 15px 0;'>";
        echo "<h4>⚠️ Authentication Required</h4>";
        echo "<p>AJAX endpoints require authentication. Please login first.</p>";
        echo "<p><a href='login.php' class='btn btn-primary'>Login First</a></p>";
        echo "<p><strong>Alternative:</strong> <a href='test_sales_direct.php'>Test Without Authentication</a></p>";
        echo "</div>";
        return;
    }
    
    // Simulate AJAX request
    $_GET['action'] = 'list';
    $_GET['page'] = 1;
    $_GET['limit'] = 5;
    
    echo "<h3>1. Testing Sales AJAX Handler (Authenticated)</h3>";
    
    // Capture output
    ob_start();
    
    try {
        // Include the AJAX handler
        include __DIR__ . '/ajax/sales.php';
        $output = ob_get_clean();
    } catch (Exception $e) {
        $output = ob_get_clean();
        echo "<div style='background: #f8d7da; padding: 15px; border-radius: 4px; color: #721c24;'>";
        echo "<strong>AJAX Error:</strong> " . $e->getMessage() . "<br>";
        echo "</div>";
    }
    
    echo "<h4>Raw AJAX Response:</h4>";
    echo "<pre style='background: #f8f9fa; padding: 15px; border-radius: 4px; overflow-x: auto;'>";
    echo htmlspecialchars($output);
    echo "</pre>";
    
    // Parse JSON response
    $response = json_decode($output, true);
    
    if ($response) {
        echo "<h4>Parsed Response Structure:</h4>";
        echo "<ul>";
        echo "<li><strong>success:</strong> " . ($response['success'] ? 'true' : 'false') . "</li>";
        echo "<li><strong>message:</strong> " . ($response['message'] ?? 'N/A') . "</li>";
        echo "<li><strong>data:</strong> " . (isset($response['data']) ? count($response['data']) . ' records' : 'N/A') . "</li>";
        echo "<li><strong>pagination:</strong> " . (isset($response['pagination']) ? 'Present' : 'Missing') . "</li>";
        echo "</ul>";
        
        if (isset($response['pagination'])) {
            echo "<h4>Pagination Structure:</h4>";
            echo "<ul>";
            foreach ($response['pagination'] as $key => $value) {
                echo "<li><strong>$key:</strong> $value</li>";
            }
            echo "</ul>";
            
            // Check required pagination fields
            $requiredFields = ['current_page', 'total_pages', 'total_records', 'records_per_page'];
            $missingFields = [];
            
            foreach ($requiredFields as $field) {
                if (!isset($response['pagination'][$field])) {
                    $missingFields[] = $field;
                }
            }
            
            if (empty($missingFields)) {
                echo "✅ All required pagination fields present<br>";
            } else {
                echo "❌ Missing pagination fields: " . implode(', ', $missingFields) . "<br>";
            }
        }
        
        if (isset($response['data']) && is_array($response['data'])) {
            echo "<h4>Sample Record Structure:</h4>";
            if (!empty($response['data'])) {
                $sampleRecord = $response['data'][0];
                echo "<ul>";
                foreach ($sampleRecord as $key => $value) {
                    $displayValue = is_null($value) ? 'NULL' : (strlen($value) > 50 ? substr($value, 0, 50) . '...' : $value);
                    echo "<li><strong>$key:</strong> " . htmlspecialchars($displayValue) . "</li>";
                }
                echo "</ul>";
            } else {
                echo "<p>No records in response (empty table)</p>";
            }
        }
        
    } else {
        echo "❌ Failed to parse JSON response<br>";
        echo "JSON Error: " . json_last_error_msg() . "<br>";
    }
    
} catch (Exception $e) {
    echo "<h3>❌ Test Failed:</h3>";
    echo "<div style='background: #f8d7da; padding: 15px; border-radius: 4px; color: #721c24;'>";
    echo "<strong>Error:</strong> " . $e->getMessage() . "<br>";
    echo "</div>";
}

echo "<h3>2. JavaScript Compatibility Test</h3>";

echo "<script>
// Test the response structure with JavaScript
const testResponse = " . ($output ?? '{}') . ";

console.log('AJAX Response Test:', testResponse);

if (testResponse.success) {
    console.log('✅ Response success flag OK');
    
    if (testResponse.pagination) {
        console.log('✅ Pagination data present');
        
        if (testResponse.pagination.total_pages !== undefined) {
            console.log('✅ total_pages field present:', testResponse.pagination.total_pages);
        } else {
            console.error('❌ total_pages field missing');
        }
        
        if (testResponse.pagination.current_page !== undefined) {
            console.log('✅ current_page field present:', testResponse.pagination.current_page);
        } else {
            console.error('❌ current_page field missing');
        }
    } else {
        console.error('❌ Pagination data missing');
    }
    
    if (testResponse.data && Array.isArray(testResponse.data)) {
        console.log('✅ Data array present with', testResponse.data.length, 'records');
    } else {
        console.error('❌ Data array missing or invalid');
    }
} else {
    console.error('❌ Response success flag false or missing');
}
</script>";

echo "<div style='background: #d1ecf1; padding: 15px; border-radius: 4px; margin: 15px 0;'>";
echo "<h4>📋 Check Browser Console</h4>";
echo "<p>Open browser developer tools (F12) and check the Console tab for JavaScript test results.</p>";
echo "<p>The JavaScript test will verify if the response structure is compatible with the frontend code.</p>";
echo "</div>";

echo "<h3>3. Manual Test Links</h3>";

echo "<ul>";
echo "<li><a href='ajax/sales.php?action=list&page=1&limit=5' target='_blank'>Test Sales List AJAX</a></li>";
echo "<li><a href='pages/sales/index.php' target='_blank'>Test Sales Page</a></li>";
echo "<li><a href='login.php' target='_blank'>Login to Test Full System</a></li>";
echo "</ul>";

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