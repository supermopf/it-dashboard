// Fasst docs/measurements/*.json zusammen (Grundlage für PERFORMANCE-BASELINE/RESULTS).
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
const dir = resolve(import.meta.dirname, '../../docs/measurements');
const pct = (arr, p) => { const a = [...arr].sort((x, y) => x - y); return a.length ? Math.round(a[Math.min(a.length - 1, Math.floor((p / 100) * a.length))]) : null; };
for (const v of ['legacy', 'new']) {
  const r = JSON.parse(readFileSync(resolve(dir, `${v}.json`), 'utf8'));
  const s = r.samples;
  const first = s[0], last = s.at(-1);
  const cdp = s.filter((x) => x.cdp).map((x) => x.cdp);
  const avg = (k) => Math.round(cdp.reduce((a, c) => a + c[k], 0) / cdp.length);
  const switches = v === 'legacy' ? r.legacySwitchMs ?? [] : s.map((x) => x.appSwitchMs).filter((x) => x != null);
  console.log(JSON.stringify({
    variant: v,
    init: r.init,
    samples: s.length,
    charts: [first.charts, last.charts, Math.max(...s.map((x) => x.charts))],
    iframesMax: Math.max(...s.map((x) => x.iframes)),
    listeners: [first.listeners, last.listeners, Math.max(...s.map((x) => x.listeners))],
    domNodes: [Math.min(...s.map((x) => x.domNodes)), Math.max(...s.map((x) => x.domNodes))],
    heapMb: [first.jsHeapUsedMb, last.jsHeapUsedMb, Math.max(...s.map((x) => x.jsHeapUsedMb))],
    heapAfterGcMb: r.heapAfterGcMb,
    listenersAfter: r.listenersAfter,
    timers: [Math.max(...s.map((x) => x.timeouts)), Math.max(...s.map((x) => x.intervals))],
    wsOpenMax: Math.max(...s.map((x) => x.wsOpen)), wsCreated: last.wsCreated,
    longTasks: last.longTasks, longTaskMaxMs: last.longTaskMaxMs,
    cdpPer10s: { scriptMs: avg('scriptMs'), layoutMs: avg('layoutMs'), styleMs: avg('styleMs'), taskMs: avg('taskMs') },
    switchMs: { n: switches.length, p50: pct(switches, 50), p95: pct(switches, 95), max: pct(switches, 100) },
    pageErrors: r.pageErrorCount, errorSamples: r.pageErrors.slice(0, 3),
    failedRequests: r.report.failedRequests, external: r.report.resources.external.length,
  }, null, 1));
}
