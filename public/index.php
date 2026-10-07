<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

app_boot();
start_session();
send_security_headers();

$routes = [
    'login', 'logout', 'dashboard', 'account',
    'farmers', 'farmer', 'farmer_edit',
    'purchases', 'purchase_new', 'purchase', 'purchase_void',
    'advances', 'advance_new', 'advance_void',
    'stock', 'dispatches', 'dispatch_new', 'dispatch_void',
    'prices', 'reports',
    'users', 'user_edit', 'settings', 'audit',
];

$page = (string) ($_GET['p'] ?? 'dashboard');
if (!in_array($page, $routes, true)) {
    http_response_code(404);
    $page = 'not_found';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_valid()) {
    http_response_code(400);
    flash('error', 'Your session expired. Please try again.');
    redirect(current_user() ? 'dashboard' : 'login');
}

if ($page !== 'login' && !current_user()) {
    redirect('login');
}

try {
    require __DIR__ . '/../app/pages/' . $page . '.php';
} catch (PDOException $e) {
    error_log('[POS] ' . $e->getMessage());
    http_response_code(500);
    if (!headers_sent()) {
        layout_start('Error');
    }
    echo '<div class="flash flash-error">A database error occurred. Nothing was saved. If it keeps happening, contact the administrator.</div>';
    layout_end();
}
