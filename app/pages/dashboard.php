<?php
require_role('clerk');
$todayByGrade = purchase_summary(today(), today());
$todayPay = payments_summary(today(), today());
$prices = current_prices();
$stock = array_filter(stock_levels(), fn ($s) => abs($s['stock_kg']) > 0.001);
$recent = q_all(
    "SELECT p.id, p.receipt_no, p.net_kg, p.amount_paid, p.status, p.created_at, f.full_name AS farmer_name, g.name AS grade_name, c.name AS commodity_name
     FROM purchases p JOIN farmers f ON f.id = p.farmer_id JOIN grades g ON g.id = p.grade_id JOIN commodities c ON c.id = g.commodity_id
     ORDER BY p.id DESC LIMIT 8"
);
$todayCount = array_sum(array_map(fn ($r) => (int) $r['n'], $todayByGrade));
$todayKg = array_sum(array_map(fn ($r) => (float) $r['kg'], $todayByGrade));
$todayPaid = array_sum(array_map(fn ($r) => (int) $r['paid'], $todayByGrade));
$missingPrices = array_filter($prices, fn ($p) => $p['price'] === null);

layout_start('Dashboard', 'dashboard');
page_head('Today · ' . date('l d M Y'), button_link('+ New purchase', url('purchase_new'), 'btn btn-primary'));
?>
<?php if ($missingPrices): ?>
  <div class="flash flash-warn">No buying price set for: <?= e(implode(', ', array_map('grade_label', $missingPrices))) ?>.
  <?php if (has_role('manager')): ?><a href="<?= e(url('prices')) ?>">Set prices</a><?php else: ?>Ask a manager to set prices.<?php endif; ?></div>
<?php endif; ?>

<div class="stats">
  <div class="stat"><span>Purchases today</span><strong><?= num($todayCount) ?></strong></div>
  <div class="stat"><span>Net weight today</span><strong><?= kg($todayKg) ?></strong></div>
  <div class="stat"><span>Paid to farmers today</span><strong><?= money($todayPaid) ?></strong></div>
  <div class="stat"><span>Advances outstanding</span><strong><?= money(total_outstanding_advances()) ?></strong></div>
</div>

<div class="grid2">
  <section class="card">
    <h2>Current buying prices</h2>
    <table class="tbl">
      <thead><tr><th>Produce</th><th class="r">Price / kg</th><th>Since</th></tr></thead>
      <tbody>
      <?php foreach ($prices as $p): ?>
        <tr><td><?= e(grade_label($p)) ?></td>
            <td class="r"><?= $p['price'] ? '<strong>' . money($p['price']['price_per_kg']) . '</strong>' : '<span class="muted">not set</span>' ?></td>
            <td class="muted"><?= $p['price'] ? e(fmt_dt($p['price']['effective_from'])) : '' ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </section>

  <section class="card">
    <h2>Stock in store</h2>
    <table class="tbl">
      <thead><tr><th>Produce</th><th class="r">Bags</th><th class="r">Net kg</th></tr></thead>
      <tbody>
      <?php foreach ($stock as $s): ?>
        <tr><td><?= e(grade_label($s)) ?></td><td class="r"><?= num($s['bags']) ?></td><td class="r"><strong><?= num($s['stock_kg'], 2) ?></strong></td></tr>
      <?php endforeach; ?>
      <?php if (!$stock): ?><tr><td colspan="3" class="muted">The store is empty.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </section>
</div>

<div class="grid2">
  <section class="card">
    <h2>Today by produce</h2>
    <?php if (!$todayByGrade): ?><p class="muted">No purchases yet today.</p><?php else: ?>
    <table class="tbl">
      <thead><tr><th>Produce</th><th class="r">Receipts</th><th class="r">Net kg</th><th class="r">Value</th></tr></thead>
      <tbody>
      <?php foreach ($todayByGrade as $r): ?>
        <tr><td><?= e($r['commodity_name'] . ' — ' . $r['grade_name']) ?></td><td class="r"><?= num($r['n']) ?></td><td class="r"><?= num($r['kg'], 2) ?></td><td class="r"><?= money($r['amount']) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
    <?php if ($todayPay): ?>
    <h3>Money out today</h3>
    <table class="tbl">
      <thead><tr><th>Method</th><th class="r">Purchases</th><th class="r">Advances</th></tr></thead>
      <tbody>
      <?php foreach ($todayPay as $m => $r): ?>
        <tr><td><?= e(payment_label($m)) ?></td><td class="r"><?= money($r['purchases']) ?></td><td class="r"><?= money($r['advances']) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </section>

  <section class="card">
    <h2>Latest receipts</h2>
    <?php if (!$recent): ?><p class="muted">No purchases recorded yet.</p><?php else: ?>
    <table class="tbl">
      <tbody>
      <?php foreach ($recent as $r): ?>
        <tr><td><a href="<?= e(url('purchase', ['id' => $r['id']])) ?>"><?= e($r['receipt_no']) ?></a><?= status_badge($r['status']) ?><br><small class="muted"><?= e(fmt_dt($r['created_at'])) ?></small></td>
            <td><?= e($r['farmer_name']) ?><br><small class="muted"><?= e($r['commodity_name'] . ' — ' . $r['grade_name']) ?></small></td>
            <td class="r"><?= num($r['net_kg'], 2) ?> kg<br><small><?= money($r['amount_paid']) ?></small></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </section>
</div>
<?php
layout_end();
