import type { Scheduler, TimerHandle, TimerOptions } from './scheduler';

/**
 * Lebensdauer-Container. Sammelt alles, was beim Deaktivieren bzw. Zerstören
 * wieder entfernt werden muss: Event-Listener, Timer, Object-URLs, beliebige Aufräumfunktionen.
 */
export class Scope {
  private cleanups: Array<() => void> = [];
  private disposed = false;
  private listenerCount = 0;

  constructor(private readonly scheduler: Scheduler) {}

  get isDisposed(): boolean {
    return this.disposed;
  }

  get listeners(): number {
    return this.listenerCount;
  }

  listen<K extends keyof HTMLElementEventMap>(
    target: HTMLElement,
    type: K,
    fn: (ev: HTMLElementEventMap[K]) => void,
    opts?: AddEventListenerOptions,
  ): void;
  listen(target: EventTarget, type: string, fn: (ev: Event) => void, opts?: AddEventListenerOptions): void;
  listen(target: EventTarget, type: string, fn: (ev: never) => void, opts?: AddEventListenerOptions): void {
    if (this.disposed) return;
    const handler = fn as unknown as EventListener;
    target.addEventListener(type, handler, opts);
    this.listenerCount++;
    this.cleanups.push(() => {
      target.removeEventListener(type, handler, opts);
      this.listenerCount--;
    });
  }

  timeout(fn: () => void, ms: number, opts?: TimerOptions): TimerHandle {
    return this.track(this.scheduler.timeout(fn, ms, this, opts));
  }

  interval(fn: () => void, ms: number, opts?: TimerOptions): TimerHandle {
    return this.track(this.scheduler.interval(fn, ms, this, opts));
  }

  objectUrl(blob: Blob): string {
    const url = URL.createObjectURL(blob);
    this.add(() => URL.revokeObjectURL(url));
    return url;
  }

  add(fn: () => void): void {
    if (this.disposed) {
      fn();
      return;
    }
    this.cleanups.push(fn);
  }

  /** Alles aufräumen. Der Scope bleibt danach unbenutzbar. */
  dispose(): void {
    if (this.disposed) return;
    this.disposed = true;
    this.runCleanups();
    this.scheduler.clearOwner(this);
  }

  /** Aufräumen, Scope aber weiterverwenden (z. B. bei deactivate()). */
  reset(): void {
    this.runCleanups();
    this.scheduler.clearOwner(this);
  }

  private track(h: TimerHandle): TimerHandle {
    return h;
  }

  private runCleanups(): void {
    const list = this.cleanups;
    this.cleanups = [];
    for (let i = list.length - 1; i >= 0; i--) {
      try {
        list[i]!();
      } catch (err) {
        console.error('[scope] cleanup failed', err);
      }
    }
  }
}
