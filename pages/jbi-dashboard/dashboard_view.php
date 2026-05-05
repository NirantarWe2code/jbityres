<?php
/**
 * Main dashboard markup (converted from TyreDashboard JSX).
 *
 * @var array<string,string> $C
 * @var array<int,array>     $yearData
 * @var list<int>            $years
 * @var list<int>            $yearsAll
 * @var list<int>            $activeYears
 * @var list<int>            $shownYears
 * @var string               $view
 * @var string               $quickFilter
 * @var ?int                 $latestYear
 * @var ?int                 $prevYear
 * @var ?array               $latestData
 * @var ?array               $prevData
 * @var list<array>          $monthlyComparison
 * @var list<array>          $brandData
 * @var list<string>         $topBrands
 * @var int                  $brandChartYearEff
 * @var list<array>          $brandMonthlyUnits
 * @var list<array>          $brandMonthlyPivot
 * @var array<string,float>  $pivotColTotals
 * @var int                  $custCurYear
 * @var ?int                 $custCmpYear
 * @var ?array               $custCurData
 * @var ?array               $custCmpData
 * @var ?array               $custAnalytics
 * @var string               $custTab
 * @var bool                 $hasData
 * @var string               $storageMsg
 * @var string               $errorFlash
 * @var string               $dbError
 * @var string               $salesDataLoadHint
 * @var int                  $actYr
 * @var int                  $areaYr
 * @var ?array               $actD
 * @var ?array               $areaD
 * @var array<string,array>  $chartConfigs
 * @var string               $stylesSans
 * @var string               $stylesMono
 */

function chart_block(string $id, int $heightPx): void
{
    global $chartConfigs;
    if (empty($chartConfigs[$id])) {
        return;
    }
    echo '<div style="width:100%;height:' . (int) $heightPx . 'px;position:relative">';
    echo '<canvas class="dashboard-chart" data-chart-id="' . h($id) . '"></canvas>';
    echo '</div>';
    echo '<script type="application/json" class="chart-data" data-chart-id="' . h($id) . '">';
    echo json_encode($chartConfigs[$id], JSON_UNESCAPED_SLASHES);
    echo '</script>';
}

$yoyRev = yoy_trend($latestData, $prevData, 'revenue');
$yoyProfit = yoy_trend($latestData, $prevData, 'profit');
$yoyUnits = yoy_trend($latestData, $prevData, 'units');

$retainedNameSet = [];
if ($custAnalytics) {
    foreach ($custAnalytics['retained'] as $rr) {
        $retainedNameSet[(string) $rr['customer']] = true;
    }
}

?>
<?php if ($dbError !== ''): ?>
  <div style="background:<?= h($C['rose']) ?>22;border:1px solid <?= h($C['rose']) ?>;border-radius:12px;padding:16px;margin-bottom:16px;color:<?= h($C['text']) ?>;font-size:13px">
    <strong>Database:</strong> <?= h($dbError) ?> — in phpMyAdmin create DB <code>tyre_dashboard</code>, import <code>database_sell_report_import.sql</code> (table <code>sales_data</code>), run <code>composer install</code>, and set <code>TYRE_DB_DSN</code> / <code>TYRE_DB_USER</code> / <code>TYRE_DB_PASS</code> if needed.
  </div>
<?php endif; ?>
<?php if (($salesDataLoadHint ?? '') !== ''): ?>
  <div style="background:<?= h($C['gold']) ?>22;border:1px solid <?= h($C['gold']) ?>;border-radius:12px;padding:16px;margin-bottom:16px;color:<?= h($C['text']) ?>;font-size:13px">
    <strong>Data load:</strong> <?= h($salesDataLoadHint) ?>
  </div>
<?php endif; ?>

<!-- Header -->
<div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:28px;flex-wrap:wrap;gap:16px">
  <div>
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:4px">
      <div>
        <div style="font-size:20px;font-weight:800;letter-spacing:-0.02em;color:<?= h($C['text']) ?>">Dashboard</div>
      </div>
    </div>
  </div>
  <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
    <?php
    if ($hasData && isset($yearsAll) && $yearsAll !== []):
        $yAllPicker = array_values(array_map('intval', $yearsAll));
        sort($yAllPicker, SORT_NUMERIC);
        $actPicker = array_values(array_map('intval', $activeYears));
        sort($actPicker, SORT_NUMERIC);
        $pickerAllOn = $actPicker !== [] && $actPicker === $yAllPicker;
        if ($pickerAllOn) {
            $pickerBtnLabel = 'All years (' . count($actPicker) . ')';
        } elseif ($actPicker === []) {
            $pickerBtnLabel = 'Select years';
        } else {
            $pickerBtnLabel = count($actPicker) <= 3
                ? implode(', ', $actPicker)
                : implode(', ', array_slice($actPicker, 0, 2)) . ' +' . (count($actPicker) - 2);
        }
        ?>
      <form method="post" action="process.php" style="margin:0;display:flex;align-items:flex-start;gap:10px;flex-wrap:wrap">
        <input type="hidden" name="action" value="set_selected_years">
        <span style="font-size:12px;color:<?= h($C['text']) ?>;font-weight:700;white-space:nowrap;padding-top:8px">Years (compare)</span>
        <div class="jbi-year-picker" style="position:relative">
          <button type="button" class="jbi-year-picker__toggle" aria-expanded="false" aria-haspopup="true" style="min-width:160px;text-align:left;background:<?= h($C['surface']) ?>;color:<?= h($C['text']) ?>;border:1px solid <?= h($C['border']) ?>;border-radius:8px;padding:8px 32px 8px 12px;cursor:pointer;font-size:13px;font-weight:700;<?= h($stylesMono) ?>;position:relative">
            <?= h($pickerBtnLabel) ?>
            <span style="position:absolute;right:10px;top:50%;transform:translateY(-50%);font-size:10px;color:<?= h($C['text']) ?>;font-weight:700">▾</span>
          </button>
          <div class="jbi-year-picker__panel" hidden style="position:absolute;left:0;top:calc(100% + 6px);min-width:220px;max-height:280px;overflow-y:auto;background:<?= h($C['surface']) ?>;border:1px solid <?= h($C['border']) ?>;border-radius:10px;box-shadow:0 12px 40px rgba(0,0,0,0.45);z-index:400;padding:8px 0">
            <?php foreach ($yAllPicker as $yy): ?>
              <label class="jbi-year-picker__row" style="display:flex;align-items:center;gap:10px;padding:8px 14px;cursor:pointer;font-size:13px;color:<?= h($C['text']) ?>;<?= h($stylesMono) ?>;user-select:none">
                <input type="checkbox" name="years[]" value="<?= (int) $yy ?>" <?= in_array((int) $yy, $activeYears, true) ? 'checked' : '' ?> style="width:16px;height:16px;accent-color:<?= h($C['teal']) ?>;cursor:pointer">
                <span><?= (int) $yy ?></span>
              </label>
            <?php endforeach; ?>
          </div>
        </div>
        <button type="submit" style="background:<?= h($C['teal']) ?>;color:<?= h($C['text']) ?>;border:none;border-radius:8px;padding:8px 14px;cursor:pointer;font-size:12px;font-weight:700;white-space:nowrap;margin-top:2px">Apply</button>
        <span style="font-size:10px;font-weight:700;color:<?= h($C['text']) ?>;max-width:200px;line-height:1.35;padding-top:4px">Tick years to compare, then Apply. No ticks + Apply = show all years.</span>
      </form>
      <style>
        .jbi-year-picker__panel .jbi-year-picker__row:hover { background: <?= h($C['bg']) ?>33; }
      </style>
      <script>
      (function () {
        document.addEventListener('DOMContentLoaded', function () {
          var root = document.querySelector('.jbi-year-picker');
          if (!root) return;
          var btn = root.querySelector('.jbi-year-picker__toggle');
          var panel = root.querySelector('.jbi-year-picker__panel');
          if (!btn || !panel) return;
          function closePicker() {
            panel.setAttribute('hidden', '');
            btn.setAttribute('aria-expanded', 'false');
          }
          function openPicker() {
            panel.removeAttribute('hidden');
            btn.setAttribute('aria-expanded', 'true');
          }
          function isOpen() {
            return !panel.hasAttribute('hidden');
          }
          btn.addEventListener('click', function (e) {
            e.preventDefault();
            if (isOpen()) closePicker(); else openPicker();
          });
          document.addEventListener('click', function (e) {
            if (!root.contains(e.target)) closePicker();
          });
          document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closePicker();
          });
        });
      })();
      </script>
    <?php endif; ?>
    <?php if ($hasData): ?>
      <div style="display:flex;align-items:center;gap:6px;margin-left:6px">
        <form method="post" action="process.php" style="margin:0">
          <input type="hidden" name="action" value="set_quick_filter">
          <input type="hidden" name="quick_filter" value="1m">
          <button type="submit" style="background:<?= $quickFilter === '1m' ? h($C['teal']) : 'transparent' ?>;color:<?= h($C['text']) ?>;border:1px solid <?= $quickFilter === '1m' ? h($C['teal']) : h($C['dim']) ?>;border-radius:999px;padding:5px 10px;cursor:pointer;font-size:11px;font-weight:700;<?= h($stylesMono) ?>">1 Month</button>
        </form>
        <form method="post" action="process.php" style="margin:0">
          <input type="hidden" name="action" value="set_quick_filter">
          <input type="hidden" name="quick_filter" value="3m">
          <button type="submit" style="background:<?= $quickFilter === '3m' ? h($C['teal']) : 'transparent' ?>;color:<?= h($C['text']) ?>;border:1px solid <?= $quickFilter === '3m' ? h($C['teal']) : h($C['dim']) ?>;border-radius:999px;padding:5px 10px;cursor:pointer;font-size:11px;font-weight:700;<?= h($stylesMono) ?>">3 Months</button>
        </form>
        <form method="post" action="process.php" style="margin:0">
          <input type="hidden" name="action" value="set_quick_filter">
          <input type="hidden" name="quick_filter" value="6m">
          <button type="submit" style="background:<?= $quickFilter === '6m' ? h($C['teal']) : 'transparent' ?>;color:<?= h($C['text']) ?>;border:1px solid <?= $quickFilter === '6m' ? h($C['teal']) : h($C['dim']) ?>;border-radius:999px;padding:5px 10px;cursor:pointer;font-size:11px;font-weight:700;<?= h($stylesMono) ?>">6 Months</button>
        </form>
        <form method="post" action="process.php" style="margin:0">
          <input type="hidden" name="action" value="set_quick_filter">
          <input type="hidden" name="quick_filter" value="year">
          <button type="submit" style="background:<?= $quickFilter === 'year' ? h($C['teal']) : 'transparent' ?>;color:<?= h($C['text']) ?>;border:1px solid <?= $quickFilter === 'year' ? h($C['teal']) : h($C['dim']) ?>;border-radius:999px;padding:5px 10px;cursor:pointer;font-size:11px;font-weight:700;<?= h($stylesMono) ?>">Year</button>
        </form>
      </div>
    <?php endif; ?>
    <?php if ($storageMsg !== ''): ?>
      <span style="font-size:11px;color:<?= strpos($storageMsg, '✓') === 0 ? h($C['green']) : h($C['gold']) ?>;<?= h($stylesMono) ?>"><?= h($storageMsg) ?></span>
    <?php endif; ?>
  </div>
</div>

<?php if ($errorFlash !== ''): ?>
  <div style="color:<?= h($C['rose']) ?>;font-size:12px;margin-bottom:12px"><?= h($errorFlash) ?></div>
<?php endif; ?>

<?php if (!$hasData): ?>
  <div style="text-align:center;margin-top:120px">
    <div style="font-size:64px;margin-bottom:16px">📊</div>
    <div style="font-size:20px;font-weight:700;color:<?= h($C['text']) ?>;margin-bottom:8px">Upload your first Sell Report</div>
    <div style="font-size:14px;color:<?= h($C['muted']) ?>">Use the upload zone above to import .csv, .txt, or .tsv files.</div>
  </div>
<?php else: ?>

<!-- Nav -->
<div style="display:flex;gap:8px;margin-bottom:24px;flex-wrap:wrap">
  <?php foreach (
      [
          'overview' => '📊 Overview',
          'monthly' => '📅 Monthly Trends',
          'brands' => '🔖 Brands',
          'products' => '📦 Top Selling Product',
          'customers' => '🏆 Customers',
          'reps' => '👤 Sales Reps',
          'activity' => '⏱ Activity & Areas',
          'rawdata' => '🧾 Raw Rows (API)',
      ] as $vk => $lbl
  ): ?>
    <form method="post" action="process.php" style="margin:0">
      <input type="hidden" name="action" value="set_view">
      <input type="hidden" name="view" value="<?= h($vk) ?>">
      <button type="submit" style="background:<?= $view === $vk ? h($C['teal']) : 'transparent' ?>;color:<?= h($C['text']) ?>;border:1px solid <?= $view === $vk ? h($C['teal']) : h($C['dim']) ?>;border-radius:8px;padding:6px 16px;cursor:pointer;font-size:12px;font-weight:700;<?= h($stylesSans) ?>"><?= h($lbl) ?></button>
    </form>
  <?php endforeach; ?>
</div>

<?php if ($latestData): ?>
<!-- KPI row -->
<div style="display:flex;gap:16px;margin-bottom:28px;flex-wrap:wrap;align-items:stretch">
  <?php
    $kpi = function (string $title, ?string $tagline, string $value, ?string $sub, string $color, ?float $trend) use ($C, $stylesSans, $stylesMono) {
        echo '<div style="background:' . h($C['card']) . ';border:1px solid ' . h($C['border']) . ';border-radius:14px;padding:22px 22px 24px;flex:1 1 168px;min-width:168px;max-width:100%;border-top:3px solid ' . h($color) . ';display:flex;flex-direction:column;box-shadow:0 8px 24px rgba(0,0,0,0.18)">';
        echo '<div style="margin-bottom:12px;' . h($stylesSans) . '">';
        echo '<div style="font-size:14px;font-weight:600;color:#e2e8f0;letter-spacing:-0.01em;line-height:1.25">' . h($title) . '</div>';
        if ($tagline !== null && $tagline !== '') {
            echo '<div style="font-size:11px;font-weight:500;color:#94a3b8;margin-top:4px;line-height:1.35">' . h($tagline) . '</div>';
        }
        echo '</div>';
        echo '<div style="font-size:30px;font-weight:700;color:' . h($color) . ';' . h($stylesMono) . ';line-height:1.05;letter-spacing:-0.02em;margin-top:auto">' . $value . '</div>';
        if ($sub) {
            echo '<div style="font-size:13px;color:#cbd5e1;margin-top:10px;line-height:1.45;' . h($stylesSans) . '">' . h($sub) . '</div>';
        }
        if ($trend !== null) {
            $tc = $trend >= 0 ? $C['green'] : $C['rose'];
            echo '<div style="font-size:12px;margin-top:8px;color:' . h($tc) . ';font-weight:600;' . h($stylesMono) . '">' . ($trend >= 0 ? '▲' : '▼') . ' ' . number_format(abs($trend), 1) . '% vs previous year</div>';
        }
        echo '</div>';
    };
  $kpi('Revenue', 'Excluding GST · ' . (int) $latestYear, fmt_aud((float) $latestData['totals']['revenue']), 'Including GST ' . fmt_aud((float) $latestData['totals']['revenue'] * 1.1), $C['teal'], $yoyRev);
  $kpi('Gross profit', (string) (int) $latestYear, fmt_aud((float) $latestData['totals']['profit']), 'GP margin ' . fmt_pct((float) $latestData['totals']['margin']), $C['gold'], $yoyProfit);
  $kpi('Units sold', 'Total quantity', fmt_num((float) round($latestData['totals']['units'])), 'Tyre units in period', $C['blue'], $yoyUnits);
  $kpi('Active customers', 'Distinct businesses', (string) (int) $latestData['totals']['customers'], fmt_num((float) $latestData['totals']['invoices']) . ' invoices in period', $C['purple'], null);
  $inv = (float) $latestData['totals']['invoices'];
  $kpi('Avg invoice', 'Revenue ÷ invoices', fmt_aud($inv > 0 ? (float) $latestData['totals']['revenue'] / $inv : 0.0), 'Excluding GST', $C['green'], null);
  ?>
</div>
<?php endif; ?>

<?php if ($view === 'overview' && $latestData): ?>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
  <div style="background:<?= h($C['card']) ?>;border:1px solid <?= h($C['border']) ?>;border-radius:12px;padding:20px;grid-column:1/-1">
    <div style="margin-bottom:16px">
      <div style="font-size:13px;font-weight:700;color:<?= h($C['text']) ?>;letter-spacing:0.06em;text-transform:uppercase;<?= h($stylesSans) ?>">Monthly Revenue (ex-GST)</div>
      <div style="font-size:11px;color:<?= h($C['muted']) ?>;margin-top:2px;<?= h($stylesSans) ?>">All loaded years</div>
    </div>
    <?php chart_block('ov_monthly_rev', 260); ?>
  </div>
  <div style="background:<?= h($C['card']) ?>;border:1px solid <?= h($C['border']) ?>;border-radius:12px;padding:20px">
    <div style="margin-bottom:16px">
      <div style="font-size:13px;font-weight:700;color:<?= h($C['text']) ?>;letter-spacing:0.06em;text-transform:uppercase;<?= h($stylesSans) ?>">GP Margin % by Month</div>
    </div>
    <?php chart_block('ov_margin_line', 220); ?>
  </div>
  <div style="background:<?= h($C['card']) ?>;border:1px solid <?= h($C['border']) ?>;border-radius:12px;padding:20px">
    <div style="margin-bottom:16px">
      <div style="font-size:13px;font-weight:700;color:<?= h($C['text']) ?>;letter-spacing:0.06em;text-transform:uppercase;<?= h($stylesSans) ?>">Brand Revenue (<?= (int) $latestYear ?>)</div>
      <div style="font-size:11px;color:<?= h($C['muted']) ?>;margin-top:2px;<?= h($stylesSans) ?>">Top 8</div>
    </div>
    <?php chart_block('ov_brands_bar', 220); ?>
  </div>
</div>
<?php endif; ?>

<?php
// ---- MONTHLY ----
if ($view === 'monthly' && $latestData):
    ?>
<div style="display:grid;gap:20px">
  <div style="background:<?= h($C['card']) ?>;border:1px solid <?= h($C['border']) ?>;border-radius:12px;padding:20px">
    <div style="margin-bottom:16px">
      <div style="font-size:13px;font-weight:700;color:<?= h($C['text']) ?>;letter-spacing:0.06em;text-transform:uppercase">Revenue by Month — Year on Year</div>
      <div style="font-size:11px;color:<?= h($C['muted']) ?>;margin-top:2px">ex-GST</div>
    </div>
    <?php chart_block('mo_rev_yoy', 300); ?>
  </div>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
    <div style="background:<?= h($C['card']) ?>;border:1px solid <?= h($C['border']) ?>;border-radius:12px;padding:20px">
      <div style="margin-bottom:16px;font-size:13px;font-weight:700;color:<?= h($C['text']) ?>;letter-spacing:0.06em;text-transform:uppercase">Gross Profit by Month</div>
      <?php chart_block('mo_profit_bar', 240); ?>
    </div>
    <div style="background:<?= h($C['card']) ?>;border:1px solid <?= h($C['border']) ?>;border-radius:12px;padding:20px">
      <div style="margin-bottom:16px;font-size:13px;font-weight:700;color:<?= h($C['text']) ?>;letter-spacing:0.06em;text-transform:uppercase">Units Sold by Month</div>
      <?php chart_block('mo_units_bar', 240); ?>
    </div>
  </div>
  <div style="background:<?= h($C['card']) ?>;border:1px solid <?= h($C['border']) ?>;border-radius:12px;padding:20px">
    <div style="margin-bottom:16px">
      <div style="font-size:13px;font-weight:700;color:<?= h($C['text']) ?>;letter-spacing:0.06em;text-transform:uppercase">Monthly Detail — <?= (int) $latestYear ?></div>
    </div>
    <table style="width:100%;border-collapse:collapse;font-size:12px">
      <thead>
        <tr>
          <?php foreach (['Month', 'Revenue ex-GST', 'Revenue inc-GST', 'Units', 'Invoices', 'Gross Profit', 'GP Margin %', 'vs Prev Mth'] as $hcol): ?>
            <th style="text-align:right;padding:8px 12px;color:<?= h($C['muted']) ?>;border-bottom:1px solid <?= h($C['border']) ?>;font-weight:600;font-size:11px;letter-spacing:0.04em;white-space:nowrap"><?= h($hcol) ?></th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($latestData['monthly'] as $i => $m):
            $prev = $i > 0 ? $latestData['monthly'][$i - 1] : null;
            $chg = ($prev && ($prev['revenue'] ?? 0) > 0) ? ((($m['revenue'] - $prev['revenue']) / $prev['revenue']) * 100) : null;
            $bg = $i % 2 === 0 ? $C['surface'] : 'transparent';
            ?>
          <tr style="background:<?= h($bg) ?>">
            <td style="padding:9px 12px;color:<?= h($C['text']) ?>;font-weight:600;text-align:right;<?= h($stylesMono) ?>"><?= h((string) $m['month']) ?></td>
            <td style="padding:9px 12px;color:<?= h($C['teal']) ?>;text-align:right;<?= h($stylesMono) ?>"><?= h(fmt_audf((float) $m['revenue'])) ?></td>
            <td style="padding:9px 12px;color:<?= h($C['muted']) ?>;text-align:right;<?= h($stylesMono) ?>"><?= h(fmt_audf((float) $m['revenue'] * 1.1)) ?></td>
            <td style="padding:9px 12px;text-align:right;<?= h($stylesMono) ?>"><?= h(fmt_num((float) round($m['units']))) ?></td>
            <td style="padding:9px 12px;text-align:right;<?= h($stylesMono) ?>"><?= h(fmt_num((float) $m['invoices'])) ?></td>
            <td style="padding:9px 12px;color:<?= h($C['gold']) ?>;text-align:right;<?= h($stylesMono) ?>"><?= h(fmt_audf((float) $m['profit'])) ?></td>
            <?php $mc = (float) $m['margin'];
            $mcol = $mc < 8 ? $C['rose'] : ($mc >= 12 ? $C['green'] : $C['text']); ?>
            <td style="padding:9px 12px;text-align:right;<?= h($stylesMono) ?>;color:<?= h($mcol) ?>"><?= h(fmt_pct($mc)) ?></td>
            <?php $ccol = $chg === null ? $C['muted'] : ($chg >= 0 ? $C['green'] : $C['rose']); ?>
            <td style="padding:9px 12px;text-align:right;<?= h($stylesMono) ?>;color:<?= h($ccol) ?>"><?= $chg === null ? '—' : (($chg >= 0 ? '▲' : '▼') . ' ' . number_format(abs($chg), 1) . '%') ?></td>
          </tr>
        <?php endforeach; ?>
        <tr style="border-top:1px solid <?= h($C['teal']) ?>">
          <td style="padding:10px 12px;color:<?= h($C['teal']) ?>;font-weight:800;text-align:right;<?= h($stylesMono) ?>">TOTAL</td>
          <td style="padding:10px 12px;color:<?= h($C['teal']) ?>;font-weight:700;text-align:right;<?= h($stylesMono) ?>"><?= h(fmt_audf((float) $latestData['totals']['revenue'])) ?></td>
          <td style="padding:10px 12px;color:<?= h($C['muted']) ?>;font-weight:700;text-align:right;<?= h($stylesMono) ?>"><?= h(fmt_audf((float) $latestData['totals']['revenue'] * 1.1)) ?></td>
          <td style="padding:10px 12px;font-weight:700;text-align:right;<?= h($stylesMono) ?>"><?= h(fmt_num((float) round($latestData['totals']['units']))) ?></td>
          <td style="padding:10px 12px;font-weight:700;text-align:right;<?= h($stylesMono) ?>"><?= h(fmt_num((float) $latestData['totals']['invoices'])) ?></td>
          <td style="padding:10px 12px;color:<?= h($C['gold']) ?>;font-weight:700;text-align:right;<?= h($stylesMono) ?>"><?= h(fmt_audf((float) $latestData['totals']['profit'])) ?></td>
          <td style="padding:10px 12px;font-weight:700;text-align:right;<?= h($stylesMono) ?>"><?= h(fmt_pct((float) $latestData['totals']['margin'])) ?></td>
          <td style="padding:10px 12px;text-align:right">—</td>
        </tr>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php
require __DIR__ . '/dashboard_view_brands_customers.php';
require __DIR__ . '/dashboard_view_reps_activity.php';
?>

<?php if ($view === 'products' && $shownYears !== []):
    $focusYear = (int) ($productFocusYear ?? $shownYears[count($shownYears) - 1]);
    $focusProducts = array_slice($yearData[$focusYear]['products'] ?? [], 0, 20);
    $focusTop = $focusProducts[0] ?? null;
    $focusTotals = $yearData[$focusYear]['totals'] ?? [];
    $focusUnique = count($yearData[$focusYear]['products'] ?? []);
    $focusTopUnits = (float) ($focusTop['units'] ?? 0);
    $focusTopName = (string) ($focusTop['product'] ?? '—');
    $focusAvgPrice = $focusTopUnits > 0 ? (float) ($focusTop['revenue'] ?? 0) / $focusTopUnits : 0.0;
    $focusAvgGpUnit = $focusTopUnits > 0 ? (float) ($focusTop['profit'] ?? 0) / $focusTopUnits : 0.0;
    $productDetailMode = (string) ($_SESSION['product_detail_mode'] ?? 'sku');
    if (!in_array($productDetailMode, ['sku', 'tyresize'], true)) {
        $productDetailMode = 'sku';
    }
    $pdRows = $productDetailMode === 'tyresize'
        ? ($yearData[$focusYear]['productDetailTyreSize'] ?? [])
        : ($yearData[$focusYear]['productDetailSku'] ?? []);
    $pdBrandSet = [];
    foreach ($pdRows as $pd) {
        $bn = trim((string) ($pd['brand'] ?? ''));
        if ($bn !== '' && $bn !== '—') {
            $pdBrandSet[$bn] = true;
        }
    }
    $pdBrands = array_keys($pdBrandSet);
    natcasesort($pdBrands);
    $pdBrands = array_values($pdBrands);
    $pdCount = count($pdRows);
    $pdUnitLabel = $productDetailMode === 'tyresize' ? 'sizes' : 'SKUs';
    ?>
<div style="display:grid;gap:18px">
  <div style="display:flex;justify-content:flex-start;align-items:center;flex-wrap:wrap;gap:16px">
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
      <?php foreach ($shownYears as $y): ?>
        <form method="post" action="process.php" style="margin:0">
          <input type="hidden" name="action" value="set_product_year">
          <input type="hidden" name="year" value="<?= (int) $y ?>">
          <button type="submit" style="border:1px solid <?= (int) $y === $focusYear ? h($C['teal']) : h($C['dim']) ?>;border-radius:999px;padding:4px 10px;font-size:11px;<?= h($stylesMono) ?>;font-weight:700;color:<?= (int) $y === $focusYear ? h($C['teal']) : h($C['muted']) ?>;background:<?= (int) $y === $focusYear ? h($C['teal']) . '1A' : 'transparent' ?>;cursor:pointer"><?= (int) $y ?></button>
        </form>
      <?php endforeach; ?>
    </div>
    <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">
      <form method="post" action="process.php" style="margin:0">
        <input type="hidden" name="action" value="set_product_detail_mode">
        <input type="hidden" name="mode" value="sku">
        <button type="submit" style="border:1px solid <?= $productDetailMode === 'sku' ? h($C['teal']) : h($C['dim']) ?>;border-radius:8px;padding:6px 12px;font-size:11px;font-weight:700;cursor:pointer;<?= h($stylesSans) ?>;color:<?= $productDetailMode === 'sku' ? h($C['bg']) : h($C['muted']) ?>;background:<?= $productDetailMode === 'sku' ? h($C['teal']) : 'transparent' ?>">📦 By Product SKU</button>
      </form>
      <form method="post" action="process.php" style="margin:0">
        <input type="hidden" name="action" value="set_product_detail_mode">
        <input type="hidden" name="mode" value="tyresize">
        <button type="submit" style="border:1px solid <?= $productDetailMode === 'tyresize' ? h($C['teal']) : h($C['dim']) ?>;border-radius:8px;padding:6px 12px;font-size:11px;font-weight:700;cursor:pointer;<?= h($stylesSans) ?>;color:<?= $productDetailMode === 'tyresize' ? h($C['bg']) : h($C['muted']) ?>;background:<?= $productDetailMode === 'tyresize' ? h($C['teal']) : 'transparent' ?>">△ By Tyre Size</button>
      </form>
    </div>
  </div>

  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px">
    <div style="background:<?= h($C['card']) ?>;border:1px solid <?= h($C['border']) ?>;border-radius:12px;padding:14px">
      <div style="font-size:10px;color:<?= h($C['muted']) ?>;letter-spacing:0.08em;text-transform:uppercase">Unique SKUs</div>
      <div style="font-size:30px;font-weight:800;color:<?= h($C['teal']) ?>;<?= h($stylesMono) ?>"><?= h(fmt_num((float) $focusUnique)) ?></div>
    </div>
    <div style="background:<?= h($C['card']) ?>;border:1px solid <?= h($C['border']) ?>;border-radius:12px;padding:14px">
      <div style="font-size:10px;color:<?= h($C['muted']) ?>;letter-spacing:0.08em;text-transform:uppercase">Top SKU Units</div>
      <div style="font-size:30px;font-weight:800;color:<?= h($C['gold']) ?>;<?= h($stylesMono) ?>"><?= h(fmt_num($focusTopUnits)) ?></div>
    </div>
    <div style="background:<?= h($C['card']) ?>;border:1px solid <?= h($C['border']) ?>;border-radius:12px;padding:14px">
      <div style="font-size:10px;color:<?= h($C['muted']) ?>;letter-spacing:0.08em;text-transform:uppercase">Top SKU</div>
      <div style="font-size:13px;font-weight:700;color:<?= h($C['text']) ?>;margin-top:8px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= h($focusTopName) ?></div>
    </div>
    <div style="background:<?= h($C['card']) ?>;border:1px solid <?= h($C['border']) ?>;border-radius:12px;padding:14px">
      <div style="font-size:10px;color:<?= h($C['muted']) ?>;letter-spacing:0.08em;text-transform:uppercase">Avg Sell Price</div>
      <div style="font-size:30px;font-weight:800;color:<?= h($C['blue']) ?>;<?= h($stylesMono) ?>">A$<?= h(number_format($focusAvgPrice, 0)) ?></div>
    </div>
    <div style="background:<?= h($C['card']) ?>;border:1px solid <?= h($C['border']) ?>;border-radius:12px;padding:14px">
      <div style="font-size:10px;color:<?= h($C['muted']) ?>;letter-spacing:0.08em;text-transform:uppercase">Avg GP / Unit</div>
      <div style="font-size:30px;font-weight:800;color:<?= h($C['green']) ?>;<?= h($stylesMono) ?>">A$<?= h(number_format($focusAvgGpUnit, 0)) ?></div>
    </div>
  </div>

  <div style="background:<?= h($C['card']) ?>;border:1px solid <?= h($C['border']) ?>;border-radius:12px;padding:18px">
    <div style="font-size:13px;font-weight:700;color:<?= h($C['text']) ?>;letter-spacing:0.06em;text-transform:uppercase;margin-bottom:8px">Top 20 Products by Units Sold — <?= (int) $focusYear ?></div>
    <?php
    $maxUnits = 0.0;
    foreach ($focusProducts as $pp) {
        $maxUnits = max($maxUnits, (float) ($pp['units'] ?? 0));
    }
    ?>
    <div style="display:grid;gap:10px;margin-top:10px">
      <?php foreach ($focusProducts as $i => $p):
          $u = (float) ($p['units'] ?? 0);
          $mar = (float) ($p['margin'] ?? 0);
          $barW = $maxUnits > 0 ? max(2.0, min(100.0, ($u / $maxUnits) * 100.0)) : 0.0;
          $barColor = $mar < 8 ? $C['gold'] : $C['teal'];
          ?>
        <div style="display:grid;grid-template-columns:minmax(140px,220px) 1fr auto;gap:10px;align-items:center">
          <div style="font-size:11px;color:<?= h($C['text']) ?>;<?= h($stylesMono) ?>;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= ($i + 1) ?>. <?= h((string) ($p['product'] ?? '')) ?></div>
          <div style="height:14px;background:<?= h($C['bg']) ?>44;border:1px solid <?= h($C['border']) ?>;border-radius:999px;overflow:hidden">
            <div style="width:<?= h(number_format($barW, 2, '.', '')) ?>%;height:100%;background:<?= h($barColor) ?>;border-radius:999px"></div>
          </div>
          <div style="font-size:11px;color:<?= h($C['text']) ?>;<?= h($stylesMono) ?>;font-weight:700;min-width:70px;text-align:right"><?= h(fmt_num($u)) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div id="jbi-product-detail" style="background:<?= h($C['card']) ?>;border:1px solid <?= h($C['border']) ?>;border-radius:12px;padding:18px">
    <div style="margin-bottom:6px">
      <div style="font-size:13px;font-weight:700;color:<?= h($C['text']) ?>;letter-spacing:0.06em;text-transform:uppercase">Product Detail — <?= (int) $focusYear ?></div>
      <div style="font-size:11px;color:<?= h($C['muted']) ?>;margin-top:4px"><?= h((string) $pdCount) ?> <?= h($pdUnitLabel) ?> · sorted by units</div>
    </div>
    <div style="display:flex;flex-direction:column;gap:10px;margin-top:14px">
      <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
        <span style="font-size:14px;color:<?= h($C['muted']) ?>">🔍</span>
        <input type="search" id="pd_search" placeholder="Search product.." autocomplete="off" style="box-sizing:border-box;width:min(100%,360px);max-width:360px;flex:0 0 auto;background:<?= h($C['surface']) ?>;color:<?= h($C['text']) ?>;border:1px solid <?= h($C['border']) ?>;border-radius:999px;padding:8px 14px;font-size:12px;<?= h($stylesSans) ?>">
      </div>
      <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
        <label for="pd_brand_select" style="font-size:12px;color:<?= h($C['muted']) ?>;white-space:nowrap;<?= h($stylesSans) ?>">Brand</label>
        <select id="pd_brand_select" style="width:min(100%,420px);max-width:420px;background:<?= h($C['surface']) ?>;color:<?= h($C['text']) ?>;border:1px solid <?= h($C['border']) ?>;border-radius:8px;padding:8px 10px;font-size:12px;<?= h($stylesSans) ?>;cursor:pointer">
          <option value="">All</option>
          <?php foreach ($pdBrands as $pillBrand): ?>
            <option value="<?= h(strtolower((string) $pillBrand)) ?>"><?= h($pillBrand) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-top:12px">
      <div id="pd_page_meta" style="font-size:11px;color:<?= h($C['muted']) ?>;<?= h($stylesSans) ?>"></div>
      <div style="display:flex;align-items:center;gap:6px">
        <button type="button" id="pd_prev" style="background:transparent;color:<?= h($C['text']) ?>;border:1px solid <?= h($C['border']) ?>;border-radius:8px;padding:6px 12px;cursor:pointer;font-size:11px;font-weight:600;<?= h($stylesSans) ?>">Prev</button>
        <button type="button" id="pd_next" style="background:transparent;color:<?= h($C['text']) ?>;border:1px solid <?= h($C['border']) ?>;border-radius:8px;padding:6px 12px;cursor:pointer;font-size:11px;font-weight:600;<?= h($stylesSans) ?>">Next</button>
      </div>
    </div>
    <div style="overflow-x:auto;margin-top:10px;max-width:100%">
      <table id="pd_table" style="width:100%;border-collapse:collapse;font-size:12px;min-width:920px">
        <thead>
          <tr>
            <?php foreach (['#', 'Brand', 'Product', 'Units', 'Avg Price', 'Revenue', 'GP Margin', 'Invoices', 'Customers'] as $hcol): ?>
              <th style="text-align:<?= in_array($hcol, ['#', 'Brand', 'Product'], true) ? 'left' : 'right' ?>;padding:8px 12px;color:<?= h($C['muted']) ?>;border-bottom:1px solid <?= h($C['border']) ?>;font-weight:600;font-size:11px;letter-spacing:0.04em;white-space:nowrap"><?= h($hcol) ?></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody id="pd_tbody"></tbody>
      </table>
    </div>
  </div>
  <script type="application/json" id="pd-json-data"><?= json_encode($pdRows, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_INVALID_UTF8_SUBSTITUTE) ?></script>
  <script>
  (function () {
    var root = document.getElementById('jbi-product-detail');
    var jsonEl = document.getElementById('pd-json-data');
    var tbody = document.getElementById('pd_tbody');
    var qIn = document.getElementById('pd_search');
    var brandSel = document.getElementById('pd_brand_select');
    var meta = document.getElementById('pd_page_meta');
    var btnPrev = document.getElementById('pd_prev');
    var btnNext = document.getElementById('pd_next');
    if (!root || !jsonEl || !tbody) return;
    var PD_PER = 20;
    var C = {
      surface: '<?= h($C['surface']) ?>',
      transparent: 'transparent',
      text: '<?= h($C['text']) ?>',
      muted: '<?= h($C['muted']) ?>',
      teal: '<?= h($C['teal']) ?>',
      gold: '<?= h($C['gold']) ?>',
      green: '<?= h($C['green']) ?>',
      rose: '<?= h($C['rose']) ?>',
      border: '<?= h($C['border']) ?>'
    };
    var monoFF = "'Courier New', Courier, monospace";
    var rows;
    try {
      rows = JSON.parse(jsonEl.textContent || '[]');
      if (!Array.isArray(rows)) rows = [];
    } catch (e) {
      rows = [];
    }
    var page = 1;
    function norm(s) { return (String(s || '')).toLowerCase().trim(); }
    function rowHay(r) {
      return norm(r.brand) + ' ' + norm(r.product);
    }
    function filtered() {
      var needle = norm(qIn ? qIn.value : '');
      var bSel = brandSel ? norm(brandSel.value) : '';
      return rows.filter(function (r) {
        var b = norm(r.brand);
        var okB = !bSel || b === bSel;
        var okQ = !needle || rowHay(r).indexOf(needle) !== -1;
        return okB && okQ;
      });
    }
    function fmtNum(n) {
      var x = Math.round(Number(n) || 0);
      return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }
    function fmtAud(n) {
      return 'A$' + fmtNum(Math.round(Number(n) || 0));
    }
    function fmtPct(n) {
      return (Math.round((Number(n) || 0) * 10) / 10).toFixed(1) + '%';
    }
    function marginColor(m) {
      m = Number(m) || 0;
      if (m < 8) return C.gold;
      if (m >= 12) return C.green;
      return C.text;
    }
    function render() {
      var list = filtered();
      var total = list.length;
      var pages = Math.max(1, Math.ceil(total / PD_PER));
      if (page > pages) page = pages;
      if (page < 1) page = 1;
      var start = (page - 1) * PD_PER;
      var slice = list.slice(start, start + PD_PER);
      var end = start + slice.length;
      if (meta) {
        meta.textContent = total === 0
          ? 'No rows match · Page 1 of 1'
          : 'Page ' + page + ' of ' + pages + ' · Showing ' + (start + 1) + '–' + end + ' of ' + total;
      }
      if (btnPrev) {
        btnPrev.disabled = page <= 1 || total === 0;
        btnPrev.style.opacity = btnPrev.disabled ? '0.45' : '1';
      }
      if (btnNext) {
        btnNext.disabled = page >= pages || total === 0;
        btnNext.style.opacity = btnNext.disabled ? '0.45' : '1';
      }
      tbody.textContent = '';
      if (slice.length === 0) {
        var tr0 = document.createElement('tr');
        var td0 = document.createElement('td');
        td0.colSpan = 9;
        td0.style.padding = '20px 12px';
        td0.style.color = C.muted;
        td0.style.textAlign = 'center';
        td0.textContent = 'No matching products.';
        tr0.appendChild(td0);
        tbody.appendChild(tr0);
        return;
      }
      slice.forEach(function (r, i) {
        var idx = start + i + 1;
        var u = Number(r.units) || 0;
        var rev = Number(r.revenue) || 0;
        var margin = Number(r.margin) || 0;
        var avgP = Number(r.avg_price) || 0;
        var inv = Number(r.invoices) || 0;
        var cust = Number(r.customers) || 0;
        var bg = (start + i) % 2 === 0 ? C.surface : C.transparent;
        var tr = document.createElement('tr');
        tr.style.background = bg;
        function td(txt, opt) {
          opt = opt || {};
          var t = document.createElement('td');
          t.textContent = txt;
          t.style.padding = '9px 12px';
          if (opt.mono) t.style.fontFamily = monoFF;
          if (opt.color) t.style.color = opt.color;
          if (opt.align) t.style.textAlign = opt.align;
          if (opt.weight) t.style.fontWeight = opt.weight;
          if (opt.maxW) {
            t.style.maxWidth = opt.maxW;
            t.style.whiteSpace = 'nowrap';
            t.style.overflow = 'hidden';
            t.style.textOverflow = 'ellipsis';
          }
          tr.appendChild(t);
        }
        td(String(idx), { mono: true, color: C.muted });
        td(String(r.brand != null ? r.brand : ''), { color: C.text, weight: '600' });
        td(String(r.product != null ? r.product : ''), { color: C.text, maxW: '340px' });
        td(fmtNum(u), { mono: true, align: 'right', color: C.teal, weight: '700' });
        td('A$' + fmtNum(Math.round(avgP)), { mono: true, align: 'right' });
        td(fmtAud(rev), { mono: true, align: 'right', color: C.gold });
        td(fmtPct(margin), { mono: true, align: 'right', weight: '600', color: marginColor(margin) });
        td(fmtNum(inv), { mono: true, align: 'right' });
        td(fmtNum(cust), { mono: true, align: 'right' });
        tbody.appendChild(tr);
      });
    }
    function resetPage() { page = 1; render(); }
    if (qIn) qIn.addEventListener('input', resetPage);
    if (brandSel) brandSel.addEventListener('change', resetPage);
    if (btnPrev) btnPrev.addEventListener('click', function () {
      if (page > 1) { page--; render(); }
    });
    if (btnNext) btnNext.addEventListener('click', function () {
      var list = filtered();
      var pages = Math.max(1, Math.ceil(list.length / PD_PER));
      if (page < pages) { page++; render(); }
    });
    render();
  })();
  </script>

  <?php if (count($shownYears) > 1): ?>
  <div style="background:<?= h($C['card']) ?>;border:1px solid <?= h($C['border']) ?>;border-radius:12px;padding:18px">
    <div style="font-size:13px;font-weight:700;color:<?= h($C['text']) ?>;letter-spacing:0.06em;text-transform:uppercase;margin-bottom:8px">Top Selling Product Comparison (Selected Years)</div>
    <div style="overflow-x:auto">
      <table style="width:100%;border-collapse:collapse;font-size:12px;min-width:900px">
        <thead>
          <tr>
            <th style="text-align:left;padding:8px 10px;color:<?= h($C['muted']) ?>;border-bottom:1px solid <?= h($C['border']) ?>">#</th>
            <th style="text-align:left;padding:8px 10px;color:<?= h($C['muted']) ?>;border-bottom:1px solid <?= h($C['border']) ?>">Product</th>
            <?php foreach ($shownYears as $y): ?>
              <th style="text-align:right;padding:8px 10px;color:<?= h($C['muted']) ?>;border-bottom:1px solid <?= h($C['border']) ?>"><?= (int) $y ?> Units</th>
            <?php endforeach; ?>
            <th style="text-align:right;padding:8px 10px;color:<?= h($C['teal']) ?>;border-bottom:1px solid <?= h($C['border']) ?>">Total Units</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach (array_slice($productCompareRows, 0, 20) as $i => $row):
              $bg = $i % 2 === 0 ? $C['surface'] : 'transparent';
              ?>
            <tr style="background:<?= h($bg) ?>">
              <td style="padding:8px 10px;color:<?= h($C['muted']) ?>;<?= h($stylesMono) ?>"><?= $i + 1 ?></td>
              <td style="padding:8px 10px;color:<?= h($C['text']) ?>;font-weight:600;max-width:320px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= h((string) ($row['product'] ?? '')) ?></td>
              <?php foreach ($shownYears as $y):
                  $u = (float) ($row['units_' . $y] ?? 0); ?>
                <td style="padding:8px 10px;text-align:right;<?= h($stylesMono) ?>;color:<?= $u > 0 ? h($C['text']) : h($C['dim']) ?>"><?= $u > 0 ? h(fmt_num($u)) : '—' ?></td>
              <?php endforeach; ?>
              <td style="padding:8px 10px;text-align:right;color:<?= h($C['teal']) ?>;<?= h($stylesMono) ?>;font-weight:700"><?= h(fmt_num((float) ($row['total_units'] ?? 0))) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($view === 'rawdata' && $years !== []): ?>
<div style="background:<?= h($C['card']) ?>;border:1px solid <?= h($C['border']) ?>;border-radius:12px;padding:20px">
  <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:14px">
    <div>
      <div style="font-size:13px;font-weight:700;color:<?= h($C['text']) ?>;letter-spacing:0.06em;text-transform:uppercase">Raw Sales Rows (from API)</div>
      <div style="font-size:11px;color:<?= h($C['muted']) ?>;margin-top:2px">Source fields, not aggregated</div>
    </div>
    <div style="display:flex;align-items:center;gap:8px">
      <label for="raw_year" style="font-size:12px;color:<?= h($C['muted']) ?>">Year</label>
      <select id="raw_year" style="background:<?= h($C['surface']) ?>;color:<?= h($C['text']) ?>;border:1px solid <?= h($C['border']) ?>;border-radius:8px;padding:6px 8px">
        <?php foreach (array_reverse($years) as $yy): ?>
          <option value="<?= (int) $yy ?>" <?= ((int) $yy === (int) $latestYear) ? 'selected' : '' ?>><?= (int) $yy ?></option>
        <?php endforeach; ?>
      </select>
      <button id="raw_load_btn" type="button" style="background:<?= h($C['teal']) ?>;color:<?= h($C['bg']) ?>;border:none;border-radius:8px;padding:6px 10px;cursor:pointer;font-weight:700">Load</button>
    </div>
  </div>

  <div id="raw_rows_meta" style="font-size:12px;color:<?= h($C['muted']) ?>;margin-bottom:10px"></div>
  <div id="raw_rows_error" style="font-size:12px;color:<?= h($C['rose']) ?>;margin-bottom:10px;display:none"></div>

  <div style="overflow:auto;max-height:520px;border:1px solid <?= h($C['border']) ?>;border-radius:10px">
    <table id="raw_rows_table" style="width:100%;border-collapse:collapse;font-size:12px;min-width:1280px">
      <thead style="position:sticky;top:0;background:<?= h($C['surface']) ?>;z-index:1">
        <tr>
          <?php foreach (['id', 'Dated', 'Business_Name', 'Sales_Rep', 'Invoice_Num', 'product', 'Delivery_Profile', 'Quantity', 'Unit_Price', 'Purchase_Price', 'imported_at'] as $hcol): ?>
            <th style="text-align:left;padding:8px 10px;color:<?= h($C['muted']) ?>;border-bottom:1px solid <?= h($C['border']) ?>;font-size:11px;white-space:nowrap"><?= h($hcol) ?></th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody id="raw_rows_tbody"></tbody>
    </table>
  </div>

  <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-top:10px">
    <div style="display:flex;align-items:center;gap:8px">
      <label for="raw_per_page" style="font-size:12px;color:<?= h($C['muted']) ?>">Per page</label>
      <select id="raw_per_page" style="background:<?= h($C['surface']) ?>;color:<?= h($C['text']) ?>;border:1px solid <?= h($C['border']) ?>;border-radius:8px;padding:5px 8px;font-size:12px">
        <option value="50">50</option>
        <option value="100" selected>100</option>
        <option value="200">200</option>
        <option value="500">500</option>
      </select>
    </div>
    <div id="raw_page_meta" style="font-size:12px;color:<?= h($C['muted']) ?>;text-align:center"></div>
  </div>
  <div style="display:flex;flex-wrap:wrap;align-items:center;justify-content:center;gap:6px;margin-top:10px">
    <button id="raw_first_btn" type="button" style="background:transparent;color:<?= h($C['text']) ?>;border:1px solid <?= h($C['border']) ?>;border-radius:8px;padding:6px 10px;cursor:pointer;font-size:11px;font-weight:600">First</button>
    <button id="raw_prev_btn" type="button" style="background:transparent;color:<?= h($C['text']) ?>;border:1px solid <?= h($C['border']) ?>;border-radius:8px;padding:6px 10px;cursor:pointer;font-size:11px;font-weight:600">Prev</button>
    <div id="raw_page_numbers" style="display:flex;flex-wrap:wrap;align-items:center;justify-content:center;gap:4px;max-width:100%"></div>
    <button id="raw_next_btn" type="button" style="background:transparent;color:<?= h($C['text']) ?>;border:1px solid <?= h($C['border']) ?>;border-radius:8px;padding:6px 10px;cursor:pointer;font-size:11px;font-weight:600">Next</button>
    <button id="raw_last_btn" type="button" style="background:transparent;color:<?= h($C['text']) ?>;border:1px solid <?= h($C['border']) ?>;border-radius:8px;padding:6px 10px;cursor:pointer;font-size:11px;font-weight:600">Last</button>
  </div>
</div>
<script>
(function () {
  var apiUrl = <?= json_encode(BASE_URL . '/pages/jbi-dashboard/raw_rows_api.php', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  var rawLimit = 100;
  var rawPage = 1;
  var loading = false;
  var rawCachedTotal = 0;
  var rawCachedYear = 0;
  var btnBase = 'border-radius:8px;padding:6px 10px;cursor:pointer;font-size:11px;font-weight:700;min-width:36px;border:1px solid <?= h($C['border']) ?>';
  var btnActive = 'background:<?= h($C['teal']) ?>;color:<?= h($C['bg']) ?>;border-color:<?= h($C['teal']) ?>';
  var btnIdle = 'background:transparent;color:<?= h($C['text']) ?>';

  function $(id) { return document.getElementById(id); }

  function esc(s) {
    if (s === null || s === undefined) return '';
    var d = document.createElement('div');
    d.textContent = String(s);
    return d.innerHTML;
  }

  function setErr(msg) {
    var el = $('raw_rows_error');
    var meta = $('raw_rows_meta');
    if (!el) return;
    if (msg) {
      el.textContent = msg;
      el.style.display = 'block';
    } else {
      el.textContent = '';
      el.style.display = 'none';
    }
    if (meta && msg) meta.textContent = '';
  }

  /** @return {(number|string)[]} */
  function visiblePageLabels(cur, totalP) {
    if (totalP <= 1) return [1];
    var delta = 2;
    var range = [];
    var i;
    for (i = 1; i <= totalP; i++) {
      if (i === 1 || i === totalP || (i >= cur - delta && i <= cur + delta)) {
        range.push(i);
      }
    }
    var out = [];
    var last = 0;
    for (i = 0; i < range.length; i++) {
      var p = range[i];
      if (last) {
        if (p - last === 2) out.push(last + 1);
        else if (p - last > 2) out.push('…');
      }
      out.push(p);
      last = p;
    }
    return out;
  }

  function renderPageButtons(total, limit, page) {
    var wrap = $('raw_page_numbers');
    if (!wrap) return;
    var totalPages = Math.max(1, Math.ceil(total / limit));
    if (total === 0) {
      wrap.innerHTML = '';
      return;
    }
    var labels = visiblePageLabels(page, totalPages);
    var parts = labels.map(function (lab) {
      if (lab === '…') {
        return '<span style="color:<?= h($C['muted']) ?>;padding:0 4px;font-size:12px">…</span>';
      }
      var isCur = lab === page;
      var st = btnBase + ';' + (isCur ? btnActive : btnIdle);
      return '<button type="button" class="raw-page-num" data-raw-page="' + lab + '" style="' + st + '"' + (isCur ? ' aria-current="page"' : '') + '>' + lab + '</button>';
    });
    wrap.innerHTML = parts.join('');
  }

  function loadRaw(resetPage) {
    var tbody = $('raw_rows_tbody');
    var yearEl = $('raw_year');
    var meta = $('raw_rows_meta');
    var pageMeta = $('raw_page_meta');
    var prevBtn = $('raw_prev_btn');
    var nextBtn = $('raw_next_btn');
    var firstBtn = $('raw_first_btn');
    var lastBtn = $('raw_last_btn');
    var pageNums = $('raw_page_numbers');
    if (!tbody || !yearEl) return;

    if (resetPage) rawPage = 1;
    if (loading) return;
    loading = true;
    setErr('');

    var year = parseInt(yearEl.value, 10) || 0;
    var url = apiUrl + '?year=' + encodeURIComponent(String(year))
      + '&limit=' + encodeURIComponent(String(rawLimit))
      + '&page=' + encodeURIComponent(String(rawPage));

    fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
      .then(function (r) {
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.json();
      })
      .then(function (j) {
        loading = false;
        if (!j || !j.success) {
          setErr((j && j.message) ? j.message : 'Failed to load');
          tbody.innerHTML = '';
          if (pageMeta) pageMeta.textContent = '';
          if (pageNums) pageNums.innerHTML = '';
          rawCachedTotal = 0;
          rawCachedYear = 0;
          return;
        }
        var d = j.data || {};
        var total = parseInt(d.total, 10) || 0;
        var rows = Array.isArray(d.rows) ? d.rows : [];
        var limit = parseInt(d.limit, 10) || rawLimit;
        var page = parseInt(d.page, 10) || rawPage;
        var totalPages = Math.max(1, Math.ceil(total / limit));
        rawCachedTotal = total;
        rawCachedYear = year;

        if (meta) {
          meta.textContent = total + ' row' + (total === 1 ? '' : 's') + ' for ' + year;
        }
        if (pageMeta) {
          var from = total === 0 ? 0 : (page - 1) * limit + 1;
          var to = Math.min((page - 1) * limit + rows.length, total);
          pageMeta.textContent = total ? ('Showing ' + from + '–' + to + ' of ' + total + ' · Page ' + page + ' / ' + totalPages) : 'No rows';
        }

        if (rows.length === 0 && total > 0) {
          var maxPage = totalPages;
          if (page > maxPage) {
            rawPage = maxPage;
            loadRaw(false);
            return;
          }
        }

        var cols = ['id', 'Dated', 'Business_Name', 'Sales_Rep', 'Invoice_Num', 'product', 'Delivery_Profile', 'Quantity', 'Unit_Price', 'Purchase_Price', 'imported_at'];
        var html = rows.map(function (row) {
          var tds = cols.map(function (c) {
            return '<td style="padding:7px 10px;border-bottom:1px solid <?= h($C['border']) ?>;color:<?= h($C['text']) ?>;white-space:nowrap">' + esc(row[c]) + '</td>';
          }).join('');
          return '<tr>' + tds + '</tr>';
        }).join('');
        tbody.innerHTML = html;

        renderPageButtons(total, limit, page);

        var atEnd = (page - 1) * limit + rows.length >= total || total === 0;
        if (prevBtn) prevBtn.disabled = page <= 1;
        if (nextBtn) nextBtn.disabled = atEnd;
        if (firstBtn) firstBtn.disabled = page <= 1;
        if (lastBtn) lastBtn.disabled = atEnd || total === 0;
      })
      .catch(function (e) {
        loading = false;
        setErr(e.message || 'Network error');
        tbody.innerHTML = '';
        if (pageMeta) pageMeta.textContent = '';
        if (pageNums) pageNums.innerHTML = '';
        rawCachedTotal = 0;
        rawCachedYear = 0;
      });
  }

  document.addEventListener('DOMContentLoaded', function () {
    if (!$('raw_load_btn') || !$('raw_rows_tbody')) return;
    var perPageEl = $('raw_per_page');
    if (perPageEl) {
      var initial = parseInt(perPageEl.value, 10);
      if (isFinite(initial) && initial > 0) rawLimit = initial;
      perPageEl.addEventListener('change', function () {
        var nextLimit = parseInt(perPageEl.value, 10);
        if (!isFinite(nextLimit) || nextLimit <= 0) return;
        rawLimit = nextLimit;
        rawCachedTotal = 0;
        rawCachedYear = 0;
        loadRaw(true);
      });
    }
    $('raw_load_btn').addEventListener('click', function () { loadRaw(true); });
    $('raw_prev_btn').addEventListener('click', function () {
      if (rawPage > 1) { rawPage--; loadRaw(false); }
    });
    $('raw_next_btn').addEventListener('click', function () {
      rawPage++;
      loadRaw(false);
    });
    $('raw_first_btn').addEventListener('click', function () {
      if (rawPage !== 1) { rawPage = 1; loadRaw(false); }
    });
    $('raw_last_btn').addEventListener('click', function () {
      var yearEl = $('raw_year');
      if (!yearEl) return;
      var y = parseInt(yearEl.value, 10) || 0;
      if (rawCachedYear === y && rawCachedTotal > 0) {
        rawPage = Math.max(1, Math.ceil(rawCachedTotal / rawLimit));
        loadRaw(false);
        return;
      }
      loading = true;
      setErr('');
      var url = apiUrl + '?year=' + encodeURIComponent(String(y))
        + '&limit=' + encodeURIComponent(String(rawLimit))
        + '&page=1';
      fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
        .then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
        .then(function (j) {
          loading = false;
          if (!j || !j.success) {
            setErr((j && j.message) ? j.message : 'Failed to load');
            return;
          }
          var total = parseInt((j.data || {}).total, 10) || 0;
          var limit = parseInt((j.data || {}).limit, 10) || rawLimit;
          rawCachedTotal = total;
          rawCachedYear = y;
          rawPage = Math.max(1, Math.ceil(total / limit));
          loadRaw(false);
        })
        .catch(function (e) {
          loading = false;
          setErr(e.message || 'Network error');
        });
    });
    var numWrap = $('raw_page_numbers');
    if (numWrap) {
      numWrap.addEventListener('click', function (ev) {
        var t = ev.target;
        if (!t || !t.closest) return;
        var btn = t.closest('.raw-page-num');
        if (!btn || !numWrap.contains(btn)) return;
        var p = parseInt(btn.getAttribute('data-raw-page'), 10);
        if (!isFinite(p) || p < 1) return;
        rawPage = p;
        loadRaw(false);
      });
    }
    loadRaw(true);
  });
})();
</script>
<?php endif; ?>

<?php endif; // hasData ?>

<div style="text-align:center;margin-top:32px;font-size:11px;color:<?= h($C['dim']) ?>">
  Tyre Retail Intelligence Dashboard • Data in MySQL • Upload new monthly files anytime
</div>
</div>
</div>

