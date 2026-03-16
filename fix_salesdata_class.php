<?php
/**
 * Fix SalesData Class According to Current Table Structure
 */

require_once __DIR__ . '/config/config.php';

echo "<h1>🔧 Fix SalesData Class</h1>";

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
    
    $mysqli->close();
    
    echo "<h3>Current Table Columns:</h3>";
    echo "<p>" . implode(', ', $currentColumns) . "</p>";
    
    echo "<h3>Updating SalesData Class...</h3>";
    
    // Read current SalesData class
    $salesDataPath = __DIR__ . '/classes/SalesData.php';
    
    if (!file_exists($salesDataPath)) {
        throw new Exception("SalesData.php file not found");
    }
    
    // Create updated SalesData class content
    $newSalesDataContent = '<?php
/**
 * Sales Data Management Class
 * Updated to work with current table structure
 */

class SalesData {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Get all sales records with pagination and filtering
     */
    public function getAllRecords($page = 1, $limit = 25, $filters = []) {
        try {
            $offset = ($page - 1) * $limit;
            
            // Build WHERE clause based on available columns
            $whereConditions = [];
            $params = [];
            
            if (!empty($filters["search"])) {
                $searchFields = [];
                if (in_array("invoice_num", $this->getTableColumns())) $searchFields[] = "invoice_num LIKE ?";
                if (in_array("business_name", $this->getTableColumns())) $searchFields[] = "business_name LIKE ?";
                if (in_array("product", $this->getTableColumns())) $searchFields[] = "product LIKE ?";
                if (in_array("sales_rep", $this->getTableColumns())) $searchFields[] = "sales_rep LIKE ?";
                
                if (!empty($searchFields)) {
                    $whereConditions[] = "(" . implode(" OR ", $searchFields) . ")";
                    $searchTerm = "%" . $filters["search"] . "%";
                    for ($i = 0; $i < count($searchFields); $i++) {
                        $params[] = $searchTerm;
                    }
                }
            }
            
            // Date filter
            if (!empty($filters["date_from"]) && in_array("dated", $this->getTableColumns())) {
                $whereConditions[] = "dated >= ?";
                $params[] = $filters["date_from"];
            }
            
            if (!empty($filters["date_to"]) && in_array("dated", $this->getTableColumns())) {
                $whereConditions[] = "dated <= ?";
                $params[] = $filters["date_to"] . " 23:59:59";
            }
            
            // Business filter
            if (!empty($filters["business_name"]) && in_array("business_name", $this->getTableColumns())) {
                $whereConditions[] = "business_name LIKE ?";
                $params[] = "%" . $filters["business_name"] . "%";
            }
            
            // Sales rep filter
            if (!empty($filters["sales_rep"]) && in_array("sales_rep", $this->getTableColumns())) {
                $whereConditions[] = "sales_rep LIKE ?";
                $params[] = "%" . $filters["sales_rep"] . "%";
            }
            
            $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";
            
            // Count total records
            $countSql = "SELECT COUNT(*) as count FROM sales_data $whereClause";
            $totalRecords = $this->db->fetchOne($countSql, $params)["count"] ?? 0;
            
            // Get actual column names for SELECT
            $selectColumns = $this->buildSelectColumns();
            
            // Sorting
            $orderBy = "ORDER BY id DESC";
            if (!empty($filters["sort_column"]) && in_array($filters["sort_column"], $this->getTableColumns())) {
                $direction = (!empty($filters["sort_direction"]) && strtoupper($filters["sort_direction"]) === "ASC") ? "ASC" : "DESC";
                $orderBy = "ORDER BY " . $filters["sort_column"] . " $direction";
            }
            
            // Main query
            $sql = "SELECT $selectColumns FROM sales_data $whereClause $orderBy LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
            
            $records = $this->db->fetchAll($sql, $params);
            
            return [
                "success" => true,
                "data" => $records,
                "pagination" => [
                    "current_page" => $page,
                    "total_pages" => ceil($totalRecords / $limit),
                    "total_records" => $totalRecords,
                    "records_per_page" => $limit
                ]
            ];
            
        } catch (Exception $e) {
            error_log("Get sales records error: " . $e->getMessage());
            return [
                "success" => false,
                "message" => "Failed to fetch sales records"
            ];
        }
    }
    
    /**
     * Get single record by ID
     */
    public function getRecordById($id) {
        try {
            $selectColumns = $this->buildSelectColumns();
            $sql = "SELECT $selectColumns FROM sales_data WHERE id = ?";
            $record = $this->db->fetchOne($sql, [$id]);
            
            if ($record) {
                return [
                    "success" => true,
                    "data" => $record
                ];
            } else {
                return [
                    "success" => false,
                    "message" => "Record not found"
                ];
            }
            
        } catch (Exception $e) {
            error_log("Get sales record error: " . $e->getMessage());
            return [
                "success" => false,
                "message" => "Failed to fetch record"
            ];
        }
    }
    
    /**
     * Create new record
     */
    public function createRecord($data) {
        try {
            $columns = $this->getTableColumns();
            $insertColumns = [];
            $placeholders = [];
            $values = [];
            
            // Build insert query based on available columns
            foreach ($data as $key => $value) {
                if (in_array($key, $columns) && $key !== "id") {
                    $insertColumns[] = $key;
                    $placeholders[] = "?";
                    $values[] = $value;
                }
            }
            
            if (empty($insertColumns)) {
                return [
                    "success" => false,
                    "message" => "No valid data provided"
                ];
            }
            
            $sql = "INSERT INTO sales_data (" . implode(", ", $insertColumns) . ") VALUES (" . implode(", ", $placeholders) . ")";
            $result = $this->db->execute($sql, $values);
            
            if ($result) {
                return [
                    "success" => true,
                    "message" => "Record created successfully",
                    "id" => $this->db->getConnection()->insert_id
                ];
            } else {
                return [
                    "success" => false,
                    "message" => "Failed to create record"
                ];
            }
            
        } catch (Exception $e) {
            error_log("Create sales record error: " . $e->getMessage());
            return [
                "success" => false,
                "message" => "Failed to create record"
            ];
        }
    }
    
    /**
     * Update record
     */
    public function updateRecord($id, $data) {
        try {
            $columns = $this->getTableColumns();
            $updateColumns = [];
            $values = [];
            
            foreach ($data as $key => $value) {
                if (in_array($key, $columns) && $key !== "id") {
                    $updateColumns[] = "$key = ?";
                    $values[] = $value;
                }
            }
            
            if (empty($updateColumns)) {
                return [
                    "success" => false,
                    "message" => "No valid data provided"
                ];
            }
            
            $values[] = $id; // Add ID for WHERE clause
            
            $sql = "UPDATE sales_data SET " . implode(", ", $updateColumns) . " WHERE id = ?";
            $result = $this->db->execute($sql, $values);
            
            if ($result) {
                return [
                    "success" => true,
                    "message" => "Record updated successfully"
                ];
            } else {
                return [
                    "success" => false,
                    "message" => "Failed to update record"
                ];
            }
            
        } catch (Exception $e) {
            error_log("Update sales record error: " . $e->getMessage());
            return [
                "success" => false,
                "message" => "Failed to update record"
            ];
        }
    }
    
    /**
     * Delete record
     */
    public function deleteRecord($id) {
        try {
            $sql = "DELETE FROM sales_data WHERE id = ?";
            $result = $this->db->execute($sql, [$id]);
            
            if ($result) {
                return [
                    "success" => true,
                    "message" => "Record deleted successfully"
                ];
            } else {
                return [
                    "success" => false,
                    "message" => "Failed to delete record"
                ];
            }
            
        } catch (Exception $e) {
            error_log("Delete sales record error: " . $e->getMessage());
            return [
                "success" => false,
                "message" => "Failed to delete record"
            ];
        }
    }
    
    /**
     * Get dashboard statistics
     */
    public function getDashboardStats() {
        try {
            $stats = [];
            
            // Total records
            $totalResult = $this->db->fetchOne("SELECT COUNT(*) as count FROM sales_data");
            $stats["total_records"] = $totalResult["count"] ?? 0;
            
            // Total revenue (if line_revenue column exists)
            if (in_array("line_revenue", $this->getTableColumns())) {
                $revenueResult = $this->db->fetchOne("SELECT SUM(line_revenue) as total FROM sales_data");
                $stats["total_revenue"] = $revenueResult["total"] ?? 0;
            }
            
            // Total quantity (if quantity column exists)
            if (in_array("quantity", $this->getTableColumns())) {
                $quantityResult = $this->db->fetchOne("SELECT SUM(quantity) as total FROM sales_data");
                $stats["total_quantity"] = $quantityResult["total"] ?? 0;
            }
            
            // Average order value
            if (isset($stats["total_revenue"]) && $stats["total_records"] > 0) {
                $stats["avg_order_value"] = $stats["total_revenue"] / $stats["total_records"];
            }
            
            return [
                "success" => true,
                "data" => $stats
            ];
            
        } catch (Exception $e) {
            error_log("Get dashboard stats error: " . $e->getMessage());
            return [
                "success" => false,
                "message" => "Failed to fetch dashboard statistics"
            ];
        }
    }
    
    /**
     * Get filter options
     */
    public function getFilterOptions() {
        try {
            $options = [];
            
            // Business names
            if (in_array("business_name", $this->getTableColumns())) {
                $businessResult = $this->db->fetchAll("SELECT DISTINCT business_name FROM sales_data WHERE business_name IS NOT NULL ORDER BY business_name");
                $options["businesses"] = array_column($businessResult, "business_name");
            }
            
            // Sales reps
            if (in_array("sales_rep", $this->getTableColumns())) {
                $repResult = $this->db->fetchAll("SELECT DISTINCT sales_rep FROM sales_data WHERE sales_rep IS NOT NULL ORDER BY sales_rep");
                $options["sales_reps"] = array_column($repResult, "sales_rep");
            }
            
            // Products
            if (in_array("product", $this->getTableColumns())) {
                $productResult = $this->db->fetchAll("SELECT DISTINCT product FROM sales_data WHERE product IS NOT NULL ORDER BY product LIMIT 50");
                $options["products"] = array_column($productResult, "product");
            }
            
            return [
                "success" => true,
                "data" => $options
            ];
            
        } catch (Exception $e) {
            error_log("Get filter options error: " . $e->getMessage());
            return [
                "success" => false,
                "message" => "Failed to fetch filter options"
            ];
        }
    }
    
    /**
     * Get table columns (cached)
     */
    private function getTableColumns() {
        static $columns = null;
        
        if ($columns === null) {
            try {
                $result = $this->db->fetchAll("DESCRIBE sales_data");
                $columns = array_column($result, "Field");
            } catch (Exception $e) {
                $columns = ["id"]; // Fallback
            }
        }
        
        return $columns;
    }
    
    /**
     * Build SELECT columns string
     */
    private function buildSelectColumns() {
        $columns = $this->getTableColumns();
        return implode(", ", $columns);
    }
}
?>';
    
    // Backup original file
    if (file_exists($salesDataPath)) {
        copy($salesDataPath, $salesDataPath . '.backup.' . date('Y-m-d-H-i-s'));
        echo "✅ Original SalesData.php backed up<br>";
    }
    
    // Write new file
    file_put_contents($salesDataPath, $newSalesDataContent);
    echo "✅ SalesData.php updated successfully<br>";
    
    echo "<h3>Changes Made:</h3>";
    echo "<ul>";
    echo "<li>✅ Dynamic column detection based on current table structure</li>";
    echo "<li>✅ Flexible getAllRecords method that adapts to available columns</li>";
    echo "<li>✅ Safe CRUD operations with column validation</li>";
    echo "<li>✅ Dashboard stats that work with any table structure</li>";
    echo "<li>✅ Error handling and logging</li>";
    echo "</ul>";
    
    echo "<h3>Test the Updated Class:</h3>";
    
    // Test the updated class
    require_once __DIR__ . '/classes/Database.php';
    require_once $salesDataPath;
    
    $salesData = new SalesData();
    
    // Test getAllRecords
    $result = $salesData->getAllRecords(1, 5);
    
    if ($result['success']) {
        echo "✅ getAllRecords test passed<br>";
        echo "Records found: " . count($result['data']) . "<br>";
        echo "Pagination: " . json_encode($result['pagination']) . "<br>";
    } else {
        echo "❌ getAllRecords test failed: " . $result['message'] . "<br>";
    }
    
    // Test getDashboardStats
    $statsResult = $salesData->getDashboardStats();
    
    if ($statsResult['success']) {
        echo "✅ getDashboardStats test passed<br>";
        echo "Stats: " . json_encode($statsResult['data']) . "<br>";
    } else {
        echo "❌ getDashboardStats test failed: " . $statsResult['message'] . "<br>";
    }
    
    echo "<div style='background: #d4edda; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
    echo "<h4>✅ SalesData Class Updated!</h4>";
    echo "<p>The class now dynamically adapts to your current table structure.</p>";
    echo "<p><strong>Next Step:</strong> <a href='fix_sales_frontend.php'>Fix Frontend Code</a></p>";
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
</style>