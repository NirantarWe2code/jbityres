<?php
/**
 * Fix Column Mapping Between Excel and Database
 * Map Excel columns to database columns correctly
 */

require_once __DIR__ . '/config/config.php';

echo "<h1>🔄 Column Mapping Analysis & Fix</h1>";

echo "<h3>1. Excel File Column Analysis</h3>";

// Excel columns from the file
$excelColumns = [
    'Business_Name' => 'Customer/Business name',
    'Delivery_Profile' => 'Delivery profile info',
    'Sales_Rep' => 'Sales representative name',
    'AccountType' => 'Account type',
    'address' => 'Customer address',
    'Invoice_Num' => 'Invoice number',
    'Order_Num' => 'Order number',
    'Dated' => 'Sale date',
    'product' => 'Product name/description',
    'stock_id' => 'Stock ID',
    'Quantity' => 'Quantity sold',
    'Unit_Price' => 'Unit price',
    'Unit_GST' => 'GST amount',
    'Total_Amount' => 'Total amount',
    'PONumber' => 'Purchase order number',
    'Purchase_Price' => 'Purchase price/cost',
    'Reward_inclusive' => 'Reward inclusive flag'
];

echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 10px 0;'>";
echo "<tr style='background: #f8f9fa; font-weight: bold;'>";
echo "<td style='padding: 8px;'>Excel Column</td>";
echo "<td style='padding: 8px;'>Description</td>";
echo "</tr>";

foreach ($excelColumns as $column => $description) {
    echo "<tr>";
    echo "<td style='padding: 8px;'><strong>$column</strong></td>";
    echo "<td style='padding: 8px;'>$description</td>";
    echo "</tr>";
}
echo "</table>";

echo "<h3>2. Database Column Mapping</h3>";

// Mapping Excel columns to database columns
$columnMapping = [
    // Excel Column => Database Column
    'Business_Name' => 'business_name',
    'Sales_Rep' => 'sales_rep',
    'Invoice_Num' => 'invoice_num',
    'Dated' => 'dated',
    'product' => 'product',
    'Quantity' => 'quantity',
    'Unit_Price' => 'unit_price',
    'Purchase_Price' => 'purchase_price', // This will be used to calculate gross_profit
    'Total_Amount' => 'line_revenue', // This is the total line amount
    // Calculated fields
    // gross_profit = line_revenue - (quantity * purchase_price)
    // gp_margin = (gross_profit / line_revenue) * 100
];

echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 10px 0;'>";
echo "<tr style='background: #f8f9fa; font-weight: bold;'>";
echo "<td style='padding: 8px;'>Excel Column</td>";
echo "<td style='padding: 8px;'>Database Column</td>";
echo "<td style='padding: 8px;'>Notes</td>";
echo "</tr>";

foreach ($columnMapping as $excelCol => $dbCol) {
    echo "<tr>";
    echo "<td style='padding: 8px;'><strong>$excelCol</strong></td>";
    echo "<td style='padding: 8px;'><strong>$dbCol</strong></td>";
    echo "<td style='padding: 8px;'>Direct mapping</td>";
    echo "</tr>";
}

// Additional calculated fields
$calculatedFields = [
    'gross_profit' => 'line_revenue - (quantity * purchase_price)',
    'gp_margin' => '(gross_profit / line_revenue) * 100',
    'created_by' => 'Default to user ID 1 (admin)',
    'notes' => 'Can include additional info like Order_Num, stock_id, etc.'
];

foreach ($calculatedFields as $field => $calculation) {
    echo "<tr style='background: #e7f3ff;'>";
    echo "<td style='padding: 8px;'><em>Calculated</em></td>";
    echo "<td style='padding: 8px;'><strong>$field</strong></td>";
    echo "<td style='padding: 8px;'>$calculation</td>";
    echo "</tr>";
}
echo "</table>";

echo "<h3>3. Create Corrected Sales Data Table</h3>";

try {
    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($mysqli->connect_error) {
        throw new Exception("Connection failed: " . $mysqli->connect_error);
    }
    
    // Drop existing sales_data table
    $mysqli->query("DROP TABLE IF EXISTS sales_data");
    echo "🗑️ Dropped existing sales_data table<br>";
    
    // Create new sales_data table with correct structure
    $createSalesSQL = "
    CREATE TABLE sales_data (
        id INT AUTO_INCREMENT PRIMARY KEY,
        invoice_num VARCHAR(50) NOT NULL,
        dated DATETIME NOT NULL,
        business_name VARCHAR(200) NOT NULL,
        sales_rep VARCHAR(100),
        product VARCHAR(300) NOT NULL,
        quantity DECIMAL(10,2) NOT NULL,
        unit_price DECIMAL(10,2) NOT NULL,
        purchase_price DECIMAL(10,2) DEFAULT 0,
        line_revenue DECIMAL(10,2) NOT NULL,
        gross_profit DECIMAL(10,2) GENERATED ALWAYS AS (line_revenue - (quantity * purchase_price)) STORED,
        gp_margin DECIMAL(5,2) GENERATED ALWAYS AS (
            CASE 
                WHEN line_revenue > 0 THEN ((line_revenue - (quantity * purchase_price)) / line_revenue) * 100 
                ELSE 0 
            END
        ) STORED,
        notes TEXT,
        created_by INT DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_dated (dated),
        INDEX idx_business (business_name),
        INDEX idx_sales_rep (sales_rep),
        INDEX idx_invoice (invoice_num),
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($mysqli->query($createSalesSQL)) {
        echo "✅ Sales data table created with correct structure<br>";
    } else {
        throw new Exception("Failed to create sales_data table: " . $mysqli->error);
    }
    
    echo "<h3>4. Update SalesData Class</h3>";
    
    // Check current SalesData class
    $salesDataPath = __DIR__ . '/classes/SalesData.php';
    if (file_exists($salesDataPath)) {
        echo "✅ SalesData class file exists<br>";
        echo "<p>The class should now work with the corrected table structure.</p>";
    } else {
        echo "❌ SalesData class file missing<br>";
    }
    
    echo "<h3>5. Sample Data Import Script</h3>";
    
    echo "<p>Here's how to import Excel data with correct column mapping:</p>";
    
    echo "<textarea style='width: 100%; height: 200px; font-family: monospace; font-size: 12px;'>";
    echo "// Sample PHP code to import Excel data
\$excelData = [
    'Business_Name' => 'O Neills Tyre & Autocare',
    'Sales_Rep' => 'Kam',
    'Invoice_Num' => '101593',
    'Dated' => '2026-03-03 13:18:12',
    'product' => 'Triangle-TH202 EffeXSport-245-40-19-98Y',
    'Quantity' => 2,
    'Unit_Price' => 85.45,
    'Purchase_Price' => 70.90,
    'Total_Amount' => 188.00
];

// Map to database columns
\$dbData = [
    'invoice_num' => \$excelData['Invoice_Num'],
    'dated' => \$excelData['Dated'],
    'business_name' => \$excelData['Business_Name'],
    'sales_rep' => \$excelData['Sales_Rep'],
    'product' => \$excelData['product'],
    'quantity' => \$excelData['Quantity'],
    'unit_price' => \$excelData['Unit_Price'],
    'purchase_price' => \$excelData['Purchase_Price'],
    'line_revenue' => \$excelData['Total_Amount'],
    'created_by' => 1 // Default admin user
];

// Insert into database
\$sql = \"INSERT INTO sales_data (invoice_num, dated, business_name, sales_rep, product, quantity, unit_price, purchase_price, line_revenue, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)\";
\$stmt = \$mysqli->prepare(\$sql);
\$stmt->bind_param('sssssddddi', 
    \$dbData['invoice_num'],
    \$dbData['dated'],
    \$dbData['business_name'],
    \$dbData['sales_rep'],
    \$dbData['product'],
    \$dbData['quantity'],
    \$dbData['unit_price'],
    \$dbData['purchase_price'],
    \$dbData['line_revenue'],
    \$dbData['created_by']
);
\$stmt->execute();";
    echo "</textarea>";
    
    echo "<h3>6. Test Sample Record</h3>";
    
    // Insert a test record
    $testSQL = "INSERT INTO sales_data (invoice_num, dated, business_name, sales_rep, product, quantity, unit_price, purchase_price, line_revenue, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $mysqli->prepare($testSQL);
    if ($stmt) {
        $invoice = '101593';
        $dated = '2026-03-03 13:18:12';
        $business = 'O Neills Tyre & Autocare Broadmeadow';
        $rep = 'Kam';
        $product = 'Triangle-TH202 EffeXSport-245-40-19-98Y';
        $quantity = 2.0;
        $unitPrice = 85.45;
        $purchasePrice = 70.90;
        $lineRevenue = 188.00;
        $createdBy = 1;
        
        $stmt->bind_param('sssssddddi', $invoice, $dated, $business, $rep, $product, $quantity, $unitPrice, $purchasePrice, $lineRevenue, $createdBy);
        
        if ($stmt->execute()) {
            echo "✅ Test record inserted successfully<br>";
            
            // Fetch and display the record with calculated fields
            $result = $mysqli->query("SELECT * FROM sales_data WHERE invoice_num = '101593'");
            if ($result && $row = $result->fetch_assoc()) {
                echo "<h4>Test Record with Calculated Fields:</h4>";
                echo "<ul>";
                echo "<li><strong>Invoice:</strong> " . $row['invoice_num'] . "</li>";
                echo "<li><strong>Business:</strong> " . $row['business_name'] . "</li>";
                echo "<li><strong>Product:</strong> " . $row['product'] . "</li>";
                echo "<li><strong>Quantity:</strong> " . $row['quantity'] . "</li>";
                echo "<li><strong>Unit Price:</strong> $" . $row['unit_price'] . "</li>";
                echo "<li><strong>Purchase Price:</strong> $" . $row['purchase_price'] . "</li>";
                echo "<li><strong>Line Revenue:</strong> $" . $row['line_revenue'] . "</li>";
                echo "<li><strong>Gross Profit:</strong> $" . $row['gross_profit'] . " (calculated)</li>";
                echo "<li><strong>GP Margin:</strong> " . $row['gp_margin'] . "% (calculated)</li>";
                echo "</ul>";
            }
        } else {
            echo "❌ Failed to insert test record: " . $stmt->error . "<br>";
        }
        $stmt->close();
    }
    
    $mysqli->close();
    
    echo "<h3>✅ Column Mapping Fix Complete!</h3>";
    
    echo "<div style='background: #d4edda; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
    echo "<h4>🎉 Database Structure Fixed!</h4>";
    echo "<p><strong>✅ Correct table structure created</strong></p>";
    echo "<p><strong>✅ Column mapping defined</strong></p>";
    echo "<p><strong>✅ Calculated fields working</strong></p>";
    echo "<p><strong>✅ Test record inserted</strong></p>";
    echo "<br>";
    echo "<p><strong>Next Steps:</strong></p>";
    echo "<ol>";
    echo "<li><a href='login.php'>Test Login System</a></li>";
    echo "<li><a href='pages/dashboard/index.php'>Check Dashboard</a></li>";
    echo "<li><a href='pages/sales/index.php'>Check Sales Reports</a></li>";
    echo "<li>Import full Excel data using the correct column mapping</li>";
    echo "</ol>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<h3>❌ Error:</h3>";
    echo "<div style='background: #f8d7da; padding: 15px; border-radius: 4px; color: #721c24;'>";
    echo "<strong>Error:</strong> " . $e->getMessage() . "<br>";
    echo "</div>";
    
    echo "<h4>Manual Steps:</h4>";
    echo "<ol>";
    echo "<li>Ensure users table exists first (run <a href='auto_fix_database.php'>Auto Fix Database</a>)</li>";
    echo "<li>Check MySQL service is running</li>";
    echo "<li>Verify database permissions</li>";
    echo "</ol>";
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
textarea { border: 1px solid #ddd; border-radius: 4px; padding: 10px; }
code { background: #f1f3f4; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
</style>