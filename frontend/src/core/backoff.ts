/**
 * Exponentieller Backoff mit „Full Jitter“: delay = random(0, min(max, base · 2^attempt)),
 * mit einer Untergrenze, damit nicht im Millisekundentakt neu verbunden wird.
 */
export interface BackoffOptions {
  baseMs: number;
  maxMs: number;
  minMs: number;
  random?: () => number;
}

export function backoffDelay(attempt: number, o: BackoffOptions): number {
  const rnd = o.random ?? Math.random;
  const exp = Math.min(o.maxMs, o.baseMs * 2 ** Math.max(0, Math.min(attempt, 20)));
  return Math.round(Math.max(o.minMs, rnd() * exp));
}
