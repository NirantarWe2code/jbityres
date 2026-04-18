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
        <span style="font-size:12px;color:<?= h($C['muted']) ?>;font-weight:600;white-space:nowrap;padding-top:8px">Years (compare)</span>
        <div class="jbi-year-picker" style="position:relative">
          <button type="button" class="jbi-year-picker__toggle" aria-expanded="false" aria-haspopup="true" style="min-width:160px;text-align:left;background:<?= h($C['surface']) ?>;color:<?= h($C['text']) ?>;border:1px solid <?= h($C['border']) ?>;border-radius:8px;padding:8px 32px 8px 12px;cursor:pointer;font-size:13px;font-weight:600;<?= h($stylesMono) ?>;position:relative">
            <?= h($pickerBtnLabel) ?>
            <span style="position:absolute;right:10px;top:50%;transform:translateY(-50%);font-size:10px;color:<?= h($C['muted']) ?>">▾</span>
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
        <button type="submit" style="background:<?= h($C['teal']) ?>;color:<?= h($C['bg']) ?>;border:none;border-radius:8px;padding:8px 14px;cursor:pointer;font-size:12px;font-weight:700;white-space:nowrap;margin-top:2px">Apply</button>
        <span style="font-size:10px;color:<?= h($C['dim']) ?>;max-width:200px;line-height:1.35;padding-top:4px">Tick years to compare, then Apply. No ticks + Apply = show all years.</span>
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
          <button type="submit" style="background:<?= $quickFilter === '1m' ? h($C['teal']) : 'transparent' ?>;color:<?= $quickFilter === '1m' ? h($C['bg']) : h($C['muted']) ?>;border:1px solid <?= $quickFilter === '1m' ? h($C['teal']) : h($C['dim']) ?>;border-radius:999px;padding:5px 10px;cursor:pointer;font-size:11px;font-weight:700;<?= h($stylesMono) ?>">1 Month</button>
        </form>
        <form method="post" action="process.php" style="margin:0">
          <input type="hidden" name="action" value="set_quick_filter">
          <input type="hidden" name="quick_filter" value="3m">
          <button type="submit" style="background:<?= $quickFilter === '3m' ? h($C['teal']) : 'transparent' ?>;color:<?= $quickFilter === '3m' ? h($C['bg']) : h($C['muted']) ?>;border:1px solid <?= $quickFilter === '3m' ? h($C['teal']) : h($C['dim']) ?>;border-radius:999px;padding:5px 10px;cursor:pointer;font-size:11px;font-weight:700;<?= h($stylesMono) ?>">3 Months</button>
        </form>
        <form method="post" action="process.php" style="margin:0">
          <input type="hidden" name="action" value="set_quick_filter">
          <input type="hidden" name="quick_filter" value="6m">
          <button type="submit" style="background:<?= $quickFilter === '6m' ? h($C['teal']) : 'transparent' ?>;color:<?= $quickFilter === '6m' ? h($C['bg']) : h($C['muted']) ?>;border:1px solid <?= $quickFilter === '6m' ? h($C['teal']) : h($C['dim']) ?>;border-radius:999px;padding:5px 10px;cursor:pointer;font-size:11px;font-weight:700;<?= h($stylesMono) ?>">6 Months</button>
        </form>
        <form method="post" action="process.php" style="margin:0">
          <input type="hidden" name="action" value="set_quick_filter">
          <input type="hidden" name="quick_filter" value="year">
          <button type="submit" style="background:<?= $quickFilter === 'year' ? h($C['teal']) : 'transparent' ?>;color:<?= $quickFilter === 'year' ? h($C['bg']) : h($C['muted']) ?>;border:1px solid <?= $quickFilter === 'year' ? h($C['teal']) : h($C['dim']) ?>;border-radius:999px;padding:5px 10px;cursor:pointer;font-size:11px;font-weight:700;<?= h($stylesMono) ?>">Year</button>
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
          'customers' => '🏆 Customers',
          'reps' => '👤 Sales Reps',
          'activity' => '⏱ Activity & Areas',
          'rawdata' => '🧾 Raw Rows (API)',
      ] as $vk => $lbl
  ): ?>
    <form method="post" action="process.php" style="margin:0">
      <input type="hidden" name="action" value="set_view">
      <input type="hidden" name="view" value="<?= h($vk) ?>">
      <button type="submit" style="background:<?= $view === $vk ? h($C['teal']) : 'transparent' ?>;color:<?= $view === $vk ? h($C['bg']) : h($C['muted']) ?>;border:1px solid <?= $view === $vk ? h($C['teal']) : h($C['dim']) ?>;border-radius:8px;padding:6px 16px;cursor:pointer;font-size:12px;font-weight:600;<?= h($stylesSans) ?>"><?= h($lbl) ?></button>
    </form>
  <?php endforeach; ?>
</div>

<?php if ($latestData): ?>
<!-- KPI row -->
<div style="display:flex;gap:12px;margin-bottom:24px;flex-wrap:wrap">
  <?php
    $kpi = function (string $label, string $value, ?string $sub, string $color, ?float $trend) use ($C, $stylesSans, $stylesMono) {
        echo '<div style="background:' . h($C['card']) . ';border:1px solid ' . h($C['border']) . ';border-radius:12px;padding:20px 24px;flex:1;min-width:160px;border-top:3px solid ' . h($color) . ';position:relative;overflow:hidden">';
        echo '<div style="font-size:11px;color:' . h($C['muted']) . ';letter-spacing:0.12em;text-transform:uppercase;margin-bottom:8px;' . h($stylesSans) . '">' . h($label) . '</div>';
        echo '<div style="font-size:28px;font-weight:700;color:' . h($color) . ';' . h($stylesMono) . ';line-height:1">' . $value . '</div>';
        if ($sub) {
            echo '<div style="font-size:12px;color:' . h($C['muted']) . ';margin-top:6px;' . h($stylesSans) . '">' . h($sub) . '</div>';
        }
        if ($trend !== null) {
            $tc = $trend >= 0 ? $C['green'] : $C['rose'];
            echo '<div style="font-size:12px;margin-top:6px;color:' . h($tc) . ';' . h($stylesMono) . '">' . ($trend >= 0 ? '▲' : '▼') . ' ' . number_format(abs($trend), 1) . '% vs prev yr</div>';
        }
        echo '</div>';
    };
  $kpi('Revenue ex-GST (' . $latestYear . ')', fmt_aud((float) $latestData['totals']['revenue']), 'inc-GST: ' . fmt_aud((float) $latestData['totals']['revenue'] * 1.1), $C['teal'], $yoyRev);
  $kpi('Gross Profit (' . $latestYear . ')', fmt_aud((float) $latestData['totals']['profit']), 'Margin: ' . fmt_pct((float) $latestData['totals']['margin']), $C['gold'], $yoyProfit);
  $kpi('Total Units', fmt_num((float) round($latestData['totals']['units'])), 'Tyres sold', $C['blue'], $yoyUnits);
  $kpi('Active Customers', (string) (int) $latestData['totals']['customers'], fmt_num((float) $latestData['totals']['invoices']) . ' invoices', $C['purple'], null);
  $inv = (float) $latestData['totals']['invoices'];
  $kpi('Avg Invoice Value', fmt_aud($inv > 0 ? (float) $latestData['totals']['revenue'] / $inv : 0.0), 'ex-GST', $C['green'], null);
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

  <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;margin-top:10px">
    <button id="raw_prev_btn" type="button" style="background:transparent;color:<?= h($C['text']) ?>;border:1px solid <?= h($C['border']) ?>;border-radius:8px;padding:6px 10px;cursor:pointer">Prev</button>
    <div id="raw_page_meta" style="font-size:12px;color:<?= h($C['muted']) ?>"></div>
    <button id="raw_next_btn" type="button" style="background:transparent;color:<?= h($C['text']) ?>;border:1px solid <?= h($C['border']) ?>;border-radius:8px;padding:6px 10px;cursor:pointer">Next</button>
  </div>
</div>
<?php endif; ?>

<?php endif; // hasData ?>

<div style="text-align:center;margin-top:32px;font-size:11px;color:<?= h($C['dim']) ?>">
  Tyre Retail Intelligence Dashboard • Data in MySQL • Upload new monthly files anytime
</div>
</div>
</div>

