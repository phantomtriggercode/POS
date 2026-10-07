<?php
require_role('clerk');
$id = (int) ($_GET['id'] ?? 0);
$f = farmer($id);
if (!$f) {
    redirect('farmers');
}
$balance = farmer_balance($id);
$purchases = q_all(
    'SELECT p.*, g.name AS grade_name, c.name AS commodity_name FROM purchases p
     JOIN grades g ON g.id = p.grade_id JOIN commodities c ON c.id = g.commodity_id
     WHERE p.farmer_id = ? ORDER BY p.id DESC LIMIT 100',
    [$id]
);
$advances = q_all('SELECT a.*, u.full_name AS by_name FROM advances a JOIN users u ON u.id = a.created_by WHERE a.farmer_id = ? ORDER BY a.id DESC LIMIT 100', [$id]);
$totals = q_one("SELECT COUNT(*) AS n, COALESCE(SUM(net_kg), 0) AS kg, COALESCE(SUM(gross_amount), 0) AS amount FROM purchases WHERE farmer_id = ? AND status = 'valid'", [$id]);

$actions = '';
if ($f['active']) {
    $actions .= button_link('+ Purchase', url('purchase_new', ['farmer_id' => $id]), 'btn btn-primary');
    if (has_role('manager')) {
        $actions .= button_link('+ Advance', url('advance_new', ['farmer_id' => $id]));
    }
}
$actions .= button_link('Edit', url('farmer_edit', ['id' => $id]), 'btn btn-ghost');

layout_start($f['full_name'], 'farmers');
page_head($f['full_name'], $actions);
?>
<div class="stats">
  <div class="stat"><span>Farmer code</span><strong><?= e($f['code']) ?></strong></div>
  <div class="stat"><span>Total sold</span><strong><?= kg($totals['kg']) ?></strong><small><?= num($totals['n']) ?> receipts · <?= money($totals['amount']) ?></small></div>
  <div class="stat<?= $balance > 0 ? ' stat-warn' : '' ?>"><span>Advance owed</span><strong><?= money($balance) ?></strong></div>
</div>
<?php if (!$f['active']): ?><div class="flash flash-warn">This farmer is inactive.</div><?php endif; ?>

<div class="grid2">
  <section class="card">
    <h2>Details</h2>
    <dl class="dl">
      <dt>Phone</dt><dd><?= $f['phone'] ? '<a href="tel:' . e(preg_replace('/[^0-9+]/', '', $f['phone'])) . '">' . e($f['phone']) . '</a>' : '—' ?></dd>
      <dt>Village</dt><dd><?= e($f['village'] ?: '—') ?></dd>
      <dt>ID number</dt><dd><?= e($f['id_number'] ?: '—') ?></dd>
      <dt>Farm</dt><dd><?= e($f['farm_location'] ?: '—') ?><?= $f['farm_size_ha'] !== null ? ' · ' . num($f['farm_size_ha'], 2) . ' ha' : '' ?></dd>
      <dt>GPS</dt><dd><?= $f['gps_lat'] !== null ? e(num($f['gps_lat'], 6) . ', ' . num($f['gps_lng'], 6)) : '—' ?></dd>
      <dt>Registered</dt><dd><?= e(fmt_d($f['created_at'])) ?></dd>
      <?php if ($f['notes']): ?><dt>Notes</dt><dd><?= nl2br(e($f['notes'])) ?></dd><?php endif; ?>
    </dl>
  </section>
  <section class="card">
    <h2>Advances</h2>
    <?php if (!$advances): ?><p class="muted">No advances.</p><?php else: ?>
    <table class="tbl">
      <thead><tr><th>Date</th><th class="r">Amount</th><th>Method</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($advances as $a): ?>
        <tr<?= $a['status'] === 'void' ? ' class="dim"' : '' ?>><td><?= e(fmt_d($a['created_at'])) ?><?= status_badge($a['status']) ?></td><td class="r"><?= money($a['amount']) ?></td><td><?= e(payment_label($a['payment_method'])) ?></td><td><small class="muted"><?= e($a['note']) ?></small></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </section>
</div>

<section class="card">
  <h2>Purchases</h2>
  <div class="scroll">
  <table class="tbl">
    <thead><tr><th>Receipt</th><th>Date</th><th>Produce</th><th class="r">Net kg</th><th class="r">Price</th><th class="r">Value</th><th class="r">Deducted</th><th class="r">Paid</th></tr></thead>
    <tbody>
    <?php foreach ($purchases as $p): ?>
      <tr<?= $p['status'] === 'void' ? ' class="dim"' : '' ?>>
        <td><a href="<?= e(url('purchase', ['id' => $p['id']])) ?>"><?= e($p['receipt_no']) ?></a><?= status_badge($p['status']) ?></td>
        <td><?= e(fmt_dt($p['created_at'])) ?></td>
        <td><?= e($p['commodity_name'] . ' — ' . $p['grade_name']) ?></td>
        <td class="r"><?= num($p['net_kg'], 2) ?></td>
        <td class="r"><?= num($p['price_per_kg']) ?></td>
        <td class="r"><?= num($p['gross_amount']) ?></td>
        <td class="r"><?= num($p['advance_deducted']) ?></td>
        <td class="r"><strong><?= num($p['amount_paid']) ?></strong></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$purchases): ?><tr><td colspan="8" class="muted">No purchases yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
</section>
<?php
layout_end();
