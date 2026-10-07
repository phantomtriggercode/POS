<?php
require_role('clerk');
$search = in_str($_GET, 'q', 80);
$showInactive = !empty($_GET['inactive']);
$where = [];
$params = [];
if ($search !== '') {
    $where[] = '(f.full_name LIKE ? OR f.code LIKE ? OR f.phone LIKE ? OR f.village LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like, $like);
}
if (!$showInactive) {
    $where[] = 'f.active = 1';
}
$sqlWhere = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$total = (int) q_val('SELECT COUNT(*) FROM farmers f' . $sqlWhere, $params);
[$offset, $page, $pages] = paginate($total, 50);
$rows = q_all(
    "SELECT f.*,
        (SELECT COALESCE(SUM(net_kg), 0) FROM purchases p WHERE p.farmer_id = f.id AND p.status = 'valid') AS total_kg,
        (SELECT MAX(created_at) FROM purchases p WHERE p.farmer_id = f.id AND p.status = 'valid') AS last_purchase
     FROM farmers f" . $sqlWhere . ' ORDER BY f.full_name LIMIT ' . 50 . ' OFFSET ' . (int) $offset,
    $params
);

layout_start('Farmers', 'farmers');
page_head('Farmers', button_link('+ Register farmer', url('farmer_edit'), 'btn btn-primary'));
?>
<form class="filters card" method="get">
  <input type="hidden" name="p" value="farmers">
  <label class="grow">Search<input type="search" name="q" value="<?= e($search) ?>" placeholder="Name, code, phone or village"></label>
  <label class="check"><input type="checkbox" name="inactive" value="1"<?= $showInactive ? ' checked' : '' ?>> Include inactive</label>
  <button class="btn" type="submit">Search</button>
</form>
<div class="card">
  <p class="muted"><?= num($total) ?> farmer<?= $total === 1 ? '' : 's' ?></p>
  <div class="scroll">
  <table class="tbl">
    <thead><tr><th>Code</th><th>Name</th><th>Phone</th><th>Village</th><th class="r">Total sold</th><th>Last sale</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $f): ?>
      <tr<?= $f['active'] ? '' : ' class="dim"' ?>>
        <td><?= e($f['code']) ?></td>
        <td><a href="<?= e(url('farmer', ['id' => $f['id']])) ?>"><strong><?= e($f['full_name']) ?></strong></a><?= $f['active'] ? '' : ' <span class="badge">inactive</span>' ?></td>
        <td><?= e($f['phone']) ?></td>
        <td><?= e($f['village']) ?></td>
        <td class="r"><?= num($f['total_kg'], 2) ?> kg</td>
        <td><?= e(fmt_d($f['last_purchase'])) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="6" class="muted">No farmers found.</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
  <?= pager($page, $pages, 'farmers', array_filter(['q' => $search, 'inactive' => $showInactive ? 1 : null])) ?>
</div>
<?php
layout_end();
