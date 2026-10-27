/**
 * Laufzeitumgebung für die serverseitig gerenderten Legacy-Ansichten (monitor/cache/pageN.php).
 *
 * Die Fragmente enthalten Inline-Skripte, die globale `$` (jQuery 2.2) und `Chart` (Chart.js 2.3) erwarten.
 * Diese Abhängigkeiten werden erst beim ersten Bedarf geladen (dist/legacy-vendor.js, einmalig) und so
 * konfiguriert, dass sie im Dauerbetrieb nicht wachsen:
 *   - Chart-Animationen aus (Standard), System-Schrift, dunkles Farbschema
 *   - `Chart.pluginService.register` dedupliziert (AUDIT P3)
 *   - beim Entfernen einer Ansicht werden alle Charts darin zerstört (AUDIT P1/P2)
 */

/* eslint-disable @typescript-eslint/no-explicit-any */

interface LegacyChartInstance {
  id: number | string;
  chart?: { canvas?: HTMLCanvasElement };
  canvas?: HTMLCanvasElement;
  destroy(): void;
}

interface LegacyChartStatic {
  instances: Record<string, LegacyChartInstance>;
  defaults: any;
  plugins?: { register(p: unknown): void; getAll?(): unknown[] };
  pluginService?: { register(p: unknown): void };
  __dashboardPatched?: boolean;
}

declare global {
  interface Window {
    Chart?: LegacyChartStatic;
    jQuery?: unknown;
    $?: unknown;
    CyclePages?: () => void;
  }
}

export interface LegacyRuntimeOptions {
  vendorUrl: string;
  chartAnimation: boolean;
  fontFamily: string;
  colors: { text: string; grid: string };
}

export class LegacyRuntime {
  private loading: Promise<void> | null = null;
  readonly stats = { pluginRegistrations: 0, pluginDuplicatesSkipped: 0, chartsDestroyed: 0 };

  constructor(private readonly o: LegacyRuntimeOptions) {}

  get loaded(): boolean {
    return typeof window.Chart !== 'undefined' && typeof window.jQuery !== 'undefined';
  }

  /** Lädt jQuery + Chart.js genau einmal. */
  ensure(): Promise<void> {
    if (this.loaded) {
      this.patch();
      return Promise.resolve();
    }
    if (this.loading) return this.loading;
    this.loading = new Promise<void>((resolve, reject) => {
      const s = document.createElement('script');
      s.src = this.o.vendorUrl;
      s.async = true;
      s.onload = () => {
        this.patch();
        resolve();
      };
      s.onerror = () => {
        this.loading = null;
        s.remove();
        reject(new Error(`Legacy-Bibliotheken konnten nicht geladen werden (${this.o.vendorUrl})`));
      };
      document.head.append(s);
    });
    return this.loading;
  }

  /** Anzahl der aktuell existierenden Chart-Instanzen (Debug, Tests). */
  chartCount(): number {
    const C = window.Chart;
    return C?.instances ? Object.keys(C.instances).length : 0;
  }

  /** Alle Charts zerstören, deren Canvas innerhalb von `root` liegt (oder nicht mehr im Dokument hängt). */
  destroyChartsWithin(root: Element): number {
    const C = window.Chart;
    if (!C?.instances) return 0;
    let n = 0;
    for (const inst of Object.values(C.instances)) {
      const canvas = inst.chart?.canvas ?? inst.canvas;
      if (!canvas || root.contains(canvas) || !canvas.isConnected) {
        try {
          inst.destroy();
        } catch {
          delete C.instances[String(inst.id)];
        }
        n++;
      }
    }
    this.stats.chartsDestroyed += n;
    return n;
  }

  private patch(): void {
    const C = window.Chart;
    if (!C || C.__dashboardPatched) return;
    C.__dashboardPatched = true;

    const g = C.defaults?.global;
    if (g) {
      if (!this.o.chartAnimation) {
        g.animation = { ...(g.animation ?? {}), duration: 0 };
        g.hover = { ...(g.hover ?? {}), animationDuration: 0 };
        g.responsiveAnimationDuration = 0;
      }
      g.defaultFontFamily = this.o.fontFamily;
      g.defaultFontColor = this.o.colors.text;
      // Tooltips/Hover sind auf einem Signage-Display ohne Maus nutzlos und kosten Event-Listener.
      g.events = [];
      if (g.tooltips) g.tooltips.enabled = false;
    }
    const scale = C.defaults?.scale;
    if (scale?.gridLines) {
      scale.gridLines.color = this.o.colors.grid;
      scale.gridLines.zeroLineColor = this.o.colors.grid;
    }

    const registry = C.plugins ?? C.pluginService;
    if (registry && typeof registry.register === 'function') {
      const original = registry.register.bind(registry);
      const seen = new Set<string>();
      const fingerprint = (p: unknown): string => {
        if (!p || typeof p !== 'object') return String(p);
        return Object.entries(p as Record<string, unknown>)
          .map(([k, v]) => `${k}:${typeof v === 'function' ? v.toString() : JSON.stringify(v)}`)
          .join('|');
      };
      const register = (p: unknown): void => {
        const list = Array.isArray(p) ? p : [p];
        for (const plugin of list) {
          const key = fingerprint(plugin);
          if (seen.has(key)) {
            this.stats.pluginDuplicatesSkipped++;
            continue;
          }
          seen.add(key);
          this.stats.pluginRegistrations++;
          original(plugin);
        }
      };
      registry.register = register;
      if (C.pluginService && C.pluginService !== registry) C.pluginService.register = register;
    }
  }
}
