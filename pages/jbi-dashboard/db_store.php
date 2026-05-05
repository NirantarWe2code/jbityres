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
 * Lowercase field name => actual column name from sales_data (handles Dated vs dated on Linux).
 *
 * @return array<string, string>
 */
function sales_data_column_map(): array
{
    static $map = null;
    if ($map !== null) {
        return $map;
    }
    $map = [];
    $res = db()->query('SHOW COLUMNS FROM sales_data');
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $field = (string) ($row['Field'] ?? '');
            if ($field !== '') {
                $map[strtolower($field)] = $field;
            }
        }
        $res->free();
    }

    return $map;
}

function sales_data_physical_backtick(string $logicalLower): string
{
    $m = sales_data_column_map();
    $field = $m[strtolower($logicalLower)] ?? $logicalLower;

    return '`' . str_replace('`', '``', $field) . '`';
}

/**
 * Priority list (logical lowercase) — first column that exists on sales_data wins.
 * Note: MySQL "Dated" becomes map key "dated"; value keeps real identifier.
 */
function sales_data_date_column_candidates(): array
{
    return [
        'order_num',
        'dated',
        'invoice_date',
        'sale_date',
        'order_date',
        'transaction_date',
        'bill_date',
        'd_date',
        'created_at',
        'updated_at',
    ];
}

/** Actual business-date column on sales_data (quoted). */
function sales_data_date_column_backtick(): string
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    $m = sales_data_column_map();
    // Pick the first preferred candidate that actually contains date-looking values
    // (preserves business-date priority: order_num/dated/... over created_at).
    foreach (sales_data_date_column_candidates() as $lc) {
        if (!isset($m[$lc])) {
            continue;
        }
        $field = $m[$lc];
        $bt = '`' . str_replace('`', '``', $field) . '`';
        $sql = "SELECT COUNT(*) AS c
                FROM sales_data
                WHERE $bt IS NOT NULL
                  AND TRIM($bt) <> ''
                  AND $bt NOT IN ('0000-00-00', '0000-00-00 00:00:00')
                  AND (
                        $bt LIKE '%/%/%'
                        OR $bt LIKE '%-%-%'
                        OR STR_TO_DATE($bt, '%d/%m/%Y %H:%i:%s') IS NOT NULL
                        OR STR_TO_DATE($bt, '%d/%m/%Y %H:%i') IS NOT NULL
                        OR STR_TO_DATE($bt, '%d-%m-%Y %H:%i:%s') IS NOT NULL
                        OR STR_TO_DATE($bt, '%d-%m-%Y %H:%i') IS NOT NULL
                        OR STR_TO_DATE($bt, '%Y-%m-%d %H:%i:%s') IS NOT NULL
                        OR STR_TO_DATE($bt, '%Y-%m-%d %H:%i') IS NOT NULL
                        OR STR_TO_DATE($bt, '%d/%m/%Y') IS NOT NULL
                        OR STR_TO_DATE($bt, '%d-%m-%Y') IS NOT NULL
                        OR STR_TO_DATE($bt, '%Y-%m-%d') IS NOT NULL
                  )";
        $res = db()->query($sql);
        $score = 0;
        if ($res) {
            $row = $res->fetch_assoc();
            $score = (int) ($row['c'] ?? 0);
            $res->free();
        }
        if ($score > 0) {
            $cached = '`' . str_replace('`', '``', $field) . '`';

            return $cached;
        }
    }
    $cached = '`dated`';

    return $cached;
}

/**
 * SELECT list: real columns aliased to legacy dashboard keys (Dated, Business_Name, …).
 */
function sales_data_select_legacy_aliases_sql(): string
{
    $m = sales_data_column_map();
    $dateExpr = sales_data_date_column_backtick();
    $p = static function (array $try) use ($m): string {
        foreach ($try as $name) {
            $k = strtolower($name);
            if (isset($m[$k])) {
                return '`' . str_replace('`', '``', $m[$k]) . '`';
            }
        }

        return '`' . str_replace('`', '``', $try[0]) . '`';
    };

    return sprintf(
        '%s AS `Dated`, %s AS `Business_Name`, %s AS `Sales_Rep`, %s AS `Invoice_Num`, %s AS `product`, %s AS `Delivery_Profile`, %s AS `Quantity`, %s AS `Unit_Price`, %s AS `Purchase_Price`',
        $dateExpr,
        $p(['business_name', 'Business_Name']),
        $p(['sales_rep', 'Sales_Rep']),
        $p(['invoice_num', 'Invoice_Num']),
        $p(['product', 'Product']),
        $p(['delivery_profile', 'Delivery_Profile']),
        $p(['quantity', 'Quantity']),
        $p(['unit_price', 'Unit_Price']),
        $p(['purchase_price', 'Purchase_Price'])
    );
}

/** INSERT column list (physical names) for dashboard upload rows. */
function sales_data_insert_columns_sql(bool $withDedupeKey): string
{
    $cols = [
        'dated', 'business_name', 'sales_rep', 'invoice_num', 'product',
        'delivery_profile', 'quantity', 'unit_price', 'purchase_price',
    ];
    $parts = [];
    foreach ($cols as $lc) {
        $parts[] = sales_data_physical_backtick($lc);
    }
    if ($withDedupeKey) {
        $parts[] = '`dedupe_key`';
    }

    return implode(', ', $parts);
}

/**
 * Robust SQL datetime parse for multiple Dated formats.
 */
function sales_data_datetime_sql_expr(): string
{
    $c = sales_data_date_column_backtick();

    return "COALESCE(
        STR_TO_DATE($c, '%d/%m/%Y %H:%i:%s'),
        STR_TO_DATE($c, '%d/%m/%Y %H:%i'),
        STR_TO_DATE($c, '%d-%m-%Y %H:%i:%s'),
        STR_TO_DATE($c, '%d-%m-%Y %H:%i'),
        STR_TO_DATE($c, '%Y-%m-%d %H:%i:%s'),
        STR_TO_DATE($c, '%Y-%m-%d %H:%i'),
        STR_TO_DATE($c, '%Y/%m/%d %H:%i:%s'),
        STR_TO_DATE($c, '%Y/%m/%d %H:%i'),
        STR_TO_DATE($c, '%m/%d/%Y %H:%i:%s'),
        STR_TO_DATE($c, '%m/%d/%Y %H:%i'),
        STR_TO_DATE($c, '%d %b %Y %H:%i:%s'),
        STR_TO_DATE($c, '%d %b %Y %H:%i'),
        STR_TO_DATE($c, '%d %M %Y %H:%i:%s'),
        STR_TO_DATE($c, '%d %M %Y %H:%i'),
        STR_TO_DATE($c, '%d-%b-%Y %H:%i:%s'),
        STR_TO_DATE($c, '%d-%b-%Y %H:%i'),
        STR_TO_DATE($c, '%d-%M-%Y %H:%i:%s'),
        STR_TO_DATE($c, '%d-%M-%Y %H:%i'),
        STR_TO_DATE($c, '%d/%m/%Y'),
        STR_TO_DATE($c, '%d-%m-%Y'),
        STR_TO_DATE($c, '%Y-%m-%d'),
        STR_TO_DATE($c, '%Y/%m/%d'),
        STR_TO_DATE($c, '%m/%d/%Y'),
        STR_TO_DATE($c, '%d %b %Y'),
        STR_TO_DATE($c, '%d %M %Y'),
        STR_TO_DATE($c, '%d-%b-%Y'),
        STR_TO_DATE($c, '%d-%M-%Y'),
        NULLIF($c, '0000-00-00 00:00:00'),
        NULLIF($c, '0000-00-00'),
        $c
    )";
}

/**
 * Robust datetime parse expression for a specific quoted column.
 */
function sales_data_datetime_sql_expr_for_column(string $quotedColumn): string
{
    $c = $quotedColumn;

    return "COALESCE(
        STR_TO_DATE($c, '%d/%m/%Y %H:%i:%s'),
        STR_TO_DATE($c, '%d/%m/%Y %H:%i'),
        STR_TO_DATE($c, '%d-%m-%Y %H:%i:%s'),
        STR_TO_DATE($c, '%d-%m-%Y %H:%i'),
        STR_TO_DATE($c, '%Y-%m-%d %H:%i:%s'),
        STR_TO_DATE($c, '%Y-%m-%d %H:%i'),
        STR_TO_DATE($c, '%Y/%m/%d %H:%i:%s'),
        STR_TO_DATE($c, '%Y/%m/%d %H:%i'),
        STR_TO_DATE($c, '%m/%d/%Y %H:%i:%s'),
        STR_TO_DATE($c, '%m/%d/%Y %H:%i'),
        STR_TO_DATE($c, '%d %b %Y %H:%i:%s'),
        STR_TO_DATE($c, '%d %b %Y %H:%i'),
        STR_TO_DATE($c, '%d %M %Y %H:%i:%s'),
        STR_TO_DATE($c, '%d %M %Y %H:%i'),
        STR_TO_DATE($c, '%d-%b-%Y %H:%i:%s'),
        STR_TO_DATE($c, '%d-%b-%Y %H:%i'),
        STR_TO_DATE($c, '%d-%M-%Y %H:%i:%s'),
        STR_TO_DATE($c, '%d-%M-%Y %H:%i'),
        STR_TO_DATE($c, '%d/%m/%Y'),
        STR_TO_DATE($c, '%d-%m-%Y'),
        STR_TO_DATE($c, '%Y-%m-%d'),
        STR_TO_DATE($c, '%Y/%m/%d'),
        STR_TO_DATE($c, '%m/%d/%Y'),
        STR_TO_DATE($c, '%d %b %Y'),
        STR_TO_DATE($c, '%d %M %Y'),
        STR_TO_DATE($c, '%d-%b-%Y'),
        STR_TO_DATE($c, '%d-%M-%Y'),
        NULLIF($c, '0000-00-00 00:00:00'),
        NULLIF($c, '0000-00-00'),
        $c
    )";
}

/**
 * Fast year predicate: prefer prefix match on normalized Dated values,
 * fallback to robust datetime parse for legacy formats.
 */
function sales_data_year_where_clause(string $dtExpr): string
{
    $c = sales_data_date_column_backtick();

    // YEAR($c) catches DATE/DATETIME rows where STR_TO_DATE chain is NULL; extra ? bound to same year.
    return "($c LIKE CONCAT(?, '-%') OR $c LIKE CONCAT(?, '/%') OR YEAR(" . $dtExpr . ") = ? OR YEAR($c) = ?)";
}

/** Extra SELECT fields so PHP can build rows when string date parsing fails. */
function sales_data_sql_date_parts_select(): string
{
    $c = sales_data_date_column_backtick();

    return ', YEAR(' . $c . ') AS `__row_year`, MONTH(' . $c . ') AS `__row_month`, DAY(' . $c . ') AS `__row_day`, '
        . 'IFNULL(HOUR(' . $c . '), 0) AS `__row_hour`, IFNULL(MINUTE(' . $c . '), 0) AS `__row_minute`';
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

    $legacySel = sales_data_select_legacy_aliases_sql();
    $result = $conn->query(
        'SELECT id, ' . $legacySel . ', dedupe_key
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
/**
 * When year-based SQL path returns nothing, read all rows and group in PHP (handles odd schemas / failed year SQL).
 *
 * @return array<int, array<string, mixed>>
 */
/** Max rows read in one pass (avoids OOM on huge imports). */
function sales_data_dashboard_row_cap(): int
{
    return max(5000, min(500000, (int) (defined('DASHBOARD_SALES_ROW_CAP') ? constant('DASHBOARD_SALES_ROW_CAP') : 200000)));
}

/**
 * Load all sales_data + optional final_salesreportdata in 1–2 queries, group by calendar year in PHP.
 *
 * @return array<int, array<string, mixed>>
 */
function load_all_year_data_single_pass(): array
{
    $legacySelect = sales_data_select_legacy_aliases_sql();
    $partsSel = sales_data_sql_date_parts_select();
    $cap = sales_data_dashboard_row_cap();
    $sql = 'SELECT ' . $legacySelect . $partsSel . ' FROM sales_data ORDER BY id ASC LIMIT ' . $cap;
    $res = db()->query($sql);
    if (!$res) {
        return [];
    }

    $byYear = [];
    while ($row = $res->fetch_assoc()) {
        $line = sales_data_row_to_parsed_line($row);
        if ($line === null) {
            continue;
        }
        $y = (int) ($line['year'] ?? 0);
        if ($y < 1 || $y > 9999) {
            continue;
        }
        $byYear[$y][] = $line;
    }
    $res->free();

    if (final_salesreportdata_exists()) {
        $sql2 = 'SELECT DATE_FORMAT(dated, \'%Y-%m-%d %H:%i:%s\') AS `Dated`,
                business_name AS `Business_Name`,
                sales_rep AS `Sales_Rep`,
                invoice_num AS `Invoice_Num`,
                product AS `product`,
                COALESCE(NULLIF(TRIM(delivery_name), \'\'), NULLIF(TRIM(delivery_routes), \'\'), \'\') AS `Delivery_Profile`,
                quantity AS `Quantity`,
                unit_price AS `Unit_Price`,
                purchase_price AS `Purchase_Price`
         FROM final_salesreportdata
         ORDER BY id ASC LIMIT ' . $cap;
        $res2 = db()->query($sql2);
        if ($res2) {
            while ($row = $res2->fetch_assoc()) {
                $line = sales_data_row_to_parsed_line($row);
                if ($line === null) {
                    continue;
                }
                $y = (int) ($line['year'] ?? 0);
                if ($y < 1 || $y > 9999) {
                    continue;
                }
                $byYear[$y][] = $line;
            }
            $res2->free();
        }
    }

    $out = [];
    foreach ($byYear as $y => $parsed) {
        if ($parsed === []) {
            continue;
        }
        $agg = aggregate($parsed);
        if ($agg !== null) {
            $out[$y] = $agg;
        }
    }
    ksort($out);

    return $out;
}

function load_all_year_data_brute_star(): array
{
    $cap = sales_data_dashboard_row_cap();
    $res = db()->query('SELECT * FROM sales_data ORDER BY id ASC LIMIT ' . $cap);
    if (!$res) {
        return [];
    }
    $byYear = [];
    while ($row = $res->fetch_assoc()) {
        $line = sales_data_row_to_parsed_line($row);
        if ($line === null) {
            continue;
        }
        $y = (int) ($line['year'] ?? 0);
        if ($y < 1 || $y > 9999) {
            continue;
        }
        $byYear[$y][] = $line;
    }
    $res->free();

    $out = [];
    foreach ($byYear as $y => $parsed) {
        if ($parsed === []) {
            continue;
        }
        $agg = aggregate($parsed);
        if ($agg !== null) {
            $out[$y] = $agg;
        }
    }
    ksort($out);

    return $out;
}

function load_all_year_data(): array
{
    static $memo = null;
    if ($memo !== null) {
        return $memo;
    }

    $out = load_all_year_data_single_pass();

    if ($out === []) {
        $out = load_all_year_data_brute_star();
    }

    $memo = $out;

    return $memo;
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
    static $memoYears = null;
    if ($memoYears !== null) {
        return $memoYears;
    }

    $out = [];
    $m = sales_data_column_map();
    $yearCols = [];
    foreach (['order_num', 'dated', 'invoice_date', 'sale_date', 'order_date', 'transaction_date', 'bill_date', 'd_date', 'created_at', 'updated_at'] as $lc) {
        if (isset($m[$lc])) {
            $yearCols[] = '`' . str_replace('`', '``', $m[$lc]) . '`';
        }
    }
    if ($yearCols === []) {
        $yearCols[] = sales_data_date_column_backtick();
    }
    $yearCols = array_values(array_unique($yearCols));

    foreach ($yearCols as $colBt) {
        $dtExpr = sales_data_datetime_sql_expr_for_column($colBt);
        $stmt = db()->query(
            'SELECT DISTINCT y FROM (
                SELECT DISTINCT YEAR(' . $dtExpr . ') AS y
                 FROM sales_data
                 WHERE ' . $dtExpr . ' IS NOT NULL
                UNION
                SELECT DISTINCT YEAR(' . $colBt . ') AS y
                 FROM sales_data
                 WHERE ' . $colBt . ' IS NOT NULL
                   AND YEAR(' . $colBt . ') > 0
            ) u
             WHERE y IS NOT NULL AND y > 0
             ORDER BY y'
        );
        if ($stmt) {
            while ($row = $stmt->fetch_assoc()) {
                $y = (int) $row['y'];
                if ($y > 0 && $y <= 9999 && !in_array($y, $out, true)) {
                    $out[] = $y;
                }
            }
            $stmt->free();
        }
    }

    if (final_salesreportdata_exists()) {
        $stmt2 = db()->query(
            'SELECT DISTINCT YEAR(dated) AS y FROM final_salesreportdata WHERE dated IS NOT NULL ORDER BY y'
        );
        if ($stmt2) {
            while ($row = $stmt2->fetch_assoc()) {
                $y = (int) $row['y'];
                if ($y > 0 && $y <= 9999 && !in_array($y, $out, true)) {
                    $out[] = $y;
                }
            }
            $stmt2->free();
        }
    }

    sort($out, SORT_NUMERIC);

    if ($out !== []) {
        sort($out, SORT_NUMERIC);
        $memoYears = $out;

        return $memoYears;
    }

    foreach (list_sales_data_years_brute_star() as $y) {
        if (!in_array($y, $out, true)) {
            $out[] = $y;
        }
    }
    foreach (list_sales_data_years_infer_any_column() as $y) {
        if (!in_array($y, $out, true)) {
            $out[] = $y;
        }
    }
    foreach (list_sales_datad_years_infer_any_column() as $y) {
        if (!in_array($y, $out, true)) {
            $out[] = $y;
        }
    }
    sort($out, SORT_NUMERIC);

    $memoYears = $out;

    return $memoYears;
}

/**
 * @return list<int>
 */
function list_sales_data_years_brute_star(): array
{
    $cap = sales_data_dashboard_row_cap();
    $res = db()->query('SELECT * FROM sales_data ORDER BY id ASC LIMIT ' . $cap);
    if (!$res) {
        return [];
    }
    $ys = [];
    while ($row = $res->fetch_assoc()) {
        $line = sales_data_row_to_parsed_line($row);
        if ($line === null) {
            continue;
        }
        $y = (int) ($line['year'] ?? 0);
        if ($y > 0 && $y <= 9999) {
            $ys[$y] = true;
        }
    }
    $res->free();
    $keys = array_map('intval', array_keys($ys));
    sort($keys, SORT_NUMERIC);

    return $keys;
}

/**
 * Infer years by scanning all row values (handles shifted/misaligned imports where date lands in unexpected columns).
 *
 * @return list<int>
 */
function list_sales_data_years_infer_any_column(): array
{
    static $memo = null;
    if ($memo !== null) {
        return $memo;
    }
    $cap = sales_data_dashboard_row_cap();
    $res = db()->query('SELECT * FROM sales_data ORDER BY id ASC LIMIT ' . $cap);
    if (!$res) {
        return $memo = [];
    }
    $ys = [];
    while ($row = $res->fetch_assoc()) {
        $dp = dashboard_infer_date_from_row_values($row);
        if ($dp === null) {
            continue;
        }
        $y = (int) ($dp[2] ?? 0);
        if ($y > 0 && $y <= 9999) {
            $ys[$y] = true;
        }
    }
    $res->free();
    $keys = array_map('intval', array_keys($ys));
    sort($keys, SORT_NUMERIC);

    return $memo = $keys;
}

/**
 * Extract year tokens like 2025/2026 from any row value.
 *
 * @return list<int>
 */
function list_sales_datad_years_infer_any_column(): array
{
    static $memo = null;
    if ($memo !== null) {
        return $memo;
    }
    $hasSalesDatad = db()->query("SHOW TABLES LIKE 'sales_datad'");
    if (!$hasSalesDatad || $hasSalesDatad->num_rows === 0) {
        if ($hasSalesDatad) {
            $hasSalesDatad->free();
        }
        return $memo = [];
    }
    $hasSalesDatad->free();

    $cap = sales_data_dashboard_row_cap();
    $res = db()->query('SELECT * FROM sales_datad ORDER BY id ASC LIMIT ' . $cap);
    if (!$res) {
        return $memo = [];
    }
    $ys = [];
    while ($row = $res->fetch_assoc()) {
        $dp = dashboard_infer_date_from_row_values($row);
        if ($dp === null) {
            continue;
        }
        $y = (int) ($dp[2] ?? 0);
        if ($y > 0 && $y <= 9999) {
            $ys[$y] = true;
        }
    }
    $res->free();
    $keys = array_map('intval', array_keys($ys));
    sort($keys, SORT_NUMERIC);

    return $memo = $keys;
}

/** @return list<array<string, mixed>> */
function fetch_all_sales_data_for_year(int $year): array
{
    $conn = db();
    $dtExpr = sales_data_datetime_sql_expr();
    $yearStr = (string) $year;
    $yearWhere = sales_data_year_where_clause($dtExpr);
    $legacySelect = sales_data_select_legacy_aliases_sql();
    $partsSel = sales_data_sql_date_parts_select();
    $stmt = $conn->prepare(
        'SELECT ' . $legacySelect . $partsSel . '
         FROM sales_data
         WHERE ' . $yearWhere . '
         ORDER BY id'
    );
    $stmt->bind_param('ssii', $yearStr, $yearStr, $year, $year);
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

/**
 * Build one year's dashboard aggregate with multi-stage fallback.
 * Useful when selected year is missing from preloaded/capped aggregates.
 *
 * @return array<string, mixed>|null
 */
function build_year_aggregate_on_demand(int $year): ?array
{
    if ($year <= 0 || $year > 9999) {
        return null;
    }

    $parsed = [];
    $rows = fetch_all_sales_data_for_year($year);
    foreach ($rows as $row) {
        $line = sales_data_row_to_parsed_line($row);
        if ($line !== null && (int) ($line['year'] ?? 0) === $year) {
            $parsed[] = $line;
        }
    }
    $agg = aggregate($parsed);
    if ($agg !== null) {
        return $agg;
    }

    // Fallback 1: stream all sales_data rows and infer year in PHP (avoids SQL year-parse edge cases).
    $legacySelect = sales_data_select_legacy_aliases_sql();
    $partsSel = sales_data_sql_date_parts_select();
    $res = db()->query('SELECT ' . $legacySelect . $partsSel . ' FROM sales_data ORDER BY id ASC');
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $line = sales_data_row_to_parsed_line($row);
            if ($line !== null && (int) ($line['year'] ?? 0) === $year) {
                $parsed[] = $line;
            }
        }
        $res->free();
    }

    // Fallback 2: scan final_salesreportdata as well.
    if (final_salesreportdata_exists()) {
        $res2 = db()->query(
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
             ORDER BY id ASC'
        );
        if ($res2) {
            while ($row = $res2->fetch_assoc()) {
                $line = sales_data_row_to_parsed_line($row);
                if ($line !== null && (int) ($line['year'] ?? 0) === $year) {
                    $parsed[] = $line;
                }
            }
            $res2->free();
        }
    }

    return aggregate($parsed);
}

function replace_year_sales_data(int $year, array $rows): int
{
    $conn = db();
    $conn->autocommit(false);

    try {
        $hasDedupeKeyColumn = sales_data_has_column('dedupe_key');
        $insertCols = sales_data_insert_columns_sql($hasDedupeKeyColumn);
        $insertColsManual = '`id`, ' . $insertCols;

        if ($hasDedupeKeyColumn) {
            $ins = $conn->prepare(
                'INSERT INTO sales_data (' . $insertCols . ') VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $insManual = $conn->prepare(
                'INSERT INTO sales_data (' . $insertColsManual . ') VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $existsByKey = $conn->prepare('SELECT 1 FROM sales_data WHERE dedupe_key = ? LIMIT 1');
        } else {
            $ins = $conn->prepare(
                'INSERT INTO sales_data (' . $insertCols . ') VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $insManual = $conn->prepare(
                'INSERT INTO sales_data (' . $insertColsManual . ') VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $dc = sales_data_physical_backtick('dated');
            $bn = sales_data_physical_backtick('business_name');
            $sr = sales_data_physical_backtick('sales_rep');
            $inv = sales_data_physical_backtick('invoice_num');
            $pr = sales_data_physical_backtick('product');
            $dp = sales_data_physical_backtick('delivery_profile');
            $qtyc = sales_data_physical_backtick('quantity');
            $upc = sales_data_physical_backtick('unit_price');
            $ppc = sales_data_physical_backtick('purchase_price');
            $existsByKey = $conn->prepare(
                'SELECT 1
                 FROM sales_data
                 WHERE TRIM(' . $dc . ') = ?
                   AND TRIM(' . $bn . ') = ?
                   AND TRIM(' . $sr . ') = ?
                   AND TRIM(' . $inv . ') = ?
                   AND TRIM(' . $pr . ') = ?
                   AND TRIM(' . $dp . ') = ?
                   AND ROUND(' . $qtyc . ', 3) = ?
                   AND ROUND(' . $upc . ', 3) = ?
                   AND ROUND(' . $ppc . ', 3) = ?
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
    $countSt->bind_param('ssii', $yearStr, $yearStr, $year, $year);
    $countSt->execute();
    $countResult = $countSt->get_result();
    $countRow = $countResult->fetch_assoc();
    $nSales = (int) ($countRow['c'] ?? 0);
    $countSt->close();

    $importedAtSelect = sales_data_has_imported_at() ? 'imported_at' : 'NULL AS imported_at';
    $legacyRowSel = sales_data_select_legacy_aliases_sql();
    $partsSel = sales_data_sql_date_parts_select();

    if (!final_salesreportdata_exists()) {
        $st = db()->prepare(
            'SELECT
                id, ' . $legacyRowSel . $partsSel . ', ' . $importedAtSelect . '
             FROM sales_data
             WHERE ' . $yearWhere . '
             ORDER BY id DESC
             LIMIT ? OFFSET ?'
        );
        $st->bind_param('ssiiii', $yearStr, $yearStr, $year, $year, $limit, $offset);
        $st->execute();
        $result = $st->get_result();
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        $st->close();

        return ['total' => $nSales, 'rows' => $rows];
    }

    $nFinal = 0;
    $cf = db()->prepare('SELECT COUNT(*) AS c FROM final_salesreportdata WHERE YEAR(dated) = ?');
    if ($cf) {
        $cf->bind_param('i', $year);
        $cf->execute();
        $rf = $cf->get_result();
        if ($rf) {
            $nFinal = (int) ($rf->fetch_assoc()['c'] ?? 0);
        }
        $cf->close();
    }

    $total = $nSales + $nFinal;
    $rows = [];
    $skipLeft = $offset;
    $remaining = $limit;

    if ($skipLeft < $nSales) {
        $take = min($remaining, $nSales - $skipLeft);
        $st = db()->prepare(
            'SELECT
                id, ' . $legacyRowSel . $partsSel . ', ' . $importedAtSelect . '
             FROM sales_data
             WHERE ' . $yearWhere . '
             ORDER BY id DESC
             LIMIT ? OFFSET ?'
        );
        $st->bind_param('ssiiii', $yearStr, $yearStr, $year, $year, $take, $skipLeft);
        $st->execute();
        $result = $st->get_result();
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        $st->close();
        $remaining -= $take;
        $skipLeft = 0;
    } else {
        $skipLeft -= $nSales;
    }

    if ($remaining > 0 && $nFinal > 0) {
        $take = min($remaining, max(0, $nFinal - $skipLeft));
        if ($take > 0) {
            $st2 = db()->prepare(
                'SELECT
                    id,
                    DATE_FORMAT(dated, \'%Y-%m-%d %H:%i:%s\') AS `Dated`,
                    business_name AS `Business_Name`,
                    sales_rep AS `Sales_Rep`,
                    invoice_num AS `Invoice_Num`,
                    product AS `product`,
                    COALESCE(NULLIF(TRIM(delivery_name), \'\'), NULLIF(TRIM(delivery_routes), \'\'), \'\') AS `Delivery_Profile`,
                    quantity AS `Quantity`,
                    unit_price AS `Unit_Price`,
                    purchase_price AS `Purchase_Price`,
                    created_at AS imported_at
                 FROM final_salesreportdata
                 WHERE YEAR(dated) = ?
                 ORDER BY id DESC
                 LIMIT ? OFFSET ?'
            );
            if ($st2) {
                $st2->bind_param('iii', $year, $take, $skipLeft);
                $st2->execute();
                $res2 = $st2->get_result();
                if ($res2) {
                    while ($row = $res2->fetch_assoc()) {
                        $rows[] = $row;
                    }
                }
                $st2->close();
            }
        }
    }

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
