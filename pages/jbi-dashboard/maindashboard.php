<?php

declare(strict_types=1);

require_once __DIR__ . '/db_store.php';
require_once __DIR__ . '/auth.php';

if (basename($_SERVER['SCRIPT_NAME']) === 'maindashboard.php') {
    header('Location: index.php', true, 302);
    exit;
}

ensure_auth_schema();
require_login_or_redirect();

function h(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$C = DASHBOARD_C;
$stylesSans = "font-family:'DM Sans','Segoe UI',sans-serif";
$stylesMono = "font-family:'Courier New',Courier,monospace";

$storageMsg = (string) ($_SESSION['flash_storage_msg'] ?? '');
$errorFlash = (string) ($_SESSION['flash_error'] ?? '');
unset($_SESSION['flash_storage_msg'], $_SESSION['flash_error']);

$yearData = [];
$yearsFromLines = [];
$dbError = '';
$cacheTtlSeconds = 90;
$canUseCache = false;

$cachedYearData = $_SESSION['year_data_cache'] ?? null;
$cachedYearsFromLines = $_SESSION['years_from_lines_cache'] ?? null;
if (
    is_array($cachedYearData)
    && is_array($cachedYearsFromLines)
    && isset($cachedYearData['generated_at'], $cachedYearData['data'])
    && isset($cachedYearsFromLines['generated_at'], $cachedYearsFromLines['data'])
    && (time() - (int) $cachedYearData['generated_at']) <= $cacheTtlSeconds
    && (time() - (int) $cachedYearsFromLines['generated_at']) <= $cacheTtlSeconds
    && is_array($cachedYearData['data'])
    && is_array($cachedYearsFromLines['data'])
) {
    $yearData = $cachedYearData['data'];
    $yearsFromLines = $cachedYearsFromLines['data'];
    $canUseCache = true;
}

if (!$canUseCache) {
    try {
        $yearData = load_all_year_data();
    } catch (Throwable $e) {
        $dbError = $e->getMessage();
        $yearData = [];
    }
}

if (!$canUseCache && $dbError === '') {
    try {
        $yearsFromLines = list_sales_data_years();
    } catch (Throwable $e) {
        $dbError = $dbError ?: $e->getMessage();
    }
}


if (!$canUseCache && $dbError === '') {
    $_SESSION['year_data_cache'] = [
        'generated_at' => time(),
        'data' => $yearData,
    ];
    $_SESSION['years_from_lines_cache'] = [
        'generated_at' => time(),
        'data' => $yearsFromLines,
    ];
}

$yearsAgg = array_keys($yearData);
$years = array_values(array_unique(array_merge($yearsAgg, $yearsFromLines)));
sort($years, SORT_NUMERIC);

$hiddenYears = array_values(array_unique(array_map('intval', $_SESSION['hidden_years'] ?? [])));
if ($hiddenYears !== []) {
    $years = array_values(array_filter($years, static fn($y) => !in_array((int) $y, $hiddenYears, true)));
}

if (!isset($_SESSION['active_years']) || $_SESSION['active_years'] === []) {
    $_SESSION['active_years'] = $years;
}
$activeYears = array_values(array_map('intval', $_SESSION['active_years']));
$activeYears = array_values(array_filter($activeYears, static fn($y) => in_array($y, $years, true)));
if ($activeYears === [] && $years !== []) {
    $activeYears = $years;
    $_SESSION['active_years'] = $years;
}

$shownYears = array_values(array_filter($activeYears, static fn($y) => isset($yearData[$y])));
$view = (string) ($_SESSION['view'] ?? 'overview');
$allowedViews = ['overview', 'monthly', 'brands', 'customers', 'reps', 'activity', 'rawdata'];
if (!in_array($view, $allowedViews, true)) {
    $view = 'overview';
}
$quickFilter = (string) ($_SESSION['quick_filter'] ?? 'year');
if (!in_array($quickFilter, ['1m', '3m', '6m', 'year'], true)) {
    $quickFilter = 'year';
}

$custTab = (string) ($_SESSION['cust_tab'] ?? 'all');
$hasData = $years !== [] || $yearData !== [];

$latestYear = count($years) > 0 ? (int) $years[count($years) - 1] : null;
$prevYear = count($years) >= 2 ? (int) $years[count($years) - 2] : null;
$latestData = ($latestYear !== null && isset($yearData[$latestYear])) ? $yearData[$latestYear] : null;
$prevData = ($prevYear !== null && isset($yearData[$prevYear])) ? $yearData[$prevYear] : null;

$selectedMonths = [];
if ($quickFilter === 'year') {
    $selectedMonths = range(1, 12);
} else {
    if ($quickFilter === '1m') {
        $monthWindow = 1;
    } elseif ($quickFilter === '3m') {
        $monthWindow = 3;
    } else {
        $monthWindow = 6;
    }

    $yearsForWindow = $shownYears !== [] ? $shownYears : ($latestYear !== null ? [$latestYear] : []);
    $commonMonthSet = null;
    foreach ($yearsForWindow as $y) {
        $yearMonths = array_values(array_unique(array_map('intval', $yearData[$y]['activeMonths'] ?? [])));
        $yearMonthSet = array_fill_keys($yearMonths, true);
        if ($commonMonthSet === null) {
            $commonMonthSet = $yearMonthSet;
            continue;
        }
        $commonMonthSet = array_intersect_key($commonMonthSet, $yearMonthSet);
    }

    $selectedMonths = array_map('intval', array_keys($commonMonthSet ?? []));
    sort($selectedMonths, SORT_NUMERIC);

    if ($selectedMonths === [] && $latestData !== null) {
        // Fallback to latest-year months when no overlap exists between selected years.
        $selectedMonths = array_values(array_unique(array_map('intval', $latestData['activeMonths'] ?? [])));
        sort($selectedMonths, SORT_NUMERIC);
    }

    if ($selectedMonths === []) {
        $selectedMonths = range(1, 12);
    }

    $selectedMonths = array_slice($selectedMonths, -$monthWindow);
}
$selectedMonthSet = $selectedMonths === [] ? [] : array_fill_keys($selectedMonths, true);

$computePeriodSnapshot = static function (?array $data, array $monthSet): ?array {
    if ($data === null || $monthSet === []) {
        return $data;
    }

    $monthly = [];
    foreach (($data['monthly'] ?? []) as $i => $row) {
        $monthNo = $i + 1;
        if (isset($monthSet[$monthNo])) {
            $monthly[] = $row;
        }
    }

    $revenue = array_sum(array_map(static fn($m) => (float) ($m['revenue'] ?? 0), $monthly));
    $profit = array_sum(array_map(static fn($m) => (float) ($m['profit'] ?? 0), $monthly));
    $units = array_sum(array_map(static fn($m) => (float) ($m['units'] ?? 0), $monthly));
    $invoices = (int) round(array_sum(array_map(static fn($m) => (float) ($m['invoices'] ?? 0), $monthly)));

    $customerCount = 0;
    if (isset($data['customerMonthly']) && is_array($data['customerMonthly'])) {
        foreach ($data['customerMonthly'] as $customerMonths) {
            if (!is_array($customerMonths)) {
                continue;
            }
            foreach ($customerMonths as $monthNo => $monthData) {
                if (isset($monthSet[(int) $monthNo]) && (($monthData['revenue'] ?? 0) > 0 || ($monthData['invoices'] ?? 0) > 0)) {
                    $customerCount++;
                    break;
                }
            }
        }
    } else {
        $customerCount = (int) ($data['totals']['customers'] ?? 0);
    }

    $out = $data;
    $out['monthly'] = $monthly;
    $out['activeMonths'] = array_values(array_filter(
        array_map('intval', $data['activeMonths'] ?? []),
        static fn($m) => isset($monthSet[$m])
    ));
    $out['totals'] = $data['totals'] ?? [];
    $out['totals']['revenue'] = $revenue;
    $out['totals']['profit'] = $profit;
    $out['totals']['units'] = $units;
    $out['totals']['invoices'] = $invoices;
    $out['totals']['customers'] = $customerCount;
    $out['totals']['margin'] = $revenue > 0 ? ($profit / $revenue) * 100 : 0.0;

    return $out;
};

if ($selectedMonthSet !== []) {
    $latestData = $computePeriodSnapshot($latestData, $selectedMonthSet);
    $prevData = $computePeriodSnapshot($prevData, $selectedMonthSet);
}

$monthlyComparison = $hasData ? compute_monthly_comparison($yearData, $shownYears, $selectedMonths) : [];

$brandData = [];
if ($latestData) {
    $brandData = array_slice($latestData['brands'], 0, 15);
}

$topBrands = compute_top_brands($yearData, $years);

$brandChartYear = isset($_SESSION['brand_chart_year']) ? (int) $_SESSION['brand_chart_year'] : null;
$brandChartYearEff = ($brandChartYear && isset($yearData[$brandChartYear]))
    ? $brandChartYear
    : (int) $latestYear;

$brandMonthlyUnits = [];
if (isset($yearData[$brandChartYearEff]) && $topBrands !== []) {
    $yd = $yearData[$brandChartYearEff];
    foreach (DASHBOARD_MONTHS as $mi => $m) {
        $row = ['month' => $m];
        foreach ($topBrands as $brand) {
            $row[$brand] = (int) round($yd['brandMonthly'][$brand][$mi + 1] ?? 0);
        }
        $brandMonthlyUnits[] = $row;
    }
}

$brandMonthlyPivot = compute_brand_monthly_pivot($yearData, $years, $topBrands);
$pivotColTotals = compute_pivot_col_totals($brandMonthlyPivot, $years);

$custYearSel = isset($_SESSION['cust_year']) ? (int) $_SESSION['cust_year'] : null;
$custCurYear = ($custYearSel && isset($yearData[$custYearSel])) ? $custYearSel : (int) $latestYear;
$yi = array_search($custCurYear, $years, true);
$custCmpYear = ($yi !== false && $yi > 0) ? $years[$yi - 1] : null;
$custCurData = $yearData[$custCurYear] ?? null;
$custCmpData = ($custCmpYear !== null && isset($yearData[$custCmpYear])) ? $yearData[$custCmpYear] : null;
$custAnalytics = compute_cust_analytics($custCurData, $custCmpData);

$activityYear = isset($_SESSION['activity_year']) ? (int) $_SESSION['activity_year'] : null;
$areaYear = isset($_SESSION['area_year']) ? (int) $_SESSION['area_year'] : null;
$actYr = ($activityYear && isset($yearData[$activityYear])) ? $activityYear : (int) $latestYear;
$areaYr = ($areaYear && isset($yearData[$areaYear])) ? $areaYear : (int) $latestYear;
$actD = $yearData[$actYr] ?? null;
$areaD = $yearData[$areaYr] ?? null;

/** Chart.js configs keyed by canvas data-chart-id */
$chartConfigs = [];

if ($view === 'overview' && $hasData && $shownYears !== [] && $monthlyComparison !== []) {
    $labels = array_column($monthlyComparison, 'month');
    $datasets = [];
    foreach ($shownYears as $i => $y) {
        $datasets[] = [
            'label' => (string) $y,
            'data' => array_map(static fn($row) => (float) ($row['rev_' . $y] ?? 0), $monthlyComparison),
            'backgroundColor' => YEAR_COLORS[$i % count(YEAR_COLORS)],
            'borderRadius' => 3,
        ];
    }
    $chartConfigs['ov_monthly_rev'] = [
        'type' => 'bar',
        'data' => ['labels' => $labels, 'datasets' => $datasets],
        'options' => [
            'plugins' => ['legend' => ['labels' => ['font' => ['size' => 12]]]],
            'scales' => [
                'x' => ['grid' => ['display' => false], 'ticks' => ['font' => ['size' => 11]]],
                'y' => [
                    'grid' => ['color' => '#1E2D45', 'borderDash' => [3, 3]],
                    'ticks' => ['font' => ['size' => 10]],
                ],
            ],
        ],
    ];

    $lineDs = [];
    foreach ($shownYears as $i => $y) {
        $lineDs[] = [
            'label' => (string) $y,
            'data' => array_map(static fn($row) => (float) ($row['margin_' . $y] ?? 0), $monthlyComparison),
            'borderColor' => YEAR_COLORS[$i % count(YEAR_COLORS)],
            'backgroundColor' => YEAR_COLORS[$i % count(YEAR_COLORS)],
            'tension' => 0.2,
            'pointRadius' => 3,
            'borderWidth' => 2.5,
        ];
    }
    $chartConfigs['ov_margin_line'] = [
        'type' => 'line',
        'data' => ['labels' => $labels, 'datasets' => $lineDs],
        'options' => [
            'plugins' => ['legend' => ['labels' => ['font' => ['size' => 12]]]],
            'scales' => [
                'x' => ['grid' => ['display' => false]],
                'y' => [
                    'grid' => ['color' => '#1E2D45', 'borderDash' => [3, 3]],
                    'ticks' => ['font' => ['size' => 10]],
                ],
            ],
        ],
    ];
}

if ($view === 'overview' && $latestData && count($brandData) > 0) {
    $b8 = array_slice($brandData, 0, 8);
    $fills = array_map(static function ($b) use ($C) {
        $m = (float) $b['margin'];
        if ($m < 0) {
            return $C['rose'];
        }
        if ($m < 8) {
            return $C['gold'];
        }

        return $C['teal'];
    }, $b8);
    $chartConfigs['ov_brands_bar'] = [
        'type' => 'bar',
        'data' => [
            'labels' => array_column($b8, 'brand'),
            'datasets' => [
                [
                    'label' => 'Revenue',
                    'data' => array_column($b8, 'revenue'),
                    'backgroundColor' => $fills,
                    'borderRadius' => [0, 4, 4, 0],
                ]
            ],
        ],
        'options' => [
            'indexAxis' => 'y',
            'plugins' => ['legend' => ['display' => false]],
            'scales' => [
                'x' => [
                    'grid' => ['color' => '#1E2D45', 'borderDash' => [3, 3]],
                    'ticks' => ['font' => ['size' => 10]],
                ],
                'y' => ['grid' => ['display' => false], 'ticks' => ['font' => ['size' => 11]]],
            ],
        ],
    ];
}

if ($view === 'monthly' && $hasData && $shownYears !== []) {
    $labels = array_column($monthlyComparison, 'month');
    $lineDs = [];
    foreach ($shownYears as $i => $y) {
        $lineDs[] = [
            'label' => (string) $y,
            'data' => array_map(static fn($row) => (float) ($row['rev_' . $y] ?? 0), $monthlyComparison),
            'borderColor' => YEAR_COLORS[$i % count(YEAR_COLORS)],
            'backgroundColor' => YEAR_COLORS[$i % count(YEAR_COLORS)],
            'tension' => 0.2,
            'pointRadius' => 4,
            'borderWidth' => 2.5,
        ];
    }
    $chartConfigs['mo_rev_yoy'] = [
        'type' => 'line',
        'data' => ['labels' => $labels, 'datasets' => $lineDs],
        'options' => [
            'plugins' => ['legend' => ['labels' => ['font' => ['size' => 12]]]],
            'scales' => [
                'x' => ['grid' => ['display' => false]],
                'y' => [
                    'grid' => ['color' => '#1E2D45', 'borderDash' => [3, 3]],
                    'ticks' => ['font' => ['size' => 10]],
                ],
            ],
        ],
    ];

    $barProfit = [];
    foreach ($shownYears as $i => $y) {
        $barProfit[] = [
            'label' => (string) $y,
            'data' => array_map(static fn($row) => (float) ($row['profit_' . $y] ?? 0), $monthlyComparison),
            'backgroundColor' => YEAR_COLORS[$i % count(YEAR_COLORS)],
            'borderRadius' => [3, 3, 0, 0],
        ];
    }
    $chartConfigs['mo_profit_bar'] = [
        'type' => 'bar',
        'data' => ['labels' => $labels, 'datasets' => $barProfit],
        'options' => [
            'plugins' => ['legend' => ['labels' => ['font' => ['size' => 12]]]],
            'scales' => [
                'x' => ['grid' => ['display' => false]],
                'y' => [
                    'grid' => ['color' => '#1E2D45', 'borderDash' => [3, 3]],
                    'ticks' => ['font' => ['size' => 10]],
                ],
            ],
        ],
    ];

    $barUnits = [];
    foreach ($shownYears as $i => $y) {
        $barUnits[] = [
            'label' => (string) $y,
            'data' => array_map(static fn($row) => (float) ($row['units_' . $y] ?? 0), $monthlyComparison),
            'backgroundColor' => YEAR_COLORS[$i % count(YEAR_COLORS)],
            'borderRadius' => [3, 3, 0, 0],
        ];
    }
    $chartConfigs['mo_units_bar'] = [
        'type' => 'bar',
        'data' => ['labels' => $labels, 'datasets' => $barUnits],
        'options' => [
            'plugins' => ['legend' => ['labels' => ['font' => ['size' => 12]]]],
            'scales' => [
                'x' => ['grid' => ['display' => false]],
                'y' => ['grid' => ['color' => '#1E2D45', 'borderDash' => [3, 3]], 'ticks' => ['font' => ['size' => 10]]],
            ],
        ],
    ];
}

if ($view === 'brands' && $latestData && $brandData !== []) {
    $fills = array_map(static function ($b) use ($C) {
        $m = (float) $b['margin'];
        if ($m < 0) {
            return $C['rose'];
        }
        if ($m < 8) {
            return $C['gold'];
        }

        return $C['teal'];
    }, $brandData);
    $chartConfigs['br_rev_all'] = [
        'type' => 'bar',
        'data' => [
            'labels' => array_column($brandData, 'brand'),
            'datasets' => [
                [
                    'label' => 'Revenue',
                    'data' => array_column($brandData, 'revenue'),
                    'backgroundColor' => $fills,
                    'borderRadius' => [0, 4, 4, 0],
                ]
            ],
        ],
        'options' => [
            'indexAxis' => 'y',
            'plugins' => ['legend' => ['display' => false]],
            'scales' => [
                'x' => [
                    'ticks' => ['font' => ['size' => 10]],
                    'grid' => ['color' => '#1E2D45', 'borderDash' => [3, 3]],
                ],
                'y' => [
                    'ticks' => ['font' => ['size' => 11], 'color' => '#E2E8F0'],
                    'grid' => ['display' => false],
                ],
            ],
        ],
    ];
}

if ($view === 'brands' && $brandMonthlyUnits !== [] && $topBrands !== []) {
    $labels = array_column($brandMonthlyUnits, 'month');
    $datasets = [];
    foreach ($topBrands as $i => $brand) {
        $datasets[] = [
            'label' => $brand,
            'data' => array_map(static fn($row) => (float) ($row[$brand] ?? 0), $brandMonthlyUnits),
            'backgroundColor' => BRAND_COLORS[$i % count(BRAND_COLORS)],
            'stack' => 'a',
        ];
    }
    $last = count($datasets) - 1;
    foreach ($datasets as $i => &$ds) {
        $ds['borderRadius'] = $i === $last ? [3, 3, 0, 0] : 0;
    }
    unset($ds);
    $chartConfigs['br_monthly_stack'] = [
        'type' => 'bar',
        'data' => ['labels' => $labels, 'datasets' => $datasets],
        'options' => [
            'plugins' => ['legend' => ['labels' => ['font' => ['size' => 11]]]],
            'scales' => [
                'x' => ['stacked' => true, 'grid' => ['display' => false]],
                'y' => ['stacked' => true, 'grid' => ['color' => '#1E2D45', 'borderDash' => [3, 3]]],
            ],
        ],
    ];
}

if ($view === 'reps' && $latestData && ($latestData['reps'] ?? []) !== []) {
    $repRows = [];
    foreach ($latestData['monthly'] as $mi => $m) {
        $row = ['month' => $m['month']];
        foreach ($latestData['reps'] as $rep) {
            $name = (string) $rep['rep'];
            $row[$name] = (float) ($latestData['repMonthly'][$name][$mi + 1] ?? 0);
        }
        $repRows[] = $row;
    }
    $labels = array_column($repRows, 'month');
    $datasets = [];
    foreach ($latestData['reps'] as $i => $rep) {
        $name = (string) $rep['rep'];
        $datasets[] = [
            'label' => $name,
            'data' => array_map(static fn($r) => (float) ($r[$name] ?? 0), $repRows),
            'backgroundColor' => YEAR_COLORS[$i % count(YEAR_COLORS)],
            'borderRadius' => [3, 3, 0, 0],
        ];
    }
    $chartConfigs['rep_monthly'] = [
        'type' => 'bar',
        'data' => ['labels' => $labels, 'datasets' => $datasets],
        'options' => [
            'plugins' => ['legend' => ['labels' => ['font' => ['size' => 12]]]],
            'scales' => [
                'x' => ['grid' => ['display' => false]],
                'y' => [
                    'grid' => ['color' => '#1E2D45', 'borderDash' => [3, 3]],
                    'ticks' => ['font' => ['size' => 10]],
                ],
            ],
        ],
    ];
}

if ($view === 'activity' && $actD) {
    $byHourF = array_values(array_filter($actD['byHour'], static fn($h) => $h['hour'] >= 6 && $h['hour'] <= 19));
    $maxInvAll = max(array_map(static fn($x) => (int) $x['invoices'], $actD['byHour'])) ?: 1;
    $fillsH = array_map(static function ($h) use ($C, $maxInvAll) {
        return ((int) $h['invoices'] === $maxInvAll && $maxInvAll > 0) ? $C['gold'] : $C['teal'];
    }, $byHourF);
    $chartConfigs['act_hour_bar'] = [
        'type' => 'bar',
        'data' => [
            'labels' => array_column($byHourF, 'label'),
            'datasets' => [
                [
                    'label' => 'Invoices',
                    'data' => array_column($byHourF, 'invoices'),
                    'backgroundColor' => $fillsH,
                    'borderRadius' => [3, 3, 0, 0],
                ]
            ],
        ],
        'options' => [
            'plugins' => ['legend' => ['display' => false]],
            'scales' => [
                'x' => ['grid' => ['display' => false], 'ticks' => ['font' => ['size' => 10]]],
                'y' => ['grid' => ['color' => '#1E2D45', 'borderDash' => [3, 3]], 'ticks' => ['font' => ['size' => 10]]],
            ],
        ],
    ];
}

if ($view === 'activity' && $areaD && ($areaD['byArea'] ?? []) !== []) {
    $a8 = array_slice($areaD['byArea'], 0, 8);
    $fillsA = [];
    foreach ($a8 as $i => $_) {
        $fillsA[] = YEAR_COLORS[$i % count(YEAR_COLORS)];
    }
    $chartConfigs['area_rev'] = [
        'type' => 'bar',
        'data' => [
            'labels' => array_column($a8, 'area'),
            'datasets' => [
                [
                    'label' => 'Revenue',
                    'data' => array_column($a8, 'revenue'),
                    'backgroundColor' => $fillsA,
                    'borderRadius' => [0, 4, 4, 0],
                ]
            ],
        ],
        'options' => [
            'indexAxis' => 'y',
            'plugins' => ['legend' => ['display' => false]],
            'scales' => [
                'x' => [
                    'ticks' => ['font' => ['size' => 10]],
                    'grid' => ['color' => '#1E2D45', 'borderDash' => [3, 3]],
                ],
                'y' => [
                    'ticks' => ['font' => ['size' => 11], 'color' => '#E2E8F0'],
                    'grid' => ['display' => false],
                ],
            ],
        ],
    ];
    $chartConfigs['area_units'] = [
        'type' => 'bar',
        'data' => [
            'labels' => array_column($a8, 'area'),
            'datasets' => [
                [
                    'label' => 'Units',
                    'data' => array_column($a8, 'units'),
                    'backgroundColor' => $fillsA,
                    'borderRadius' => [0, 4, 4, 0],
                ]
            ],
        ],
        'options' => [
            'indexAxis' => 'y',
            'plugins' => ['legend' => ['display' => false]],
            'scales' => [
                'x' => ['ticks' => ['font' => ['size' => 10]], 'grid' => ['color' => '#1E2D45', 'borderDash' => [3, 3]]],
                'y' => [
                    'ticks' => ['font' => ['size' => 11], 'color' => '#E2E8F0'],
                    'grid' => ['display' => false],
                ],
            ],
        ],
    ];
}

require __DIR__ . '/dashboard_view.php';
