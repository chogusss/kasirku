<?php
/**
 * Dashboard - Owner Only
 */
require_once __DIR__ . '/../includes/auth_check.php';
requireOwner();

$pageTitle    = 'Dashboard — KasirKu';
$pageSubtitle = 'Ringkasan Bisnis Anda';

require_once __DIR__ . '/../includes/header.php';
?>

<!-- ===== STATS ===== -->
<div class="stats-grid" id="statsGrid">

  <div class="stat-card cyan">
    <div class="stat-card-inner">
      <div class="stat-body">
        <div class="stat-label">Pendapatan Hari Ini</div>
        <div class="stat-value" id="todayRevenue">—</div>
      </div>
      <div class="stat-icon-box">💰</div>
    </div>
  </div>

  <div class="stat-card violet">
    <div class="stat-card-inner">
      <div class="stat-body">
        <div class="stat-label">Transaksi Hari Ini</div>
        <div class="stat-value" id="todayTxn">—</div>
      </div>
      <div class="stat-icon-box">🧾</div>
    </div>
  </div>

  <div class="stat-card green">
    <div class="stat-card-inner">
      <div class="stat-body">
        <div class="stat-label">Total Produk</div>
        <div class="stat-value" id="totalProducts">—</div>
      </div>
      <div class="stat-icon-box">📦</div>
    </div>
  </div>

  <div class="stat-card amber">
    <div class="stat-card-inner">
      <div class="stat-body">
        <div class="stat-label">Stok Hampir Habis</div>
        <div class="stat-value" id="lowStockCount">—</div>
      </div>
      <div class="stat-icon-box">⚠️</div>
    </div>
  </div>

</div>

<!-- ===== MAIN GRID ===== -->
<div class="dashboard-grid">

  <!-- Chart -->
  <div class="card">
    <div class="card-header">
      <div class="card-title">Pendapatan 7 Hari Terakhir</div>
      <a href="<?= appEscape(appUrl('pages/report.php')) ?>" class="btn btn-sm btn-secondary">Laporan Lengkap</a>
    </div>
    <div class="card-body">
      <div class="chart-container" id="revenueChartContainer">
        <canvas id="revenueChart" role="img" aria-label="Grafik pendapatan 7 hari terakhir" aria-describedby="revenueChartSummary"></canvas>
      </div>
      <div class="sr-only" id="revenueChartAccessibleData">
        <p id="revenueChartSummary" aria-live="polite">Memuat data pendapatan tujuh hari terakhir.</p>
        <table>
          <caption>Rincian pendapatan tujuh hari terakhir</caption>
          <thead>
            <tr><th scope="col">Hari</th><th scope="col">Pendapatan</th></tr>
          </thead>
          <tbody id="revenueChartDataBody">
            <tr><td colspan="2">Memuat data…</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Low Stock -->
  <div class="card">
    <div class="card-header">
      <div class="card-title">Stok Menipis</div>
      <a href="<?= appEscape(appUrl('pages/stock.php')) ?>" class="btn btn-sm btn-secondary">Kelola</a>
    </div>
    <div class="card-body" style="padding:0">
      <div id="lowStockList">
        <div class="loading"><div class="spinner" aria-label="Memuat stok menipis"></div></div>
      </div>
    </div>
  </div>

</div>

<!-- ===== RECENT TRANSACTIONS ===== -->
<div class="card mt-3">
  <div class="card-header">
    <div class="card-title">Transaksi Terbaru</div>
    <a href="<?= appEscape(appUrl('pages/history.php')) ?>" class="btn btn-sm btn-secondary">Lihat Semua</a>
  </div>
  <div class="table-wrapper">
    <table class="table responsive-table" aria-label="Tabel transaksi terbaru">
      <thead>
        <tr>
          <th>No. Invoice</th>
          <th>Kasir</th>
          <th>Total</th>
          <th>Waktu</th>
        </tr>
      </thead>
      <tbody id="recentTxnTable">
        <tr>
          <td colspan="4" style="text-align:center;padding:28px;color:var(--ink-3)">Memuat data…</td>
        </tr>
      </tbody>
    </table>
  </div>
</div>

<script>
let revenueChart = null;

function dashboardUrl(path) {
  const baseUrl = String(window.KASIRKU_CONFIG?.baseUrl || '').replace(/\/$/, '');
  return `${baseUrl}/${String(path).replace(/^\//, '')}`;
}

function escapeDashboardHtml(value) {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

function localDateKey(date) {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

function parseDashboardDate(value) {
  const date = new Date(String(value || '').replace(' ', 'T'));
  return Number.isNaN(date.getTime()) ? null : date;
}

function formatRupiah(n) {
  return 'Rp\u00a0' + Number(n).toLocaleString('id-ID');
}

function setAccessibleRevenueData(labels = [], revenues = [], errorMessage = '') {
  const summary = document.getElementById('revenueChartSummary');
  const tableBody = document.getElementById('revenueChartDataBody');
  if (!summary || !tableBody) return;

  tableBody.replaceChildren();
  if (errorMessage) {
    summary.textContent = errorMessage;
    const row = document.createElement('tr');
    const cell = document.createElement('td');
    cell.colSpan = 2;
    cell.textContent = errorMessage;
    row.appendChild(cell);
    tableBody.appendChild(row);
    return;
  }

  const details = [];
  labels.forEach((label, index) => {
    const revenue = Number(revenues[index]) || 0;
    details.push(`${label}: ${formatRupiah(revenue)}`);

    const row = document.createElement('tr');
    const labelCell = document.createElement('td');
    const valueCell = document.createElement('td');
    labelCell.textContent = label;
    valueCell.textContent = formatRupiah(revenue);
    row.append(labelCell, valueCell);
    tableBody.appendChild(row);
  });
  summary.textContent = `Pendapatan tujuh hari terakhir. ${details.join('; ')}.`;
}

function setDashboardErrorState(message) {
  ['todayRevenue', 'todayTxn', 'totalProducts', 'lowStockCount'].forEach(id => {
    const element = document.getElementById(id);
    if (element) element.textContent = 'Tidak tersedia';
  });

  if (revenueChart) {
    revenueChart.destroy();
    revenueChart = null;
  }
  const chartContainer = document.getElementById('revenueChartContainer');
  if (chartContainer) {
    chartContainer.innerHTML = `
      <div class="no-data">
        <div class="no-data-text">Grafik tidak dapat dimuat</div>
        <div class="no-data-sub">${escapeDashboardHtml(message)}</div>
        <button type="button" class="btn btn-secondary btn-sm mt-1" onclick="window.location.reload()">Coba lagi</button>
      </div>`;
  }
  setAccessibleRevenueData([], [], `Data pendapatan tidak dapat dimuat. ${message}`);

  const lowStock = document.getElementById('lowStockList');
  if (lowStock) {
    lowStock.innerHTML = `
      <div class="no-data">
        <div class="no-data-text">Data stok tidak dapat dimuat</div>
        <div class="no-data-sub">Silakan coba lagi.</div>
      </div>`;
  }

  const recentTransactions = document.getElementById('recentTxnTable');
  if (recentTransactions) {
    recentTransactions.innerHTML = `
      <tr><td colspan="4" class="text-center text-muted" style="padding:28px">Transaksi terbaru tidak dapat dimuat. Silakan coba lagi.</td></tr>`;
  }
}

async function loadDashboard() {
  try {
    const res  = await fetch(dashboardUrl('api/reports.php?action=dashboard'), { headers: { Accept: 'application/json' } });
    const json = await res.json();
    if (!res.ok || !json.success) throw new Error(json.message || 'Gagal memuat dashboard');

    // --- Stats ---
    document.getElementById('todayRevenue').textContent  = formatRupiah(json.today.revenue);
    document.getElementById('todayTxn').textContent      = json.today.txn_count;
    document.getElementById('totalProducts').textContent = json.products;
    document.getElementById('lowStockCount').textContent = json.low_stock;

    // --- Chart ---
    const dayNames = ['Min','Sen','Sel','Rab','Kam','Jum','Sab'];
    const labels = [], revenues = [];

    for (let i = 6; i >= 0; i--) {
      const d = new Date();
      d.setDate(d.getDate() - i);
      const dateStr  = localDateKey(d);
      const dayLabel = dayNames[d.getDay()] + ' ' + d.getDate();
      const found    = (Array.isArray(json.chart) ? json.chart : []).find(c => c.date === dateStr);
      labels.push(dayLabel);
      revenues.push(found ? parseFloat(found.revenue) : 0);
    }
    setAccessibleRevenueData(labels, revenues);

    try {
      if (typeof window.Chart !== 'function') throw new Error('Pustaka grafik tidak tersedia');
      if (revenueChart) revenueChart.destroy();

    const ctx = document.getElementById('revenueChart').getContext('2d');

    // Gradient fill using OKLCH primary teal
    const grad = ctx.createLinearGradient(0, 0, 0, 220);
    grad.addColorStop(0,   'oklch(0.62 0.14 160 / 0.30)');
    grad.addColorStop(1,   'oklch(0.62 0.14 160 / 0.02)');

    revenueChart = new window.Chart(ctx, {
      type: 'bar',
      data: {
        labels,
        datasets: [{
          label: 'Pendapatan',
          data: revenues,
          backgroundColor: grad,
          borderColor: 'oklch(0.62 0.14 160)',
          borderWidth: 1.5,
          borderRadius: 5,
          borderSkipped: false,
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            backgroundColor: 'oklch(0.17 0.012 220)',
            borderColor:     'oklch(0.35 0.010 220)',
            borderWidth: 1,
            titleColor: 'oklch(0.94 0.005 220)',
            bodyColor:  'oklch(0.76 0.008 220)',
            padding: 10,
            callbacks: {
              label: ctx => formatRupiah(ctx.raw)
            }
          }
        },
        scales: {
          x: {
            grid: { color: 'oklch(0.25 0.008 220 / 0.6)', drawBorder: false },
            ticks: { color: 'oklch(0.75 0.008 220)', font: { size: 12 } },
            border: { display: false }
          },
          y: {
            grid: { color: 'oklch(0.25 0.008 220 / 0.6)', drawBorder: false },
            ticks: {
              color: 'oklch(0.75 0.008 220)',
              font: { size: 11 },
              callback: val => 'Rp\u00a0' + (val >= 1000 ? (val/1000).toLocaleString('id-ID') + 'k' : val)
            },
            border: { display: false }
          }
        }
      }
    });
    } catch (chartError) {
      console.warn(chartError);
      const chartContainer = document.getElementById('revenueChart')?.parentElement;
      if (chartContainer) {
        chartContainer.innerHTML = '<div class="no-data"><div class="no-data-text">Grafik tidak tersedia</div><div class="no-data-sub">Ringkasan lain tetap dapat digunakan.</div></div>';
      }
    }

    // --- Low stock ---
    const lowEl = document.getElementById('lowStockList');
    const lowStockList = Array.isArray(json.low_stock_list) ? json.low_stock_list : [];
    if (lowStockList.length === 0) {
      lowEl.innerHTML = `
        <div class="no-data">
          <span class="no-data-icon">✅</span>
          <div class="no-data-text">Stok aman!</div>
          <div class="no-data-sub">Semua produk masih tersedia.</div>
        </div>`;
    } else {
      lowEl.innerHTML = lowStockList.map(p => {
        const stock = Number.parseInt(p.stock, 10) || 0;
        const cls = stock <= 5 ? 'badge-danger' : 'badge-warning';
        return `<div style="display:flex;align-items:center;justify-content:space-between;padding:11px 18px;border-bottom:1px solid oklch(0.20 0.005 220 / 0.6)">
          <span style="font-size:13px;font-weight:500;color:var(--ink)">${escapeDashboardHtml(p.name || 'Tanpa nama')}</span>
          <span class="badge ${cls}">${stock} ${escapeDashboardHtml(p.unit || 'pcs')}</span>
        </div>`;
      }).join('');
    }

    // --- Recent transactions ---
    const tbody = document.getElementById('recentTxnTable');
    const recentTransactions = Array.isArray(json.recent) ? json.recent : [];
    if (recentTransactions.length === 0) {
      tbody.innerHTML = `<tr><td colspan="4" class="text-center text-muted" style="padding:28px">Belum ada transaksi hari ini.</td></tr>`;
    } else {
      tbody.innerHTML = recentTransactions.map(t => {
        const transactionDate = parseDashboardDate(t.created_at);
        return `
        <tr>
          <td data-label="Invoice"><span class="badge badge-primary">${escapeDashboardHtml(t.invoice_no || '—')}</span></td>
          <td data-label="Kasir" style="font-weight:500">${escapeDashboardHtml(t.cashier_name || '—')}</td>
          <td data-label="Total" style="font-weight:700;color:var(--primary)">${escapeDashboardHtml(formatRupiah(t.total_amount))}</td>
          <td data-label="Waktu" style="color:var(--ink-3)">${escapeDashboardHtml(transactionDate ? transactionDate.toLocaleString('id-ID') : '—')}</td>
        </tr>
      `; }).join('');
    }

  } catch (e) {
    console.error(e);
    const message = e.message || 'Gagal memuat data dashboard';
    setDashboardErrorState(message);
    showToast(message, 'error');
  }
}

document.addEventListener('DOMContentLoaded', loadDashboard);
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
