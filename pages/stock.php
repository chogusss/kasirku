<?php
/**
 * Stock Management Page - Owner Only
 */
require_once __DIR__ . '/../includes/auth_check.php';
requireOwner();

$pageTitle    = 'Stok & Produk — KasirKu';
$pageSubtitle = 'Manajemen Produk & Inventaris';

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Toolbar -->
<div class="page-toolbar">
  <div class="toolbar-fields">
    <div class="search-wrapper toolbar-search">
      <span class="search-icon">🔍</span>
      <input type="search" id="stockSearch" class="form-control" placeholder="Cari produk..." oninput="debounceLoad()" aria-label="Cari produk" autocomplete="off">
    </div>
    <select id="stockCategoryFilter" class="form-control toolbar-select" onchange="applyStockFilters()" aria-label="Filter kategori produk">
      <option value="0">Semua Kategori</option>
    </select>
  </div>
  <button type="button" class="btn btn-primary toolbar-primary-action" onclick="openAddModal()">
    ➕ Tambah Produk
  </button>
</div>

<!-- Stock Table -->
<div class="card">
  <div class="card-header">
    <div class="card-title">📦 Daftar Produk</div>
    <span class="badge badge-primary" id="stockTotal">— produk</span>
  </div>
  <div class="table-wrapper">
    <table class="table responsive-table stock-table" aria-label="Daftar stok dan produk">
      <thead>
        <tr>
          <th>#</th>
          <th>Nama Produk</th>
          <th>Kategori</th>
          <th>Harga</th>
          <th>Stok</th>
          <th>Satuan</th>
          <th>Diperbarui</th>
          <th>Aksi</th>
        </tr>
      </thead>
      <tbody id="stockTableBody">
        <tr><td colspan="8" style="text-align:center;padding:40px;color:var(--text-muted)">Memuat data...</td></tr>
      </tbody>
    </table>
  </div>
  <!-- Pagination -->
  <div class="pagination" id="stockPagination" style="padding:16px" role="navigation" aria-label="Navigasi halaman produk"></div>
</div>

<!-- ===== ADD PRODUCT MODAL ===== -->
<div class="modal-overlay" id="addProductModal" role="dialog" aria-modal="true" aria-labelledby="addProductTitle">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title" id="addProductTitle">➕ Tambah Produk</div>
      <button type="button" class="modal-close" onclick="closeModal('addProductModal')" aria-label="Tutup form tambah produk">✕</button>
    </div>
    <form id="addProductForm" onsubmit="saveProduct(event)">
    <div class="modal-body">
      <div class="form-row">
        <div class="form-group">
          <label class="form-label" for="addName">Nama Produk *</label>
          <input type="text" id="addName" class="form-control" placeholder="Cth: Nasi Goreng" maxlength="120" required autocomplete="off">
        </div>
        <div class="form-group">
          <label class="form-label" for="addCategory">Kategori</label>
          <select id="addCategory" class="form-control">
            <option value="">Pilih Kategori</option>
          </select>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label" for="addPrice">Harga Jual (Rp) *</label>
          <input type="text" id="addPrice" class="form-control" placeholder="Cth: 15.000" oninput="formatNumberInput(this)" inputmode="numeric" required autocomplete="off">
        </div>
        <div class="form-group">
          <label class="form-label" for="addStock">Stok Awal</label>
          <input type="number" id="addStock" class="form-control" placeholder="0" min="0" step="1" value="0" required>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label" for="addUnit">Satuan</label>
        <select id="addUnit" class="form-control">
          <option value="pcs">pcs</option>
          <option value="porsi">porsi</option>
          <option value="gelas">gelas</option>
          <option value="botol">botol</option>
          <option value="bungkus">bungkus</option>
          <option value="kg">kg</option>
          <option value="liter">liter</option>
          <option value="lusin">lusin</option>
          <option value="sachet">sachet</option>
          <option value="butir">butir</option>
        </select>
      </div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-secondary" onclick="closeModal('addProductModal')">Batal</button>
      <button type="submit" class="btn btn-primary" id="saveProductBtn">💾 Simpan Produk</button>
    </div>
    </form>
  </div>
</div>

<!-- ===== EDIT PRODUCT MODAL ===== -->
<div class="modal-overlay" id="editProductModal" role="dialog" aria-modal="true" aria-labelledby="editProductTitle">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title" id="editProductTitle">✏️ Edit Produk</div>
      <button type="button" class="modal-close" onclick="closeModal('editProductModal')" aria-label="Tutup form edit produk">✕</button>
    </div>
    <form id="editProductForm" onsubmit="updateProduct(event)">
    <div class="modal-body">
      <input type="hidden" id="editId">
      <div class="form-row">
        <div class="form-group">
          <label class="form-label" for="editName">Nama Produk *</label>
          <input type="text" id="editName" class="form-control" maxlength="120" required autocomplete="off">
        </div>
        <div class="form-group">
          <label class="form-label" for="editCategory">Kategori</label>
          <select id="editCategory" class="form-control">
            <option value="">Pilih Kategori</option>
          </select>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label" for="editPrice">Harga Jual (Rp) *</label>
          <input type="text" id="editPrice" class="form-control" oninput="formatNumberInput(this)" inputmode="numeric" required autocomplete="off">
        </div>
        <div class="form-group">
          <label class="form-label" for="editStock">Stok</label>
          <input type="number" id="editStock" class="form-control" min="0" step="1" required>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label" for="editUnit">Satuan</label>
        <select id="editUnit" class="form-control">
          <option value="pcs">pcs</option>
          <option value="porsi">porsi</option>
          <option value="gelas">gelas</option>
          <option value="botol">botol</option>
          <option value="bungkus">bungkus</option>
          <option value="kg">kg</option>
          <option value="liter">liter</option>
          <option value="lusin">lusin</option>
          <option value="sachet">sachet</option>
          <option value="butir">butir</option>
        </select>
      </div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-secondary" onclick="closeModal('editProductModal')">Batal</button>
      <button type="submit" class="btn btn-primary" id="updateProductBtn">💾 Perbarui Produk</button>
    </div>
    </form>
  </div>
</div>

<script src="<?= appEscape(appAssetUrl('assets/js/stock.js')) ?>"></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
