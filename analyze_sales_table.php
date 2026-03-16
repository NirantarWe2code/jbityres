<?php
/**
 * Analyze Sales Data Table Structure
 * Check current structure and compare with Excel requirements
 */

require_once __DIR__ . '/config/config.php';

echo "<h1>📊 Sales Data Table Analysis</h1>";

try {
    // Direct MySQLi connection
    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($mysqli->connect_error) {
        throw new Exception("Connection failed: " . $mysqli->connect_error);
    }
    
    echo "<h3>1. Current Sales Data Table Structure</h3>";
    
    // Check if table exists
    $tableExists = false;
    $result = $mysqli->query("SHOW TABLES LIKE 'sales_data'");
    if ($result && $result->num_rows > 0) {
        $tableExists = true;
        echo "✅ Sales_data table exists<br>";
    } else {
        echo "❌ Sales_data table does not exist<br>";
    }
    
    if ($tableExists) {
        // Get table structure
        $result = $mysqli->query("DESCRIBE sales_data");
        
        echo "<h4>Current Table Structure:</h4>";
        echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 10px 0;'>";
        echo "<tr style='background: #f8f9fa; font-weight: bold;'>";
        echo "<td style='padding: 8px;'>Field</td>";
        echo "<td style='padding: 8px;'>Type</td>";
        echo "<td style='padding: 8px;'>Null</td>";
        echo "<td style='padding: 8px;'>Key</td>";
        echo "<td style='padding: 8px;'>Default</td>";
        echo "<td style='padding: 8px;'>Extra</td>";
        echo "</tr>";
        
        $currentColumns = [];
        while ($row = $result->fetch_assoc()) {
            $currentColumns[$row['Field']] = $row;
            echo "<tr>";
            echo "<td style='padding: 8px;'><strong>" . $row['Field'] . "</strong></td>";
            echo "<td style='padding: 8px;'>" . $row['Type'] . "</td>";
            echo "<td style='padding: 8px;'>" . $row['Null'] . "</td>";
            echo "<td style='padding: 8px;'>" . $row['Key'] . "</td>";
            echo "<td style='padding: 8px;'>" . ($row['Default'] ?? 'NULL') . "</td>";
            echo "<td style='padding: 8px;'>" . $row['Extra'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Check sample data
        echo "<h4>Sample Data (First 3 Records):</h4>";
        $sampleResult = $mysqli->query("SELECT * FROM sales_data LIMIT 3");
        
        if ($sampleResult && $sampleResult->num_rows > 0) {
            echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 10px 0; font-size: 11px;'>";
            
            $firstRow = true;
            while ($row = $sampleResult->fetch_assoc()) {
                if ($firstRow) {
                    echo "<tr style='background: #f8f9fa; font-weight: bold;'>";
                    foreach (array_keys($row) as $column) {
                        echo "<td style='padding: 4px; max-width: 100px;'>$column</td>";
                    }
                    echo "</tr>";
                    $firstRow = false;
                }
                
                echo "<tr>";
                foreach ($row as $value) {
                    $displayValue = is_null($value) ? 'NULL' : htmlspecialchars(substr($value, 0, 50));
                    echo "<td style='padding: 4px; max-width: 100px; word-wrap: break-word;'>$displayValue</td>";
                }
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p>⚠️ No sample data found in table</p>";
        }
        
        // Get total record count
        $countResult = $mysqli->query("SELECT COUNT(*) as total FROM sales_data");
        $totalRecords = $countResult ? $countResult->fetch_assoc()['total'] : 0;
        echo "<p><strong>Total Records:</strong> $totalRecords</p>";
    }
    
    echo "<h3>2. Excel Data Requirements Analysis</h3>";
    
    // Excel columns from the actual file
    $excelColumns = [
        'Business_Name' => ['type' => 'VARCHAR(200)', 'required' => true, 'maps_to' => 'business_name'],
        'Delivery_Profile' => ['type' => 'TEXT', 'required' => false, 'maps_to' => 'delivery_profile'],
        'Sales_Rep' => ['type' => 'VARCHAR(100)', 'required' => false, 'maps_to' => 'sales_rep'],
        'AccountType' => ['type' => 'VARCHAR(100)', 'required' => false, 'maps_to' => 'account_type'],
        'address' => ['type' => 'TEXT', 'required' => false, 'maps_to' => 'customer_address'],
        'Invoice_Num' => ['type' => 'VARCHAR(50)', 'required' => true, 'maps_to' => 'invoice_num'],
        'Order_Num' => ['type' => 'VARCHAR(50)', 'required' => false, 'maps_to' => 'order_num'],
        'Dated' => ['type' => 'DATETIME', 'required' => true, 'maps_to' => 'dated'],
        'product' => ['type' => 'VARCHAR(300)', 'required' => true, 'maps_to' => 'product'],
        'stock_id' => ['type' => 'VARCHAR(50)', 'required' => false, 'maps_to' => 'stock_id'],
        'Quantity' => ['type' => 'DECIMAL(10,2)', 'required' => true, 'maps_to' => 'quantity'],
        'Unit_Price' => ['type' => 'DECIMAL(10,2)', 'required' => true, 'maps_to' => 'unit_price'],
        'Unit_GST' => ['type' => 'DECIMAL(10,2)', 'required' => false, 'maps_to' => 'unit_gst'],
        'Total_Amount' => ['type' => 'DECIMAL(10,2)', 'required' => true, 'maps_to' => 'line_revenue'],
        'PONumber' => ['type' => 'VARCHAR(50)', 'required' => false, 'maps_to' => 'po_number'],
        'Purchase_Price' => ['type' => 'DECIMAL(10,2)', 'required' => false, 'maps_to' => 'purchase_price'],
        'Reward_inclusive' => ['type' => 'ENUM("Yes","No")', 'required' => false, 'maps_to' => 'reward_inclusive']
    ];
    
    echo "<h4>Excel Columns to Database Mapping:</h4>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 10px 0;'>";
    echo "<tr style='background: #f8f9fa; font-weight: bold;'>";
    echo "<td style='padding: 8px;'>Excel Column</td>";
    echo "<td style='padding: 8px;'>Database Column</td>";
    echo "<td style='padding: 8px;'>Data Type</td>";
    echo "<td style='padding: 8px;'>Required</td>";
    echo "<td style='padding: 8px;'>Current Status</td>";
    echo "</tr>";
    
    foreach ($excelColumns as $excelCol => $info) {
        $dbColumn = $info['maps_to'];
        $exists = isset($currentColumns[$dbColumn]);
        
        echo "<tr>";
        echo "<td style='padding: 8px;'><strong>$excelCol</strong></td>";
        echo "<td style='padding: 8px;'><strong>$dbColumn</strong></td>";
        echo "<td style='padding: 8px;'>" . $info['type'] . "</td>";
        echo "<td style='padding: 8px;'>" . ($info['required'] ? 'Yes' : 'No') . "</td>";
        echo "<td style='padding: 8px;'>" . ($exists ? "✅ EXISTS" : "❌ MISSING") . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<h3>3. Recommended Table Structure</h3>";
    
    echo "<h4>Optimized Sales Data Table Structure:</h4>";
    
    $recommendedStructure = "
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
        
        -- Foreign key
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    echo "<textarea style='width: 100%; height: 400px; font-family: monospace; font-size: 12px;'>";
    echo $recommendedStructure;
    echo "</textarea>";
    
    echo "<h3>4. Migration Analysis</h3>";
    
    if ($tableExists) {
        echo "<h4>Missing Columns Analysis:</h4>";
        
        $requiredColumns = array_column($excelColumns, 'maps_to');
        $currentColumnNames = array_keys($currentColumns);
        $missingColumns = array_diff($requiredColumns, $currentColumnNames);
        
        if (!empty($missingColumns)) {
            echo "<div style='background: #fff3cd; padding: 15px; border-radius: 4px; margin: 10px 0;'>";
            echo "<h5>⚠️ Missing Columns:</h5>";
            echo "<ul>";
            foreach ($missingColumns as $column) {
                // Find the Excel column that maps to this
                $excelCol = '';
                foreach ($excelColumns as $excel => $info) {
                    if ($info['maps_to'] === $column) {
                        $excelCol = $excel;
                        break;
                    }
                }
                echo "<li><strong>$column</strong> (from Excel: $excelCol)</li>";
            }
            echo "</ul>";
            echo "</div>";
            
            echo "<h5>Migration Options:</h5>";
            echo "<ol>";
            echo "<li><strong>Add Missing Columns:</strong> ALTER TABLE to add missing columns</li>";
            echo "<li><strong>Recreate Table:</strong> Drop and recreate with full structure</li>";
            echo "<li><strong>Create New Table:</strong> Create sales_data_new with full structure</li>";
            echo "</ol>";
        } else {
            echo "<div style='background: #d4edda; padding: 15px; border-radius: 4px;'>";
            echo "✅ All required columns are present in the current table.";
            echo "</div>";
        }
        
        // Check data compatibility
        echo "<h4>Data Compatibility Check:</h4>";
        
        if ($totalRecords > 0) {
            echo "<p><strong>Current Data:</strong> $totalRecords records exist</p>";
            echo "<p><strong>Recommendation:</strong> Backup existing data before any structural changes</p>";
        } else {
            echo "<p><strong>Current Data:</strong> Table is empty</p>";
            echo "<p><strong>Recommendation:</strong> Safe to recreate table structure</p>";
        }
    }
    
    $mysqli->close();
    
    echo "<h3>5. Action Buttons</h3>";
    
    echo "<div style='margin: 20px 0;'>";
    echo "<a href='restructure_sales_table.php' style='padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; margin-right: 10px;'>🔧 Restructure Table</a>";
    echo "<a href='import_excel_data.php' style='padding: 10px 20px; background: #28a745; color: white; text-decoration: none; border-radius: 4px; margin-right: 10px;'>📊 Import Excel Data</a>";
    echo "<a href='test_sales_ajax.php' style='padding: 10px 20px; background: #17a2b8; color: white; text-decoration: none; border-radius: 4px;'>🧪 Test AJAX</a>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<h3>❌ Analysis Failed:</h3>";
    echo "<div style='background: #f8d7da; padding: 15px; border-radius: 4px; color: #721c24;'>";
    echo "<strong>Error:</strong> " . $e->getMessage() . "<br>";
    echo "</div>";
    
    echo "<h4>Troubleshooting:</h4>";
    echo "<ul>";
    echo "<li>Check XAMPP MySQL service is running</li>";
    echo "<li>Verify database credentials in config.php</li>";
    echo "<li>Ensure database 'sales_reports' exists</li>";
    echo "<li><a href='quick_fix.php'>Run Quick Fix</a> to create basic structure</li>";
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
textarea { border: 1px solid #ddd; border-radius: 4px; padding: 10px; }
</style>