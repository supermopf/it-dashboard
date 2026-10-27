import { vi } from 'vitest';
import { DEFAULT_CONFIG, type DashboardConfig } from '../../src/config';
import { Logger } from '../../src/core/log';
import { Scheduler, type Clock } from '../../src/core/scheduler';
import { MediaManager } from '../../src/media/media-manager';
import type { PanelContext } from '../../src/panels/panel';

/** jsdom implementiert Medienwiedergabe nicht – minimale, zustandsbehaftete Attrappe. */
export function installMediaStubs(): void {
  const paused = new WeakMap<HTMLMediaElement, boolean>();
  Object.defineProperty(HTMLMediaElement.prototype, 'paused', {
    configurable: true,
    get(this: HTMLMediaElement) {
      return paused.get(this) ?? true;
    },
  });
  Object.defineProperty(HTMLMediaElement.prototype, 'play', {
    configurable: true,
    value: vi.fn(function (this: HTMLMediaElement) {
      paused.set(this, false);
      this.dispatchEvent(new Event('play'));
      return Promise.resolve();
    }),
  });
  Object.defineProperty(HTMLMediaElement.prototype, 'pause', {
    configurable: true,
    value: vi.fn(function (this: HTMLMediaElement) {
      paused.set(this, true);
    }),
  });
  Object.defineProperty(HTMLMediaElement.prototype, 'load', {
    configurable: true,
    value: vi.fn(function (this: HTMLMediaElement) {
      paused.set(this, true);
    }),
  });
}

/** Uhr, die mit vi.useFakeTimers() zusammenarbeitet. */
export const fakeClock: Clock = {
  setTimeout: (fn, ms) => setTimeout(fn, ms),
  clearTimeout: (h) => clearTimeout(h),
  now: () => Date.now(),
};

export function makeCtx(overrides: Partial<DashboardConfig> = {}): PanelContext & { scheduler: Scheduler; media: MediaManager; sent: string[] } {
  const sent: string[] = [];
  const scheduler = new Scheduler(fakeClock);
  const media = new MediaManager(overrides.maxActiveVideos ?? 1);
  return {
    config: { ...DEFAULT_CONFIG, ...overrides },
    scheduler,
    media,
    log: new Logger(50),
    send: (m: string) => {
      sent.push(m);
      return true;
    },
    asset: (p: string) => new URL(p, 'http://dash.test/monitor/').href,
    sent,
  };
}

/** Steuerbarer WebSocket-Ersatz. */
export class FakeWebSocket {
  static CONNECTING = 0;
  static OPEN = 1;
  static CLOSING = 2;
  static CLOSED = 3;
  static instances: FakeWebSocket[] = [];
  readyState = 0;
  sent: string[] = [];
  onopen: ((ev: Event) => void) | null = null;
  onclose: ((ev: Event) => void) | null = null;
  onerror: ((ev: Event) => void) | null = null;
  onmessage: ((ev: MessageEvent) => void) | null = null;
  constructor(readonly url: string) {
    FakeWebSocket.instances.push(this);
  }
  send(d: string): void {
    this.sent.push(d);
  }
  close(): void {
    if (this.readyState === 3) return;
    this.readyState = 3;
    this.onclose?.(new Event('close'));
  }
  // Teststeuerung
  open(): void {
    this.readyState = 1;
    this.onopen?.(new Event('open'));
  }
  message(data: unknown): void {
    this.onmessage?.(new MessageEvent('message', { data: typeof data === 'string' ? data : JSON.stringify(data) }));
  }
  fail(): void {
    this.readyState = 3;
    this.onerror?.(new Event('error'));
    this.onclose?.(new Event('close'));
  }
  static get live(): FakeWebSocket[] {
    return FakeWebSocket.instances.filter((s) => s.readyState === 0 || s.readyState === 1);
  }
  static reset(): void {
    FakeWebSocket.instances = [];
  }
}
