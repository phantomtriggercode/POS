<?php
require_role('admin');
$id = (int) ($_GET['id'] ?? 0);
$u = $id ? q_one('SELECT * FROM users WHERE id = ?', [$id]) : null;
if ($id && !$u) {
    redirect('users');
}
$old = $u ?? ['role' => 'clerk', 'active' => 1];
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = $_POST + ['active' => 0];
    $fullName = in_str($_POST, 'full_name', 120);
    $username = mb_strtolower(in_str($_POST, 'username', 50));
    $role = (string) ($_POST['role'] ?? '');
    $active = !empty($_POST['active']) ? 1 : 0;
    $password = (string) ($_POST['password'] ?? '');
    $me = current_user()['id'];
    $admins = (int) q_val("SELECT COUNT(*) FROM users WHERE role = 'admin' AND active = 1 AND id <> ?", [$id]);

    if (mb_strlen($fullName) < 2) {
        $error = 'Enter the person’s full name.';
    } elseif (!$u && !preg_match('/^[a-z0-9._-]{3,50}$/', $username)) {
        $error = 'Username must be 3–50 characters: letters, numbers, dot, dash or underscore.';
    } elseif (!$u && q_val('SELECT COUNT(*) FROM users WHERE username = ?', [$username])) {
        $error = 'That username is taken.';
    } elseif (!isset(ROLES[$role])) {
        $error = 'Choose a role.';
    } elseif ((!$u || $password !== '') && ($p = password_problem($password))) {
        $error = $p;
    } elseif ($u && $u['role'] === 'admin' && ($role !== 'admin' || !$active) && $admins === 0) {
        $error = 'There must be at least one active administrator.';
    } elseif ($u && $id === $me && !$active) {
        $error = 'You cannot disable your own account.';
    } else {
        if ($u) {
            $data = ['full_name' => $fullName, 'role' => $role, 'active' => $active];
            if ($password !== '') {
                $data['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
            }
            update('users', $id, $data);
            audit('update', 'user', $id, "$fullName role=$role active=$active" . ($password !== '' ? ' password reset' : ''));
            flash('ok', 'User updated.');
        } else {
            $id = insert('users', [
                'username' => $username, 'full_name' => $fullName, 'role' => $role,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT), 'active' => 1, 'created_at' => now(),
            ]);
            audit('create', 'user', $id, "$username ($role)");
            flash('ok', 'User created. Give them their username and password.');
        }
        redirect('users');
    }
}

$title = $u ? 'Edit user' : 'Add user';
layout_start($title, 'users');
page_head($title);
?>
<form method="post" class="card form narrow" action="<?= e(url('user_edit', $u ? ['id' => $id] : [])) ?>">
  <?= csrf_field() ?>
  <?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>
  <label>Full name *<input name="full_name" value="<?= e($old['full_name'] ?? '') ?>" required maxlength="120"></label>
  <label>Username *<input name="username" value="<?= e($old['username'] ?? '') ?>" <?= $u ? 'disabled' : 'required' ?> autocapitalize="none" maxlength="50"></label>
  <label>Role *<select name="role"><?php foreach (ROLES as $k => $label): ?><option value="<?= e($k) ?>"<?= ($old['role'] ?? '') === $k ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
  <label><?= $u ? 'New password (leave empty to keep)' : 'Password *' ?><input type="password" name="password" autocomplete="new-password" minlength="8"<?= $u ? '' : ' required' ?>></label>
  <?php if ($u): ?><label class="check"><input type="checkbox" name="active" value="1"<?= !empty($old['active']) ? ' checked' : '' ?>> Account active</label><?php endif; ?>
  <div class="form-actions"><button class="btn btn-primary" type="submit">Save</button><a class="btn btn-ghost" href="<?= e(url('users')) ?>">Cancel</a></div>
</form>
<?php
layout_end();
