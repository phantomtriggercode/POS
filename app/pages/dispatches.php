<?php
require_role('manager');
$total = (int) q_val('SELECT COUNT(*) FROM dispatches');
[$offset, $page, $pages] = paginate($total, 50);
$rows = q_all(
    'SELECT d.*, g.name AS grade_name, c.name AS commodity_name, u.full_name AS by_name
     FROM dispatches d JOIN grades g ON g.id = d.grade_id JOIN commodities c ON c.id = g.commodity_id JOIN users u ON u.id = d.created_by
     ORDER BY d.id DESC LIMIT 50 OFFSET ' . (int) $offset
);
layout_start('Dispatches', 'dispatches');
page_head('Dispatches & sales', button_link('+ New dispatch', url('dispatch_new'), 'btn btn-primary'));
?>
<div class="card">
  <div class="scroll">
  <table class="tbl">
    <thead><tr><th>Ref.</th><th>Date</th><th>Produce</th><th class="r">Bags</th><th class="r">Net kg</th><th>Buyer / destination</th><th>Vehicle</th><th class="r">Sale value</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $d): ?>
      <tr<?= $d['status'] === 'void' ? ' class="dim"' : '' ?>>
        <td><?= e($d['ref_no']) ?><?= status_badge($d['status']) ?></td>
        <td><?= e(fmt_dt($d['created_at'])) ?></td>
        <td><?= e($d['commodity_name'] . ' — ' . $d['grade_name']) ?></td>
        <td class="r"><?= num($d['bags']) ?></td>
        <td class="r"><?= num($d['net_kg'], 2) ?></td>
        <td><?= e($d['buyer_name']) ?><?php if ($d['notes']): ?><br><small class="muted"><?= e($d['notes']) ?></small><?php endif; ?><?php if ($d['status'] === 'void'): ?><br><small class="muted">Void: <?= e($d['void_reason']) ?></small><?php endif; ?></td>
        <td><?= e($d['vehicle_no']) ?><?= $d['driver_name'] ? '<br><small class="muted">' . e($d['driver_name']) . '</small>' : '' ?></td>
        <td class="r"><?= $d['sale_amount'] !== null ? money($d['sale_amount']) : '—' ?></td>
        <td><?php if ($d['status'] === 'valid'): ?>
          <form method="post" action="<?= e(url('dispatch_void', ['id' => $d['id']])) ?>" data-confirm="Void dispatch <?= e($d['ref_no']) ?>? The weight returns to stock." data-reason>
            <?= csrf_field() ?><input type="hidden" name="reason" value=""><button class="linkish danger" type="submit">Void</button>
          </form><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="9" class="muted">No dispatches yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
  <?= pager($page, $pages, 'dispatches') ?>
</div>
<?php
layout_end();
