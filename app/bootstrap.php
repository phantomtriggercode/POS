<?php
declare(strict_types=1);

define('ROOT', dirname(__DIR__));
define('CONFIG_FILE', getenv('POS_CONFIG') ?: ROOT . '/config/config.php');

ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', ROOT . '/storage/logs/php-error.log');
error_reporting(E_ALL);

require __DIR__ . '/lib/helpers.php';
require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/schema.php';
require __DIR__ . '/lib/auth.php';
require __DIR__ . '/lib/domain.php';
require __DIR__ . '/lib/layout.php';

function app_boot(bool $requireInstalled = true): void
{
    if (!is_file(CONFIG_FILE)) {
        if ($requireInstalled) {
            header('Location: install.php');
            exit;
        }
        date_default_timezone_set('Africa/Douala');
        return;
    }
    $GLOBALS['__config'] = require CONFIG_FILE;
    date_default_timezone_set(config('timezone', 'Africa/Douala'));
    if (config('debug')) {
        ini_set('display_errors', '1');
    }
}

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_name('mfpos');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    session_start();

    // Sign idle users out after 8 hours of inactivity.
    $now = time();
    if (isset($_SESSION['last_seen']) && $now - $_SESSION['last_seen'] > 8 * 3600) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
    $_SESSION['last_seen'] = $now;
}

function send_security_headers(): void
{
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; form-action 'self'; frame-ancestors 'none'");
}
