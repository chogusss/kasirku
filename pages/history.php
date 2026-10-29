<?php
/**
 * Transaction History Page
 */
require_once __DIR__ . '/../includes/auth_check.php';

$pageTitle    = 'Riwayat Transaksi — KasirKu';
$pageSubtitle = 'Lacak Semua Transaksi';

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Toolbar -->
<div class="page-toolbar">
  <div class="toolbar-fields">
    <div class="search-wrapper toolbar-search">
      <span class="search-icon" aria-hidden="true">🔍</span>
      <input type="search" id="histSearch" class="form-control" placeholder="Cari no. invoice / kasir..." oninput="debounceLoad()" aria-label="Cari nomor invoice atau kasir" autocomplete="off">
    </div>
    <div class="date-picker-group">
      <input type="date" id="dateFrom" class="form-control" onchange="applyHistoryFilters()" aria-label="Tanggal awal">
      <span class="date-sep">s/d</span>
      <input type="date" id="dateTo" class="form-control" onchange="applyHistoryFilters()" aria-label="Tanggal akhir">
    </div>
    <button type="button" class="btn btn-secondary btn-sm" onclick="resetFilter()">Reset</button>
  </div>
</div>

<!-- History Table -->
<div class="card">
  <div class="card-header">
    <div class="card-title">🧾 Semua Transaksi</div>
    <span class="badge badge-primary" id="histTotal">—</span>
  </div>
  <div class="table-wrapper">
    <table class="table responsive-table history-table" aria-label="Riwayat transaksi">
      <thead>
        <tr>
          <th>#</th>
          <th>No. Invoice</th>
          <th>Kasir</th>
          <th>Total Bayar</th>
          <th>Uang Diterima</th>
          <th>Kembalian</th>
          <th>Tanggal & Waktu</th>
          <th>Aksi</th>
        </tr>
      </thead>
      <tbody id="histTableBody">
        <tr><td colspan="8" style="text-align:center;padding:40px;color:var(--text-muted)">Memuat data...</td></tr>
      </tbody>
    </table>
  </div>
  <div class="pagination" id="histPagination" style="padding:16px" role="navigation" aria-label="Navigasi halaman transaksi"></div>
</div>

<!-- ===== DETAIL MODAL ===== -->
<div class="modal-overlay" id="detailModal" role="dialog" aria-modal="true" aria-labelledby="detailModalTitle">
  <div class="modal modal-lg">
    <div class="modal-header">
      <div class="modal-title" id="detailModalTitle">🧾 Detail Transaksi</div>
      <button type="button" class="modal-close" onclick="closeModal('detailModal')" aria-label="Tutup detail transaksi">✕</button>
    </div>
    <div class="modal-body" id="detailContent">
      <div class="loading"><div class="spinner"></div></div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-secondary" onclick="closeModal('detailModal')">Tutup</button>
      <button type="button" class="btn btn-primary" id="reprintBtn" onclick="reprintReceipt()" disabled>🖨️ Cetak Ulang Struk</button>
    </div>
  </div>
</div>

<!-- Print area -->
<link rel="stylesheet" href="<?= appEscape(appAssetUrl('assets/css/print.css')) ?>" media="print">
<script src="<?= appEscape(appAssetUrl('assets/js/history.js')) ?>"></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
