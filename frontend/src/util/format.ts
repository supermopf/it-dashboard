/** Formatierung für die Anzeige aus mehreren Metern Entfernung. */

const nf = new Intl.NumberFormat('de-DE');

/** Große Zahlen kompakt (12.345 → „12.345“, 1.234.567 → „1,2 Mio.“), ungültige Werte → „–“. */
export function formatNumber(v: unknown): string {
  const n = typeof v === 'number' ? v : typeof v === 'string' && v.trim() !== '' ? Number(v) : NaN;
  if (!Number.isFinite(n)) return '–';
  const abs = Math.abs(n);
  if (abs >= 1e9) return `${trim1(n / 1e9)} Mrd.`;
  if (abs >= 1e6) return `${trim1(n / 1e6)} Mio.`;
  return nf.format(Math.round(n * 10) / 10);
}

function trim1(n: number): string {
  return new Intl.NumberFormat('de-DE', { maximumFractionDigits: 1 }).format(n);
}

export function pad2(n: number): string {
  return n < 10 ? `0${n}` : String(n);
}

export function formatClock(d: Date): string {
  return `${pad2(d.getHours())}:${pad2(d.getMinutes())}:${pad2(d.getSeconds())}`;
}

export function formatTime(ts: number): string {
  const d = new Date(ts);
  return `${pad2(d.getHours())}:${pad2(d.getMinutes())}`;
}

/** „vor 3 min“ usw. */
export function formatAge(ms: number): string {
  if (!Number.isFinite(ms) || ms < 0) return '–';
  const s = Math.round(ms / 1000);
  if (s < 60) return `vor ${s} s`;
  const m = Math.round(s / 60);
  if (m < 60) return `vor ${m} min`;
  const h = Math.round(m / 60);
  return h < 48 ? `vor ${h} h` : `vor ${Math.round(h / 24)} Tagen`;
}

/** Text auf eine maximale Länge kürzen (zusätzlich zur CSS-Ellipse, damit das DOM klein bleibt). */
export function clampText(s: string, max: number): string {
  const t = (s ?? '').trim();
  return t.length > max ? `${t.slice(0, max - 1)}…` : t;
}
