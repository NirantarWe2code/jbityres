<?php
/**
 * Sales Data Management Class
 * Updated to work with current table structure
 */

require_once __DIR__ . '/Database.php';

class SalesData
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Get all sales records with pagination and filtering
     */
    public function getAllRecords($page = 1, $limit = 25, $filters = [])
    {
        try {
            $offset = ($page - 1) * $limit;

            // Build WHERE clause based on available columns
            $whereConditions = [];
            $params = [];

            if (!empty($filters["search"])) {
                $searchFields = [];
                if (in_array("invoice_num", $this->getTableColumns()))
                    $searchFields[] = "invoice_num LIKE ?";
                if (in_array("business_name", $this->getTableColumns()))
                    $searchFields[] = "business_name LIKE ?";
                if (in_array("product", $this->getTableColumns()))
                    $searchFields[] = "product LIKE ?";
                if (in_array("sales_rep", $this->getTableColumns()))
                    $searchFields[] = "sales_rep LIKE ?";

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
    public function getRecordById($id)
    {
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
    public function createRecord($data)
    {
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
    public function updateRecord($id, $data)
    {
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
    public function deleteRecord($id)
    {
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
    public function getDashboardStats()
    {
        try {
            $stats = [];

            // Total records/sales
            $totalResult = $this->db->fetchOne("SELECT COUNT(*) as count FROM sales_data");
            $stats["total_records"] = $totalResult["count"] ?? 0;
            $stats["total_sales"] = $totalResult["count"] ?? 0; // Alias for frontend compatibility

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

            // Total profit (if gross_profit column exists)
            if (in_array("gross_profit", $this->getTableColumns())) {
                $profitResult = $this->db->fetchOne("SELECT SUM(gross_profit) as total FROM sales_data");
                $stats["total_profit"] = $profitResult["total"] ?? 0;
            } else {
                // Calculate profit if we have revenue and purchase price
                if (in_array("line_revenue", $this->getTableColumns()) && in_array("purchase_price", $this->getTableColumns()) && in_array("quantity", $this->getTableColumns())) {
                    $profitResult = $this->db->fetchOne("SELECT SUM(line_revenue - (quantity * purchase_price)) as total FROM sales_data");
                    $stats["total_profit"] = $profitResult["total"] ?? 0;
                } else {
                    $stats["total_profit"] = 0;
                }
            }

            // Average margin (if gp_margin column exists)
            if (in_array("gp_margin", $this->getTableColumns())) {
                $marginResult = $this->db->fetchOne("SELECT AVG(gp_margin) as avg FROM sales_data WHERE gp_margin IS NOT NULL");
                $stats["avg_margin"] = $marginResult["avg"] ?? 0;
            } else {
                // Calculate average margin manually
                if (isset($stats["total_revenue"]) && $stats["total_revenue"] > 0 && isset($stats["total_profit"])) {
                    $stats["avg_margin"] = ($stats["total_profit"] / $stats["total_revenue"]) * 100;
                } else {
                    $stats["avg_margin"] = 0;
                }
            }

            // Average order value
            if (isset($stats["total_revenue"]) && $stats["total_records"] > 0) {
                $stats["avg_order_value"] = $stats["total_revenue"] / $stats["total_records"];
            }

            // Top products by revenue
            $cols = $this->getTableColumns();
            $revenueCol = in_array("line_revenue", $cols) ? "line_revenue" : (in_array("Total_Amount", $cols) ? "Total_Amount" : (in_array("total_amount", $cols) ? "total_amount" : null));
            $productCol = in_array("product", $cols) ? "product" : (in_array("Product", $cols) ? "Product" : null);
            if ($revenueCol && $productCol) {
                $topProducts = $this->db->fetchAll("SELECT $productCol as product, SUM($revenueCol) as revenue, COUNT(*) as sales_count FROM sales_data WHERE $productCol IS NOT NULL AND $productCol != '' AND $revenueCol IS NOT NULL GROUP BY $productCol ORDER BY revenue DESC LIMIT 10");
                $stats["top_products"] = $topProducts;
            } else {
                $stats["top_products"] = [];
            }

            // Top businesses by revenue
            $businessCol = in_array("business_name", $cols) ? "business_name" : (in_array("Business_Name", $cols) ? "Business_Name" : null);
            if ($revenueCol && $businessCol) {
                $topBusinesses = $this->db->fetchAll("SELECT $businessCol as business_name, SUM($revenueCol) as revenue, COUNT(*) as sales_count FROM sales_data WHERE $businessCol IS NOT NULL AND $businessCol != '' AND $revenueCol IS NOT NULL GROUP BY $businessCol ORDER BY revenue DESC LIMIT 10");
                $stats["top_businesses"] = $topBusinesses;
            } else {
                $stats["top_businesses"] = [];
            }

            // Monthly sales for chart
            if ($revenueCol && in_array("dated", $cols)) {
                $monthlySales = $this->db->fetchAll("SELECT DATE_FORMAT(dated, '%Y-%m') as month, SUM($revenueCol) as revenue, COUNT(*) as sales_count FROM sales_data WHERE dated IS NOT NULL AND $revenueCol IS NOT NULL GROUP BY DATE_FORMAT(dated, '%Y-%m') ORDER BY month DESC LIMIT 12");
                $stats["monthly_sales"] = array_reverse($monthlySales); // Oldest first for chart
            } else {
                $stats["monthly_sales"] = [];
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
     * Get comprehensive Tyre Analytics (based on TyreDashboard.jsx + formulas.html)
     * Formulas: Line Revenue = Qty×Unit_Price, GP = (Unit_Price-Purchase_Price)×Qty, GP Margin = GP/Revenue×100
     * Margin tiers: <0% red, 0-8% amber, 8-12% green, ≥12% teal
     */
    public function getTyreAnalytics($filters = [])
    {
        try {
            $cols = $this->getTableColumns();
            $revenueCol = in_array("line_revenue", $cols) ? "line_revenue" : (in_array("Total_Amount", $cols) ? "Total_Amount" : (in_array("total_amount", $cols) ? "total_amount" : null));
            $productCol = in_array("product", $cols) ? "product" : (in_array("Product", $cols) ? "Product" : null);
            $businessCol = in_array("business_name", $cols) ? "business_name" : (in_array("Business_Name", $cols) ? "Business_Name" : null);
            $repCol = in_array("sales_rep", $cols) ? "sales_rep" : (in_array("Sales_Rep", $cols) ? "Sales_Rep" : null);
            $deliveryCol = in_array("delivery_profile", $cols) ? "delivery_profile" : (in_array("Delivery_Profile", $cols) ? "Delivery_Profile" : null);

            // Dynamic columns for profit calculation (avoids "Unknown column" SQL errors)
            $grossProfitCol = in_array("gross_profit", $cols) ? "gross_profit" : (in_array("Gross_Profit", $cols) ? "Gross_Profit" : null);
            $qtyCol = in_array("quantity", $cols) ? "quantity" : (in_array("Quantity", $cols) ? "Quantity" : null);
            $purchaseCol = in_array("purchase_price", $cols) ? "purchase_price" : (in_array("Purchase_Price", $cols) ? "Purchase_Price" : null);
            $invoiceCol = in_array("invoice_num", $cols) ? "invoice_num" : (in_array("Invoice_Num", $cols) ? "Invoice_Num" : "invoice_num");

            $profitExpr = "0";
            if ($grossProfitCol && $revenueCol && $qtyCol && $purchaseCol) {
                $profitExpr = "COALESCE($grossProfitCol, $revenueCol - ($qtyCol * COALESCE($purchaseCol,0)))";
            } elseif ($grossProfitCol) {
                $profitExpr = "COALESCE($grossProfitCol, 0)";
            } elseif ($revenueCol && $qtyCol && $purchaseCol) {
                $profitExpr = "$revenueCol - ($qtyCol * COALESCE($purchaseCol,0))";
            }
            $unitsExpr = $qtyCol ? "SUM(COALESCE($qtyCol, 0))" : "0";

            $where = [];
            $params = [];
            if (!empty($filters["date_from"]) && in_array("dated", $cols)) {
                $where[] = "dated >= ?";
                $params[] = $filters["date_from"];
            }
            if (!empty($filters["date_to"]) && in_array("dated", $cols)) {
                $where[] = "dated <= ?";
                $params[] = ($filters["date_to"] ?? date("Y-m-d")) . " 23:59:59";
            }
            $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
            $baseWhere = $whereClause ?: "WHERE 1=1"; // For queries that append extra conditions
            $whereParams = $params;

            if (!$revenueCol) {
                return ["success" => false, "message" => "Revenue column not found", "data" => null];
            }

            $analytics = [];

            // Totals (formulas: Line Revenue, GP, GP Margin %, Avg Invoice, Avg Units)
            $customersExpr = $businessCol ? "COUNT(DISTINCT $businessCol)" : "0";
            $totalsSql = "SELECT 
                SUM($revenueCol) as revenue,
                SUM($profitExpr) as profit,
                $unitsExpr as units,
                COUNT(DISTINCT $invoiceCol) as invoices,
                $customersExpr as customers
                FROM sales_data $whereClause";
            $totalsRow = $this->db->fetchOne($totalsSql, $whereParams);
            $revenue = (float) ($totalsRow["revenue"] ?? 0);
            $profit = (float) ($totalsRow["profit"] ?? 0);
            $analytics["totals"] = [
                "revenue" => $revenue,
                "profit" => $profit,
                "units" => (float) ($totalsRow["units"] ?? 0),
                "invoices" => (int) ($totalsRow["invoices"] ?? 0),
                "customers" => (int) ($totalsRow["customers"] ?? 0),
                "margin" => $revenue > 0 ? ($profit / $revenue) * 100 : 0,
                "avg_invoice" => ($totalsRow["invoices"] ?? 0) > 0 ? $revenue / (int) $totalsRow["invoices"] : 0,
                "avg_units" => ($totalsRow["invoices"] ?? 0) > 0 ? ((float) ($totalsRow["units"] ?? 0)) / (int) $totalsRow["invoices"] : 0,
                "revenue_inc_gst" => $revenue * 1.1,
            ];

            // Monthly (formula: MoM %)
            if (in_array("dated", $cols)) {
                $monthlySql = "SELECT DATE_FORMAT(dated, '%Y-%m') as month, 
                    SUM($revenueCol) as revenue, 
                    SUM($profitExpr) as profit,
                    $unitsExpr as units,
                    COUNT(DISTINCT $invoiceCol) as invoices
                    FROM sales_data $whereClause
                    GROUP BY DATE_FORMAT(dated, '%Y-%m')
                    ORDER BY month";
                $analytics["monthly"] = $this->db->fetchAll($monthlySql, $whereParams);
                foreach ($analytics["monthly"] as &$m) {
                    $m["revenue"] = (float) $m["revenue"];
                    $m["profit"] = (float) $m["profit"];
                    $m["units"] = (float) $m["units"];
                    $m["margin"] = $m["revenue"] > 0 ? ($m["profit"] / $m["revenue"]) * 100 : 0;
                }
            } else {
                $analytics["monthly"] = [];
            }

            // Brands (extract first part before hyphen - formula from formulas.html)
            if ($productCol) {
                $brandWhere = $whereClause;
                $brandWhere .= ($brandWhere ? " AND " : " WHERE ") . "$productCol IS NOT NULL AND TRIM($productCol) != ''";
                $brandSql = "SELECT TRIM(SUBSTRING_INDEX($productCol, '-', 1)) as brand,
                    SUM($revenueCol) as revenue,
                    SUM($profitExpr) as profit,
                    $unitsExpr as units
                    FROM sales_data $brandWhere
                    GROUP BY TRIM(SUBSTRING_INDEX($productCol, '-', 1))
                    ORDER BY revenue DESC LIMIT 20";
                $analytics["brands"] = $this->db->fetchAll($brandSql, $whereParams);
                foreach ($analytics["brands"] as &$b) {
                    $b["revenue"] = (float) $b["revenue"];
                    $b["profit"] = (float) $b["profit"];
                    $b["units"] = (float) $b["units"];
                    $b["margin"] = $b["revenue"] > 0 ? ($b["profit"] / $b["revenue"]) * 100 : 0;
                    $b["rev_share"] = $revenue > 0 ? ($b["revenue"] / $revenue) * 100 : 0;
                }
            } else {
                $analytics["brands"] = [];
            }

            // Customers (business_name)
            if ($businessCol) {
                $custWhere = ($whereClause ? $whereClause . " AND " : "WHERE ") . "$businessCol IS NOT NULL";
                $custSql = "SELECT $businessCol as customer,
                    SUM($revenueCol) as revenue,
                    SUM($profitExpr) as profit,
                    $unitsExpr as units,
                    COUNT(DISTINCT $invoiceCol) as invoices
                    FROM sales_data $custWhere
                    GROUP BY $businessCol
                    ORDER BY revenue DESC LIMIT 50";
                $analytics["customers"] = $this->db->fetchAll($custSql, $whereParams);
                foreach ($analytics["customers"] as &$c) {
                    $c["revenue"] = (float) $c["revenue"];
                    $c["profit"] = (float) $c["profit"];
                    $c["units"] = (float) $c["units"];
                    $c["margin"] = $c["revenue"] > 0 ? ($c["profit"] / $c["revenue"]) * 100 : 0;
                    $c["rev_share"] = $revenue > 0 ? ($c["revenue"] / $revenue) * 100 : 0;
                }
            } else {
                $analytics["customers"] = [];
            }

            // Sales Reps (formula: Rep Rev Share %, Rep GP Margin)
            if ($repCol) {
                $repWhere = ($whereClause ? $whereClause . " AND " : "WHERE ") . "$repCol IS NOT NULL";
                $repSql = "SELECT $repCol as rep,
                    SUM($revenueCol) as revenue,
                    SUM($profitExpr) as profit,
                    $unitsExpr as units,
                    COUNT(DISTINCT $invoiceCol) as invoices
                    FROM sales_data $repWhere
                    GROUP BY $repCol
                    ORDER BY revenue DESC";
                $analytics["reps"] = $this->db->fetchAll($repSql, $whereParams);
                foreach ($analytics["reps"] as &$r) {
                    $r["revenue"] = (float) $r["revenue"];
                    $r["profit"] = (float) $r["profit"];
                    $r["units"] = (float) $r["units"];
                    $r["margin"] = $r["revenue"] > 0 ? ($r["profit"] / $r["revenue"]) * 100 : 0;
                    $r["rev_share"] = $revenue > 0 ? ($r["revenue"] / $revenue) * 100 : 0;
                }
            } else {
                $analytics["reps"] = [];
            }

            // Areas (from Delivery_Profile: text after ' - ' before ':')
            if ($deliveryCol && in_array("dated", $cols)) {
                $areaWhere = ($whereClause ? $whereClause . " AND " : "WHERE ") . "$deliveryCol IS NOT NULL AND $deliveryCol != ''";
                $areaSql = "SELECT TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX($deliveryCol, ' - ', -1), ':', 1)) as area,
                    SUM($revenueCol) as revenue,
                    SUM($profitExpr) as profit,
                    $unitsExpr as units,
                    COUNT(DISTINCT $invoiceCol) as invoices,
                    COUNT(DISTINCT $businessCol) as customers
                    FROM sales_data $areaWhere
                    GROUP BY TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX($deliveryCol, ' - ', -1), ':', 1))
                    ORDER BY revenue DESC LIMIT 15";
                try {
                    $analytics["areas"] = $this->db->fetchAll($areaSql, $whereParams);
                    foreach ($analytics["areas"] as &$a) {
                        $a["revenue"] = (float) $a["revenue"];
                        $a["profit"] = (float) $a["profit"];
                        $a["units"] = (float) $a["units"];
                        $a["margin"] = $a["revenue"] > 0 ? ($a["profit"] / $a["revenue"]) * 100 : 0;
                        $a["rev_share"] = $revenue > 0 ? ($a["revenue"] / $revenue) * 100 : 0;
                    }
                } catch (Exception $e) {
                    $analytics["areas"] = [];
                }
            } else {
                $analytics["areas"] = [];
            }

            // By day of week and hour (activity analysis - formulas: Invoice deduplication)
            if (in_array("dated", $cols)) {
                $daySql = "SELECT DAYOFWEEK(dated) as day_num,
                    SUM($revenueCol) as revenue,
                    $unitsExpr as units,
                    COUNT(DISTINCT $invoiceCol) as invoices
                    FROM sales_data $whereClause
                    GROUP BY DAYOFWEEK(dated)
                    ORDER BY day_num";
                $analytics["byDayOfWeek"] = $this->db->fetchAll($daySql, $whereParams);
                $dayNames = ['', 'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
                foreach ($analytics["byDayOfWeek"] as &$d) {
                    $d["day"] = $dayNames[(int) $d["day_num"]] ?? '';
                    $d["revenue"] = (float) $d["revenue"];
                    $d["units"] = (float) $d["units"];
                    $d["invoices"] = (int) $d["invoices"];
                }
                $hourSql = "SELECT HOUR(dated) as hour,
                    SUM($revenueCol) as revenue,
                    $unitsExpr as units,
                    COUNT(DISTINCT $invoiceCol) as invoices
                    FROM sales_data $whereClause
                    GROUP BY HOUR(dated)
                    ORDER BY hour";
                $analytics["byHour"] = $this->db->fetchAll($hourSql, $whereParams);
                foreach ($analytics["byHour"] as &$h) {
                    $hr = (int) $h["hour"];
                    $h["label"] = $hr === 0 ? '12am' : ($hr < 12 ? $hr . 'am' : ($hr === 12 ? '12pm' : ($hr - 12) . 'pm'));
                    $h["revenue"] = (float) $h["revenue"];
                    $h["units"] = (float) $h["units"];
                    $h["invoices"] = (int) $h["invoices"];
                }
            } else {
                $analytics["byDayOfWeek"] = [];
                $analytics["byHour"] = [];
            }

            return ["success" => true, "data" => $analytics];

        } catch (Exception $e) {
            error_log("Tyre analytics error: " . $e->getMessage());
            return ["success" => false, "message" => "Failed to fetch analytics", "data" => null];
        }
    }

    /**
     * Get filter options
     */
    public function getFilterOptions()
    {
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
    private function getTableColumns()
    {
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
    private function buildSelectColumns()
    {
        $columns = $this->getTableColumns();
        return implode(", ", $columns);
    }
}
?>