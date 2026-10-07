<?php
require_role('clerk');
$rows = stock_levels();
layout_start('Stock', 'stock');
page_head('Stock in store', has_role('manager') ? button_link('+ Dispatch', url('dispatch_new'), 'btn btn-primary') : '');
$byCommodity = [];
foreach ($rows as $r) {
    $byCommodity[$r['commodity_name']][] = $r;
}
?>
<p class="muted">Stock = net kg bought (valid receipts) − net kg dispatched. Physical counts should be compared with these figures regularly.</p>
<?php foreach ($byCommodity as $name => $list): $tot = array_sum(array_column($list, 'stock_kg')); ?>
<section class="card">
  <h2><?= e($name) ?> <small class="muted">· <?= kg($tot) ?> in store</small></h2>
  <div class="scroll">
  <table class="tbl">
    <thead><tr><th>Grade</th><th class="r">Bought (kg)</th><th class="r">Dispatched (kg)</th><th class="r">In store (kg)</th><th class="r">Bags</th><?php if (has_role('manager')): ?><th class="r">Avg. cost / kg</th><?php endif; ?></tr></thead>
    <tbody>
    <?php foreach ($list as $r): ?>
      <tr><td><?= e($r['name']) ?></td><td class="r"><?= num($r['in_kg'], 2) ?></td><td class="r"><?= num($r['out_kg'], 2) ?></td><td class="r"><strong><?= num($r['stock_kg'], 2) ?></strong></td><td class="r"><?= num($r['bags']) ?></td>
      <?php if (has_role('manager')): ?><td class="r"><?= $r['avg_cost'] !== null ? money($r['avg_cost']) : '—' ?></td><?php endif; ?></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</section>
<?php endforeach; ?>
<?php
layout_end();
