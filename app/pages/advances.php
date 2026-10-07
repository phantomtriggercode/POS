<?php
require_role('clerk');
$status = in_array($_GET['status'] ?? '', ['valid', 'void', 'all'], true) ? $_GET['status'] : 'valid';
$where = $status === 'all' ? '' : ' WHERE a.status = ?';
$params = $status === 'all' ? [] : [$status];
$total = (int) q_val('SELECT COUNT(*) FROM advances a' . $where, $params);
[$offset, $page, $pages] = paginate($total, 50);
$rows = q_all(
    'SELECT a.*, f.full_name AS farmer_name, f.code AS farmer_code, u.full_name AS by_name
     FROM advances a JOIN farmers f ON f.id = a.farmer_id JOIN users u ON u.id = a.created_by' . $where .
    ' ORDER BY a.id DESC LIMIT 50 OFFSET ' . (int) $offset,
    $params
);
$owing = q_all(
    "SELECT f.id, f.full_name, f.code, f.phone,
        (SELECT COALESCE(SUM(amount), 0) FROM advances a WHERE a.farmer_id = f.id AND a.status = 'valid') -
        (SELECT COALESCE(SUM(advance_deducted), 0) FROM purchases p WHERE p.farmer_id = f.id AND p.status = 'valid') AS balance
     FROM farmers f WHERE f.id IN (SELECT farmer_id FROM advances WHERE status = 'valid')
     ORDER BY balance DESC"
);
$owing = array_filter($owing, fn ($r) => (int) $r['balance'] > 0);

layout_start('Advances', 'advances');
page_head('Advances to farmers', has_role('manager') ? button_link('+ Give advance', url('advance_new'), 'btn btn-primary') : '');
?>
<div class="stats">
  <div class="stat stat-warn"><span>Total outstanding</span><strong><?= money(total_outstanding_advances()) ?></strong><small><?= count($owing) ?> farmer<?= count($owing) === 1 ? '' : 's' ?> owing</small></div>
</div>
<div class="grid2">
  <section class="card">
    <h2>Who owes</h2>
    <?php if (!$owing): ?><p class="muted">No outstanding advances.</p><?php else: ?>
    <table class="tbl">
      <thead><tr><th>Farmer</th><th>Phone</th><th class="r">Owes</th></tr></thead>
      <tbody>
      <?php foreach ($owing as $r): ?>
        <tr><td><a href="<?= e(url('farmer', ['id' => $r['id']])) ?>"><?= e($r['full_name']) ?></a> <small class="muted"><?= e($r['code']) ?></small></td><td><?= e($r['phone']) ?></td><td class="r"><strong><?= money($r['balance']) ?></strong></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </section>
  <section class="card">
    <div class="page-head small"><h2>Advance history</h2>
      <form method="get"><input type="hidden" name="p" value="advances"><select name="status" data-autosubmit><?php foreach (['valid' => 'Valid', 'void' => 'Void', 'all' => 'All'] as $k => $v): ?><option value="<?= $k ?>"<?= $status === $k ? ' selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select><noscript><button class="btn">Go</button></noscript></form>
    </div>
    <div class="scroll">
    <table class="tbl">
      <thead><tr><th>Date</th><th>Farmer</th><th class="r">Amount</th><th>Method</th><th>By</th><?php if (has_role('manager')): ?><th></th><?php endif; ?></tr></thead>
      <tbody>
      <?php foreach ($rows as $a): ?>
        <tr<?= $a['status'] === 'void' ? ' class="dim"' : '' ?>>
          <td><?= e(fmt_d($a['created_at'])) ?><?= status_badge($a['status']) ?></td>
          <td><a href="<?= e(url('farmer', ['id' => $a['farmer_id']])) ?>"><?= e($a['farmer_name']) ?></a><?php if ($a['note']): ?><br><small class="muted"><?= e($a['note']) ?></small><?php endif; ?><?php if ($a['status'] === 'void'): ?><br><small class="muted">Void: <?= e($a['void_reason']) ?></small><?php endif; ?></td>
          <td class="r"><?= money($a['amount']) ?></td>
          <td><?= e(payment_label($a['payment_method'])) ?></td>
          <td><?= e($a['by_name']) ?></td>
          <?php if (has_role('manager')): ?><td><?php if ($a['status'] === 'valid'): ?>
            <form method="post" action="<?= e(url('advance_void', ['id' => $a['id']])) ?>" data-confirm="Void this advance?" data-reason>
              <?= csrf_field() ?><input type="hidden" name="reason" value=""><button class="linkish danger" type="submit">Void</button>
            </form><?php endif; ?></td><?php endif; ?>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="6" class="muted">Nothing here.</td></tr><?php endif; ?>
      </tbody>
    </table>
    </div>
    <?= pager($page, $pages, 'advances', ['status' => $status]) ?>
  </section>
</div>
<?php
layout_end();
