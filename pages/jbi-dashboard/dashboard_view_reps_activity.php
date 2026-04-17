<?php
/** Reps + Activity & Areas */
if (!function_exists('chart_block')) {
    return;
}

if ($view === 'reps' && $latestData):
    ?>
<div style="display:grid;gap:20px">
  <div style="display:flex;gap:16px;flex-wrap:wrap">
    <?php foreach ($latestData['reps'] as $i => $rep):
        $yc = YEAR_COLORS[$i % count(YEAR_COLORS)];
        $margin = (float) $rep['margin'];
        $margCol = $margin >= 12 ? $C['green'] : ($margin >= 8 ? $C['gold'] : $C['rose']);
        ?>
      <div style="background:<?= h($C['card']) ?>;border:1px solid <?= h($C['border']) ?>;border-radius:12px;padding:24px;flex:1;min-width:220px;border-left:4px solid <?= h($yc) ?>">
        <div style="font-size:18px;font-weight:800;color:<?= h($C['text']) ?>;margin-bottom:16px">👤 <?= h((string) $rep['rep']) ?></div>
        <?php
        $rows = [
            ['Revenue ex-GST', fmt_audf((float) $rep['revenue']), $C['teal']],
            ['Revenue inc-GST', fmt_audf((float) $rep['revenue'] * 1.1), $C['muted']],
            ['Gross Profit', fmt_audf((float) $rep['profit']), $C['gold']],
            ['GP Margin', fmt_pct($margin), $margCol],
            ['Units Sold', fmt_num((float) round($rep['units'])), $C['blue']],
            ['Invoices', fmt_num((float) $rep['invoices']), $C['text']],
            ['Rev Share', fmt_pct(((float) $rep['revenue'] / max(1e-9, (float) $latestData['totals']['revenue'])) * 100), $C['purple']],
        ];
        foreach ($rows as [$lb, $vl, $col]):
            ?>
          <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid <?= h($C['border']) ?>">
            <span style="font-size:11px;color:<?= h($C['muted']) ?>"><?= h($lb) ?></span>
            <span style="font-size:13px;color:<?= h($col) ?>;<?= h($stylesMono) ?>;font-weight:600"><?= h($vl) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
  </div>
  <div style="background:<?= h($C['card']) ?>;border:1px solid <?= h($C['border']) ?>;border-radius:12px;padding:20px">
    <div style="margin-bottom:16px">
      <div style="font-size:13px;font-weight:700;color:<?= h($C['text']) ?>;letter-spacing:0.06em;text-transform:uppercase">Monthly Revenue by Rep — <?= (int) $latestYear ?></div>
    </div>
    <?php chart_block('rep_monthly', 280); ?>
  </div>
</div>
<?php endif; ?>

<?php
if ($view === 'activity' && $latestData && $actD && $areaD):
    $DAYS_ORDER = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    $DAYS_IDX = ['Mon' => 1, 'Tue' => 2, 'Wed' => 3, 'Thu' => 4, 'Fri' => 5, 'Sat' => 6, 'Sun' => 0];
    $dowOrdered = [];
    foreach ($DAYS_ORDER as $d) {
        foreach ($actD['byDayOfWeek'] as $r) {
            if (($r['day'] ?? '') === $d) {
                $dowOrdered[] = $r;
                break;
            }
        }
    }
    $maxDowInv = 0;
    foreach ($dowOrdered as $r) {
        $maxDowInv = max($maxDowInv, (int) ($r['invoices'] ?? 0));
    }
    $BIZ_HOURS = range(6, 20);
    $heatmap = $actD['heatmap'] ?? [];
    $heatVals = array_map('floatval', array_values($heatmap));
    $maxHeatVal = $heatVals === [] ? 1.0 : max(1.0, ...$heatVals);
    $heatColor = static function (int $v) use ($maxHeatVal, $C): string {
        if ($v <= 0) {
            return $C['surface'];
        }
        $pct = $v / $maxHeatVal;
        if ($pct < 0.25) {
            return '#1E3A5F';
        }
        if ($pct < 0.5) {
            return '#1A6B8A';
        }
        if ($pct < 0.75) {
            return '#00897B';
        }

        return '#00D4C8';
    };
    $totalAreaRev = array_sum(array_map(static fn ($a) => (float) $a['revenue'], $areaD['byArea']));
    ?>
<div style="display:grid;gap:20px">
  <div style="display:flex;align-items:center;justify-content:space-between;background:<?= h($C['card']) ?>;border:1px solid <?= h($C['border']) ?>;border-radius:12px;padding:14px 20px">
    <div>
      <div style="font-size:13px;font-weight:700;color:<?= h($C['text']) ?>;text-transform:uppercase;letter-spacing:0.06em">⏱ Order Activity Analysis</div>
      <div style="font-size:11px;color:<?= h($C['muted']) ?>;margin-top:2px">Busiest days &amp; times — unique invoice counts</div>
    </div>
    <div style="display:flex;gap:5px">
      <?php foreach ($years as $i => $y):
          $yc = YEAR_COLORS[$i % count(YEAR_COLORS)];
          $sel = $actYr === $y;
          ?>
        <form method="post" action="process.php" style="margin:0">
          <input type="hidden" name="action" value="set_activity_year">
          <input type="hidden" name="year" value="<?= (int) $y ?>">
          <button type="submit" style="background:<?= $sel ? h($yc) . '25' : 'transparent' ?>;color:<?= $sel ? h($yc) : h($C['muted']) ?>;border:1px solid <?= $sel ? h($yc) : h($C['dim']) ?>;border-radius:16px;padding:3px 14px;cursor:pointer;font-size:12px;font-weight:700;font-family:monospace"><?= (int) $y ?></button>
        </form>
      <?php endforeach; ?>
    </div>
  </div>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
    <div style="background:<?= h($C['card']) ?>;border:1px solid <?= h($C['border']) ?>;border-radius:12px;padding:20px">
      <div style="margin-bottom:16px">
        <div style="font-size:13px;font-weight:700;color:<?= h($C['text']) ?>;letter-spacing:0.06em;text-transform:uppercase">Orders by Day of Week — <?= (int) $actYr ?></div>
        <div style="font-size:11px;color:<?= h($C['muted']) ?>;margin-top:2px">Unique invoices Mon–Sun</div>
      </div>
      <div style="display:flex;flex-direction:column;gap:8px;margin-top:8px">
        <?php foreach ($dowOrdered as $r):
            if ($r === null) {
                continue;
            }
            $pct = $maxDowInv ? (float) $r['invoices'] / $maxDowInv : 0;
            $wk = ($r['day'] === 'Sat' || $r['day'] === 'Sun');
            ?>
          <div style="display:flex;align-items:center;gap:10px">
            <div style="width:32px;font-size:12px;font-weight:700;color:<?= $wk ? h($C['muted']) : h($C['text']) ?>;<?= h($stylesMono) ?>;flex-shrink:0"><?= h((string) $r['day']) ?></div>
            <div style="flex:1;height:28px;background:<?= h($C['surface']) ?>;border-radius:4px;overflow:hidden;position:relative">
              <div style="width:<?= $pct * 100 ?>%;height:100%;background:<?= $wk ? h($C['dim']) : 'linear-gradient(90deg,' . h($C['teal']) . ',#00897B)' ?>;border-radius:4px"></div>
              <span style="position:absolute;right:8px;top:50%;transform:translateY(-50%);font-size:11px;color:<?= h($C['muted']) ?>;<?= h($stylesMono) ?>"><?= h(fmt_num((float) $r['invoices'])) ?></span>
            </div>
            <div style="width:60px;font-size:11px;color:<?= h($C['muted']) ?>;text-align:right;<?= h($stylesMono) ?>;flex-shrink:0"><?= h(fmt_aud((float) $r['revenue'])) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <div style="background:<?= h($C['card']) ?>;border:1px solid <?= h($C['border']) ?>;border-radius:12px;padding:20px">
      <div style="margin-bottom:16px">
        <div style="font-size:13px;font-weight:700;color:<?= h($C['text']) ?>;letter-spacing:0.06em;text-transform:uppercase">Orders by Hour of Day — <?= (int) $actYr ?></div>
        <div style="font-size:11px;color:<?= h($C['muted']) ?>;margin-top:2px">Unique invoices, business hours</div>
      </div>
      <?php chart_block('act_hour_bar', 220); ?>
    </div>
  </div>

  <div style="background:<?= h($C['card']) ?>;border:1px solid <?= h($C['border']) ?>;border-radius:12px;padding:20px">
    <div style="margin-bottom:16px">
      <div style="font-size:13px;font-weight:700;color:<?= h($C['text']) ?>;letter-spacing:0.06em;text-transform:uppercase">Order Heatmap — Day × Hour (<?= (int) $actYr ?>)</div>
      <div style="font-size:11px;color:<?= h($C['muted']) ?>;margin-top:2px">Darker = more invoices. Business hours 6am–8pm shown.</div>
    </div>
    <div style="overflow-x:auto">
      <table style="border-collapse:separate;border-spacing:3px;font-size:11px">
        <thead>
          <tr>
            <th style="width:36px;color:<?= h($C['muted']) ?>;font-size:10px;padding:4px 6px;text-align:left"></th>
            <?php foreach ($BIZ_HOURS as $h): ?>
              <th style="color:<?= h($C['muted']) ?>;font-size:10px;padding:4px 6px;text-align:center;min-width:38px;font-weight:600"><?= $h < 12 ? $h . 'am' : ($h === 12 ? '12pm' : ($h - 12) . 'pm') ?></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($DAYS_ORDER as $day):
              $dowIdx = $DAYS_IDX[$day];
              $wk = $day === 'Sat' || $day === 'Sun';
              ?>
            <tr>
              <td style="color:<?= $wk ? h($C['dim']) : h($C['muted']) ?>;font-size:11px;font-weight:700;padding:4px 8px 4px 0;<?= h($stylesMono) ?>"><?= h($day) ?></td>
              <?php foreach ($BIZ_HOURS as $h):
                  $val = (int) ($heatmap[$dowIdx . '_' . $h] ?? 0);
                  $bg = $heatColor($val);
                  $tc = $val > $maxHeatVal * 0.5 ? $C['bg'] : $C['muted'];
                  ?>
                <td title="<?= h($day . ' ' . $val . ' orders') ?>" style="width:38px;height:32px;border-radius:4px;background:<?= h($bg) ?>;text-align:center;vertical-align:middle;color:<?= h($tc) ?>;font-size:10px;font-weight:600;cursor:default;<?= h($stylesMono) ?>"><?= $val > 0 ? (string) $val : '' ?></td>
              <?php endforeach; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <div style="display:flex;align-items:center;gap:6px;margin-top:12px">
        <span style="font-size:10px;color:<?= h($C['muted']) ?>">Low</span>
        <?php foreach (['#1E3A5F', '#1A6B8A', '#00897B', '#00D4C8'] as $cc): ?>
          <div style="width:24px;height:16px;border-radius:3px;background:<?= h($cc) ?>"></div>
        <?php endforeach; ?>
        <span style="font-size:10px;color:<?= h($C['muted']) ?>">High</span>
      </div>
    </div>
  </div>

  <div style="display:flex;align-items:center;justify-content:space-between;background:<?= h($C['card']) ?>;border:1px solid <?= h($C['border']) ?>;border-radius:12px;padding:14px 20px;margin-top:8px">
    <div>
      <div style="font-size:13px;font-weight:700;color:<?= h($C['text']) ?>;text-transform:uppercase;letter-spacing:0.06em">📍 Delivery Area Analysis</div>
      <div style="font-size:11px;color:<?= h($C['muted']) ?>;margin-top:2px">From Delivery_Profile field — area extracted after dash</div>
    </div>
    <div style="display:flex;gap:5px">
      <?php foreach ($years as $i => $y):
          $yc = YEAR_COLORS[$i % count(YEAR_COLORS)];
          $sel = $areaYr === $y;
          ?>
        <form method="post" action="process.php" style="margin:0">
          <input type="hidden" name="action" value="set_area_year">
          <input type="hidden" name="year" value="<?= (int) $y ?>">
          <button type="submit" style="background:<?= $sel ? h($yc) . '25' : 'transparent' ?>;color:<?= $sel ? h($yc) : h($C['muted']) ?>;border:1px solid <?= $sel ? h($yc) : h($C['dim']) ?>;border-radius:16px;padding:3px 14px;cursor:pointer;font-size:12px;font-weight:700;font-family:monospace"><?= (int) $y ?></button>
        </form>
      <?php endforeach; ?>
    </div>
  </div>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
    <div style="background:<?= h($C['card']) ?>;border:1px solid <?= h($C['border']) ?>;border-radius:12px;padding:20px">
      <div style="margin-bottom:16px;font-size:13px;font-weight:700;color:<?= h($C['text']) ?>;letter-spacing:0.06em;text-transform:uppercase">Revenue by Delivery Area — <?= (int) $areaYr ?></div>
      <?php chart_block('area_rev', 240); ?>
    </div>
    <div style="background:<?= h($C['card']) ?>;border:1px solid <?= h($C['border']) ?>;border-radius:12px;padding:20px">
      <div style="margin-bottom:16px;font-size:13px;font-weight:700;color:<?= h($C['text']) ?>;letter-spacing:0.06em;text-transform:uppercase">Units by Delivery Area — <?= (int) $areaYr ?></div>
      <?php chart_block('area_units', 240); ?>
    </div>
  </div>

  <div style="background:<?= h($C['card']) ?>;border:1px solid <?= h($C['border']) ?>;border-radius:12px;padding:20px">
    <div style="margin-bottom:16px">
      <div style="font-size:13px;font-weight:700;color:<?= h($C['text']) ?>;letter-spacing:0.06em;text-transform:uppercase">Area Detail — <?= (int) $areaYr ?></div>
      <div style="font-size:11px;color:<?= h($C['muted']) ?>;margin-top:2px">Sorted by revenue</div>
    </div>
    <table style="width:100%;border-collapse:collapse;font-size:12px">
      <thead>
        <tr>
          <?php foreach (['#', 'Area', 'Revenue ex-GST', 'Rev %', 'Units', 'Invoices', 'Customers', 'Gross Profit', 'GP Margin'] as $hc):
              $al = in_array($hc, ['#', 'Area'], true) ? 'left' : 'right'; ?>
            <th style="text-align:<?= h($al) ?>;padding:8px 12px;color:<?= h($C['muted']) ?>;border-bottom:1px solid <?= h($C['border']) ?>;font-weight:600;font-size:11px;white-space:nowrap"><?= h($hc) ?></th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($areaD['byArea'] as $i => $a):
            $bg = $i % 2 === 0 ? $C['surface'] : 'transparent';
            $mc = (float) $a['margin'];
            $mcol = $mc < 0 ? $C['rose'] : ($mc < 8 ? $C['gold'] : $C['green']);
            $yc = YEAR_COLORS[$i % count(YEAR_COLORS)];
            ?>
          <tr style="background:<?= h($bg) ?>">
            <td style="padding:9px 12px;color:<?= h($C['muted']) ?>;<?= h($stylesMono) ?>"><?= $i + 1 ?></td>
            <td style="padding:9px 12px;color:<?= h($C['text']) ?>;font-weight:600">
              <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:<?= h($yc) ?>;margin-right:8px"></span><?= h((string) $a['area']) ?>
            </td>
            <td style="padding:9px 12px;color:<?= h($C['teal']) ?>;text-align:right;<?= h($stylesMono) ?>"><?= h(fmt_audf((float) $a['revenue'])) ?></td>
            <td style="padding:9px 12px;color:<?= h($C['muted']) ?>;text-align:right;<?= h($stylesMono) ?>"><?= h(fmt_pct($totalAreaRev > 0 ? ((float) $a['revenue'] / $totalAreaRev) * 100 : 0)) ?></td>
            <td style="padding:9px 12px;text-align:right;<?= h($stylesMono) ?>"><?= h(fmt_num((float) round($a['units']))) ?></td>
            <td style="padding:9px 12px;text-align:right;<?= h($stylesMono) ?>"><?= h(fmt_num((float) $a['invoices'])) ?></td>
            <td style="padding:9px 12px;text-align:right;<?= h($stylesMono) ?>"><?= h(fmt_num((float) $a['customers'])) ?></td>
            <td style="padding:9px 12px;text-align:right;<?= h($stylesMono) ?>;color:<?= ((float) $a['profit'] < 0) ? h($C['rose']) : h($C['gold']) ?>"><?= h(fmt_audf((float) $a['profit'])) ?></td>
            <td style="padding:9px 12px;text-align:right;<?= h($stylesMono) ?>;font-weight:700;color:<?= h($mcol) ?>"><?= h(fmt_pct($mc)) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
