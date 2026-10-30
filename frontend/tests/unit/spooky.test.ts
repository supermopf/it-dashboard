import { afterEach, describe, expect, it, vi } from 'vitest';
import { Scheduler } from '../../src/core/scheduler';
import { NO_TITLE_TEXT, SongToast } from '../../src/features/song-toast';
import { Spooky, isNight } from '../../src/features/spooky';
import { parseUpdate } from '../../src/protocol/messages';
import { fakeClock } from './helpers';

const at = (h: number, m = 0) => new Date(2026, 9, 29, h, m);

describe('Gruselmodus', () => {
  afterEach(() => {
    vi.useRealTimers();
    document.documentElement.classList.remove('is-spooky');
  });

  it('erkennt das Nachtfenster über Mitternacht', () => {
    expect(isNight(at(20), 20, 6)).toBe(true);
    expect(isNight(at(23, 59), 20, 6)).toBe(true);
    expect(isNight(at(5, 59), 20, 6)).toBe(true);
    expect(isNight(at(6), 20, 6)).toBe(false);
    expect(isNight(at(12), 20, 6)).toBe(false);
    expect(isNight(at(13), 12, 14)).toBe(true);
  });

  it('auto folgt der Uhrzeit, on/off überstimmen sie', () => {
    vi.useFakeTimers();
    const s = new Spooky(new Scheduler(fakeClock), { from: 20, to: 6 });
    s.evaluate(at(12));
    expect(s.active).toBe(false);
    s.evaluate(at(22));
    expect(s.active).toBe(true);
    expect(document.documentElement.classList.contains('is-spooky')).toBe(true);
    s.setMode('off');
    expect(s.active).toBe(false);
    expect(document.documentElement.classList.contains('is-spooky')).toBe(false);
    s.setMode('on');
    expect(s.active).toBe(true);
    expect(s.element.classList.contains('is-on')).toBe(true);
  });

  it('Spinnen erscheinen und verschwinden wieder (höchstens zwei)', () => {
    vi.useFakeTimers();
    const sched = new Scheduler(fakeClock);
    const s = new Spooky(sched, { from: 20, to: 6 });
    s.setMode('on');
    vi.advanceTimersByTime(11_000);
    expect(s.element.querySelectorAll('.spooky-spider').length).toBe(1);
    vi.advanceTimersByTime(120_000);
    expect(s.element.querySelectorAll('.spooky-spider').length).toBeLessThanOrEqual(2);
    s.setMode('off');
    expect(s.element.querySelectorAll('.spooky-spider').length).toBe(0);
  });

  it('Server-Update: Spooky wird normalisiert', () => {
    expect(parseUpdate({ Spooky: 'on' }).Spooky).toBe('on');
    expect(parseUpdate({ Spooky: 'kaputt' }).Spooky).toBe('auto');
    expect(parseUpdate({}).Spooky).toBe('auto');
  });
});

describe('Songtitel-Toast', () => {
  it('zeigt ohne Titel einen Hinweis statt eines leeren Felds', () => {
    vi.useFakeTimers();
    const t = new SongToast(new Scheduler(fakeClock));
    t.show('', '');
    expect(t.element.classList.contains('is-visible')).toBe(true);
    expect(t.element.querySelector('.song-text')?.textContent).toBe(NO_TITLE_TEXT);
    expect(t.element.querySelector<HTMLImageElement>('.song-icon')?.hidden).toBe(true);
    t.show('https://example.test/logo.png', 'Artist - Song');
    expect(t.element.querySelector('.song-text')?.textContent).toBe('Artist - Song');
    vi.advanceTimersByTime(12_500);
    expect(t.element.classList.contains('is-visible')).toBe(false);
    vi.useRealTimers();
  });
});
