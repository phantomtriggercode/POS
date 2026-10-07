<?php
if (current_user()) {
    redirect('dashboard');
}
$error = null;
$username = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = in_str($_POST, 'username', 50);
    $error = attempt_login($username, (string) ($_POST['password'] ?? ''));
    if ($error === null) {
        redirect('dashboard');
    }
}
layout_start('Sign in');
?>
<div class="login">
  <div class="brand big"><span class="mark">MF</span><span><?= e(setting('company_name', 'Motambarea Farmers')) ?><small>Buying management</small></span></div>
  <form method="post" class="card" action="<?= e(url('login')) ?>">
    <?= csrf_field() ?>
    <h1>Sign in</h1>
    <?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>
    <label>Username<input name="username" autocomplete="username" autocapitalize="none" value="<?= e($username) ?>" required autofocus></label>
    <label>Password<input type="password" name="password" autocomplete="current-password" required></label>
    <button class="btn btn-primary btn-block" type="submit">Sign in</button>
  </form>
</div>
<?php
layout_end();
