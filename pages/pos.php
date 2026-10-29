<?php
/**
 * POS - Point of Sale
 */
require_once __DIR__ . '/../includes/auth_check.php';

$pageTitle    = 'Point of Sale — KasirKu';
$pageSubtitle = 'Kasir & Transaksi';

require_once __DIR__ . '/../includes/header.php';
?>

<style>
  /* POS-specific layout — tighter, task-focused */
  .pos-layout {
    display: grid;
    grid-template-columns: 1fr 380px;
    gap: 16px;
    width: 100%;
    min-width: 0;
    min-height: 0;
    height: 100%;
  }

  @media (max-width: 1100px) {
    .pos-layout {
      grid-template-columns: minmax(0, 1fr);
      height: 100%;
    }
  }

  /* Products panel */
  .pos-products {
    display: flex;
    flex-direction: column;
    min-height: 0;
    overflow: hidden;
  }

  .pos-toolbar {
    display: flex;
    gap: 9px;
    margin-bottom: 11px;
    flex-wrap: wrap;
    align-items: center;
  }

  .products-scrollarea {
    flex: 1;
    overflow-y: auto;
    min-height: 0;
  }

  /* Cart header action row */
  .cart-header-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    width: 100%;
  }

  /* Inline clear button in cart header */
  .btn-clear-cart {
    background: none;
    border: none;
    color: var(--ink-3);
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    padding: 6px 12px;
    border-radius: 5px;
    transition: color 160ms ease, background 160ms ease;
    font-family: inherit;
    position: relative;
    z-index: 2;
    min-height: 32px;
  }
  .btn-clear-cart:hover { color: var(--danger); background: var(--danger-surface); }

  /* Payment divider */
  .payment-divider {
    height: 1px;
    background: var(--border);
    margin: 10px 0;
  }

  /* Notes input smaller */
  .notes-input {
    font-size: 12px;
    padding: 8px 11px;
  }

  /* Process button — prominent, green */
  .btn-process {
    width: 100%;
    padding: 13px;
    background: var(--success);
    color: oklch(0.10 0 0);
    border: none;
    border-radius: 8px;
    font-size: 15px;
    font-weight: 800;
    font-family: inherit;
    cursor: pointer;
    display: flex; align-items: center; justify-content: center; gap: 8px;
    transition: background 160ms ease, box-shadow 160ms ease;
    margin-top: 10px;
    letter-spacing: -0.01em;
  }
  .btn-process:hover:not(:disabled) {
    background: oklch(0.56 0.14 145);
    color: var(--ink);
    box-shadow: 0 4px 18px oklch(0.64 0.15 145 / 0.30);
  }
  .btn-process:disabled {
    opacity: 0.35;
    cursor: not-allowed;
  }

  /* Badge on cart header */
  .cart-badge {
    background: var(--primary);
    color: oklch(0.10 0 0);
    font-size: 10px;
    font-weight: 800;
    padding: 2px 8px;
    border-radius: 10px;
    letter-spacing: 0.02em;
  }

  /* Close cart button styling for mobile */
  .btn-close-cart-mobile {
    background: var(--surface-3) !important;
    border: 1px solid var(--border) !important;
    padding: 6px 12px !important;
    border-radius: 6px !important;
    min-height: 36px !important;
    color: var(--ink-2) !important;
  }
  .btn-close-cart-mobile:hover {
    background: var(--danger-surface) !important;
    color: var(--danger) !important;
    border-color: var(--danger) !important;
  }

  @media only screen and (max-width: 1100px) {
    #cartFab { z-index: var(--z-sticky); }
    #cartOverlay { z-index: var(--z-modal-bg); }
    #cartPanel { z-index: calc(var(--z-modal-bg) + 1); }
  }
</style>

<div class="pos-layout">

  <!-- ===== LEFT: Products ===== -->
  <div class="pos-products">

    <!-- Toolbar -->
    <div class="pos-toolbar">
      <div class="search-wrapper" style="flex:1;min-width:200px">
        <span class="search-icon">🔍</span>
        <input
          type="text"
          id="productSearch"
          class="form-control"
          placeholder="Cari produk…"
          oninput="filterProducts()"
          autocomplete="off"
          spellcheck="false"
          aria-label="Cari produk"
        >
      </div>
    </div>

    <!-- Category pills -->
    <div class="category-pills" id="categoryPills" role="group" aria-label="Filter kategori">
      <button type="button" class="category-pill active" data-id="0" aria-pressed="true" onclick="selectCategory(this, 0)">Semua</button>
    </div>

    <!-- Products grid -->
    <div class="products-scrollarea">
      <div class="products-grid" id="productsGrid" aria-label="Daftar produk" aria-live="polite">
        <div class="loading"><div class="spinner"></div></div>
      </div>
    </div>

  </div>

  <!-- ===== RIGHT: Cart ===== -->
  <div class="pos-cart" id="cartPanel">

    <!-- Cart header -->
    <div class="cart-header">
      <div class="cart-header-row">
        <div style="display:flex;align-items:center;gap:8px;">
          <button type="button" class="btn-close-cart-mobile" onclick="toggleCart(false)" aria-label="Tutup keranjang" style="display:none; background:none; border:none; font-size:16px; cursor:pointer; color:var(--ink-2); margin-right:4px;">✕</button>
          <span style="font-size:14px;font-weight:700">Keranjang</span>
          <span id="cartCount" class="cart-badge" style="display:none">0 item</span>
        </div>
        <button type="button" class="btn-clear-cart" onclick="clearCart()">Kosongkan</button>
      </div>
    </div>

    <!-- Cart items -->
    <div class="cart-items" id="cartItems" aria-label="Item keranjang" aria-live="polite">
      <div class="cart-empty">
        <span class="empty-icon">🛒</span>
        <div class="cart-empty-title">Keranjang Kosong</div>
        <div class="cart-empty-sub">Klik produk untuk menambahkan</div>
      </div>
    </div>

    <!-- Cart footer -->
    <div class="cart-footer">

      <!-- Summary -->
      <div class="cart-summary">
        <div class="summary-row">
          <span>Subtotal</span>
          <span id="cartSubtotal" aria-live="polite">Rp 0</span>
        </div>
        <div class="summary-row total">
          <span>Total</span>
          <span class="amount" id="cartTotal" aria-live="polite">Rp 0</span>
        </div>
      </div>

      <div class="payment-divider"></div>

      <!-- Payment amount -->
      <div class="payment-section">
        <label class="form-label" for="paymentInput">Nominal Bayar</label>
        <div class="payment-input-wrapper">
          <span class="currency-prefix">Rp</span>
          <input
            type="text"
            id="paymentInput"
            class="form-control"
            placeholder="0"
            oninput="onPaymentInput(this)"
            style="font-size:16px;font-weight:700;font-variant-numeric:tabular-nums"
            aria-label="Jumlah pembayaran"
            inputmode="numeric"
          >
        </div>

        <!-- Quick amount buttons -->
        <div style="display:flex;gap:5px;margin-top:8px;flex-wrap:wrap" id="quickPayBtns" aria-label="Pilihan cepat nominal bayar"></div>
      </div>

      <!-- Kembalian -->
      <div class="change-display" id="changeDisplay" style="display:none" aria-live="polite">
        <span class="change-label">Kembalian</span>
        <span class="change-amount" id="changeAmount">Rp 0</span>
      </div>

      <!-- Notes -->
      <div style="margin-top:9px">
        <input
          type="text"
          id="txnNotes"
          class="form-control notes-input"
          placeholder="Catatan transaksi (opsional)"
          aria-label="Catatan transaksi"
          maxlength="200"
        >
      </div>

      <!-- Process -->
      <button
        type="button"
        id="processTxnBtn"
        class="btn-process"
        onclick="processTransaction()"
        disabled
        aria-label="Proses transaksi"
      >
        ✓ Proses Transaksi
      </button>

      <!-- Mobile Close Cart Button -->
      <button
        type="button"
        class="btn btn-secondary btn-block mt-1 btn-close-cart-mobile"
        onclick="toggleCart(false)"
        style="display:none; margin-top: 8px; min-height: 40px;"
      >
        ✕ Tutup Keranjang
      </button>

    </div>
  </div>
</div>

<!-- Mobile Cart FAB Toggler -->
<button type="button" class="cart-fab" id="cartFab" onclick="toggleCart(true)" aria-controls="cartPanel" aria-expanded="false">
  <span>🛒 Keranjang</span>
  <span class="cart-fab-badge" id="cartFabBadge">0</span>
  <span class="cart-fab-total" id="cartFabTotal">Rp 0</span>
</button>

<!-- Mobile Cart Overlay -->
<div class="sidebar-overlay" id="cartOverlay" onclick="toggleCart(false)" aria-hidden="true"></div>

<!-- ===== RECEIPT MODAL ===== -->
<div class="modal-overlay" id="receiptModal" role="dialog" aria-modal="true" aria-labelledby="receiptModalTitle">
  <div class="modal modal-sm">
    <div class="modal-header">
      <div class="modal-title" id="receiptModalTitle">Struk Transaksi</div>
      <button type="button" class="modal-close" onclick="closeModal('receiptModal')" aria-label="Tutup struk">✕</button>
    </div>
    <div class="modal-body">
      <div id="receiptContent"></div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-secondary" onclick="closeModal('receiptModal')">Tutup</button>
      <button type="button" class="btn btn-primary" onclick="printReceipt()">🖨️ Cetak Struk</button>
    </div>
  </div>
</div>

<link rel="stylesheet" href="<?= appEscape(appAssetUrl('assets/css/print.css')) ?>" media="print">
<script src="<?= appEscape(appAssetUrl('assets/js/app.js')) ?>"></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
