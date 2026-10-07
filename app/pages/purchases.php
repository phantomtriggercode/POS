<?php
require_role('clerk');
$from = in_str($_GET, 'from', 10);
$to = in_str($_GET, 'to', 10);
$from = valid_date($from) ? $from : date('Y-m-01');
$to = valid_date($to) ? $to : today();
$gradeId = (int) ($_GET['grade_id'] ?? 0);
$clerkId = (int) ($_GET['clerk'] ?? 0);
$search = in_str($_GET, 'q', 80);
$status = in_array($_GET['status'] ?? '', ['valid', 'void', 'all'], true) ? $_GET['status'] : 'valid';

[$a, $b] = date_bounds($from, $to);
$where = ['p.created_at >= ?', 'p.created_at < ?'];
$params = [$a, $b];
if ($gradeId) { $where[] = 'p.grade_id = ?'; $params[] = $gradeId; }
if ($clerkId) { $where[] = 'p.created_by = ?'; $params[] = $clerkId; }
if ($status !== 'all') { $where[] = 'p.status = ?'; $params[] = $status; }
if ($search !== '') {
    $where[] = '(f.full_name LIKE ? OR f.code LIKE ? OR p.receipt_no LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like);
}
$from_sql = ' FROM purchases p JOIN farmers f ON f.id = p.farmer_id JOIN grades g ON g.id = p.grade_id
              JOIN commodities c ON c.id = g.commodity_id JOIN users u ON u.id = p.created_by WHERE ' . implode(' AND ', $where);
$select = 'SELECT p.*, f.full_name AS farmer_name, f.code AS farmer_code, g.name AS grade_name, c.name AS commodity_name, u.full_name AS clerk_name';

if (($_GET['export'] ?? '') === 'csv' && has_role('manager')) {
    $rows = q($select . $from_sql . ' ORDER BY p.id', $params);
    audit('export', 'purchase', null, "$from to $to");
    send_csv("purchases_{$from}_{$to}.csv",
        ['Receipt', 'Date', 'Farmer code', 'Farmer', 'Commodity', 'Grade', 'Bags', 'Gross kg', 'Tare kg', 'Net kg', 'Moisture %', 'Price/kg', 'Value', 'Advance deducted', 'Paid', 'Method', 'Ref', 'Clerk', 'Status', 'Void reason', 'Notes'],
        (function () use ($rows) {
            foreach ($rows as $r) {
                yield [$r['receipt_no'], $r['created_at'], $r['farmer_code'], $r['farmer_name'], $r['commodity_name'], $r['grade_name'], $r['bags'], $r['gross_kg'], $r['tare_kg'], $r['net_kg'], $r['moisture_pct'], $r['price_per_kg'], $r['gross_amount'], $r['advance_deducted'], $r['amount_paid'], payment_label($r['payment_method']), $r['payment_ref'], $r['clerk_name'], $r['status'], $r['void_reason'], $r['notes']];
            }
        })()
    );
}

$sum = q_one('SELECT COUNT(*) AS n, COALESCE(SUM(p.net_kg), 0) AS kg, COALESCE(SUM(p.gross_amount), 0) AS amount, COALESCE(SUM(p.amount_paid), 0) AS paid' . $from_sql, $params);
[$offset, $page, $pages] = paginate((int) $sum['n'], 50);
$rows = q_all($select . $from_sql . ' ORDER BY p.id DESC LIMIT 50 OFFSET ' . (int) $offset, $params);
$clerks = q_all('SELECT id, full_name FROM users ORDER BY full_name');
$filters = array_filter(['from' => $from, 'to' => $to, 'grade_id' => $gradeId ?: null, 'clerk' => $clerkId ?: null, 'q' => $search ?: null, 'status' => $status]);

layout_start('Purchases', 'purchases');
page_head('Purchases', has_role('manager') ? button_link('Export CSV', url('purchases', $filters + ['export' => 'csv'])) : '');
?>
<form class="filters card" method="get">
  <input type="hidden" name="p" value="purchases">
  <label>From<input type="date" name="from" value="<?= e($from) ?>"></label>
  <label>To<input type="date" name="to" value="<?= e($to) ?>"></label>
  <label>Produce<?= str_replace(' required', '', grade_select('grade_id', $gradeId ?: '', false, false)) ?></label>
  <label>Clerk<select name="clerk"><option value="">All</option><?php foreach ($clerks as $c): ?><option value="<?= (int) $c['id'] ?>"<?= $clerkId === (int) $c['id'] ? ' selected' : '' ?>><?= e($c['full_name']) ?></option><?php endforeach; ?></select></label>
  <label>Status<select name="status"><?php foreach (['valid' => 'Valid', 'void' => 'Void', 'all' => 'All'] as $k => $v): ?><option value="<?= $k ?>"<?= $status === $k ? ' selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select></label>
  <label class="grow">Search<input type="search" name="q" value="<?= e($search) ?>" placeholder="Farmer or receipt no."></label>
  <button class="btn" type="submit">Filter</button>
</form>

<div class="stats">
  <div class="stat"><span>Receipts</span><strong><?= num($sum['n']) ?></strong></div>
  <div class="stat"><span>Net weight</span><strong><?= kg($sum['kg']) ?></strong></div>
  <div class="stat"><span>Value</span><strong><?= money($sum['amount']) ?></strong></div>
  <div class="stat"><span>Paid out</span><strong><?= money($sum['paid']) ?></strong></div>
</div>

<div class="card">
  <div class="scroll">
  <table class="tbl">
    <thead><tr><th>Receipt</th><th>Date</th><th>Farmer</th><th>Produce</th><th class="r">Net kg</th><th class="r">Price</th><th class="r">Paid</th><th>Method</th><th>Clerk</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $p): ?>
      <tr<?= $p['status'] === 'void' ? ' class="dim"' : '' ?>>
        <td><a href="<?= e(url('purchase', ['id' => $p['id']])) ?>"><?= e($p['receipt_no']) ?></a><?= status_badge($p['status']) ?></td>
        <td><?= e(fmt_dt($p['created_at'])) ?></td>
        <td><a href="<?= e(url('farmer', ['id' => $p['farmer_id']])) ?>"><?= e($p['farmer_name']) ?></a></td>
        <td><?= e($p['commodity_name'] . ' — ' . $p['grade_name']) ?></td>
        <td class="r"><?= num($p['net_kg'], 2) ?></td>
        <td class="r"><?= num($p['price_per_kg']) ?></td>
        <td class="r"><strong><?= num($p['amount_paid']) ?></strong></td>
        <td><?= e(payment_label($p['payment_method'])) ?></td>
        <td><?= e($p['clerk_name']) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="9" class="muted">No purchases in this period.</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
  <?= pager($page, $pages, 'purchases', $filters) ?>
</div>
<?php
layout_end();
