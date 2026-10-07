<?php
require_role('manager');
$error = null;
$old = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = $_POST;
    try {
        create_dispatch($_POST);
        flash('ok', 'Dispatch recorded and stock updated.');
        redirect('dispatches');
    } catch (DomainException $ex) {
        $error = $ex->getMessage();
    }
}
$stock = stock_levels();
layout_start('New dispatch', 'dispatches');
page_head('New dispatch');
?>
<form method="post" class="card form" action="<?= e(url('dispatch_new')) ?>">
  <?= csrf_field() ?>
  <?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>
  <p class="hint">In store: <?= e(implode(' · ', array_map(fn ($s) => grade_label($s) . ' ' . kg($s['stock_kg']), array_filter($stock, fn ($s) => $s['stock_kg'] > 0))) ?: 'nothing') ?></p>
  <div class="row">
    <label class="grow">Produce &amp; grade *<?= grade_select('grade_id', $old['grade_id'] ?? '', true, false) ?></label>
    <label>Bags<input name="bags" inputmode="numeric" value="<?= e($old['bags'] ?? '') ?>"></label>
    <label>Net weight (kg) *<input name="net_kg" inputmode="decimal" value="<?= e($old['net_kg'] ?? '') ?>" required></label>
  </div>
  <div class="row">
    <label class="grow">Buyer / destination *<input name="buyer_name" value="<?= e($old['buyer_name'] ?? '') ?>" required maxlength="120" placeholder="Exporter, warehouse, depot…"></label>
    <label>Sale price / kg<input name="sale_price_per_kg" inputmode="numeric" value="<?= e($old['sale_price_per_kg'] ?? '') ?>" placeholder="optional"></label>
  </div>
  <div class="row">
    <label>Vehicle no.<input name="vehicle_no" value="<?= e($old['vehicle_no'] ?? '') ?>" maxlength="40"></label>
    <label class="grow">Driver<input name="driver_name" value="<?= e($old['driver_name'] ?? '') ?>" maxlength="120"></label>
  </div>
  <label>Notes<input name="notes" value="<?= e($old['notes'] ?? '') ?>" maxlength="500" placeholder="Waybill no., lot no., quality notes…"></label>
  <div class="form-actions"><button class="btn btn-primary" type="submit">Record dispatch</button><a class="btn btn-ghost" href="<?= e(url('dispatches')) ?>">Cancel</a></div>
</form>
<?php
layout_end();
