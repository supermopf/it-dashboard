import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { Scheduler } from '../../src/core/scheduler';
import { Scope } from '../../src/core/scope';
import { fakeClock } from './helpers';

describe('Scheduler', () => {
  beforeEach(() => vi.useFakeTimers());
  afterEach(() => vi.useRealTimers());

  it('führt Timeouts einmal aus und zählt aktive Timer', () => {
    const s = new Scheduler(fakeClock);
    const fn = vi.fn();
    s.timeout(fn, 100);
    expect(s.activeCount).toBe(1);
    vi.advanceTimersByTime(100);
    expect(fn).toHaveBeenCalledTimes(1);
    expect(s.activeCount).toBe(0);
  });

  it('Intervalle laufen weiter, bis sie entfernt werden; Mindestperiode 250 ms', () => {
    const s = new Scheduler(fakeClock);
    const fn = vi.fn();
    const h = s.interval(fn, 10);
    vi.advanceTimersByTime(1000);
    expect(fn).toHaveBeenCalledTimes(4);
    h.cancel();
    vi.advanceTimersByTime(1000);
    expect(fn).toHaveBeenCalledTimes(4);
    expect(s.activeCount).toBe(0);
  });

  it('clearOwner entfernt alle Timer eines Besitzers', () => {
    const s = new Scheduler(fakeClock);
    const owner = {};
    const fn = vi.fn();
    s.interval(fn, 300, owner);
    s.timeout(fn, 300, owner);
    s.timeout(fn, 300, {});
    expect(s.clearOwner(owner)).toBe(2);
    expect(s.activeCount).toBe(1);
  });

  it('pausiert bei verborgenem Dokument und startet ohne Doppelregistrierung neu', () => {
    const s = new Scheduler(fakeClock);
    const fn = vi.fn();
    s.interval(fn, 1000);
    s.interval(() => undefined, 1000, null, { pauseWhenHidden: false });
    s.setHidden(true);
    vi.advanceTimersByTime(5000);
    expect(fn).not.toHaveBeenCalled();
    s.setHidden(false);
    s.setHidden(false); // doppeltes visible-Event
    vi.advanceTimersByTime(1000);
    expect(fn).toHaveBeenCalledTimes(1);
    expect(vi.getTimerCount()).toBe(2);
  });

  it('richtet Intervalle auf Periodengrenzen aus (Uhr)', () => {
    vi.setSystemTime(new Date('2026-10-27T10:00:00.400Z'));
    const s = new Scheduler(fakeClock);
    const fn = vi.fn();
    s.interval(fn, 1000, null, { align: true });
    vi.advanceTimersByTime(599);
    expect(fn).not.toHaveBeenCalled();
    vi.advanceTimersByTime(2);
    expect(fn).toHaveBeenCalledTimes(1);
  });

  it('ein werfender Callback beendet das Intervall nicht', () => {
    const s = new Scheduler(fakeClock);
    const err = vi.spyOn(console, 'error').mockImplementation(() => undefined);
    let n = 0;
    s.interval(() => {
      n++;
      throw new Error('boom');
    }, 300);
    vi.advanceTimersByTime(900);
    expect(n).toBe(3);
    expect(err).toHaveBeenCalled();
  });
});

describe('Scope', () => {
  beforeEach(() => vi.useFakeTimers());
  afterEach(() => vi.useRealTimers());

  it('entfernt Listener, Timer und Object-URLs bei dispose()', () => {
    const s = new Scheduler(fakeClock);
    const scope = new Scope(s);
    const target = document.createElement('div');
    const onClick = vi.fn();
    scope.listen(target, 'click', onClick);
    scope.interval(() => undefined, 1000);
    const revoke = vi.fn();
    const create = vi.fn(() => 'blob:x');
    Object.assign(URL, { createObjectURL: create, revokeObjectURL: revoke });
    scope.objectUrl(new Blob(['x']));
    expect(scope.listeners).toBe(1);
    expect(s.activeCount).toBe(1);

    scope.dispose();
    target.click();
    expect(onClick).not.toHaveBeenCalled();
    expect(scope.listeners).toBe(0);
    expect(s.activeCount).toBe(0);
    expect(revoke).toHaveBeenCalledWith('blob:x');
  });

  it('reset() räumt auf, Scope bleibt nutzbar', () => {
    const s = new Scheduler(fakeClock);
    const scope = new Scope(s);
    scope.interval(() => undefined, 1000);
    scope.reset();
    expect(s.activeCount).toBe(0);
    scope.interval(() => undefined, 1000);
    expect(s.activeCount).toBe(1);
  });
});
