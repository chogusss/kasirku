<?php
declare(strict_types=1);

/**
 * Login Page - KasirKu UMKM
 */
require_once __DIR__ . '/includes/bootstrap.php';

// Already logged in
if (appIsAuthenticated()) {
    appRedirect(appRoleHome());
}

require_once __DIR__ . '/config/db.php';

$error = '';
$notice = isset($_SESSION['auth_notice']) && is_string($_SESSION['auth_notice'])
    ? $_SESSION['auth_notice']
    : '';
unset($_SESSION['auth_notice']);

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usernameInput = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $username = is_string($usernameInput) ? trim($usernameInput) : '';

    if (!appVerifyCsrf(isset($_POST['csrf_token']) && is_string($_POST['csrf_token']) ? $_POST['csrf_token'] : null)) {
        $error = 'Sesi formulir telah berakhir. Silakan coba lagi.';
        appRotateCsrfToken();
    } elseif (!is_string($usernameInput) || !is_string($password) || $username === '' || $password === '') {
        $error = 'Username dan password wajib diisi.';
    } elseif (strlen($username) > 50 || strlen($password) > 4096) {
        $error = 'Username atau password salah.';
    } else {
        $stmt = $pdo->prepare('SELECT id, name, username, password, role FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user
            && in_array($user['role'] ?? null, ['owner', 'staff'], true)
            && password_verify($password, (string) $user['password'])) {
            $role = (string) $user['role'];
            $returnTo = appSafeReturnPath(
                isset($_SESSION['return_to']) && is_string($_SESSION['return_to']) ? $_SESSION['return_to'] : null,
                $role
            );

            appResetSession();
            $_SESSION['user_id'] = (int) $user['id'];
            $_SESSION['name'] = (string) $user['name'];
            $_SESSION['username'] = (string) $user['username'];
            $_SESSION['role'] = $role;
            $_SESSION['auth_login_at'] = time();
            $_SESSION['auth_last_seen'] = time();
            $_SESSION['auth_regenerated_at'] = time();

            appRedirect($returnTo ?? appRoleHome($role));
        } else {
            $error = 'Username atau password salah.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="KasirKu — Aplikasi Kasir & Manajemen Toko untuk UMKM Indonesia">
  <title>Masuk — KasirKu</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,300;0,14..32,400;0,14..32,500;0,14..32,600;0,14..32,700;0,14..32,800&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --bg:            oklch(0.13 0.012 220);
      --surface:       oklch(0.17 0.012 220);
      --surface-2:     oklch(0.21 0.010 220);
      --surface-3:     oklch(0.25 0.008 220);
      --ink:           oklch(0.94 0.005 220);
      --ink-2:         oklch(0.76 0.008 220);
      --ink-3:         oklch(0.55 0.008 220);
      --primary:       oklch(0.62 0.14 160);
      --primary-dim:   oklch(0.48 0.12 160);
      --primary-surface: oklch(0.20 0.04 160);
      --primary-border:  oklch(0.35 0.08 160);
      --danger:        oklch(0.60 0.19 20);
      --danger-surface:oklch(0.18 0.05 20);
      --border:        oklch(0.28 0.006 220);
      --border-strong: oklch(0.35 0.010 220);
      --ease-out: cubic-bezier(0.22, 1, 0.36, 1);
    }

    html, body {
      height: 100%;
      font-family: 'Inter', system-ui, sans-serif;
      background: var(--bg);
      color: var(--ink);
      -webkit-font-smoothing: antialiased;
    }

    body {
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
      padding: 20px;
      position: relative;
    }

    /* Two-panel ambient glow — restrained, not decorative */
    body::before {
      content: '';
      position: fixed;
      inset: 0;
      background:
        radial-gradient(ellipse 50% 40% at 15% 15%, oklch(0.35 0.07 160 / 0.12) 0%, transparent 60%),
        radial-gradient(ellipse 40% 30% at 85% 85%, oklch(0.28 0.05 220 / 0.08) 0%, transparent 60%);
      pointer-events: none;
    }

    @media (prefers-reduced-motion: no-preference) {
      .login-panel { animation: panelUp 280ms var(--ease-out); }
    }
    @keyframes panelUp {
      from { opacity: 0; transform: translateY(12px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    /* ===== SPLIT LAYOUT ===== */
    .login-panel {
      width: 100%;
      max-width: 400px;
      position: relative;
      z-index: 1;
    }

    /* Brand header */
    .brand {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 28px;
    }

    .brand-mark {
      width: 42px; height: 42px;
      background: var(--primary);
      border-radius: 10px;
      display: flex; align-items: center; justify-content: center;
      font-size: 20px;
      flex-shrink: 0;
    }

    .brand-text {
      display: flex;
      flex-direction: column;
    }

    .brand-name {
      font-size: 20px;
      font-weight: 800;
      color: var(--ink);
      letter-spacing: -0.02em;
      line-height: 1.1;
    }

    .brand-tagline {
      font-size: 12px;
      color: var(--ink-3);
      margin-top: 2px;
    }

    /* Form card */
    .login-card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 14px;
      padding: 28px;
    }

    .login-heading {
      font-size: 18px;
      font-weight: 700;
      color: var(--ink);
      margin-bottom: 4px;
      letter-spacing: -0.02em;
    }

    .login-sub {
      font-size: 13px;
      color: var(--ink-3);
      margin-bottom: 22px;
    }

    /* Error */
    .error-alert {
      display: flex;
      align-items: center;
      gap: 9px;
      padding: 11px 14px;
      background: var(--danger-surface);
      border: 1px solid oklch(0.32 0.10 20);
      border-radius: 8px;
      margin-bottom: 18px;
      font-size: 13px;
      color: var(--danger);
      font-weight: 500;
    }

    /* Form */
    .form-group { margin-bottom: 15px; }

    .form-label {
      display: block;
      font-size: 12px;
      font-weight: 600;
      color: var(--ink-2);
      margin-bottom: 6px;
    }

    .input-wrap { position: relative; }

    .input-icon {
      position: absolute;
      left: 12px; top: 50%;
      transform: translateY(-50%);
      font-size: 14px;
      pointer-events: none;
    }

    .form-control {
      width: 100%;
      padding: 10px 12px 10px 38px;
      background: var(--surface-2);
      border: 1px solid var(--border);
      border-radius: 8px;
      color: var(--ink);
      font-size: 14px;
      font-family: inherit;
      outline: none;
      transition: border-color 160ms var(--ease-out), box-shadow 160ms var(--ease-out);
    }

    .form-control:focus {
      border-color: var(--primary);
      box-shadow: 0 0 0 3px oklch(0.62 0.14 160 / 0.15);
    }

    /* Placeholder at 4.5:1+ — use ink-2 not ink-3 */
    .form-control::placeholder { color: var(--ink-2); opacity: 1; }

    .form-control:focus-visible,
    .btn-login:focus-visible {
      outline: 3px solid var(--primary);
      outline-offset: 2px;
    }

    /* Submit */
    .btn-login {
      width: 100%;
      padding: 11px;
      background: var(--primary);
      color: oklch(0.10 0 0);
      border: none;
      border-radius: 8px;
      font-size: 14px;
      font-weight: 700;
      font-family: inherit;
      cursor: pointer;
      transition: background 160ms var(--ease-out), box-shadow 160ms var(--ease-out);
      display: flex; align-items: center; justify-content: center;
      gap: 7px;
      margin-top: 4px;
    }

    .btn-login:hover:not(:disabled) {
      background: var(--primary-dim);
      color: var(--ink);
      box-shadow: 0 4px 16px oklch(0.62 0.14 160 / 0.28);
    }

    .btn-login:disabled {
      opacity: 0.5;
      cursor: not-allowed;
    }

    /* Demo credentials */
    .demo-box {
      margin-top: 18px;
      padding: 13px 15px;
      background: var(--surface-2);
      border: 1px solid var(--border);
      border-radius: 8px;
    }

    .demo-label {
      font-size: 10px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.06em;
      color: var(--ink-3);
      margin-bottom: 9px;
    }

    .demo-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-size: 12px;
      color: var(--ink-3);
      margin-bottom: 5px;
    }
    .demo-row:last-child { margin-bottom: 0; }

    .demo-row code {
      font-family: 'Courier New', monospace;
      color: var(--ink-2);
      font-size: 12px;
      background: var(--surface-3);
      padding: 2px 7px;
      border-radius: 4px;
    }

    /* Footer */
    .login-footer {
      text-align: center;
      margin-top: 18px;
      font-size: 11px;
      color: var(--ink-3);
    }
  </style>
</head>
<body>
  <div class="login-panel">

    <!-- Brand -->
    <div class="brand">
      <div class="brand-mark">🧾</div>
      <div class="brand-text">
        <div class="brand-name"><?= appEscape(APP_NAME) ?></div>
        <div class="brand-tagline">Kasir &amp; Manajemen Toko untuk UMKM</div>
      </div>
    </div>

    <!-- Card -->
    <div class="login-card">
      <h1 class="login-heading">Masuk ke Akun</h1>
      <p class="login-sub">Selamat datang kembali. Masukkan kredensial Anda.</p>

      <?php if ($notice !== ''): ?>
      <div class="error-alert" role="status">
        <span aria-hidden="true">ℹ️</span>
        <?= appEscape($notice) ?>
      </div>
      <?php endif; ?>

      <?php if ($error !== ''): ?>
      <div class="error-alert" role="alert">
        <span aria-hidden="true">⚠</span>
        <?= appEscape($error) ?>
      </div>
      <?php endif; ?>

      <form method="POST" action="<?= appEscape(appUrl('login.php')) ?>" autocomplete="on" id="loginForm">
        <input type="hidden" name="csrf_token" value="<?= appEscape(appCsrfToken()) ?>">
        <div class="form-group">
          <label class="form-label" for="username">Username</label>
          <div class="input-wrap">
            <span class="input-icon">👤</span>
            <input
              id="username"
              name="username"
              type="text"
              class="form-control"
              placeholder="Masukkan username"
              value="<?= appEscape(isset($usernameInput) && is_string($usernameInput) ? $usernameInput : '') ?>"
              required
              autofocus
              autocomplete="username"
              autocapitalize="none"
              maxlength="50"
              spellcheck="false"
            >
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" for="password">Password</label>
          <div class="input-wrap">
            <span class="input-icon">🔑</span>
            <input
              id="password"
              name="password"
              type="password"
              class="form-control"
              placeholder="Masukkan password"
              required
              autocomplete="current-password"
              maxlength="4096"
            >
          </div>
        </div>

        <button type="submit" class="btn-login" id="loginBtn">
          Masuk Sekarang
        </button>
      </form>

      <!-- Demo credentials -->
      <div class="demo-box">
        <div class="demo-label">Akun Demo</div>
        <div class="demo-row">
          <span>Owner</span>
          <code>admin / admin123</code>
        </div>
        <div class="demo-row">
          <span>Staff</span>
          <code>staff / staff123</code>
        </div>
      </div>
    </div>

    <div class="login-footer">
      &copy; <?= date('Y') ?> <?= appEscape(APP_NAME) ?> &mdash; <?= appEscape(STORE_NAME) ?>
    </div>

  </div>

  <script>
    document.getElementById('loginForm').addEventListener('submit', function() {
      const btn = document.getElementById('loginBtn');
      btn.textContent = 'Memproses…';
      btn.disabled = true;
    });
  </script>
</body>
</html>
