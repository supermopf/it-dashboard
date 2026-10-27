/**
 * YouTube-URL → Video-ID und Startzeit.
 * Portierung der Logik aus monitor/cache/pageYT.php (Shorts, watch?v=, youtu.be/<id>, Parameter t).
 */
export interface YouTubeTarget {
  videoId: string;
  start: number;
}

const ID = /^[a-zA-Z0-9_-]{11}$/;

export function parseYouTube(raw: string): YouTubeTarget | null {
  const s = (raw ?? '').trim();
  if (!s) return null;
  const shorts = /youtube\.com\/shorts\/([a-zA-Z0-9_-]{11})/i.exec(s);
  let url: URL | null;
  try {
    url = new URL(s);
  } catch {
    url = null;
  }
  const start = url ? parseStart(url.searchParams.get('t')) : 0;
  if (shorts) return { videoId: shorts[1]!, start };
  if (!url) return null;
  const v = url.searchParams.get('v');
  if (v && ID.test(v.trim())) return { videoId: v.trim(), start };
  const path = url.pathname.replace(/^\/+|\/+$/g, '');
  if (ID.test(path)) return { videoId: path, start };
  return null;
}

/** `t` als Sekunden („90“) oder YouTube-Format („1m30s“). */
function parseStart(t: string | null): number {
  if (!t) return 0;
  if (/^\d+$/.test(t)) return Math.min(86400, Number(t));
  const m = /^(?:(\d+)h)?(?:(\d+)m)?(?:(\d+)s)?$/.exec(t);
  if (!m) return 0;
  return Math.min(86400, Number(m[1] ?? 0) * 3600 + Number(m[2] ?? 0) * 60 + Number(m[3] ?? 0));
}
