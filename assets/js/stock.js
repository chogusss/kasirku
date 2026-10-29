/**
 * stock.js - Stock Management Logic
 * KasirKu UMKM
 */

let stockPage = 1;
let stockTimer = null;
let stockRequestController = null;
let stockRequestSequence = 0;
let stockMutationInFlight = false;
let categories = [];
let stockItemsById = new Map();

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

async function readStockResponse(response) {
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
  loadCategoriesForStock();
  loadStock();
});

function debounceLoad() {
  stockPage = 1;
  clearTimeout(stockTimer);
  stockTimer = setTimeout(loadStock, 300);
}

function applyStockFilters() {
  stockPage = 1;
  loadStock();
}

async function loadCategoriesForStock() {
  try {
    const response = await fetch(kasirkuUrl('api/products.php?action=categories'), {
      headers: { Accept: 'application/json' }
    });
    const payload = await readStockResponse(response);
    categories = Array.isArray(payload.data) ? payload.data : [];

    const targets = [
      { element: document.getElementById('stockCategoryFilter'), placeholder: 'Semua Kategori', value: '0' },
      { element: document.getElementById('addCategory'), placeholder: 'Pilih Kategori', value: '' },
      { element: document.getElementById('editCategory'), placeholder: 'Pilih Kategori', value: '' }
    ];

    targets.forEach(({ element, placeholder, value }) => {
      if (!element) return;
      element.replaceChildren(new Option(placeholder, value));
      categories.forEach(category => {
        element.add(new Option(category.name || 'Tanpa nama', String(category.id)));
      });
    });
  } catch (error) {
    console.error(error);
    showToast(error.message || 'Gagal memuat kategori', 'error');
  }
}

async function loadStock() {
  const searchInput = document.getElementById('stockSearch');
  const categoryFilter = document.getElementById('stockCategoryFilter');
  const tableBody = document.getElementById('stockTableBody');
  const totalElement = document.getElementById('stockTotal');
  if (!searchInput || !categoryFilter || !tableBody || !totalElement) return;

  stockRequestController?.abort();
  stockRequestController = new AbortController();
  const requestSequence = ++stockRequestSequence;

  tableBody.setAttribute('aria-busy', 'true');
  tableBody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:36px"><div class="spinner" style="margin:0 auto" aria-label="Memuat produk"></div></td></tr>';

  try {
    const params = new URLSearchParams({
      search: searchInput.value.trim(),
      page: String(stockPage),
      per_page: '15'
    });
    if (categoryFilter.value !== '0') params.set('category_id', categoryFilter.value);
    const response = await fetch(kasirkuUrl(`api/products.php?${params.toString()}`), {
      headers: { Accept: 'application/json' },
      signal: stockRequestController.signal
    });
    const payload = await readStockResponse(response);
    if (requestSequence !== stockRequestSequence) return;

    const pages = Math.max(1, Number.parseInt(payload.pages, 10) || 1);
    if (stockPage > pages) {
      stockPage = pages;
      loadStock();
      return;
    }

    const products = Array.isArray(payload.data) ? payload.data : [];
    stockItemsById = new Map(products.map(product => [Number.parseInt(product.id, 10), product]));
    totalElement.textContent = `${Number.parseInt(payload.total, 10) || 0} produk`;

    if (products.length === 0) {
      tableBody.innerHTML = `
        <tr><td colspan="8" style="text-align:center;padding:40px">
          <div class="no-data-icon" aria-hidden="true">📦</div>
          <div class="no-data-text">Produk tidak ditemukan</div>
          <div class="no-data-sub">Coba ubah pencarian atau kategori.</div>
        </td></tr>`;
      renderStockPagination(payload);
      return;
    }

    tableBody.innerHTML = products.map((product, index) => {
      const id = Number.parseInt(product.id, 10);
      const stock = Math.max(0, Number.parseInt(product.stock, 10) || 0);
      const stockClass = stock <= 5 ? 'badge-danger' : stock <= 10 ? 'badge-warning' : 'badge-success';
      const stockLabel = stock <= 5 ? 'Kritis' : stock <= 10 ? 'Menipis' : 'Aman';
      const rowNumber = ((stockPage - 1) * 15) + index + 1;

      return `
        <tr>
          <td data-label="#" class="text-muted">${rowNumber}</td>
          <td data-label="Produk" style="font-weight:600;color:var(--text-primary)">${escapeHtml(product.name || 'Tanpa nama')}</td>
          <td data-label="Kategori">${product.category_name ? `<span class="badge badge-info">${escapeHtml(product.category_name)}</span>` : '<span class="text-muted">—</span>'}</td>
          <td data-label="Harga" class="font-bold text-primary">${escapeHtml(formatRupiah(product.price))}</td>
          <td data-label="Stok">
            <span class="badge ${stockClass}">${stock} ${escapeHtml(product.unit || 'pcs')}</span>
            <div style="font-size:10px;color:var(--text-muted);margin-top:2px">${stockLabel}</div>
          </td>
          <td data-label="Satuan" class="text-muted">${escapeHtml(product.unit || 'pcs')}</td>
          <td data-label="Diperbarui" class="text-muted" style="font-size:12px">${escapeHtml(formatSqlDate(product.updated_at))}</td>
          <td data-label="Aksi">
            <div style="display:flex;gap:6px">
              <button type="button" class="btn btn-warning btn-sm" onclick="openEditModal(${id})" aria-label="Edit ${escapeHtml(product.name)}">✏️ Edit</button>
              <button type="button" class="btn btn-danger btn-sm" data-delete-id="${id}" onclick="deleteProduct(${id})" aria-label="Hapus ${escapeHtml(product.name)}">🗑️</button>
            </div>
          </td>
        </tr>`;
    }).join('');

    renderStockPagination(payload);
  } catch (error) {
    if (error.name === 'AbortError') return;
    tableBody.innerHTML = `<tr><td colspan="8" class="text-center text-muted" style="padding:30px">${escapeHtml(error.message || 'Koneksi bermasalah')}</td></tr>`;
    console.error(error);
  } finally {
    if (requestSequence === stockRequestSequence) tableBody.setAttribute('aria-busy', 'false');
  }
}

function formatSqlDate(value) {
  if (!value) return '—';
  const date = new Date(String(value).replace(' ', 'T'));
  return Number.isNaN(date.getTime()) ? '—' : date.toLocaleDateString('id-ID');
}

function renderStockPagination(payload) {
  const pages = Math.max(1, Number.parseInt(payload.pages, 10) || 1);
  const pagination = document.getElementById('stockPagination');
  if (!pagination) return;

  if (pages <= 1) {
    pagination.innerHTML = '';
    return;
  }

  let html = '';
  if (stockPage > 1) {
    html += `<button type="button" class="page-btn" onclick="goStockPage(${stockPage - 1})" aria-label="Halaman sebelumnya">‹</button>`;
  }
  for (let page = Math.max(1, stockPage - 2); page <= Math.min(pages, stockPage + 2); page += 1) {
    html += `<button type="button" class="page-btn ${page === stockPage ? 'active' : ''}" onclick="goStockPage(${page})" aria-label="Halaman ${page}" ${page === stockPage ? 'aria-current="page"' : ''}>${page}</button>`;
  }
  if (stockPage < pages) {
    html += `<button type="button" class="page-btn" onclick="goStockPage(${stockPage + 1})" aria-label="Halaman berikutnya">›</button>`;
  }
  pagination.innerHTML = html;
}

function goStockPage(page) {
  stockPage = Math.max(1, Number.parseInt(page, 10) || 1);
  loadStock();
}

function openAddModal() {
  document.getElementById('addProductForm')?.reset();
  const priceInput = document.getElementById('addPrice');
  if (priceInput) priceInput.dataset.rawValue = '';
  document.getElementById('addStock').value = '0';
  document.getElementById('addUnit').value = 'pcs';
  openModal('addProductModal');
  window.setTimeout(() => document.getElementById('addName')?.focus(), 0);
}

function productFormData(prefix) {
  const name = document.getElementById(`${prefix}Name`).value.trim();
  const priceInput = document.getElementById(`${prefix}Price`);
  const priceRaw = String(priceInput.dataset.rawValue || priceInput.value).replace(/\D/g, '');
  const price = Number.parseInt(priceRaw, 10);
  const stock = Number(document.getElementById(`${prefix}Stock`).value);
  const categoryId = document.getElementById(`${prefix}Category`).value;
  const unit = document.getElementById(`${prefix}Unit`).value;

  if (!name) throw new Error('Nama produk wajib diisi.');
  if (!Number.isFinite(price) || price <= 0) throw new Error('Harga harus lebih dari 0.');
  if (!Number.isInteger(stock) || stock < 0) throw new Error('Stok harus berupa angka 0 atau lebih.');

  return { name, price, stock, category_id: categoryId, unit };
}

async function saveProduct(event) {
  event?.preventDefault();
  if (stockMutationInFlight) return;

  let data;
  try {
    data = productFormData('add');
  } catch (error) {
    showToast(error.message, 'warning');
    return;
  }

  const button = document.getElementById('saveProductBtn');
  stockMutationInFlight = true;
  button.disabled = true;
  button.textContent = 'Menyimpan…';

  try {
    const response = await fetch(kasirkuUrl('api/products.php'), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ action: 'add', ...data })
    });
    const payload = await readStockResponse(response);
    showToast(payload.message || 'Produk berhasil ditambahkan', 'success');
    closeModal('addProductModal');
    stockPage = 1;
    await loadStock();
  } catch (error) {
    console.error(error);
    showToast(error.message || 'Gagal menyimpan produk', 'error');
  } finally {
    stockMutationInFlight = false;
    button.disabled = false;
    button.textContent = '💾 Simpan Produk';
  }
}

function openEditModal(productId) {
  const id = Number.parseInt(productId, 10);
  const product = stockItemsById.get(id);
  if (!product) {
    showToast('Produk tidak ditemukan pada daftar saat ini.', 'error');
    loadStock();
    return;
  }

  document.getElementById('editId').value = String(id);
  document.getElementById('editName').value = product.name || '';
  const priceInput = document.getElementById('editPrice');
  const price = Math.round(Number.parseFloat(product.price) || 0);
  priceInput.value = price.toLocaleString('id-ID');
  priceInput.dataset.rawValue = String(price);
  document.getElementById('editStock').value = String(Math.max(0, Number.parseInt(product.stock, 10) || 0));
  document.getElementById('editCategory').value = product.category_id || '';
  document.getElementById('editUnit').value = product.unit || 'pcs';

  openModal('editProductModal');
  window.setTimeout(() => document.getElementById('editName')?.focus(), 0);
}

async function updateProduct(event) {
  event?.preventDefault();
  if (stockMutationInFlight) return;

  let data;
  try {
    data = productFormData('edit');
  } catch (error) {
    showToast(error.message, 'warning');
    return;
  }

  const id = Number.parseInt(document.getElementById('editId').value, 10);
  if (!Number.isInteger(id) || id <= 0) {
    showToast('ID produk tidak valid.', 'error');
    return;
  }

  const button = document.getElementById('updateProductBtn');
  stockMutationInFlight = true;
  button.disabled = true;
  button.textContent = 'Memperbarui…';

  try {
    const response = await fetch(kasirkuUrl('api/products.php'), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ action: 'edit', id, ...data })
    });
    const payload = await readStockResponse(response);
    showToast(payload.message || 'Produk berhasil diperbarui', 'success');
    closeModal('editProductModal');
    await loadStock();
  } catch (error) {
    console.error(error);
    showToast(error.message || 'Gagal memperbarui produk', 'error');
  } finally {
    stockMutationInFlight = false;
    button.disabled = false;
    button.textContent = '💾 Perbarui Produk';
  }
}

async function deleteProduct(productId) {
  if (stockMutationInFlight) return;
  const id = Number.parseInt(productId, 10);
  const product = stockItemsById.get(id);
  if (!product) {
    showToast('Produk tidak ditemukan pada daftar saat ini.', 'error');
    return;
  }
  if (!window.confirm(`Hapus produk "${product.name}"?\n\nProduk yang terhapus tidak dapat dikembalikan.`)) return;

  const button = document.querySelector(`[data-delete-id="${id}"]`);
  stockMutationInFlight = true;
  if (button) button.disabled = true;

  try {
    const response = await fetch(kasirkuUrl('api/products.php'), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ action: 'delete', id })
    });
    const payload = await readStockResponse(response);
    showToast(payload.message || 'Produk berhasil dihapus', 'success');
    await loadStock();
  } catch (error) {
    console.error(error);
    showToast(error.message || 'Gagal menghapus produk', 'error');
  } finally {
    stockMutationInFlight = false;
    if (button?.isConnected) button.disabled = false;
  }
}
