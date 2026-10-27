import './styles/tokens.css';
import './styles/base.css';
import './styles/shell.css';
import './styles/legacy-compat.css';
import './styles/panels.css';

import { App } from './app';
import { readConfig, readDashboardName } from './config';
import { log } from './core/log';

const t0 = performance.now();
const cfg = readConfig();
const name = readDashboardName();

const rootEl = document.getElementById('app') ?? document.body.appendChild(document.createElement('div'));
rootEl.id = 'app';

// 4K-Modus: alle rem-basierten Maße verdoppeln (?ui=4k oder automatisch ab 3000 px Breite).
const scale = cfg.uiScale === 'auto' ? (window.innerWidth >= 3000 ? 2 : 1) : cfg.uiScale;
document.documentElement.style.setProperty('--ui-scale', String(scale));
document.documentElement.dataset.signage = '1';

const app = new App(cfg, name, rootEl);
app.start();
app.metrics.initMs = performance.now() - t0;
performance.mark?.('dashboard:ready');
log.info(`Initialisiert in ${Math.round(app.metrics.initMs)} ms`);

if (cfg.debug) {
  // Debug-Modus als eigener Chunk – im Produktionsbetrieb wird dieser Code nie geladen.
  void import('./debug/debug').then((m) => m.startDebug(app));
}

declare global {
  interface Window {
    __dashboard?: App;
  }
}
window.__dashboard = app;
