<?php
require_role('clerk');
$farmerId = (int) ($_GET['farmer_id'] ?? $_POST['farmer_id'] ?? 0);
$f = $farmerId ? farmer($farmerId) : null;
$error = null;
$old = ['payment_method' => 'cash', 'tare_kg' => '', 'advance_deducted' => '0'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = $_POST;
    try {
        $id = create_purchase($_POST);
        flash('ok', 'Purchase saved.');
        redirect('purchase', ['id' => $id, 'print' => 1]);
    } catch (DomainException $ex) {
        $error = $ex->getMessage();
    }
}

layout_start('New purchase', 'purchase_new');
page_head('New purchase');

if (!$f || !$f['active']) {
    // Step 1: find the farmer.
    $search = in_str($_GET, 'q', 80);
    $matches = [];
    if ($search !== '') {
        $like = '%' . $search . '%';
        $matches = q_all('SELECT id, code, full_name, phone, village FROM farmers WHERE active = 1 AND (full_name LIKE ? OR code LIKE ? OR phone LIKE ?) ORDER BY full_name LIMIT 30', [$like, $like, $like]);
    }
    ?>
    <form class="card filters" method="get">
      <input type="hidden" name="p" value="purchase_new">
      <label class="grow">Find the farmer<input type="search" name="q" value="<?= e($search) ?>" placeholder="Name, code or phone" autofocus></label>
      <button class="btn btn-primary" type="submit">Search</button>
      <a class="btn" href="<?= e(url('farmer_edit')) ?>">+ New farmer</a>
    </form>
    <?php if ($search !== ''): ?>
    <div class="card">
      <?php if (!$matches): ?><p class="muted">No active farmer matches “<?= e($search) ?>”. <a href="<?= e(url('farmer_edit')) ?>">Register a new farmer</a>.</p><?php endif; ?>
      <ul class="pick">
      <?php foreach ($matches as $m): ?>
        <li><a href="<?= e(url('purchase_new', ['farmer_id' => $m['id']])) ?>"><strong><?= e($m['full_name']) ?></strong> <span class="muted"><?= e($m['code']) ?> · <?= e($m['phone'] ?: 'no phone') ?> · <?= e($m['village'] ?: '') ?></span></a></li>
      <?php endforeach; ?>
      </ul>
    </div>
    <?php endif;
    layout_end();
    return;
}

$balance = farmer_balance($farmerId);
$priceMap = [];
foreach (current_prices() as $p) {
    $priceMap[$p['id']] = $p['price'] ? (int) $p['price']['price_per_kg'] : null;
}
$isManager = has_role('manager');
?>
<form method="post" class="card form" id="purchase-form" action="<?= e(url('purchase_new')) ?>"
      data-prices="<?= e(json_encode($priceMap)) ?>" data-balance="<?= (int) $balance ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="farmer_id" value="<?= (int) $farmerId ?>">
  <?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>

  <div class="farmer-strip">
    <div><strong><?= e($f['full_name']) ?></strong> <span class="muted"><?= e($f['code']) ?><?= $f['village'] ? ' · ' . e($f['village']) : '' ?></span></div>
    <div>Advance owed: <strong><?= money($balance) ?></strong> · <a href="<?= e(url('purchase_new')) ?>">change farmer</a></div>
  </div>

  <div class="row">
    <label class="grow">Produce &amp; grade *<?= grade_select('grade_id', $old['grade_id'] ?? '') ?></label>
    <label>Price / kg<?php if ($isManager): ?>
      <input name="price_per_kg" id="price_per_kg" inputmode="numeric" value="<?= e($old['price_per_kg'] ?? '') ?>" placeholder="current price">
    <?php else: ?>
      <input id="price_per_kg" readonly tabindex="-1" placeholder="choose grade">
    <?php endif; ?></label>
  </div>
  <?php if ($isManager): ?><p class="hint">Leave the price empty to use the official price. A different price needs a note.</p><?php endif; ?>

  <div class="row">
    <label>Bags<input name="bags" id="bags" inputmode="numeric" value="<?= e($old['bags'] ?? '') ?>"></label>
    <label>Gross weight (kg) *<input name="gross_kg" id="gross_kg" inputmode="decimal" value="<?= e($old['gross_kg'] ?? '') ?>" required></label>
    <label>Tare / bags weight (kg)<input name="tare_kg" id="tare_kg" inputmode="decimal" value="<?= e($old['tare_kg'] ?? '') ?>" placeholder="0"></label>
    <label>Moisture %<input name="moisture_pct" inputmode="decimal" value="<?= e($old['moisture_pct'] ?? '') ?>"></label>
  </div>

  <div class="row">
    <label>Deduct from advance<input name="advance_deducted" id="advance_deducted" inputmode="numeric" value="<?= e($old['advance_deducted'] ?? '0') ?>"<?= $balance > 0 ? '' : ' readonly' ?>></label>
    <label>Paid by *<?= payment_select('payment_method', $old['payment_method'] ?? 'cash') ?></label>
    <label>Transaction ref.<input name="payment_ref" value="<?= e($old['payment_ref'] ?? '') ?>" maxlength="80" placeholder="Mobile money / bank ref"></label>
  </div>
  <label>Notes<input name="notes" value="<?= e($old['notes'] ?? '') ?>" maxlength="500"></label>

  <div class="totals" aria-live="polite">
    <div><span>Net weight</span><strong id="t-net">—</strong></div>
    <div><span>Value</span><strong id="t-value">—</strong></div>
    <div><span>Advance deducted</span><strong id="t-deduct">—</strong></div>
    <div class="pay"><span>To pay farmer</span><strong id="t-pay">—</strong></div>
  </div>
  <p class="hint">Figures above are a preview. The server recalculates everything when you save.</p>

  <div class="form-actions">
    <button class="btn btn-primary btn-lg" type="submit">Save &amp; print receipt</button>
    <a class="btn btn-ghost" href="<?= e(url('dashboard')) ?>">Cancel</a>
  </div>
</form>
<?php
layout_end();
