<?php
require_role('admin');
$fields = [
    'company_name'     => ['Company name', 120],
    'company_location' => ['Location (shown on receipts)', 120],
    'company_phone'    => ['Phone (shown on receipts)', 40],
    'receipt_footer'   => ['Receipt footer', 255],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'company') {
            foreach ($fields as $k => [, $max]) {
                setting_set($k, in_str($_POST, $k, $max));
            }
            audit('update', 'settings', null, 'company details');
            flash('ok', 'Settings saved.');
        } elseif ($action === 'add_commodity') {
            $name = in_str($_POST, 'name', 60);
            $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', in_str($_POST, 'code', 20)));
            if ($name === '' || $code === '') {
                throw new DomainException('Enter a name and a short code.');
            }
            if (q_val('SELECT COUNT(*) FROM commodities WHERE code = ?', [$code])) {
                throw new DomainException('That code is already used.');
            }
            $id = insert('commodities', ['name' => $name, 'code' => $code, 'active' => 1, 'sort_order' => (int) q_val('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM commodities')]);
            audit('create', 'commodity', $id, $name);
            flash('ok', 'Produce added. Now add its grades.');
        } elseif ($action === 'add_grade') {
            $cid = (int) ($_POST['commodity_id'] ?? 0);
            $name = in_str($_POST, 'name', 60);
            if (!q_val('SELECT COUNT(*) FROM commodities WHERE id = ?', [$cid]) || $name === '') {
                throw new DomainException('Choose the produce and enter a grade name.');
            }
            $id = insert('grades', ['commodity_id' => $cid, 'name' => $name, 'active' => 1, 'sort_order' => (int) q_val('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM grades WHERE commodity_id = ?', [$cid])]);
            audit('create', 'grade', $id, $name);
            flash('ok', 'Grade added.');
        } elseif ($action === 'rename_grade' || $action === 'toggle_grade') {
            $gid = (int) ($_POST['id'] ?? 0);
            $g = q_one('SELECT * FROM grades WHERE id = ?', [$gid]);
            if (!$g) {
                throw new DomainException('Grade not found.');
            }
            if ($action === 'rename_grade') {
                $name = in_str($_POST, 'name', 60);
                if ($name === '') {
                    throw new DomainException('Enter a grade name.');
                }
                update('grades', $gid, ['name' => $name]);
                audit('update', 'grade', $gid, $g['name'] . ' → ' . $name);
            } else {
                update('grades', $gid, ['active' => $g['active'] ? 0 : 1]);
                audit('update', 'grade', $gid, $g['name'] . ($g['active'] ? ' disabled' : ' enabled'));
            }
            flash('ok', 'Grade updated.');
        } elseif ($action === 'toggle_commodity') {
            $cid = (int) ($_POST['id'] ?? 0);
            $c = q_one('SELECT * FROM commodities WHERE id = ?', [$cid]);
            if (!$c) {
                throw new DomainException('Produce not found.');
            }
            update('commodities', $cid, ['active' => $c['active'] ? 0 : 1]);
            audit('update', 'commodity', $cid, $c['name'] . ($c['active'] ? ' disabled' : ' enabled'));
            flash('ok', 'Produce updated.');
        }
    } catch (DomainException $ex) {
        flash('error', $ex->getMessage());
    }
    redirect('settings');
}

$commodities = commodities(false);
$grades = [];
foreach (grades(false) as $g) {
    $grades[$g['commodity_id']][] = $g;
}
layout_start('Settings', 'settings');
page_head('Settings');
?>
<div class="grid2">
  <section class="card">
    <h2>Company details</h2>
    <form method="post" class="form" action="<?= e(url('settings')) ?>">
      <?= csrf_field() ?><input type="hidden" name="action" value="company">
      <?php foreach ($fields as $k => [$label, $max]): ?>
        <label><?= e($label) ?><input name="<?= e($k) ?>" value="<?= e(setting($k, '')) ?>" maxlength="<?= $max ?>"></label>
      <?php endforeach; ?>
      <button class="btn btn-primary" type="submit">Save</button>
    </form>
  </section>
  <section class="card">
    <h2>Add produce</h2>
    <form method="post" class="form" action="<?= e(url('settings')) ?>">
      <?= csrf_field() ?><input type="hidden" name="action" value="add_commodity">
      <div class="row"><label class="grow">Name<input name="name" maxlength="60" placeholder="e.g. Palm kernels"></label><label>Code<input name="code" maxlength="20" placeholder="PALMK"></label></div>
      <button class="btn" type="submit">Add produce</button>
    </form>
    <h2>Add grade</h2>
    <form method="post" class="form" action="<?= e(url('settings')) ?>">
      <?= csrf_field() ?><input type="hidden" name="action" value="add_grade">
      <div class="row">
        <label>Produce<select name="commodity_id"><?php foreach ($commodities as $c): ?><option value="<?= (int) $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?></select></label>
        <label class="grow">Grade name<input name="name" maxlength="60"></label>
      </div>
      <button class="btn" type="submit">Add grade</button>
    </form>
  </section>
</div>

<section class="card">
  <h2>Produce &amp; grades</h2>
  <p class="hint">Disabled grades can no longer be bought, but their history and stock remain.</p>
  <?php foreach ($commodities as $c): ?>
    <div class="commodity<?= $c['active'] ? '' : ' dim' ?>">
      <div class="page-head small"><h3><?= e($c['name']) ?> <small class="muted"><?= e($c['code']) ?></small><?= $c['active'] ? '' : ' <span class="badge">disabled</span>' ?></h3>
        <form method="post" action="<?= e(url('settings')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="toggle_commodity"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>"><button class="linkish" type="submit"><?= $c['active'] ? 'Disable' : 'Enable' ?></button></form>
      </div>
      <ul class="grades">
      <?php foreach ($grades[$c['id']] ?? [] as $g): ?>
        <li<?= $g['active'] ? '' : ' class="dim"' ?>>
          <form method="post" class="inline" action="<?= e(url('settings')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="rename_grade"><input type="hidden" name="id" value="<?= (int) $g['id'] ?>"><input name="name" value="<?= e($g['name']) ?>" maxlength="60" aria-label="Grade name"><button class="linkish" type="submit">Rename</button></form>
          <form method="post" class="inline" action="<?= e(url('settings')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="toggle_grade"><input type="hidden" name="id" value="<?= (int) $g['id'] ?>"><button class="linkish" type="submit"><?= $g['active'] ? 'Disable' : 'Enable' ?></button></form>
        </li>
      <?php endforeach; ?>
      </ul>
    </div>
  <?php endforeach; ?>
</section>
<?php
layout_end();
