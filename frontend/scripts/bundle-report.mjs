// Produktions-Bundle-Analyse: Größe roh/gzip/brotli je Datei in monitor/dist und Abgleich mit den Budgets.
//   node scripts/bundle-report.mjs            → Tabelle
//   node scripts/bundle-report.mjs --json     → JSON
import { readdirSync, readFileSync, statSync } from 'node:fs';
import { join, relative, resolve } from 'node:path';
import { brotliCompressSync, gzipSync } from 'node:zlib';

const dist = resolve(import.meta.dirname, '../../monitor/dist');
const BUDGET = { initialJsGzip: 150 * 1024, cssGzip: 50 * 1024 };

function walk(dir) {
  return readdirSync(dir).flatMap((f) => {
    const p = join(dir, f);
    return statSync(p).isDirectory() ? walk(p) : [p];
  });
}

let files;
try {
  files = walk(dist).filter((f) => /\.(js|css)$/.test(f));
} catch {
  console.error('monitor/dist fehlt – zuerst `npm run build` ausführen.');
  process.exit(1);
}

const rows = files.map((f) => {
  const buf = readFileSync(f);
  return {
    file: relative(dist, f).replace(/\\/g, '/'),
    raw: buf.length,
    gzip: gzipSync(buf, { level: 9 }).length,
    brotli: brotliCompressSync(buf).length,
  };
});

const manifest = JSON.parse(readFileSync(join(dist, '.vite/manifest.json'), 'utf8'));
const entry = manifest['src/main.ts'];
const initialJs = rows.filter((r) => r.file === entry.file);
const css = rows.filter((r) => r.file.endsWith('.css'));
const vendor = rows.filter((r) => r.file === 'legacy-vendor.js');
const sum = (list, k) => list.reduce((a, r) => a + r[k], 0);

const summary = {
  initialJsGzip: sum(initialJs, 'gzip'),
  initialJsPlusLegacyVendorGzip: sum(initialJs, 'gzip') + sum(vendor, 'gzip'),
  cssGzip: sum(css, 'gzip'),
  budgets: BUDGET,
  withinBudget: {
    initialJs: sum(initialJs, 'gzip') <= BUDGET.initialJsGzip,
    initialJsPlusLegacyVendor: sum(initialJs, 'gzip') + sum(vendor, 'gzip') <= BUDGET.initialJsGzip,
    css: sum(css, 'gzip') <= BUDGET.cssGzip,
  },
};

if (process.argv.includes('--json')) {
  console.log(JSON.stringify({ rows, summary }, null, 2));
} else {
  const kb = (n) => `${(n / 1024).toFixed(1)} KB`.padStart(10);
  console.log('Datei'.padEnd(36) + 'roh'.padStart(10) + 'gzip'.padStart(10) + 'brotli'.padStart(10));
  for (const r of rows.sort((a, b) => b.gzip - a.gzip)) console.log(r.file.padEnd(36) + kb(r.raw) + kb(r.gzip) + kb(r.brotli));
  console.log('');
  console.log(`Initiales JS (App):              ${kb(summary.initialJsGzip)} gzip  Budget ${kb(BUDGET.initialJsGzip)}  ${summary.withinBudget.initialJs ? 'OK' : 'ÜBERSCHRITTEN'}`);
  console.log(`App + Legacy-Vendor (1. Ansicht): ${kb(summary.initialJsPlusLegacyVendorGzip)} gzip  Budget ${kb(BUDGET.initialJsGzip)}  ${summary.withinBudget.initialJsPlusLegacyVendor ? 'OK' : 'ÜBERSCHRITTEN'}`);
  console.log(`CSS:                             ${kb(summary.cssGzip)} gzip  Budget ${kb(BUDGET.cssGzip)}  ${summary.withinBudget.css ? 'OK' : 'ÜBERSCHRITTEN'}`);
  if (!summary.withinBudget.initialJsPlusLegacyVendor || !summary.withinBudget.css) process.exitCode = 1;
}
