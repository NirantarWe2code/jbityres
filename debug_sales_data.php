<?php
/**
 * Debug Sales Data Issues
 * Check why sales table is not showing data
 */

require_once __DIR__ . '/config/config.php';

echo "<h1>🔍 Sales Data Debug</h1>";

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
    echo "<h3>1. Database Connection Test</h3>";
    
    require_once __DIR__ . '/classes/Database.php';
    $db = Database::getInstance();
    
    $testResult = $db->fetchOne("SELECT 1 as test");
    if ($testResult && $testResult['test'] == 1) {
        echo "✅ Database connection working<br>";
    } else {
        throw new Exception("Database connection failed");
    }
    
    echo "<h3>2. Sales Data Table Check</h3>";
    
    // Check if table exists
    $tableExists = $db->tableExists('sales_data');
    if ($tableExists) {
        echo "✅ sales_data table exists<br>";
    } else {
        echo "❌ sales_data table does not exist<br>";
        echo "<p><a href='quick_fix.php'>Run Quick Fix to create table</a></p>";
        exit;
    }
    
    // Check table structure
    $columns = $db->getTableColumns('sales_data');
    echo "<h4>Table Columns (" . count($columns) . "):</h4>";
    echo "<ul>";
    foreach ($columns as $column) {
        echo "<li><strong>" . $column['Field'] . "</strong> (" . $column['Type'] . ")</li>";
    }
    echo "</ul>";
    
    // Check record count
    $countResult = $db->fetchOne("SELECT COUNT(*) as count FROM sales_data");
    $totalRecords = $countResult['count'] ?? 0;
    echo "<h4>Total Records: $totalRecords</h4>";
    
    if ($totalRecords == 0) {
        echo "<div style='background: #fff3cd; padding: 15px; border-radius: 4px; margin: 15px 0;'>";
        echo "<h5>⚠️ No Data Found</h5>";
        echo "<p>The sales_data table is empty. You need to add some sample data.</p>";
        echo "<p><a href='#add-sample-data' onclick='addSampleData()' style='padding: 8px 16px; background: #28a745; color: white; text-decoration: none; border-radius: 4px;'>Add Sample Data</a></p>";
        echo "</div>";
    } else {
        // Show sample records
        echo "<h4>Sample Records (First 3):</h4>";
        $sampleRecords = $db->fetchAll("SELECT * FROM sales_data LIMIT 3");
        
        if (!empty($sampleRecords)) {
            echo "<table border='1' style='border-collapse: collapse; width: 100%; font-size: 12px;'>";
            echo "<tr style='background: #f8f9fa; font-weight: bold;'>";
            foreach (array_keys($sampleRecords[0]) as $column) {
                echo "<td style='padding: 4px; border: 1px solid #ddd;'>$column</td>";
            }
            echo "</tr>";
            
            foreach ($sampleRecords as $record) {
                echo "<tr>";
                foreach ($record as $value) {
                    $displayValue = is_null($value) ? 'NULL' : htmlspecialchars(substr($value, 0, 20));
                    echo "<td style='padding: 4px; border: 1px solid #ddd;'>$displayValue</td>";
                }
                echo "</tr>";
            }
            echo "</table>";
        }
    }
    
    echo "<h3>3. SalesData Class Test</h3>";
    
    require_once __DIR__ . '/classes/SalesData.php';
    $salesData = new SalesData();
    
    $result = $salesData->getAllRecords(1, 5);
    
    echo "<h4>SalesData->getAllRecords() Result:</h4>";
    echo "<pre style='background: #f8f9fa; padding: 15px; border-radius: 4px; font-size: 12px; overflow-x: auto;'>";
    echo json_encode($result, JSON_PRETTY_PRINT);
    echo "</pre>";
    
    if ($result['success']) {
        echo "✅ SalesData class working correctly<br>";
        echo "Records returned: " . count($result['data']) . "<br>";
    } else {
        echo "❌ SalesData class error: " . $result['message'] . "<br>";
    }
    
    echo "<h3>4. AJAX Handler Test</h3>";
    
    // Simulate AJAX request
    $_GET['action'] = 'list';
    $_GET['page'] = 1;
    $_GET['limit'] = 5;
    
    ob_start();
    include __DIR__ . '/ajax/sales.php';
    $ajaxOutput = ob_get_clean();
    
    echo "<h4>AJAX Response:</h4>";
    echo "<pre style='background: #f8f9fa; padding: 15px; border-radius: 4px; font-size: 12px; overflow-x: auto;'>";
    echo htmlspecialchars($ajaxOutput);
    echo "</pre>";
    
    $ajaxResponse = json_decode($ajaxOutput, true);
    
    if ($ajaxResponse && $ajaxResponse['success']) {
        echo "✅ AJAX handler working correctly<br>";
        echo "AJAX Records returned: " . count($ajaxResponse['data']) . "<br>";
    } else {
        echo "❌ AJAX handler error<br>";
        if ($ajaxResponse) {
            echo "Error: " . ($ajaxResponse['message'] ?? 'Unknown error') . "<br>";
        }
    }
    
    echo "<h3>5. Frontend JavaScript Test</h3>";
    
    echo "<div id='js-test-results'></div>";
    
    echo "<script>
    console.log('=== Sales Data Debug Test ===');
    
    // Test AJAX response
    const ajaxResponse = " . json_encode($ajaxResponse) . ";
    console.log('AJAX Response:', ajaxResponse);
    
    let testResults = [];
    
    if (ajaxResponse && ajaxResponse.success) {
        testResults.push('✅ AJAX response valid');
        
        if (ajaxResponse.data && Array.isArray(ajaxResponse.data)) {
            testResults.push('✅ Data array present (' + ajaxResponse.data.length + ' records)');
            
            if (ajaxResponse.data.length > 0) {
                testResults.push('✅ Records found in response');
                console.log('Sample record:', ajaxResponse.data[0]);
            } else {
                testResults.push('⚠️ No records in response (empty table)');
            }
        } else {
            testResults.push('❌ Data array missing or invalid');
        }
        
        if (ajaxResponse.pagination) {
            testResults.push('✅ Pagination data present');
            console.log('Pagination:', ajaxResponse.pagination);
        } else {
            testResults.push('❌ Pagination data missing');
        }
        
    } else {
        testResults.push('❌ AJAX response invalid or failed');
        if (ajaxResponse && ajaxResponse.message) {
            testResults.push('Error: ' + ajaxResponse.message);
        }
    }
    
    // Test if SalesController would work
    if (typeof SalesController !== 'undefined') {
        testResults.push('✅ SalesController class available');
    } else {
        testResults.push('❌ SalesController class not found');
    }
    
    // Test if required utilities exist
    if (typeof Utils !== 'undefined') {
        testResults.push('✅ Utils class available');
    } else {
        testResults.push('❌ Utils class not found');
    }
    
    if (typeof AjaxHelper !== 'undefined') {
        testResults.push('✅ AjaxHelper class available');
    } else {
        testResults.push('❌ AjaxHelper class not found');
    }
    
    // Display results
    document.getElementById('js-test-results').innerHTML = 
        '<h4>JavaScript Test Results:</h4><ul>' + 
        testResults.map(result => '<li>' + result + '</li>').join('') + 
        '</ul>';
    </script>";
    
    echo "<h3>6. Recommendations</h3>";
    
    if ($totalRecords == 0) {
        echo "<div style='background: #f8d7da; padding: 15px; border-radius: 4px; color: #721c24;'>";
        echo "<h5>❌ Main Issue: No Data in Table</h5>";
        echo "<p>The sales_data table exists but is empty. You need to add some records.</p>";
        echo "</div>";
    } elseif (!$result['success']) {
        echo "<div style='background: #f8d7da; padding: 15px; border-radius: 4px; color: #721c24;'>";
        echo "<h5>❌ Main Issue: SalesData Class Error</h5>";
        echo "<p>The SalesData class is not working correctly: " . $result['message'] . "</p>";
        echo "</div>";
    } elseif (!$ajaxResponse || !$ajaxResponse['success']) {
        echo "<div style='background: #f8d7da; padding: 15px; border-radius: 4px; color: #721c24;'>";
        echo "<h5>❌ Main Issue: AJAX Handler Error</h5>";
        echo "<p>The AJAX handler is not working correctly.</p>";
        echo "</div>";
    } else {
        echo "<div style='background: #d4edda; padding: 15px; border-radius: 4px;'>";
        echo "<h5>✅ Backend Working Correctly</h5>";
        echo "<p>The issue might be in the frontend JavaScript or browser cache.</p>";
        echo "</div>";
    }
    
    echo "<h3>7. Quick Actions</h3>";
    
    echo "<div style='margin: 20px 0;'>";
    echo "<button onclick='addSampleData()' style='padding: 10px 20px; background: #28a745; color: white; border: none; border-radius: 4px; margin-right: 10px; cursor: pointer;'>➕ Add Sample Data</button>";
    echo "<a href='pages/sales/index.php?v=" . time() . "' target='_blank' style='padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; margin-right: 10px;'>🧪 Test Sales Page</a>";
    echo "<a href='ajax/sales.php?action=list&page=1&limit=5' target='_blank' style='padding: 10px 20px; background: #17a2b8; color: white; text-decoration: none; border-radius: 4px;'>🔗 Test AJAX Direct</a>";
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

<script>
async function addSampleData() {
    if (!confirm('Add sample sales data to the table?')) return;
    
    try {
        const response = await fetch(window.location.href, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=add_sample_data'
        });
        
        const text = await response.text();
        alert('Sample data added! Refresh the page to see results.');
        location.reload();
        
    } catch (error) {
        alert('Error adding sample data: ' + error.message);
    }
}
</script>

<?php
// Handle sample data addition
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_sample_data') {
    try {
        require_once __DIR__ . '/classes/Database.php';
        $db = Database::getInstance();
        
        $sampleData = [
            [
                'invoice_num' => 'INV-001',
                'dated' => '2026-03-01 10:00:00',
                'business_name' => 'ABC Company Ltd',
                'sales_rep' => 'John Smith',
                'product' => 'Sample Product A',
                'quantity' => 10,
                'unit_price' => 25.50,
                'line_revenue' => 255.00,
                'created_by' => getCurrentUser()['id']
            ],
            [
                'invoice_num' => 'INV-002', 
                'dated' => '2026-03-02 14:30:00',
                'business_name' => 'XYZ Corporation',
                'sales_rep' => 'Jane Doe',
                'product' => 'Sample Product B',
                'quantity' => 5,
                'unit_price' => 45.00,
                'line_revenue' => 225.00,
                'created_by' => getCurrentUser()['id']
            ],
            [
                'invoice_num' => 'INV-003',
                'dated' => '2026-03-03 09:15:00', 
                'business_name' => 'Test Business Inc',
                'sales_rep' => 'Mike Johnson',
                'product' => 'Sample Product C',
                'quantity' => 8,
                'unit_price' => 30.75,
                'line_revenue' => 246.00,
                'created_by' => getCurrentUser()['id']
            ]
        ];
        
        foreach ($sampleData as $data) {
            $sql = "INSERT INTO sales_data (invoice_num, dated, business_name, sales_rep, product, quantity, unit_price, line_revenue, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $db->execute($sql, [
                $data['invoice_num'],
                $data['dated'],
                $data['business_name'],
                $data['sales_rep'],
                $data['product'],
                $data['quantity'],
                $data['unit_price'],
                $data['line_revenue'],
                $data['created_by']
            ]);
        }
        
        echo "<div style='background: #d4edda; padding: 15px; border-radius: 4px; margin: 15px 0;'>";
        echo "<h4>✅ Sample Data Added!</h4>";
        echo "<p>3 sample records have been added to the sales_data table.</p>";
        echo "</div>";
        
    } catch (Exception $e) {
        echo "<div style='background: #f8d7da; padding: 15px; border-radius: 4px; color: #721c24; margin: 15px 0;'>";
        echo "<h4>❌ Error Adding Sample Data</h4>";
        echo "<p>" . $e->getMessage() . "</p>";
        echo "</div>";
    }
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; background: #f8f9fa; }
h1, h3, h4, h5 { color: #333; }
table { background: white; border-collapse: collapse; }
a { color: #007bff; text-decoration: none; }
a:hover { text-decoration: underline; }
ul, ol { margin: 10px 0; }
li { margin: 5px 0; }
pre { font-size: 11px; }
button { cursor: pointer; }
button:hover { opacity: 0.9; }
</style>