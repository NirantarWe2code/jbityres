<?php
/**
 * Check Current Table Structure
 * Get exact current structure to adapt code accordingly
 */

require_once __DIR__ . '/config/config.php';

echo "<h1>🔍 Current Table Structure Check</h1>";

try {
    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($mysqli->connect_error) {
        throw new Exception("Connection failed: " . $mysqli->connect_error);
    }
    
    echo "<h3>1. Current sales_data Table Structure</h3>";
    
    $result = $mysqli->query("DESCRIBE sales_data");
    
    if ($result) {
        $currentColumns = [];
        
        echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 10px 0;'>";
        echo "<tr style='background: #f8f9fa; font-weight: bold;'>";
        echo "<td style='padding: 8px;'>Field</td>";
        echo "<td style='padding: 8px;'>Type</td>";
        echo "<td style='padding: 8px;'>Null</td>";
        echo "<td style='padding: 8px;'>Key</td>";
        echo "<td style='padding: 8px;'>Default</td>";
        echo "</tr>";
        
        while ($row = $result->fetch_assoc()) {
            $currentColumns[] = $row['Field'];
            echo "<tr>";
            echo "<td style='padding: 8px;'><strong>" . $row['Field'] . "</strong></td>";
            echo "<td style='padding: 8px;'>" . $row['Type'] . "</td>";
            echo "<td style='padding: 8px;'>" . $row['Null'] . "</td>";
            echo "<td style='padding: 8px;'>" . $row['Key'] . "</td>";
            echo "<td style='padding: 8px;'>" . ($row['Default'] ?? 'NULL') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        echo "<h3>2. Sample Data Structure</h3>";
        
        $sampleResult = $mysqli->query("SELECT * FROM sales_data LIMIT 1");
        if ($sampleResult && $sampleResult->num_rows > 0) {
            $sampleRow = $sampleResult->fetch_assoc();
            
            echo "<h4>Available Fields in Current Table:</h4>";
            echo "<ul>";
            foreach ($sampleRow as $field => $value) {
                echo "<li><strong>$field:</strong> " . (is_null($value) ? 'NULL' : htmlspecialchars(substr($value, 0, 50))) . "</li>";
            }
            echo "</ul>";
        } else {
            echo "<p>⚠️ No sample data found. Will work with column structure only.</p>";
        }
        
        echo "<h3>3. Code Adaptation Plan</h3>";
        
        // Generate PHP code for SalesData class
        echo "<h4>A. SalesData Class - getAllRecords Method:</h4>";
        
        $selectFields = implode(', ', $currentColumns);
        
        echo "<textarea style='width: 100%; height: 150px; font-family: monospace; font-size: 12px;'>";
        echo "// Updated getAllRecords method for SalesData class
public function getAllRecords(\$page = 1, \$limit = 25, \$filters = []) {
    try {
        \$offset = (\$page - 1) * \$limit;
        
        // Count total records
        \$countSql = \"SELECT COUNT(*) as count FROM sales_data\";
        \$totalRecords = \$this->db->fetchOne(\$countSql)['count'] ?? 0;
        
        // Build main query with actual columns
        \$sql = \"SELECT $selectFields FROM sales_data ORDER BY id DESC LIMIT ? OFFSET ?\";
        \$records = \$this->db->fetchAll(\$sql, [\$limit, \$offset]);
        
        return [
            'success' => true,
            'data' => \$records,
            'pagination' => [
                'current_page' => \$page,
                'total_pages' => ceil(\$totalRecords / \$limit),
                'total_records' => \$totalRecords,
                'records_per_page' => \$limit
            ]
        ];
    } catch (Exception \$e) {
        return ['success' => false, 'message' => 'Failed to fetch records'];
    }
}";
        echo "</textarea>";
        
        // Generate JavaScript code
        echo "<h4>B. JavaScript - displaySalesData Method:</h4>";
        
        echo "<textarea style='width: 100%; height: 200px; font-family: monospace; font-size: 12px;'>";
        echo "// Updated displaySalesData method for sales.js
displaySalesData(records) {
    const tbody = document.querySelector('#salesTable tbody');
    if (!tbody) return;
    
    if (!records || records.length === 0) {
        tbody.innerHTML = '<tr><td colspan=\"8\" class=\"text-center\">No records found</td></tr>';
        return;
    }
    
    let html = '';
    records.forEach(record => {
        html += `
            <tr>
                <td>\${record.id || ''}</td>
                <td>\${record.invoice_num || ''}</td>
                <td>\${record.dated ? new Date(record.dated).toLocaleDateString() : ''}</td>
                <td>\${record.business_name || ''}</td>
                <td>\${record.sales_rep || ''}</td>
                <td>\${record.product || ''}</td>
                <td>\${record.quantity || 0}</td>
                <td>$\${record.unit_price || 0}</td>
                <td>$\${record.line_revenue || 0}</td>
                <td>\${record.gross_profit || 0}</td>
                <td>\${record.gp_margin || 0}%</td>
                <td>
                    <button class=\"btn btn-sm btn-info\" onclick=\"salesController.viewRecord(\${record.id})\">View</button>
                    <button class=\"btn btn-sm btn-warning\" onclick=\"salesController.editRecord(\${record.id})\">Edit</button>
                    <button class=\"btn btn-sm btn-danger\" onclick=\"salesController.deleteRecord(\${record.id})\">Delete</button>
                </td>
            </tr>
        `;
    });
    
    tbody.innerHTML = html;
}";
        echo "</textarea>";
        
        // Generate HTML table structure
        echo "<h4>C. HTML Table Structure for sales/index.php:</h4>";
        
        echo "<textarea style='width: 100%; height: 150px; font-family: monospace; font-size: 12px;'>";
        echo "<!-- Updated table structure for sales page -->
<table id=\"salesTable\" class=\"table table-striped table-hover\">
    <thead class=\"thead-dark\">
        <tr>
            <th>ID</th>
            <th>Invoice #</th>
            <th>Date</th>
            <th>Business</th>
            <th>Sales Rep</th>
            <th>Product</th>
            <th>Quantity</th>
            <th>Unit Price</th>
            <th>Revenue</th>
            <th>Profit</th>
            <th>Margin %</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <!-- Data will be populated by JavaScript -->
    </tbody>
</table>";
        echo "</textarea>";
        
        echo "<h3>4. Files to Update</h3>";
        
        echo "<div style='background: #fff3cd; padding: 15px; border-radius: 4px;'>";
        echo "<h5>📝 Files that need code adaptation:</h5>";
        echo "<ol>";
        echo "<li><strong>classes/SalesData.php</strong> - Update getAllRecords, getRecordById methods</li>";
        echo "<li><strong>assets/js/sales.js</strong> - Update displaySalesData, form handling</li>";
        echo "<li><strong>pages/sales/index.php</strong> - Update table HTML structure</li>";
        echo "<li><strong>ajax/sales.php</strong> - Update CRUD operations</li>";
        echo "</ol>";
        echo "</div>";
        
        echo "<h3>5. Action Buttons</h3>";
        
        echo "<div style='margin: 20px 0;'>";
        echo "<a href='fix_salesdata_class.php' style='padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; margin-right: 10px;'>🔧 Fix SalesData Class</a>";
        echo "<a href='fix_sales_frontend.php' style='padding: 10px 20px; background: #28a745; color: white; text-decoration: none; border-radius: 4px; margin-right: 10px;'>🎨 Fix Frontend Code</a>";
        echo "<a href='test_fixed_system.php' style='padding: 10px 20px; background: #17a2b8; color: white; text-decoration: none; border-radius: 4px;'>🧪 Test System</a>";
        echo "</div>";
        
    } else {
        echo "❌ Could not describe sales_data table: " . $mysqli->error;
    }
    
    $mysqli->close();
    
} catch (Exception $e) {
    echo "<h3>❌ Error:</h3>";
    echo "<div style='background: #f8d7da; padding: 15px; border-radius: 4px; color: #721c24;'>";
    echo "<strong>Error:</strong> " . $e->getMessage() . "<br>";
    echo "</div>";
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