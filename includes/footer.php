<?php
declare(strict_types=1);

/**
 * Authenticated application shell footer and shared browser runtime.
 */

$needsChartJs = isset($currentPage) && in_array($currentPage, ['dashboard', 'report'], true);
?>
    </main><!-- /.page-content -->
  </div><!-- /.main-content -->
</div><!-- /.app-wrapper -->

<div class="toast-container" id="toastContainer" role="region" aria-label="Notifikasi" aria-live="polite" aria-atomic="false"></div>

<?php if ($needsChartJs): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<?php endif; ?>
<script>
(() => {
  'use strict';

  const mobileBreakpoint = 1024;
  const modalReturnFocus = new Map();
  const modalInertStates = new Map();
  let desktopSidebarCollapsed = false;

  function pageOverlayIsActive() {
    return Boolean(
      document.querySelector('.modal-overlay.active, .sidebar.open, #cartPanel.open')
    );
  }

  function syncBodyScrollLock() {
    document.body.style.overflow = pageOverlayIsActive() ? 'hidden' : '';
  }

  function setOutsideInert(target, active, state = new Map()) {
    if (!(target instanceof HTMLElement)) return state;

    if (!active) {
      state.forEach((wasInert, element) => {
        if (document.contains(element)) element.inert = wasInert;
      });
      state.clear();
      return state;
    }

    let current = target;
    while (current.parentElement && current.parentElement !== document.body) {
      Array.from(current.parentElement.children).forEach((sibling) => {
        if (sibling === current || !(sibling instanceof HTMLElement)) return;
        if (!state.has(sibling)) state.set(sibling, sibling.inert);
        sibling.inert = true;
      });
      current = current.parentElement;
    }
    return state;
  }

  function updateClock() {
    const clock = document.getElementById('clock');
    if (!clock) return;
    clock.textContent = new Date().toLocaleTimeString('id-ID', {
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit'
    });
  }

  function setMobileSidebar(open, restoreFocus = false) {
    const sidebar = document.getElementById('sidebar');
    const main = document.getElementById('mainContent');
    const overlay = document.getElementById('sidebarOverlay');
    const menu = document.getElementById('menuToggle');
    if (!sidebar || !main || !overlay || !menu) return;

    sidebar.classList.toggle('open', open);
    overlay.classList.toggle('active', open);
    overlay.tabIndex = -1;
    menu.setAttribute('aria-expanded', open ? 'true' : 'false');
    sidebar.inert = !open;
    sidebar.setAttribute('aria-hidden', open ? 'false' : 'true');
    main.inert = open;
    syncBodyScrollLock();

    if (open) {
      const currentLink = sidebar.querySelector('[aria-current="page"]');
      const focusable = focusableElements(sidebar);
      (currentLink || focusable[0] || sidebar).focus();
    } else if (restoreFocus) {
      menu.focus();
    }
  }

  window.toggleSidebar = function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const main = document.getElementById('mainContent');
    const menu = document.getElementById('menuToggle');
    if (!sidebar || !main || !menu) return;

    if (window.innerWidth <= mobileBreakpoint) {
      setMobileSidebar(!sidebar.classList.contains('open'));
      return;
    }

    desktopSidebarCollapsed = !desktopSidebarCollapsed;
    sidebar.style.transform = desktopSidebarCollapsed ? 'translateX(-100%)' : '';
    main.style.marginLeft = desktopSidebarCollapsed ? '0' : '';
    sidebar.inert = desktopSidebarCollapsed;
    sidebar.setAttribute('aria-hidden', desktopSidebarCollapsed ? 'true' : 'false');
    menu.setAttribute('aria-expanded', desktopSidebarCollapsed ? 'false' : 'true');
  };

  function syncSidebarForViewport() {
    const sidebar = document.getElementById('sidebar');
    const main = document.getElementById('mainContent');
    const overlay = document.getElementById('sidebarOverlay');
    const menu = document.getElementById('menuToggle');
    if (!sidebar || !main || !overlay || !menu) return;

    sidebar.classList.remove('open');
    overlay.classList.remove('active');
    overlay.tabIndex = -1;
    main.inert = false;

    if (window.innerWidth <= mobileBreakpoint) {
      sidebar.style.transform = '';
      main.style.marginLeft = '';
      sidebar.inert = true;
      sidebar.setAttribute('aria-hidden', 'true');
      menu.setAttribute('aria-expanded', 'false');
    } else {
      sidebar.style.transform = desktopSidebarCollapsed ? 'translateX(-100%)' : '';
      main.style.marginLeft = desktopSidebarCollapsed ? '0' : '';
      sidebar.inert = desktopSidebarCollapsed;
      sidebar.setAttribute('aria-hidden', desktopSidebarCollapsed ? 'true' : 'false');
      menu.setAttribute('aria-expanded', desktopSidebarCollapsed ? 'false' : 'true');
    }
    syncBodyScrollLock();
  }

  window.showToast = function showToast(message, type = 'success', duration = 3000) {
    const container = document.getElementById('toastContainer');
    if (!container) return;

    const allowedTypes = ['success', 'error', 'warning', 'info'];
    const safeType = allowedTypes.includes(type) ? type : 'info';
    const icons = { success: '✅', error: '❌', warning: '⚠️', info: 'ℹ️' };
    const toast = document.createElement('div');
    const icon = document.createElement('span');
    const text = document.createElement('span');

    toast.className = `toast toast-${safeType}`;
    toast.setAttribute('role', safeType === 'error' ? 'alert' : 'status');
    icon.textContent = icons[safeType];
    icon.setAttribute('aria-hidden', 'true');
    text.textContent = String(message ?? '');
    toast.append(icon, text);
    container.appendChild(toast);

    const timeout = Number.isFinite(Number(duration))
      ? Math.min(Math.max(Number(duration), 1000), 30000)
      : 3000;
    window.setTimeout(() => toast.remove(), timeout);
  };

  window.formatRupiah = function formatRupiah(amount) {
    const value = Number(amount);
    return 'Rp ' + (Number.isFinite(value) ? value : 0).toLocaleString('id-ID', {
      maximumFractionDigits: 0
    });
  };

  window.formatNumberInput = function formatNumberInput(input) {
    if (!input) return;
    const value = String(input.value ?? '').replace(/\D/g, '');
    input.dataset.rawValue = value;
    input.value = value ? Number(value).toLocaleString('id-ID') : '';
  };

  window.confirmDelete = function confirmDelete(message, callback) {
    if (window.confirm(message || 'Yakin ingin menghapus data ini?') && typeof callback === 'function') {
      callback();
    }
  };

  function focusableElements(container) {
    return Array.from(container.querySelectorAll(
      'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
    )).filter((element) => !element.hidden && element.getAttribute('aria-hidden') !== 'true');
  }

  function modalDialog(overlay) {
    if (overlay.matches('[role="dialog"], [role="alertdialog"]')) return overlay;
    return overlay.querySelector('.modal') || overlay;
  }

  function trapTabWithin(event, container) {
    const focusable = focusableElements(container);
    if (focusable.length === 0) {
      event.preventDefault();
      container.focus();
      return;
    }

    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    if (!container.contains(document.activeElement)) {
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

  window.openModal = function openModal(id) {
    const overlay = document.getElementById(id);
    if (!overlay) return;

    modalReturnFocus.set(id, document.activeElement);
    overlay.classList.add('active');
    overlay.setAttribute('aria-hidden', 'false');
    const dialog = modalDialog(overlay);
    dialog.setAttribute('role', 'dialog');
    dialog.setAttribute('aria-modal', 'true');
    const title = dialog.querySelector('.modal-title');
    if (title) {
      if (!title.id) title.id = `${id}Title`;
      dialog.setAttribute('aria-labelledby', title.id);
    } else if (!dialog.hasAttribute('aria-label')) {
      dialog.setAttribute('aria-label', 'Dialog');
    }
    if (!dialog.hasAttribute('tabindex')) dialog.setAttribute('tabindex', '-1');
    const inertState = new Map();
    setOutsideInert(overlay, true, inertState);
    modalInertStates.set(id, inertState);
    syncBodyScrollLock();

    const focusable = focusableElements(dialog);
    (focusable[0] || dialog).focus();
  };

  window.closeModal = function closeModal(id) {
    const overlay = document.getElementById(id);
    if (!overlay) return;

    overlay.classList.remove('active');
    overlay.setAttribute('aria-hidden', 'true');
    const inertState = modalInertStates.get(id);
    if (inertState) setOutsideInert(overlay, false, inertState);
    modalInertStates.delete(id);
    syncBodyScrollLock();

    const returnTarget = modalReturnFocus.get(id);
    modalReturnFocus.delete(id);
    if (returnTarget && document.contains(returnTarget) && typeof returnTarget.focus === 'function') {
      returnTarget.focus();
    }
  };

  function closeTopModal() {
    const active = Array.from(document.querySelectorAll('.modal-overlay.active')).pop();
    if (active?.id) window.closeModal(active.id);
    return Boolean(active);
  }

  function installFetchGuard() {
    if (window.__kasirkuFetchWrapped || typeof window.fetch !== 'function') return;
    const nativeFetch = window.fetch.bind(window);
    const csrfToken = window.KASIRKU_CONFIG?.csrfToken
      || document.querySelector('meta[name="csrf-token"]')?.content
      || '';

    window.fetch = async function kasirkuFetch(input, init = {}) {
      const requestUrl = new URL(input instanceof Request ? input.url : String(input), window.location.href);
      const method = String(init.method || (input instanceof Request ? input.method : 'GET')).toUpperCase();
      const requestInit = { ...init };

      if (requestUrl.origin === window.location.origin && !['GET', 'HEAD', 'OPTIONS'].includes(method)) {
        const headers = new Headers(input instanceof Request ? input.headers : undefined);
        new Headers(init.headers || {}).forEach((value, key) => headers.set(key, value));
        if (csrfToken && !headers.has('X-CSRF-Token')) headers.set('X-CSRF-Token', csrfToken);
        requestInit.headers = headers;
      }

      const response = await nativeFetch(input, requestInit);
      if (response.status === 401) {
        const baseUrl = String(window.KASIRKU_CONFIG?.baseUrl || '').replace(/\/$/, '');
        window.location.assign(`${baseUrl}/login.php`);
      }
      return response;
    };
    window.__kasirkuFetchWrapped = true;
  }

  document.addEventListener('DOMContentLoaded', () => {
    const menu = document.getElementById('menuToggle');
    const overlay = document.getElementById('sidebarOverlay');

    // app.js historically registered the same function on POS. Removing it
    // first makes this shared runtime the single event source on every page.
    if (menu) {
      menu.removeEventListener('click', window.toggleSidebar);
      menu.addEventListener('click', window.toggleSidebar);
    }
    overlay?.addEventListener('click', () => setMobileSidebar(false, true));

    document.querySelectorAll('.modal-overlay').forEach((modal) => {
      if (!modal.classList.contains('active')) modal.setAttribute('aria-hidden', 'true');
      modal.querySelectorAll('.modal-close').forEach((button) => {
        if (!button.hasAttribute('aria-label')) button.setAttribute('aria-label', 'Tutup dialog');
      });
      modal.addEventListener('click', (event) => {
        if (event.target === modal && modal.id) window.closeModal(modal.id);
      });
    });

    syncSidebarForViewport();
    updateClock();
    window.setInterval(updateClock, 1000);
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      if (closeTopModal()) return;
      const sidebar = document.getElementById('sidebar');
      if (window.innerWidth <= mobileBreakpoint && sidebar?.classList.contains('open')) {
        setMobileSidebar(false, true);
      }
      return;
    }

    if (event.key !== 'Tab') return;
    const activeModal = Array.from(document.querySelectorAll('.modal-overlay.active')).pop();
    if (activeModal) {
      trapTabWithin(event, modalDialog(activeModal));
      return;
    }

    const sidebar = document.getElementById('sidebar');
    if (window.innerWidth <= mobileBreakpoint && sidebar?.classList.contains('open')) {
      trapTabWithin(event, sidebar);
    }
  });

  window.addEventListener('resize', syncSidebarForViewport);
  installFetchGuard();
})();
</script>
<?= $extraScripts ?? '' ?>
</body>
</html>
