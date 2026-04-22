<?php

declare(strict_types=1);

/**
 * Paginated raw sales_data rows for JBI dashboard Raw Rows tab (JSON).
 */

require_once __DIR__ . '/db_store.php';

requirePermission('dashboard.view');

$year = (int) ($_GET['year'] ?? 0);
$page = (int) ($_GET['page'] ?? 1);
$limit = (int) ($_GET['limit'] ?? 100);

if ($year < 1990 || $year > 2100) {
    jsonResponse(false, 'Invalid year', null);
}

if ($page < 1) {
    $page = 1;
}

$limit = max(1, min(500, $limit));
$offset = ($page - 1) * $limit;

try {
    $result = fetch_sales_rows($year, $limit, $offset);
} catch (Throwable $e) {
    jsonResponse(false, DEBUG_MODE ? $e->getMessage() : 'Failed to load rows', null);
}

jsonResponse(true, 'OK', [
    'total' => $result['total'],
    'rows' => $result['rows'],
    'limit' => $limit,
    'page' => $page,
    'offset' => $offset,
]);
