<?php
/**
 * Tyre dashboard — business logic ported from index.jsx (parseSellReport, aggregate, derived metrics).
 */

declare(strict_types=1);

const DASHBOARD_MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

const DASHBOARD_C = [
    'bg' => '#0A0F1E',
    'surface' => '#111827',
    'card' => '#151D2E',
    'border' => '#1E2D45',
    'teal' => '#00D4C8',
    'tealDim' => '#00897B',
    'gold' => '#FFB74D',
    'rose' => '#F87171',
    'blue' => '#60A5FA',
    'purple' => '#A78BFA',
    'green' => '#34D399',
    'text' => '#E2E8F0',
    'muted' => '#64748B',
    'dim' => '#334155',
];

const YEAR_COLORS = ['#00D4C8', '#FFB74D', '#60A5FA', '#A78BFA', '#34D399'];

const BRAND_COLORS = ['#00D4C8', '#FFB74D', '#60A5FA', '#A78BFA', '#34D399', '#F87171', '#FB923C', '#E879F9'];

/**
 * Map spreadsheet header text → keys used by parse_sell_report() / sales_data.
 */
function dashboard_normalize_sell_column_name(string $raw): string
{
    $t = trim((string) $raw);
    $t = preg_replace('/^\xEF\xBB\xBF/', '', $t) ?? $t;
    $t = trim($t, '"');
    $key = strtolower((string) (preg_replace('/[\s\-]+/u', '_', $t) ?? $t));
    $key = preg_replace('/_+/', '_', $key) ?? $key;

    if (in_array($key, ['dated', 'date', 'sale_date', 'invoice_date', 'd_date'], true)) {
        return 'Dated';
    }
    if (in_array($key, ['business_name', 'customer', 'cust_name', 'business', 'client'], true)) {
        return 'Business_Name';
    }
    if (in_array($key, ['sales_rep', 'rep', 'salesman', 'salesperson', 'sales_rep_name'], true)) {
        return 'Sales_Rep';
    }
    if (in_array($key, ['invoice_num', 'invoice_no', 'invoice_number', 'inv', 'inv_no', 'invoice'], true)) {
        return 'Invoice_Num';
    }
    if (in_array($key, ['delivery_profile', 'delivery', 'ship_to', 'address'], true)) {
        return 'Delivery_Profile';
    }
    if (in_array($key, ['quantity', 'qty', 'qnty', 'units', 'unit', 'sale_qty'], true)) {
        return 'Quantity';
    }
    if (in_array($key, ['unit_price', 'unitprice', 'sell_price', 'sellprice', 'price', 'selling_price'], true)) {
        return 'Unit_Price';
    }
    if (in_array($key, ['purchase_price', 'purchaseprice', 'cost', 'unit_cost', 'unitcost', 'buy_price'], true)) {
        return 'Purchase_Price';
    }
    if (in_array($key, ['product', 'product_name', 'productname', 'item', 'items', 'sku', 'description', 'desc', 'line', 'line_item', 'lineitem', 'tyre', 'tire', 'stock_code', 'part', 'parts'], true)) {
        return 'product';
    }

    return $t;
}

/**
 * Pick tab vs comma vs semicolon by how many required columns we recognize.
 *
 * @return array{0: string, 1: list<string>}
 */
function dashboard_pick_best_delimiter_for_sell_header(string $headerLine): array
{
    $candidates = [
        ['tab', explode("\t", $headerLine)],
        ['comma', str_getcsv($headerLine, ',', '"')],
        ['semi', str_getcsv($headerLine, ';', '"')],
    ];
    $bestDelim = 'tab';
    $bestRaw = explode("\t", $headerLine);
    $bestScore = -1.0;

    foreach ($candidates as [$del, $raw]) {
        if (count($raw) < 3) {
            continue;
        }
        $norm = [];
        foreach ($raw as $cell) {
            $norm[] = dashboard_normalize_sell_column_name(trim((string) $cell));
        }
        $need = ['Dated', 'Quantity', 'Unit_Price', 'product'];
        $score = 0.0;
        foreach ($need as $n) {
            if (in_array($n, $norm, true)) {
                $score += 1.0;
            }
        }
        $score += count($raw) / 1000.0;
        if ($score > $bestScore) {
            $bestScore = $score;
            $bestDelim = $del;
            $bestRaw = $raw;
        }
    }

    return [$bestDelim, $bestRaw];
}

/** @return list<string> */
function dashboard_split_sell_line(string $line, string $delim): array
{
    if ($delim === 'comma') {
        return str_getcsv($line, ',', '"');
    }
    if ($delim === 'semi') {
        return str_getcsv($line, ';', '"');
    }

    return explode("\t", $line);
}

/**
 * @return array{0:int,1:int,2:int,3:int,4:int}|null  day, month, year, hour, minute
 */
function dashboard_parse_dated_parts(string $dateStr): ?array
{
    $s = trim($dateStr);
    if ($s === '') {
        return null;
    }
    // DD/MM/YYYY [HH:MM] (also accepts single-digit day/month/hour/minute)
    if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{2,4})(?:\s+(\d{1,2}):(\d{1,2}))?/', $s, $m)) {
        $yyyy = (int) $m[3];
        if ($yyyy < 100) {
            $yyyy += 2000;
        }
        $hh = isset($m[4]) && $m[4] !== '' ? (int) $m[4] : 0;
        $ii = isset($m[5]) && $m[5] !== '' ? (int) $m[5] : 0;
        $a = (int) $m[1];
        $b = (int) $m[2];
        $dd = $a;
        $mm = $b;
        if (!checkdate($mm, $dd, $yyyy)) {
            if (checkdate($a, $b, $yyyy)) {
                $mm = $a;
                $dd = $b;
            } elseif (checkdate($b, $a, $yyyy)) {
                $mm = $b;
                $dd = $a;
            } else {
                return null;
            }
        }

        return [$dd, $mm, $yyyy, $hh, $ii];
    }
    // DD-MM-YYYY [HH:MM] (Excel/text exports often use dashes)
    if (preg_match('/^(\d{1,2})-(\d{1,2})-(\d{2,4})(?:\s+(\d{1,2}):(\d{1,2}))?/', $s, $m)) {
        $yyyy = (int) $m[3];
        if ($yyyy < 100) {
            $yyyy += 2000;
        }
        $hh = isset($m[4]) && $m[4] !== '' ? (int) $m[4] : 0;
        $ii = isset($m[5]) && $m[5] !== '' ? (int) $m[5] : 0;
        $a = (int) $m[1];
        $b = (int) $m[2];
        $dd = $a;
        $mm = $b;
        if (!checkdate($mm, $dd, $yyyy)) {
            if (checkdate($a, $b, $yyyy)) {
                $mm = $a;
                $dd = $b;
            } elseif (checkdate($b, $a, $yyyy)) {
                $mm = $b;
                $dd = $a;
            } else {
                return null;
            }
        }

        return [$dd, $mm, $yyyy, $hh, $ii];
    }
    // YYYY-MM-DD [HH:MM] / YYYY/MM/DD [HH:MM] / ISO T
    if (preg_match('/^(\d{4})[\/-](\d{1,2})[\/-](\d{1,2})(?:[T\s]+(\d{1,2}):(\d{1,2}))?/', $s, $m)) {
        $hh = isset($m[4]) && $m[4] !== '' ? (int) $m[4] : 0;
        $ii = isset($m[5]) && $m[5] !== '' ? (int) $m[5] : 0;

        $yyyy = (int) $m[1];
        $mm = (int) $m[2];
        $dd = (int) $m[3];
        if (!checkdate($mm, $dd, $yyyy)) {
            return null;
        }

        return [$dd, $mm, $yyyy, $hh, $ii];
    }

    // Common textual / locale variants (includes seconds + AM/PM forms).
    $formats = [
        'd/m/Y H:i:s',
        'd/m/Y H:i',
        'd-m-Y H:i:s',
        'd-m-Y H:i',
        'Y-m-d H:i:s',
        'Y-m-d H:i',
        'Y/m/d H:i:s',
        'Y/m/d H:i',
        'm/d/Y H:i:s',
        'm/d/Y H:i',
        'm/d/Y h:i:s A',
        'm/d/Y h:i A',
        'd M Y H:i:s',
        'd M Y H:i',
        'd-M-Y H:i:s',
        'd-M-Y H:i',
    ];
    foreach ($formats as $fmt) {
        $dt = DateTimeImmutable::createFromFormat($fmt, $s);
        if ($dt !== false) {
            return [
                (int) $dt->format('d'),
                (int) $dt->format('m'),
                (int) $dt->format('Y'),
                (int) $dt->format('H'),
                (int) $dt->format('i'),
            ];
        }
    }

    // Final fallback for parseable date strings.
    $ts = strtotime($s);
    if ($ts !== false) {
        $dt = new DateTimeImmutable('@' . $ts);
        $dt = $dt->setTimezone(new DateTimeZone(date_default_timezone_get() ?: 'UTC'));
        return [
            (int) $dt->format('d'),
            (int) $dt->format('m'),
            (int) $dt->format('Y'),
            (int) $dt->format('H'),
            (int) $dt->format('i'),
        ];
    }

    return null;
}

function dashboard_parse_area(string $raw): string
{
    if ($raw === '') {
        return 'Unknown';
    }
    $v = trim(preg_replace('/^"+|"+$/u', '', trim($raw)) ?? '');
    if (preg_match('/-\s*([^:"]+)/u', $v, $m)) {
        return trim(str_replace('"', '', trim($m[1])));
    }
    if (preg_match('/^([^:"]+)/u', $v, $m2)) {
        return trim($m2[1]);
    }
    return 'Unknown';
}

/**
 * @return list<array<string, mixed>>
 */
function parse_sell_report(string $text): array
{
    $text = (string) $text;
    $text = preg_replace('/^\xEF\xBB\xBF/', '', $text) ?? $text;
    if ($text !== '' && !mb_check_encoding($text, 'UTF-8')) {
        $enc = mb_detect_encoding($text, ['UTF-8', 'Windows-1252', 'ISO-8859-1', 'UTF-16LE', 'UTF-16BE'], true);
        if ($enc !== false && $enc !== 'UTF-8') {
            $text = mb_convert_encoding($text, 'UTF-8', $enc);
        }
    }

    $allLines = array_values(array_filter(preg_split("/\r\n|\n|\r/", $text) ?: [], static fn ($l) => trim((string) $l) !== ''));
    if ($allLines === []) {
        return [];
    }

    $required = ['Dated', 'Quantity', 'Unit_Price', 'product'];
    $maxSkip = min(25, count($allLines));

    for ($skip = 0; $skip < $maxSkip; $skip++) {
        $lines = array_slice($allLines, $skip);
        $headerLine = $lines[0];
        $headerLine = preg_replace('/^\xEF\xBB\xBF/', '', $headerLine) ?? $headerLine;

        [$delim, $rawHeaders] = dashboard_pick_best_delimiter_for_sell_header($headerLine);

        $headers = [];
        foreach ($rawHeaders as $h) {
            $clean = trim(preg_replace('/^"|"$/u', '', trim((string) $h)) ?? '');
            $headers[] = dashboard_normalize_sell_column_name($clean);
        }

        $idx = [];
        foreach ($headers as $i => $h) {
            if ($h !== '' && !isset($idx[$h])) {
                $idx[$h] = $i;
            }
        }

        $ok = true;
        foreach ($required as $req) {
            if (!isset($idx[$req])) {
                $ok = false;
                break;
            }
        }
        if (!$ok) {
            continue;
        }

        $maxIdx = max($idx);
        $minParts = $maxIdx + 1;
        $dataLines = array_slice($lines, 1);

        $DAY_NAMES = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        $rows = [];

        foreach ($dataLines as $line) {
            $parts = dashboard_split_sell_line($line, $delim);
            if (count($parts) < $minParts) {
                continue;
            }

            $get = static function (string $key) use ($parts, $idx): string {
                $i = $idx[$key] ?? null;
                if ($i === null || !isset($parts[$i])) {
                    return '';
                }

                return trim(preg_replace('/^"|"$/u', '', trim((string) $parts[$i])) ?? '');
            };

            $qty = (float) str_replace([',', ' '], '', $get('Quantity'));
            $price = (float) str_replace([',', ' '], '', $get('Unit_Price'));
            $cost = (float) str_replace([',', ' '], '', $get('Purchase_Price'));
            $dateStr = $get('Dated');

            $dparts = dashboard_parse_dated_parts($dateStr);
            if ($dparts === null) {
                continue;
            }
            [$dd, $mm, $yyyy, $hh, $ii] = $dparts;
            $month = $mm;
            $year = $yyyy;
            $hour = $hh;
            $dateStrNorm = sprintf('%02d/%02d/%04d %02d:%02d', $dd, $mm, $yyyy, $hh, $ii);

            $dateObj = new DateTimeImmutable(sprintf('%04d-%02d-%02d', $yyyy, $mm, $dd));
            $dayOfWeek = (int) $dateObj->format('w');
            $dayName = $DAY_NAMES[$dayOfWeek];

            $revenue = $qty * $price;
            $profit = ($price - $cost) * $qty;
            $product = $get('product');
            $brandPart = explode('-', $product, 2);
            $brand = trim($brandPart[0]);
            $area = dashboard_parse_area($get('Delivery_Profile'));

            $rows[] = [
                'year' => $year,
                'month' => $month,
                'hour' => $hour,
                'dated' => $dateStrNorm,
                'dayOfWeek' => $dayOfWeek,
                'dayName' => $dayName,
                'customer' => trim(preg_replace('/^"/', '', $get('Business_Name')) ?? ''),
                'rep' => $get('Sales_Rep'),
                'invoice' => $get('Invoice_Num'),
                'product' => $product,
                'brand' => $brand,
                'deliveryProfile' => $get('Delivery_Profile'),
                'area' => $area,
                'qty' => $qty,
                'price' => $price,
                'cost' => $cost,
                'revenue' => $revenue,
                'profit' => $profit,
            ];
        }

        if ($rows !== []) {
            return $rows;
        }
    }

    return [];
}

/**
 * One DB row from sales_data (XLS column names) → same shape as parse_sell_report() lines for aggregate().
 *
 * @param array<string, mixed> $row
 * @return array<string, mixed>|null
 */
function sales_data_row_to_parsed_line(array $row): ?array
{
    $dated = (string) ($row['Dated'] ?? $row['dated'] ?? '');
    $dparts = dashboard_parse_dated_parts($dated);
    if ($dparts === null) {
        return null;
    }
    [$dd, $mm, $yyyy, $hh, $ii] = $dparts;
    $dated = sprintf('%02d/%02d/%04d %02d:%02d', $dd, $mm, $yyyy, $hh, $ii);

    $DAY_NAMES = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    $dateObj = new DateTimeImmutable(sprintf('%04d-%02d-%02d', $yyyy, $mm, $dd));
    $dayOfWeek = (int) $dateObj->format('w');
    $dayName = $DAY_NAMES[$dayOfWeek];

    $qty = (float) ($row['Quantity'] ?? $row['quantity'] ?? 0);
    $price = (float) ($row['Unit_Price'] ?? $row['unit_price'] ?? 0);
    $cost = (float) ($row['Purchase_Price'] ?? $row['purchase_price'] ?? 0);
    $product = (string) ($row['product'] ?? '');
    $brandPart = explode('-', $product, 2);
    $brand = trim($brandPart[0]);
    $delivery = (string) ($row['Delivery_Profile'] ?? $row['delivery_profile'] ?? '');
    $customer = trim(preg_replace('/^"/', '', (string) ($row['Business_Name'] ?? $row['business_name'] ?? '')) ?? '');

    return [
        'year' => $yyyy,
        'month' => $mm,
        'hour' => $hh,
        'dated' => $dated,
        'dayOfWeek' => $dayOfWeek,
        'dayName' => $dayName,
        'customer' => $customer,
        'rep' => (string) ($row['Sales_Rep'] ?? $row['sales_rep'] ?? ''),
        'invoice' => (string) ($row['Invoice_Num'] ?? $row['invoice_num'] ?? ''),
        'product' => $product,
        'brand' => $brand,
        'deliveryProfile' => $delivery,
        'area' => dashboard_parse_area($delivery),
        'qty' => $qty,
        'price' => $price,
        'cost' => $cost,
        'revenue' => $qty * $price,
        'profit' => ($price - $cost) * $qty,
    ];
}

/**
 * @param list<array<string, mixed>> $rows
 * @return array<string, mixed>|null
 */
function aggregate(array $rows): ?array
{
    if ($rows === []) {
        return null;
    }
    $year = (int) $rows[0]['year'];

    $monthMap = [];
    foreach (DASHBOARD_MONTHS as $i => $m) {
        $monthMap[$i + 1] = [
            'month' => $m,
            'revenue' => 0.0,
            'profit' => 0.0,
            'units' => 0.0,
            'invoices' => [],
        ];
    }

    foreach ($rows as $r) {
        $mo = (int) $r['month'];
        if (!isset($monthMap[$mo])) {
            continue;
        }
        $monthMap[$mo]['revenue'] += (float) $r['revenue'];
        $monthMap[$mo]['profit'] += (float) $r['profit'];
        $monthMap[$mo]['units'] += (float) $r['qty'];
        $inv = (string) $r['invoice'];
        $monthMap[$mo]['invoices'][$inv] = true;
    }

    $monthly = [];
    foreach ($monthMap as $m) {
        $invCount = count($m['invoices']);
        $rev = $m['revenue'];
        $monthly[] = [
            'month' => $m['month'],
            'revenue' => $rev,
            'profit' => $m['profit'],
            'units' => $m['units'],
            'invoices' => $invCount,
            'margin' => $rev > 0 ? ($m['profit'] / $rev) * 100 : 0.0,
        ];
    }

    $activeMonths = array_values(array_unique(array_map(static fn ($r) => (int) $r['month'], $rows)));
    sort($activeMonths);

    $brandMap = [];
    foreach ($rows as $r) {
        $b = (string) $r['brand'];
        if (!isset($brandMap[$b])) {
            $brandMap[$b] = ['brand' => $b, 'revenue' => 0.0, 'profit' => 0.0, 'units' => 0.0];
        }
        $brandMap[$b]['revenue'] += (float) $r['revenue'];
        $brandMap[$b]['profit'] += (float) $r['profit'];
        $brandMap[$b]['units'] += (float) $r['qty'];
    }
    $brands = array_values($brandMap);
    usort($brands, static fn ($a, $b) => $b['revenue'] <=> $a['revenue']);
    $brands = array_map(static function ($b) {
        $rev = $b['revenue'];

        return [
            'brand' => $b['brand'],
            'revenue' => $rev,
            'profit' => $b['profit'],
            'units' => $b['units'],
            'margin' => $rev > 0 ? ($b['profit'] / $rev) * 100 : 0.0,
        ];
    }, $brands);

    $brandMonthly = [];
    foreach ($rows as $r) {
        $b = (string) $r['brand'];
        $mo = (int) $r['month'];
        if (!isset($brandMonthly[$b])) {
            $brandMonthly[$b] = [];
        }
        if (!isset($brandMonthly[$b][$mo])) {
            $brandMonthly[$b][$mo] = 0.0;
        }
        $brandMonthly[$b][$mo] += (float) $r['qty'];
    }

    $repMap = [];
    $repMonthly = [];
    foreach ($rows as $r) {
        $rep = (string) $r['rep'];
        if (!isset($repMap[$rep])) {
            $repMap[$rep] = [
                'rep' => $rep,
                'revenue' => 0.0,
                'profit' => 0.0,
                'units' => 0.0,
                'invoices' => [],
            ];
        }
        $repMap[$rep]['revenue'] += (float) $r['revenue'];
        $repMap[$rep]['profit'] += (float) $r['profit'];
        $repMap[$rep]['units'] += (float) $r['qty'];
        $inv = (string) $r['invoice'];
        $repMap[$rep]['invoices'][$inv] = true;
        $mo = (int) $r['month'];
        if (!isset($repMonthly[$rep])) {
            $repMonthly[$rep] = [];
        }
        if (!isset($repMonthly[$rep][$mo])) {
            $repMonthly[$rep][$mo] = 0.0;
        }
        $repMonthly[$rep][$mo] += (float) $r['revenue'];
    }
    $reps = [];
    foreach ($repMap as $r) {
        $rev = $r['revenue'];
        $reps[] = [
            'rep' => $r['rep'],
            'revenue' => $rev,
            'profit' => $r['profit'],
            'units' => $r['units'],
            'invoices' => count($r['invoices']),
            'margin' => $rev > 0 ? ($r['profit'] / $rev) * 100 : 0.0,
        ];
    }
    usort($reps, static fn ($a, $b) => $b['revenue'] <=> $a['revenue']);

    $custMap = [];
    foreach ($rows as $r) {
        $k = (string) $r['customer'];
        if (!isset($custMap[$k])) {
            $custMap[$k] = [
                'customer' => $k,
                'revenue' => 0.0,
                'profit' => 0.0,
                'units' => 0.0,
                'invoices' => [],
            ];
        }
        $custMap[$k]['revenue'] += (float) $r['revenue'];
        $custMap[$k]['profit'] += (float) $r['profit'];
        $custMap[$k]['units'] += (float) $r['qty'];
        $custMap[$k]['invoices'][(string) $r['invoice']] = true;
    }
    $customers = [];
    foreach ($custMap as $c) {
        $rev = $c['revenue'];
        $customers[] = [
            'customer' => $c['customer'],
            'revenue' => $rev,
            'profit' => $c['profit'],
            'units' => $c['units'],
            'invoices' => count($c['invoices']),
            'margin' => $rev > 0 ? ($c['profit'] / $rev) * 100 : 0.0,
        ];
    }
    usort($customers, static fn ($a, $b) => $b['revenue'] <=> $a['revenue']);

    $customerMonthly = [];
    foreach ($rows as $r) {
        $k = (string) $r['customer'];
        $mo = (int) $r['month'];
        if (!isset($customerMonthly[$k])) {
            $customerMonthly[$k] = [];
        }
        if (!isset($customerMonthly[$k][$mo])) {
            $customerMonthly[$k][$mo] = [
                'revenue' => 0.0,
                'profit' => 0.0,
                'units' => 0.0,
                'invoices' => [],
            ];
        }
        $customerMonthly[$k][$mo]['revenue'] += (float) $r['revenue'];
        $customerMonthly[$k][$mo]['profit'] += (float) $r['profit'];
        $customerMonthly[$k][$mo]['units'] += (float) $r['qty'];
        $customerMonthly[$k][$mo]['invoices'][(string) $r['invoice']] = true;
    }
    foreach ($customerMonthly as &$cm) {
        foreach ($cm as &$m) {
            $m['invoices'] = count($m['invoices']);
        }
        unset($m);
    }
    unset($cm);

    $invSeen = [];
    $heatmap = [];
    $dayInvMap = [];
    $dayRevMap = [];
    $dayUnitMap = [];
    $hourInvMap = [];
    $hourRevMap = [];
    $hourUnitMap = [];

    foreach ($rows as $r) {
        $hmKey = $r['dayOfWeek'] . '_' . $r['hour'];
        $invKey = $r['invoice'] . '_' . $hmKey;
        if (!isset($invSeen[$invKey])) {
            $invSeen[$invKey] = true;
            $heatmap[$hmKey] = ($heatmap[$hmKey] ?? 0) + 1;
        }
        $dow = (int) $r['dayOfWeek'];
        if (!isset($dayInvMap[$dow])) {
            $dayInvMap[$dow] = [];
        }
        $dayInvMap[$dow][(string) $r['invoice']] = true;
        $dayRevMap[$dow] = ($dayRevMap[$dow] ?? 0) + (float) $r['revenue'];
        $dayUnitMap[$dow] = ($dayUnitMap[$dow] ?? 0) + (float) $r['qty'];

        $hr = (int) $r['hour'];
        if (!isset($hourInvMap[$hr])) {
            $hourInvMap[$hr] = [];
        }
        $hourInvMap[$hr][(string) $r['invoice']] = true;
        $hourRevMap[$hr] = ($hourRevMap[$hr] ?? 0) + (float) $r['revenue'];
        $hourUnitMap[$hr] = ($hourUnitMap[$hr] ?? 0) + (float) $r['qty'];
    }

    $DAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    $byDayOfWeek = [];
    foreach ($DAYS as $i => $d) {
        $byDayOfWeek[] = [
            'day' => $d,
            'dayIndex' => $i,
            'invoices' => isset($dayInvMap[$i]) ? count($dayInvMap[$i]) : 0,
            'revenue' => $dayRevMap[$i] ?? 0.0,
            'units' => $dayUnitMap[$i] ?? 0.0,
        ];
    }

    $byHour = [];
    for ($h = 0; $h < 24; $h++) {
        $label = $h === 0 ? '12am' : ($h < 12 ? $h . 'am' : ($h === 12 ? '12pm' : ($h - 12) . 'pm'));
        $byHour[] = [
            'hour' => $h,
            'label' => $label,
            'invoices' => isset($hourInvMap[$h]) ? count($hourInvMap[$h]) : 0,
            'revenue' => $hourRevMap[$h] ?? 0.0,
            'units' => $hourUnitMap[$h] ?? 0.0,
        ];
    }

    $areaMap = [];
    foreach ($rows as $r) {
        $k = (string) $r['area'];
        if (!isset($areaMap[$k])) {
            $areaMap[$k] = [
                'area' => $k,
                'revenue' => 0.0,
                'profit' => 0.0,
                'units' => 0.0,
                'invoices' => [],
                'customers' => [],
            ];
        }
        $areaMap[$k]['revenue'] += (float) $r['revenue'];
        $areaMap[$k]['profit'] += (float) $r['profit'];
        $areaMap[$k]['units'] += (float) $r['qty'];
        $areaMap[$k]['invoices'][(string) $r['invoice']] = true;
        $areaMap[$k]['customers'][(string) $r['customer']] = true;
    }
    $byArea = [];
    foreach ($areaMap as $a) {
        $rev = $a['revenue'];
        $byArea[] = [
            'area' => $a['area'],
            'revenue' => $rev,
            'profit' => $a['profit'],
            'units' => $a['units'],
            'invoices' => count($a['invoices']),
            'customers' => count($a['customers']),
            'margin' => $rev > 0 ? ($a['profit'] / $rev) * 100 : 0.0,
        ];
    }
    usort($byArea, static fn ($x, $y) => $y['revenue'] <=> $x['revenue']);

    $totalRevenue = array_sum(array_map(static fn ($r) => (float) $r['revenue'], $rows));
    $totalProfit = array_sum(array_map(static fn ($r) => (float) $r['profit'], $rows));
    $totalUnits = array_sum(array_map(static fn ($r) => (float) $r['qty'], $rows));
    $uniqInv = [];
    $uniqCust = [];
    foreach ($rows as $r) {
        $uniqInv[(string) $r['invoice']] = true;
        $uniqCust[(string) $r['customer']] = true;
    }

    return [
        'year' => $year,
        'monthly' => $monthly,
        'activeMonths' => $activeMonths,
        'brands' => $brands,
        'brandMonthly' => $brandMonthly,
        'reps' => $reps,
        'repMonthly' => $repMonthly,
        'customers' => $customers,
        'customerMonthly' => $customerMonthly,
        'heatmap' => $heatmap,
        'byDayOfWeek' => $byDayOfWeek,
        'byHour' => $byHour,
        'byArea' => $byArea,
        'totals' => [
            'revenue' => $totalRevenue,
            'profit' => $totalProfit,
            'units' => $totalUnits,
            'invoices' => count($uniqInv),
            'customers' => count($uniqCust),
            'margin' => $totalRevenue > 0 ? ($totalProfit / $totalRevenue) * 100 : 0.0,
        ],
    ];
}

function fmt_aud(?float $v): string
{
    if ($v === null) {
        return '—';
    }
    $a = abs($v);
    if ($a >= 1e6) {
        return 'A$' . number_format($v / 1e6, 2) . 'M';
    }
    if ($a >= 1e3) {
        return 'A$' . number_format($v / 1e3, 0) . 'K';
    }

    return 'A$' . number_format($v, 0, '.', '');
}

function fmt_audf(?float $v): string
{
    if ($v === null) {
        return '—';
    }

    return 'A$' . number_format($v, 0, '.', ',');
}

function fmt_pct(?float $v): string
{
    if ($v === null) {
        return '—';
    }

    return number_format($v, 1) . '%';
}

function fmt_num(?float $v): string
{
    if ($v === null) {
        return '—';
    }

    return number_format($v, 0, '.', ',');
}

/**
 * @param array<int, array<string, mixed>> $yearData keyed by year int
 * @param list<int>                        $shownYears
 * @param ?list<int>                       $monthIndexes
 * @return list<array<string, mixed>>
 */
function compute_monthly_comparison(array $yearData, array $shownYears, ?array $monthIndexes = null): array
{
    $out = [];
    $monthSet = $monthIndexes === null ? null : array_fill_keys(array_map('intval', $monthIndexes), true);

    foreach (DASHBOARD_MONTHS as $i => $m) {
        $monthNo = $i + 1;
        if ($monthSet !== null && !isset($monthSet[$monthNo])) {
            continue;
        }
        $row = ['month' => $m];
        foreach ($shownYears as $y) {
            $d = $yearData[$y]['monthly'][$i] ?? null;
            $row['rev_' . $y] = $d['revenue'] ?? 0.0;
            $row['profit_' . $y] = $d['profit'] ?? 0.0;
            $row['margin_' . $y] = $d['margin'] ?? 0.0;
            $row['units_' . $y] = $d['units'] ?? 0.0;
        }
        $out[] = $row;
    }

    return $out;
}

/**
 * @param list<int> $years
 * @return list<string>
 */
function compute_top_brands(array $yearData, array $years): array
{
    if ($years === []) {
        return [];
    }
    $totals = [];
    foreach ($years as $y) {
        foreach ($yearData[$y]['brands'] ?? [] as $b) {
            $name = (string) $b['brand'];
            $totals[$name] = ($totals[$name] ?? 0) + (float) $b['units'];
        }
    }
    arsort($totals);
    $top = array_slice(array_keys($totals), 0, 8);

    return $top;
}

/**
 * @param list<string> $topBrands
 * @return list<array<string, mixed>>
 */
function compute_brand_monthly_pivot(array $yearData, array $years, array $topBrands): array
{
    if ($topBrands === []) {
        return [];
    }
    $rows = [];
    foreach ($topBrands as $brand) {
        foreach ($years as $y) {
            if (!isset($yearData[$y])) {
                continue;
            }
            $yd = $yearData[$y];
            $row = ['brand' => $brand, 'year' => $y];
            $total = 0;
            foreach (DASHBOARD_MONTHS as $mi => $m) {
                $units = (int) round($yd['brandMonthly'][$brand][$mi + 1] ?? 0);
                $row[$m] = $units;
                $total += $units;
            }
            $row['Total'] = $total;
            $rows[] = $row;
        }
    }

    return $rows;
}

/**
 * @return array<string, float|int>
 */
function compute_pivot_col_totals(array $brandMonthlyPivot, array $years): array
{
    $result = [];
    foreach ($years as $y) {
        foreach (DASHBOARD_MONTHS as $m) {
            $key = $m . '_' . $y;
            $sum = 0.0;
            foreach ($brandMonthlyPivot as $r) {
                if (($r['year'] ?? null) === $y) {
                    $sum += (float) ($r[$m] ?? 0);
                }
            }
            $result[$key] = $sum;
        }
        $t = 0.0;
        foreach ($brandMonthlyPivot as $r) {
            if (($r['year'] ?? null) === $y) {
                $t += (float) ($r['Total'] ?? 0);
            }
        }
        $result['Total_' . $y] = $t;
    }

    return $result;
}

/**
 * @return array<string, mixed>|null
 */
function compute_cust_analytics(?array $custCurData, ?array $custCmpData): ?array
{
    if ($custCurData === null || $custCmpData === null) {
        return null;
    }

    $curMonths = array_fill_keys($custCurData['activeMonths'] ?? [], true);
    if ($curMonths === []) {
        return null;
    }
    $minMonth = min(array_keys($curMonths));
    $maxMonth = max(array_keys($curMonths));

    $cmpPeriodMap = [];
    foreach ($custCmpData['customerMonthly'] ?? [] as $customer => $monthData) {
        foreach ($monthData as $mStr => $data) {
            $m = (int) $mStr;
            if ($m >= $minMonth && $m <= $maxMonth) {
                if (!isset($cmpPeriodMap[$customer])) {
                    $cmpPeriodMap[$customer] = [
                        'revenue' => 0.0,
                        'profit' => 0.0,
                        'units' => 0.0,
                        'invoices' => 0,
                    ];
                }
                $cmpPeriodMap[$customer]['revenue'] += (float) $data['revenue'];
                $cmpPeriodMap[$customer]['profit'] += (float) $data['profit'];
                $cmpPeriodMap[$customer]['units'] += (float) $data['units'];
                $cmpPeriodMap[$customer]['invoices'] += (int) $data['invoices'];
            }
        }
    }

    $curMap = [];
    foreach ($custCurData['customers'] as $c) {
        $curMap[(string) $c['customer']] = $c;
    }

    $curNames = array_fill_keys(array_keys($curMap), true);
    $cmpNames = array_fill_keys(array_keys($cmpPeriodMap), true);

    $retained = [];
    $gained = [];
    $lost = [];

    foreach (array_keys($curNames) as $name) {
        if (isset($cmpNames[$name])) {
            $cur = $curMap[$name];
            $cmp = $cmpPeriodMap[$name];
            $revChg = $cmp['revenue'] > 0
                ? (($cur['revenue'] - $cmp['revenue']) / $cmp['revenue']) * 100
                : null;
            $retained[] = array_merge($cur, [
                'prevRevenue' => $cmp['revenue'],
                'prevUnits' => $cmp['units'],
                'revChg' => $revChg,
            ]);
        } else {
            $gained[] = $curMap[$name];
        }
    }

    foreach (array_keys($cmpNames) as $name) {
        if (!isset($curNames[$name])) {
            $p = $cmpPeriodMap[$name];
            $lost[] = [
                'customer' => $name,
                'samePeriodRevenue' => $p['revenue'],
                'revenue' => $p['revenue'],
                'profit' => $p['profit'],
                'units' => $p['units'],
                'invoices' => $p['invoices'],
                'margin' => $p['revenue'] > 0 ? ($p['profit'] / $p['revenue']) * 100 : 0.0,
            ];
        }
    }

    usort($retained, static fn ($a, $b) => $b['revenue'] <=> $a['revenue']);
    usort($gained, static fn ($a, $b) => $b['revenue'] <=> $a['revenue']);
    usort($lost, static fn ($a, $b) => $b['samePeriodRevenue'] <=> $a['samePeriodRevenue']);

    $retainedRevCur = array_sum(array_column($retained, 'revenue'));
    $retainedRevCmp = array_sum(array_column($retained, 'prevRevenue'));
    $gainedRev = array_sum(array_column($gained, 'revenue'));
    $lostRev = array_sum(array_column($lost, 'samePeriodRevenue'));
    $cmpSize = count($cmpNames);
    $retentionRate = $cmpSize > 0 ? (count($retained) / $cmpSize) * 100 : null;

    $sortedMonthIdx = array_keys($curMonths);
    sort($sortedMonthIdx, SORT_NUMERIC);
    $mNames = array_map(static fn (int $cm) => DASHBOARD_MONTHS[$cm - 1], $sortedMonthIdx);
    $periodLabel = count($mNames) === 1
        ? $mNames[0]
        : $mNames[0] . '–' . $mNames[count($mNames) - 1];

    return [
        'retained' => $retained,
        'gained' => $gained,
        'lost' => $lost,
        'retainedRevLatest' => $retainedRevCur,
        'retainedRevPrev' => $retainedRevCmp,
        'gainedRev' => $gainedRev,
        'lostRev' => $lostRev,
        'retentionRate' => $retentionRate,
        'periodLabel' => $periodLabel,
        'totalPrev' => $cmpSize,
        'totalLatest' => count($curNames),
    ];
}

function yoy_trend(?array $latestData, ?array $prevData, string $key): ?float
{
    if ($latestData === null || $prevData === null) {
        return null;
    }
    $cur = (float) ($latestData['totals'][$key] ?? 0);
    $pre = (float) ($prevData['totals'][$key] ?? 0);

    return $pre > 0 ? (($cur - $pre) / $pre) * 100 : null;
}

/**
 * Decode aggregate JSON from DB (stdClass → array recursively).
 */
function agg_json_decode(string $json): array
{
    $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($data)) {
        throw new RuntimeException('Invalid aggregate JSON');
    }

    return $data;
}
