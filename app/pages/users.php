<?php
require_role('admin');
$rows = q_all('SELECT * FROM users ORDER BY active DESC, full_name');
layout_start('Users', 'users');
page_head('Users', button_link('+ Add user', url('user_edit'), 'btn btn-primary'));
?>
<div class="card">
  <p class="hint"><strong>Clerks</strong> register farmers and record purchases at the official price. <strong>Managers</strong> also set prices, give advances, void receipts, dispatch stock and see reports. <strong>Administrators</strong> also manage users and settings.</p>
  <div class="scroll">
  <table class="tbl">
    <thead><tr><th>Name</th><th>Username</th><th>Role</th><th>Last sign-in</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $u): ?>
      <tr<?= $u['active'] ? '' : ' class="dim"' ?>><td><?= e($u['full_name']) ?><?= $u['active'] ? '' : ' <span class="badge">disabled</span>' ?></td><td><?= e($u['username']) ?></td><td><?= e(ROLES[$u['role']] ?? $u['role']) ?></td><td><?= e(fmt_dt($u['last_login_at']) ?: 'never') ?></td><td><a href="<?= e(url('user_edit', ['id' => $u['id']])) ?>">Edit</a></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>
<?php
layout_end();
