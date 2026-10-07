<?php
require_role('clerk');
$p = purchase((int) ($_GET['id'] ?? 0));
if (!$p) {
    redirect('purchases');
}
$void = $p['status'] === 'void';
layout_start('Receipt ' . $p['receipt_no'], 'purchases');
page_head('Receipt ' . $p['receipt_no'],
    '<button class="btn btn-primary" type="button" data-print>Print</button>' . button_link('+ Next purchase', url('purchase_new')) . button_link('Farmer', url('farmer', ['id' => $p['farmer_id']]), 'btn btn-ghost'));
?>
<?php if (!empty($_GET['print'])): ?><span data-autoprint hidden></span><?php endif; ?>
<?php if ($void): ?>
  <div class="flash flash-error">VOID — <?= e($p['void_reason']) ?> (by <?= e($p['voided_by_name']) ?>, <?= e(fmt_dt($p['voided_at'])) ?>)</div>
<?php endif; ?>

<article class="receipt<?= $void ? ' is-void' : '' ?>">
  <header>
    <strong><?= e(setting('company_name', 'Motambarea Farmers')) ?></strong>
    <div><?= e(setting('company_location', 'Kumba, Cameroon')) ?></div>
    <?php if (setting('company_phone')): ?><div>Tel: <?= e(setting('company_phone')) ?></div><?php endif; ?>
    <div class="rtitle">PURCHASE RECEIPT<?= $void ? ' — VOID' : '' ?></div>
  </header>
  <dl>
    <dt>Receipt no.</dt><dd><?= e($p['receipt_no']) ?></dd>
    <dt>Date</dt><dd><?= e(fmt_dt($p['created_at'])) ?></dd>
    <dt>Farmer</dt><dd><?= e($p['farmer_name']) ?> (<?= e($p['farmer_code']) ?>)</dd>
    <?php if ($p['farmer_village']): ?><dt>Village</dt><dd><?= e($p['farmer_village']) ?></dd><?php endif; ?>
    <dt>Produce</dt><dd><?= e($p['commodity_name'] . ' — ' . $p['grade_name']) ?></dd>
    <dt>Bags</dt><dd><?= num($p['bags']) ?></dd>
    <dt>Gross weight</dt><dd><?= kg($p['gross_kg']) ?></dd>
    <dt>Tare</dt><dd><?= kg($p['tare_kg']) ?></dd>
    <dt>Net weight</dt><dd><strong><?= kg($p['net_kg']) ?></strong></dd>
    <?php if ($p['moisture_pct'] !== null): ?><dt>Moisture</dt><dd><?= num($p['moisture_pct'], 1) ?> %</dd><?php endif; ?>
    <dt>Price / kg</dt><dd><?= money($p['price_per_kg']) ?></dd>
    <dt>Value</dt><dd><?= money($p['gross_amount']) ?></dd>
    <?php if ((int) $p['advance_deducted'] > 0): ?><dt>Less advance</dt><dd>− <?= money($p['advance_deducted']) ?></dd><?php endif; ?>
    <dt class="big">PAID</dt><dd class="big"><?= money($p['amount_paid']) ?></dd>
    <dt>Method</dt><dd><?= e(payment_label($p['payment_method'])) ?><?= $p['payment_ref'] ? ' · ' . e($p['payment_ref']) : '' ?></dd>
    <dt>Served by</dt><dd><?= e($p['clerk_name']) ?></dd>
    <?php if ($p['notes']): ?><dt>Notes</dt><dd><?= e($p['notes']) ?></dd><?php endif; ?>
  </dl>
  <div class="sign"><span>Farmer signature</span><span>Clerk signature</span></div>
  <footer><?= e(setting('receipt_footer', '')) ?></footer>
</article>

<?php if (!$void && has_role('manager')): ?>
<details class="card no-print">
  <summary>Void this purchase</summary>
  <form method="post" action="<?= e(url('purchase_void', ['id' => $p['id']])) ?>" data-confirm="Void receipt <?= e($p['receipt_no']) ?>? This cannot be undone.">
    <?= csrf_field() ?>
    <label>Reason<input name="reason" required minlength="3" maxlength="255" placeholder="e.g. wrong weight entered, re-issued as …"></label>
    <button class="btn btn-danger" type="submit">Void purchase</button>
  </form>
</details>
<?php endif; ?>
<?php
layout_end();
