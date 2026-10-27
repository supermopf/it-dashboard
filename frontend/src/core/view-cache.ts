import { fetchText, HttpError } from './http';

/**
 * Lädt Ansichtsdaten (HTML-Fragmente aus monitor/cache/) und hält die letzte gültige Version vor.
 * - bedingte Requests (304) sparen Übertragung und Parsing
 * - ungültige Antworten (leer, PHP-/SQL-Fehlerdump) ersetzen nie eine gültige Version
 * - die letzte gültige Version wird zusätzlich in localStorage gesichert (begrenzt), damit nach einem
 *   Recovery-Reload ohne Server sofort etwas angezeigt werden kann
 */

export interface CachedView {
  html: string;
  fetchedAt: number;
  lastModified: string | null;
  etag: string | null;
}

export interface LoadResult {
  view: CachedView;
  /** true = Daten stammen aus dem Cache, weil der Abruf fehlschlug */
  stale: boolean;
  error?: string;
}

const STORAGE_PREFIX = 'dashboard.view.';
const MAX_STORED_BYTES = 400_000;
const MAX_MEMORY_ENTRIES = 16;

/** Erkennt Fehlerausgaben der PHP-Seiten (`die(print_r(sqlsrv_errors()))`, Fatal Errors). */
export function looksInvalid(html: string): string | null {
  const t = html.trim();
  if (t.length === 0) return 'leere Antwort';
  if (/^<pre>\s*Array\s*\(/i.test(t) || /\[SQLSTATE\]\s*=>/i.test(t)) return 'Datenbankfehler';
  if (/<b>(Fatal error|Parse error)<\/b>:/i.test(t) || /^(PHP )?Fatal error:/im.test(t)) return 'PHP-Fehler';
  return null;
}

export class ViewCache {
  private mem = new Map<string, CachedView>();
  private inflight = new Map<string, { promise: Promise<LoadResult>; ctrl: AbortController }>();
  readonly stats = { loads: 0, failures: 0, notModified: 0, staleServed: 0 };

  constructor(
    private readonly urlFor: (page: string) => string,
    private readonly timeoutMs: number,
    private readonly storage: Storage | null = safeStorage(),
  ) {}

  peek(page: string): CachedView | null {
    return this.mem.get(page) ?? this.restore(page);
  }

  /** Lädt eine Ansicht; gleichzeitige Aufrufe für dieselbe Seite teilen sich einen Request. */
  load(page: string): Promise<LoadResult> {
    const existing = this.inflight.get(page);
    if (existing) return existing.promise;
    const ctrl = new AbortController();
    const promise = this.doLoad(page, ctrl.signal).finally(() => this.inflight.delete(page));
    this.inflight.set(page, { promise, ctrl });
    return promise;
  }

  /** Vorladen (nur Netzwerk + Cache, kein Rendern). Fehler werden ignoriert. */
  prefetch(page: string): void {
    this.load(page).catch(() => undefined);
  }

  abortAllExcept(page: string | null): void {
    for (const [p, f] of this.inflight) if (p !== page) f.ctrl.abort();
  }

  private async doLoad(page: string, signal: AbortSignal): Promise<LoadResult> {
    this.stats.loads++;
    const prev = this.peek(page);
    try {
      const res = await fetchText(this.urlFor(page), {
        timeoutMs: this.timeoutMs,
        signal,
        etag: prev?.etag ?? null,
        lastModified: prev?.lastModified ?? null,
      });
      if (res.text === null && prev) {
        this.stats.notModified++;
        const view = { ...prev, fetchedAt: Date.now() };
        this.remember(page, view, false);
        return { view, stale: false };
      }
      const html = res.text ?? '';
      const invalid = looksInvalid(html);
      if (invalid) throw new HttpError(`Ungültige Daten (${invalid})`, res.status, 'invalid');
      const view: CachedView = { html, fetchedAt: Date.now(), lastModified: res.lastModified, etag: res.etag };
      this.remember(page, view, true);
      return { view, stale: false };
    } catch (err) {
      this.stats.failures++;
      const msg = err instanceof Error ? err.message : String(err);
      if (err instanceof HttpError && err.kind === 'abort') throw err;
      if (prev) {
        this.stats.staleServed++;
        return { view: prev, stale: true, error: msg };
      }
      throw err;
    }
  }

  private remember(page: string, view: CachedView, persist: boolean): void {
    this.mem.delete(page);
    this.mem.set(page, view);
    while (this.mem.size > MAX_MEMORY_ENTRIES) {
      const oldest = this.mem.keys().next().value as string;
      this.mem.delete(oldest);
    }
    if (persist && this.storage && view.html.length <= MAX_STORED_BYTES) {
      try {
        this.storage.setItem(STORAGE_PREFIX + page, JSON.stringify(view));
      } catch {
        // Kontingent voll: diese Seite nicht persistieren.
        try {
          this.storage.removeItem(STORAGE_PREFIX + page);
        } catch {
          /* ignorieren */
        }
      }
    }
  }

  private restore(page: string): CachedView | null {
    if (!this.storage) return null;
    try {
      const raw = this.storage.getItem(STORAGE_PREFIX + page);
      if (!raw) return null;
      const v = JSON.parse(raw) as CachedView;
      if (typeof v.html !== 'string' || looksInvalid(v.html)) return null;
      this.mem.set(page, v);
      return v;
    } catch {
      return null;
    }
  }
}

function safeStorage(): Storage | null {
  try {
    const s = window.localStorage;
    const k = '__dashboard_probe__';
    s.setItem(k, '1');
    s.removeItem(k);
    return s;
  } catch {
    return null;
  }
}
