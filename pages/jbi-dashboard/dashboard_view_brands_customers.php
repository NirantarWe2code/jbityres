<?php
/** @var string $view @var ?array $latestData @var int $latestYear @var list<int> $years @var array $C @var string $stylesSans @var string $stylesMono */
/** @var list<array> $brandData @var list<string> $topBrands @var int $brandChartYearEff @var list<array> $brandMonthlyUnits @var list<array> $brandMonthlyPivot @var array $pivotColTotals */
/** @var int $custCurYear @var ?int $custCmpYear @var ?array $custCurData @var ?array $custCmpData @var ?array $custAnalytics @var string $custTab @var ?int $prevYear @var ?int $latestYear */

if (!function_exists('chart_block')) {
    return;
}

// ---- BRANDS ----
if ($view === 'brands' && $latestData):
    ?>
<div style="display:grid;gap:20px">
  <div style="background:<?= h($C['card']) ?>;border:1px solid <?= h($C['border']) ?>;border-radius:12px;padding:20px">
    <div style="margin-bottom:16px">
      <div style="font-size:13px;font-weight:700;color:<?= h($C['text']) ?>;letter-spacing:0.06em;text-transform:uppercase">Brand Revenue (<?= (int) $latestYear ?>)</div>
      <div style="font-size:11px;color:<?= h($C['muted']) ?>;margin-top:2px">Colour = margin tier: red &lt; 0%, amber 0–8%, teal 8%+</div>
    </div>
    <?php chart_block('br_rev_all', 320); ?>
  </div>
  <div style="background:<?= h($C['card']) ?>;border:1px solid <?= h($C['border']) ?>;border-radius:12px;padding:20px">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:16px;flex-wrap:wrap;gap:10px">
      <div>
        <div style="font-size:13px;font-weight:700;color:<?= h($C['text']) ?>;letter-spacing:0.06em;text-transform:uppercase">Units Sold per Month by Brand</div>
        <div style="font-size:11px;color:<?= h($C['muted']) ?>;margin-top:2px">Top <?= count($topBrands) ?> brands by volume — stacked</div>
      </div>
      <div style="display:flex;gap:6px">
        <?php foreach ($years as $i => $y):
            $yc = YEAR_COLORS[$i % count(YEAR_COLORS)];
            $sel = $brandChartYearEff === $y;
            ?>
          <form method="post" action="process.php" style="margin:0">
            <input type="hidden" name="action" value="set_brand_chart_year">
            <input type="hidden" name="year" value="<?= (int) $y ?>">
            <button type="submit" style="background:<?= $sel ? h($yc) . '25' : 'transparent' ?>;color:<?= $sel ? h($yc) : h($C['muted']) ?>;border:1px solid <?= $sel ? h($yc) : h($C['dim']) ?>;border-radius:16px;padding:4px 14px;cursor:pointer;font-size:12px;font-weight:700;font-family:monospace"><?= (int) $y ?></button>
          </form>
        <?php endforeach; ?>
      </div>
    </div>
    <?php chart_block('br_monthly_stack', 300); ?>
  </div>
  <div style="background:<?= h($C['card']) ?>;border:1px solid <?= h($C['border']) ?>;border-radius:12px;padding:20px">
    <div style="margin-bottom:16px">
      <div style="font-size:13px;font-weight:700;color:<?= h($C['text']) ?>;letter-spacing:0.06em;text-transform:uppercase">Units Sold — Monthly Breakdown by Brand &amp; Year</div>
      <div style="font-size:11px;color:<?= h($C['muted']) ?>;margin-top:2px">Top <?= count($topBrands) ?> brands · <?= h(implode(' vs ', array_map('strval', $years))) ?></div>
    </div>
    <div style="overflow-x:auto">
      <table style="border-collapse:collapse;font-size:11px;min-width:100%">
        <thead>
          <tr>
            <th style="text-align:left;padding:8px 12px;color:<?= h($C['muted']) ?>;border-bottom:1px solid <?= h($C['border']) ?>;font-weight:600;min-width:80px;position:sticky;left:0;background:<?= h($C['card']) ?>">Brand</th>
            <th style="text-align:center;padding:8px;color:<?= h($C['muted']) ?>;border-bottom:1px solid <?= h($C['border']) ?>;font-weight:600;min-width:44px">Yr</th>
            <?php foreach (DASHBOARD_MONTHS as $m): ?>
              <th style="text-align:right;padding:8px;color:<?= h($C['muted']) ?>;border-bottom:1px solid <?= h($C['border']) ?>;font-weight:600;min-width:46px"><?= h($m) ?></th>
            <?php endforeach; ?>
            <th style="text-align:right;padding:8px 12px;color:<?= h($C['teal']) ?>;border-bottom:1px solid <?= h($C['border']) ?>;font-weight:700;min-width:58px">Total</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($topBrands as $bi => $brand):
              $brandRows = array_values(array_filter($brandMonthlyPivot, static fn ($r) => ($r['brand'] ?? '') === $brand));
              foreach ($brandRows as $yi => $row):
                  $isFirst = $yi === 0;
                  $isLast = $yi === count($brandRows) - 1;
                  $prevRow = $yi > 0 ? $brandRows[$yi - 1] : null;
                  $yoyChg = ($prevRow && ($prevRow['Total'] ?? 0) > 0) ? ((($row['Total'] - $prevRow['Total']) / $prevRow['Total']) * 100) : null;
                  $bg = $bi % 2 === 0 ? $C['surface'] : 'transparent';
                  ?>
                <tr style="background:<?= h($bg) ?>;border-bottom:<?= $isLast ? '1px solid ' . h($C['border']) : 'none' ?>">
                  <td style="padding:<?= $isFirst ? '10px 12px 4px' : '4px 12px 10px' ?>;font-weight:700;color:<?= h(BRAND_COLORS[$bi % count(BRAND_COLORS)]) ?>;position:sticky;left:0;background:<?= h($bi % 2 === 0 ? $C['surface'] : $C['card']) ?>;font-size:12px;vertical-align:middle">
                    <?php if ($isFirst): ?><span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:<?= h(BRAND_COLORS[$bi % count(BRAND_COLORS)]) ?>;margin-right:6px"></span><?= h($brand) ?><?php endif; ?>
                  </td>
                  <?php
                  $yidx = array_search($row['year'], $years, true);
                  $yidx = $yidx === false ? 0 : (int) $yidx;
                  $ycc = YEAR_COLORS[$yidx % count(YEAR_COLORS)];
                  ?>
                  <td style="padding:6px 8px;text-align:center;<?= h($stylesMono) ?>;font-size:11px;color:<?= h($ycc) ?>;font-weight:700"><?= (int) $row['year'] ?></td>
                  <?php foreach (DASHBOARD_MONTHS as $m):
                      $v = (int) ($row[$m] ?? 0); ?>
                    <td style="padding:6px 8px;text-align:right;<?= h($stylesMono) ?>;color:<?= $v > 0 ? h($C['text']) : h($C['dim']) ?>"><?= $v > 0 ? h(fmt_num((float) $v)) : '—' ?></td>
                  <?php endforeach; ?>
                  <td style="padding:6px 12px;text-align:right;<?= h($stylesMono) ?>;font-weight:700">
                    <span style="color:<?= h($C['teal']) ?>"><?= h(fmt_num((float) ($row['Total'] ?? 0))) ?></span>
                    <?php if ($yoyChg !== null): ?>
                      <span style="display:block;font-size:10px;color:<?= $yoyChg >= 0 ? h($C['green']) : h($C['rose']) ?>;font-weight:600"><?= $yoyChg >= 0 ? '▲' : '▼' ?> <?= number_format(abs($yoyChg), 0) ?>%</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
          <?php endforeach; ?>
          <?php foreach ($years as $yi => $y):
              $yc = YEAR_COLORS[$yi % count(YEAR_COLORS)]; ?>
            <tr style="border-top:<?= $yi === 0 ? '2px solid ' . h($C['teal']) : 'none' ?>;background:<?= h($C['card']) ?>">
              <td style="padding:9px 12px;font-weight:800;color:<?= h($C['teal']) ?>;position:sticky;left:0;background:<?= h($C['card']) ?>;font-size:12px"><?= $yi === 0 ? 'TOTAL' : '' ?></td>
              <td style="padding:6px 8px;text-align:center;<?= h($stylesMono) ?>;font-size:11px;color:<?= h($yc) ?>;font-weight:700"><?= (int) $y ?></td>
              <?php foreach (DASHBOARD_MONTHS as $m):
                  $k = $m . '_' . $y;
                  $tv = $pivotColTotals[$k] ?? 0; ?>
                <td style="padding:9px 8px;text-align:right;<?= h($stylesMono) ?>;font-weight:700;color:<?= h($C['teal']) ?>"><?= $tv > 0 ? h(fmt_num((float) $tv)) : '—' ?></td>
              <?php endforeach; ?>
              <td style="padding:9px 12px;text-align:right;<?= h($stylesMono) ?>;font-weight:800;color:<?= h($C['teal']) ?>"><?= h(fmt_num((float) ($pivotColTotals['Total_' . $y] ?? 0))) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div style="background:<?= h($C['card']) ?>;border:1px solid <?= h($C['border']) ?>;border-radius:12px;padding:20px">
    <div style="margin-bottom:16px">
      <div style="font-size:13px;font-weight:700;color:<?= h($C['text']) ?>;letter-spacing:0.06em;text-transform:uppercase">Full Brand Summary</div>
      <div style="font-size:11px;color:<?= h($C['muted']) ?>;margin-top:2px">All brands — sorted by revenue (top 15 slice per React)</div>
    </div>
    <table style="width:100%;border-collapse:collapse;font-size:12px">
      <thead>
        <tr>
          <?php foreach (['#', 'Brand', 'Revenue ex-GST', 'Rev %', 'Total Units', 'Gross Profit', 'GP Margin'] as $hc):
              $al = in_array($hc, ['#', 'Brand'], true) ? 'left' : 'right'; ?>
            <th style="text-align:<?= h($al) ?>;padding:8px 12px;color:<?= h($C['muted']) ?>;border-bottom:1px solid <?= h($C['border']) ?>;font-weight:600;font-size:11px;letter-spacing:0.04em"><?= h($hc) ?></th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($brandData as $i => $b):
            $bg = $i % 2 === 0 ? $C['surface'] : 'transparent';
            $mc = (float) $b['margin'];
            $mcol = $mc < 0 ? $C['rose'] : ($mc < 8 ? $C['gold'] : $C['green']);
            ?>
          <tr style="background:<?= h($bg) ?>">
            <td style="padding:9px 12px;color:<?= h($C['muted']) ?>;<?= h($stylesMono) ?>"><?= $i + 1 ?></td>
            <td style="padding:9px 12px;color:<?= h($C['text']) ?>;font-weight:600"><?= h((string) $b['brand']) ?></td>
            <td style="padding:9px 12px;color:<?= h($C['teal']) ?>;text-align:right;<?= h($stylesMono) ?>"><?= h(fmt_audf((float) $b['revenue'])) ?></td>
            <td style="padding:9px 12px;color:<?= h($C['muted']) ?>;text-align:right;<?= h($stylesMono) ?>"><?= h(fmt_pct(((float) $b['revenue'] / max(1e-9, (float) $latestData['totals']['revenue'])) * 100)) ?></td>
            <td style="padding:9px 12px;text-align:right;<?= h($stylesMono) ?>"><?= h(fmt_num((float) round($b['units']))) ?></td>
            <td style="padding:9px 12px;text-align:right;<?= h($stylesMono) ?>;color:<?= ((float) $b['profit'] < 0) ? h($C['rose']) : h($C['gold']) ?>"><?= h(fmt_audf((float) $b['profit'])) ?></td>
            <td style="padding:9px 12px;text-align:right;<?= h($stylesMono) ?>;font-weight:700;color:<?= h($mcol) ?>"><?= h(fmt_pct($mc)) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php
// ---- CUSTOMERS ----
if ($view === 'customers' && $custCurData):
    ?>
<div style="display:grid;gap:20px">
  <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
    <span style="font-size:12px;color:<?= h($C['muted']) ?>;<?= h($stylesSans) ?>">Viewing year:</span>
    <?php foreach ($years as $i => $y):
        $yc = YEAR_COLORS[$i % count(YEAR_COLORS)];
        $sel = $custCurYear === $y;
        ?>
      <form method="post" action="process.php" style="margin:0">
        <input type="hidden" name="action" value="set_cust_year">
        <input type="hidden" name="year" value="<?= (int) $y ?>">
        <button type="submit" style="background:<?= $sel ? h($yc) . '25' : 'transparent' ?>;color:<?= $sel ? h($yc) : h($C['muted']) ?>;border:1px solid <?= $sel ? h($yc) : h($C['dim']) ?>;border-radius:20px;padding:5px 18px;cursor:pointer;font-size:13px;font-weight:700;<?= h($stylesMono) ?>"><?= (int) $y ?></button>
      </form>
    <?php endforeach; ?>
    <?php if ($custCmpYear): ?>
      <span style="font-size:11px;color:<?= h($C['muted']) ?>;<?= h($stylesSans) ?>">← comparing vs <?= (int) $custCmpYear ?> (same period)</span>
    <?php else: ?>
      <span style="font-size:11px;color:<?= h($C['muted']) ?>;<?= h($stylesSans) ?>">← upload <?= $prevYear ? (int) $prevYear - 1 : 'another' ?> to unlock retention analysis</span>
    <?php endif; ?>
  </div>

  <?php if ($custAnalytics):
      $rr = (float) $custAnalytics['retentionRate'];
      $rcol = $rr >= 80 ? $C['green'] : ($rr >= 60 ? $C['gold'] : $C['rose']);
      $rrv = $custAnalytics['retainedRevLatest'];
      $rrp = $custAnalytics['retainedRevPrev'];
      $rg = $rrv >= $rrp ? $C['green'] : $C['rose'];
      $pctRet = $rrp > 0 ? (($rrv - $rrp) / $rrp) * 100 : null;
      ?>
    <div style="display:flex;gap:12px;flex-wrap:wrap">
      <div style="background:<?= h($C['card']) ?>;border:1px solid <?= h($C['border']) ?>;border-radius:12px;padding:18px 24px;flex:1;min-width:160px;border-top:3px solid <?= h($C['teal']) ?>">
        <div style="font-size:11px;color:<?= h($C['muted']) ?>;letter-spacing:0.1em;text-transform:uppercase;margin-bottom:6px;<?= h($stylesSans) ?>">Retention Rate</div>
        <div style="font-size:30px;font-weight:800;color:<?= h($rcol) ?>;<?= h($stylesMono) ?>"><?= h(fmt_pct($rr)) ?></div>
        <div style="font-size:11px;color:<?= h($C['muted']) ?>;margin-top:4px"><?= count($custAnalytics['retained']) ?> of <?= (int) $custAnalytics['totalPrev'] ?> kept (<?= h($custAnalytics['periodLabel']) ?>)</div>
      </div>
      <div style="background:<?= h($C['card']) ?>;border:1px solid <?= h($C['border']) ?>;border-radius:12px;padding:18px 24px;flex:1;min-width:160px;border-top:3px solid <?= h($C['green']) ?>">
        <div style="font-size:11px;color:<?= h($C['muted']) ?>;letter-spacing:0.1em;text-transform:uppercase;margin-bottom:6px">New Customers</div>
        <div style="font-size:30px;font-weight:800;color:<?= h($C['green']) ?>;<?= h($stylesMono) ?>"><?= count($custAnalytics['gained']) ?></div>
        <div style="font-size:11px;color:<?= h($C['muted']) ?>;margin-top:4px">+<?= h(fmt_aud((float) $custAnalytics['gainedRev'])) ?> revenue</div>
      </div>
      <div style="background:<?= h($C['card']) ?>;border:1px solid <?= h($C['border']) ?>;border-radius:12px;padding:18px 24px;flex:1;min-width:160px;border-top:3px solid <?= h($C['rose']) ?>">
        <div style="font-size:11px;color:<?= h($C['muted']) ?>;letter-spacing:0.1em;text-transform:uppercase;margin-bottom:6px">Lost Customers</div>
        <div style="font-size:30px;font-weight:800;color:<?= h($C['rose']) ?>;<?= h($stylesMono) ?>"><?= count($custAnalytics['lost']) ?></div>
        <div style="font-size:11px;color:<?= h($C['muted']) ?>;margin-top:4px"><?= h(fmt_aud((float) $custAnalytics['lostRev'])) ?> same-period rev at risk</div>
      </div>
      <div style="background:<?= h($C['card']) ?>;border:1px solid <?= h($C['border']) ?>;border-radius:12px;padding:18px 24px;flex:1;min-width:160px;border-top:3px solid <?= h($C['gold']) ?>">
        <div style="font-size:11px;color:<?= h($C['muted']) ?>;letter-spacing:0.1em;text-transform:uppercase;margin-bottom:6px">Retained Revenue Growth</div>
        <div style="font-size:30px;font-weight:800;color:<?= h($rg) ?>;<?= h($stylesMono) ?>"><?= $pctRet === null ? '—' : (($rrv >= $rrp ? '+' : '') . number_format($pctRet, 1) . '%') ?></div>
        <div style="font-size:11px;color:<?= h($C['muted']) ?>;margin-top:4px"><?= h(fmt_aud((float) $rrv)) ?> vs <?= h(fmt_aud((float) $rrp)) ?></div>
      </div>
      <div style="background:<?= h($C['card']) ?>;border:1px solid <?= h($C['border']) ?>;border-radius:12px;padding:18px 24px;flex:1;min-width:160px;border-top:3px solid <?= h($C['purple']) ?>">
        <div style="font-size:11px;color:<?= h($C['muted']) ?>;letter-spacing:0.1em;text-transform:uppercase;margin-bottom:6px">Net Customer Change</div>
        <?php $nc = (int) $custAnalytics['totalLatest'] - (int) $custAnalytics['totalPrev'];
        $ncol = (int) $custAnalytics['totalLatest'] >= (int) $custAnalytics['totalPrev'] ? $C['green'] : $C['rose']; ?>
        <div style="font-size:30px;font-weight:800;color:<?= h($ncol) ?>;<?= h($stylesMono) ?>"><?= $nc >= 0 ? '+' : '' ?><?= $nc ?></div>
        <div style="font-size:11px;color:<?= h($C['muted']) ?>;margin-top:4px"><?= (int) $custAnalytics['totalPrev'] ?> → <?= (int) $custAnalytics['totalLatest'] ?> accounts</div>
      </div>
    </div>
  <?php endif; ?>

  <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
    <?php
    $tabs = [
        ['all', 'All (' . count($custCurData['customers']) . ')', $C['teal']],
        ['retained', $custAnalytics ? 'Retained (' . count($custAnalytics['retained']) . ')' : 'Retained', $C['gold']],
        ['gained', $custAnalytics ? 'New (' . count($custAnalytics['gained']) . ')' : 'New', $C['green']],
        ['lost', $custAnalytics ? 'Lost (' . count($custAnalytics['lost']) . ')' : 'Lost', $C['rose']],
    ];
foreach ($tabs as [$tid, $tlab, $tcol]):
    $dis = !$custAnalytics && $tid !== 'all';
    ?>
      <form method="post" action="process.php" style="margin:0">
        <input type="hidden" name="action" value="set_cust_tab">
        <input type="hidden" name="tab" value="<?= h($tid) ?>">
        <button type="submit" <?= $dis ? 'disabled' : '' ?> style="background:<?= $custTab === $tid ? h($tcol) . '20' : 'transparent' ?>;color:<?= $custTab === $tid ? h($tcol) : h($C['muted']) ?>;border:1px solid <?= $custTab === $tid ? h($tcol) : h($C['dim']) ?>;border-radius:8px;padding:6px 16px;cursor:<?= $dis ? 'not-allowed' : 'pointer' ?>;font-size:12px;font-weight:600;<?= h($stylesSans) ?>;opacity:<?= $dis ? '0.4' : '1' ?>"><?= h($tlab) ?></button>
      </form>
    <?php endforeach; ?>
    <?php if (!$custAnalytics): ?>
      <span style="font-size:11px;color:<?= h($C['muted']) ?>;margin-left:8px">↑ Upload <?= $prevYear ? (int) $prevYear - 1 : 'another' ?> year to unlock retention analysis</span>
    <?php endif; ?>
  </div>

  <?php if ($custTab === 'all'): ?>
  <div style="background:<?= h($C['card']) ?>;border:1px solid <?= h($C['border']) ?>;border-radius:12px;padding:20px">
    <div style="margin-bottom:16px">
      <div style="font-size:13px;font-weight:700;color:<?= h($C['text']) ?>;letter-spacing:0.06em;text-transform:uppercase">All Customers — <?= (int) $custCurYear ?></div>
      <div style="font-size:11px;color:<?= h($C['muted']) ?>;margin-top:2px">Sorted by revenue</div>
    </div>
    <div style="overflow-x:auto">
      <table style="width:100%;border-collapse:collapse;font-size:12px">
        <thead>
          <tr>
            <?php foreach (['#', 'Customer', 'Status', 'Revenue ex-GST', 'Rev %', 'Units', 'Invoices', 'Gross Profit', 'GP Margin'] as $hc):
                $al = in_array($hc, ['#', 'Customer', 'Status'], true) ? 'left' : 'right'; ?>
              <th style="text-align:<?= h($al) ?>;padding:8px 12px;color:<?= h($C['muted']) ?>;border-bottom:1px solid <?= h($C['border']) ?>;font-weight:600;font-size:11px;white-space:nowrap"><?= h($hc) ?></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($custCurData['customers'] as $i => $c):
              $isNew = $custAnalytics && !isset($retainedNameSet[(string) $c['customer']]);
              $status = !$custAnalytics ? '—' : ($isNew ? '🟢 New' : '🔵 Retained');
              $bg = $i % 2 === 0 ? $C['surface'] : 'transparent';
              $mc = (float) $c['margin'];
              $mcol = $mc < 0 ? $C['rose'] : ($mc < 8 ? $C['gold'] : $C['green']);
              ?>
            <tr style="background:<?= h($bg) ?>">
              <td style="padding:9px 12px;color:<?= h($C['muted']) ?>;<?= h($stylesMono) ?>"><?= $i + 1 ?></td>
              <td style="padding:9px 12px;color:<?= h($C['text']) ?>;font-weight:500;max-width:260px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= h((string) $c['customer']) ?></td>
              <td style="padding:9px 12px;font-size:11px"><?= h($status) ?></td>
              <td style="padding:9px 12px;color:<?= h($C['teal']) ?>;text-align:right;<?= h($stylesMono) ?>"><?= h(fmt_audf((float) $c['revenue'])) ?></td>
              <td style="padding:9px 12px;color:<?= h($C['muted']) ?>;text-align:right;<?= h($stylesMono) ?>"><?= h(fmt_pct(((float) $c['revenue'] / max(1e-9, (float) $custCurData['totals']['revenue'])) * 100)) ?></td>
              <td style="padding:9px 12px;text-align:right;<?= h($stylesMono) ?>"><?= h(fmt_num((float) round($c['units']))) ?></td>
              <td style="padding:9px 12px;text-align:right;<?= h($stylesMono) ?>"><?= h(fmt_num((float) $c['invoices'])) ?></td>
              <td style="padding:9px 12px;text-align:right;<?= h($stylesMono) ?>;color:<?= ((float) $c['profit'] < 0) ? h($C['rose']) : h($C['gold']) ?>"><?= h(fmt_audf((float) $c['profit'])) ?></td>
              <td style="padding:9px 12px;text-align:right;<?= h($stylesMono) ?>;font-weight:700;color:<?= h($mcol) ?>"><?= h(fmt_pct($mc)) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($custTab === 'retained' && $custAnalytics): ?>
  <div style="background:<?= h($C['card']) ?>;border:1px solid <?= h($C['border']) ?>;border-radius:12px;padding:20px">
    <div style="margin-bottom:16px">
      <div style="font-size:13px;font-weight:700;color:<?= h($C['text']) ?>">Retained Customers — <?= (int) $custCmpYear ?> → <?= (int) $custCurYear ?></div>
      <div style="font-size:11px;color:<?= h($C['muted']) ?>;margin-top:2px">Comparing same period (<?= h($custAnalytics['periodLabel']) ?>) in both years — sorted by current revenue</div>
    </div>
    <div style="overflow-x:auto">
      <table style="width:100%;border-collapse:collapse;font-size:12px">
        <thead><tr>
          <?php foreach (['#', 'Customer', 'Rev ' . $custCurYear, 'Rev ' . $custCmpYear . ' (same period)', 'A$ Change', 'Units', 'Invoices', 'GP Margin', '% Trend'] as $hc):
              $al = in_array($hc, ['#', 'Customer'], true) ? 'left' : 'right'; ?>
            <th style="text-align:<?= h($al) ?>;padding:8px 12px;color:<?= h($C['muted']) ?>;border-bottom:1px solid <?= h($C['border']) ?>;font-weight:600;font-size:11px;white-space:nowrap"><?= h((string) $hc) ?></th>
          <?php endforeach; ?>
        </tr></thead>
        <tbody>
          <?php foreach ($custAnalytics['retained'] as $i => $c):
              $bg = $i % 2 === 0 ? $C['surface'] : 'transparent';
              $rc = $c['revChg'] ?? null;
              $mc = (float) $c['margin'];
              $mcol = $mc < 0 ? $C['rose'] : ($mc < 8 ? $C['gold'] : $C['green']);
              ?>
            <tr style="background:<?= h($bg) ?>">
              <td style="padding:9px 12px;color:<?= h($C['muted']) ?>;<?= h($stylesMono) ?>"><?= $i + 1 ?></td>
              <td style="padding:9px 12px;color:<?= h($C['text']) ?>;font-weight:500;max-width:260px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= h((string) $c['customer']) ?></td>
              <td style="padding:9px 12px;color:<?= h($C['teal']) ?>;text-align:right;<?= h($stylesMono) ?>"><?= h(fmt_audf((float) $c['revenue'])) ?></td>
              <td style="padding:9px 12px;color:<?= h($C['muted']) ?>;text-align:right;<?= h($stylesMono) ?>"><?= h(fmt_audf((float) $c['prevRevenue'])) ?></td>
              <td style="padding:9px 12px;text-align:right;<?= h($stylesMono) ?>;color:<?= ($rc !== null && $rc >= 0) ? h($C['green']) : h($C['rose']) ?>"><?= $rc === null ? '—' : (($c['revenue'] - $c['prevRevenue']) >= 0 ? '+' : '') . h(fmt_audf((float) $c['revenue'] - (float) $c['prevRevenue'])) ?></td>
              <td style="padding:9px 12px;text-align:right;<?= h($stylesMono) ?>"><?= h(fmt_num((float) round($c['units']))) ?></td>
              <td style="padding:9px 12px;text-align:right;<?= h($stylesMono) ?>"><?= h(fmt_num((float) $c['invoices'])) ?></td>
              <td style="padding:9px 12px;text-align:right;<?= h($stylesMono) ?>;font-weight:700;color:<?= h($mcol) ?>"><?= h(fmt_pct($mc)) ?></td>
              <td style="padding:9px 12px;text-align:right;<?= h($stylesMono) ?>;font-weight:700;color:<?= ($rc !== null && $rc >= 0) ? h($C['green']) : h($C['rose']) ?>"><?= $rc === null ? '—' : (($rc >= 0 ? '▲' : '▼') . ' ' . number_format(abs($rc), 1) . '%') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($custTab === 'gained' && $custAnalytics): ?>
  <div style="background:<?= h($C['card']) ?>;border:1px solid <?= h($C['border']) ?>;border-radius:12px;padding:20px">
    <div style="margin-bottom:16px">
      <div style="font-size:13px;font-weight:700;color:<?= h($C['text']) ?>">New Customers in <?= (int) $custCurYear ?></div>
      <div style="font-size:11px;color:<?= h($C['muted']) ?>;margin-top:2px"><?= count($custAnalytics['gained']) ?> accounts not seen in <?= (int) $custCmpYear ?> (<?= h($custAnalytics['periodLabel']) ?>) — total new revenue: <?= h(fmt_audf((float) $custAnalytics['gainedRev'])) ?></div>
    </div>
    <div style="overflow-x:auto">
      <table style="width:100%;border-collapse:collapse;font-size:12px">
        <thead><tr>
          <?php foreach (['#', 'Customer', 'Revenue ex-GST', 'Rev %', 'Units', 'Invoices', 'Gross Profit', 'GP Margin'] as $hc):
              $al = in_array($hc, ['#', 'Customer'], true) ? 'left' : 'right'; ?>
            <th style="text-align:<?= h($al) ?>;padding:8px 12px;color:<?= h($C['muted']) ?>;border-bottom:1px solid <?= h($C['border']) ?>;font-weight:600;font-size:11px"><?= h($hc) ?></th>
          <?php endforeach; ?>
        </tr></thead>
        <tbody>
          <?php foreach ($custAnalytics['gained'] as $i => $c):
              $bg = $i % 2 === 0 ? $C['surface'] : 'transparent';
              $mc = (float) $c['margin'];
              $mcol = $mc < 0 ? $C['rose'] : ($mc < 8 ? $C['gold'] : $C['green']);
              ?>
            <tr style="background:<?= h($bg) ?>">
              <td style="padding:9px 12px;color:<?= h($C['muted']) ?>;<?= h($stylesMono) ?>"><?= $i + 1 ?></td>
              <td style="padding:9px 12px;color:<?= h($C['text']) ?>;font-weight:500;max-width:280px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><span style="color:<?= h($C['green']) ?>;margin-right:6px;font-size:10px">🟢 NEW</span><?= h((string) $c['customer']) ?></td>
              <td style="padding:9px 12px;color:<?= h($C['teal']) ?>;text-align:right;<?= h($stylesMono) ?>"><?= h(fmt_audf((float) $c['revenue'])) ?></td>
              <td style="padding:9px 12px;color:<?= h($C['muted']) ?>;text-align:right;<?= h($stylesMono) ?>"><?= h(fmt_pct(((float) $c['revenue'] / max(1e-9, (float) $custCurData['totals']['revenue'])) * 100)) ?></td>
              <td style="padding:9px 12px;text-align:right;<?= h($stylesMono) ?>"><?= h(fmt_num((float) round($c['units']))) ?></td>
              <td style="padding:9px 12px;text-align:right;<?= h($stylesMono) ?>"><?= h(fmt_num((float) $c['invoices'])) ?></td>
              <td style="padding:9px 12px;text-align:right;<?= h($stylesMono) ?>;color:<?= ((float) $c['profit'] < 0) ? h($C['rose']) : h($C['gold']) ?>"><?= h(fmt_audf((float) $c['profit'])) ?></td>
              <td style="padding:9px 12px;text-align:right;<?= h($stylesMono) ?>;font-weight:700;color:<?= h($mcol) ?>"><?= h(fmt_pct($mc)) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($custTab === 'lost' && $custAnalytics && $custCmpData): ?>
  <div style="background:<?= h($C['card']) ?>;border:1px solid <?= h($C['border']) ?>;border-radius:12px;padding:20px">
    <div style="margin-bottom:16px">
      <div style="font-size:13px;font-weight:700;color:<?= h($C['text']) ?>">Lost Customers — not seen in <?= (int) $custCurYear ?></div>
      <div style="font-size:11px;color:<?= h($C['muted']) ?>;margin-top:2px">Bought in <?= (int) $custCmpYear ?> (<?= h($custAnalytics['periodLabel']) ?>) but not in <?= (int) $custCurYear ?> — <?= h(fmt_audf((float) $custAnalytics['lostRev'])) ?> same-period revenue at risk</div>
    </div>
    <div style="overflow-x:auto">
      <table style="width:100%;border-collapse:collapse;font-size:12px">
        <thead><tr>
          <?php foreach (['#', 'Customer', (int) $custCmpYear . ' Same-Period Rev', 'Rev % of ' . (int) $custCmpYear . ' period', 'Units', 'Invoices', 'GP Margin'] as $hc):
              $al = in_array($hc, ['#', 'Customer'], true) ? 'left' : 'right'; ?>
            <th style="text-align:<?= h($al) ?>;padding:8px 12px;color:<?= h($C['muted']) ?>;border-bottom:1px solid <?= h($C['border']) ?>;font-weight:600;font-size:11px;white-space:nowrap"><?= h((string) $hc) ?></th>
          <?php endforeach; ?>
        </tr></thead>
        <tbody>
          <?php foreach ($custAnalytics['lost'] as $i => $c):
              $bg = $i % 2 === 0 ? $C['surface'] : 'transparent';
              $mc = (float) $c['margin'];
              $mcol = $mc < 0 ? $C['rose'] : ($mc < 8 ? $C['gold'] : $C['green']);
              $cmpTot = (float) ($custCmpData['totals']['revenue'] ?? 1);
              ?>
            <tr style="background:<?= h($bg) ?>">
              <td style="padding:9px 12px;color:<?= h($C['muted']) ?>;<?= h($stylesMono) ?>"><?= $i + 1 ?></td>
              <td style="padding:9px 12px;color:<?= h($C['text']) ?>;font-weight:500;max-width:280px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><span style="color:<?= h($C['rose']) ?>;margin-right:6px;font-size:10px">🔴 LOST</span><?= h((string) $c['customer']) ?></td>
              <td style="padding:9px 12px;color:<?= h($C['rose']) ?>;text-align:right;<?= h($stylesMono) ?>"><?= h(fmt_audf((float) $c['samePeriodRevenue'])) ?></td>
              <td style="padding:9px 12px;color:<?= h($C['muted']) ?>;text-align:right;<?= h($stylesMono) ?>"><?= h(fmt_pct(((float) $c['samePeriodRevenue'] / max(1e-9, $cmpTot)) * 100)) ?></td>
              <td style="padding:9px 12px;text-align:right;<?= h($stylesMono) ?>"><?= h(fmt_num((float) round($c['units']))) ?></td>
              <td style="padding:9px 12px;text-align:right;<?= h($stylesMono) ?>"><?= h(fmt_num((float) $c['invoices'])) ?></td>
              <td style="padding:9px 12px;text-align:right;<?= h($stylesMono) ?>;font-weight:700;color:<?= h($mcol) ?>"><?= h(fmt_pct($mc)) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>
