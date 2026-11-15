<?php
declare(strict_types=1);

/**
 * Authenticated application shell header and sidebar.
 */

require_once __DIR__ . '/auth_check.php';

$currentPage = basename((string) ($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? ''), '.php');

function navActive(string $page): string
{
    global $currentPage;
    return $currentPage === $page ? 'active' : '';
}

function navCurrent(string $page): string
{
    global $currentPage;
    return $currentPage === $page ? 'aria-current="page"' : '';
}

function appIcon(string $name, string $className = ''): string
{
    $paths = [
        'dashboard' => '<rect x="3" y="3" width="7" height="7" rx="2"/><rect x="14" y="3" width="7" height="7" rx="2"/><rect x="3" y="14" width="7" height="7" rx="2"/><rect x="14" y="14" width="7" height="7" rx="2"/>',
        'pos' => '<circle cx="9" cy="20" r="1"/><circle cx="19" cy="20" r="1"/><path d="M3 4h2l2.6 10.4a2 2 0 0 0 2 1.6h7.8a2 2 0 0 0 2-1.6L21 8H7"/>',
        'history' => '<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5M12 7v5l3 2"/>',
        'stock' => '<path d="m21 8-9 5-9-5 9-5 9 5Z"/><path d="m3 8 9 5 9-5M3 8v8l9 5 9-5V8M12 13v8"/>',
        'report' => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
        'revenue' => '<rect x="3" y="5" width="18" height="14" rx="3"/><path d="M3 10h18M7 15h2"/>',
        'alert' => '<path d="M10.3 3.7 2.6 17a2 2 0 0 0 1.7 3h15.4a2 2 0 0 0 1.7-3L13.7 3.7a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4M12 17h.01"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06-2.83 2.83-.06-.06A1.7 1.7 0 0 0 15 19.4a1.7 1.7 0 0 0-1 .6 1.7 1.7 0 0 0-.4 1.1V21H9.6v-.09A1.7 1.7 0 0 0 8.5 19.4a1.7 1.7 0 0 0-1.88.34l-.06.06-2.83-2.83.06-.06A1.7 1.7 0 0 0 4.6 15a1.7 1.7 0 0 0-1.51-1H3V10h.09A1.7 1.7 0 0 0 4.6 9a1.7 1.7 0 0 0-.34-1.88l-.06-.06 2.83-2.83.06.06A1.7 1.7 0 0 0 9 4.6a1.7 1.7 0 0 0 1-.6 1.7 1.7 0 0 0 .4-1.1V3h4v.09A1.7 1.7 0 0 0 15.5 4.6a1.7 1.7 0 0 0 1.88-.34l.06-.06 2.83 2.83-.06.06A1.7 1.7 0 0 0 19.4 9c.18.58.7 1 1.31 1H21v4h-.09A1.7 1.7 0 0 0 19.4 15Z"/>',
        'menu' => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'logout' => '<path d="M10 17l5-5-5-5M15 12H3M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/>',
    ];

    $path = $paths[$name] ?? $paths['dashboard'];
    $classAttribute = $className !== '' ? ' class="' . appEscape($className) . '"' : '';

    return '<svg' . $classAttribute . ' viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $path . '</svg>';
}

$userName = isset($_SESSION['name']) && is_string($_SESSION['name']) ? $_SESSION['name'] : 'User';
$userRole = appSessionRole() ?? 'staff';
$firstCharacter = function_exists('mb_substr') ? mb_substr($userName, 0, 1, 'UTF-8') : substr($userName, 0, 1);
$userInitial = function_exists('mb_strtoupper') ? mb_strtoupper($firstCharacter, 'UTF-8') : strtoupper($firstCharacter);
$userRoleLabel = $userRole === 'owner' ? 'Pemilik' : 'Staf Kasir';
$resolvedPageTitle = isset($pageTitle) && is_string($pageTitle) ? $pageTitle : APP_NAME;
$resolvedPageSubtitle = isset($pageSubtitle) && is_string($pageSubtitle) ? $pageSubtitle : appIndonesianDate();
$displayPageTitle = trim(explode('—', $resolvedPageTitle, 2)[0]);
$csrfToken = appCsrfToken();
$clientConfig = json_encode([
    'storeName' => (string) STORE_NAME,
    'address' => (string) STORE_ADDRESS,
    'phone' => (string) STORE_PHONE,
    'baseUrl' => appBasePath(),
    'csrfToken' => $csrfToken,
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($clientConfig === false) {
    $clientConfig = '{}';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <meta name="description" content="KasirKu - Aplikasi Kasir &amp; Manajemen Toko untuk UMKM">
  <meta name="csrf-token" content="<?= appEscape($csrfToken) ?>">
  <meta name="referrer" content="same-origin">
  <title><?= appEscape($resolvedPageTitle) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&amp;family=Manrope:wght@600;700;800&amp;display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= appEscape(appAssetUrl('assets/css/main.css')) ?>">
  <script>window.KASIRKU_CONFIG = <?= $clientConfig ?>;</script>
  <?= $extraHead ?? '' ?>
</head>
<body class="<?= $currentPage === 'pos' ? 'pos-page' : '' ?>">
<a class="skip-link" href="#mainContentArea">Lewati ke konten utama</a>
<div class="app-wrapper">

  <aside class="sidebar" id="sidebar" aria-label="Navigasi aplikasi">
    <div class="sidebar-logo">
      <div class="logo-icon" aria-hidden="true">K</div>
      <div class="logo-text">
        <?= appEscape(APP_NAME) ?>
        <span><?= appEscape(STORE_NAME) ?></span>
      </div>
    </div>

    <nav class="sidebar-nav" aria-label="Menu utama">
      <?php if ($userRole === 'owner'): ?>
      <div class="nav-section" aria-hidden="true">Utama</div>
      <a href="<?= appEscape(appUrl('pages/dashboard.php')) ?>" class="nav-item <?= navActive('dashboard') ?>" <?= navCurrent('dashboard') ?> data-tooltip="Dashboard" title="Dashboard">
        <span class="nav-icon"><?= appIcon('dashboard') ?></span>
        <span class="nav-label">Dashboard</span>
      </a>
      <?php endif; ?>

      <div class="nav-section" aria-hidden="true">Kasir</div>
      <a href="<?= appEscape(appUrl('pages/pos.php')) ?>" class="nav-item <?= navActive('pos') ?>" <?= navCurrent('pos') ?> data-tooltip="Point of Sale" title="Point of Sale">
        <span class="nav-icon"><?= appIcon('pos') ?></span>
        <span class="nav-label">Point of Sale</span>
      </a>
      <a href="<?= appEscape(appUrl('pages/history.php')) ?>" class="nav-item <?= navActive('history') ?>" <?= navCurrent('history') ?> data-tooltip="Riwayat Transaksi" title="Riwayat Transaksi">
        <span class="nav-icon"><?= appIcon('history') ?></span>
        <span class="nav-label">Riwayat Transaksi</span>
      </a>

      <?php if ($userRole === 'owner'): ?>
      <div class="nav-section" aria-hidden="true">Manajemen</div>
      <a href="<?= appEscape(appUrl('pages/stock.php')) ?>" class="nav-item <?= navActive('stock') ?>" <?= navCurrent('stock') ?> data-tooltip="Stok &amp; Produk" title="Stok &amp; Produk">
        <span class="nav-icon"><?= appIcon('stock') ?></span>
        <span class="nav-label">Stok &amp; Produk</span>
      </a>
      <a href="<?= appEscape(appUrl('pages/report.php')) ?>" class="nav-item <?= navActive('report') ?>" <?= navCurrent('report') ?> data-tooltip="Laporan Pendapatan" title="Laporan Pendapatan">
        <span class="nav-icon"><?= appIcon('report') ?></span>
        <span class="nav-label">Laporan Pendapatan</span>
      </a>
      <?php endif; ?>

      <div class="nav-section" aria-hidden="true">Akun</div>
      <a href="<?= appEscape(appUrl('pages/settings.php')) ?>" class="nav-item <?= navActive('settings') ?>" <?= navCurrent('settings') ?> data-tooltip="Pengaturan" title="Pengaturan">
        <span class="nav-icon"><?= appIcon('settings') ?></span>
        <span class="nav-label">Pengaturan</span>
      </a>
    </nav>

    <div class="sidebar-footer">
      <div class="user-info">
        <div class="user-avatar" aria-hidden="true"><?= appEscape($userInitial) ?></div>
        <div class="user-details">
          <div class="user-name"><?= appEscape($userName) ?></div>
          <div class="user-role"><?= appEscape($userRoleLabel) ?></div>
        </div>
      </div>
    </div>
  </aside>
  <button type="button" class="sidebar-overlay" id="sidebarOverlay" aria-label="Tutup menu" tabindex="-1" style="border:0"></button>

  <div class="main-content" id="mainContent">
    <header class="topbar">
      <div class="topbar-left">
        <button type="button" class="btn-menu" id="menuToggle" title="Buka atau tutup menu" aria-label="Buka atau tutup menu" aria-controls="sidebar" aria-expanded="false"><?= appIcon('menu') ?></button>
        <div>
          <div class="page-kicker">KasirKu <span aria-hidden="true">/</span> <?= appEscape($resolvedPageSubtitle) ?></div>
          <h1 class="page-title"><?= appEscape($displayPageTitle) ?></h1>
        </div>
      </div>
      <div class="topbar-right">
        <div class="topbar-time" id="clock" aria-hidden="true"></div>
        <div class="topbar-profile" aria-label="Pengguna aktif">
          <div class="topbar-avatar" aria-hidden="true"><?= appEscape($userInitial) ?></div>
          <div class="topbar-profile-copy">
            <strong><?= appEscape($userName) ?></strong>
            <span><?= appEscape($userRoleLabel) ?></span>
          </div>
        </div>
        <form method="post" action="<?= appEscape(appUrl('logout.php')) ?>" style="display:inline-flex">
          <input type="hidden" name="csrf_token" value="<?= appEscape($csrfToken) ?>">
          <button type="submit" class="btn-logout" id="logoutBtn">
            <?= appIcon('logout') ?> <span class="logout-label">Keluar</span>
          </button>
        </form>
      </div>
    </header>

    <main class="page-content page-enter" id="mainContentArea" tabindex="-1">
