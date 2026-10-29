#!/usr/bin/env node

import { mkdir, writeFile } from 'node:fs/promises';

const debuggerUrl = (process.argv[2] || 'http://127.0.0.1:9225').replace(/\/$/, '');
const appUrl = (process.argv[3] || 'http://127.0.0.1/kasirku').replace(/\/$/, '');
const screenshotDirectory = process.argv[4] || '';

class Cdp {
  constructor(socket) {
    this.socket = socket;
    this.id = 1;
    this.pending = new Map();
    this.waiters = new Map();
    socket.addEventListener('message', event => {
      const message = JSON.parse(String(event.data));
      if (message.id) {
        const pending = this.pending.get(message.id);
        if (!pending) return;
        this.pending.delete(message.id);
        message.error ? pending.reject(new Error(message.error.message)) : pending.resolve(message.result || {});
        return;
      }
      const queue = this.waiters.get(message.method);
      if (queue?.length) queue.shift()(message.params || {});
    });
  }

  static async connect(url) {
    const socket = new WebSocket(url);
    await new Promise((resolve, reject) => {
      socket.addEventListener('open', resolve, { once: true });
      socket.addEventListener('error', reject, { once: true });
    });
    return new Cdp(socket);
  }

  send(method, params = {}) {
    const id = this.id++;
    return new Promise((resolve, reject) => {
      this.pending.set(id, { resolve, reject });
      this.socket.send(JSON.stringify({ id, method, params }));
    });
  }

  event(method) {
    return new Promise(resolve => {
      const queue = this.waiters.get(method) || [];
      queue.push(resolve);
      this.waiters.set(method, queue);
    });
  }
}

const delay = ms => new Promise(resolve => setTimeout(resolve, ms));
const targetResponse = await fetch(`${debuggerUrl}/json/new?${encodeURIComponent(`${appUrl}/login.php`)}`, { method: 'PUT' });
const target = await targetResponse.json();
const cdp = await Cdp.connect(target.webSocketDebuggerUrl);

async function evaluate(expression) {
  const response = await cdp.send('Runtime.evaluate', {
    expression,
    returnByValue: true,
    awaitPromise: true,
    userGesture: true
  });
  if (response.exceptionDetails) {
    throw new Error(response.exceptionDetails.exception?.description || response.exceptionDetails.text);
  }
  return response.result?.value;
}

async function navigate(url) {
  const loaded = cdp.event('Page.loadEventFired');
  await cdp.send('Page.navigate', { url });
  await loaded;
  await delay(700);
}

async function captureScreenshot(name) {
  if (!screenshotDirectory) return;
  const screenshot = await cdp.send('Page.captureScreenshot', {
    format: 'png',
    fromSurface: true
  });
  await writeFile(`${screenshotDirectory}/${name}.png`, Buffer.from(screenshot.data, 'base64'));
}

try {
  if (screenshotDirectory) await mkdir(screenshotDirectory, { recursive: true });
  await Promise.all([cdp.send('Page.enable'), cdp.send('Runtime.enable')]);
  await navigate(`${appUrl}/login.php`);
  await cdp.send('Emulation.setDeviceMetricsOverride', {
    width: 1280, height: 900, deviceScaleFactor: 1, mobile: false
  });
  await delay(300);

  const loginLoaded = cdp.event('Page.loadEventFired');
  await evaluate(`(() => {
    if (!document.getElementById('loginForm')) throw new Error('Form login tidak ditemukan');
    document.getElementById('username').value = 'admin';
    document.getElementById('password').value = 'admin123';
    document.getElementById('loginForm').requestSubmit();
  })()`);
  await loginLoaded;

  const widths = [320, 360, 390, 600, 768, 820, 1024, 1100, 1440];
  const pages = ['dashboard.php', 'pos.php', 'history.php', 'stock.php', 'report.php', 'settings.php'];
  const results = [];

  for (const width of widths) {
    await cdp.send('Emulation.setDeviceMetricsOverride', {
      width,
      height: width <= 600 ? 740 : 900,
      deviceScaleFactor: 1,
      mobile: width <= 768
    });
    for (const page of pages) {
      await navigate(`${appUrl}/pages/${page}`);
      const report = await evaluate(`(() => {
        const visible = element => {
          const style = getComputedStyle(element);
          const rect = element.getBoundingClientRect();
          return style.display !== 'none' && style.visibility !== 'hidden'
            && Number(style.opacity || 1) !== 0 && rect.width > 0 && rect.height > 0;
        };
        const intentionallyOffscreen = element =>
          element.closest('.table-wrapper, .category-pills, .products-scrollarea, .cart-items')
          || element.closest('.sidebar:not(.open), .pos-cart:not(.open), .modal-overlay:not(.active)')
          || element.closest('.sr-only');
        const offenders = [...document.querySelectorAll('body *')]
          .filter(visible)
          .filter(element => {
            if (intentionallyOffscreen(element)) return false;
            const rect = element.getBoundingClientRect();
            return rect.right > innerWidth + 1 || rect.left < -1 || rect.width > innerWidth + 1;
          })
          .slice(0, 12)
          .map(element => {
            const rect = element.getBoundingClientRect();
            return {
              tag: element.tagName.toLowerCase(),
              id: element.id,
              cls: String(element.className || '').slice(0, 80),
              left: Math.round(rect.left),
              right: Math.round(rect.right),
              width: Math.round(rect.width)
            };
          });
        const rect = selector => {
          const element = document.querySelector(selector);
          if (!element) return null;
          const box = element.getBoundingClientRect();
          return { left: Math.round(box.left), right: Math.round(box.right), width: Math.round(box.width), height: Math.round(box.height) };
        };
        return {
          viewport: innerWidth,
          viewportHeight: innerHeight,
          docClient: document.documentElement.clientWidth,
          docScroll: document.documentElement.scrollWidth,
          bodyScroll: document.body.scrollWidth,
          page: location.pathname,
          sidebarVisible: getComputedStyle(document.getElementById('sidebar')).transform === 'none',
          topbar: rect('.topbar'),
          content: rect('.page-content'),
          primaryToolbar: rect('.page-content > div:first-child'),
          cartMode: document.body.classList.contains('pos-page') ? {
            panelPosition: getComputedStyle(document.getElementById('cartPanel')).position,
            panelInert: document.getElementById('cartPanel').inert,
            fabDisplay: getComputedStyle(document.getElementById('cartFab')).display
          } : null,
          offenders
        };
      })()`);
      results.push({ width, page, ...report });

      const screenshotKey = `${width}:${page}`;
      const screenshotNames = {
        '320:dashboard.php': 'dashboard-320',
        '320:stock.php': 'stock-320',
        '390:history.php': 'history-390',
        '390:report.php': 'report-390',
        '390:pos.php': 'pos-products-390'
      };
      if (screenshotNames[screenshotKey]) await captureScreenshot(screenshotNames[screenshotKey]);

      if (screenshotDirectory && page === 'pos.php' && [390, 820].includes(width)) {
        await evaluate(`(() => {
          document.querySelector('[data-product-id]:not([disabled])')?.click();
          window.toggleCart(true);
        })()`);
        await delay(250);
        await captureScreenshot(`pos-cart-${width}`);
      }
    }
  }

  for (const viewport of [{ width: 390, height: 520 }, { width: 820, height: 600 }]) {
    await cdp.send('Emulation.setDeviceMetricsOverride', {
      width: viewport.width,
      height: viewport.height,
      deviceScaleFactor: 1,
      mobile: viewport.width <= 768
    });
    await navigate(`${appUrl}/pages/pos.php`);
    await evaluate(`(() => {
      document.querySelector('[data-product-id]:not([disabled])')?.click();
      window.toggleCart(true);
    })()`);
    await delay(250);
    const drawerReport = await evaluate(`(() => {
      const rect = selector => {
        const box = document.querySelector(selector)?.getBoundingClientRect();
        return box ? { top: Math.round(box.top), bottom: Math.round(box.bottom), height: Math.round(box.height) } : null;
      };
      return {
        viewport: innerWidth,
        viewportHeight: innerHeight,
        page: location.pathname,
        state: 'cart-open',
        sidebarVisible: getComputedStyle(document.getElementById('sidebar')).transform === 'none',
        bodyScroll: document.body.scrollWidth,
        content: null,
        offenders: [],
        cartMode: {
          panelPosition: getComputedStyle(document.getElementById('cartPanel')).position,
          panelInert: document.getElementById('cartPanel').inert,
          fabDisplay: getComputedStyle(document.getElementById('cartFab')).display
        },
        drawer: rect('#cartPanel'),
        processButton: rect('#processTxnBtn'),
        closeButton: rect('.cart-footer .btn-close-cart-mobile')
      };
    })()`);
    results.push({ width: viewport.width, page: 'pos-cart-open', ...drawerReport });
  }

  console.log(JSON.stringify(results, null, 2));
  const failures = results.filter(result => {
    if (result.state === 'cart-open') {
      return result.drawer?.top < -1
        || result.drawer?.bottom > result.viewportHeight + 1
        || result.processButton?.bottom > result.viewportHeight + 1
        || result.closeButton?.bottom > result.viewportHeight + 1;
    }
    const layoutOverflow = result.bodyScroll > result.viewport + 1
      || result.content?.right > result.viewport + 1
      || result.offenders.length > 0;
    const shouldUseDrawer = result.page.endsWith('/pos.php') && result.width <= 1100;
    const invalidCartMode = shouldUseDrawer && (
      result.cartMode?.panelPosition !== 'fixed'
      || result.cartMode?.fabDisplay === 'none'
      || result.cartMode?.panelInert !== true
    );
    const invalidSidebarMode = result.width <= 1024
      ? result.sidebarVisible
      : !result.sidebarVisible;
    return layoutOverflow || invalidCartMode || invalidSidebarMode;
  });
  if (failures.length > 0) {
    console.error(`Responsive regression failures: ${failures.length}`);
    process.exitCode = 1;
  }
} finally {
  try { await cdp.send('Browser.close'); } catch {}
  cdp.socket.close();
}
