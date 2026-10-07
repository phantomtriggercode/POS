<?php
$u = current_user();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $row = q_one('SELECT password_hash FROM users WHERE id = ?', [$u['id']]);
    $current = (string) ($_POST['current_password'] ?? '');
    $new = (string) ($_POST['new_password'] ?? '');
    if (!password_verify($current, $row['password_hash'])) {
        flash('error', 'Your current password is incorrect.');
    } elseif ($problem = password_problem($new)) {
        flash('error', $problem);
    } elseif ($new !== ($_POST['confirm_password'] ?? '')) {
        flash('error', 'The new passwords do not match.');
    } else {
        q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $u['id']]);
        session_regenerate_id(true);
        audit('password_change', 'user', $u['id']);
        flash('ok', 'Password changed.');
    }
    redirect('account');
}
layout_start('My account', 'account');
page_head('My account');
?>
<div class="card narrow">
  <p><strong><?= e($u['full_name']) ?></strong><br><span class="muted">@<?= e($u['username']) ?> · <?= e(ROLES[$u['role']]) ?></span></p>
  <form method="post" action="<?= e(url('account')) ?>">
    <?= csrf_field() ?>
    <h2>Change password</h2>
    <label>Current password<input type="password" name="current_password" autocomplete="current-password" required></label>
    <label>New password<input type="password" name="new_password" autocomplete="new-password" minlength="8" required></label>
    <label>Repeat new password<input type="password" name="confirm_password" autocomplete="new-password" minlength="8" required></label>
    <button class="btn btn-primary" type="submit">Change password</button>
  </form>
</div>
<?php
layout_end();
