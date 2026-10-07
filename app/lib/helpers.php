<?php
declare(strict_types=1);

function config(?string $key = null, $default = null)
{
    $cfg = $GLOBALS['__config'] ?? [];
    if ($key === null) {
        return $cfg;
    }
    return $cfg[$key] ?? $default;
}

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function today(): string
{
    return date('Y-m-d');
}

function url(string $page, array $params = []): string
{
    return 'index.php?' . http_build_query(['p' => $page] + $params);
}

function redirect(string $page, array $params = []): void
{
    header('Location: ' . url($page, $params));
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = [$type, $message];
}

function take_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

/* ---------- CSRF ---------- */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_valid(): bool
{
    $sent = $_POST['_csrf'] ?? '';
    return is_string($sent) && $sent !== '' && hash_equals(csrf_token(), $sent);
}

/* ---------- Input ---------- */

function in_str(array $src, string $key, int $max = 255): string
{
    $v = $src[$key] ?? '';
    if (!is_string($v)) {
        return '';
    }
    $v = trim(preg_replace('/\s+/u', ' ', $v) ?? '');
    return mb_substr($v, 0, $max);
}

function in_text(array $src, string $key, int $max = 2000): string
{
    $v = $src[$key] ?? '';
    return is_string($v) ? mb_substr(trim($v), 0, $max) : '';
}

/** Parses "1 250,5" or "1250.5" style numbers. Returns null when empty or invalid. */
function in_num(array $src, string $key): ?float
{
    $v = $src[$key] ?? '';
    if (!is_string($v) && !is_int($v) && !is_float($v)) {
        return null;
    }
    $v = str_replace([' ', "\u{00A0}"], '', (string) $v);
    if ($v === '') {
        return null;
    }
    if (strpos($v, ',') !== false && strpos($v, '.') === false) {
        $v = str_replace(',', '.', $v);
    } else {
        $v = str_replace(',', '', $v);
    }
    return is_numeric($v) ? (float) $v : null;
}

function in_int(array $src, string $key): ?int
{
    $n = in_num($src, $key);
    return $n === null ? null : (int) round($n);
}

function valid_date(string $d): bool
{
    $dt = DateTime::createFromFormat('Y-m-d', $d);
    return $dt !== false && $dt->format('Y-m-d') === $d;
}

/* ---------- Formatting ---------- */

function money($amount): string
{
    return number_format((float) $amount, 0, '.', ' ') . ' ' . setting('currency', 'XAF');
}

function num($n, int $dec = 0): string
{
    return number_format((float) $n, $dec, '.', ' ');
}

function kg($n): string
{
    return number_format((float) $n, 2, '.', ' ') . ' kg';
}

function fmt_dt(?string $dt): string
{
    if (!$dt) {
        return '';
    }
    $t = strtotime($dt);
    return $t ? date('d M Y H:i', $t) : '';
}

function fmt_d(?string $dt): string
{
    if (!$dt) {
        return '';
    }
    $t = strtotime($dt);
    return $t ? date('d M Y', $t) : '';
}

const PAYMENT_METHODS = [
    'cash'         => 'Cash',
    'mtn_momo'     => 'MTN Mobile Money',
    'orange_money' => 'Orange Money',
    'bank'         => 'Bank transfer',
    'cheque'       => 'Cheque',
];

function payment_label(?string $m): string
{
    return PAYMENT_METHODS[$m] ?? (string) $m;
}

/* ---------- Misc ---------- */

function client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? 'cli'), 0, 45);
}

function setting(string $key, ?string $default = null): ?string
{
    static $cache = null;
    if ($key === '__reset') {
        $cache = null;
        return null;
    }
    if ($cache === null) {
        $cache = [];
        try {
            foreach (q_all('SELECT skey, svalue FROM settings') as $r) {
                $cache[$r['skey']] = $r['svalue'];
            }
        } catch (Throwable $e) {
            // Settings are unavailable before installation.
        }
    }
    return array_key_exists($key, $cache) && $cache[$key] !== '' ? $cache[$key] : $default;
}

function setting_set(string $key, string $value): void
{
    $exists = q_val('SELECT COUNT(*) FROM settings WHERE skey = ?', [$key]);
    if ($exists) {
        q('UPDATE settings SET svalue = ? WHERE skey = ?', [$value, $key]);
    } else {
        q('INSERT INTO settings (skey, svalue) VALUES (?, ?)', [$key, $value]);
    }
    setting('__reset');
}

function audit(string $action, string $entity, $entityId = null, string $details = ''): void
{
    $u = current_user();
    q(
        'INSERT INTO audit_log (user_id, action, entity, entity_id, details, ip, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
        [$u['id'] ?? null, $action, $entity, $entityId, mb_substr($details, 0, 1000), client_ip(), now()]
    );
}

/** Returns [offset, page, pages] for simple pagination. */
function paginate(int $total, int $perPage = 50): array
{
    $pages = max(1, (int) ceil($total / $perPage));
    $page = max(1, min($pages, (int) ($_GET['page'] ?? 1)));
    return [($page - 1) * $perPage, $page, $pages];
}

function pager(int $page, int $pages, string $route, array $params = []): string
{
    if ($pages <= 1) {
        return '';
    }
    $h = '<nav class="pager">';
    if ($page > 1) {
        $h .= '<a href="' . e(url($route, $params + ['page' => $page - 1])) . '">&larr; Previous</a>';
    }
    $h .= '<span>Page ' . $page . ' of ' . $pages . '</span>';
    if ($page < $pages) {
        $h .= '<a href="' . e(url($route, $params + ['page' => $page + 1])) . '">Next &rarr;</a>';
    }
    return $h . '</nav>';
}

function send_csv(string $filename, array $header, iterable $rows): void
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel opens accents correctly
    fputcsv($out, $header);
    foreach ($rows as $r) {
        // Neutralise spreadsheet formula injection.
        $r = array_map(static function ($v) {
            $v = (string) $v;
            return ($v !== '' && strpbrk($v[0], '=+-@') !== false && !is_numeric($v)) ? "'" . $v : $v;
        }, $r);
        fputcsv($out, $r);
    }
    fclose($out);
    exit;
}
