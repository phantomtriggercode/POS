<?php
require_role('admin');
$entity = in_str($_GET, 'entity', 40);
$where = $entity !== '' ? ' WHERE a.entity = ?' : '';
$params = $entity !== '' ? [$entity] : [];
$total = (int) q_val('SELECT COUNT(*) FROM audit_log a' . $where, $params);
[$offset, $page, $pages] = paginate($total, 100);
$rows = q_all('SELECT a.*, u.full_name FROM audit_log a LEFT JOIN users u ON u.id = a.user_id' . $where . ' ORDER BY a.id DESC LIMIT 100 OFFSET ' . (int) $offset, $params);
$entities = array_column(q_all('SELECT DISTINCT entity FROM audit_log ORDER BY entity'), 'entity');
layout_start('Audit log', 'audit');
page_head('Audit log');
?>
<form class="filters card" method="get">
  <input type="hidden" name="p" value="audit">
  <label>Type<select name="entity"><option value="">All</option><?php foreach ($entities as $en): ?><option<?= $en === $entity ? ' selected' : '' ?>><?= e($en) ?></option><?php endforeach; ?></select></label>
  <button class="btn" type="submit">Filter</button>
</form>
<div class="card">
  <div class="scroll">
  <table class="tbl">
    <thead><tr><th>When</th><th>Who</th><th>Action</th><th>Details</th><th>IP</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr><td><?= e(fmt_dt($r['created_at'])) ?></td><td><?= e($r['full_name'] ?? '—') ?></td><td><?= e($r['action'] . ' ' . $r['entity']) ?><?= $r['entity_id'] ? ' #' . (int) $r['entity_id'] : '' ?></td><td><?= e($r['details']) ?></td><td class="muted"><?= e($r['ip']) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?= pager($page, $pages, 'audit', array_filter(['entity' => $entity])) ?>
</div>
<?php
layout_end();
