<?php
/**
 * Cron: API Sales Data Sync
 * Runs daily - fetches from API, inserts into sales_data
 * Crontab: 0 1 * * * php /path/to/cron/api_sync_sales.php
 */

set_time_limit(300);
ini_set('memory_limit', '128M');

if (php_sapi_name() !== 'cli') {
    header('Content-Type: text/plain; charset=utf-8');
}

define('CRON_ROOT', dirname(__DIR__));
require_once CRON_ROOT . '/config/config.php';
require_once CRON_ROOT . '/classes/Database.php';

function out($msg)
{
    echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n";
    if (php_sapi_name() !== 'cli')
        flush();
}

$configPath = CRON_ROOT . '/config/cron_config.php';
if (!file_exists($configPath))
    $configPath = CRON_ROOT . '/config/cron_config.sample.php';
if (!file_exists($configPath)) {
    out("ERROR: cron_config.php not found");
    exit(1);
}

$cfg = require $configPath;
$apiBaseUrl = rtrim($cfg['api_base_url'] ?? '', '?');
$accNum = trim($cfg['acc_num'] ?? '');
$bearerToken = trim($cfg['bearer_token'] ?? '');
$daysBack = (int) ($cfg['days_back'] ?? 1);
$tokenParam = $cfg['token_param'] ?? null;

if (empty($apiBaseUrl) || empty($bearerToken)) {
    out("ERROR: Configure api_base_url and bearer_token");
    exit(1);
}
out("Config loaded. API: " . $apiBaseUrl);

$defaultDateFrom = date('2020-07-04 H:i:s');
$defaultDateTo = date('Y-m-d H:i:s', strtotime($defaultDateFrom . ' +1 day'));
;

try {
    $db = Database::getInstance();
    out("Database connected");

    $tables = $db->fetchOne("SHOW TABLES LIKE 'cron_sync_log'");
    if (empty($tables)) {
        $sql = file_get_contents(CRON_ROOT . '/database/cron_sync_log.sql');
        if ($sql) {
            $db->getConnection()->multi_query($sql);
            while ($db->getConnection()->next_result()) { /* flush */
            }
        }
    }

    $dateRow = $db->fetchOne("SELECT date_from, date_to FROM cron_sync_log ORDER BY id DESC LIMIT 1");
    if (!empty($dateRow['date_from']) && !empty($dateRow['date_to'])) {
        $dateFrom = $dateRow['date_to'];
        $dateTo = date('Y-m-d H:i:s', strtotime($dateFrom . ' +1 day'));
        ;
        out("Using date range from cron_sync_log");
    } else {
        $dateFrom = $defaultDateFrom;
        $dateTo = $defaultDateTo;
        out("Using default date range");
    }
    $syncStartedAt = date('Y-m-d H:i:s');

    out("=== API Sales Sync ===");
    out("Date range: {$dateFrom} to {$dateTo}");
    //Date range: 2020-07-07 23:57:28 to 2020-07-08 23:57:28
    $params = [
        'accNum' => $accNum,
        'from_date' => $dateFrom,
        'from_date2' => $dateTo,
    ];
    if ($tokenParam)
        $params[$tokenParam] = $bearerToken;
    $url = $apiBaseUrl . '?' . http_build_query($params);
    out("Calling API...");

    $headers = ['Accept: application/json'];
    if (!$tokenParam)
        $headers[] = 'Authorization: Bearer ' . $bearerToken;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 60,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        throw new Exception("cURL error: " . curl_error($ch));
    }
    out("API HTTP {$httpCode}");

    $response = trim($response);
    $json = json_decode($response, true);
    print_r($json);
    //out("json: " . json_encode($json));

    $records = $json['data']['records'] ?? [];
    if (!empty($json['message']) && $httpCode >= 400) {
        throw new Exception("API {$httpCode}: " . $json['message']);
    }
    out("Records: " . count($records));

    $colsResult = $db->fetchAll("DESCRIBE sales_data");
    $columns = array_column($colsResult, 'Field');
    //out("columns: " . implode('  ', $columns));
    $fieldMap = [
        'Business_Name' => ['business_name', 'Business_Name'],
        'Delivery_Name' => ['delivery_profile', 'Delivery_Profile', 'delivery_name'],
        'Delivery_Routes' => ['delivery_routes', 'Delivery_Routes'],
        'Sales_Rep' => ['sales_rep', 'Sales_Rep'],
        'AccountType' => ['account_type', 'AccountType'],
        'address' => ['address', 'address'],
        'Invoice_Num' => ['invoice_num', 'Invoice_Num'],
        'Order_Num' => ['order_num', 'Order_Num'],
        'Dated' => ['dated', 'Dated'],
        'Product' => ['product', 'Product'],
        'stock_id' => ['stock_id'],
        'Quantity' => ['quantity', 'Quantity'],
        'Unit_Price' => ['unit_price'],
        'Unit_GST' => ['unit_gst'],
        'Total_Amount' => ['total_amount', 'Total_Amount'],
        'PONumber' => ['po_number', 'po_number'],
        'Purchase_Price' => ['purchase_price', 'Purchase_Price'],
        'Reward_inclusive' => ['reward_inclusive'],
        'line_revenue' => ['line_revenue'],
    ];

    $inserted = 0;
    $skipped = 0;
    foreach ($records as $rec) {

        $row = [];
        foreach ($fieldMap as $apiKey => $dbCols) {
            //out("apiKey: " . $apiKey);
            if (!isset($rec[$apiKey]))
                continue;
            $val = $rec[$apiKey];

            //out("val: " . $val);
            foreach ((array) $dbCols as $col) {
                if (in_array($col, $columns)) {
                    $row[$col] = $val;
                    break;
                }
            }
        }

        if (isset($row['dated']) && strlen((string) $row['dated']) === 10) {
            $row['dated'] .= ' 00:00:00';
        }
        $qty = (float) ($rec['Quantity'] ?? 1);
        $total = (float) ($rec['Total_Amount'] ?? 0);
        if ($qty > 0 && in_array('unit_price', $columns) && empty($row['unit_price'])) {
            $row['unit_price'] = round($total / $qty, 2);
        }
        if (in_array('purchase_price', $columns) && !isset($row['purchase_price'])) {
            $row['purchase_price'] = 0;
        }
        if (isset($rec['Reward_inclusive']) && in_array('reward_inclusive', $columns)) {
            $row['reward_inclusive'] = ($rec['Reward_inclusive'] == 1 || $rec['Reward_inclusive'] === 'Yes') ? 'Yes' : 'No';
        }

        $insertData = [];
        foreach ($row as $k => $v) {
            if (in_array($k, $columns) && $k !== 'id') {
                $insertData[$k] = $v;
            }
        }
        if (empty($insertData)) {
            $skipped++;
            continue;
        }

        $dupKeyCols = ['business_name', 'Business_Name', 'delivery_profile', 'Delivery_Profile', 'delivery_routes', 'Delivery_Routes', 'sales_rep', 'Sales_Rep', 'account_type', 'AccountType', 'customer_address', 'address', 'invoice_num', 'Invoice_Num', 'order_num', 'Order_Num', 'dated', 'Dated', 'product', 'Product', 'stock_id', 'quantity', 'Quantity', 'unit_price', 'Unit_Price', 'unit_gst', 'Unit_GST', 'line_revenue', 'total_amount', 'Total_Amount', 'po_number', 'purchase_price', 'Purchase_Price', 'reward_inclusive'];
        $dupData = [];
        foreach ($dupKeyCols as $col) {
            if (isset($insertData[$col]) && in_array($col, $columns)) {
                $dupData[$col] = $insertData[$col];
            }
        }
        if (!empty($dupData)) {
            $whereParts = [];
            $checkParams = [];
            foreach ($dupData as $col => $val) {
                if ($val === null) {
                    // $whereParts[] = "$col IS NULL";
                } else {
                    $whereParts[] = "$col = ?";
                    $checkParams[] = $val;
                }
            }
            $exists = $db->fetchOne("SELECT 1 FROM sales_data WHERE " . implode(' AND ', $whereParts) . " LIMIT 1", $checkParams);
            if (!empty($exists)) {
                $skipped++;
                continue;
            }
        }

        try {
            $cols = implode(', ', array_keys($insertData));
            $placeholders = implode(', ', array_fill(0, count($insertData), '?'));
            $sql = "INSERT INTO sales_data ({$cols}) VALUES ({$placeholders})";
            $db->execute($sql, array_values($insertData));
            $inserted++;
        } catch (Exception $e) {
            $skipped++;
            out("Insert failed: " . $e->getMessage());
        }
    }

    $syncCompletedAt = date('Y-m-d H:i:s');
    $status = ($inserted > 0 || count($records) === 0) ? 'success' : 'partial';
    $message = "Fetched: " . count($records) . ", Inserted: {$inserted}, Skipped: {$skipped}";

    $db->execute(
        "INSERT INTO cron_sync_log (sync_started_at, sync_completed_at, date_from, date_to, records_fetched, records_inserted, records_updated, records_skipped, status, message, api_response_count) VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?)",
        [$syncStartedAt, $syncCompletedAt, $dateFrom, $dateTo, count($records), $inserted, $skipped, $status, $message, $json['data']['count'] ?? count($records)]
    );

    out("SUCCESS: {$message}");
    exit(0);

} catch (Exception $e) {
    out("ERROR: " . $e->getMessage());
    error_log("[Cron API Sync] " . $e->getMessage());
    try {
        if (isset($db)) {
            $db->execute(
                "INSERT INTO cron_sync_log (sync_started_at, date_from, date_to, records_fetched, records_inserted, records_skipped, status, message) VALUES (?, ?, ?, 0, 0, 0, 'failed', ?)",
                [$syncStartedAt, $dateFrom, $dateTo, $e->getMessage()]
            );
        }
    } catch (Exception $x) {
    }
    exit(1);
}
