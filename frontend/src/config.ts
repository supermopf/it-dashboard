/**
 * Laufzeitkonfiguration. Quelle: `<script id="dashboard-config" type="application/json">`,
 * das monitor/index.php aus config.php erzeugt (nur nicht-geheime Werte). Fehlende Felder → Defaults.
 */

export interface PageDef {
  id: string;
  title: string;
  icon: string;
}

export interface DashboardConfig {
  wsUrl: string;
  cachePath: string;
  assetPath: string;
  pages: PageDef[];
  /** Seiten, bei denen PageStatus „warning“ tatsächlich Alarme bedeutet (AUDIT §5.1). */
  alertPages: string[];
  cycleSeconds: number;
  fetchTimeoutMs: number;
  prefetchLeadSeconds: number;
  maxActiveVideos: number;
  chartAnimation: boolean;
  snowFlakes: number;
  /** 'reload-only': nur der Legacy-Reload-Toast wird ausgeführt; 'legacy': Skripte im ToastBody ausführen (unsicher). */
  toastScripts: 'reload-only' | 'legacy';
  recovery: {
    enabled: boolean;
    wsDownMs: number;
    maxReloadsPerHour: number;
    heapLimitMb: number;
  };
  wsWatchdogMs: number;
  uiScale: 'auto' | number;
  debug: boolean;
}

export const DEFAULT_PAGES: PageDef[] = [
  { id: '1', title: 'Temperatur', icon: 'cloud' },
  { id: '2', title: 'helpLine', icon: 'lifebuoy' },
  { id: '3', title: 'Performance', icon: 'chart' },
  { id: '4', title: 'VMware', icon: 'chart' },
  { id: '5', title: 'SCOM', icon: 'bell' },
  { id: '6', title: 'Backup', icon: 'save' },
  { id: '7', title: 'M365', icon: 'branch' },
  { id: '8', title: 'Updates', icon: 'wrench' },
  { id: '9', title: 'DERDACK', icon: 'pager' },
  { id: '10', title: 'Julian-O-Meter', icon: 'gauge' },
];

export const DEFAULT_CONFIG: DashboardConfig = {
  wsUrl: '',
  cachePath: './cache/',
  assetPath: './',
  pages: DEFAULT_PAGES,
  alertPages: ['3', '4', '5', '6', '9'],
  cycleSeconds: 30,
  fetchTimeoutMs: 8000,
  prefetchLeadSeconds: 4,
  maxActiveVideos: 1,
  chartAnimation: false,
  snowFlakes: 60,
  toastScripts: 'reload-only',
  recovery: { enabled: true, wsDownMs: 10 * 60 * 1000, maxReloadsPerHour: 3, heapLimitMb: 0 },
  wsWatchdogMs: 10000,
  uiScale: 'auto',
  debug: false,
};

export function readConfig(doc: Document = document, search: string = location.search): DashboardConfig {
  let raw: Partial<DashboardConfig> = {};
  const node = doc.getElementById('dashboard-config');
  if (node?.textContent) {
    try {
      raw = JSON.parse(node.textContent) as Partial<DashboardConfig>;
    } catch {
      raw = {};
    }
  }
  const cfg: DashboardConfig = {
    ...DEFAULT_CONFIG,
    ...raw,
    recovery: { ...DEFAULT_CONFIG.recovery, ...(raw.recovery ?? {}) },
    pages: Array.isArray(raw.pages) && raw.pages.length ? raw.pages : DEFAULT_PAGES,
  };
  const params = new URLSearchParams(search);
  if (params.get('debug') === '1') cfg.debug = true;
  try {
    if (localStorage.getItem('dashboard.debug') === '1') cfg.debug = true;
  } catch {
    /* Storage blockiert */
  }
  const ui = params.get('ui');
  if (ui === '4k') cfg.uiScale = 2;
  else if (ui === 'hd') cfg.uiScale = 1;
  const ws = params.get('ws');
  // Nur für Tests/Entwicklung: WebSocket-URL per Parameter überschreiben, wenn keine konfiguriert ist.
  if (ws && !cfg.wsUrl) cfg.wsUrl = ws;
  return cfg;
}

/** Dashboard-Name aus `?Name=` (Legacy: getURLParameter('Name')). Leer = unbenanntes Display. */
export function readDashboardName(search: string = location.search): string {
  const v = new URLSearchParams(search).get('Name');
  if (!v || v === 'null') return '';
  return v.slice(0, 64);
}
