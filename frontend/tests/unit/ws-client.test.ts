import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { backoffDelay } from '../../src/core/backoff';
import { Scheduler } from '../../src/core/scheduler';
import { WsClient } from '../../src/core/ws-client';
import { FakeWebSocket, fakeClock } from './helpers';

function client(extra: Partial<ConstructorParameters<typeof WsClient>[0]> = {}) {
  const scheduler = new Scheduler(fakeClock);
  const messages: string[] = [];
  const c = new WsClient({
    url: 'ws://test/',
    scheduler,
    WebSocketImpl: FakeWebSocket as unknown as typeof WebSocket,
    backoff: { baseMs: 1000, maxMs: 30000, minMs: 500, random: () => 1 },
    watchdogMs: 10000,
    onMessage: (d) => messages.push(d),
    ...extra,
  });
  return { c, scheduler, messages };
}

describe('backoffDelay', () => {
  it('wächst exponentiell bis zur Obergrenze', () => {
    const o = { baseMs: 1000, maxMs: 30000, minMs: 500, random: () => 1 };
    expect([0, 1, 2, 3, 4, 5, 6, 30].map((a) => backoffDelay(a, o))).toEqual([1000, 2000, 4000, 8000, 16000, 30000, 30000, 30000]);
  });
  it('Jitter bleibt innerhalb [min, exp]', () => {
    for (let i = 0; i < 200; i++) {
      const d = backoffDelay(4, { baseMs: 1000, maxMs: 30000, minMs: 500 });
      expect(d).toBeGreaterThanOrEqual(500);
      expect(d).toBeLessThanOrEqual(16000);
    }
  });
});

describe('WsClient', () => {
  beforeEach(() => {
    vi.useFakeTimers();
    FakeWebSocket.reset();
  });
  afterEach(() => vi.useRealTimers());

  it('baut genau eine Verbindung auf und meldet Nachrichten', () => {
    const { c, messages } = client();
    c.start();
    c.start();
    expect(FakeWebSocket.instances.length).toBe(1);
    FakeWebSocket.instances[0]!.open();
    FakeWebSocket.instances[0]!.message({ type: 'update' });
    expect(messages.length).toBe(1);
    expect(c.state).toBe('open');
    expect(c.stats.activeSockets).toBe(1);
  });

  it('verbindet nach Abbruch mit Backoff neu – ohne parallele Schleifen', () => {
    const { c } = client();
    c.start();
    const first = FakeWebSocket.instances[0]!;
    first.open();
    first.fail();
    first.close(); // doppeltes close darf keinen zweiten Reconnect planen
    expect(FakeWebSocket.live.length).toBe(0);
    vi.advanceTimersByTime(999);
    expect(FakeWebSocket.instances.length).toBe(1);
    vi.advanceTimersByTime(1);
    expect(FakeWebSocket.instances.length).toBe(2);
    // zweiter Versuch schlägt fehl → 2 s
    FakeWebSocket.instances[1]!.fail();
    vi.advanceTimersByTime(1999);
    expect(FakeWebSocket.instances.length).toBe(2);
    vi.advanceTimersByTime(1);
    expect(FakeWebSocket.instances.length).toBe(3);
    expect(FakeWebSocket.live.length).toBe(1);
  });

  it('zählt Reconnects und setzt den Backoff nach Erfolg zurück', () => {
    const { c } = client();
    c.start();
    FakeWebSocket.instances[0]!.open();
    FakeWebSocket.instances[0]!.message('{}');
    FakeWebSocket.instances[0]!.fail();
    vi.advanceTimersByTime(1000);
    FakeWebSocket.instances[1]!.open();
    expect(c.stats.reconnects).toBe(0); // erst die erste Nachricht zählt
    FakeWebSocket.instances[1]!.message('{}');
    expect(c.stats.reconnects).toBe(1);
    FakeWebSocket.instances[1]!.fail();
    vi.advanceTimersByTime(1000);
    expect(FakeWebSocket.instances.length).toBe(3);
  });

  it('Server trennt direkt nach dem Handshake: Backoff wächst weiter, Offline-Dauer läuft weiter', () => {
    const { c } = client();
    c.start();
    FakeWebSocket.instances[0]!.open();
    FakeWebSocket.instances[0]!.message('{}');
    FakeWebSocket.instances[0]!.fail();
    const since = c.stats.disconnectedSince;
    const delays: number[] = [];
    for (let i = 1; i <= 5; i++) {
      const before = Date.now();
      while (FakeWebSocket.instances.length === i) vi.advanceTimersByTime(100);
      delays.push(Date.now() - before);
      const s = FakeWebSocket.instances[i]!;
      s.open(); // Handshake ok …
      s.fail(); // … aber sofort getrennt, ohne Nachricht
    }
    expect(delays).toEqual([1000, 2000, 4000, 8000, 16000]);
    expect(c.stats.disconnectedSince).toBe(since);
    expect(c.state).toBe('closed');
  });

  it('Watchdog erkennt halb-offene Verbindungen', () => {
    const { c } = client();
    c.start();
    FakeWebSocket.instances[0]!.open();
    vi.advanceTimersByTime(9000);
    FakeWebSocket.instances[0]!.message('{}');
    vi.advanceTimersByTime(9000);
    expect(FakeWebSocket.instances[0]!.readyState).toBe(1);
    vi.advanceTimersByTime(1001);
    expect(FakeWebSocket.instances[0]!.readyState).toBe(3);
    expect(c.state).toBe('closed');
    vi.advanceTimersByTime(1000);
    expect(FakeWebSocket.instances.length).toBe(2);
  });

  it('Verbindungs-Timeout bei hängendem Handshake', () => {
    const { c } = client({ connectTimeoutMs: 5000 });
    c.start();
    vi.advanceTimersByTime(5000);
    expect(FakeWebSocket.instances[0]!.readyState).toBe(3);
    vi.advanceTimersByTime(1000);
    expect(FakeWebSocket.instances.length).toBe(2);
  });

  it('reconnectNow ersetzt den geplanten Versuch statt einen zweiten zu starten', () => {
    const { c, scheduler } = client();
    c.start();
    FakeWebSocket.instances[0]!.fail();
    c.reconnectNow();
    c.reconnectNow();
    expect(FakeWebSocket.instances.length).toBe(2);
    expect(FakeWebSocket.live.length).toBe(1);
    expect(scheduler.describe()['ws-reconnect']).toBeUndefined();
  });

  it('stop() beendet Verbindung und alle Timer', () => {
    const { c, scheduler } = client();
    c.start();
    FakeWebSocket.instances[0]!.open();
    c.stop();
    expect(scheduler.activeCount).toBe(0);
    vi.advanceTimersByTime(60000);
    expect(FakeWebSocket.instances.length).toBe(1);
  });

  it('send() schlägt bei geschlossener Verbindung fehl statt zu werfen', () => {
    const { c } = client();
    expect(c.send('!repeat')).toBe(false);
    c.start();
    FakeWebSocket.instances[0]!.open();
    expect(c.send('!repeat')).toBe(true);
    expect(FakeWebSocket.instances[0]!.sent).toEqual(['{"message":"!repeat"}']);
  });
});
