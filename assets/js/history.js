/**
 * history.js - Transaction History Logic
 * KasirKu UMKM
 */

let histPage = 1;
let histTimer = null;
let historyRequestController = null;
let detailRequestController = null;
let historyRequestSequence = 0;
let currentTxnDetail = null;

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

async function readHistoryResponse(response) {
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

document.addEventListener('DOMContentLoaded', () => {
  setDefaultHistoryDates();
  loadHistory();
});

function toDateInput(date) {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

function setDefaultHistoryDates() {
  const today = new Date();
  const monthAgo = new Date(today);
  monthAgo.setDate(monthAgo.getDate() - 30);
  document.getElementById('dateTo').value = toDateInput(today);
  document.getElementById('dateFrom').value = toDateInput(monthAgo);
}

function debounceLoad() {
  histPage = 1;
  clearTimeout(histTimer);
  histTimer = setTimeout(loadHistory, 300);
}

function applyHistoryFilters() {
  histPage = 1;
  loadHistory();
}

function resetFilter() {
  document.getElementById('histSearch').value = '';
  setDefaultHistoryDates();
  histPage = 1;
  loadHistory();
}

function historyDatesAreValid(dateFrom, dateTo) {
  if (dateFrom && dateTo && dateFrom > dateTo) {
    showToast('Tanggal awal tidak boleh setelah tanggal akhir.', 'warning');
    document.getElementById('dateFrom')?.focus();
    return false;
  }
  return true;
}

async function loadHistory() {
  const searchInput = document.getElementById('histSearch');
  const dateFromInput = document.getElementById('dateFrom');
  const dateToInput = document.getElementById('dateTo');
  const tableBody = document.getElementById('histTableBody');
  const totalElement = document.getElementById('histTotal');
  if (!searchInput || !dateFromInput || !dateToInput || !tableBody || !totalElement) return;

  const dateFrom = dateFromInput.value;
  const dateTo = dateToInput.value;
  if (!historyDatesAreValid(dateFrom, dateTo)) return;

  historyRequestController?.abort();
  historyRequestController = new AbortController();
  const requestSequence = ++historyRequestSequence;

  tableBody.setAttribute('aria-busy', 'true');
  tableBody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:36px"><div class="spinner" style="margin:0 auto" aria-label="Memuat transaksi"></div></td></tr>';

  try {
    const params = new URLSearchParams({
      search: searchInput.value.trim(),
      date_from: dateFrom,
      date_to: dateTo,
      page: String(histPage),
      per_page: '15'
    });
    const response = await fetch(kasirkuUrl(`api/transactions.php?${params.toString()}`), {
      headers: { Accept: 'application/json' },
      signal: historyRequestController.signal
    });
    const payload = await readHistoryResponse(response);
    if (requestSequence !== historyRequestSequence) return;

    const pages = Math.max(1, Number.parseInt(payload.pages, 10) || 1);
    if (histPage > pages) {
      histPage = pages;
      loadHistory();
      return;
    }

    const transactions = Array.isArray(payload.data) ? payload.data : [];
    totalElement.textContent = `${Number.parseInt(payload.total, 10) || 0} transaksi`;

    if (transactions.length === 0) {
      tableBody.innerHTML = `
        <tr><td colspan="8" style="text-align:center;padding:48px">
          <div class="no-data-icon" aria-hidden="true">🧾</div>
          <div class="no-data-text">Belum ada transaksi</div>
          <div class="no-data-sub">Coba ubah rentang tanggal atau kata pencarian.</div>
        </td></tr>`;
      renderHistPagination(payload);
      return;
    }

    tableBody.innerHTML = transactions.map((transaction, index) => {
      const id = Number.parseInt(transaction.id, 10);
      const rowNumber = ((histPage - 1) * 15) + index + 1;
      const date = parseSqlDate(transaction.created_at);
      const dateString = date ? date.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }) : '—';
      const timeString = date ? date.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) : '';

      return `
        <tr>
          <td data-label="#" class="text-muted">${rowNumber}</td>
          <td data-label="Invoice"><span class="badge badge-primary">${escapeHtml(transaction.invoice_no || '—')}</span></td>
          <td data-label="Kasir" style="font-weight:500">${escapeHtml(transaction.cashier_name || '—')}</td>
          <td data-label="Total" class="font-bold text-primary">${escapeHtml(formatRupiah(transaction.total_amount))}</td>
          <td data-label="Diterima" style="color:var(--text-secondary)">${escapeHtml(formatRupiah(transaction.payment_amount))}</td>
          <td data-label="Kembalian" style="color:var(--success);font-weight:600">${escapeHtml(formatRupiah(transaction.change_amount))}</td>
          <td data-label="Waktu" class="text-muted" style="font-size:12px">${escapeHtml(dateString)} ${escapeHtml(timeString)}</td>
          <td data-label="Aksi"><button type="button" class="btn btn-secondary btn-sm" onclick="viewDetail(${id})" aria-label="Lihat detail ${escapeHtml(transaction.invoice_no)}">🔍 Detail</button></td>
        </tr>`;
    }).join('');

    renderHistPagination(payload);
  } catch (error) {
    if (error.name === 'AbortError') return;
    tableBody.innerHTML = `<tr><td colspan="8" class="text-center text-muted" style="padding:30px">${escapeHtml(error.message || 'Koneksi bermasalah')}</td></tr>`;
    console.error(error);
  } finally {
    if (requestSequence === historyRequestSequence) tableBody.setAttribute('aria-busy', 'false');
  }
}

function parseSqlDate(value) {
  if (!value) return null;
  const date = new Date(String(value).replace(' ', 'T'));
  return Number.isNaN(date.getTime()) ? null : date;
}

function renderHistPagination(payload) {
  const pages = Math.max(1, Number.parseInt(payload.pages, 10) || 1);
  const pagination = document.getElementById('histPagination');
  if (!pagination) return;
  if (pages <= 1) {
    pagination.innerHTML = '';
    return;
  }

  let html = '';
  if (histPage > 1) {
    html += `<button type="button" class="page-btn" onclick="goHistPage(${histPage - 1})" aria-label="Halaman sebelumnya">‹</button>`;
  }
  for (let page = Math.max(1, histPage - 2); page <= Math.min(pages, histPage + 2); page += 1) {
    html += `<button type="button" class="page-btn ${page === histPage ? 'active' : ''}" onclick="goHistPage(${page})" aria-label="Halaman ${page}" ${page === histPage ? 'aria-current="page"' : ''}>${page}</button>`;
  }
  if (histPage < pages) {
    html += `<button type="button" class="page-btn" onclick="goHistPage(${histPage + 1})" aria-label="Halaman berikutnya">›</button>`;
  }
  pagination.innerHTML = html;
}

function goHistPage(page) {
  histPage = Math.max(1, Number.parseInt(page, 10) || 1);
  loadHistory();
}

async function viewDetail(transactionId) {
  const id = Number.parseInt(transactionId, 10);
  const content = document.getElementById('detailContent');
  const reprintButton = document.getElementById('reprintBtn');
  if (!Number.isInteger(id) || !content || !reprintButton) return;

  detailRequestController?.abort();
  detailRequestController = new AbortController();
  currentTxnDetail = null;
  reprintButton.disabled = true;
  content.setAttribute('aria-busy', 'true');
  content.innerHTML = '<div class="loading"><div class="spinner" aria-label="Memuat detail transaksi"></div></div>';
  openModal('detailModal');

  try {
    const response = await fetch(kasirkuUrl(`api/transactions.php?action=detail&id=${id}`), {
      headers: { Accept: 'application/json' },
      signal: detailRequestController.signal
    });
    const payload = await readHistoryResponse(response);
    currentTxnDetail = payload;
    reprintButton.disabled = false;
    renderTransactionDetail(payload);
  } catch (error) {
    if (error.name === 'AbortError') return;
    content.innerHTML = `<div class="text-center text-danger" style="padding:30px">${escapeHtml(error.message || 'Koneksi bermasalah')}</div>`;
    console.error(error);
  } finally {
    content.setAttribute('aria-busy', 'false');
  }
}

function renderTransactionDetail(payload) {
  const content = document.getElementById('detailContent');
  const transaction = payload.transaction || {};
  const items = Array.isArray(payload.items) ? payload.items : [];
  const date = parseSqlDate(transaction.created_at);
  const dateString = date
    ? date.toLocaleDateString('id-ID', { weekday: 'long', day: '2-digit', month: 'long', year: 'numeric' })
    : '—';
  const timeString = date ? date.toLocaleTimeString('id-ID') : '';

  const itemRows = items.map(item => `
    <tr>
      <td data-label="Produk" style="font-weight:600;color:var(--text-primary)">${escapeHtml(item.product_name || 'Produk')}</td>
      <td data-label="Qty" class="text-center">${Number.parseInt(item.quantity, 10) || 0}</td>
      <td data-label="Harga" class="text-right">${escapeHtml(formatRupiah(item.price))}</td>
      <td data-label="Subtotal" class="text-right font-bold text-primary">${escapeHtml(formatRupiah(item.subtotal))}</td>
    </tr>`).join('');

  content.innerHTML = `
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-bottom:20px">
      <div style="background:var(--surface-3);border:1px solid var(--border);border-radius:8px;padding:14px">
        <div style="font-size:11px;color:var(--text-muted);margin-bottom:4px;font-weight:700;text-transform:uppercase">No. Invoice</div>
        <div style="font-size:15px;font-weight:800;color:var(--primary)">${escapeHtml(transaction.invoice_no || '—')}</div>
      </div>
      <div style="background:var(--surface-3);border:1px solid var(--border);border-radius:8px;padding:14px">
        <div style="font-size:11px;color:var(--text-muted);margin-bottom:4px;font-weight:700;text-transform:uppercase">Kasir</div>
        <div style="font-size:14px;font-weight:700">${escapeHtml(transaction.cashier_name || '—')}</div>
      </div>
      <div style="background:var(--surface-3);border:1px solid var(--border);border-radius:8px;padding:14px;grid-column:1/-1">
        <div style="font-size:11px;color:var(--text-muted);margin-bottom:4px;font-weight:700;text-transform:uppercase">Tanggal & Waktu</div>
        <div style="font-size:13px;font-weight:600">${escapeHtml(dateString)} · ${escapeHtml(timeString)}</div>
      </div>
    </div>
    <div class="table-wrapper" style="border-radius:8px;overflow:auto;border:1px solid var(--border)">
      <table class="table responsive-table" aria-label="Item transaksi">
        <thead><tr><th>Produk</th><th class="text-center">Qty</th><th class="text-right">Harga</th><th class="text-right">Subtotal</th></tr></thead>
        <tbody>${itemRows || '<tr><td colspan="4" class="text-center text-muted">Tidak ada item.</td></tr>'}</tbody>
      </table>
    </div>
    <div style="margin-top:16px;background:var(--surface-3);border:1px solid var(--border);border-radius:8px;padding:16px">
      <div style="display:flex;justify-content:space-between;margin-bottom:8px;font-size:13px;color:var(--text-secondary)"><span>Total Belanja</span><span style="font-weight:700">${escapeHtml(formatRupiah(transaction.total_amount))}</span></div>
      <div style="display:flex;justify-content:space-between;margin-bottom:8px;font-size:13px;color:var(--text-secondary)"><span>Uang Diterima</span><span style="font-weight:700">${escapeHtml(formatRupiah(transaction.payment_amount))}</span></div>
      <div style="display:flex;justify-content:space-between;font-size:16px;font-weight:800;color:var(--success);padding-top:8px;border-top:1px solid var(--border)"><span>Kembalian</span><span>${escapeHtml(formatRupiah(transaction.change_amount))}</span></div>
      ${transaction.notes ? `<div style="margin-top:10px;font-size:12px;color:var(--text-muted)">Catatan: ${escapeHtml(transaction.notes)}</div>` : ''}
    </div>`;
}

function reprintReceipt() {
  if (!currentTxnDetail) {
    showToast('Detail transaksi belum siap.', 'warning');
    return;
  }

  const transaction = currentTxnDetail.transaction || {};
  const items = Array.isArray(currentTxnDetail.items) ? currentTxnDetail.items : [];
  const date = parseSqlDate(transaction.created_at);
  const store = window.KASIRKU_CONFIG || {};
  const receiptHtml = `
    <div class="receipt" id="receiptReprint">
      <div class="receipt-store-name">${escapeHtml(store.storeName || 'Toko Serba Ada')}</div>
      <div class="receipt-store-sub">${escapeHtml(store.address || 'Jl. Raya No. 1, Jakarta')}<br>Telp: ${escapeHtml(store.phone || '0812-3456-7890')}</div>
      <hr class="receipt-divider">
      <div class="receipt-info">
        <div class="row"><span>Invoice</span><span>${escapeHtml(transaction.invoice_no || '—')}</span></div>
        <div class="row"><span>Kasir</span><span>${escapeHtml(transaction.cashier_name || '—')}</span></div>
        <div class="row"><span>Tgl</span><span>${escapeHtml(date ? date.toLocaleString('id-ID') : '—')}</span></div>
      </div>
      <hr class="receipt-divider">
      <div class="receipt-title">— DETAIL PESANAN —</div>
      ${items.map(item => `
        <div class="receipt-item">
          <span class="receipt-item-name">${escapeHtml(item.product_name || 'Produk')}</span>
          <div class="receipt-item-detail"><span>${Number.parseInt(item.quantity, 10) || 0} × ${escapeHtml(formatRupiah(item.price))}</span><span>${escapeHtml(formatRupiah(item.subtotal))}</span></div>
        </div>`).join('')}
      <hr class="receipt-divider">
      <div class="receipt-totals">
        <div class="grand"><span>TOTAL</span><span>${escapeHtml(formatRupiah(transaction.total_amount))}</span></div>
        <div class="row" style="margin-top:5px"><span>Bayar</span><span>${escapeHtml(formatRupiah(transaction.payment_amount))}</span></div>
        <div class="row"><span>Kembali</span><span>${escapeHtml(formatRupiah(transaction.change_amount))}</span></div>
      </div>
      <hr class="receipt-divider">
      <div class="receipt-footer">Terima kasih telah berbelanja!<br>Barang yang sudah dibeli<br>tidak dapat dikembalikan.</div>
    </div>`;

  let printArea = document.getElementById('printArea');
  if (!printArea) {
    printArea = document.createElement('div');
    printArea.id = 'printArea';
    document.body.appendChild(printArea);
  }
  printArea.innerHTML = receiptHtml;
  window.print();
}
