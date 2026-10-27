/**
 * Zentraler Scheduler.
 *
 * Alle wiederkehrenden Arbeiten der App laufen hierüber. Dadurch
 *  - ist die Zahl aktiver Timer jederzeit bekannt (Debug-Modus),
 *  - lassen sich Timer pro Besitzer (Panel, Feature) gesammelt entfernen,
 *  - pausieren Timer mit `pauseWhenHidden` bei verborgenem Dokument und werden
 *    beim Wiedereinblenden genau einmal neu gestartet (keine Doppelregistrierung).
 */

export type TimerKind = 'timeout' | 'interval';

export interface TimerOptions {
  /** Timer bei `document.hidden` anhalten (Standard: true). */
  pauseWhenHidden?: boolean;
  /** Bei Intervallen auf ganze Vielfache der Periode ausrichten (z. B. Uhr auf Sekundengrenze). */
  align?: boolean;
  /** Bezeichnung für Debug-Ausgaben. */
  label?: string;
}

interface TimerEntry {
  id: number;
  kind: TimerKind;
  fn: () => void;
  ms: number;
  owner: object | null;
  opts: Required<Pick<TimerOptions, 'pauseWhenHidden' | 'align'>> & { label: string };
  handle: ReturnType<typeof setTimeout> | null;
  paused: boolean;
}

export interface TimerHandle {
  cancel(): void;
}

export interface Clock {
  setTimeout(fn: () => void, ms: number): ReturnType<typeof setTimeout>;
  clearTimeout(h: ReturnType<typeof setTimeout>): void;
  now(): number;
}

const realClock: Clock = {
  setTimeout: (fn, ms) => globalThis.setTimeout(fn, ms),
  clearTimeout: (h) => globalThis.clearTimeout(h),
  now: () => Date.now(),
};

export class Scheduler {
  private timers = new Map<number, TimerEntry>();
  private nextId = 1;
  private hidden = false;

  constructor(private readonly clock: Clock = realClock) {}

  /** Einmaliger Timer. */
  timeout(fn: () => void, ms: number, owner: object | null = null, opts: TimerOptions = {}): TimerHandle {
    return this.add('timeout', fn, ms, owner, opts);
  }

  /** Wiederkehrender Timer. Mindestperiode 250 ms (kein Dauer-Polling). */
  interval(fn: () => void, ms: number, owner: object | null = null, opts: TimerOptions = {}): TimerHandle {
    return this.add('interval', fn, Math.max(250, ms), owner, opts);
  }

  /** Alle Timer eines Besitzers entfernen. */
  clearOwner(owner: object): number {
    let n = 0;
    for (const t of [...this.timers.values()]) {
      if (t.owner === owner) {
        this.remove(t.id);
        n++;
      }
    }
    return n;
  }

  /** Sichtbarkeit setzen (wird von main.ts an `visibilitychange` gebunden). */
  setHidden(hidden: boolean): void {
    if (hidden === this.hidden) return;
    this.hidden = hidden;
    for (const t of this.timers.values()) {
      if (!t.opts.pauseWhenHidden) continue;
      if (hidden && !t.paused) {
        if (t.handle !== null) this.clock.clearTimeout(t.handle);
        t.handle = null;
        t.paused = true;
      } else if (!hidden && t.paused) {
        t.paused = false;
        this.arm(t);
      }
    }
  }

  get activeCount(): number {
    return this.timers.size;
  }

  /** Für Debug-Ausgaben: Anzahl pro Label. */
  describe(): Record<string, number> {
    const out: Record<string, number> = {};
    for (const t of this.timers.values()) out[t.opts.label] = (out[t.opts.label] ?? 0) + 1;
    return out;
  }

  dispose(): void {
    for (const id of [...this.timers.keys()]) this.remove(id);
  }

  private add(kind: TimerKind, fn: () => void, ms: number, owner: object | null, opts: TimerOptions): TimerHandle {
    const entry: TimerEntry = {
      id: this.nextId++,
      kind,
      fn,
      ms: Math.max(0, ms),
      owner,
      opts: {
        pauseWhenHidden: opts.pauseWhenHidden ?? true,
        align: opts.align ?? false,
        label: opts.label ?? kind,
      },
      handle: null,
      paused: false,
    };
    this.timers.set(entry.id, entry);
    if (this.hidden && entry.opts.pauseWhenHidden) entry.paused = true;
    else this.arm(entry);
    const id = entry.id;
    return { cancel: () => this.remove(id) };
  }

  private arm(t: TimerEntry): void {
    let delay = t.ms;
    if (t.kind === 'interval' && t.opts.align) {
      const now = this.clock.now();
      delay = t.ms - (now % t.ms);
      if (delay < 5) delay += t.ms;
    }
    t.handle = this.clock.setTimeout(() => this.fire(t.id), delay);
  }

  private fire(id: number): void {
    const t = this.timers.get(id);
    if (!t) return;
    t.handle = null;
    if (t.kind === 'timeout') this.timers.delete(id);
    try {
      t.fn();
    } catch (err) {
      console.error('[scheduler]', t.opts.label, err);
    }
    // Intervall nur neu armieren, wenn es während fn() nicht entfernt oder pausiert wurde.
    if (t.kind === 'interval' && this.timers.has(id) && !t.paused && t.handle === null) this.arm(t);
  }

  private remove(id: number): void {
    const t = this.timers.get(id);
    if (!t) return;
    if (t.handle !== null) this.clock.clearTimeout(t.handle);
    this.timers.delete(id);
  }
}
