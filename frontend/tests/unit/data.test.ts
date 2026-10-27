import { afterEach, describe, expect, it, vi } from 'vitest';
import { predictNextPage } from '../../src/app';
import { looksInvalid, ViewCache } from '../../src/core/view-cache';
import { ReloadGuard } from '../../src/core/recovery';
import { Logger } from '../../src/core/log';
import { parseMessage, parsePageToken, parseRoll, parseUpdate } from '../../src/protocol/messages';
import { clampText, formatNumber } from '../../src/util/format';
import { parseYouTube } from '../../src/util/youtube';

function memStorage(): Storage {
  const m = new Map<string, string>();
  return {
    get length() {
      return m.size;
    },
    clear: () => m.clear(),
    getItem: (k) => m.get(k) ?? null,
    key: (i) => [...m.keys()][i] ?? null,
    removeItem: (k) => void m.delete(k),
    setItem: (k, v) => void m.set(k, String(v)),
  };
}

describe('Protokoll', () => {
  it('normalisiert Updates (Strings → Typen, Grenzen, fehlerhafte Felder)', () => {
    const u = parseUpdate({ Page: '3', FUN: 'true', Radio: 'false', Radiovolume: '7', CD: 'abc', PageStatus: { 3: 'warning', x: 'warning', 4: 'evil' } });
    expect(u.Page).toBe(3);
    expect(u.FUN).toBe(true);
    expect(u.Radio).toBe(false);
    expect(u.Radiovolume).toBe(1);
    expect(u.CD).toBe(0);
    expect(u.PageStatus).toEqual({ 3: 'warning' });
  });

  it('sehr große Zahlen werden begrenzt', () => {
    const u = parseUpdate({ CD: 1e12, YTvolume: -5, SongTitle: 'x'.repeat(5000) });
    expect(u.CD).toBe(3600);
    expect(u.YTvolume).toBe(0);
    expect(u.SongTitle.length).toBe(300);
  });

  it('ungültiges JSON und Nicht-Objekte werden verworfen', () => {
    expect(parseMessage('{kaputt')).toBeNull();
    expect(parseMessage('42')).toBeNull();
    expect(parseMessage('null')).toBeNull();
  });

  it('Befehle wie im Legacy-Client zerlegen', () => {
    expect(parseMessage('{"type":"command","message":"!page 5"}')).toMatchObject({ command: '!page', arg: '5' });
    expect(parseMessage('{"type":"command","message":"!urgent {\\"a\\": 1}"}')).toMatchObject({ command: '!urgent', arg: '{"a": 1}' });
    expect(parseMessage('{"type":"command","message":"!songtitle"}')).toMatchObject({ command: '!songtitle', arg: '' });
  });

  it('Seitentoken validieren', () => {
    expect(parsePageToken('7')).toBe('7');
    expect(parsePageToken('FUN')).toBe('FUN');
    expect(parsePageToken('../etc')).toBeNull();
    expect(parsePageToken('')).toBeNull();
  });

  it('Würfelwerte 1…20 → Index 0…19, Ausreißer werden gefaltet (AUDIT P28)', () => {
    expect(parseRoll('1 20 10', [20, 20, 20])).toEqual([0, 19, 9]);
    expect(parseRoll('21 0 -1', [20, 20, 20])).toEqual([0, 19, 18]);
    expect(parseRoll('1 2', [20, 20, 20])).toBeNull();
    expect(parseRoll('a b c', [20, 20, 20])).toBeNull();
  });
});

describe('Rotation', () => {
  it('sagt die nächste Seite wie server.php voraus (Seiten mit hasData=false/„ok“ überspringen)', () => {
    expect(predictNextPage(1, {})).toBe('2');
    expect(predictNextPage(10, {})).toBe('1');
    expect(predictNextPage(2, { 3: 'ok', 4: 'ok', 5: 'warning' })).toBe('5');
    expect(predictNextPage(9, { 10: 'ok', 1: 'ok' })).toBe('2');
    const allOk = Object.fromEntries(Array.from({ length: 10 }, (_, i) => [String(i + 1), 'ok']));
    expect(predictNextPage(4, allOk)).toMatch(/^\d+$/);
  });
});

describe('ViewCache (Offline-Fallback, Cache der letzten gültigen Daten)', () => {
  afterEach(() => vi.unstubAllGlobals());

  const respond = (body: string, status = 200, headers: Record<string, string> = {}) =>
    Promise.resolve(new Response(status === 304 ? null : body, { status, headers }));

  it('erkennt ungültige Antworten', () => {
    expect(looksInvalid('')).toBe('leere Antwort');
    expect(looksInvalid('<pre>Array\n(\n [0] => Array')).toBe('Datenbankfehler');
    expect(looksInvalid('<b>Fatal error</b>: Uncaught Error')).toBe('PHP-Fehler');
    expect(looksInvalid('<div class="row"></div>')).toBeNull();
  });

  it('liefert bei Fehlern die letzte gültige Version (stale) und ersetzt sie nie durch Fehlerausgaben', async () => {
    const fetchMock = vi.fn().mockReturnValueOnce(respond('<p>v1</p>', 200, { ETag: '"1"' })).mockReturnValueOnce(respond('<pre>Array\n(\n')).mockRejectedValueOnce(new TypeError('offline'));
    vi.stubGlobal('fetch', fetchMock);
    const vc = new ViewCache((p) => `/cache/page${p}.php`, 1000, memStorage());
    expect((await vc.load('1')).view.html).toBe('<p>v1</p>');
    const r2 = await vc.load('1');
    expect(r2.stale).toBe(true);
    expect(r2.view.html).toBe('<p>v1</p>');
    const r3 = await vc.load('1');
    expect(r3.stale).toBe(true);
    expect(r3.error).toContain('Netzwerkfehler');
  });

  it('nutzt bedingte Requests (304)', async () => {
    const fetchMock = vi.fn().mockReturnValueOnce(respond('<p>v1</p>', 200, { ETag: '"abc"' })).mockReturnValueOnce(respond('', 304));
    vi.stubGlobal('fetch', fetchMock);
    const vc = new ViewCache((p) => `/p${p}`, 1000, memStorage());
    await vc.load('2');
    const r = await vc.load('2');
    expect(r.view.html).toBe('<p>v1</p>');
    expect(vc.stats.notModified).toBe(1);
    const headers = fetchMock.mock.calls[1]![1].headers as Record<string, string>;
    expect(headers['If-None-Match']).toBe('"abc"');
  });

  it('langsame API → Timeout, kein Hängenbleiben', async () => {
    vi.useFakeTimers();
    vi.stubGlobal('fetch', (_: string, init: RequestInit) => new Promise((_r, reject) => init.signal!.addEventListener('abort', () => reject(new DOMException('abort', 'AbortError')))));
    const vc = new ViewCache((p) => `/p${p}`, 500, memStorage());
    const pr = vc.load('3');
    vi.advanceTimersByTime(500);
    await expect(pr).rejects.toThrow(/Timeout/);
    vi.useRealTimers();
  });

  it('gleichzeitige Abrufe derselben Seite teilen sich einen Request', async () => {
    const fetchMock = vi.fn().mockImplementation(() => respond('<p>x</p>'));
    vi.stubGlobal('fetch', fetchMock);
    const vc = new ViewCache((p) => `/p${p}`, 1000, memStorage());
    await Promise.all([vc.load('4'), vc.load('4'), vc.load('4')]);
    expect(fetchMock).toHaveBeenCalledTimes(1);
  });

  it('stellt nach einem Neustart die letzte Version aus dem Storage wieder her', async () => {
    const storage = memStorage();
    vi.stubGlobal('fetch', vi.fn().mockReturnValueOnce(respond('<p>gespeichert</p>')));
    await new ViewCache((p) => `/p${p}`, 1000, storage).load('5');
    const fresh = new ViewCache((p) => `/p${p}`, 1000, storage);
    expect(fresh.peek('5')?.html).toBe('<p>gespeichert</p>');
  });
});

describe('ReloadGuard (Schutz gegen Reload-Schleifen)', () => {
  it('erlaubt höchstens N automatische Reloads pro Stunde', () => {
    let now = 1_000_000;
    const reload = vi.fn();
    const g = new ReloadGuard({ maxPerHour: 3, storage: memStorage(), now: () => now, reload });
    expect([g.reload('ws-down'), g.reload('ws-down'), g.reload('heap'), g.reload('ws-down')]).toEqual([true, true, true, false]);
    now += 3600_001;
    expect(g.reload('ws-down')).toBe(true);
    expect(reload).toHaveBeenCalledTimes(4);
  });
  it('Remote-Reloads haben ein eigenes Limit', () => {
    const g = new ReloadGuard({ maxPerHour: 1, maxRemotePerHour: 2, storage: memStorage(), reload: () => undefined });
    expect(g.reload('ws-down')).toBe(true);
    expect(g.reload('remote')).toBe(true);
    expect(g.reload('remote')).toBe(true);
    expect(g.reload('remote')).toBe(false);
  });
});

describe('Formatierung', () => {
  it('sehr große und ungültige Zahlen', () => {
    expect(formatNumber(1234)).toBe('1.234');
    expect(formatNumber(12_345_678)).toBe('12,3 Mio.');
    expect(formatNumber(4.2e12)).toBe('4.200 Mrd.');
    expect(formatNumber('abc')).toBe('–');
    expect(formatNumber(Infinity)).toBe('–');
  });
  it('kürzt lange Texte', () => {
    expect(clampText('x'.repeat(50), 10)).toBe(`${'x'.repeat(9)}…`);
    expect(clampText('  kurz ', 10)).toBe('kurz');
  });
});

describe('YouTube-URLs (Portierung pageYT.php)', () => {
  it.each([
    ['https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'dQw4w9WgXcQ', 0],
    ['https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=42', 'dQw4w9WgXcQ', 42],
    ['https://youtu.be/dQw4w9WgXcQ?t=1m5s', 'dQw4w9WgXcQ', 65],
    ['https://www.youtube.com/shorts/abcdefghijk', 'abcdefghijk', 0],
  ])('%s', (url, id, start) => {
    expect(parseYouTube(url)).toEqual({ videoId: id, start });
  });
  it('ungültige URLs', () => {
    expect(parseYouTube('https://example.com/video')).toBeNull();
    expect(parseYouTube('kein link')).toBeNull();
    expect(parseYouTube('')).toBeNull();
  });
});

describe('Logger', () => {
  it('Ringpuffer wächst nicht unbegrenzt', () => {
    const l = new Logger(10);
    l.consoleLevel = 'error';
    for (let i = 0; i < 1000; i++) l.info(`m${i}`);
    expect(l.length).toBe(10);
    expect(l.entries().map((e) => e.msg)).toEqual(Array.from({ length: 10 }, (_, i) => `m${990 + i}`));
  });
  it('kürzt sehr lange Meldungen', () => {
    const l = new Logger(2);
    l.consoleLevel = 'error';
    l.info('y'.repeat(10_000));
    expect(l.entries()[0]!.msg.length).toBe(300);
  });
});
