#!/usr/bin/env node

/**
 * Browser smoke test for navigation, responsive accessibility, and modal focus.
 * Start Chrome with a remote-debugging port, then run:
 *   node tests/browser_smoke.mjs http://127.0.0.1:9223 http://127.0.0.1:8767
 */

const debuggerUrl = (process.argv[2] || 'http://127.0.0.1:9223').replace(/\/$/, '');
const appUrl = (process.argv[3] || 'http://127.0.0.1:8767').replace(/\/$/, '');

if (!/^http:\/\/(?:127\.0\.0\.1|localhost):\d+$/i.test(debuggerUrl)
    || !/^https?:\/\/(?:127\.0\.0\.1|localhost)(?::\d+)?(?:\/.*)?$/i.test(appUrl)) {
  throw new Error('Browser dan aplikasi harus menggunakan alamat lokal.');
}

class CdpClient {
  constructor(socket) {
    this.socket = socket;
    this.nextId = 1;
    this.pending = new Map();
    this.waiters = new Map();
    this.handlers = new Map();

    socket.addEventListener('message', event => {
      const message = JSON.parse(String(event.data));
      if (message.id) {
        const request = this.pending.get(message.id);
        if (!request) return;
        this.pending.delete(message.id);
        if (message.error) request.reject(new Error(message.error.message));
        else request.resolve(message.result || {});
        return;
      }

      const queued = this.waiters.get(message.method);
      if (queued?.length) {
        const waiter = queued.shift();
        clearTimeout(waiter.timer);
        waiter.resolve(message.params || {});
      }
      for (const handler of this.handlers.get(message.method) || []) {
        handler(message.params || {});
      }
    });
  }

  static async connect(url) {
    const socket = new WebSocket(url);
    await new Promise((resolve, reject) => {
      const timer = setTimeout(() => reject(new Error('Timeout menghubungkan DevTools.')), 10000);
      socket.addEventListener('open', () => {
        clearTimeout(timer);
        resolve();
      }, { once: true });
      socket.addEventListener('error', () => {
        clearTimeout(timer);
        reject(new Error('Tidak dapat menghubungkan DevTools.'));
      }, { once: true });
    });
    return new CdpClient(socket);
  }

  send(method, params = {}) {
    const id = this.nextId++;
    return new Promise((resolve, reject) => {
      this.pending.set(id, { resolve, reject });
      this.socket.send(JSON.stringify({ id, method, params }));
    });
  }

  waitForEvent(method, timeout = 15000) {
    return new Promise((resolve, reject) => {
      const timer = setTimeout(() => reject(new Error(`Timeout menunggu ${method}.`)), timeout);
      const queue = this.waiters.get(method) || [];
      queue.push({ resolve, reject, timer });
      this.waiters.set(method, queue);
    });
  }

  on(method, handler) {
    const handlers = this.handlers.get(method) || [];
    handlers.push(handler);
    this.handlers.set(method, handlers);
  }

  close() {
    this.socket.close();
  }
}

let checks = 0;
function ensure(condition, message) {
  if (!condition) throw new Error(message);
  checks += 1;
  console.log(`[OK] ${message}`);
}

const delay = milliseconds => new Promise(resolve => setTimeout(resolve, milliseconds));

async function openTarget() {
  const response = await fetch(`${debuggerUrl}/json/new?${encodeURIComponent(`${appUrl}/login.php`)}`, {
    method: 'PUT'
  });
  if (!response.ok) throw new Error(`Gagal membuat tab browser (${response.status}).`);
  return response.json();
}

const target = await openTarget();
const cdp = await CdpClient.connect(target.webSocketDebuggerUrl);
const exceptions = [];

async function evaluate(expression) {
  const result = await cdp.send('Runtime.evaluate', {
    expression,
    awaitPromise: true,
    returnByValue: true,
    userGesture: true
  });
  if (result.exceptionDetails) {
    throw new Error(result.exceptionDetails.exception?.description || result.exceptionDetails.text);
  }
  return result.result?.value;
}

async function waitFor(expression, message, timeout = 15000) {
  const started = Date.now();
  while (Date.now() - started < timeout) {
    if (await evaluate(`Boolean(${expression})`)) return;
    await delay(100);
  }
  throw new Error(`Timeout: ${message}`);
}

async function navigate(url) {
  const loaded = cdp.waitForEvent('Page.loadEventFired');
  await cdp.send('Page.navigate', { url });
  await loaded;
  await waitFor("document.readyState === 'complete'", `memuat ${url}`);
}

async function pressKey(key, code, keyCode) {
  await cdp.send('Input.dispatchKeyEvent', {
    type: 'rawKeyDown', key, code, windowsVirtualKeyCode: keyCode, nativeVirtualKeyCode: keyCode
  });
  await cdp.send('Input.dispatchKeyEvent', {
    type: 'keyUp', key, code, windowsVirtualKeyCode: keyCode, nativeVirtualKeyCode: keyCode
  });
}

try {
  await Promise.all([
    cdp.send('Page.enable'),
    cdp.send('Runtime.enable'),
    cdp.send('DOM.enable')
  ]);
  await cdp.send('Emulation.setDeviceMetricsOverride', {
    width: 1280,
    height: 900,
    deviceScaleFactor: 1,
    mobile: false
  });
  cdp.on('Runtime.exceptionThrown', event => {
    exceptions.push(event.exceptionDetails?.exception?.description || event.exceptionDetails?.text || 'JavaScript error');
  });

  await waitFor("document.readyState === 'complete'", 'halaman login siap');
  ensure(await evaluate("Boolean(document.querySelector('input[name=csrf_token]'))"), 'Form login memuat token CSRF');

  const dashboardLoaded = cdp.waitForEvent('Page.loadEventFired');
  await evaluate(`(() => {
    document.getElementById('username').value = 'admin';
    document.getElementById('password').value = 'admin123';
    document.getElementById('loginForm').requestSubmit();
    return true;
  })()`);
  await dashboardLoaded;
  await waitFor("location.pathname.endsWith('/pages/dashboard.php')", 'login owner menuju dashboard');
  ensure(await evaluate("location.pathname.endsWith('/pages/dashboard.php')"), 'Login owner berhasil di browser');
  ensure(await evaluate("document.querySelector('.skip-link')?.getAttribute('href') === '#mainContentArea'"), 'Skip-link tersedia');

  await waitFor(
    "document.querySelectorAll('#revenueChartDataBody tr').length === 7",
    'data alternatif grafik dashboard'
  );
  ensure(await evaluate("document.querySelectorAll('#revenueChartDataBody tr').length === 7"), 'Grafik dashboard memiliki tujuh baris data alternatif');

  ensure(await evaluate("document.getElementById('sidebar').inert === false"), 'Sidebar desktop aktif untuk keyboard');
  await evaluate("document.getElementById('menuToggle').click()");
  ensure(await evaluate("document.getElementById('sidebar').inert === true && document.getElementById('sidebar').getAttribute('aria-hidden') === 'true'"), 'Sidebar desktop yang ditutup keluar dari tab order');
  await evaluate("document.getElementById('menuToggle').click()");
  ensure(await evaluate("document.getElementById('sidebar').inert === false"), 'Sidebar desktop dapat dibuka kembali');

  await navigate(`${appUrl}/pages/pos.php`);
  await waitFor("document.getElementById('productsGrid')?.getAttribute('aria-busy') === 'false'", 'produk POS selesai dimuat');
  ensure(await evaluate(`(() => {
    const buttons = [...document.querySelectorAll('.category-pill')];
    return buttons.length > 0 && buttons.every(button => button.hasAttribute('aria-pressed'))
      && buttons.filter(button => button.getAttribute('aria-pressed') === 'true').length === 1;
  })()`), 'Filter kategori mengekspos state pilihan');

  await cdp.send('Emulation.setDeviceMetricsOverride', {
    width: 390,
    height: 844,
    deviceScaleFactor: 1,
    mobile: true
  });
  await navigate(`${appUrl}/pages/pos.php`);
  await waitFor("document.getElementById('productsGrid')?.getAttribute('aria-busy') === 'false'", 'POS mobile selesai dimuat');
  ensure(await evaluate("document.getElementById('sidebar').inert === true"), 'Sidebar mobile tersembunyi keluar dari tab order');

  await evaluate("document.getElementById('menuToggle').click()");
  ensure(await evaluate(`(() => {
    const sidebar = document.getElementById('sidebar');
    return sidebar.classList.contains('open') && !sidebar.inert
      && document.getElementById('mainContent').inert
      && sidebar.contains(document.activeElement);
  })()`), 'Sidebar mobile memindahkan fokus dan mengunci latar');
  await pressKey('Escape', 'Escape', 27);
  ensure(await evaluate(`(() => {
    const sidebar = document.getElementById('sidebar');
    return !sidebar.classList.contains('open') && sidebar.inert
      && !document.getElementById('mainContent').inert
      && document.activeElement === document.getElementById('menuToggle');
  })()`), 'Escape menutup sidebar dan mengembalikan fokus');

  await evaluate("document.getElementById('cartFab').click()");
  ensure(await evaluate(`(() => {
    const cart = document.getElementById('cartPanel');
    return cart.classList.contains('open') && cart.getAttribute('role') === 'dialog'
      && cart.getAttribute('aria-modal') === 'true' && !cart.inert
      && document.querySelector('.pos-products').inert
      && cart.contains(document.activeElement);
  })()`), 'Keranjang mobile menjadi dialog dan mengunci latar');

  const focusSelector = 'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
  await evaluate(`(() => {
    const cart = document.getElementById('cartPanel');
    const focusable = [...cart.querySelectorAll(${JSON.stringify(focusSelector)})]
      .filter(element => getComputedStyle(element).display !== 'none' && getComputedStyle(element).visibility !== 'hidden');
    focusable.at(-1)?.focus();
    return focusable.length;
  })()`);
  await pressKey('Tab', 'Tab', 9);
  ensure(await evaluate(`(() => {
    const cart = document.getElementById('cartPanel');
    const focusable = [...cart.querySelectorAll(${JSON.stringify(focusSelector)})]
      .filter(element => getComputedStyle(element).display !== 'none' && getComputedStyle(element).visibility !== 'hidden');
    return document.activeElement === focusable[0];
  })()`), 'Tab ditahan di dalam keranjang mobile');
  await pressKey('Escape', 'Escape', 27);
  ensure(await evaluate(`(() => {
    const cart = document.getElementById('cartPanel');
    return !cart.classList.contains('open') && cart.inert
      && !document.querySelector('.pos-products').inert
      && document.activeElement === document.getElementById('cartFab');
  })()`), 'Escape menutup keranjang dan mengembalikan fokus');

  await evaluate("openModal('receiptModal')");
  ensure(await evaluate(`(() => {
    const overlay = document.getElementById('receiptModal');
    const inner = overlay.querySelector('.modal');
    return overlay.classList.contains('active') && overlay.getAttribute('role') === 'dialog'
      && !inner.hasAttribute('role') && document.querySelector('.pos-layout').inert
      && overlay.contains(document.activeElement);
  })()`), 'Modal memakai satu role dialog dan mengunci latar');
  await pressKey('Escape', 'Escape', 27);
  ensure(await evaluate("!document.getElementById('receiptModal').classList.contains('active') && !document.querySelector('.pos-layout').inert"), 'Escape menutup modal dan memulihkan latar');

  ensure(exceptions.length === 0, `Tidak ada exception JavaScript (${exceptions.length})`);
  console.log(`Browser smoke test selesai: ${checks} pemeriksaan lulus.`);
} finally {
  try {
    await cdp.send('Browser.close');
  } catch {
    // Chrome may close the socket before acknowledging Browser.close.
  }
  cdp.close();
}
