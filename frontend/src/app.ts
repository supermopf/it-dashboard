import type { DashboardConfig } from './config';
import { httpStats } from './core/http';
import { log } from './core/log';
import { ReloadGuard, type ReloadReason } from './core/recovery';
import { Scheduler } from './core/scheduler';
import { Scope } from './core/scope';
import { Store } from './core/store';
import { ViewCache } from './core/view-cache';
import { WsClient, type WsState } from './core/ws-client';
import { Bus } from './features/bus';
import { Critters } from './features/critters';
import { OfflineBanner } from './features/offline-banner';
import { RadioController } from './features/radio';
import { Snow } from './features/snow';
import { Spooky } from './features/spooky';
import { SongToast } from './features/song-toast';
import { Topbar } from './features/topbar';
import { UrgentToast, type UrgentPayload } from './features/urgent-toast';
import { LegacyRuntime } from './legacy/legacy-runtime';
import { MediaManager } from './media/media-manager';
import { parseMessage, parsePageToken, parseRoll, type ServerUpdate } from './protocol/messages';
import { FragmentPanel } from './panels/fragment-panel';
import { MessagePanel } from './panels/message-panel';
import { panelStats, type Panel, type PanelContext } from './panels/panel';
import { PanelHost } from './panels/panel-host';
import { WheelPanel } from './panels/wheel-panel';
import { YouTubePanel } from './panels/youtube-panel';
import { el } from './util/dom';
import { parseYouTube } from './util/youtube';

export const FONT_STACK =
  'system-ui, -apple-system, "Segoe UI", Roboto, "Noto Sans", "Liberation Sans", "DejaVu Sans", Arial, sans-serif';

interface AppState extends ServerUpdate {
  ws: WsState;
}

const INITIAL: AppState = {
  ws: 'idle',
  Page: null,
  FUN: false,
  Wheel: false,
  Radio: false,
  Radiostation: '',
  Radiovolume: 0.01,
  YTvolume: 0.01,
  Cat: false,
  Snow: false,
  BusActive: false,
  CD: 0,
  Cycle: true,
  SongTitle: '',
  RadioStationIcon: '',
  PageStatus: {},
  Spooky: 'auto',
};

/** Kennzahlen für den Debug-Modus (werden auch im Normalbetrieb billig mitgezählt). */
export interface AppMetrics {
  initMs: number;
  lastSwitchMs: number;
  switches: number;
  failedApiCalls: number;
}

export class App {
  readonly scheduler = new Scheduler();
  readonly store = new Store<AppState>(INITIAL);
  readonly media: MediaManager;
  readonly legacy: LegacyRuntime;
  readonly views: ViewCache;
  readonly ws: WsClient;
  readonly metrics: AppMetrics = { initMs: 0, lastSwitchMs: 0, switches: 0, failedApiCalls: 0 };
  private readonly scope: Scope;
  private readonly host: PanelHost;
  private readonly topbar: Topbar;
  private readonly urgent: UrgentToast;
  private readonly song: SongToast;
  private readonly radio: RadioController;
  private readonly critters: Critters;
  private readonly bus: Bus;
  private readonly snow: Snow;
  private readonly spooky: Spooky;
  private readonly banner: OfflineBanner;
  private readonly guard: ReloadGuard;
  private readonly ctx: PanelContext;
  private currentPage: string | null = null;
  private shownToken: string | null = null;
  private shownHtml: string | null = null;
  private requestSeq = 0;
  private youtube = false;
  private registered = false;
  private prefetchedFor = '';
  private lastSong = '';
  private lastOkAt: number | null = null;
  private pendingHeapReload = false;

  constructor(
    private readonly cfg: DashboardConfig,
    private readonly name: string,
    root: HTMLElement,
    private readonly base: string = location.href,
  ) {
    const t0 = performance.now();
    this.scope = new Scope(this.scheduler);
    this.media = new MediaManager(cfg.maxActiveVideos);
    this.legacy = new LegacyRuntime({
      vendorUrl: this.asset('dist/legacy-vendor.js'),
      chartAnimation: cfg.chartAnimation,
      fontFamily: FONT_STACK,
      colors: { text: '#a3acb9', grid: 'rgba(255,255,255,0.08)' },
    });
    this.views = new ViewCache((p) => this.asset(`${cfg.cachePath}page${p}.php`), cfg.fetchTimeoutMs);
    this.guard = new ReloadGuard({ maxPerHour: cfg.recovery.maxReloadsPerHour });
    this.ctx = {
      config: cfg,
      scheduler: this.scheduler,
      media: this.media,
      log,
      send: (m) => this.ws.send(m),
      asset: (p) => this.asset(p),
    };

    this.topbar = new Topbar(cfg, this.scope, (page) => this.ws.send(`!fpage ${page}`));
    const main = el('main', { class: 'stage' });
    this.host = new PanelHost(main, log, {
      onShown: (panel, ms) => {
        this.metrics.lastSwitchMs = ms;
        this.metrics.switches++;
        this.topbar.setFreshness(panel.dataTimestamp, panel.stale);
      },
    });
    this.urgent = new UrgentToast({
      scheduler: this.scheduler,
      media: this.media,
      log,
      defaultSound: this.asset('res/alert.mp3'),
      onReloadRequest: () => this.requestReload('remote'),
      allowLegacyScripts: cfg.toastScripts === 'legacy',
    });
    this.song = new SongToast(this.scheduler);
    this.radio = new RadioController(this.media, log);
    this.critters = new Critters(this.topbar.critterHost, this.media, (p) => this.asset(p), this.named);
    this.bus = new Bus(this.scope, (p) => this.asset(p), (m) => this.ws.send(m));
    this.banner = new OfflineBanner();
    const overlay = el('div', { class: 'overlay-layer' });
    this.snow = new Snow(overlay, cfg.snowFlakes);
    this.spooky = new Spooky(this.scheduler, cfg.spooky);

    root.replaceChildren(
      this.topbar.element,
      main,
      this.bus.element,
      this.spooky.element,
      this.song.element,
      this.banner.element,
      this.urgent.element,
      overlay,
    );

    this.ws = new WsClient({
      url: cfg.wsUrl,
      scheduler: this.scheduler,
      watchdogMs: cfg.wsWatchdogMs,
      onOpen: () => this.onOpen(),
      onMessage: (d) => this.onMessage(d),
      onStateChange: (s) => this.store.patch({ ws: s }),
    });

    this.bindStore();
    this.metrics.initMs = performance.now() - t0;
  }

  get named(): boolean {
    return this.name !== '';
  }

  start(): void {
    this.scheduler.interval(() => this.topbar.tickClock(new Date()), 1000, this.scope, { align: true, label: 'clock' });
    this.topbar.tickClock(new Date());
    this.scheduler.interval(() => this.healthCheck(), 5000, this.scope, { pauseWhenHidden: false, label: 'health' });

    this.scope.listen(document, 'visibilitychange', () => {
      this.scheduler.setHidden(document.hidden);
      if (!document.hidden) this.ws.reconnectNow();
    });
    this.scope.listen(window, 'online', () => this.ws.reconnectNow());
    this.scheduler.setHidden(document.hidden);

    if (!this.cfg.wsUrl) {
      log.error('Keine WebSocket-URL konfiguriert');
      this.banner.show('Keine WebSocket-URL konfiguriert', null);
    } else {
      this.ws.start();
    }
    // Bis der Server eine Seite vorgibt: letzte bekannte Ansicht aus dem Cache bzw. Ladeanzeige.
    const cached = this.views.peek('1');
    void this.host.show(
      () =>
        cached
          ? new FragmentPanel('1', this.ctx, { html: cached.html, legacy: this.legacy, fetchedAt: cached.fetchedAt, stale: true })
          : new MessagePanel('loading', this.ctx, 'loading', 'Verbinde mit dem Dashboard-Server …'),
      (err) => new MessagePanel('error', this.ctx, 'error', 'Ansicht konnte nicht dargestellt werden', String(err)),
    );
    if (cached) {
      this.shownToken = '1';
      this.shownHtml = cached.html;
    }
  }

  /** Für Tests und Debug. */
  stats(): Record<string, unknown> {
    return {
      wsState: this.ws.state,
      ws: { ...this.ws.stats },
      timers: this.scheduler.activeCount,
      timersByLabel: this.scheduler.describe(),
      panels: panelStats.live,
      activePanel: this.host.active?.id ?? null,
      switching: this.host.busy,
      charts: this.legacy.chartCount(),
      legacy: { ...this.legacy.stats },
      media: this.media.counts(),
      http: { ...httpStats },
      views: { ...this.views.stats },
      store: { ...this.store.stats, listeners: this.store.listenerCount },
      metrics: { ...this.metrics },
      logEntries: log.length,
    };
  }

  private asset(path: string): string {
    if (/^https?:\/\//i.test(path)) return path;
    return new URL(path.replace(/^\.\//, ''), new URL(this.cfg.assetPath, this.base)).href;
  }

  // ---- WebSocket ----------------------------------------------------------------------------------------

  private onOpen(): void {
    this.registered = false;
    this.ws.send('!repeat');
    this.ws.send(`!reg [Dashboard]${this.name}`);
  }

  private onMessage(raw: string): void {
    const msg = parseMessage(raw);
    if (!msg) {
      log.debug('Ungültige WebSocket-Nachricht verworfen');
      return;
    }
    // Legacy: Registrierung erst nach der ersten Servernachricht (Verbindung stabil).
    if (!this.registered && this.named) {
      this.registered = true;
      this.ws.send(`!dashboard ${this.name}`);
    }
    this.lastOkAt = Date.now();
    switch (msg.type) {
      case 'update':
        this.store.patch(msg.update);
        break;
      case 'command':
        this.onCommand(msg.command, msg.arg);
        break;
      case 'console':
        log.debug(`Server: ${msg.message}`);
        break;
      case 'auth':
        this.ws.send(`!reg [Dashboard]${this.name}`);
        if (this.named) this.ws.send(`!dashboard ${this.name}`);
        break;
      default:
        break;
    }
  }

  private onCommand(command: string, arg: string): void {
    switch (command) {
      case '!page': {
        const token = parsePageToken(arg);
        if (!token) return;
        if ((token === 'FUN' || token === 'WHEEL') && !this.named) return;
        void this.showPage(token);
        break;
      }
      case '!video':
        if (this.named) this.playYouTube(arg);
        break;
      case '!roll': {
        const p = this.host.active;
        if (this.named && p instanceof WheelPanel) {
          const idx = parseRoll(arg, p.segmentCounts);
          if (idx) p.spin(idx);
        }
        break;
      }
      case '!bus':
        if (this.named) this.bus.handle(arg.trim(), this.store.get().BusActive);
        break;
      case '!songtitle': {
        const st = this.store.get();
        if (this.radio.isPlaying) this.song.show(st.RadioStationIcon, st.SongTitle);
        break;
      }
      case '!urgent':
        if (this.named) {
          try {
            this.urgent.show(JSON.parse(arg) as UrgentPayload);
          } catch {
            log.warn('Toast mit ungültigem JSON verworfen');
          }
        }
        break;
      default:
        log.debug(`Befehl nicht behandelt: ${command}`);
    }
  }

  // ---- Ansichten ----------------------------------------------------------------------------------------

  private async showPage(token: string): Promise<void> {
    if (this.youtube) return; // Legacy: während YouTube keine Seitenwechsel
    const seq = ++this.requestSeq;
    this.views.abortAllExcept(token);
    this.critters.reroll();
    const numeric = /^\d+$/.test(token);
    if (numeric) {
      this.currentPage = token;
      this.topbar.setActivePage(token, this.pageTitle(token));
    } else {
      this.topbar.setActivePage(null, token === 'FUN' ? 'Fun Mode' : token === 'WHEEL' ? 'Glücksrad' : token);
    }

    if (this.pendingHeapReload && (await this.requestReload('heap'))) return;

    if (token === 'WHEEL') {
      this.shownToken = token;
      this.shownHtml = null;
      await this.host.show(
        () => new WheelPanel(this.ctx),
        (err) => new MessagePanel('WHEEL', this.ctx, 'error', 'Glücksrad nicht verfügbar', String(err)),
      );
      return;
    }

    let result;
    try {
      result = await this.views.load(token);
    } catch (err) {
      if (seq !== this.requestSeq) return;
      this.metrics.failedApiCalls++;
      log.warn(`Ansicht ${token} nicht ladbar: ${String(err)}`);
      if (this.shownToken !== null && this.host.active && !(this.host.active instanceof MessagePanel)) {
        // Letzte Ansicht stehen lassen, nur als veraltet markieren.
        this.topbar.setFreshness(this.host.active.dataTimestamp, true);
        return;
      }
      this.shownToken = token;
      this.shownHtml = null;
      await this.host.show(
        () => new MessagePanel(token, this.ctx, 'error', `${this.pageTitle(token)}: Daten nicht verfügbar`, 'Neuer Versuch beim nächsten Seitenwechsel.'),
        () => new MessagePanel(token, this.ctx, 'error', 'Daten nicht verfügbar'),
      );
      return;
    }
    if (seq !== this.requestSeq) return;
    if (result.stale) this.metrics.failedApiCalls++;

    // Gleiche Seite, gleiche Daten → nicht neu rendern (z. B. `!repeat` nach Reconnect).
    if (token === this.shownToken && result.view.html === this.shownHtml && this.host.active) {
      this.topbar.setFreshness(result.view.fetchedAt, result.stale);
      return;
    }
    this.shownToken = token;
    this.shownHtml = result.view.html;
    const view = result.view;
    await this.host.show(
      () => new FragmentPanel(token, this.ctx, { html: view.html, legacy: this.legacy, fetchedAt: view.fetchedAt, stale: result.stale }),
      (err) => new MessagePanel(token, this.ctx, 'error', `${this.pageTitle(token)} konnte nicht dargestellt werden`, String(err)),
    );
  }

  private playYouTube(arg: string): void {
    const target = parseYouTube(arg);
    if (!target) {
      log.warn('Keine gültige YouTube-Video-ID gefunden');
      return;
    }
    this.youtube = true;
    this.shownToken = 'YT';
    this.shownHtml = null;
    this.topbar.setActivePage(null, 'YouTube');
    this.applyRadio();
    void this.host.show(
      () =>
        new YouTubePanel(this.ctx, {
          target,
          volume: this.store.get().YTvolume,
          onDone: () => {
            this.youtube = false;
            this.applyRadio();
            void this.showPage(this.currentPage ?? String(this.store.get().Page ?? 1));
          },
        }),
      () => new MessagePanel('YT', this.ctx, 'error', 'Video nicht verfügbar'),
    );
  }

  private pageTitle(id: string): string {
    return this.cfg.pages.find((p) => p.id === id)?.title ?? `Seite ${id}`;
  }

  // ---- Status-Updates -----------------------------------------------------------------------------------

  private bindStore(): void {
    const s = this.store;
    s.subscribe(['CD', 'Cycle'], (st) => {
      this.topbar.setCountdown(st.CD, st.Cycle);
      this.maybePrefetch(st);
    });
    s.subscribe(['PageStatus'], (st) => this.topbar.setPageStatus(st.PageStatus));
    s.subscribe(['Cat', 'Page'], (st) => this.critters.apply(st.Cat, st.Page));
    s.subscribe(['Radio', 'Radiostation', 'Radiovolume'], () => this.applyRadio());
    s.subscribe(['YTvolume'], (st) => {
      if (this.youtube) this.host.active?.update(st.YTvolume);
    });
    s.subscribe(['SongTitle'], (st) => {
      if (st.SongTitle !== this.lastSong) {
        this.lastSong = st.SongTitle;
        if (this.radio.isPlaying && st.SongTitle) this.song.show(st.RadioStationIcon, st.SongTitle);
      }
    });
    s.subscribe(['Snow'], (st) => this.snow.set(st.Snow));
    s.subscribe(['Spooky'], (st) => this.spooky.setMode(st.Spooky));
    s.subscribe(['ws'], (st) => this.onWsState(st.ws));
  }

  private applyRadio(): void {
    const st = this.store.get();
    this.radio.apply(st.Radio && !this.youtube && this.named, st.Radiostation, st.Radiovolume);
  }

  /** Nächste Seite wie der Server vorhersagen (server.php:263-300) und kurz vor dem Wechsel vorladen. */
  private maybePrefetch(st: Readonly<AppState>): void {
    if (!st.Cycle || st.CD > this.cfg.prefetchLeadSeconds || st.Page === null || this.youtube) return;
    const next = st.FUN ? 'FUN' : predictNextPage(st.Page, st.PageStatus, this.cfg.pages.length);
    const key = `${st.Page}->${next}`;
    if (this.prefetchedFor === key) return;
    this.prefetchedFor = key;
    if (next !== 'FUN' || this.named) this.views.prefetch(next);
  }

  // ---- Health & Recovery ----------------------------------------------------------------------------------

  private onWsState(s: WsState): void {
    if (s === 'open') {
      this.topbar.setHealth('ok', 'Live');
      this.banner.hide();
    } else if (s === 'connecting') {
      this.topbar.setHealth('degraded', 'Verbinde …');
    } else {
      this.topbar.setHealth('down', 'Offline');
    }
  }

  private healthCheck(): void {
    const ws = this.ws.state;
    const downFor = ws === 'open' ? 0 : Date.now() - this.ws.stats.disconnectedSince;
    if (ws !== 'open' && downFor > 5000 && this.cfg.wsUrl) {
      this.banner.show('Keine Verbindung zum Dashboard-Server – Anzeige zeigt den letzten Stand', this.lastOkAt);
    }
    const rec = this.cfg.recovery;
    if (rec.enabled && ws !== 'open' && downFor > rec.wsDownMs) void this.requestReload('ws-down');
    if (rec.heapLimitMb > 0) {
      const mem = (performance as Performance & { memory?: { usedJSHeapSize: number } }).memory;
      if (mem && mem.usedJSHeapSize > rec.heapLimitMb * 1024 * 1024) this.pendingHeapReload = true;
    }
  }

  /**
   * Reload nur, wenn (a) der Reload-Schutz es erlaubt und (b) der Webserver erreichbar ist –
   * sonst bliebe Chromium auf einer Fehlerseite stehen.
   */
  private async requestReload(reason: ReloadReason): Promise<boolean> {
    if (!this.guard.canReload(reason)) {
      log.warn(`Reload (${reason}) unterdrückt: Limit erreicht`);
      return false;
    }
    if (reason !== 'remote' && !(await this.serverReachable())) return false;
    this.pendingHeapReload = false;
    log.warn(`Kontrollierter Reload: ${reason}`);
    return this.guard.reload(reason);
  }

  private async serverReachable(): Promise<boolean> {
    const ctrl = new AbortController();
    const t = setTimeout(() => ctrl.abort(), 5000);
    try {
      const res = await fetch(location.href, { method: 'GET', cache: 'no-store', signal: ctrl.signal });
      return res.ok;
    } catch {
      return false;
    } finally {
      clearTimeout(t);
    }
  }
}

/**
 * Nachbildung von server.php: Page 10 → 1, sonst +1; Seiten mit hasData=false (PageStatus „ok“) werden
 * übersprungen, maximal 10 Versuche.
 */
export function predictNextPage(page: number, status: Record<string, string>, pageCount = 10): string {
  let p = page >= pageCount ? 1 : page + 1;
  for (let i = 0; i < pageCount && status[String(p)] === 'ok'; i++) p = p >= pageCount ? 1 : p + 1;
  return String(p);
}

export type { Panel };
