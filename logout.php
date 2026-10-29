<?php
declare(strict_types=1);

/**
 * Logout handler. State changes are POST-only and CSRF protected.
 */

require_once __DIR__ . '/includes/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Metode tidak diizinkan.';
    exit;
}

$token = isset($_POST['csrf_token']) && is_string($_POST['csrf_token'])
    ? $_POST['csrf_token']
    : null;

if (!appIsAuthenticated() || !appVerifyCsrf($token)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Permintaan keluar tidak valid. Muat ulang halaman lalu coba lagi.';
    exit;
}

appDestroySession();
appRedirect(appUrl('login.php'), 303);
