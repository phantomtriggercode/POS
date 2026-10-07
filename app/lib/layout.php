<?php
declare(strict_types=1);

const NAV = [
    // route => [label, minimum role]
    'dashboard'    => ['Dashboard', 'clerk'],
    'purchase_new' => ['New purchase', 'clerk'],
    'purchases'    => ['Purchases', 'clerk'],
    'farmers'      => ['Farmers', 'clerk'],
    'advances'     => ['Advances', 'clerk'],
    'stock'        => ['Stock', 'clerk'],
    'dispatches'   => ['Dispatches', 'manager'],
    'prices'       => ['Prices', 'clerk'],
    'reports'      => ['Reports', 'manager'],
    'users'        => ['Users', 'admin'],
    'settings'     => ['Settings', 'admin'],
    'audit'        => ['Audit log', 'admin'],
];

function layout_start(string $title, string $active = ''): void
{
    $u = current_user();
    $company = setting('company_name', 'Motambarea Farmers');
    ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<meta name="theme-color" content="#193b2e">
<title><?= e($title) ?> · <?= e($company) ?> POS</title>
<link rel="stylesheet" href="assets/app.css?v=1">
<script defer src="assets/app.js?v=1"></script>
</head>
<body class="<?= $u ? 'app' : 'guest' ?>">
<?php if ($u): ?>
<header class="topbar">
  <a class="brand" href="<?= e(url('dashboard')) ?>"><span class="mark">MF</span><span><?= e($company) ?><small>Buying management</small></span></a>
  <button class="menu-btn" type="button" aria-expanded="false" aria-controls="sidenav">Menu</button>
</header>
<nav id="sidenav" class="sidenav" aria-label="Main">
  <?php foreach (NAV as $route => [$label, $role]): if (!has_role($role)) continue; ?>
    <a href="<?= e(url($route)) ?>"<?= $route === $active ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
  <?php endforeach; ?>
  <div class="who">
    <a href="<?= e(url('account')) ?>"<?= $active === 'account' ? ' aria-current="page"' : '' ?>><?= e($u['full_name']) ?><small><?= e(ROLES[$u['role']] ?? $u['role']) ?></small></a>
    <form method="post" action="<?= e(url('logout')) ?>"><?= csrf_field() ?><button class="linkish" type="submit">Sign out</button></form>
  </div>
</nav>
<?php endif; ?>
<main class="main" id="main">
<?php foreach (take_flashes() as [$type, $msg]): ?>
  <div class="flash flash-<?= e($type) ?>" role="status"><?= e($msg) ?></div>
<?php endforeach; ?>
<?php
}

function layout_end(): void
{
    echo "</main>\n</body>\n</html>\n";
}

function page_head(string $title, string $actions = ''): void
{
    echo '<div class="page-head"><h1>' . e($title) . '</h1>' . ($actions !== '' ? '<div class="actions">' . $actions . '</div>' : '') . '</div>';
}

function button_link(string $label, string $href, string $class = 'btn'): string
{
    return '<a class="' . e($class) . '" href="' . e($href) . '">' . e($label) . '</a>';
}

function status_badge(string $status): string
{
    return $status === 'valid' ? '' : ' <span class="badge badge-void">VOID</span>';
}

/** Renders a <select> of grades grouped by commodity. */
function grade_select(string $name, $selected, bool $required = true, bool $activeOnly = true): string
{
    $h = '<select name="' . e($name) . '" id="' . e($name) . '"' . ($required ? ' required' : '') . '><option value="">Choose…</option>';
    $group = null;
    foreach (grades($activeOnly) as $g) {
        if ($group !== $g['commodity_name']) {
            if ($group !== null) {
                $h .= '</optgroup>';
            }
            $group = $g['commodity_name'];
            $h .= '<optgroup label="' . e($group) . '">';
        }
        $h .= '<option value="' . (int) $g['id'] . '"' . ((string) $selected === (string) $g['id'] ? ' selected' : '') . '>'
            . e($g['commodity_name'] . ' — ' . $g['name']) . '</option>';
    }
    if ($group !== null) {
        $h .= '</optgroup>';
    }
    return $h . '</select>';
}

function payment_select(string $name, $selected): string
{
    $h = '<select name="' . e($name) . '" id="' . e($name) . '" required>';
    foreach (PAYMENT_METHODS as $k => $label) {
        $h .= '<option value="' . e($k) . '"' . ($selected === $k ? ' selected' : '') . '>' . e($label) . '</option>';
    }
    return $h . '</select>';
}
