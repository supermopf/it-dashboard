/**
 * Mess-Probe für den Debug-Modus. Wird NUR im Debug-Modus geladen (neue App: dynamischer Import,
 * Legacy-Oberfläche: dist/debug-probe.js per Playwright-Init-Script). Instrumentiert:
 *  Timer (setTimeout/setInterval), WebSocket-Verbindungen, fetch/XHR-Fehler, Long Tasks,
 *  Ressourcen-Ladezeiten, Medienwiedergabe, DOM-Größe, Heap (nur Chromium: performance.memory).
 */

/* eslint-disable @typescript-eslint/no-explicit-any */

export interface ProbeSample {
  t: number;
  domNodes: number;
  iframes: number;
  timeouts: number;
  intervals: number;
  wsOpen: number;
  wsCreated: number;
  heapUsedMb: number | null;
  playingAudio: number;
  playingVideo: number;
  charts: number;
  panels: number | null;
  longTasks: number;
  longTaskMaxMs: number;
}

export interface ProbeHooks {
  panels?: () => number;
  extra?: () => Record<string, unknown>;
}

export interface Probe {
  sample(): ProbeSample;
  samples(): ProbeSample[];
  report(): Record<string, unknown>;
  mark(name: string): void;
  marks: Array<{ name: string; t: number }>;
}

const MAX_SAMPLES = 720;

export function installProbe(hooks: ProbeHooks = {}): Probe {
  const w = window as any;
  if (w.__dashboardProbe) return w.__dashboardProbe as Probe;

  const timeouts = new Set<unknown>();
  const intervals = new Set<unknown>();
  const sockets = new Set<WebSocket>();
  const media = new Set<HTMLMediaElement>();
  const longTasks: number[] = [];
  let longTaskCount = 0;
  let longTaskMax = 0;
  let wsCreated = 0;
  let wsOpened = 0;
  let fetchFailures = 0;
  let xhrFailures = 0;
  const samples: ProbeSample[] = [];
  const marks: Array<{ name: string; t: number }> = [];

  // --- Timer ---
  const oST = w.setTimeout.bind(w);
  const oCT = w.clearTimeout.bind(w);
  const oSI = w.setInterval.bind(w);
  const oCI = w.clearInterval.bind(w);
  w.setTimeout = (fn: any, ms?: number, ...a: any[]) => {
    const id = oST((...args: any[]) => {
      timeouts.delete(id);
      if (typeof fn === 'function') fn(...args);
    }, ms, ...a);
    timeouts.add(id);
    return id;
  };
  w.clearTimeout = (id: any) => {
    timeouts.delete(id);
    oCT(id);
  };
  w.setInterval = (fn: any, ms?: number, ...a: any[]) => {
    const id = oSI(fn, ms, ...a);
    intervals.add(id);
    return id;
  };
  w.clearInterval = (id: any) => {
    intervals.delete(id);
    oCI(id);
  };

  // --- WebSocket ---
  const OWS = w.WebSocket;
  if (OWS) {
    const Wrapped = function (this: any, url: string, protocols?: string | string[]) {
      const ws: WebSocket = protocols === undefined ? new OWS(url) : new OWS(url, protocols);
      wsCreated++;
      sockets.add(ws);
      ws.addEventListener('open', () => wsOpened++);
      ws.addEventListener('close', () => sockets.delete(ws));
      return ws;
    } as any;
    Wrapped.prototype = OWS.prototype;
    for (const k of ['CONNECTING', 'OPEN', 'CLOSING', 'CLOSED']) Wrapped[k] = OWS[k];
    w.WebSocket = Wrapped;
  }

  // --- fetch / XHR ---
  const oFetch = w.fetch?.bind(w);
  if (oFetch) {
    w.fetch = async (...args: any[]) => {
      try {
        const res = await oFetch(...args);
        if (!res.ok && res.status !== 304) fetchFailures++;
        return res;
      } catch (e) {
        fetchFailures++;
        throw e;
      }
    };
  }
  const XHR = w.XMLHttpRequest;
  if (XHR) {
    const oSend = XHR.prototype.send;
    XHR.prototype.send = function (this: XMLHttpRequest, ...a: any[]) {
      this.addEventListener('loadend', () => {
        if (this.status === 0 || this.status >= 400) xhrFailures++;
      });
      return oSend.apply(this, a);
    };
  }

  // --- Medien ---
  const oPlay = HTMLMediaElement.prototype.play;
  HTMLMediaElement.prototype.play = function (this: HTMLMediaElement) {
    media.add(this);
    return oPlay.call(this);
  };

  // --- Long Tasks ---
  try {
    new PerformanceObserver((list) => {
      for (const e of list.getEntries()) {
        longTaskCount++;
        longTaskMax = Math.max(longTaskMax, e.duration);
        longTasks.push(Math.round(e.duration));
        if (longTasks.length > 200) longTasks.shift();
      }
    }).observe({ type: 'longtask', buffered: true });
  } catch {
    /* nicht unterstützt */
  }

  const probe: Probe = {
    marks,
    mark(name: string) {
      marks.push({ name, t: performance.now() });
      if (marks.length > 500) marks.shift();
    },
    sample(): ProbeSample {
      let playingAudio = 0;
      let playingVideo = 0;
      for (const m of [...media]) {
        if (!m.isConnected && m.paused) {
          media.delete(m);
          continue;
        }
        if (!m.paused && !m.ended) {
          if (m instanceof HTMLVideoElement) playingVideo++;
          else playingAudio++;
        }
      }
      const mem = (performance as any).memory;
      const s: ProbeSample = {
        t: Math.round(performance.now()),
        domNodes: document.getElementsByTagName('*').length,
        iframes: document.getElementsByTagName('iframe').length,
        timeouts: timeouts.size,
        intervals: intervals.size,
        wsOpen: [...sockets].filter((s) => s.readyState === 1).length,
        wsCreated,
        heapUsedMb: mem ? Math.round((mem.usedJSHeapSize / 1048576) * 10) / 10 : null,
        playingAudio,
        playingVideo,
        charts: w.Chart?.instances ? Object.keys(w.Chart.instances).length : 0,
        panels: hooks.panels ? hooks.panels() : null,
        longTasks: longTaskCount,
        longTaskMaxMs: Math.round(longTaskMax),
      };
      samples.push(s);
      if (samples.length > MAX_SAMPLES) samples.shift();
      return s;
    },
    samples: () => [...samples],
    report() {
      const nav = performance.getEntriesByType('navigation')[0] as PerformanceNavigationTiming | undefined;
      const res = performance.getEntriesByType('resource') as PerformanceResourceTiming[];
      const external = res.filter((r) => !r.name.startsWith(location.origin));
      return {
        navigation: nav
          ? { domContentLoadedMs: Math.round(nav.domContentLoadedEventEnd), loadMs: Math.round(nav.loadEventEnd) }
          : null,
        resources: {
          count: res.length,
          external: external.map((r) => ({ url: r.name.slice(0, 120), ms: Math.round(r.duration) })).slice(0, 30),
          slowest: [...res]
            .sort((a, b) => b.duration - a.duration)
            .slice(0, 10)
            .map((r) => ({ url: r.name.slice(-80), ms: Math.round(r.duration), bytes: r.transferSize })),
        },
        websocket: { created: wsCreated, opened: wsOpened, reconnects: Math.max(0, wsOpened - 1), open: [...sockets].filter((s) => s.readyState === 1).length },
        failedRequests: { fetch: fetchFailures, xhr: xhrFailures },
        longTasks: { count: longTaskCount, maxMs: Math.round(longTaskMax), recent: [...longTasks].slice(-20) },
        latest: samples[samples.length - 1] ?? null,
        marks: marks.slice(-50),
        extra: hooks.extra ? hooks.extra() : null,
      };
    },
  };
  w.__dashboardProbe = probe;
  return probe;
}
