/**
 * report.js - Revenue Report Logic
 * KasirKu UMKM
 */

let monthlyChart = null;
let reportRequestController = null;
let reportRequestSequence = 0;
let lastReport = null;

function kasirkuUrl(path) {
  const baseUrl = String(window.KASIRKU_CONFIG?.baseUrl || '').replace(/\/$/, '');
  return `${baseUrl}/${String(path).replace(/^\//, '')}`;
}

function escapeHtml(value) {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

async function readReportResponse(response) {
  let payload;
  try {
    payload = await response.json();
  } catch (error) {
    throw new Error('Respons server tidak valid.');
  }

  if (response.status === 401) {
    window.location.assign(kasirkuUrl('login.php'));
    throw new Error('Sesi Anda telah berakhir.');
  }
  if (!response.ok || !payload.success) {
    throw new Error(payload.message || 'Permintaan tidak dapat diproses.');
  }
  return payload;
}

document.addEventListener('DOMContentLoaded', loadYears);

async function loadYears() {
  const select = document.getElementById('reportYear');
  const currentYear = new Date().getFullYear();
  if (!select) return;

  try {
    const response = await fetch(kasirkuUrl('api/reports.php?action=years'), {
      headers: { Accept: 'application/json' }
    });
    const payload = await readReportResponse(response);
    const years = [...new Set([
      currentYear,
      ...(Array.isArray(payload.data) ? payload.data : []).map(Number).filter(Number.isInteger)
    ])].sort((a, b) => b - a);
    populateYearOptions(select, years, currentYear);
  } catch (error) {
    console.error(error);
    populateYearOptions(select, [currentYear], currentYear);
    showToast(error.message || 'Gagal memuat daftar tahun', 'warning');
  }

  loadReport();
}

function populateYearOptions(select, years, selectedYear) {
  select.replaceChildren();
  years.forEach(year => {
    const option = new Option(`Tahun ${year}`, String(year), false, year === selectedYear);
    select.add(option);
  });
}

async function loadReport() {
  const yearSelect = document.getElementById('reportYear');
  const tableBody = document.getElementById('monthlyTableBody');
  const tableFoot = document.getElementById('monthlyTableFoot');
  const exportButton = document.getElementById('exportReportBtn');
  if (!yearSelect || !tableBody || !tableFoot) return;

  const year = Number.parseInt(yearSelect.value, 10) || new Date().getFullYear();
  reportRequestController?.abort();
  reportRequestController = new AbortController();
  const requestSequence = ++reportRequestSequence;
  lastReport = null;
  if (exportButton) exportButton.disabled = true;

  document.getElementById('annualRevenue').textContent = '—';
  document.getElementById('annualLabel').textContent = `Memuat data tahun ${year}…`;
  tableBody.setAttribute('aria-busy', 'true');
  tableBody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:36px"><div class="spinner" style="margin:0 auto" aria-label="Memuat laporan"></div></td></tr>';
  tableFoot.innerHTML = '';

  try {
    const response = await fetch(kasirkuUrl(`api/reports.php?action=monthly&year=${encodeURIComponent(year)}`), {
      headers: { Accept: 'application/json' },
      signal: reportRequestController.signal
    });
    const payload = await readReportResponse(response);
    if (requestSequence !== reportRequestSequence) return;

    const data = Array.isArray(payload.data) ? payload.data : [];
    const annual = payload.annual || { total_transactions: 0, total_revenue: 0 };
    lastReport = { year, data, annual };
    if (exportButton) exportButton.disabled = false;

    document.getElementById('annualRevenue').textContent = formatRupiah(annual.total_revenue);
    document.getElementById('annualLabel').textContent = `Total ${Number.parseInt(annual.total_transactions, 10) || 0} transaksi sepanjang tahun ${year}`;

    renderMonthlyChart(data, year);
    renderMonthlyTable(data, annual, year, tableBody, tableFoot);
  } catch (error) {
    if (error.name === 'AbortError') return;
    document.getElementById('annualLabel').textContent = 'Data tidak dapat dimuat.';
    tableBody.innerHTML = `<tr><td colspan="5" class="text-center text-muted" style="padding:30px">${escapeHtml(error.message || 'Koneksi bermasalah')}</td></tr>`;
    showChartFallback(error.message || 'Grafik tidak dapat dimuat.');
    console.error(error);
  } finally {
    if (requestSequence === reportRequestSequence) tableBody.setAttribute('aria-busy', 'false');
  }
}

function renderMonthlyChart(data, year) {
  const canvas = document.getElementById('monthlyChart');
  if (!canvas) return;

  if (typeof window.Chart !== 'function') {
    showChartFallback('Pustaka grafik tidak tersedia. Rincian tetap dapat dilihat pada tabel.');
    return;
  }

  clearChartFallback();
  canvas.hidden = false;
  if (monthlyChart) monthlyChart.destroy();

  const revenues = data.map(month => Number.parseFloat(month.total_revenue) || 0);
  const currentMonth = new Date().getMonth();
  const isCurrentYear = year === new Date().getFullYear();
  const compactChart = window.innerWidth <= 600;

  try {
    monthlyChart = new window.Chart(canvas.getContext('2d'), {
      type: 'bar',
      data: {
        labels: data.map(month => String(month.month_name || '').slice(0, 3)),
        datasets: [
          {
            label: 'Pendapatan',
            data: revenues,
            backgroundColor: data.map((_, index) => index === currentMonth && isCurrentYear ? 'rgba(34, 197, 137, 0.75)' : 'rgba(34, 197, 137, 0.35)'),
            borderColor: 'rgba(34, 197, 137, 1)',
            borderWidth: 1,
            borderRadius: 5,
            borderSkipped: false
          },
          {
            label: 'Transaksi',
            data: data.map(month => Number.parseInt(month.total_transactions, 10) || 0),
            type: 'line',
            borderColor: 'rgba(139, 92, 246, 1)',
            backgroundColor: 'rgba(139, 92, 246, 0.1)',
            borderWidth: 2,
            pointBackgroundColor: 'rgba(139, 92, 246, 1)',
            pointRadius: 3,
            tension: 0.25,
            yAxisID: 'y2'
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: !compactChart,
            labels: { color: '#94A3B8', font: { size: 12 } }
          },
          tooltip: {
            callbacks: {
              label(context) {
                return context.dataset.label === 'Pendapatan'
                  ? ` ${formatRupiah(context.raw)}`
                  : ` ${context.raw} transaksi`;
              }
            }
          }
        },
        scales: {
          x: {
            grid: { color: 'rgba(148,163,184,0.07)' },
            ticks: {
              color: '#94A3B8',
              font: { size: compactChart ? 10 : 11 },
              autoSkip: true,
              maxTicksLimit: compactChart ? 6 : 12
            }
          },
          y: {
            beginAtZero: true,
            grid: { color: 'rgba(148,163,184,0.07)' },
            ticks: {
              color: '#94A3B8',
              font: { size: 11 },
              callback(value) {
                if (value >= 1000000) return `Rp ${(value / 1000000).toLocaleString('id-ID', { maximumFractionDigits: 1 })} jt`;
                if (value >= 1000) return `Rp ${Math.round(value / 1000)} rb`;
                return `Rp ${value}`;
              }
            }
          },
          y2: {
            display: !compactChart,
            beginAtZero: true,
            position: 'right',
            grid: { display: false },
            ticks: { color: '#A78BFA', precision: 0, font: { size: 11 } }
          }
        }
      }
    });
  } catch (error) {
    console.error(error);
    showChartFallback('Grafik tidak dapat ditampilkan. Rincian tetap tersedia pada tabel.');
  }
}

function showChartFallback(message) {
  const canvas = document.getElementById('monthlyChart');
  if (!canvas) return;
  canvas.hidden = true;
  let fallback = document.getElementById('monthlyChartFallback');
  if (!fallback) {
    fallback = document.createElement('div');
    fallback.id = 'monthlyChartFallback';
    fallback.className = 'no-data';
    canvas.parentElement.appendChild(fallback);
  }
  fallback.textContent = message;
}

function clearChartFallback() {
  document.getElementById('monthlyChartFallback')?.remove();
}

function renderMonthlyTable(data, annual, year, tableBody, tableFoot) {
  const revenues = data.map(month => Number.parseFloat(month.total_revenue) || 0);
  const maxRevenue = Math.max(...revenues, 1);
  const hasAnnualData = data.some(month => (Number.parseInt(month.total_transactions, 10) || 0) > 0);

  if (!hasAnnualData) {
    tableBody.innerHTML = `
      <tr><td colspan="5" style="text-align:center;padding:48px">
        <div class="no-data-icon" aria-hidden="true">📈</div>
        <div class="no-data-text">Belum ada data transaksi</div>
        <div class="no-data-sub">untuk tahun ${year}</div>
      </td></tr>`;
    tableFoot.innerHTML = '';
    return;
  }

  tableBody.innerHTML = data.map((month, index) => {
    const transactionCount = Number.parseInt(month.total_transactions, 10) || 0;
    const revenue = Number.parseFloat(month.total_revenue) || 0;
    const average = Number.parseFloat(month.avg_per_transaction) || 0;
    const hasData = transactionCount > 0;
    const isCurrentMonth = index === new Date().getMonth() && year === new Date().getFullYear();
    const percentage = Math.max(0, Math.min(100, revenue / maxRevenue * 100));

    return `
      <tr style="${isCurrentMonth ? 'background:var(--primary-surface)' : ''}">
        <td data-label="Bulan" style="font-weight:${hasData ? '700' : '400'};color:${hasData ? 'var(--text-primary)' : 'var(--text-muted)'}">${isCurrentMonth ? '📍 ' : ''}${escapeHtml(month.month_name || '')}</td>
        <td data-label="Transaksi" class="text-right">${hasData ? `<span class="badge badge-info">${transactionCount} transaksi</span>` : '<span class="text-muted">—</span>'}</td>
        <td data-label="Pendapatan" class="text-right font-bold ${hasData ? 'text-primary' : 'text-muted'}">${hasData ? escapeHtml(formatRupiah(revenue)) : '—'}</td>
        <td data-label="Rata-rata" class="text-right text-muted" style="font-size:12px">${hasData ? escapeHtml(formatRupiah(average)) : '—'}</td>
        <td data-label="Grafik" style="min-width:120px;padding-right:20px"><div style="background:var(--surface-3);border-radius:4px;height:8px;overflow:hidden"><div class="mini-bar" style="width:${percentage.toFixed(1)}%"></div></div></td>
      </tr>`;
  }).join('');

  const totalTransactions = Number.parseInt(annual.total_transactions, 10) || 0;
  const totalRevenue = Number.parseFloat(annual.total_revenue) || 0;
  tableFoot.innerHTML = `
    <tr style="background:var(--primary-surface);border-top:2px solid var(--primary-border)">
      <td data-label="Periode" style="font-weight:800;color:var(--primary);font-size:14px">TOTAL ${year}</td>
      <td data-label="Transaksi" class="text-right"><span class="badge badge-primary">${totalTransactions} transaksi</span></td>
      <td data-label="Pendapatan" class="text-right font-bold text-primary" style="font-size:16px">${escapeHtml(formatRupiah(totalRevenue))}</td>
      <td data-label="Rata-rata" class="text-right text-muted" style="font-size:12px">${totalTransactions > 0 ? escapeHtml(formatRupiah(totalRevenue / totalTransactions)) : '—'}</td>
      <td data-label="Grafik"></td>
    </tr>`;
}

function exportReportCsv() {
  if (!lastReport) {
    showToast('Laporan belum siap untuk diekspor.', 'warning');
    return;
  }

  const rows = [
    ['Bulan', 'Jumlah Transaksi', 'Total Pendapatan', 'Rata-rata per Transaksi'],
    ...lastReport.data.map(month => [
      month.month_name,
      Number.parseInt(month.total_transactions, 10) || 0,
      Number.parseFloat(month.total_revenue) || 0,
      Number.parseFloat(month.avg_per_transaction) || 0
    ]),
    ['TOTAL', Number.parseInt(lastReport.annual.total_transactions, 10) || 0, Number.parseFloat(lastReport.annual.total_revenue) || 0, '']
  ];
  const csv = `\uFEFF${rows.map(row => row.map(csvCell).join(';')).join('\r\n')}`;
  const url = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }));
  const link = document.createElement('a');
  link.href = url;
  link.download = `laporan-pendapatan-${lastReport.year}.csv`;
  document.body.appendChild(link);
  link.click();
  link.remove();
  window.setTimeout(() => URL.revokeObjectURL(url), 0);
}

function csvCell(value) {
  return `"${String(value ?? '').replace(/"/g, '""')}"`;
}

function printReport() {
  window.print();
}
