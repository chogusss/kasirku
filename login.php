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
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&amp;family=Manrope:wght@600;700;800&amp;display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --bg:            oklch(0.965 0.009 310);
      --surface:       oklch(1 0 0);
      --surface-2:     oklch(0.97 0.012 305);
      --surface-3:     oklch(0.94 0.022 305);
      --ink:           oklch(0.26 0.022 310);
      --ink-2:         oklch(0.43 0.027 310);
      --ink-3:         oklch(0.52 0.025 310);
      --primary:       oklch(0.27 0.017 310);
      --primary-dim:   oklch(0.21 0.015 310);
      --primary-surface: oklch(0.91 0.034 305);
      --primary-border:  oklch(0.82 0.052 302);
      --danger:        oklch(0.51 0.16 20);
      --danger-surface:oklch(0.92 0.028 350);
      --border:        oklch(0.90 0.014 310);
      --border-strong: oklch(0.82 0.026 310);
      --ease-out: cubic-bezier(0.22, 1, 0.36, 1);
    }

    html, body {
      height: 100%;
      font-family: 'DM Sans', system-ui, sans-serif;
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

    body::before {
      content: '';
      position: fixed;
      inset: 0;
      background: none;
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
      border: 1px solid oklch(0.80 0.06 20);
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
      box-shadow: 0 0 0 3px oklch(0.84 0.055 302 / 0.34);
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
      color: oklch(1 0 0);
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
      box-shadow: 0 4px 8px oklch(0.27 0.017 310 / 0.18);
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

    /* Soft Lavender Workspace */
    :root {
      --bg: oklch(0.965 0.009 310);
      --surface: oklch(1 0 0);
      --surface-2: oklch(0.97 0.012 305);
      --surface-3: oklch(0.94 0.022 305);
      --ink: oklch(0.26 0.022 310);
      --ink-2: oklch(0.43 0.027 310);
      --ink-3: oklch(0.52 0.025 310);
      --primary: oklch(0.27 0.017 310);
      --primary-dim: oklch(0.21 0.015 310);
      --primary-surface: oklch(0.91 0.034 305);
      --primary-border: oklch(0.82 0.052 302);
      --danger: oklch(0.51 0.16 20);
      --danger-surface: oklch(0.92 0.028 350);
      --border: oklch(0.90 0.014 310);
      --border-strong: oklch(0.82 0.026 310);
    }

    html, body { min-height: 100%; height: auto; font-family: 'DM Sans', system-ui, sans-serif; }
    body { display: grid; place-items: center; padding: 24px; background: var(--bg); }
    body::before { content: none; }
    .login-shell {
      display: grid;
      grid-template-columns: minmax(0, 1.08fr) minmax(360px, 0.92fr);
      width: min(100%, 1020px);
      min-height: 620px;
      overflow: hidden;
      border: 1px solid var(--border);
      border-radius: 16px;
      background: var(--surface);
      box-shadow: 0 8px 24px oklch(0.25 0.02 310 / 0.10);
    }

    .login-showcase {
      position: relative;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      min-width: 0;
      padding: 42px;
      overflow: hidden;
      background: var(--primary-surface);
      border-right: 1px solid var(--primary-border);
    }
    .brand { position: relative; z-index: 2; margin-bottom: 0; }
    .brand-mark { background: var(--primary); color: oklch(1 0 0); border-radius: 12px; font: 800 18px/1 'Manrope', sans-serif; }
    .brand-name { font-family: 'Manrope', sans-serif; }
    .brand-tagline { color: var(--ink-2); }
    .showcase-copy { position: relative; z-index: 2; max-width: 440px; margin-block: auto; padding-block: 50px; }
    .showcase-overline { margin-bottom: 12px; color: var(--ink-2); font-size: 11px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; }
    .showcase-copy h1 { margin: 0 0 14px; color: var(--ink); font: 800 34px/1.18 'Manrope', sans-serif; letter-spacing: -0.04em; text-wrap: balance; }
    .showcase-copy p { max-width: 48ch; color: var(--ink-2); font-size: 14px; line-height: 1.7; }
    .showcase-points { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 22px; }
    .showcase-points span { padding: 6px 10px; border: 1px solid var(--primary-border); border-radius: 999px; background: oklch(1 0 0 / 0.55); color: var(--ink-2); font-size: 11px; font-weight: 700; }
    .showcase-art { position: absolute; right: -28px; bottom: -36px; width: 230px; height: 190px; pointer-events: none; }
    .showcase-art span { position: absolute; display: block; border: 1px solid var(--primary-border); box-shadow: 0 7px 8px oklch(0.40 0.05 302 / 0.10); }
    .art-one { width: 132px; height: 132px; right: 12px; bottom: 18px; border-radius: 50%; background: var(--surface); }
    .art-two { width: 138px; height: 88px; right: 86px; bottom: 10px; border-radius: 48% 52% 44% 50%; background: oklch(0.84 0.055 302); transform: rotate(-12deg); }
    .art-three { width: 72px; height: 72px; right: 64px; bottom: 54px; border-radius: 16px; background: var(--primary); transform: rotate(9deg); }

    .login-panel { display: flex; flex-direction: column; justify-content: center; width: 100%; max-width: none; padding: 42px; }
    .login-card { padding: 0; border: 0; background: transparent; }
    .login-heading { font: 800 24px/1.25 'Manrope', sans-serif; }
    .login-sub { color: var(--ink-2); margin-bottom: 26px; }
    .form-label { color: var(--ink-2); }
    .form-control { min-height: 46px; background: var(--surface); border-color: var(--border-strong); border-radius: 10px; color: var(--ink); }
    .form-control:focus { border-color: var(--primary); box-shadow: 0 0 0 3px oklch(0.84 0.055 302 / 0.34); }
    .btn-login { min-height: 46px; border-radius: 10px; background: var(--primary); color: oklch(1 0 0); }
    .btn-login:hover:not(:disabled) { background: var(--primary-dim); color: oklch(1 0 0); box-shadow: 0 4px 8px oklch(0.27 0.017 310 / 0.18); }
    .demo-box { background: var(--surface-2); border-color: var(--border); border-radius: 12px; }
    .demo-row code { background: var(--primary-surface); color: var(--ink-2); }
    .error-alert { border-color: oklch(0.80 0.06 20); }

    @media (max-width: 820px) {
      body { padding: 14px; }
      .login-shell { grid-template-columns: 1fr; min-height: auto; }
      .login-showcase { min-height: 220px; padding: 28px; border-right: 0; border-bottom: 1px solid var(--primary-border); }
      .showcase-copy { padding: 34px 0 10px; }
      .showcase-copy h1 { max-width: 18ch; font-size: 27px; }
      .showcase-copy p, .showcase-points { display: none; }
      .showcase-art { transform: scale(0.76); transform-origin: right bottom; }
      .login-panel { padding: 30px 28px; }
    }

    @media (max-width: 480px) {
      body { display: block; padding: 0; background: var(--surface); }
      .login-shell { min-height: 100dvh; border: 0; border-radius: 0; box-shadow: none; }
      .login-showcase { min-height: 196px; padding: 24px 20px; }
      .brand-tagline { display: none; }
      .showcase-copy { padding-top: 26px; }
      .showcase-copy h1 { font-size: 23px; }
      .showcase-art { opacity: 0.7; transform: scale(0.62); }
      .login-panel { padding: 28px 20px 34px; }
    }
  </style>
</head>
<body>
  <main class="login-shell">
    <section class="login-showcase" aria-label="Tentang KasirKu">
    <div class="brand">
      <div class="brand-mark">K</div>
      <div class="brand-text">
        <div class="brand-name"><?= appEscape(APP_NAME) ?></div>
        <div class="brand-tagline">Kasir &amp; Manajemen Toko untuk UMKM</div>
      </div>
    </div>

      <div class="showcase-copy">
        <div class="showcase-overline">Ruang kerja toko Anda</div>
        <h1>Kasir lebih cepat, bisnis lebih terarah.</h1>
        <p>Kelola transaksi, stok, dan laporan harian dalam antarmuka yang sederhana dan nyaman dipakai.</p>
        <div class="showcase-points" aria-hidden="true">
          <span>Transaksi cepat</span>
          <span>Stok terpantau</span>
          <span>Laporan rapi</span>
        </div>
      </div>
      <div class="showcase-art" aria-hidden="true"><span class="art-one"></span><span class="art-two"></span><span class="art-three"></span></div>
    </section>

    <div class="login-panel">

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
  </main>

  <script>
    document.getElementById('loginForm').addEventListener('submit', function() {
      const btn = document.getElementById('loginBtn');
      btn.textContent = 'Memproses…';
      btn.disabled = true;
    });
  </script>
</body>
</html>
