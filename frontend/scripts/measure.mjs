// Vergleichsmessung Legacy-Oberfläche vs. neue App-Shell unter identischer, beschleunigter Rotation.
//
//   node scripts/measure.mjs [--minutes 5] [--interval 2000] [--only legacy|new] [--out ../docs/measurements]
//
// Startet php -S (Test-Konfiguration, Fixtures) und den Mock-WebSocket-Server, öffnet Chromium (headless),
// injiziert dist/debug-probe.js und sammelt alle 10 s: Heap, DOM-Knoten, iFrames, Chart-Instanzen, Timer,
// WebSocket-Verbindungen, Long Tasks, Medien sowie CDP-Metriken (Script-/Layout-/Style-Zeit).
// Für die Legacy-Oberfläche werden die Fixtures temporär nach monitor/cache/ kopiert (nur wenn dort keine
// Dateien liegen) und danach wieder entfernt.
import { chromium } from '@playwright/test';
import { spawn } from 'node:child_process';
import { copyFileSync, existsSync, mkdirSync, readdirSync, rmSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';

const args = Object.fromEntries(
  process.argv.slice(2).reduce((acc, a, i, arr) => (a.startsWith('--') ? [...acc, [a.slice(2), arr[i + 1]]] : acc), []),
);
const minutes = Number(args.minutes ?? 5);
const interval = Number(args.interval ?? 2000);
const outDir = resolve(import.meta.dirname, args.out ?? '../../docs/measurements');
const root = resolve(import.meta.dirname, '../..');
const fixtures = resolve(import.meta.dirname, '../tests/fixtures/cache');
const PHP_PORT = 8097;
const WS_PORT = 9110;
const CTRL = `http://127.0.0.1:${WS_PORT + 1}`;
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const ctrl = (path, body = {}) => fetch(CTRL + path, { method: 'POST', body: JSON.stringify(body) }).then((r) => r.json());

function start(cmd, argv, env, cwd) {
  const p = spawn(cmd, argv, { cwd, env: { ...process.env, ...env }, stdio: 'ignore' });
  return p;
}

async function waitHttp(url) {
  for (let i = 0; i < 100; i++) {
    try {
      await fetch(url);
      return;
    } catch {
      await sleep(200);
    }
  }
  throw new Error(`nicht erreichbar: ${url}`);
}

const LEGACY_SWITCH_PROBE = `
  (() => {
    window.__switches = [];
    let pending = 0;
    const OWS = window.WebSocket;
    window.WebSocket = function (u, p) {
      const ws = new OWS(u, p);
      ws.addEventListener('message', (e) => { try { const m = JSON.parse(e.data); if (m.type === 'command' && /^!page /.test(m.message)) pending = performance.now(); } catch {} });
      return ws;
    };
    window.WebSocket.prototype = OWS.prototype;
    ['CONNECTING','OPEN','CLOSING','CLOSED'].forEach((k) => (window.WebSocket[k] = OWS[k]));
    document.addEventListener('DOMContentLoaded', () => {
      const target = document.getElementById('ajax');
      if (!target) return;
      new MutationObserver(() => {
        if (!pending) return;
        const t0 = pending; pending = 0;
        // Ende = nach Abschluss der animate.css-Animation (bounceInUp, ~1 s) bzw. zwei Frames ohne Animation
        const done = () => requestAnimationFrame(() => requestAnimationFrame(() => { window.__switches.push(performance.now() - t0); if (window.__switches.length > 500) window.__switches.shift(); }));
        target.addEventListener('animationend', done, { once: true });
      }).observe(target, { childList: true });
    });
  })();`;

async function run(variant) {
  const url = `http://127.0.0.1:${PHP_PORT}/monitor/?Name=Messung${variant === 'legacy' ? '&legacy=1' : ''}`;
  const browser = await chromium.launch({ args: ['--enable-precise-memory-info', '--js-flags=--expose-gc', '--autoplay-policy=no-user-gesture-required'] });
  const page = await browser.newPage({ viewport: { width: 1920, height: 1080 } });
  await page.addInitScript({ path: resolve(root, 'monitor/dist/debug-probe.js') });
  if (variant === 'legacy') await page.addInitScript(LEGACY_SWITCH_PROBE);
  const cdp = await page.context().newCDPSession(page);
  await cdp.send('Performance.enable');
  const errors = [];
  page.on('pageerror', (e) => errors.push(e.message.slice(0, 200)));

  await ctrl('/reset');
  const t0 = Date.now();
  await page.goto(url, { waitUntil: 'load' });
  await sleep(4000);
  const init = await page.evaluate(() => {
    const nav = performance.getEntriesByType('navigation')[0];
    const ready = performance.getEntriesByName('dashboard:ready')[0];
    return { domContentLoadedMs: Math.round(nav.domContentLoadedEventEnd), loadMs: Math.round(nav.loadEventEnd), appReadyMs: ready ? Math.round(ready.startTime) : null };
  });
  await ctrl('/cycle', { ms: interval });

  const samples = [];
  const end = t0 + minutes * 60_000;
  let lastMetrics = null;
  while (Date.now() < end) {
    await sleep(10_000);
    const m = Object.fromEntries((await cdp.send('Performance.getMetrics')).metrics.map((x) => [x.name, x.value]));
    const s = await page.evaluate(() => {
      const p = window.__dashboardProbe.sample();
      const app = window.__dashboard ? window.__dashboard.stats() : null;
      return { ...p, appSwitchMs: app ? app.metrics.lastSwitchMs : null, appPanels: app ? app.panels : null, appTimers: app ? app.timers : null, legacySwitches: window.__switches ? window.__switches.slice(-5) : null };
    });
    const delta = lastMetrics
      ? {
          scriptMs: Math.round((m.ScriptDuration - lastMetrics.ScriptDuration) * 1000),
          layoutMs: Math.round((m.LayoutDuration - lastMetrics.LayoutDuration) * 1000),
          styleMs: Math.round((m.RecalcStyleDuration - lastMetrics.RecalcStyleDuration) * 1000),
          taskMs: Math.round((m.TaskDuration - lastMetrics.TaskDuration) * 1000),
        }
      : null;
    lastMetrics = m;
    samples.push({ elapsedS: Math.round((Date.now() - t0) / 1000), ...s, cdp: delta, jsHeapUsedMb: Math.round((m.JSHeapUsedSize / 1048576) * 10) / 10, nodes: m.Nodes, listeners: m.JSEventListeners });
    process.stdout.write(`${variant} t=${samples.at(-1).elapsedS}s heap=${samples.at(-1).jsHeapUsedMb}MB dom=${s.domNodes} charts=${s.charts} iframes=${s.iframes} listeners=${m.JSEventListeners}\n`);
  }
  await ctrl('/cycle', { ms: 0 });
  // Nach erzwungener Garbage Collection: verbleibender Heap (zeigt echte Lecks)
  await page.evaluate(() => window.gc && window.gc());
  await sleep(1000);
  const after = Object.fromEntries((await cdp.send('Performance.getMetrics')).metrics.map((x) => [x.name, x.value]));
  const report = await page.evaluate(() => window.__dashboardProbe.report());
  const switches = await page.evaluate(() => (window.__switches ? window.__switches : null));
  await browser.close();
  return {
    variant,
    minutes,
    pageIntervalMs: interval,
    init,
    samples,
    heapAfterGcMb: Math.round((after.JSHeapUsedSize / 1048576) * 10) / 10,
    listenersAfter: after.JSEventListeners,
    nodesAfter: after.Nodes,
    report,
    legacySwitchMs: switches,
    pageErrors: [...new Set(errors)].slice(0, 20),
    pageErrorCount: errors.length,
  };
}

const copied = [];
const php = start('php', ['-S', `127.0.0.1:${PHP_PORT}`, '-t', '.'], {
  DASHBOARD_CONFIG: resolve(import.meta.dirname, '../tests/fixtures/config.test.php'),
  TEST_WS_URL: `ws://127.0.0.1:${WS_PORT}/`,
}, root);
const mock = start('node', ['tests/mock-server/server.mjs'], { WS_PORT: String(WS_PORT) }, resolve(import.meta.dirname, '..'));
try {
  await waitHttp(`http://127.0.0.1:${PHP_PORT}/monitor/legacy.php`);
  await waitHttp(`${CTRL}/state`);
  const cacheDir = resolve(root, 'monitor/cache');
  for (const f of readdirSync(fixtures)) {
    const target = resolve(cacheDir, f);
    if (!existsSync(target)) {
      copyFileSync(resolve(fixtures, f), target);
      copied.push(target);
    }
  }
  mkdirSync(outDir, { recursive: true });
  for (const v of args.only ? [args.only] : ['legacy', 'new']) {
    const r = await run(v);
    writeFileSync(resolve(outDir, `${v}.json`), JSON.stringify(r, null, 2));
    console.log(`→ ${resolve(outDir, `${v}.json`)}`);
  }
} finally {
  for (const f of copied) rmSync(f, { force: true });
  php.kill();
  mock.kill();
}
