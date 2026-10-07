<?php
require_role('clerk');
$id = (int) ($_GET['id'] ?? 0);
$f = $id ? farmer($id) : null;
if ($id && !$f) {
    redirect('farmers');
}
$old = $f ?? ['active' => 1];
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = $_POST + ['active' => 0];
    try {
        if ($f) {
            // Only managers may deactivate a farmer.
            $active = has_role('manager') ? !empty($_POST['active']) : (bool) $f['active'];
            update_farmer($id, $_POST, $active);
            flash('ok', 'Farmer updated.');
        } else {
            $id = create_farmer($_POST);
            flash('ok', 'Farmer registered.');
            if (($_POST['next'] ?? '') === 'purchase') {
                redirect('purchase_new', ['farmer_id' => $id]);
            }
        }
        redirect('farmer', ['id' => $id]);
    } catch (DomainException $ex) {
        $error = $ex->getMessage();
    }
}

$title = $f ? 'Edit farmer' : 'Register farmer';
layout_start($title, 'farmers');
page_head($title);
?>
<form method="post" class="card form" action="<?= e(url('farmer_edit', $f ? ['id' => $id] : [])) ?>">
  <?= csrf_field() ?>
  <?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>
  <div class="row">
    <label class="grow">Full name *<input name="full_name" value="<?= e($old['full_name'] ?? '') ?>" required maxlength="120"></label>
    <label>Farmer code<input name="code" value="<?= e($old['code'] ?? '') ?>" maxlength="30" placeholder="<?= $f ? '' : 'Automatic' ?>"<?= $f ? ' required' : '' ?>></label>
  </div>
  <div class="row">
    <label>Phone / WhatsApp<input name="phone" type="tel" value="<?= e($old['phone'] ?? '') ?>" maxlength="40" placeholder="+237 6…"></label>
    <label>Village / locality<input name="village" value="<?= e($old['village'] ?? '') ?>" maxlength="120"></label>
    <label>ID card number<input name="id_number" value="<?= e($old['id_number'] ?? '') ?>" maxlength="60"></label>
  </div>
  <fieldset>
    <legend>Farm (for traceability)</legend>
    <div class="row">
      <label class="grow">Farm location / description<input name="farm_location" value="<?= e($old['farm_location'] ?? '') ?>" maxlength="255"></label>
      <label>Size (hectares)<input name="farm_size_ha" inputmode="decimal" value="<?= e($old['farm_size_ha'] ?? '') ?>"></label>
    </div>
    <div class="row">
      <label>GPS latitude<input name="gps_lat" inputmode="decimal" value="<?= e($old['gps_lat'] ?? '') ?>" placeholder="4.6363"></label>
      <label>GPS longitude<input name="gps_lng" inputmode="decimal" value="<?= e($old['gps_lng'] ?? '') ?>" placeholder="9.4469"></label>
      <div class="align-end"><button class="btn" type="button" data-gps>Use this phone’s location</button></div>
    </div>
  </fieldset>
  <label>Notes<textarea name="notes" rows="3" maxlength="2000"><?= e($old['notes'] ?? '') ?></textarea></label>
  <?php if ($f && has_role('manager')): ?>
    <label class="check"><input type="checkbox" name="active" value="1"<?= !empty($old['active']) ? ' checked' : '' ?>> Active (inactive farmers cannot sell or receive advances)</label>
  <?php endif; ?>
  <div class="form-actions">
    <button class="btn btn-primary" type="submit">Save</button>
    <?php if (!$f): ?><button class="btn" type="submit" name="next" value="purchase">Save &amp; record purchase</button><?php endif; ?>
    <a class="btn btn-ghost" href="<?= e($f ? url('farmer', ['id' => $id]) : url('farmers')) ?>">Cancel</a>
  </div>
</form>
<?php
layout_end();
