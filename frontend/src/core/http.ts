/**
 * HTTP-Abrufe mit Timeout, AbortController und bedingten Requests (ETag / Last-Modified).
 */

export interface FetchTextOptions {
  timeoutMs: number;
  signal?: AbortSignal;
  etag?: string | null;
  lastModified?: string | null;
}

export interface FetchTextResult {
  status: number;
  /** null bei 304 Not Modified */
  text: string | null;
  etag: string | null;
  lastModified: string | null;
}

export class HttpError extends Error {
  constructor(
    message: string,
    readonly status: number,
    readonly kind: 'timeout' | 'abort' | 'network' | 'http' | 'invalid',
  ) {
    super(message);
  }
}

export interface HttpStats {
  requests: number;
  failures: number;
  notModified: number;
}

export const httpStats: HttpStats = { requests: 0, failures: 0, notModified: 0 };

export async function fetchText(url: string, o: FetchTextOptions): Promise<FetchTextResult> {
  const ctrl = new AbortController();
  let timedOut = false;
  const timer = setTimeout(() => {
    timedOut = true;
    ctrl.abort();
  }, o.timeoutMs);
  const onAbort = () => ctrl.abort();
  o.signal?.addEventListener('abort', onAbort, { once: true });
  httpStats.requests++;

  const headers: Record<string, string> = {};
  if (o.etag) headers['If-None-Match'] = o.etag;
  if (o.lastModified) headers['If-Modified-Since'] = o.lastModified;

  try {
    const res = await fetch(url, {
      signal: ctrl.signal,
      headers,
      cache: 'no-cache',
      credentials: 'same-origin',
    });
    if (res.status === 304) {
      httpStats.notModified++;
      return { status: 304, text: null, etag: o.etag ?? null, lastModified: o.lastModified ?? null };
    }
    if (!res.ok) throw new HttpError(`HTTP ${res.status} für ${url}`, res.status, 'http');
    const text = await res.text();
    return {
      status: res.status,
      text,
      etag: res.headers.get('ETag'),
      lastModified: res.headers.get('Last-Modified'),
    };
  } catch (err) {
    httpStats.failures++;
    if (err instanceof HttpError) throw err;
    if (timedOut) throw new HttpError(`Timeout nach ${o.timeoutMs} ms: ${url}`, 0, 'timeout');
    if (ctrl.signal.aborted) throw new HttpError(`Abgebrochen: ${url}`, 0, 'abort');
    throw new HttpError(`Netzwerkfehler: ${url}`, 0, 'network');
  } finally {
    clearTimeout(timer);
    o.signal?.removeEventListener('abort', onAbort);
  }
}
