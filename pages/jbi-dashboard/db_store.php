<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/dashboard_functions.php';

// Use centralized database connection
$db = Database::getInstance();

function db(): mysqli
{
    global $db;
    return $db->getConnection();
}

/**
 * Robust SQL datetime parse for multiple Dated formats.
 */
function sales_data_datetime_sql_expr(): string
{
    return "COALESCE(
        STR_TO_DATE(`Dated`, '%d/%m/%Y %H:%i:%s'),
        STR_TO_DATE(`Dated`, '%d/%m/%Y %H:%i'),
        STR_TO_DATE(`Dated`, '%d-%m-%Y %H:%i:%s'),
        STR_TO_DATE(`Dated`, '%d-%m-%Y %H:%i'),
        STR_TO_DATE(`Dated`, '%Y-%m-%d %H:%i:%s'),
        STR_TO_DATE(`Dated`, '%Y-%m-%d %H:%i'),
        STR_TO_DATE(`Dated`, '%Y/%m/%d %H:%i:%s'),
        STR_TO_DATE(`Dated`, '%Y/%m/%d %H:%i'),
        STR_TO_DATE(`Dated`, '%m/%d/%Y %H:%i:%s'),
        STR_TO_DATE(`Dated`, '%m/%d/%Y %H:%i'),
        STR_TO_DATE(`Dated`, '%d/%m/%Y'),
        STR_TO_DATE(`Dated`, '%d-%m-%Y'),
        STR_TO_DATE(`Dated`, '%Y-%m-%d'),
        STR_TO_DATE(`Dated`, '%Y/%m/%d'),
        STR_TO_DATE(`Dated`, '%m/%d/%Y')
    )";
}

/**
 * Fast year predicate: prefer prefix match on normalized Dated values,
 * fallback to robust datetime parse for legacy formats.
 */
function sales_data_year_where_clause(string $dtExpr): string
{
    return "(`Dated` LIKE CONCAT(?, '-%') OR `Dated` LIKE CONCAT(?, '/%') OR YEAR(" . $dtExpr . ") = ?)";
}

/** API/cron sync table — same logical rows as sales_data for dashboard aggregates. */
function final_salesreportdata_exists(): bool
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    $res = db()->query("SHOW TABLES LIKE 'final_salesreportdata'");
    $cached = $res && $res->num_rows > 0;
    if ($res) {
        $res->free();
    }

    return $cached;
}

function normalize_dated_for_storage(string $datedRaw): string
{
    $dparts = dashboard_parse_dated_parts($datedRaw);
    if ($dparts === null) {
        // Keep canonical fallback aligned with MySQL datetime coercion.
        return '0000-00-00 00:00:00';
    }
    [$dd, $mm, $yyyy, $hh, $ii] = $dparts;
    return sprintf('%04d-%02d-%02d %02d:%02d:%02d', $yyyy, $mm, $dd, $hh, $ii, 0);
}

function sales_row_key(
    string $dated,
    string $businessName,
    string $salesRep,
    string $invoiceNum,
    string $product,
    string $deliveryProfile,
    float $quantity,
    float $unitPrice,
    float $purchasePrice
): string {
    $parts = [
        trim($dated),
        trim($businessName),
        trim($salesRep),
        trim($invoiceNum),
        trim($product),
        trim($deliveryProfile),
        number_format(round($quantity, 3), 3, '.', ''),
        number_format(round($unitPrice, 3), 3, '.', ''),
        number_format(round($purchasePrice, 3), 3, '.', ''),
    ];

    return implode('|', $parts);
}

function sales_row_dedupe_key(
    string $dated,
    string $businessName,
    string $salesRep,
    string $invoiceNum,
    string $product,
    string $deliveryProfile,
    float $quantity,
    float $unitPrice,
    float $purchasePrice
): string {
    return sha1(sales_row_key(
        $dated,
        $businessName,
        $salesRep,
        $invoiceNum,
        $product,
        $deliveryProfile,
        $quantity,
        $unitPrice,
        $purchasePrice
    ));
}

function sales_data_has_column(string $column): bool
{
    $result = db()->query("SHOW COLUMNS FROM sales_data LIKE '" . db()->escape_string($column) . "'");
    return $result->fetch_assoc() !== null;
}

function sales_data_has_unique_index(string $indexName): bool
{
    $result = db()->query("SHOW INDEX FROM sales_data WHERE Key_name = '" . db()->escape_string($indexName) . "'");
    $row = $result->fetch_assoc();
    if (!$row) {
        return false;
    }

    return (int) ($row['Non_unique'] ?? 1) === 0;
}

/**
 * One-time migration path: create dedupe_key and unique index so DB rejects duplicate rows.
 */
function ensure_sales_data_dedupe_ready(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    if (sales_data_has_unique_index('uq_sales_data_dedupe_key')) {
        $done = true;
        return;
    }

    $conn = db();
    if (!sales_data_has_column('dedupe_key')) {
        $conn->query("ALTER TABLE sales_data ADD COLUMN dedupe_key CHAR(40) NULL AFTER `Purchase_Price`");
    }

    $result = $conn->query(
        'SELECT id, `Dated`, `Business_Name`, `Sales_Rep`, `Invoice_Num`, `product`, `Delivery_Profile`,
                `Quantity`, `Unit_Price`, `Purchase_Price`, dedupe_key
         FROM sales_data'
    );
    $rows = $result->fetch_all(MYSQLI_ASSOC);

    $stmt = $conn->prepare('UPDATE sales_data SET dedupe_key = ? WHERE id = ?');
    foreach ($rows as $r) {
        $dated = normalize_dated_for_storage((string) ($r['Dated'] ?? ''));
        $key = sales_row_dedupe_key(
            $dated,
            (string) ($r['Business_Name'] ?? ''),
            (string) ($r['Sales_Rep'] ?? ''),
            (string) ($r['Invoice_Num'] ?? ''),
            (string) ($r['product'] ?? ''),
            (string) ($r['Delivery_Profile'] ?? ''),
            (float) ($r['Quantity'] ?? 0),
            (float) ($r['Unit_Price'] ?? 0),
            (float) ($r['Purchase_Price'] ?? 0)
        );
        if ((string) ($r['dedupe_key'] ?? '') !== $key) {
            $stmt->bind_param('si', $key, $r['id']);
            $stmt->execute();
        }
    }
    $stmt->close();

    // Keep first row and delete all later duplicates for same dedupe key.
    $conn->query(
        "DELETE t1 FROM sales_data t1
         JOIN sales_data t2
           ON t1.dedupe_key = t2.dedupe_key
          AND t1.id > t2.id
         WHERE t1.dedupe_key IS NOT NULL
           AND t1.dedupe_key <> ''"
    );

    if (!sales_data_has_unique_index('uq_sales_data_dedupe_key')) {
        $conn->query("ALTER TABLE sales_data ADD UNIQUE KEY uq_sales_data_dedupe_key (dedupe_key)");
    }

    $done = true;
}

/**
 * Chart/dashboard structure: built in memory from row-level sales_data via aggregate() — never stored as JSON in DB.
 *
 * @return array<int, array<string, mixed>>
 */
function load_all_year_data(): array
{
    $years = list_sales_data_years();
    $out = [];
    foreach ($years as $y) {
        $dbRows = fetch_all_sales_data_for_year($y);
        $parsed = [];
        foreach ($dbRows as $dbRow) {
            $line = sales_data_row_to_parsed_line($dbRow);
            if ($line !== null) {
                $parsed[] = $line;
            }
        }
        if ($parsed === []) {
            continue;
        }
        $agg = aggregate($parsed);
        if ($agg !== null) {
            $out[$y] = $agg;
        }
    }

    return $out;
}

/** Years that have dashboard aggregates (year picker + set_selected_years use this set). */
function list_dashboard_compare_years(): array
{
    $out = array_map('intval', array_keys(load_all_year_data()));
    sort($out, SORT_NUMERIC);

    return array_values($out);
}

/**
 * @return list<int>
 */
function list_sales_data_years(): array
{
    $dtExpr = sales_data_datetime_sql_expr();
    $stmt = db()->query(
        'SELECT DISTINCT YEAR(' . $dtExpr . ') AS y
         FROM sales_data
         WHERE ' . $dtExpr . ' IS NOT NULL
         ORDER BY y'
    );
    $out = [];
    if ($stmt) {
        while ($row = $stmt->fetch_assoc()) {
            $out[] = (int) $row['y'];
        }
        $stmt->free();
    }

    if (final_salesreportdata_exists()) {
        $stmt2 = db()->query(
            'SELECT DISTINCT YEAR(dated) AS y FROM final_salesreportdata WHERE dated IS NOT NULL ORDER BY y'
        );
        if ($stmt2) {
            while ($row = $stmt2->fetch_assoc()) {
                $y = (int) $row['y'];
                if ($y > 0 && !in_array($y, $out, true)) {
                    $out[] = $y;
                }
            }
            $stmt2->free();
        }
    }

    sort($out, SORT_NUMERIC);

    return $out;
}

/** @return list<array<string, mixed>> */
function fetch_all_sales_data_for_year(int $year): array
{
    $conn = db();
    $dtExpr = sales_data_datetime_sql_expr();
    $yearStr = (string) $year;
    $yearWhere = sales_data_year_where_clause($dtExpr);
    $stmt = $conn->prepare(
        'SELECT `Dated`, `Business_Name`, `Sales_Rep`, `Invoice_Num`, `product`, `Delivery_Profile`,
                `Quantity`, `Unit_Price`, `Purchase_Price`
         FROM sales_data
         WHERE ' . $yearWhere . '
         ORDER BY id'
    );
    $stmt->bind_param('sss', $yearStr, $yearStr, $year);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (final_salesreportdata_exists()) {
        $stmt2 = $conn->prepare(
            'SELECT DATE_FORMAT(dated, \'%Y-%m-%d %H:%i:%s\') AS `Dated`,
                    business_name AS `Business_Name`,
                    sales_rep AS `Sales_Rep`,
                    invoice_num AS `Invoice_Num`,
                    product AS `product`,
                    COALESCE(NULLIF(TRIM(delivery_name), \'\'), NULLIF(TRIM(delivery_routes), \'\'), \'\') AS `Delivery_Profile`,
                    quantity AS `Quantity`,
                    unit_price AS `Unit_Price`,
                    purchase_price AS `Purchase_Price`
             FROM final_salesreportdata
             WHERE YEAR(dated) = ?'
        );
        if ($stmt2) {
            $stmt2->bind_param('i', $year);
            $stmt2->execute();
            $res2 = $stmt2->get_result();
            if ($res2) {
                $rows = array_merge($rows, $res2->fetch_all(MYSQLI_ASSOC));
            }
            $stmt2->close();
        }
    }

    return $rows;
}

function replace_year_sales_data(int $year, array $rows): int
{
    $conn = db();
    $conn->autocommit(false);

    try {
        $hasDedupeKeyColumn = sales_data_has_column('dedupe_key');

        if ($hasDedupeKeyColumn) {
            $ins = $conn->prepare(
                'INSERT INTO sales_data (
                    `Dated`, `Business_Name`, `Sales_Rep`, `Invoice_Num`, `product`, `Delivery_Profile`,
                    `Quantity`, `Unit_Price`, `Purchase_Price`, dedupe_key
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $insManual = $conn->prepare(
                'INSERT INTO sales_data (
                    id, `Dated`, `Business_Name`, `Sales_Rep`, `Invoice_Num`, `product`, `Delivery_Profile`,
                    `Quantity`, `Unit_Price`, `Purchase_Price`, dedupe_key
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $existsByKey = $conn->prepare('SELECT 1 FROM sales_data WHERE dedupe_key = ? LIMIT 1');
        } else {
            $ins = $conn->prepare(
                'INSERT INTO sales_data (
                    `Dated`, `Business_Name`, `Sales_Rep`, `Invoice_Num`, `product`, `Delivery_Profile`,
                    `Quantity`, `Unit_Price`, `Purchase_Price`
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $insManual = $conn->prepare(
                'INSERT INTO sales_data (
                    id, `Dated`, `Business_Name`, `Sales_Rep`, `Invoice_Num`, `product`, `Delivery_Profile`,
                    `Quantity`, `Unit_Price`, `Purchase_Price`
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $existsByKey = $conn->prepare(
                'SELECT 1
                 FROM sales_data
                 WHERE TRIM(`Dated`) = ?
                   AND TRIM(`Business_Name`) = ?
                   AND TRIM(`Sales_Rep`) = ?
                   AND TRIM(`Invoice_Num`) = ?
                   AND TRIM(`product`) = ?
                   AND TRIM(`Delivery_Profile`) = ?
                   AND ROUND(`Quantity`, 3) = ?
                   AND ROUND(`Unit_Price`, 3) = ?
                   AND ROUND(`Purchase_Price`, 3) = ?
                 LIMIT 1'
            );
        }

        $insertedCount = 0;
        $seenInUpload = [];
        foreach ($rows as $r) {
            $qty = round((float) ($r['qty'] ?? 0), 3);
            $unitPrice = round((float) ($r['price'] ?? 0), 3);
            $purchasePrice = round((float) ($r['cost'] ?? 0), 3);
            $datedNormalized = normalize_dated_for_storage((string) ($r['dated'] ?? ''));
            $customer = trim((string) ($r['customer'] ?? ''));
            $rep = trim((string) ($r['rep'] ?? ''));
            $invoice = trim((string) ($r['invoice'] ?? ''));
            $product = trim((string) ($r['product'] ?? ''));
            $deliveryProfile = trim((string) ($r['deliveryProfile'] ?? ''));
            $rowKey = sales_row_dedupe_key(
                $datedNormalized,
                $customer,
                $rep,
                $invoice,
                $product,
                $deliveryProfile,
                $qty,
                $unitPrice,
                $purchasePrice,
            );
            if (isset($seenInUpload[$rowKey])) {
                continue;
            }

            if ($hasDedupeKeyColumn) {
                $existsByKey->bind_param('s', $rowKey);
                $existsByKey->execute();
                $result = $existsByKey->get_result();
            } else {
                $existsByKey->bind_param('sssssddd', $datedNormalized, $customer, $rep, $invoice, $product, $deliveryProfile, $qty, $unitPrice, $purchasePrice);
                $existsByKey->execute();
                $result = $existsByKey->get_result();
            }
            if ($result->fetch_assoc() !== null) {
                $seenInUpload[$rowKey] = true;
                continue;
            }

            $params = [
                $datedNormalized,
                $customer,
                $rep,
                $invoice,
                $product,
                $deliveryProfile,
                $qty,
                $unitPrice,
                $purchasePrice,
            ];
            if ($hasDedupeKeyColumn) {
                $params[] = $rowKey;
            }

            $types = str_repeat('s', count($params) - 3) . 'ddd'; // strings for most, doubles for the numeric values
            if ($hasDedupeKeyColumn) {
                $types = str_repeat('s', count($params) - 4) . 'ddds'; // last one is string for dedupe_key
            }

            try {
                $stmt = $ins;
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $insertedCount += $conn->affected_rows;
                $seenInUpload[$rowKey] = true;
            } catch (Throwable $e) {
                if (!is_auto_increment_read_error($e)) {
                    // Duplicate key conflict should be silently ignored.
                    if (strpos($e->getMessage(), '1062') !== false) {
                        $seenInUpload[$rowKey] = true;
                        continue;
                    }
                    throw $e;
                }
                $manualId = next_sales_data_id($conn);
                array_unshift($params, $manualId);
                $types = 'i' . $types; // Add integer type for ID
                $insManual->bind_param($types, ...$params);
                $insManual->execute();
                $insertedCount += $conn->affected_rows;
                $seenInUpload[$rowKey] = true;
            }
        }

        $conn->commit();
        $conn->autocommit(true);

        if (isset($ins)) $ins->close();
        if (isset($insManual)) $insManual->close();
        if (isset($existsByKey)) $existsByKey->close();

        return $insertedCount;

    } catch (Throwable $e) {
        $conn->rollback();
        $conn->autocommit(true);
        throw $e;
    }
}

function is_auto_increment_read_error(Throwable $e): bool
{
    return strpos($e->getMessage(), '1467') !== false;
}

function next_sales_data_id(mysqli $conn): int
{
    $result = $conn->query('SELECT COALESCE(MAX(id), 0) + 1 AS next_id FROM sales_data');
    $row = $result->fetch_assoc();
    return (int) ($row['next_id'] ?? 1);
}

function sales_data_has_imported_at(): bool
{
    static $has = null;
    if ($has !== null) {
        return $has;
    }
    $result = db()->query("SHOW COLUMNS FROM sales_data LIKE 'imported_at'");
    $has = $result->fetch_assoc() !== null;
    return $has;
}

function fetch_sales_rows(int $year, int $limit, int $offset): array
{
    $limit = max(1, min(500, $limit));
    $offset = max(0, $offset);
    $dtExpr = sales_data_datetime_sql_expr();
    $yearStr = (string) $year;
    $yearWhere = sales_data_year_where_clause($dtExpr);

    $countSt = db()->prepare(
        'SELECT COUNT(*) AS c FROM sales_data
         WHERE ' . $yearWhere
    );
    $countSt->bind_param('sii', $yearStr, $yearStr, $year);
    $countSt->execute();
    $countResult = $countSt->get_result();
    $countRow = $countResult->fetch_assoc();
    $total = (int) ($countRow['c'] ?? 0);
    $countSt->close();

    $importedAtSelect = sales_data_has_imported_at() ? 'imported_at' : 'NULL AS imported_at';
    $st = db()->prepare(
        'SELECT
            id, `Dated`, `Business_Name`, `Sales_Rep`, `Invoice_Num`, `product`, `Delivery_Profile`,
            `Quantity`, `Unit_Price`, `Purchase_Price`, ' . $importedAtSelect . '
         FROM sales_data
         WHERE ' . $yearWhere . '
         ORDER BY id DESC
         LIMIT ? OFFSET ?'
    );
    $st->bind_param('siii', $yearStr, $yearStr, $year, $limit, $offset);
    $st->execute();
    $result = $st->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $st->close();

    return ['total' => $total, 'rows' => $rows];
}

/** Remove sales_data rows for calendar year. */
function delete_year_aggregate(int $year): void
{
    $dtExpr = sales_data_datetime_sql_expr();
    $stRows = db()->prepare(
        'DELETE FROM sales_data WHERE YEAR(' . $dtExpr . ') = ?'
    );
    $stRows->bind_param('i', $year);
    $stRows->execute();
    $stRows->close();
}
