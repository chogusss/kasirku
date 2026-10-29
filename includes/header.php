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

$userName = isset($_SESSION['name']) && is_string($_SESSION['name']) ? $_SESSION['name'] : 'User';
$userRole = appSessionRole() ?? 'staff';
$firstCharacter = function_exists('mb_substr') ? mb_substr($userName, 0, 1, 'UTF-8') : substr($userName, 0, 1);
$userInitial = function_exists('mb_strtoupper') ? mb_strtoupper($firstCharacter, 'UTF-8') : strtoupper($firstCharacter);
$userRoleLabel = $userRole === 'owner' ? 'Pemilik' : 'Staf Kasir';
$resolvedPageTitle = isset($pageTitle) && is_string($pageTitle) ? $pageTitle : APP_NAME;
$resolvedPageSubtitle = isset($pageSubtitle) && is_string($pageSubtitle) ? $pageSubtitle : appIndonesianDate();
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
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&amp;display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= appEscape(appAssetUrl('assets/css/main.css')) ?>">
  <script>window.KASIRKU_CONFIG = <?= $clientConfig ?>;</script>
  <?= $extraHead ?? '' ?>
</head>
<body class="<?= $currentPage === 'pos' ? 'pos-page' : '' ?>">
<a class="skip-link" href="#mainContentArea">Lewati ke konten utama</a>
<div class="app-wrapper">

  <aside class="sidebar" id="sidebar" aria-label="Navigasi aplikasi">
    <div class="sidebar-logo">
      <div class="logo-icon" aria-hidden="true">🧾</div>
      <div class="logo-text">
        <?= appEscape(APP_NAME) ?>
        <span><?= appEscape(STORE_NAME) ?></span>
      </div>
    </div>

    <nav class="sidebar-nav" aria-label="Menu utama">
      <?php if ($userRole === 'owner'): ?>
      <div class="nav-section" aria-hidden="true">Utama</div>
      <a href="<?= appEscape(appUrl('pages/dashboard.php')) ?>" class="nav-item <?= navActive('dashboard') ?>" <?= navCurrent('dashboard') ?>>
        <span class="nav-icon" aria-hidden="true">📊</span>
        <span class="nav-label">Dashboard</span>
      </a>
      <?php endif; ?>

      <div class="nav-section" aria-hidden="true">Kasir</div>
      <a href="<?= appEscape(appUrl('pages/pos.php')) ?>" class="nav-item <?= navActive('pos') ?>" <?= navCurrent('pos') ?>>
        <span class="nav-icon" aria-hidden="true">🛒</span>
        <span class="nav-label">Point of Sale</span>
      </a>
      <a href="<?= appEscape(appUrl('pages/history.php')) ?>" class="nav-item <?= navActive('history') ?>" <?= navCurrent('history') ?>>
        <span class="nav-icon" aria-hidden="true">🧾</span>
        <span class="nav-label">Riwayat Transaksi</span>
      </a>

      <?php if ($userRole === 'owner'): ?>
      <div class="nav-section" aria-hidden="true">Manajemen</div>
      <a href="<?= appEscape(appUrl('pages/stock.php')) ?>" class="nav-item <?= navActive('stock') ?>" <?= navCurrent('stock') ?>>
        <span class="nav-icon" aria-hidden="true">📦</span>
        <span class="nav-label">Stok &amp; Produk</span>
      </a>
      <a href="<?= appEscape(appUrl('pages/report.php')) ?>" class="nav-item <?= navActive('report') ?>" <?= navCurrent('report') ?>>
        <span class="nav-icon" aria-hidden="true">📈</span>
        <span class="nav-label">Laporan Pendapatan</span>
      </a>
      <?php endif; ?>

      <div class="nav-section" aria-hidden="true">Akun</div>
      <a href="<?= appEscape(appUrl('pages/settings.php')) ?>" class="nav-item <?= navActive('settings') ?>" <?= navCurrent('settings') ?>>
        <span class="nav-icon" aria-hidden="true">⚙️</span>
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
        <button type="button" class="btn-menu" id="menuToggle" title="Buka atau tutup menu" aria-label="Buka atau tutup menu" aria-controls="sidebar" aria-expanded="false">☰</button>
        <div>
          <h1 class="page-title"><?= appEscape($resolvedPageTitle) ?></h1>
          <div class="page-subtitle"><?= appEscape($resolvedPageSubtitle) ?></div>
        </div>
      </div>
      <div class="topbar-right">
        <div class="topbar-time" id="clock" aria-hidden="true"></div>
        <form method="post" action="<?= appEscape(appUrl('logout.php')) ?>" style="display:inline-flex">
          <input type="hidden" name="csrf_token" value="<?= appEscape($csrfToken) ?>">
          <button type="submit" class="btn-logout" id="logoutBtn">
            <span aria-hidden="true">🚪</span> Keluar
          </button>
        </form>
      </div>
    </header>

    <main class="page-content page-enter" id="mainContentArea" tabindex="-1">
