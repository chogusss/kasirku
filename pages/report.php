<?php
/**
 * Revenue Reports - Owner Only
 */
require_once __DIR__ . '/../includes/auth_check.php';
requireOwner();

$pageTitle    = 'Laporan Pendapatan — KasirKu';
$pageSubtitle = 'Ringkasan Omset per Bulan & Tahun';

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Toolbar -->
<div class="page-toolbar report-toolbar">
  <div class="toolbar-fields toolbar-fields-inline">
    <label for="reportYear" style="color:var(--text-muted);font-size:13px">Tahun:</label>
    <select id="reportYear" class="form-control toolbar-year" onchange="loadReport()">
    </select>
  </div>
  <div class="toolbar-actions">
    <button type="button" class="btn btn-secondary" id="exportReportBtn" onclick="exportReportCsv()" disabled>Ekspor CSV</button>
    <button type="button" class="btn btn-secondary" onclick="printReport()">🖨️ Cetak Laporan</button>
  </div>
</div>

<!-- Annual Total -->
<div class="report-total-card" id="annualCard">
  <div class="report-total-label">Total Pendapatan Tahunan</div>
  <div class="report-total-value" id="annualRevenue" aria-live="polite">—</div>
  <div class="report-total-year" id="annualLabel" aria-live="polite">Memuat...</div>
</div>

<!-- Chart -->
<div class="card mb-3">
  <div class="card-header">
    <div class="card-title">📊 Grafik Pendapatan Bulanan</div>
  </div>
  <div class="card-body">
    <div class="chart-container">
      <canvas id="monthlyChart" role="img" aria-label="Grafik pendapatan dan transaksi bulanan"></canvas>
    </div>
  </div>
</div>

<!-- Monthly Table -->
<div class="card">
  <div class="card-header">
    <div class="card-title">📋 Rincian Per Bulan</div>
  </div>
  <div class="table-wrapper">
    <table class="table responsive-table report-table" aria-label="Rincian laporan pendapatan bulanan">
      <thead>
        <tr>
          <th>Bulan</th>
          <th class="text-right">Jumlah Transaksi</th>
          <th class="text-right">Total Pendapatan</th>
          <th class="text-right">Rata-rata/Transaksi</th>
          <th>Grafik Bar</th>
        </tr>
      </thead>
      <tbody id="monthlyTableBody">
        <tr><td colspan="5" style="text-align:center;padding:40px;color:var(--text-muted)">Memuat data...</td></tr>
      </tbody>
      <tfoot id="monthlyTableFoot"></tfoot>
    </table>
  </div>
</div>

<style>
@media print {
  @page { size: A4 portrait; margin: 12mm; }
  body { background: #fff !important; color: #111 !important; }
  body::before, .sidebar, .topbar, .report-toolbar, .toast-container { display: none !important; }
  .main-content { margin-left: 0 !important; min-height: auto !important; }
  .page-content { padding: 0 !important; }
  .card, .report-total-card { background: #fff !important; color: #111 !important; border: 1px solid #bbb !important; break-inside: avoid; }
  .card-title, .report-total-label, .report-total-value, .report-total-year,
  .table th, .table td, .text-primary, .text-muted { color: #111 !important; }
  .table th, .table td { background: #fff !important; border-color: #ccc !important; }
  .mini-bar { background: #222 !important; }
  .chart-container { height: 210px !important; }
}
</style>
<script src="<?= appEscape(appAssetUrl('assets/js/report.js')) ?>"></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
