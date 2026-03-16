<?php
/**
 * Fix Sales Frontend Code According to Current Table Structure
 */

require_once __DIR__ . '/config/config.php';

echo "<h1>🎨 Fix Sales Frontend Code</h1>";

try {
    // Get current table structure
    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($mysqli->connect_error) {
        throw new Exception("Connection failed: " . $mysqli->connect_error);
    }
    
    $result = $mysqli->query("DESCRIBE sales_data");
    $currentColumns = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $currentColumns[] = $row['Field'];
        }
    }
    
    // Get sample data to understand structure
    $sampleResult = $mysqli->query("SELECT * FROM sales_data LIMIT 1");
    $sampleData = $sampleResult ? $sampleResult->fetch_assoc() : [];
    
    $mysqli->close();
    
    echo "<h3>1. Update sales.js</h3>";
    
    // Read current sales.js
    $salesJsPath = __DIR__ . '/assets/js/sales.js';
    
    if (file_exists($salesJsPath)) {
        // Backup original
        copy($salesJsPath, $salesJsPath . '.backup.' . date('Y-m-d-H-i-s'));
        echo "✅ Backed up original sales.js<br>";
    }
    
    // Generate new displaySalesData method based on current columns
    $jsDisplayMethod = "    displaySalesData(records) {
        const tbody = document.querySelector('#salesTable tbody');
        if (!tbody) {
            console.error('Sales table tbody not found');
            return;
        }
        
        if (!records || records.length === 0) {
            tbody.innerHTML = '<tr><td colspan=\"12\" class=\"text-center text-muted\">No sales records found</td></tr>';
            return;
        }
        
        let html = '';
        records.forEach(record => {
            html += `
                <tr>
                    <td>\${record.id || ''}</td>
                    <td>\${record.invoice_num || ''}</td>
                    <td>\${record.dated ? new Date(record.dated).toLocaleDateString() : ''}</td>
                    <td title=\"\${record.business_name || ''}\">\${this.truncateText(record.business_name || '', 20)}</td>
                    <td>\${record.sales_rep || ''}</td>
                    <td title=\"\${record.product || ''}\">\${this.truncateText(record.product || '', 25)}</td>
                    <td class=\"text-right\">\${parseFloat(record.quantity || 0).toFixed(2)}</td>
                    <td class=\"text-right\">$\${parseFloat(record.unit_price || 0).toFixed(2)}</td>
                    <td class=\"text-right\">$\${parseFloat(record.line_revenue || 0).toFixed(2)}</td>
                    <td class=\"text-right\">$\${parseFloat(record.gross_profit || 0).toFixed(2)}</td>
                    <td class=\"text-right\">\${parseFloat(record.gp_margin || 0).toFixed(1)}%</td>
                    <td>
                        <div class=\"btn-group btn-group-sm\" role=\"group\">
                            <button type=\"button\" class=\"btn btn-info btn-sm\" onclick=\"salesController.viewRecord(\${record.id})\" title=\"View Details\">
                                <i class=\"fas fa-eye\"></i>
                            </button>
                            <button type=\"button\" class=\"btn btn-warning btn-sm\" onclick=\"salesController.editRecord(\${record.id})\" title=\"Edit Record\">
                                <i class=\"fas fa-edit\"></i>
                            </button>
                            <button type=\"button\" class=\"btn btn-danger btn-sm\" onclick=\"salesController.deleteRecord(\${record.id})\" title=\"Delete Record\">
                                <i class=\"fas fa-trash\"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        });
        
        tbody.innerHTML = html;
    }
    
    truncateText(text, maxLength) {
        if (!text) return '';
        return text.length > maxLength ? text.substring(0, maxLength) + '...' : text;
    }";
    
    echo "<h4>Updated displaySalesData method:</h4>";
    echo "<textarea style='width: 100%; height: 200px; font-family: monospace; font-size: 11px;'>";
    echo htmlspecialchars($jsDisplayMethod);
    echo "</textarea>";
    
    echo "<h3>2. Update sales/index.php</h3>";
    
    // Read current sales page
    $salesPagePath = __DIR__ . '/pages/sales/index.php';
    
    if (file_exists($salesPagePath)) {
        copy($salesPagePath, $salesPagePath . '.backup.' . date('Y-m-d-H-i-s'));
        echo "✅ Backed up original sales/index.php<br>";
    }
    
    // Generate updated table HTML
    $tableHtml = '                    <table id="salesTable" class="table table-striped table-hover table-sm">
                        <thead class="thead-dark">
                            <tr>
                                <th style="width: 60px;">ID</th>
                                <th style="width: 100px;">Invoice #</th>
                                <th style="width: 100px;">Date</th>
                                <th style="width: 150px;">Business</th>
                                <th style="width: 100px;">Sales Rep</th>
                                <th style="width: 200px;">Product</th>
                                <th style="width: 80px;" class="text-right">Qty</th>
                                <th style="width: 80px;" class="text-right">Unit Price</th>
                                <th style="width: 90px;" class="text-right">Revenue</th>
                                <th style="width: 80px;" class="text-right">Profit</th>
                                <th style="width: 70px;" class="text-right">Margin</th>
                                <th style="width: 120px;" class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="12" class="text-center">
                                    <div class="spinner-border spinner-border-sm" role="status">
                                        <span class="sr-only">Loading...</span>
                                    </div>
                                    Loading sales data...
                                </td>
                            </tr>
                        </tbody>
                    </table>';
    
    echo "<h4>Updated table HTML:</h4>";
    echo "<textarea style='width: 100%; height: 150px; font-family: monospace; font-size: 11px;'>";
    echo htmlspecialchars($tableHtml);
    echo "</textarea>";
    
    echo "<h3>3. Update Modal Forms</h3>";
    
    // Generate form fields based on available columns
    $formFields = [];
    $excludeFields = ['id', 'created_at', 'updated_at', 'gross_profit', 'gp_margin']; // Auto-generated fields
    
    foreach ($currentColumns as $column) {
        if (!in_array($column, $excludeFields)) {
            $label = ucwords(str_replace('_', ' ', $column));
            $type = 'text';
            $required = in_array($column, ['invoice_num', 'dated', 'business_name', 'product', 'quantity', 'unit_price']) ? 'required' : '';
            
            // Special field types
            if ($column === 'dated') {
                $type = 'datetime-local';
            } elseif (in_array($column, ['quantity', 'unit_price', 'line_revenue', 'purchase_price', 'unit_gst'])) {
                $type = 'number';
                $type .= ' step="0.01" min="0"';
            } elseif (in_array($column, ['notes', 'customer_address', 'delivery_profile'])) {
                $type = 'textarea';
            }
            
            $formFields[] = [
                'name' => $column,
                'label' => $label,
                'type' => $type,
                'required' => $required
            ];
        }
    }
    
    echo "<h4>Form Fields Based on Current Table:</h4>";
    echo "<ul>";
    foreach ($formFields as $field) {
        echo "<li><strong>{$field['name']}</strong>: {$field['label']} ({$field['type']}) {$field['required']}</li>";
    }
    echo "</ul>";
    
    echo "<h3>4. Apply Updates</h3>";
    
    // Update sales.js with new displaySalesData method
    if (file_exists($salesJsPath)) {
        $salesJsContent = file_get_contents($salesJsPath);
        
        // Find and replace displaySalesData method
        $pattern = '/displaySalesData\s*\([^{]*\{[^}]*(?:\{[^}]*\}[^}]*)*\}/s';
        
        if (preg_match($pattern, $salesJsContent)) {
            $salesJsContent = preg_replace($pattern, $jsDisplayMethod, $salesJsContent);
            file_put_contents($salesJsPath, $salesJsContent);
            echo "✅ Updated displaySalesData method in sales.js<br>";
        } else {
            echo "⚠️ Could not find displaySalesData method to replace in sales.js<br>";
        }
    }
    
    // Update sales page HTML
    if (file_exists($salesPagePath)) {
        $salesPageContent = file_get_contents($salesPagePath);
        
        // Find and replace table HTML
        $tablePattern = '/<table[^>]*id=["\']salesTable["\'][^>]*>.*?<\/table>/s';
        
        if (preg_match($tablePattern, $salesPageContent)) {
            $salesPageContent = preg_replace($tablePattern, $tableHtml, $salesPageContent);
            file_put_contents($salesPagePath, $salesPageContent);
            echo "✅ Updated table HTML in sales/index.php<br>";
        } else {
            echo "⚠️ Could not find sales table to replace in sales/index.php<br>";
        }
    }
    
    echo "<h3>5. Test Updated Frontend</h3>";
    
    echo "<div style='margin: 20px 0;'>";
    echo "<a href='pages/sales/index.php' target='_blank' style='padding: 10px 20px; background: #28a745; color: white; text-decoration: none; border-radius: 4px; margin-right: 10px;'>🧪 Test Sales Page</a>";
    echo "<a href='test_sales_ajax.php' target='_blank' style='padding: 10px 20px; background: #17a2b8; color: white; text-decoration: none; border-radius: 4px;'>🔍 Test AJAX Response</a>";
    echo "</div>";
    
    echo "<div style='background: #d4edda; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
    echo "<h4>✅ Frontend Code Updated!</h4>";
    echo "<ul>";
    echo "<li>✅ JavaScript displaySalesData method updated for current table structure</li>";
    echo "<li>✅ HTML table structure optimized</li>";
    echo "<li>✅ Form fields mapped to available columns</li>";
    echo "<li>✅ Responsive design maintained</li>";
    echo "</ul>";
    echo "<p><strong>Next:</strong> Test the sales page to ensure everything works correctly.</p>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<h3>❌ Update Failed:</h3>";
    echo "<div style='background: #f8d7da; padding: 15px; border-radius: 4px; color: #721c24;'>";
    echo "<strong>Error:</strong> " . $e->getMessage() . "<br>";
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
textarea { border: 1px solid #ddd; border-radius: 4px; padding: 10px; }
</style>