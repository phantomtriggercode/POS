<?php
declare(strict_types=1);

const ROLES = [
    'clerk'   => 'Clerk (buying station)',
    'manager' => 'Manager',
    'admin'   => 'Administrator',
];

const ROLE_LEVEL = ['clerk' => 1, 'manager' => 2, 'admin' => 3];

const LOGIN_MAX_ATTEMPTS = 5;
const LOGIN_WINDOW_MIN   = 15;

function current_user(): ?array
{
    static $cache = [];
    $uid = $_SESSION['uid'] ?? null;
    if (!$uid) {
        return null;
    }
    if (!array_key_exists($uid, $cache)) {
        $row = q_one('SELECT id, username, full_name, role, active FROM users WHERE id = ?', [$uid]);
        if ($row && (int) $row['active'] === 1) {
            $row['id'] = (int) $row['id'];
            $cache[$uid] = $row;
        } else {
            $cache[$uid] = null;
        }
    }
    if ($cache[$uid] === null) {
        unset($_SESSION['uid']);
    }
    return $cache[$uid];
}

function has_role(string $minimum): bool
{
    $u = current_user();
    return $u !== null && (ROLE_LEVEL[$u['role']] ?? 0) >= (ROLE_LEVEL[$minimum] ?? 99);
}

function require_role(string $minimum): void
{
    if (!current_user()) {
        redirect('login');
    }
    if (!has_role($minimum)) {
        http_response_code(403);
        layout_start('Not allowed');
        echo '<div class="card"><h2>Not allowed</h2><p>Your account does not have access to this page. Ask a manager or administrator.</p></div>';
        layout_end();
        exit;
    }
}

function login_locked(string $username): bool
{
    $since = date('Y-m-d H:i:s', time() - LOGIN_WINDOW_MIN * 60);
    $byUser = (int) q_val('SELECT COUNT(*) FROM login_attempts WHERE username = ? AND attempted_at >= ?', [$username, $since]);
    $byIp = (int) q_val('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND attempted_at >= ?', [client_ip(), $since]);
    return $byUser >= LOGIN_MAX_ATTEMPTS || $byIp >= LOGIN_MAX_ATTEMPTS * 4;
}

/** Returns null on success or an error message. */
function attempt_login(string $username, string $password): ?string
{
    $username = mb_strtolower(trim($username));
    if ($username === '' || $password === '') {
        return 'Enter your username and password.';
    }
    if (login_locked($username)) {
        return 'Too many failed attempts. Wait ' . LOGIN_WINDOW_MIN . ' minutes and try again.';
    }
    $row = q_one('SELECT id, password_hash, active FROM users WHERE username = ?', [$username]);
    if (!$row || !password_verify($password, $row['password_hash']) || (int) $row['active'] !== 1) {
        q('INSERT INTO login_attempts (username, ip, attempted_at) VALUES (?, ?, ?)', [$username, client_ip(), now()]);
        return 'Incorrect username or password.';
    }
    if (password_needs_rehash($row['password_hash'], PASSWORD_DEFAULT)) {
        q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $row['id']]);
    }
    session_regenerate_id(true);
    $_SESSION['uid'] = (int) $row['id'];
    unset($_SESSION['csrf']);
    q('UPDATE users SET last_login_at = ? WHERE id = ?', [now(), $row['id']]);
    q('DELETE FROM login_attempts WHERE username = ?', [$username]);
    audit('login', 'user', (int) $row['id']);
    return null;
}

function logout(): void
{
    if (current_user()) {
        audit('logout', 'user', current_user()['id']);
    }
    $_SESSION = [];
    session_regenerate_id(true);
}

function password_problem(string $pw): ?string
{
    if (mb_strlen($pw) < 8) {
        return 'Password must be at least 8 characters.';
    }
    return null;
}
