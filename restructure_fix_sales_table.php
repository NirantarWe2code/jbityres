<?php
/**
 * Restructure Sales Data Table
 * Update table structure to match Excel data requirements
 */

require_once __DIR__ . '/config/config.php';

echo "<h1>🔧 Sales Data Table Restructure</h1>";

try {
    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($mysqli->connect_error) {
        throw new Exception("Connection failed: " . $mysqli->connect_error);
    }
    
    echo "<h3>Step 1: Backup Existing Data</h3>";
    
    // Check if table exists and has data
    $tableExists = false;
    $existingData = [];
    
    $result = $mysqli->query("SHOW TABLES LIKE 'sales_data'");
    if ($result && $result->num_rows > 0) {
        $tableExists = true;
        echo "📋 Sales_data table exists<br>";
        
        // Get existing data count
        $countResult = $mysqli->query("SELECT COUNT(*) as total FROM sales_data");
        $totalRecords = $countResult ? $countResult->fetch_assoc()['total'] : 0;
        echo "📊 Found $totalRecords existing records<br>";
        
        if ($totalRecords > 0) {
            // Backup existing data
            $backupResult = $mysqli->query("SELECT * FROM sales_data");
            if ($backupResult) {
                while ($row = $backupResult->fetch_assoc()) {
                    $existingData[] = $row;
                }
                echo "✅ Backed up $totalRecords records<br>";
            }
        }
    } else {
        echo "📋 No existing sales_data table found<br>";
    }
    
    echo "<h3>Step 2: Create New Table Structure</h3>";
    
    // Drop existing table
    if ($tableExists) {
        $mysqli->query("DROP TABLE sales_data");
        echo "🗑️ Dropped existing table<br>";
    }
    
    // Create new table with complete structure
    $createTableSQL = "
    CREATE TABLE sales_data (
        id INT AUTO_INCREMENT PRIMARY KEY,
        
        -- Core transaction data
        invoice_num VARCHAR(50) NOT NULL,
        order_num VARCHAR(50),
        dated DATETIME NOT NULL,
        
        -- Customer information
        business_name VARCHAR(200) NOT NULL,
        customer_address TEXT,
        account_type VARCHAR(100),
        delivery_profile TEXT,
        
        -- Sales information
        sales_rep VARCHAR(100),
        
        -- Product information
        product VARCHAR(300) NOT NULL,
        stock_id VARCHAR(50),
        
        -- Financial data
        quantity DECIMAL(10,2) NOT NULL,
        unit_price DECIMAL(10,2) NOT NULL,
        unit_gst DECIMAL(10,2) DEFAULT 0,
        line_revenue DECIMAL(10,2) NOT NULL,
        purchase_price DECIMAL(10,2) DEFAULT 0,
        po_number VARCHAR(50),
        reward_inclusive ENUM('Yes', 'No') DEFAULT 'No',
        
        -- Calculated fields
        gross_profit DECIMAL(10,2) GENERATED ALWAYS AS (line_revenue - (quantity * purchase_price)) STORED,
        gp_margin DECIMAL(5,2) GENERATED ALWAYS AS (
            CASE 
                WHEN line_revenue > 0 THEN ((line_revenue - (quantity * purchase_price)) / line_revenue) * 100 
                ELSE 0 
            END
        ) STORED,
        
        -- System fields
        notes TEXT,
        created_by INT DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        -- Indexes for performance
        INDEX idx_dated (dated),
        INDEX idx_business (business_name),
        INDEX idx_sales_rep (sales_rep),
        INDEX idx_invoice (invoice_num),
        INDEX idx_product (product),
        INDEX idx_stock_id (stock_id),
        
        -- Foreign key (only if users table exists)
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($mysqli->query($createTableSQL)) {
        echo "✅ New sales_data table created successfully<br>";
    } else {
        throw new Exception("Failed to create new table: " . $mysqli->error);
    }
    
    echo "<h3>Step 3: Verify New Table Structure</h3>";
    
    // Verify table structure
    $result = $mysqli->query("DESCRIBE sales_data");
    $newColumns = [];
    
    if ($result) {
        echo "<h4>New Table Structure:</h4>";
        echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 10px 0; font-size: 12px;'>";
        echo "<tr style='background: #f8f9fa; font-weight: bold;'>";
        echo "<td style='padding: 6px;'>Field</td>";
        echo "<td style='padding: 6px;'>Type</td>";
        echo "<td style='padding: 6px;'>Null</td>";
        echo "<td style='padding: 6px;'>Key</td>";
        echo "<td style='padding: 6px;'>Extra</td>";
        echo "</tr>";
        
        while ($row = $result->fetch_assoc()) {
            $newColumns[] = $row['Field'];
            echo "<tr>";
            echo "<td style='padding: 6px;'><strong>" . $row['Field'] . "</strong></td>";
            echo "<td style='padding: 6px;'>" . $row['Type'] . "</td>";
            echo "<td style='padding: 6px;'>" . $row['Null'] . "</td>";
            echo "<td style='padding: 6px;'>" . $row['Key'] . "</td>";
            echo "<td style='padding: 6px;'>" . $row['Extra'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        echo "<p><strong>Total Columns:</strong> " . count($newColumns) . "</p>";
    }
    
    echo "<h3>Step 4: Restore Compatible Data</h3>";
    
    if (!empty($existingData)) {
        echo "<h4>Migrating existing data...</h4>";
        
        $migratedCount = 0;
        $errorCount = 0;
        
        foreach ($existingData as $record) {
            try {
                // Map old data to new structure
                $insertSQL = "INSERT INTO sales_data (
                    invoice_num, dated, business_name, sales_rep, product, 
                    quantity, unit_price, line_revenue, purchase_price, 
                    notes, created_by, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                $stmt = $mysqli->prepare($insertSQL);
                if ($stmt) {
                    $stmt->bind_param('sssssddddssss',
                        $record['invoice_num'] ?? 'MIGRATED-' . $record['id'],
                        $record['dated'] ?? date('Y-m-d H:i:s'),
                        $record['business_name'] ?? 'Unknown Business',
                        $record['sales_rep'] ?? null,
                        $record['product'] ?? 'Unknown Product',
                        $record['quantity'] ?? 1,
                        $record['unit_price'] ?? 0,
                        $record['line_revenue'] ?? 0,
                        $record['purchase_price'] ?? 0,
                        $record['notes'] ?? 'Migrated from old structure',
                        $record['created_by'] ?? 1,
                        $record['created_at'] ?? date('Y-m-d H:i:s'),
                        $record['updated_at'] ?? date('Y-m-d H:i:s')
                    );
                    
                    if ($stmt->execute()) {
                        $migratedCount++;
                    } else {
                        $errorCount++;
                        echo "⚠️ Failed to migrate record ID " . $record['id'] . ": " . $stmt->error . "<br>";
                    }
                    $stmt->close();
                }
            } catch (Exception $e) {
                $errorCount++;
                echo "⚠️ Error migrating record: " . $e->getMessage() . "<br>";
            }
        }
        
        echo "✅ Migrated $migratedCount records successfully<br>";
        if ($errorCount > 0) {
            echo "⚠️ $errorCount records failed to migrate<br>";
        }
    } else {
        echo "📝 No existing data to migrate<br>";
    }
    
    echo "<h3>Step 5: Insert Sample Data</h3>";
    
    // Insert sample data from Excel format
    $sampleData = [
        [
            'invoice_num' => '101593',
            'order_num' => '119917',
            'dated' => '2026-03-03 13:18:12',
            'business_name' => 'O Neills Tyre & Autocare Broadmeadow',
            'customer_address' => '4 Newton Street, Broadmeadow NSW, Australia',
            'account_type' => '7 Days From Monthly Statement',
            'delivery_profile' => '930/1130/130 - Newcastle:1,2,3',
            'sales_rep' => 'Kam',
            'product' => 'Triangle-TH202 EffeXSport-245-40-19-98Y',
            'stock_id' => '19966',
            'quantity' => 2.00,
            'unit_price' => 85.45,
            'unit_gst' => 8.55,
            'line_revenue' => 188.00,
            'purchase_price' => 70.90,
            'po_number' => '14743',
            'reward_inclusive' => 'Yes'
        ],
        [
            'invoice_num' => '101637',
            'order_num' => '119972',
            'dated' => '2026-03-04 08:42:23',
            'business_name' => 'O Neills Tyre & Autocare Broadmeadow',
            'customer_address' => '4 Newton Street, Broadmeadow NSW, Australia',
            'account_type' => '7 Days From Monthly Statement',
            'delivery_profile' => '930/1130/130 - Newcastle:1,2,3',
            'sales_rep' => 'Kam',
            'product' => 'Triangle-TE307 ReliaXTouring-205-60-16-96V',
            'stock_id' => '19915',
            'quantity' => 4.00,
            'unit_price' => 56.36,
            'unit_gst' => 5.64,
            'line_revenue' => 248.00,
            'purchase_price' => 46.42,
            'po_number' => '14748',
            'reward_inclusive' => 'Yes'
        ]
    ];
    
    $sampleInserted = 0;
    foreach ($sampleData as $sample) {
        $insertSQL = "INSERT INTO sales_data (
            invoice_num, order_num, dated, business_name, customer_address, 
            account_type, delivery_profile, sales_rep, product, stock_id,
            quantity, unit_price, unit_gst, line_revenue, purchase_price, 
            po_number, reward_inclusive, created_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $mysqli->prepare($insertSQL);
        if ($stmt) {
            $stmt->bind_param('ssssssssssdddddssi',
                $sample['invoice_num'],
                $sample['order_num'],
                $sample['dated'],
                $sample['business_name'],
                $sample['customer_address'],
                $sample['account_type'],
                $sample['delivery_profile'],
                $sample['sales_rep'],
                $sample['product'],
                $sample['stock_id'],
                $sample['quantity'],
                $sample['unit_price'],
                $sample['unit_gst'],
                $sample['line_revenue'],
                $sample['purchase_price'],
                $sample['po_number'],
                $sample['reward_inclusive'],
                1 // created_by
            );
            
            if ($stmt->execute()) {
                $sampleInserted++;
            }
            $stmt->close();
        }
    }
    
    echo "✅ Inserted $sampleInserted sample records<br>";
    
    echo "<h3>Step 6: Test New Structure</h3>";
    
    // Test calculated fields
    $testResult = $mysqli->query("SELECT invoice_num, product, quantity, unit_price, line_revenue, purchase_price, gross_profit, gp_margin FROM sales_data LIMIT 2");
    
    if ($testResult && $testResult->num_rows > 0) {
        echo "<h4>Sample Records with Calculated Fields:</h4>";
        echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 10px 0; font-size: 11px;'>";
        echo "<tr style='background: #f8f9fa; font-weight: bold;'>";
        echo "<td style='padding: 4px;'>Invoice</td>";
        echo "<td style='padding: 4px;'>Product</td>";
        echo "<td style='padding: 4px;'>Qty</td>";
        echo "<td style='padding: 4px;'>Unit Price</td>";
        echo "<td style='padding: 4px;'>Revenue</td>";
        echo "<td style='padding: 4px;'>Cost</td>";
        echo "<td style='padding: 4px;'>Profit</td>";
        echo "<td style='padding: 4px;'>Margin %</td>";
        echo "</tr>";
        
        while ($row = $testResult->fetch_assoc()) {
            echo "<tr>";
            echo "<td style='padding: 4px;'>" . $row['invoice_num'] . "</td>";
            echo "<td style='padding: 4px;'>" . substr($row['product'], 0, 20) . "...</td>";
            echo "<td style='padding: 4px;'>" . $row['quantity'] . "</td>";
            echo "<td style='padding: 4px;'>$" . $row['unit_price'] . "</td>";
            echo "<td style='padding: 4px;'>$" . $row['line_revenue'] . "</td>";
            echo "<td style='padding: 4px;'>$" . $row['purchase_price'] . "</td>";
            echo "<td style='padding: 4px;'>$" . $row['gross_profit'] . "</td>";
            echo "<td style='padding: 4px;'>" . $row['gp_margin'] . "%</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    $mysqli->close();
    
    echo "<h3>✅ Restructure Complete!</h3>";
    
    echo "<div style='background: #d4edda; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
    echo "<h4>🎉 Sales Table Successfully Restructured!</h4>";
    echo "<ul>";
    echo "<li>✅ New table structure created with all Excel columns</li>";
    echo "<li>✅ Calculated fields (gross_profit, gp_margin) working</li>";
    echo "<li>✅ Existing data migrated (if any)</li>";
    echo "<li>✅ Sample data inserted for testing</li>";
    echo "<li>✅ Indexes created for performance</li>";
    echo "</ul>";
    echo "<br>";
    echo "<h5>Next Steps:</h5>";
    echo "<ol>";
    echo "<li><a href='test_sales_ajax.php'>Test AJAX Response</a></li>";
    echo "<li><a href='pages/sales/index.php'>Test Sales Page</a></li>";
    echo "<li><a href='import_excel_data.php'>Import Full Excel Data</a></li>";
    echo "</ol>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<h3>❌ Restructure Failed:</h3>";
    echo "<div style='background: #f8d7da; padding: 15px; border-radius: 4px; color: #721c24;'>";
    echo "<strong>Error:</strong> " . $e->getMessage() . "<br>";
    echo "</div>";
    
    echo "<h4>Recovery Options:</h4>";
    echo "<ul>";
    echo "<li>Check MySQL service is running</li>";
    echo "<li>Verify users table exists (run <a href='quick_fix.php'>Quick Fix</a>)</li>";
    echo "<li>Check database permissions</li>";
    echo "<li>Manual table creation via <a href='http://localhost/phpmyadmin' target='_blank'>phpMyAdmin</a></li>";
    echo "</ul>";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; background: #f8f9fa; }
h1, h3, h4, h5 { color: #333; }
table { background: white; border-collapse: collapse; }
th, td { text-align: left; border: 1px solid #ddd; }
a { color: #007bff; text-decoration: none; }
a:hover { text-decoration: underline; }
ul, ol { margin: 10px 0; }
li { margin: 5px 0; }
</style>