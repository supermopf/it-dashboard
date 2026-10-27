/**
 * Minimaler State Store.
 *
 * - `patch()` übernimmt nur tatsächlich geänderte Felder (flacher Vergleich, für Objekte per JSON-Vergleich),
 * - Listener werden pro Feld registriert und gebündelt höchstens einmal pro Animation Frame benachrichtigt,
 * - bei verborgenem Dokument feuert requestAnimationFrame nicht: Änderungen sammeln sich und werden beim
 *   Wiedereinblenden einmalig gerendert.
 */

export type Listener<S> = (state: Readonly<S>, changed: ReadonlySet<keyof S>) => void;

export type FrameRequest = (cb: () => void) => void;

const defaultFrame: FrameRequest = (cb) => {
  if (typeof requestAnimationFrame === 'function') requestAnimationFrame(() => cb());
  else setTimeout(cb, 16);
};

function sameValue(a: unknown, b: unknown): boolean {
  if (Object.is(a, b)) return true;
  if (a && b && typeof a === 'object' && typeof b === 'object') {
    try {
      return JSON.stringify(a) === JSON.stringify(b);
    } catch {
      return false;
    }
  }
  return false;
}

export class Store<S extends object> {
  private state: S;
  private pending = new Set<keyof S>();
  private scheduled = false;
  private subs = new Set<{ keys: ReadonlySet<keyof S> | null; fn: Listener<S> }>();
  /** Anzahl der übernommenen bzw. verworfenen Patches (Debug). */
  readonly stats = { applied: 0, deduped: 0, fieldsSkipped: 0, flushes: 0 };

  constructor(initial: S, private readonly frame: FrameRequest = defaultFrame) {
    this.state = { ...initial };
  }

  get(): Readonly<S> {
    return this.state;
  }

  patch(partial: Partial<S>): void {
    let changed = false;
    for (const key of Object.keys(partial) as Array<keyof S>) {
      const next = partial[key] as S[keyof S];
      if (sameValue(this.state[key], next)) {
        this.stats.fieldsSkipped++;
        continue;
      }
      this.state = { ...this.state, [key]: next };
      this.pending.add(key);
      changed = true;
    }
    if (changed) {
      this.stats.applied++;
      this.schedule();
    } else {
      this.stats.deduped++;
    }
  }

  /**
   * Listener für bestimmte Felder (oder alle, wenn `keys` leer ist).
   * Gibt eine Abmeldefunktion zurück.
   */
  subscribe(keys: Array<keyof S> | null, fn: Listener<S>): () => void {
    const entry = { keys: keys && keys.length ? new Set(keys) : null, fn };
    this.subs.add(entry);
    return () => this.subs.delete(entry);
  }

  get listenerCount(): number {
    return this.subs.size;
  }

  /** Sofort benachrichtigen (Tests, Initialisierung). */
  flush(): void {
    this.scheduled = false;
    if (this.pending.size === 0) return;
    const changed = this.pending;
    this.pending = new Set();
    this.stats.flushes++;
    for (const sub of [...this.subs]) {
      if (sub.keys && ![...changed].some((k) => sub.keys!.has(k))) continue;
      try {
        sub.fn(this.state, changed);
      } catch (err) {
        console.error('[store] listener failed', err);
      }
    }
  }

  private schedule(): void {
    if (this.scheduled) return;
    this.scheduled = true;
    this.frame(() => this.flush());
  }
}
