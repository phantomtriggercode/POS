<?php
require_role('clerk');
$error = null;
$old = ['effective_date' => today(), 'effective_time' => date('H:i')];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_role('manager');
    $old = $_POST;
    try {
        $d = in_str($_POST, 'effective_date', 10);
        $t = in_str($_POST, 'effective_time', 5);
        if (!valid_date($d) || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $t)) {
            throw new DomainException('Enter a valid date and time.');
        }
        set_price((int) ($_POST['grade_id'] ?? 0), in_int($_POST, 'price_per_kg') ?? 0, "$d $t:00", !empty($_POST['is_public']), in_str($_POST, 'note', 255));
        flash('ok', 'Price saved.');
        redirect('prices');
    } catch (DomainException $ex) {
        $error = $ex->getMessage();
    }
}
$current = current_prices();
$history = q_all(
    'SELECT p.*, g.name AS grade_name, c.name AS commodity_name, u.full_name AS by_name
     FROM prices p JOIN grades g ON g.id = p.grade_id JOIN commodities c ON c.id = g.commodity_id LEFT JOIN users u ON u.id = p.created_by
     ORDER BY p.effective_from DESC, p.id DESC LIMIT 100'
);
layout_start('Prices', 'prices');
page_head('Buying prices');
?>
<div class="grid2">
  <section class="card">
    <h2>Current prices</h2>
    <table class="tbl">
      <thead><tr><th>Produce</th><th class="r">Price / kg</th><th>Since</th><th>Website</th></tr></thead>
      <tbody>
      <?php foreach ($current as $p): ?>
        <tr><td><?= e(grade_label($p)) ?></td>
          <td class="r"><?= $p['price'] ? '<strong>' . money($p['price']['price_per_kg']) . '</strong>' : '<span class="muted">not set</span>' ?></td>
          <td><?= $p['price'] ? e(fmt_dt($p['price']['effective_from'])) : '' ?></td>
          <td><?= $p['price'] && $p['price']['is_public'] ? 'Published' : '<span class="muted">Hidden</span>' ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </section>
  <?php if (has_role('manager')): ?>
  <section class="card">
    <h2>Set a new price</h2>
    <form method="post" class="form" action="<?= e(url('prices')) ?>">
      <?= csrf_field() ?>
      <?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>
      <label>Produce &amp; grade *<?= grade_select('grade_id', $old['grade_id'] ?? '') ?></label>
      <label>Price per kg (<?= e(setting('currency', 'XAF')) ?>) *<input name="price_per_kg" inputmode="numeric" value="<?= e($old['price_per_kg'] ?? '') ?>" required></label>
      <div class="row">
        <label>Effective date<input type="date" name="effective_date" value="<?= e($old['effective_date'] ?? today()) ?>" required></label>
        <label>Time<input type="time" name="effective_time" value="<?= e($old['effective_time'] ?? '') ?>" required></label>
      </div>
      <label>Note<input name="note" value="<?= e($old['note'] ?? '') ?>" maxlength="255" placeholder="Source, e.g. ONCC / NCCB reference"></label>
      <label class="check"><input type="checkbox" name="is_public" value="1"<?= !empty($old['is_public']) ? ' checked' : '' ?>> Show this price on the public website</label>
      <button class="btn btn-primary" type="submit">Save price</button>
    </form>
  </section>
  <?php endif; ?>
</div>
<section class="card">
  <h2>Price history</h2>
  <div class="scroll">
  <table class="tbl">
    <thead><tr><th>Effective from</th><th>Produce</th><th class="r">Price / kg</th><th>Website</th><th>Set by</th><th>Note</th></tr></thead>
    <tbody>
    <?php foreach ($history as $h): ?>
      <tr><td><?= e(fmt_dt($h['effective_from'])) ?><?= $h['effective_from'] > now() ? ' <span class="badge">scheduled</span>' : '' ?></td><td><?= e($h['commodity_name'] . ' — ' . $h['grade_name']) ?></td><td class="r"><?= money($h['price_per_kg']) ?></td><td><?= $h['is_public'] ? 'Yes' : 'No' ?></td><td><?= e($h['by_name']) ?></td><td><?= e($h['note']) ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$history): ?><tr><td colspan="6" class="muted">No prices set yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
</section>
<?php
layout_end();
