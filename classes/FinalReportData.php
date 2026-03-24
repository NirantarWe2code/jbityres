<?php
/**
 * Final Report Data - uses final_salesreportdata table
 * Columns: business_name, delivery_name, sales_rep, product, total_amount, quantity, purchase_price, dated, etc.
 */

require_once __DIR__ . '/Database.php';

class FinalReportData
{
    private $db;
    private $table = 'final_salesreportdata';

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Get dashboard statistics from final_salesreportdata
     */
    public function getDashboardStats($filters = [])
    {
        try {
            $exists = $this->db->fetchOne("SHOW TABLES LIKE '{$this->table}'");
            if (empty($exists)) {
                return [
                    "success" => true,
                    "data" => [
                        "total_records" => 0, "total_sales" => 0, "total_revenue" => 0,
                        "total_quantity" => 0, "total_profit" => 0, "avg_margin" => 0,
                        "avg_order_value" => 0, "top_products" => [], "top_businesses" => [], "monthly_sales" => []
                    ]
                ];
            }
            $where = [];
            $params = [];
            if (!empty($filters['date_from'])) {
                $where[] = "dated >= ?";
                $params[] = $filters['date_from'] . ' 00:00:00';
            }
            if (!empty($filters['date_to'])) {
                $where[] = "dated <= ?";
                $params[] = $filters['date_to'] . ' 23:59:59';
            }
            $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : 'WHERE 1=1';

            $stats = [];

            $totalResult = $this->db->fetchOne("SELECT COUNT(*) as count FROM {$this->table} $whereClause", $params);
            $stats["total_records"] = $totalResult["count"] ?? 0;
            $stats["total_sales"] = $stats["total_records"];

            $revenueResult = $this->db->fetchOne("SELECT SUM(total_amount) as total FROM {$this->table} $whereClause", $params);
            $stats["total_revenue"] = $revenueResult["total"] ?? 0;

            $quantityResult = $this->db->fetchOne("SELECT SUM(quantity) as total FROM {$this->table} $whereClause", $params);
            $stats["total_quantity"] = $quantityResult["total"] ?? 0;

            $profitResult = $this->db->fetchOne("SELECT SUM(total_amount - (quantity * COALESCE(purchase_price, 0))) as total FROM {$this->table} $whereClause", $params);
            $stats["total_profit"] = $profitResult["total"] ?? 0;

            $stats["avg_margin"] = 0;
            if ($stats["total_revenue"] > 0 && isset($stats["total_profit"])) {
                $stats["avg_margin"] = ($stats["total_profit"] / $stats["total_revenue"]) * 100;
            }

            $stats["avg_order_value"] = ($stats["total_records"] > 0 && $stats["total_revenue"] > 0) ? $stats["total_revenue"] / $stats["total_records"] : 0;

            $topProducts = $this->db->fetchAll(
                "SELECT product, SUM(total_amount) as revenue, COUNT(*) as sales_count FROM {$this->table} {$whereClause} AND product IS NOT NULL AND product != '' AND total_amount IS NOT NULL GROUP BY product ORDER BY revenue DESC LIMIT 10",
                $params
            );
            $stats["top_products"] = $topProducts;

            $topBusinesses = $this->db->fetchAll(
                "SELECT business_name, SUM(total_amount) as revenue, COUNT(*) as sales_count FROM {$this->table} {$whereClause} AND business_name IS NOT NULL AND business_name != '' AND total_amount IS NOT NULL GROUP BY business_name ORDER BY revenue DESC LIMIT 10",
                $params
            );
            $stats["top_businesses"] = $topBusinesses;

            $monthlySales = $this->db->fetchAll(
                "SELECT DATE_FORMAT(dated, '%Y-%m') as month, SUM(total_amount) as revenue, COUNT(*) as sales_count FROM {$this->table} {$whereClause} AND dated IS NOT NULL AND total_amount IS NOT NULL GROUP BY DATE_FORMAT(dated, '%Y-%m') ORDER BY month DESC LIMIT 12",
                $params
            );
            $stats["monthly_sales"] = array_reverse($monthlySales);

            return [
                "success" => true,
                "data" => $stats
            ];
        } catch (Exception $e) {
            error_log("FinalReportData getDashboardStats error: " . $e->getMessage());
            return [
                "success" => false,
                "message" => "Failed to fetch dashboard statistics"
            ];
        }
    }

    /**
     * Get comprehensive Tyre Analytics (based on SalesData::getTyreAnalytics)
     * Uses final_salesreportdata: total_amount, quantity, purchase_price, product, business_name, sales_rep, delivery_routes
     */
    public function getTyreAnalytics($filters = [])
    {
        try {
            $exists = $this->db->fetchOne("SHOW TABLES LIKE '{$this->table}'");
            if (empty($exists)) {
                return ["success" => true, "data" => $this->emptyTyreAnalytics()];
            }

            $where = [];
            $params = [];
            if (!empty($filters['date_from'])) {
                $where[] = "dated >= ?";
                $params[] = $filters['date_from'] . ' 00:00:00';
            }
            if (!empty($filters['date_to'])) {
                $where[] = "dated <= ?";
                $params[] = ($filters['date_to'] ?? date('Y-m-d')) . ' 23:59:59';
            }
            $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : 'WHERE 1=1';
            $whereParams = $params;

            $profitExpr = "total_amount - (quantity * COALESCE(purchase_price, 0))";
            $unitsExpr = "SUM(COALESCE(quantity, 0))";

            $analytics = [];

            // Totals
            $totalsSql = "SELECT
                SUM(total_amount) as revenue,
                SUM($profitExpr) as profit,
                $unitsExpr as units,
                COUNT(DISTINCT invoice_num) as invoices,
                COUNT(DISTINCT business_name) as customers
                FROM {$this->table} $whereClause";
            $totalsRow = $this->db->fetchOne($totalsSql, $whereParams);
            $revenue = (float)($totalsRow['revenue'] ?? 0);
            $profit = (float)($totalsRow['profit'] ?? 0);
            $analytics['totals'] = [
                'revenue' => $revenue,
                'profit' => $profit,
                'units' => (float)($totalsRow['units'] ?? 0),
                'invoices' => (int)($totalsRow['invoices'] ?? 0),
                'customers' => (int)($totalsRow['customers'] ?? 0),
                'margin' => $revenue > 0 ? ($profit / $revenue) * 100 : 0,
                'avg_invoice' => ($totalsRow['invoices'] ?? 0) > 0 ? $revenue / (int)$totalsRow['invoices'] : 0,
                'avg_units' => ($totalsRow['invoices'] ?? 0) > 0 ? ((float)($totalsRow['units'] ?? 0)) / (int)$totalsRow['invoices'] : 0,
                'revenue_inc_gst' => $revenue * 1.1,
            ];

            // Monthly
            $monthlySql = "SELECT DATE_FORMAT(dated, '%Y-%m') as month,
                SUM(total_amount) as revenue,
                SUM($profitExpr) as profit,
                $unitsExpr as units,
                COUNT(DISTINCT invoice_num) as invoices
                FROM {$this->table} $whereClause
                GROUP BY DATE_FORMAT(dated, '%Y-%m')
                ORDER BY month";
            $analytics['monthly'] = $this->db->fetchAll($monthlySql, $whereParams);
            foreach ($analytics['monthly'] as &$m) {
                $m['revenue'] = (float)$m['revenue'];
                $m['profit'] = (float)$m['profit'];
                $m['units'] = (float)$m['units'];
                $m['margin'] = $m['revenue'] > 0 ? ($m['profit'] / $m['revenue']) * 100 : 0;
            }

            // Brands (from product: first part before hyphen)
            $brandWhere = $whereClause . " AND product IS NOT NULL AND TRIM(product) != ''";
            $brandSql = "SELECT TRIM(SUBSTRING_INDEX(product, '-', 1)) as brand,
                SUM(total_amount) as revenue,
                SUM($profitExpr) as profit,
                $unitsExpr as units
                FROM {$this->table} $brandWhere
                GROUP BY TRIM(SUBSTRING_INDEX(product, '-', 1))
                ORDER BY revenue DESC LIMIT 20";
            $analytics['brands'] = $this->db->fetchAll($brandSql, $whereParams);
            foreach ($analytics['brands'] as &$b) {
                $b['revenue'] = (float)$b['revenue'];
                $b['profit'] = (float)$b['profit'];
                $b['units'] = (float)$b['units'];
                $b['margin'] = $b['revenue'] > 0 ? ($b['profit'] / $b['revenue']) * 100 : 0;
                $b['rev_share'] = $revenue > 0 ? ($b['revenue'] / $revenue) * 100 : 0;
            }

            // Customers
            $custWhere = $whereClause . " AND business_name IS NOT NULL";
            $custSql = "SELECT business_name as customer,
                SUM(total_amount) as revenue,
                SUM($profitExpr) as profit,
                $unitsExpr as units,
                COUNT(DISTINCT invoice_num) as invoices
                FROM {$this->table} $custWhere
                GROUP BY business_name
                ORDER BY revenue DESC LIMIT 50";
            $analytics['customers'] = $this->db->fetchAll($custSql, $whereParams);
            foreach ($analytics['customers'] as &$c) {
                $c['revenue'] = (float)$c['revenue'];
                $c['profit'] = (float)$c['profit'];
                $c['units'] = (float)$c['units'];
                $c['margin'] = $c['revenue'] > 0 ? ($c['profit'] / $c['revenue']) * 100 : 0;
                $c['rev_share'] = $revenue > 0 ? ($c['revenue'] / $revenue) * 100 : 0;
            }

            // Sales Reps
            $repWhere = $whereClause . " AND sales_rep IS NOT NULL";
            $repSql = "SELECT sales_rep as rep,
                SUM(total_amount) as revenue,
                SUM($profitExpr) as profit,
                $unitsExpr as units,
                COUNT(DISTINCT invoice_num) as invoices
                FROM {$this->table} $repWhere
                GROUP BY sales_rep
                ORDER BY revenue DESC";
            $analytics['reps'] = $this->db->fetchAll($repSql, $whereParams);
            foreach ($analytics['reps'] as &$r) {
                $r['revenue'] = (float)$r['revenue'];
                $r['profit'] = (float)$r['profit'];
                $r['units'] = (float)$r['units'];
                $r['margin'] = $r['revenue'] > 0 ? ($r['profit'] / $r['revenue']) * 100 : 0;
                $r['rev_share'] = $revenue > 0 ? ($r['revenue'] / $revenue) * 100 : 0;
            }

            // Areas (from delivery_routes: part after ' - ' before ':', e.g. "930/1130 - Newcastle:1,2")
            $areaWhere = $whereClause . " AND delivery_routes IS NOT NULL AND TRIM(delivery_routes) != ''";
            try {
                $areaSql = "SELECT TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(delivery_routes, ' - ', -1), ':', 1)) as area,
                    SUM(total_amount) as revenue,
                    SUM($profitExpr) as profit,
                    $unitsExpr as units,
                    COUNT(DISTINCT invoice_num) as invoices,
                    COUNT(DISTINCT business_name) as customers
                    FROM {$this->table} $areaWhere
                    GROUP BY TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(delivery_routes, ' - ', -1), ':', 1))
                    ORDER BY revenue DESC LIMIT 15";
                $analytics['areas'] = $this->db->fetchAll($areaSql, $whereParams);
                foreach ($analytics['areas'] as &$a) {
                    $a['revenue'] = (float)$a['revenue'];
                    $a['profit'] = (float)$a['profit'];
                    $a['units'] = (float)$a['units'];
                    $a['margin'] = $a['revenue'] > 0 ? ($a['profit'] / $a['revenue']) * 100 : 0;
                    $a['rev_share'] = $revenue > 0 ? ($a['revenue'] / $revenue) * 100 : 0;
                }
            } catch (Exception $e) {
                $analytics['areas'] = [];
            }

            // By day of week and hour
            $daySql = "SELECT DAYOFWEEK(dated) as day_num,
                SUM(total_amount) as revenue,
                $unitsExpr as units,
                COUNT(DISTINCT invoice_num) as invoices
                FROM {$this->table} $whereClause
                GROUP BY DAYOFWEEK(dated)
                ORDER BY day_num";
            $analytics['byDayOfWeek'] = $this->db->fetchAll($daySql, $whereParams);
            $dayNames = ['', 'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
            foreach ($analytics['byDayOfWeek'] as &$d) {
                $d['day'] = $dayNames[(int)$d['day_num']] ?? '';
                $d['revenue'] = (float)$d['revenue'];
                $d['units'] = (float)$d['units'];
                $d['invoices'] = (int)$d['invoices'];
            }

            $hourSql = "SELECT HOUR(dated) as hour,
                SUM(total_amount) as revenue,
                $unitsExpr as units,
                COUNT(DISTINCT invoice_num) as invoices
                FROM {$this->table} $whereClause
                GROUP BY HOUR(dated)
                ORDER BY hour";
            $analytics['byHour'] = $this->db->fetchAll($hourSql, $whereParams);
            foreach ($analytics['byHour'] as &$h) {
                $hr = (int)$h['hour'];
                $h['label'] = $hr === 0 ? '12am' : ($hr < 12 ? sprintf('%dam', $hr) : ($hr === 12 ? '12pm' : sprintf('%dpm', $hr - 12)));
                $h['revenue'] = (float)$h['revenue'];
                $h['units'] = (float)$h['units'];
                $h['invoices'] = (int)$h['invoices'];
            }

            return ['success' => true, 'data' => $analytics];
        } catch (Exception $e) {
            error_log('FinalReportData getTyreAnalytics error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to fetch analytics', 'data' => null];
        }
    }

    private function emptyTyreAnalytics()
    {
        return [
            'totals' => ['revenue' => 0, 'profit' => 0, 'units' => 0, 'invoices' => 0, 'customers' => 0, 'margin' => 0, 'avg_invoice' => 0, 'avg_units' => 0, 'revenue_inc_gst' => 0],
            'monthly' => [],
            'brands' => [],
            'customers' => [],
            'reps' => [],
            'areas' => [],
            'byDayOfWeek' => [],
            'byHour' => [],
        ];
    }

    /**
     * Get all sales records with pagination and filtering (Sales Reports)
     * Returns records with line_revenue, gross_profit, gp_margin for frontend compatibility
     */
    public function getAllRecords($page = 1, $limit = 25, $filters = [])
    {
        try {
            $exists = $this->db->fetchOne("SHOW TABLES LIKE '{$this->table}'");
            if (empty($exists)) {
                return ['success' => true, 'data' => [], 'pagination' => ['current_page' => 1, 'total_pages' => 0, 'total_records' => 0, 'records_per_page' => $limit]];
            }

            $offset = ($page - 1) * $limit;
            $where = [];
            $params = [];

            if (!empty($filters['search'])) {
                $term = '%' . $filters['search'] . '%';
                $where[] = "(invoice_num LIKE ? OR business_name LIKE ? OR product LIKE ? OR sales_rep LIKE ?)";
                $params = array_merge($params, [$term, $term, $term, $term]);
            }
            if (!empty($filters['date_from'])) {
                $where[] = "dated >= ?";
                $params[] = $filters['date_from'] . ' 00:00:00';
            }
            if (!empty($filters['date_to'])) {
                $where[] = "dated <= ?";
                $params[] = $filters['date_to'] . ' 23:59:59';
            }
            if (!empty($filters['business_name'])) {
                $where[] = "business_name LIKE ?";
                $params[] = '%' . $filters['business_name'] . '%';
            }
            if (!empty($filters['sales_rep'])) {
                $where[] = "sales_rep LIKE ?";
                $params[] = '%' . $filters['sales_rep'] . '%';
            }
            if (!empty($filters['product'])) {
                $where[] = "product LIKE ?";
                $params[] = '%' . $filters['product'] . '%';
            }

            $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : 'WHERE 1=1';

            $countSql = "SELECT COUNT(*) as count FROM {$this->table} $whereClause";
            $totalRecords = (int)($this->db->fetchOne($countSql, $params)['count'] ?? 0);

            $orderBy = 'ORDER BY id DESC';
            $sortMap = ['line_revenue' => 'total_amount', 'gross_profit' => '(total_amount - (quantity * COALESCE(purchase_price, 0)))', 'gp_margin' => '((total_amount - (quantity * COALESCE(purchase_price, 0))) / NULLIF(total_amount, 0) * 100)'];
            $sortCols = ['id', 'invoice_num', 'dated', 'business_name', 'sales_rep', 'product', 'quantity', 'unit_price', 'total_amount', 'line_revenue', 'gross_profit', 'gp_margin'];
            if (!empty($filters['sort_column']) && in_array($filters['sort_column'], $sortCols)) {
                $dir = (!empty($filters['sort_direction']) && strtoupper($filters['sort_direction']) === 'ASC') ? 'ASC' : 'DESC';
                $sortCol = $sortMap[$filters['sort_column']] ?? $filters['sort_column'];
                $orderBy = "ORDER BY $sortCol $dir";
            }

            $select = "id, invoice_num, dated, business_name, sales_rep, product, quantity, unit_price, purchase_price,
                total_amount as line_revenue,
                (total_amount - (quantity * COALESCE(purchase_price, 0))) as gross_profit,
                CASE WHEN total_amount > 0 THEN ((total_amount - (quantity * COALESCE(purchase_price, 0))) / total_amount) * 100 ELSE 0 END as gp_margin";

            $sql = "SELECT $select FROM {$this->table} $whereClause $orderBy LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
            $records = $this->db->fetchAll($sql, $params);

            foreach ($records as &$r) {
                $r['cost_price'] = $r['purchase_price'] ?? 0;
            }

            return [
                'success' => true,
                'data' => $records,
                'pagination' => [
                    'current_page' => $page,
                    'total_pages' => (int)ceil($totalRecords / $limit),
                    'total_records' => $totalRecords,
                    'records_per_page' => $limit
                ]
            ];
        } catch (Exception $e) {
            error_log('FinalReportData getAllRecords error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to fetch records'];
        }
    }

    /**
     * Get single record by ID (for view/edit) - all columns
     */
    public function getRecordById($id)
    {
        try {
            $select = "id, business_name, delivery_name, delivery_routes, sales_rep, account_type, address,
                invoice_num, order_num, dated, product, stock_id, quantity, unit_price, unit_gst, total_amount,
                po_number, purchase_price, reward_inclusive,
                total_amount as line_revenue,
                (total_amount - (quantity * COALESCE(purchase_price, 0))) as gross_profit,
                CASE WHEN total_amount > 0 THEN ((total_amount - (quantity * COALESCE(purchase_price, 0))) / total_amount) * 100 ELSE 0 END as gp_margin";
            $sql = "SELECT $select FROM {$this->table} WHERE id = ?";
            $record = $this->db->fetchOne($sql, [(int)$id]);

            if ($record) {
                return ['success' => true, 'data' => $record];
            }
            return ['success' => false, 'message' => 'Record not found'];
        } catch (Exception $e) {
            error_log('FinalReportData getRecordById error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to fetch record'];
        }
    }

    /**
     * Create new record - all columns
     */
    public function createRecord($data)
    {
        try {
            $quantity = (float)($data['quantity'] ?? 0);
            $unitPrice = (float)($data['unit_price'] ?? 0);
            $totalAmount = (float)($data['total_amount'] ?? ($quantity * $unitPrice));
            if ($totalAmount == 0 && $quantity > 0 && $unitPrice > 0) {
                $totalAmount = round($quantity * $unitPrice, 2);
            }

            $dated = $data['dated'] ?? '';
            if (!empty($dated) && strlen((string)$dated) === 10) {
                $dated .= ' 00:00:00';
            }

            $insert = [
                'business_name' => $data['business_name'] ?? '',
                'delivery_name' => $data['delivery_name'] ?? '',
                'delivery_routes' => $data['delivery_routes'] ?? '',
                'sales_rep' => $data['sales_rep'] ?? '',
                'account_type' => $data['account_type'] ?? '',
                'address' => $data['address'] ?? '',
                'invoice_num' => $data['invoice_num'] ?? '',
                'order_num' => $data['order_num'] ?? '',
                'dated' => $dated,
                'product' => $data['product'] ?? '',
                'stock_id' => $data['stock_id'] ?? '',
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'unit_gst' => (float)($data['unit_gst'] ?? 0),
                'total_amount' => round($totalAmount, 2),
                'po_number' => $data['po_number'] ?? '',
                'purchase_price' => (float)($data['purchase_price'] ?? 0),
                'reward_inclusive' => in_array($data['reward_inclusive'] ?? '', ['Yes', '1']) ? 'Yes' : 'No',
            ];

            $cols = implode(', ', array_keys($insert));
            $placeholders = implode(', ', array_fill(0, count($insert), '?'));
            $sql = "INSERT INTO {$this->table} ($cols) VALUES ($placeholders)";
            $this->db->execute($sql, array_values($insert));
            $newId = $this->db->getConnection()->insert_id;

            return ['success' => true, 'message' => 'Record created successfully', 'record_id' => $newId];
        } catch (Exception $e) {
            error_log('FinalReportData createRecord error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to create record'];
        }
    }

    /**
     * Update record - all columns
     */
    public function updateRecord($id, $data)
    {
        try {
            $quantity = (float)($data['quantity'] ?? 0);
            $unitPrice = (float)($data['unit_price'] ?? 0);
            $totalAmount = (float)($data['total_amount'] ?? ($quantity * $unitPrice));
            if ($totalAmount == 0 && $quantity > 0 && $unitPrice > 0) {
                $totalAmount = round($quantity * $unitPrice, 2);
            }

            $dated = $data['dated'] ?? '';
            if (!empty($dated) && strlen((string)$dated) === 10) {
                $dated .= ' 00:00:00';
            }

            $sql = "UPDATE {$this->table} SET
                business_name = ?, delivery_name = ?, delivery_routes = ?, sales_rep = ?, account_type = ?,
                address = ?, invoice_num = ?, order_num = ?, dated = ?, product = ?, stock_id = ?,
                quantity = ?, unit_price = ?, unit_gst = ?, total_amount = ?, po_number = ?,
                purchase_price = ?, reward_inclusive = ?
                WHERE id = ?";
            $params = [
                $data['business_name'] ?? '',
                $data['delivery_name'] ?? '',
                $data['delivery_routes'] ?? '',
                $data['sales_rep'] ?? '',
                $data['account_type'] ?? '',
                $data['address'] ?? '',
                $data['invoice_num'] ?? '',
                $data['order_num'] ?? '',
                $dated,
                $data['product'] ?? '',
                $data['stock_id'] ?? '',
                $quantity,
                $unitPrice,
                (float)($data['unit_gst'] ?? 0),
                round($totalAmount, 2),
                $data['po_number'] ?? '',
                (float)($data['purchase_price'] ?? 0),
                in_array($data['reward_inclusive'] ?? '', ['Yes', '1']) ? 'Yes' : 'No',
                (int)$id
            ];

            $this->db->execute($sql, $params);
            return ['success' => true, 'message' => 'Record updated successfully'];
        } catch (Exception $e) {
            error_log('FinalReportData updateRecord error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to update record'];
        }
    }

    /**
     * Delete record
     */
    public function deleteRecord($id)
    {
        try {
            $sql = "DELETE FROM {$this->table} WHERE id = ?";
            $this->db->execute($sql, [(int)$id]);
            return ['success' => true, 'message' => 'Record deleted successfully'];
        } catch (Exception $e) {
            error_log('FinalReportData deleteRecord error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to delete record'];
        }
    }

    /**
     * Get filter options from final_salesreportdata
     */
    public function getFilterOptions()
    {
        try {
            $options = [];
            $options["businesses"] = array_column($this->db->fetchAll("SELECT DISTINCT business_name FROM {$this->table} WHERE business_name IS NOT NULL ORDER BY business_name"), "business_name");
            $options["sales_reps"] = array_column($this->db->fetchAll("SELECT DISTINCT sales_rep FROM {$this->table} WHERE sales_rep IS NOT NULL ORDER BY sales_rep"), "sales_rep");
            $options["products"] = array_column($this->db->fetchAll("SELECT DISTINCT product FROM {$this->table} WHERE product IS NOT NULL ORDER BY product LIMIT 50"), "product");
            return ["success" => true, "data" => $options];
        } catch (Exception $e) {
            error_log("FinalReportData getFilterOptions error: " . $e->getMessage());
            return ["success" => false, "message" => "Failed to fetch filter options"];
        }
    }
}
