import { describe, expect, it, vi } from 'vitest';
import { Store } from '../../src/core/store';

function manualFrame() {
  const queue: Array<() => void> = [];
  return { frame: (cb: () => void) => queue.push(cb), run: () => queue.splice(0).forEach((f) => f()), queue };
}

describe('Store', () => {
  it('dedupliziert identische Updates (z. B. sekündliche Statusmeldungen)', () => {
    const f = manualFrame();
    const s = new Store({ a: 1, b: { x: 1 } }, f.frame);
    const fn = vi.fn();
    s.subscribe(null, fn);
    s.patch({ a: 1, b: { x: 1 } });
    f.run();
    expect(fn).not.toHaveBeenCalled();
    expect(s.stats.deduped).toBe(1);
    expect(s.stats.fieldsSkipped).toBe(2);
  });

  it('bündelt mehrere Änderungen zu einer Benachrichtigung pro Frame', () => {
    const f = manualFrame();
    const s = new Store({ a: 1, b: 2 }, f.frame);
    const fn = vi.fn();
    s.subscribe(null, fn);
    s.patch({ a: 2 });
    s.patch({ b: 3 });
    s.patch({ a: 4 });
    expect(f.queue.length).toBe(1);
    f.run();
    expect(fn).toHaveBeenCalledTimes(1);
    expect([...fn.mock.calls[0]![1]].sort()).toEqual(['a', 'b']);
    expect(s.get()).toEqual({ a: 4, b: 3 });
  });

  it('benachrichtigt nur Listener betroffener Felder', () => {
    const f = manualFrame();
    const s = new Store({ a: 1, b: 2 }, f.frame);
    const onA = vi.fn();
    const onB = vi.fn();
    s.subscribe(['a'], onA);
    s.subscribe(['b'], onB);
    s.patch({ a: 5 });
    f.run();
    expect(onA).toHaveBeenCalledTimes(1);
    expect(onB).not.toHaveBeenCalled();
  });

  it('Abmelden entfernt den Listener', () => {
    const f = manualFrame();
    const s = new Store({ a: 1 }, f.frame);
    const fn = vi.fn();
    const off = s.subscribe(null, fn);
    off();
    s.patch({ a: 2 });
    f.run();
    expect(fn).not.toHaveBeenCalled();
    expect(s.listenerCount).toBe(0);
  });
});
