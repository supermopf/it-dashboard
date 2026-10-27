/**
 * Kontrollierter Recovery-Reload mit Schutz gegen Reload-Schleifen.
 * Reloads werden mit Zeitstempel in localStorage protokolliert; innerhalb einer Stunde sind höchstens
 * `maxPerHour` automatische Reloads erlaubt. Remote angeforderte Reloads (Reload-Toast) haben ein eigenes,
 * großzügigeres Limit, damit „Alle neu laden“ funktioniert, aber keine Schleife entstehen kann.
 */

const KEY = 'dashboard.reloads';

export type ReloadReason = 'remote' | 'ws-down' | 'heap' | 'fatal';

export interface ReloadGuardOptions {
  maxPerHour: number;
  maxRemotePerHour?: number;
  storage?: Storage | null;
  now?: () => number;
  reload?: () => void;
}

interface Entry {
  t: number;
  r: ReloadReason;
}

export class ReloadGuard {
  private readonly storage: Storage | null;
  private readonly now: () => number;
  private readonly doReload: () => void;
  private memory: Entry[] = [];

  constructor(private readonly o: ReloadGuardOptions) {
    this.storage = o.storage === undefined ? safe() : o.storage;
    this.now = o.now ?? Date.now;
    this.doReload = o.reload ?? (() => location.reload());
  }

  recent(): Entry[] {
    const cutoff = this.now() - 3600_000;
    let list: Entry[] = this.memory;
    if (this.storage) {
      try {
        list = JSON.parse(this.storage.getItem(KEY) ?? '[]') as Entry[];
      } catch {
        list = [];
      }
    }
    return list.filter((e) => e && typeof e.t === 'number' && e.t > cutoff).slice(-50);
  }

  canReload(reason: ReloadReason): boolean {
    const list = this.recent();
    if (reason === 'remote') return list.filter((e) => e.r === 'remote').length < (this.o.maxRemotePerHour ?? 12);
    return list.filter((e) => e.r !== 'remote').length < this.o.maxPerHour;
  }

  /** Führt den Reload aus, wenn erlaubt. Gibt false zurück, wenn der Schutz greift. */
  reload(reason: ReloadReason): boolean {
    if (!this.canReload(reason)) return false;
    const list = [...this.recent(), { t: this.now(), r: reason }];
    this.memory = list;
    try {
      this.storage?.setItem(KEY, JSON.stringify(list));
    } catch {
      /* ignorieren */
    }
    this.doReload();
    return true;
  }
}

function safe(): Storage | null {
  try {
    return window.localStorage;
  } catch {
    return null;
  }
}
