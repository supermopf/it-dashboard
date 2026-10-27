import { defineConfig, type Plugin } from 'vite';
import { resolve } from 'node:path';

/**
 * monitor/lib/js/jquery.min.js enthält am Ende einen angehängten Block `$.fn.extend({animateCss…})`, der ein
 * globales `$` voraussetzt. Im CommonJS-Modus registriert jQuery sich aber nicht global → ReferenceError.
 * Das Plugin erzwingt den Browser-Modus (globales jQuery/$), wie beim bisherigen <script>-Einbinden.
 */
function jqueryGlobalMode(): Plugin {
  const detection = '"object"==typeof module&&"object"==typeof module.exports';
  return {
    name: 'jquery-global-mode',
    transform(code, id) {
      if (!id.replace(/\\/g, '/').endsWith('monitor/lib/js/jquery.min.js')) return null;
      if (!code.includes(detection)) this.error('jQuery-Moduldetektion nicht gefunden – Plugin anpassen');
      return { code: code.replace(detection, 'false'), map: null };
    },
  };
}

/**
 * Drei Build-Modi, alle mit Ausgabe nach ../monitor/dist:
 *  - production (Standard): App-Shell als ES-Modul + manifest.json (wird von monitor/index.php gelesen)
 *  - vendor: jQuery 2.2.2 + Chart.js 2.3.0 aus monitor/lib als minifiziertes IIFE (nur für Legacy-Ansichten)
 *  - probe:  eigenständige Debug-Probe als IIFE (Messung der Legacy-Oberfläche, siehe docs/PERFORMANCE-BASELINE.md)
 */
export default defineConfig(({ mode }) => {
  const outDir = resolve(import.meta.dirname, '../monitor/dist');

  if (mode === 'vendor' || mode === 'probe') {
    const entry = mode === 'vendor' ? 'src/legacy/vendor-entry.js' : 'src/debug/probe-standalone.ts';
    return {
      base: './',
      plugins: mode === 'vendor' ? [jqueryGlobalMode()] : [],
      build: {
        outDir,
        emptyOutDir: false,
        target: 'es2018',
        lib: {
          entry: resolve(import.meta.dirname, entry),
          name: mode === 'vendor' ? 'DashboardLegacyVendor' : 'DashboardProbe',
          formats: ['iife'],
          fileName: () => (mode === 'vendor' ? 'legacy-vendor.js' : 'debug-probe.js'),
        },
        server: undefined,
      },
    };
  }

  return {
    base: './',
    server: { fs: { allow: ['..'] } },
    build: {
      outDir,
      emptyOutDir: true,
      manifest: true,
      target: 'es2020',
      cssCodeSplit: true,
      sourcemap: false,
      rollupOptions: {
        input: resolve(import.meta.dirname, 'src/main.ts'),
      },
    },
  };
});
