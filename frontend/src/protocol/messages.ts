/**
 * Nachrichtenformate von websocket/server.php (unverändert übernommen, siehe docs/AUDIT.md §2).
 * Booleans kommen als Strings "true"/"false".
 */

export type PageStatusValue = 'warning' | 'ok';

export interface ServerUpdate {
  Page: number | null;
  FUN: boolean;
  Wheel: boolean;
  Radio: boolean;
  Radiostation: string;
  Radiovolume: number;
  YTvolume: number;
  Cat: boolean;
  Snow: boolean;
  BusActive: boolean;
  CD: number;
  Cycle: boolean;
  SongTitle: string;
  RadioStationIcon: string;
  PageStatus: Record<string, PageStatusValue>;
}

export type ServerMessage =
  | { type: 'update'; update: ServerUpdate }
  | { type: 'command'; command: string; arg: string; raw: string }
  | { type: 'console'; message: string }
  | { type: 'auth' }
  | { type: 'other'; raw: unknown };

const bool = (v: unknown): boolean => v === true || v === 'true' || v === 'True' || v === 1 || v === '1';

function num(v: unknown, fallback: number, min = -Infinity, max = Infinity): number {
  const n = typeof v === 'number' ? v : typeof v === 'string' && v.trim() !== '' ? Number(v) : NaN;
  if (!Number.isFinite(n)) return fallback;
  return Math.min(max, Math.max(min, n));
}

const str = (v: unknown): string => (typeof v === 'string' ? v : v == null ? '' : String(v));

export function parseUpdate(m: Record<string, unknown>): ServerUpdate {
  const status: Record<string, PageStatusValue> = {};
  if (m.PageStatus && typeof m.PageStatus === 'object') {
    for (const [k, v] of Object.entries(m.PageStatus as Record<string, unknown>)) {
      if (/^\d{1,2}$/.test(k) && (v === 'warning' || v === 'ok')) status[k] = v;
    }
  }
  const page = num(m.Page, NaN);
  return {
    Page: Number.isFinite(page) ? page : null,
    FUN: bool(m.FUN),
    Wheel: bool(m.Wheel),
    Radio: bool(m.Radio),
    Radiostation: str(m.Radiostation),
    Radiovolume: num(m.Radiovolume, 0.01, 0, 1),
    YTvolume: num(m.YTvolume, 0.01, 0, 1),
    Cat: bool(m.Cat),
    Snow: bool(m.Snow),
    BusActive: bool(m.BusActive),
    CD: num(m.CD, 0, 0, 3600),
    Cycle: bool(m.Cycle),
    SongTitle: str(m.SongTitle).slice(0, 300),
    RadioStationIcon: str(m.RadioStationIcon),
    PageStatus: status,
  };
}

/** Parst eine Rohnachricht; wirft nie. */
export function parseMessage(raw: string): ServerMessage | null {
  let m: unknown;
  try {
    m = JSON.parse(raw);
  } catch {
    return null;
  }
  if (!m || typeof m !== 'object') return null;
  const o = m as Record<string, unknown>;
  switch (o.type) {
    case 'update':
      return { type: 'update', update: parseUpdate(o) };
    case 'command': {
      const message = str(o.message);
      const sp = message.indexOf(' ');
      // Legacy: Befehl = Text bis zum ersten Leerzeichen (app.js nutzt substring(0, indexOf(' '))).
      const command = sp === -1 ? message : message.slice(0, sp);
      const arg = sp === -1 ? '' : message.slice(sp + 1);
      return { type: 'command', command, arg, raw: message };
    }
    case 'console':
      return { type: 'console', message: str(o.message) };
    case 'auth':
      return { type: 'auth' };
    default:
      return { type: 'other', raw: m };
  }
}

/** Seitentoken aus `!page X` – Zahlen oder Spezialseiten (FUN, WHEEL, …). */
export function parsePageToken(arg: string): string | null {
  const t = arg.trim();
  return /^[A-Za-z0-9_]{1,16}$/.test(t) ? t : null;
}

/** `!roll a b c` – Werte vom Server sind 1…20 (rand(1,20)), das Rad nutzt 0-basierte Indizes. */
export function parseRoll(arg: string, segments: number[]): [number, number, number] | null {
  const parts = arg.trim().split(/\s+/).map((p) => Number.parseInt(p, 10));
  if (parts.length < 3 || parts.some((p) => !Number.isFinite(p))) return null;
  const out = parts.slice(0, 3).map((v, i) => {
    const n = Math.max(1, segments[i] ?? 1);
    // 1-basiert → 0-basiert, Werte außerhalb werden in den gültigen Bereich gefaltet (siehe AUDIT P28).
    return (((v - 1) % n) + n) % n;
  });
  return out as [number, number, number];
}
