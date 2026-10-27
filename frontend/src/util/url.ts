/**
 * Validierung von Medien-URLs (ToastPicture, ToastSound, Radiosender, Sender-Icons).
 * Erlaubt: http(s) absolut, relative Pfade, keine javascript:/data:/blob:-URLs.
 */
export function safeMediaUrl(raw: string, base: string = defaultBase()): string | null {
  const s = (raw ?? '').trim();
  if (!s || s.length > 2048) return null;
  // eslint-disable-next-line no-control-regex -- Steuerzeichen in URLs gezielt ablehnen
  if (/[\u0000-\u001f]/.test(s)) return null;
  let u: URL;
  try {
    u = new URL(s, base);
  } catch {
    return null;
  }
  if (u.protocol !== 'http:' && u.protocol !== 'https:') return null;
  return u.href;
}

export type MediaKind = 'video' | 'image';

/** Legacy-Regel aus monitor/js/app.js: enthält die URL „mp4“ oder „webm“, ist es ein Video. */
export function mediaKind(url: string): MediaKind {
  return url.includes('mp4') || url.includes('webm') ? 'video' : 'image';
}

function defaultBase(): string {
  return typeof location !== 'undefined' ? location.href : 'http://localhost/';
}
