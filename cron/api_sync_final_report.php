<?php
/**
 * Cron: API Sync to Final Sales Report Data
 * Fetches from API, inserts into final_salesreportdata (only API response columns)
 * Crontab: 0 1 * * * php /path/to/cron/api_sync_final_report.php
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

    $finalTable = $db->fetchOne("SHOW TABLES LIKE 'final_salesreportdata'");
    if (empty($finalTable)) {
        out("Creating final_salesreportdata table...");
        $sql = file_get_contents(CRON_ROOT . '/database/final_salesreportdata.sql');
        if ($sql) {
            $db->getConnection()->multi_query($sql);
            while ($db->getConnection()->next_result()) { /* flush */
            }
        }
        out("Table created");
    }

    out("=== Final Sales Report Sync ===");
    out("Date range: {$dateFrom} to {$dateTo}");

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
    $response = preg_replace('/^\xEF\xBB\xBF/', '', $response);
    $json = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $first = strpos($response, '{');
        if ($first !== false) {
            $last = strrpos($response, '}');
            if ($last !== false && $last > $first) {
                $json = json_decode(substr($response, $first, $last - $first + 1), true);
            }
        }
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid JSON: " . json_last_error_msg());
        }
    }

    $records = $json['data']['records'] ?? [];
    if (!empty($json['message']) && $httpCode >= 400) {
        throw new Exception("API {$httpCode}: " . $json['message']);
    }
    out("Records: " . count($records));

    $tableName = 'final_salesreportdata';
    $apiToDb = [
        'Business_Name' => 'business_name',
        'Delivery_Name' => 'delivery_name',
        'Delivery_Routes' => 'delivery_routes',
        'Sales_Rep' => 'sales_rep',
        'AccountType' => 'account_type',
        'address' => 'address',
        'Invoice_Num' => 'invoice_num',
        'Order_Num' => 'order_num',
        'Dated' => 'dated',
        'product' => 'product',
        'Product' => 'product',
        'stock_id' => 'stock_id',
        'Quantity' => 'quantity',
        'Unit_Price' => 'unit_price',
        'Unit_GST' => 'unit_gst',
        'Total_Amount' => 'total_amount',
        'PONumber' => 'po_number',
        'Purchase_Price' => 'purchase_price',
        'Reward_inclusive' => 'reward_inclusive',
    ];

    $inserted = 0;
    $skipped = 0;

    foreach ($records as $rec) {
        $insertData = [];
        foreach ($apiToDb as $apiKey => $dbCol) {
            if (!isset($rec[$apiKey]))
                continue;
            $val = $rec[$apiKey];
            if ($apiKey === 'Reward_inclusive') {
                $insertData[$dbCol] = ($val == 1 || $val === 'Yes') ? 'Yes' : 'No';
            } else {
                $insertData[$dbCol] = $val;
            }
        }

        if (isset($insertData['dated']) && strlen((string) $insertData['dated']) === 10) {
            $insertData['dated'] .= ' 00:00:00';
        }
        $qty = (float) ($rec['Quantity'] ?? 1);
        $total = (float) ($rec['Total_Amount'] ?? 0);
        if ($qty > 0 && empty($insertData['unit_price']) && $total > 0) {
            $insertData['unit_price'] = round($total / $qty, 2);
        }
        if (!isset($insertData['purchase_price'])) {
            $insertData['purchase_price'] = 0;
        }

        if (empty($insertData)) {
            $skipped++;
            continue;
        }

        $whereParts = [];
        $checkParams = [];
        foreach ($insertData as $col => $val) {
            if ($val === null) {
                $whereParts[] = "$col IS NULL";
            } else {
                $whereParts[] = "$col = ?";
                $checkParams[] = $val;
            }
        }
        if (!empty($whereParts)) {
            $exists = $db->fetchOne("SELECT 1 FROM {$tableName} WHERE " . implode(' AND ', $whereParts) . " LIMIT 1", $checkParams);
            if (!empty($exists)) {
                $skipped++;
                continue;
            }
        }

        try {
            $cols = implode(', ', array_keys($insertData));
            $placeholders = implode(', ', array_fill(0, count($insertData), '?'));
            $sql = "INSERT INTO {$tableName} ({$cols}) VALUES ({$placeholders})";
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
    error_log("[Cron Final Report Sync] " . $e->getMessage());
    try {
        if (isset($db)) {
            $db->execute(
                "INSERT INTO cron_sync_log (sync_started_at, date_from, date_to, records_fetched, records_inserted, records_skipped, status, message) VALUES (?, ?, ?, 0, 0, 0, 'failed', ?)",
                [$syncStartedAt ?? date('Y-m-d H:i:s'), $dateFrom ?? date('Y-m-d'), $dateTo ?? date('Y-m-d'), $e->getMessage()]
            );
        }
    } catch (Exception $x) {
    }
    exit(1);
}
