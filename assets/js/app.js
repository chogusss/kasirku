/**
 * app.js - POS (Point of Sale)
 * KasirKu UMKM
 */

let cart = [];
let allProducts = [];
let filteredProducts = [];
let selectedCategory = 0;
let currentReceipt = null;
let transactionInFlight = false;
let productsRequestController = null;
const cartBackgroundState = new Map();

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

async function readApiResponse(response) {
  let payload;
  try {
    payload = await response.json();
  } catch (error) {
    throw new Error('Respons server tidak valid. Silakan coba lagi.');
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
  const productsGrid = document.getElementById('productsGrid');
  if (!productsGrid) return;

  productsGrid.addEventListener('click', event => {
    const card = event.target.closest('[data-product-id]');
    if (!card || card.disabled) return;

    const productId = Number.parseInt(card.dataset.productId, 10);
    const product = allProducts.find(item => Number.parseInt(item.id, 10) === productId);
    if (product) addToCart(product);
  });

  loadCategories();
  loadProducts();
  renderCart();
  updateMobileCartFab();

  window.addEventListener('resize', handleCartViewportChange);
  document.addEventListener('keydown', event => {
    const cartPanel = document.getElementById('cartPanel');
    if (event.key === 'Escape' && cartPanel?.classList.contains('open')) {
      toggleCart(false);
      return;
    }

    if (event.key === 'Tab' && cartPanel?.classList.contains('open')) {
      trapCartFocus(event, cartPanel);
    }
  });
});

async function loadCategories() {
  const pills = document.getElementById('categoryPills');
  if (!pills) return;

  try {
    const response = await fetch(kasirkuUrl('api/products.php?action=categories'), {
      headers: { Accept: 'application/json' }
    });
    const payload = await readApiResponse(response);

    pills.querySelectorAll('[data-dynamic-category]').forEach(item => item.remove());
    (Array.isArray(payload.data) ? payload.data : []).forEach(category => {
      const id = Number.parseInt(category.id, 10);
      if (!Number.isInteger(id)) return;

      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'category-pill';
      button.dataset.id = String(id);
      button.dataset.dynamicCategory = 'true';
      button.setAttribute('aria-pressed', 'false');
      button.textContent = category.name || 'Tanpa nama';
      button.addEventListener('click', () => selectCategory(button, id));
      pills.appendChild(button);
    });
  } catch (error) {
    console.error('Gagal memuat kategori:', error);
    showToast(error.message || 'Gagal memuat kategori', 'error');
  }
}

function selectCategory(button, categoryId) {
  document.querySelectorAll('.category-pill').forEach(item => {
    item.classList.remove('active');
    item.setAttribute('aria-pressed', 'false');
  });
  button?.classList.add('active');
  button?.setAttribute('aria-pressed', 'true');
  selectedCategory = Number.parseInt(categoryId, 10) || 0;
  filterProducts();
}

async function loadProducts() {
  const grid = document.getElementById('productsGrid');
  if (!grid) return;

  productsRequestController?.abort();
  productsRequestController = new AbortController();
  grid.setAttribute('aria-busy', 'true');
  grid.innerHTML = '<div class="loading"><div class="spinner" aria-label="Memuat produk"></div></div>';

  try {
    const firstResponse = await fetch(kasirkuUrl('api/products.php?page=1&per_page=100'), {
      headers: { Accept: 'application/json' },
      signal: productsRequestController.signal
    });
    const firstPayload = await readApiResponse(firstResponse);
    const products = Array.isArray(firstPayload.data) ? [...firstPayload.data] : [];
    const totalPages = Math.max(1, Number.parseInt(firstPayload.pages, 10) || 1);

    for (let page = 2; page <= totalPages; page += 1) {
      const response = await fetch(kasirkuUrl(`api/products.php?page=${page}&per_page=100`), {
        headers: { Accept: 'application/json' },
        signal: productsRequestController.signal
      });
      const payload = await readApiResponse(response);
      if (Array.isArray(payload.data)) products.push(...payload.data);
    }
    allProducts = products;

    const cartAdjusted = reconcileCartWithProducts();
    filterProducts();
    if (cartAdjusted) {
      renderCart();
      showToast('Keranjang disesuaikan dengan stok terbaru.', 'warning');
    }
  } catch (error) {
    if (error.name === 'AbortError') return;
    grid.innerHTML = `
      <div class="no-data" style="grid-column:1/-1">
        <div class="no-data-text">Gagal memuat produk</div>
        <div class="no-data-sub">${escapeHtml(error.message)}</div>
        <button type="button" class="btn btn-secondary btn-sm mt-1" onclick="loadProducts()">Coba lagi</button>
      </div>`;
    console.error(error);
  } finally {
    grid.setAttribute('aria-busy', 'false');
  }
}

function reconcileCartWithProducts() {
  if (cart.length === 0) return false;

  const productsById = new Map(allProducts.map(product => [Number.parseInt(product.id, 10), product]));
  let changed = false;
  const nextCart = [];

  cart.forEach(item => {
    const product = productsById.get(item.id);
    const availableStock = Number.parseInt(product?.stock, 10) || 0;
    if (!product || availableStock <= 0) {
      changed = true;
      return;
    }

    const nextQuantity = Math.min(item.qty, availableStock);
    const nextPrice = Number.parseFloat(product.price) || 0;
    if (
      nextQuantity !== item.qty ||
      nextPrice !== item.price ||
      product.name !== item.name ||
      product.unit !== item.unit
    ) changed = true;

    nextCart.push({
      id: item.id,
      name: product.name,
      price: nextPrice,
      qty: nextQuantity,
      stock: availableStock,
      unit: product.unit || 'pcs'
    });
  });

  cart = nextCart;
  return changed;
}

function filterProducts() {
  const searchInput = document.getElementById('productSearch');
  const search = (searchInput?.value || '').toLocaleLowerCase('id-ID').trim();

  filteredProducts = allProducts.filter(product => {
    const name = String(product.name || '').toLocaleLowerCase('id-ID');
    const matchesSearch = !search || name.includes(search);
    const matchesCategory = selectedCategory === 0 || Number.parseInt(product.category_id, 10) === selectedCategory;
    return matchesSearch && matchesCategory;
  });

  renderProducts();
}

const categoryEmojis = {
  makanan: '🍽️',
  minuman: '🥤',
  snack: '🍿',
  rokok: '🚬',
  sembako: '🛒',
  lainnya: '📦'
};

function getProductEmoji(product) {
  const categoryName = String(product.category_name || '').toLocaleLowerCase('id-ID');
  const match = Object.entries(categoryEmojis).find(([key]) => categoryName.includes(key));
  return match ? match[1] : '🛍️';
}

function renderProducts() {
  const grid = document.getElementById('productsGrid');
  if (!grid) return;

  if (filteredProducts.length === 0) {
    grid.innerHTML = `
      <div class="no-data" style="grid-column:1/-1">
        <span class="no-data-icon" aria-hidden="true">🔎</span>
        <div class="no-data-text">Produk tidak ditemukan</div>
        <div class="no-data-sub">Coba kata kunci atau kategori lain.</div>
      </div>`;
    return;
  }

  grid.innerHTML = filteredProducts.map(product => {
    const id = Number.parseInt(product.id, 10);
    const stock = Math.max(0, Number.parseInt(product.stock, 10) || 0);
    const price = Number.parseFloat(product.price) || 0;
    const name = String(product.name || 'Produk tanpa nama');
    const unit = String(product.unit || 'pcs');
    const outOfStock = stock <= 0;
    const stockClass = stock <= 5 ? 'stock-low' : stock <= 10 ? 'stock-medium' : 'stock-ok';
    const actionLabel = outOfStock ? `${name}, stok habis` : `Tambahkan ${name} ke keranjang`;

    return `
      <button type="button"
              class="product-card ${outOfStock ? 'out-of-stock' : ''}"
              data-product-id="${id}"
              title="${escapeHtml(name)} - ${escapeHtml(formatRupiah(price))}"
              aria-label="${escapeHtml(actionLabel)}"
              ${outOfStock ? 'disabled aria-disabled="true"' : ''}>
        <span class="product-emoji" aria-hidden="true">${getProductEmoji(product)}</span>
        <span class="product-name">${escapeHtml(name)}</span>
        <span class="product-price">${escapeHtml(formatRupiah(price))}</span>
        <span class="product-stock ${stockClass}">Stok: ${stock} ${escapeHtml(unit)}</span>
        ${outOfStock ? '<span style="font-size:10px;color:var(--danger);font-weight:700;margin-top:3px">HABIS</span>' : ''}
      </button>`;
  }).join('');
}

function addToCart(product) {
  const productId = Number.parseInt(product.id, 10);
  const maxStock = Math.max(0, Number.parseInt(product.stock, 10) || 0);
  if (!Number.isInteger(productId) || maxStock <= 0) return;

  const existing = cart.find(item => item.id === productId);
  if (existing) {
    if (existing.qty >= maxStock) {
      showToast(`Stok ${product.name} hanya ${maxStock} ${product.unit || 'pcs'}`, 'warning');
      return;
    }
    existing.qty += 1;
  } else {
    cart.push({
      id: productId,
      name: String(product.name || 'Produk'),
      price: Number.parseFloat(product.price) || 0,
      qty: 1,
      stock: maxStock,
      unit: String(product.unit || 'pcs')
    });
  }

  renderCart();
  updateQuickPayButtons();
}

function updateQty(productId, delta) {
  const id = Number.parseInt(productId, 10);
  const item = cart.find(entry => entry.id === id);
  if (!item) return;

  item.qty += Number.parseInt(delta, 10) || 0;
  if (item.qty <= 0) {
    cart = cart.filter(entry => entry.id !== id);
  } else if (item.qty > item.stock) {
    item.qty = item.stock;
    showToast(`Maksimal stok ${item.stock} ${item.unit}`, 'warning');
  }

  renderCart();
  updateQuickPayButtons();
}

function removeFromCart(productId) {
  const id = Number.parseInt(productId, 10);
  cart = cart.filter(item => item.id !== id);
  renderCart();
  updateQuickPayButtons();
}

function clearCart() {
  if (cart.length === 0) {
    showToast('Keranjang sudah kosong', 'info');
    return;
  }

  cart = [];
  const paymentInput = document.getElementById('paymentInput');
  const notesInput = document.getElementById('txnNotes');
  if (paymentInput) {
    paymentInput.value = '';
    paymentInput.dataset.rawValue = '';
  }
  if (notesInput) notesInput.value = '';
  renderCart();
  updateQuickPayButtons();
  showToast('Keranjang dikosongkan', 'success');
}

function renderCart() {
  const cartItems = document.getElementById('cartItems');
  const cartCount = document.getElementById('cartCount');
  const subtotal = document.getElementById('cartSubtotal');
  const totalElement = document.getElementById('cartTotal');
  const processButton = document.getElementById('processTxnBtn');
  if (!cartItems || !cartCount || !subtotal || !totalElement || !processButton) return;

  const total = cart.reduce((sum, item) => sum + item.price * item.qty, 0);
  const totalQuantity = cart.reduce((sum, item) => sum + item.qty, 0);

  cartCount.textContent = `${totalQuantity} item`;
  cartCount.style.display = totalQuantity > 0 ? '' : 'none';
  subtotal.textContent = formatRupiah(total);
  totalElement.textContent = formatRupiah(total);

  const payment = parseRawValue(document.getElementById('paymentInput'));
  processButton.disabled = transactionInFlight || cart.length === 0 || payment < total;

  if (cart.length === 0) {
    cartItems.innerHTML = `
      <div class="cart-empty">
        <span class="empty-icon" aria-hidden="true">🛒</span>
        <div class="cart-empty-title">Keranjang Kosong</div>
        <div class="cart-empty-sub">Pilih produk untuk menambahkannya.</div>
      </div>`;
    updateChange();
    updateMobileCartFab();
    return;
  }

  cartItems.innerHTML = cart.map(item => `
    <div class="cart-item" id="cartItem_${item.id}">
      <div class="cart-item-info">
        <div class="cart-item-name">${escapeHtml(item.name)}</div>
        <div class="cart-item-price">${escapeHtml(formatRupiah(item.price))} / ${escapeHtml(item.unit)}</div>
      </div>
      <div class="cart-item-controls" aria-label="Jumlah ${escapeHtml(item.name)}">
        <button type="button" class="qty-btn" onclick="updateQty(${item.id}, -1)" aria-label="Kurangi ${escapeHtml(item.name)}">−</button>
        <span class="qty-display" aria-live="polite">${item.qty}</span>
        <button type="button" class="qty-btn" onclick="updateQty(${item.id}, 1)" aria-label="Tambah ${escapeHtml(item.name)}">+</button>
      </div>
      <div class="cart-item-subtotal">${escapeHtml(formatRupiah(item.price * item.qty))}</div>
      <button type="button" class="btn-remove" onclick="removeFromCart(${item.id})" aria-label="Hapus ${escapeHtml(item.name)}">×</button>
    </div>
  `).join('');

  updateChange();
  updateMobileCartFab();
}

function onPaymentInput(input) {
  formatNumberInput(input);
  updateChange();
}

function parseRawValue(input) {
  if (!input) return 0;
  const raw = String(input.dataset.rawValue || input.value || '').replace(/\D/g, '');
  return raw ? Number.parseInt(raw, 10) : 0;
}

function updateChange() {
  const paymentInput = document.getElementById('paymentInput');
  const changeDisplay = document.getElementById('changeDisplay');
  const changeAmount = document.getElementById('changeAmount');
  const processButton = document.getElementById('processTxnBtn');
  if (!paymentInput || !changeDisplay || !changeAmount || !processButton) return;

  const total = cart.reduce((sum, item) => sum + item.price * item.qty, 0);
  const payment = parseRawValue(paymentInput);
  const isSufficient = total > 0 && payment >= total;

  changeDisplay.style.display = isSufficient ? 'flex' : 'none';
  if (isSufficient) changeAmount.textContent = formatRupiah(payment - total);
  processButton.disabled = transactionInFlight || !isSufficient;
}

function updateQuickPayButtons() {
  const container = document.getElementById('quickPayBtns');
  if (!container) return;

  const total = cart.reduce((sum, item) => sum + item.price * item.qty, 0);
  if (total <= 0) {
    container.innerHTML = '';
    return;
  }

  const candidates = [total, 1000, 2000, 5000, 10000, 20000, 50000, 100000]
    .map((value, index) => index === 0 ? value : Math.ceil(total / value) * value);
  const amounts = [...new Set(candidates)].filter(value => value >= total).slice(0, 4);

  container.innerHTML = amounts.map(amount => `
    <button type="button" class="quick-pay-btn" onclick="setPayment(${amount})" aria-label="Bayar ${escapeHtml(formatRupiah(amount))}">
      ${escapeHtml(formatRupiah(amount))}
    </button>
  `).join('');
}

function setPayment(amount) {
  const input = document.getElementById('paymentInput');
  if (!input) return;
  const safeAmount = Math.max(0, Number.parseInt(amount, 10) || 0);
  input.dataset.rawValue = String(safeAmount);
  input.value = safeAmount.toLocaleString('id-ID');
  updateChange();
}

async function processTransaction() {
  if (transactionInFlight) return;
  if (cart.length === 0) {
    showToast('Keranjang masih kosong.', 'warning');
    return;
  }

  const paymentInput = document.getElementById('paymentInput');
  const notesInput = document.getElementById('txnNotes');
  const button = document.getElementById('processTxnBtn');
  const total = cart.reduce((sum, item) => sum + item.price * item.qty, 0);
  const payment = parseRawValue(paymentInput);
  const notes = (notesInput?.value || '').trim();

  if (payment < total) {
    showToast('Nominal bayar kurang dari total.', 'error');
    paymentInput?.focus();
    return;
  }

  const submittedItems = cart.map(item => ({ ...item }));
  transactionInFlight = true;
  button.disabled = true;
  button.textContent = 'Memproses…';

  try {
    const response = await fetch(kasirkuUrl('api/transactions.php'), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({
        items: submittedItems.map(item => ({
          id: item.id,
          name: item.name,
          price: item.price,
          quantity: item.qty
        })),
        payment_amount: payment,
        notes
      })
    });
    const payload = await readApiResponse(response);

    const receiptItems = Array.isArray(payload.items) && payload.items.length > 0
      ? payload.items.map(item => ({
          id: Number.parseInt(item.id, 10) || 0,
          name: String(item.name || 'Produk'),
          price: Number.parseFloat(item.price) || 0,
          qty: Number.parseInt(item.qty ?? item.quantity, 10) || 0,
          unit: String(item.unit || 'pcs')
        }))
      : submittedItems;

    currentReceipt = {
      invoice: payload.invoice_no,
      items: receiptItems,
      total: Number.parseFloat(payload.total) || total,
      payment: Number.parseFloat(payload.payment) || payment,
      change: Number.parseFloat(payload.change) || (payment - total),
      date: new Date().toLocaleString('id-ID'),
      notes
    };

    cart = [];
    paymentInput.value = '';
    paymentInput.dataset.rawValue = '';
    notesInput.value = '';
    toggleCart(false);
    showReceipt(currentReceipt);
    showToast('Transaksi berhasil disimpan.', 'success');
    await loadProducts();
    updateQuickPayButtons();
  } catch (error) {
    console.error(error);
    showToast(error.message || 'Transaksi gagal. Silakan coba lagi.', 'error');
    if (/stok/i.test(error.message || '')) loadProducts();
  } finally {
    transactionInFlight = false;
    button.textContent = '✓ Proses Transaksi';
    renderCart();
  }
}

function showReceipt(data) {
  const content = document.getElementById('receiptContent');
  if (!content) return;
  content.innerHTML = buildReceiptHTML(data);
  openModal('receiptModal');
}

function buildReceiptHTML(data) {
  const store = window.KASIRKU_CONFIG || {};
  const itemRows = data.items.map(item => `
    <div class="receipt-item">
      <span class="receipt-item-name">${escapeHtml(item.name)}</span>
      <div class="receipt-item-detail">
        <span>${item.qty} × ${escapeHtml(formatRupiah(item.price))}</span>
        <span>${escapeHtml(formatRupiah(item.price * item.qty))}</span>
      </div>
    </div>
  `).join('');

  return `
    <div class="receipt" id="receiptPreview">
      <div class="receipt-store-name">${escapeHtml(store.storeName || 'Toko Serba Ada')}</div>
      <div class="receipt-store-sub">
        ${escapeHtml(store.address || 'Jl. Raya No. 1, Jakarta')}<br>
        Telp: ${escapeHtml(store.phone || '0812-3456-7890')}
      </div>
      <hr class="receipt-divider">
      <div class="receipt-info">
        <div class="row"><span>No. Invoice</span><span>${escapeHtml(data.invoice)}</span></div>
        <div class="row"><span>Tanggal</span><span>${escapeHtml(data.date)}</span></div>
        ${data.notes ? `<div class="row"><span>Catatan</span><span>${escapeHtml(data.notes)}</span></div>` : ''}
      </div>
      <hr class="receipt-divider">
      <div class="receipt-title">— DETAIL PESANAN —</div>
      ${itemRows}
      <hr class="receipt-divider">
      <div class="receipt-totals">
        <div class="row"><span>Subtotal</span><span>${escapeHtml(formatRupiah(data.total))}</span></div>
        <div class="grand"><span>TOTAL</span><span>${escapeHtml(formatRupiah(data.total))}</span></div>
        <div class="row" style="margin-top:6px"><span>Bayar</span><span>${escapeHtml(formatRupiah(data.payment))}</span></div>
        <div class="row"><span>Kembali</span><span>${escapeHtml(formatRupiah(data.change))}</span></div>
      </div>
      <hr class="receipt-divider">
      <div class="receipt-footer">
        Terima kasih telah berbelanja!<br>
        Barang yang sudah dibeli<br>
        tidak dapat dikembalikan.
      </div>
    </div>`;
}

function printReceipt() {
  const receipt = document.getElementById('receiptPreview');
  if (!receipt) {
    showToast('Struk belum tersedia.', 'warning');
    return;
  }

  let printArea = document.getElementById('printArea');
  if (!printArea) {
    printArea = document.createElement('div');
    printArea.id = 'printArea';
    document.body.appendChild(printArea);
  }
  printArea.innerHTML = receipt.outerHTML;
  window.print();
}

function isMobileCartMode() {
  return window.matchMedia('(max-width: 1100px)').matches;
}

function setCartBackgroundInert(active) {
  const targets = [
    document.getElementById('sidebar'),
    document.querySelector('.topbar'),
    document.querySelector('.pos-products'),
    document.getElementById('cartFab')
  ].filter(target => target instanceof HTMLElement);

  if (active) {
    targets.forEach(target => {
      if (!cartBackgroundState.has(target)) cartBackgroundState.set(target, target.inert);
      target.inert = true;
    });
    return;
  }

  cartBackgroundState.forEach((wasInert, target) => {
    if (document.contains(target)) target.inert = wasInert;
  });
  cartBackgroundState.clear();
}

function cartFocusableElements(cartPanel) {
  return Array.from(cartPanel.querySelectorAll(
    'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
  )).filter(element => {
    const style = window.getComputedStyle(element);
    return !element.hidden && element.getAttribute('aria-hidden') !== 'true'
      && style.display !== 'none' && style.visibility !== 'hidden';
  });
}

function trapCartFocus(event, cartPanel) {
  const focusable = cartFocusableElements(cartPanel);
  if (focusable.length === 0) {
    event.preventDefault();
    cartPanel.focus();
    return;
  }

  const first = focusable[0];
  const last = focusable[focusable.length - 1];
  if (!cartPanel.contains(document.activeElement)) {
    event.preventDefault();
    first.focus();
  } else if (event.shiftKey && document.activeElement === first) {
    event.preventDefault();
    last.focus();
  } else if (!event.shiftKey && document.activeElement === last) {
    event.preventDefault();
    first.focus();
  }
}

function syncCartScrollLock() {
  const locked = document.querySelector('.modal-overlay.active, .sidebar.open, #cartPanel.open');
  document.body.style.overflow = locked ? 'hidden' : '';
}

function toggleCart(show) {
  const cartPanel = document.getElementById('cartPanel');
  const overlay = document.getElementById('cartOverlay');
  const fab = document.getElementById('cartFab');
  if (!cartPanel || !overlay) return;

  const shouldOpen = Boolean(show) && isMobileCartMode();
  cartPanel.classList.toggle('open', shouldOpen);
  overlay.classList.toggle('active', shouldOpen);
  cartPanel.setAttribute('aria-hidden', String(isMobileCartMode() && !shouldOpen));
  cartPanel.inert = isMobileCartMode() && !shouldOpen;
  fab?.setAttribute('aria-expanded', String(shouldOpen));

  if (shouldOpen) {
    cartPanel.setAttribute('role', 'dialog');
    cartPanel.setAttribute('aria-modal', 'true');
    cartPanel.setAttribute('aria-label', 'Keranjang dan pembayaran');
    if (!cartPanel.hasAttribute('tabindex')) cartPanel.setAttribute('tabindex', '-1');
    setCartBackgroundInert(true);
    syncCartScrollLock();
    cartPanel.querySelector('.btn-close-cart-mobile')?.focus();
  } else {
    cartPanel.removeAttribute('role');
    cartPanel.removeAttribute('aria-modal');
    cartPanel.removeAttribute('aria-label');
    setCartBackgroundInert(false);
    syncCartScrollLock();
    if (show === false && isMobileCartMode()) fab?.focus();
  }
}

function updateMobileCartFab() {
  const fab = document.getElementById('cartFab');
  const fabBadge = document.getElementById('cartFabBadge');
  const fabTotal = document.getElementById('cartFabTotal');
  const cartPanel = document.getElementById('cartPanel');
  if (!fab || !fabBadge || !fabTotal || !cartPanel) return;

  const total = cart.reduce((sum, item) => sum + item.price * item.qty, 0);
  const totalQuantity = cart.reduce((sum, item) => sum + item.qty, 0);
  const mobile = isMobileCartMode();

  fab.style.display = mobile ? 'flex' : 'none';
  fabBadge.textContent = String(totalQuantity);
  fabTotal.textContent = formatRupiah(total);
  fab.setAttribute('aria-label', `Buka keranjang, ${totalQuantity} item, total ${formatRupiah(total)}`);

  document.querySelectorAll('.btn-close-cart-mobile').forEach(button => {
    button.style.display = mobile ? 'inline-flex' : 'none';
  });

  if (!mobile) {
    cartPanel.classList.remove('open');
    cartPanel.removeAttribute('aria-hidden');
    cartPanel.removeAttribute('role');
    cartPanel.removeAttribute('aria-modal');
    cartPanel.removeAttribute('aria-label');
    cartPanel.inert = false;
    document.getElementById('cartOverlay')?.classList.remove('active');
    setCartBackgroundInert(false);
    syncCartScrollLock();
  } else if (!cartPanel.classList.contains('open')) {
    cartPanel.setAttribute('aria-hidden', 'true');
    cartPanel.inert = true;
    setCartBackgroundInert(false);
    syncCartScrollLock();
  }
}

function handleCartViewportChange() {
  updateMobileCartFab();
}
