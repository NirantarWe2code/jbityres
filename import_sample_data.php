<?php
/**
 * Sample Data Import Script
 * Imports data from Excel file to database for testing
 */

require_once __DIR__ . '/config/config.php';

// Check if running from command line or web
$isCommandLine = php_sapi_name() === 'cli';

if (!$isCommandLine) {
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>Sample Data Import - " . APP_NAME . "</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
            .container { max-width: 800px; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            .success { color: #28a745; }
            .error { color: #dc3545; }
            .info { color: #17a2b8; }
            .warning { color: #ffc107; }
            pre { background: #f8f9fa; padding: 15px; border-radius: 4px; overflow-x: auto; }
            .btn { padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; display: inline-block; margin: 10px 0; }
            .progress { width: 100%; background: #f0f0f0; border-radius: 4px; margin: 10px 0; }
            .progress-bar { height: 20px; background: #28a745; border-radius: 4px; transition: width 0.3s; }
        </style>
    </head>
    <body>
        <div class='container'>
            <h1>Sample Data Import - " . APP_NAME . "</h1>";
}

function logMessage($message, $type = 'info')
{
    global $isCommandLine;

    if ($isCommandLine) {
        echo "[" . strtoupper($type) . "] " . $message . "\n";
    } else {
        echo "<p class='{$type}'>[" . strtoupper($type) . "] " . htmlspecialchars($message) . "</p>";
        flush();
    }
}

function parseExcelData($filePath)
{
    logMessage("Reading Excel file: " . basename($filePath));

    if (!file_exists($filePath)) {
        throw new Exception("Excel file not found: {$filePath}");
    }

    $content = file_get_contents($filePath);
    if ($content === false) {
        throw new Exception("Failed to read Excel file");
    }

    // Split into lines
    $lines = explode("\n", $content);
    if (empty($lines)) {
        throw new Exception("Excel file is empty");
    }

    // Get headers from first line
    $headers = explode("\t", trim($lines[0]));
    $headers = array_map(function ($header) {
        return trim($header, '"');
    }, $headers);

    logMessage("Found " . count($headers) . " columns in Excel file");
    logMessage("Processing " . (count($lines) - 1) . " data rows");

    $data = [];
    $skippedRows = 0;

    // Process data rows
    for ($i = 1; $i < count($lines); $i++) {
        $line = trim($lines[$i]);
        if (empty($line)) {
            continue;
        }

        $values = explode("\t", $line);
        if (count($values) < 10) { // Minimum required columns
            $skippedRows++;
            continue;
        }

        // Clean values
        $values = array_map(function ($value) {
            return trim($value, '"');
        }, $values);

        // Map to database columns
        $row = [
            'business_name' => $values[0] ?? '',
            'delivery_profile' => $values[1] ?? '',
            'sales_rep' => $values[2] ?? '',
            'account_type' => $values[3] ?? '',
            'address' => $values[4] ?? '',
            'invoice_num' => $values[5] ?? '',
            'order_num' => $values[6] ?? '',
            'dated' => parseDate($values[7] ?? ''),
            'product' => $values[8] ?? '',
            'stock_id' => $values[9] ?? '',
            'quantity' => (int) ($values[10] ?? 0),
            'unit_price' => (float) ($values[11] ?? 0),
            'unit_gst' => (float) ($values[12] ?? 0),
            'total_amount' => (float) ($values[13] ?? 0),
            'po_number' => $values[14] ?? '',
            'purchase_price' => (float) ($values[15] ?? 0),
            'reward_inclusive' => ($values[16] ?? '') === 'Yes' ? 'Yes' : 'No'
        ];

        // Validate required fields
        if (empty($row['business_name']) || empty($row['invoice_num']) || empty($row['product'])) {
            $skippedRows++;
            continue;
        }

        $data[] = $row;
    }

    if ($skippedRows > 0) {
        logMessage("Skipped {$skippedRows} invalid rows", 'warning');
    }

    return $data;
}

function parseDate($dateString)
{
    if (empty($dateString)) {
        return date('Y-m-d H:i:s');
    }

    // Try different date formats
    $formats = [
        'Y-m-d H:i:s',
        'd/m/Y H:i:s',
        'd/m/Y H:i',
        'Y-m-d',
        'd/m/Y'
    ];

    foreach ($formats as $format) {
        $date = DateTime::createFromFormat($format, $dateString);
        if ($date !== false) {
            return $date->format('Y-m-d H:i:s');
        }
    }

    // Fallback to current date
    return date('Y-m-d H:i:s');
}

function importData($data)
{
    logMessage("Starting data import...");

    $db = new Database();
    $salesData = new SalesData();

    $imported = 0;
    $failed = 0;
    $total = count($data);

    // Get admin user ID for created_by
    $adminUser = $db->fetchRow("SELECT id FROM users WHERE role = 'super_admin' LIMIT 1");
    $createdBy = $adminUser ? $adminUser['id'] : 1;

    foreach ($data as $index => $row) {
        try {
            $row['created_by'] = $createdBy;

            $result = $salesData->create($row);

            if ($result['success']) {
                $imported++;
            } else {
                $failed++;
                logMessage("Failed to import row " . ($index + 1) . ": " . $result['message'], 'warning');
            }

            // Show progress every 100 records
            if (($index + 1) % 100 === 0) {
                $progress = round((($index + 1) / $total) * 100, 1);
                logMessage("Progress: {$progress}% ({$imported} imported, {$failed} failed)");

                if (!$isCommandLine) {
                    echo "<script>
                        if (document.getElementById('progress-bar')) {
                            document.getElementById('progress-bar').style.width = '{$progress}%';
                        }
                    </script>";
                    flush();
                }
            }

        } catch (Exception $e) {
            $failed++;
            logMessage("Error importing row " . ($index + 1) . ": " . $e->getMessage(), 'error');
        }
    }

    return ['imported' => $imported, 'failed' => $failed, 'total' => $total];
}

function showImportStats($stats)
{
    logMessage("Import completed!", 'success');
    logMessage("Total records processed: {$stats['total']}");
    logMessage("Successfully imported: {$stats['imported']}", 'success');

    if ($stats['failed'] > 0) {
        logMessage("Failed imports: {$stats['failed']}", 'warning');
    }

    $successRate = round(($stats['imported'] / $stats['total']) * 100, 1);
    logMessage("Success rate: {$successRate}%", $successRate > 90 ? 'success' : 'warning');
}

// Main execution
if (isset($_GET['action']) && $_GET['action'] === 'import' || $isCommandLine) {

    try {
        // Check if database exists
        $db = new Database();
        $connection = $db->getConnection();

        if (!$connection) {
            throw new Exception("Database connection failed. Please run database setup first.");
        }

        // Check if sales_data table exists
        $result = $db->fetchRow("SHOW TABLES LIKE 'sales_data'");
        if (!$result) {
            throw new Exception("sales_data table not found. Please run database setup first.");
        }

        logMessage("Database connection successful", 'success');

        // Find Excel file
        echo $excelFile = __DIR__ . '/example report/SellReport20260312.xls';
        if (!file_exists($excelFile)) {
            throw new Exception("Sample Excel file not found: {$excelFile}");
        }

        if (!$isCommandLine) {
            echo "<div class='progress'><div id='progress-bar' class='progress-bar' style='width: 0%'></div></div>";
        }

        // Parse Excel data
        $data = parseExcelData($excelFile);

        if (empty($data)) {
            throw new Exception("No valid data found in Excel file");
        }

        logMessage("Parsed " . count($data) . " valid records from Excel file", 'success');

        // Import data
        $stats = importData($data);

        // Show results
        showImportStats($stats);

        if (!$isCommandLine && $stats['imported'] > 0) {
            echo "<br><a href='../pages/dashboard.php' class='btn'>View Dashboard</a>";
            echo "<a href='../pages/sales-data/' class='btn'>View Sales Data</a>";
        }

    } catch (Exception $e) {
        logMessage("Import failed: " . $e->getMessage(), 'error');
    }

} else if (!$isCommandLine) {
    // Show import form
    echo "<p>This script will import sample data from the Excel file into the database.</p>";
    echo "<p><strong>Make sure the database is set up before running this import.</strong></p>";

    $excelFile = __DIR__ . '/../example report/SellReport20260312.xls';
    if (file_exists($excelFile)) {
        $fileSize = round(filesize($excelFile) / 1024, 1);
        echo "<p>✓ Excel file found: " . basename($excelFile) . " ({$fileSize} KB)</p>";
        echo "<p><a href='?action=import' class='btn'>Start Data Import</a></p>";
    } else {
        echo "<p class='error'>✗ Excel file not found: {$excelFile}</p>";
        echo "<p>Please make sure the sample Excel file is in the correct location.</p>";
    }
}

if (!$isCommandLine) {
    echo "</div></body></html>";
}
?>