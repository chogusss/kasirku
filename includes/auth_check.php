<?php
declare(strict_types=1);

/**
 * Authentication middleware for protected pages.
 */

require_once __DIR__ . '/bootstrap.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

const AUTH_IDLE_TIMEOUT = 7200;
const AUTH_REGENERATION_INTERVAL = 900;

/** @return never */
function redirectToLogin(string $notice = ''): never
{
    appRememberIntendedPath();
    $returnTo = isset($_SESSION['return_to']) && is_string($_SESSION['return_to'])
        ? $_SESSION['return_to']
        : null;

    $preserve = [];
    if ($returnTo !== null) {
        $preserve['return_to'] = $returnTo;
    }
    if ($notice !== '') {
        $preserve['auth_notice'] = $notice;
    }

    appResetSession($preserve);
    appRedirect(appUrl('login.php'));
}

if (!appIsAuthenticated()) {
    redirectToLogin();
}

$now = time();
$lastSeen = filter_var($_SESSION['auth_last_seen'] ?? null, FILTER_VALIDATE_INT);
if ($lastSeen !== false && $lastSeen > 0 && ($now - $lastSeen) > AUTH_IDLE_TIMEOUT) {
    redirectToLogin('Sesi Anda berakhir karena tidak ada aktivitas. Silakan masuk kembali.');
}

require_once __DIR__ . '/../config/db.php';

$stmt = $pdo->prepare('SELECT id, name, username, role FROM users WHERE id = ? LIMIT 1');
$stmt->execute([appSessionUserId()]);
$sessionUser = $stmt->fetch();

if (!$sessionUser || !in_array($sessionUser['role'] ?? null, ['owner', 'staff'], true)) {
    redirectToLogin('Akun tidak lagi tersedia. Silakan masuk dengan akun lain.');
}

// Refresh identity and role from the database so stale session data cannot
// retain privileges after an account change.
$_SESSION['user_id'] = (int) $sessionUser['id'];
$_SESSION['name'] = (string) $sessionUser['name'];
$_SESSION['username'] = (string) $sessionUser['username'];
$_SESSION['role'] = (string) $sessionUser['role'];
$_SESSION['auth_last_seen'] = $now;

$lastRegeneration = filter_var($_SESSION['auth_regenerated_at'] ?? null, FILTER_VALIDATE_INT);
if ($lastRegeneration === false || $lastRegeneration <= 0 || ($now - $lastRegeneration) > AUTH_REGENERATION_INTERVAL) {
    session_regenerate_id(true);
    $_SESSION['auth_regenerated_at'] = $now;
}

function isOwner(): bool
{
    return appSessionRole() === 'owner';
}

function requireOwner(): void
{
    if (!isOwner()) {
        appRedirect(appRoleHome('staff'));
    }
}
