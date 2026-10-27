import { backoffDelay, type BackoffOptions } from './backoff';
import type { Scheduler, TimerHandle } from './scheduler';

/**
 * Genau eine WebSocket-Verbindung pro App.
 *
 * - Reconnect mit exponentiellem Backoff + Jitter, unbegrenzt (kein alert(), kein Abbruch nach N Versuchen)
 * - garantiert keine parallelen Verbindungs- oder Reconnect-Schleifen (ein Socket, ein Reconnect-Timer)
 * - Watchdog: kommt länger als `watchdogMs` keine Nachricht (Server sendet jede Sekunde `update`),
 *   gilt die Verbindung als halb-offen und wird neu aufgebaut
 */

export type WsState = 'idle' | 'connecting' | 'open' | 'closed';

export interface WsClientOptions {
  url: string;
  scheduler: Scheduler;
  backoff?: Partial<BackoffOptions>;
  watchdogMs?: number;
  connectTimeoutMs?: number;
  WebSocketImpl?: typeof WebSocket;
  onOpen?: () => void;
  onMessage?: (data: string) => void;
  onStateChange?: (state: WsState) => void;
}

export interface WsStats {
  connects: number;
  reconnects: number;
  messages: number;
  activeSockets: number;
  lastMessageAt: number;
  lastOpenAt: number;
  disconnectedSince: number;
}

export class WsClient {
  private ws: WebSocket | null = null;
  private stateValue: WsState = 'idle';
  private attempt = 0;
  private reconnectTimer: TimerHandle | null = null;
  private watchdog: TimerHandle | null = null;
  private connectTimer: TimerHandle | null = null;
  private stopped = false;
  /** true ab der ersten Servernachricht einer Verbindung. */
  private healthy = false;
  private readonly backoff: BackoffOptions;
  private readonly Impl: typeof WebSocket;
  readonly stats: WsStats = {
    connects: 0,
    reconnects: 0,
    messages: 0,
    activeSockets: 0,
    lastMessageAt: 0,
    lastOpenAt: 0,
    disconnectedSince: Date.now(),
  };

  constructor(private readonly o: WsClientOptions) {
    this.backoff = { baseMs: 1000, maxMs: 30000, minMs: 500, ...o.backoff };
    this.Impl = o.WebSocketImpl ?? WebSocket;
  }

  get state(): WsState {
    return this.stateValue;
  }

  start(): void {
    this.stopped = false;
    this.connect();
  }

  stop(): void {
    this.stopped = true;
    this.clearTimers();
    this.teardown();
    this.healthy = false;
    this.setState('closed');
  }

  /** Sofortiger neuer Versuch, z. B. nach `online`-Event – ohne zweite Schleife. */
  reconnectNow(): void {
    if (this.stopped || this.stateValue === 'open' || this.stateValue === 'connecting') return;
    this.reconnectTimer?.cancel();
    this.reconnectTimer = null;
    this.connect();
  }

  send(message: string): boolean {
    if (!this.ws || this.ws.readyState !== this.Impl.OPEN) return false;
    this.ws.send(JSON.stringify({ message }));
    return true;
  }

  private connect(): void {
    if (this.stopped || this.ws) return;
    this.setState('connecting');
    let socket: WebSocket;
    try {
      socket = new this.Impl(this.o.url);
    } catch {
      this.scheduleReconnect();
      return;
    }
    this.ws = socket;
    this.stats.activeSockets = 1;
    const timeout = this.o.connectTimeoutMs ?? 10000;
    this.connectTimer = this.o.scheduler.timeout(
      () => {
        if (this.ws === socket && socket.readyState !== this.Impl.OPEN) this.handleClose(socket);
      },
      timeout,
      this,
      { pauseWhenHidden: false, label: 'ws-connect-timeout' },
    );

    socket.onopen = () => {
      if (this.ws !== socket) return;
      this.connectTimer?.cancel();
      this.connectTimer = null;
      this.stats.lastOpenAt = Date.now();
      // Watchdog greift auch, wenn nach dem Handshake keine einzige Nachricht kommt.
      this.armWatchdog();
      this.o.onOpen?.();
    };
    socket.onmessage = (ev: MessageEvent) => {
      if (this.ws !== socket) return;
      this.stats.messages++;
      this.stats.lastMessageAt = Date.now();
      // Erst die erste Servernachricht macht die Verbindung „gesund“: Ein Server, der direkt nach dem
      // Handshake trennt (z. B. Verbindungslimit pro IP), setzt so weder Backoff noch Offline-Dauer zurück.
      if (!this.healthy) {
        this.healthy = true;
        this.attempt = 0;
        if (this.stats.connects > 0) this.stats.reconnects++;
        this.stats.connects++;
        this.setState('open');
      }
      this.armWatchdog();
      if (typeof ev.data === 'string') this.o.onMessage?.(ev.data);
    };
    socket.onerror = () => {
      // onclose folgt; dort wird der Reconnect geplant.
    };
    socket.onclose = () => this.handleClose(socket);
  }

  private handleClose(socket: WebSocket): void {
    if (this.ws !== socket) return;
    this.teardown();
    if (this.healthy) this.stats.disconnectedSince = Date.now();
    this.healthy = false;
    this.setState('closed');
    this.scheduleReconnect();
  }

  private scheduleReconnect(): void {
    if (this.stopped || this.reconnectTimer) return;
    const delay = backoffDelay(this.attempt++, this.backoff);
    this.reconnectTimer = this.o.scheduler.timeout(
      () => {
        this.reconnectTimer = null;
        this.connect();
      },
      delay,
      this,
      // Verbindung auch bei verborgenem Tab halten: Befehle (Toast, Reload) müssen ankommen.
      { pauseWhenHidden: false, label: 'ws-reconnect' },
    );
  }

  private armWatchdog(): void {
    const ms = this.o.watchdogMs ?? 10000;
    if (ms <= 0) return;
    this.watchdog?.cancel();
    this.watchdog = this.o.scheduler.timeout(
      () => {
        const s = this.ws;
        if (s) this.handleClose(s);
      },
      ms,
      this,
      { pauseWhenHidden: false, label: 'ws-watchdog' },
    );
  }

  private teardown(): void {
    this.watchdog?.cancel();
    this.watchdog = null;
    this.connectTimer?.cancel();
    this.connectTimer = null;
    const s = this.ws;
    this.ws = null;
    this.stats.activeSockets = 0;
    if (!s) return;
    s.onopen = null;
    s.onmessage = null;
    s.onerror = null;
    s.onclose = null;
    try {
      s.close();
    } catch {
      /* bereits geschlossen */
    }
  }

  private clearTimers(): void {
    this.reconnectTimer?.cancel();
    this.reconnectTimer = null;
  }

  private setState(s: WsState): void {
    if (s === this.stateValue) return;
    this.stateValue = s;
    this.o.onStateChange?.(s);
  }
}
