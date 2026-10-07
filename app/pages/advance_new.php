<?php
require_role('manager');
$farmerId = (int) ($_GET['farmer_id'] ?? $_POST['farmer_id'] ?? 0);
$error = null;
$old = ['farmer_id' => $farmerId, 'payment_method' => 'cash'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = $_POST;
    try {
        create_advance($_POST);
        flash('ok', 'Advance recorded.');
        redirect('farmer', ['id' => (int) $_POST['farmer_id']]);
    } catch (DomainException $ex) {
        $error = $ex->getMessage();
    }
}
$farmers = q_all('SELECT id, code, full_name FROM farmers WHERE active = 1 ORDER BY full_name');
layout_start('Give advance', 'advances');
page_head('Give an advance');
?>
<form method="post" class="card form narrow" action="<?= e(url('advance_new')) ?>">
  <?= csrf_field() ?>
  <?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>
  <label>Farmer *<select name="farmer_id" required><option value="">Choose…</option>
    <?php foreach ($farmers as $f): ?><option value="<?= (int) $f['id'] ?>"<?= (int) $old['farmer_id'] === (int) $f['id'] ? ' selected' : '' ?>><?= e($f['full_name'] . ' (' . $f['code'] . ')') ?></option><?php endforeach; ?>
  </select></label>
  <label>Amount (<?= e(setting('currency', 'XAF')) ?>) *<input name="amount" inputmode="numeric" value="<?= e($old['amount'] ?? '') ?>" required></label>
  <label>Paid by *<?= payment_select('payment_method', $old['payment_method'] ?? 'cash') ?></label>
  <label>Note<input name="note" value="<?= e($old['note'] ?? '') ?>" maxlength="255" placeholder="Purpose, agreement, expected delivery…"></label>
  <p class="hint">The advance is recovered by deducting from this farmer’s future purchases.</p>
  <div class="form-actions"><button class="btn btn-primary" type="submit">Record advance</button><a class="btn btn-ghost" href="<?= e(url('advances')) ?>">Cancel</a></div>
</form>
<?php
layout_end();
