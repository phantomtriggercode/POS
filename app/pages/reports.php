<?php
require_role('manager');
$from = in_str($_GET, 'from', 10);
$to = in_str($_GET, 'to', 10);
$from = valid_date($from) ? $from : date('Y-m-01');
$to = valid_date($to) ? $to : today();
if ($from > $to) {
    [$from, $to] = [$to, $from];
}
[$a, $b] = date_bounds($from, $to);

$byGrade = purchase_summary($from, $to);
$payments = payments_summary($from, $to);
$byClerk = q_all(
    "SELECT u.full_name, COUNT(*) AS n, SUM(p.net_kg) AS kg, SUM(p.amount_paid) AS paid
     FROM purchases p JOIN users u ON u.id = p.created_by
     WHERE p.status = 'valid' AND p.created_at >= ? AND p.created_at < ?
     GROUP BY u.id, u.full_name ORDER BY kg DESC",
    [$a, $b]
);
$topFarmers = q_all(
    "SELECT f.id, f.full_name, f.code, COUNT(*) AS n, SUM(p.net_kg) AS kg, SUM(p.gross_amount) AS amount
     FROM purchases p JOIN farmers f ON f.id = p.farmer_id
     WHERE p.status = 'valid' AND p.created_at >= ? AND p.created_at < ?
     GROUP BY f.id, f.full_name, f.code ORDER BY kg DESC LIMIT 20",
    [$a, $b]
);
$dispatched = q_all(
    "SELECT c.name AS commodity_name, g.name AS grade_name, COUNT(*) AS n, SUM(d.net_kg) AS kg, SUM(d.sale_amount) AS amount
     FROM dispatches d JOIN grades g ON g.id = d.grade_id JOIN commodities c ON c.id = g.commodity_id
     WHERE d.status = 'valid' AND d.created_at >= ? AND d.created_at < ?
     GROUP BY c.id, c.name, c.sort_order, g.id, g.name, g.sort_order ORDER BY c.sort_order, g.sort_order",
    [$a, $b]
);
$voids = (int) q_val("SELECT COUNT(*) FROM purchases WHERE status = 'void' AND created_at >= ? AND created_at < ?", [$a, $b]);

$t = ['n' => 0, 'kg' => 0, 'amount' => 0, 'deducted' => 0, 'paid' => 0];
foreach ($byGrade as $r) {
    foreach ($t as $k => $_) {
        $t[$k] += (float) $r[$k];
    }
}

layout_start('Reports', 'reports');
page_head('Reports', button_link('Export purchases CSV', url('purchases', ['from' => $from, 'to' => $to, 'status' => 'all', 'export' => 'csv'])) . '<button class="btn btn-ghost" type="button" data-print>Print</button>');
?>
<form class="filters card no-print" method="get">
  <input type="hidden" name="p" value="reports">
  <label>From<input type="date" name="from" value="<?= e($from) ?>"></label>
  <label>To<input type="date" name="to" value="<?= e($to) ?>"></label>
  <button class="btn" type="submit">Run report</button>
  <span class="quick">
    <a href="<?= e(url('reports', ['from' => today(), 'to' => today()])) ?>">Today</a>
    <a href="<?= e(url('reports', ['from' => date('Y-m-d', strtotime('monday this week')), 'to' => today()])) ?>">This week</a>
    <a href="<?= e(url('reports', ['from' => date('Y-m-01'), 'to' => today()])) ?>">This month</a>
    <a href="<?= e(url('reports', ['from' => date('Y-01-01'), 'to' => today()])) ?>">This year</a>
  </span>
</form>
<p class="muted print-only"><?= e(setting('company_name', '')) ?> · Report <?= e(fmt_d($from)) ?> – <?= e(fmt_d($to)) ?></p>

<div class="stats">
  <div class="stat"><span>Receipts</span><strong><?= num($t['n']) ?></strong><?= $voids ? '<small>' . $voids . ' voided</small>' : '' ?></div>
  <div class="stat"><span>Net bought</span><strong><?= kg($t['kg']) ?></strong></div>
  <div class="stat"><span>Purchase value</span><strong><?= money($t['amount']) ?></strong></div>
  <div class="stat"><span>Average price</span><strong><?= $t['kg'] > 0 ? money(round($t['amount'] / $t['kg'])) : '—' ?></strong><small>per kg, all produce</small></div>
</div>

<section class="card">
  <h2>Purchases by produce</h2>
  <div class="scroll">
  <table class="tbl">
    <thead><tr><th>Produce</th><th class="r">Receipts</th><th class="r">Bags</th><th class="r">Net kg</th><th class="r">Avg. price/kg</th><th class="r">Value</th><th class="r">Advance recovered</th><th class="r">Paid out</th></tr></thead>
    <tbody>
    <?php foreach ($byGrade as $r): ?>
      <tr><td><?= e($r['commodity_name'] . ' — ' . $r['grade_name']) ?></td><td class="r"><?= num($r['n']) ?></td><td class="r"><?= num($r['bags']) ?></td><td class="r"><?= num($r['kg'], 2) ?></td><td class="r"><?= num($r['kg'] > 0 ? $r['amount'] / $r['kg'] : 0) ?></td><td class="r"><?= num($r['amount']) ?></td><td class="r"><?= num($r['deducted']) ?></td><td class="r"><?= num($r['paid']) ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$byGrade): ?><tr><td colspan="8" class="muted">No purchases in this period.</td></tr><?php else: ?>
      <tr class="total"><td>Total</td><td class="r"><?= num($t['n']) ?></td><td></td><td class="r"><?= num($t['kg'], 2) ?></td><td></td><td class="r"><?= num($t['amount']) ?></td><td class="r"><?= num($t['deducted']) ?></td><td class="r"><?= num($t['paid']) ?></td></tr>
    <?php endif; ?>
    </tbody>
  </table>
  </div>
</section>

<div class="grid2">
  <section class="card">
    <h2>Money paid out by method</h2>
    <table class="tbl">
      <thead><tr><th>Method</th><th class="r">Purchases</th><th class="r">Advances</th><th class="r">Total</th></tr></thead>
      <tbody>
      <?php foreach ($payments as $m => $r): ?>
        <tr><td><?= e(payment_label($m)) ?></td><td class="r"><?= num($r['purchases']) ?></td><td class="r"><?= num($r['advances']) ?></td><td class="r"><strong><?= num($r['purchases'] + $r['advances']) ?></strong></td></tr>
      <?php endforeach; ?>
      <?php if (!$payments): ?><tr><td colspan="4" class="muted">Nothing paid in this period.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </section>
  <section class="card">
    <h2>By clerk</h2>
    <table class="tbl">
      <thead><tr><th>Clerk</th><th class="r">Receipts</th><th class="r">Net kg</th><th class="r">Paid</th></tr></thead>
      <tbody>
      <?php foreach ($byClerk as $r): ?>
        <tr><td><?= e($r['full_name']) ?></td><td class="r"><?= num($r['n']) ?></td><td class="r"><?= num($r['kg'], 2) ?></td><td class="r"><?= num($r['paid']) ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$byClerk): ?><tr><td colspan="4" class="muted">—</td></tr><?php endif; ?>
      </tbody>
    </table>
  </section>
</div>

<div class="grid2">
  <section class="card">
    <h2>Top farmers</h2>
    <table class="tbl">
      <thead><tr><th>Farmer</th><th class="r">Receipts</th><th class="r">Net kg</th><th class="r">Value</th></tr></thead>
      <tbody>
      <?php foreach ($topFarmers as $r): ?>
        <tr><td><a href="<?= e(url('farmer', ['id' => $r['id']])) ?>"><?= e($r['full_name']) ?></a> <small class="muted"><?= e($r['code']) ?></small></td><td class="r"><?= num($r['n']) ?></td><td class="r"><?= num($r['kg'], 2) ?></td><td class="r"><?= num($r['amount']) ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$topFarmers): ?><tr><td colspan="4" class="muted">—</td></tr><?php endif; ?>
      </tbody>
    </table>
  </section>
  <section class="card">
    <h2>Dispatched</h2>
    <table class="tbl">
      <thead><tr><th>Produce</th><th class="r">Loads</th><th class="r">Net kg</th><th class="r">Sale value</th></tr></thead>
      <tbody>
      <?php foreach ($dispatched as $r): ?>
        <tr><td><?= e($r['commodity_name'] . ' — ' . $r['grade_name']) ?></td><td class="r"><?= num($r['n']) ?></td><td class="r"><?= num($r['kg'], 2) ?></td><td class="r"><?= $r['amount'] !== null ? num($r['amount']) : '—' ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$dispatched): ?><tr><td colspan="4" class="muted">No dispatches in this period.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </section>
</div>
<?php
layout_end();
