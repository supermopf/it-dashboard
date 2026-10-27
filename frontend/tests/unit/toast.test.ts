import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import { UrgentToast, safeColor, toastDuration, toastVolume } from '../../src/features/urgent-toast';
import { RadioController } from '../../src/features/radio';
import { Logger } from '../../src/core/log';
import { Scheduler } from '../../src/core/scheduler';
import { MediaManager } from '../../src/media/media-manager';
import { isReloadPayload, sanitizeHtml } from '../../src/util/sanitize';
import { safeMediaUrl } from '../../src/util/url';
import { fakeClock, installMediaStubs } from './helpers';

beforeAll(() => installMediaStubs());

const html = (frag: DocumentFragment) => {
  const d = document.createElement('div');
  d.append(frag);
  return d.innerHTML;
};

describe('Sanitizer', () => {
  it('entfernt Skripte, Event-Handler und gefährliche Elemente', () => {
    const out = html(sanitizeHtml('<b onclick="x()">fett</b><script>alert(1)</script><iframe src="x"></iframe><img src="javascript:alert(1)"><a href="javascript:x">link</a>'));
    expect(out).toBe('<b>fett</b>link');
  });
  it('erlaubt einfache Formatierung und gültige Bilder', () => {
    const out = html(sanitizeHtml('<p>Hallo <em>Welt</em></p><img src="https://example.test/a.png" alt="A" style="x">'));
    expect(out).toContain('<p>Hallo <em>Welt</em></p>');
    expect(out).toContain('src="https://example.test/a.png"');
    expect(out).not.toContain('style');
  });
  it('erkennt den Legacy-Reload-Toast', () => {
    expect(isReloadPayload('<script>location.reload()</script>')).toBe(true);
    expect(isReloadPayload('<script> window.location.reload( ); </script>')).toBe(true);
    expect(isReloadPayload('<script>fetch("//evil")</script>')).toBe(false);
    expect(isReloadPayload('Bitte neu laden')).toBe(false);
  });
});

describe('Medien-URLs (ToastPicture/ToastSound)', () => {
  it.each([
    ['https://example.test/a.mp4', true],
    ['./res/alert.mp3', true],
    ['javascript:alert(1)', false],
    ['data:text/html,<script>', false],
    ['  ', false],
    ['http://x/\u0000', false],
  ])('%s', (url, ok) => {
    expect(safeMediaUrl(url, 'http://dash.test/monitor/') !== null).toBe(ok);
  });
});

describe('Toast-Werte', () => {
  it('validiert Farben, Dauer und Lautstärke', () => {
    expect(safeColor('#FA2A00', 'x')).toBe('#FA2A00');
    expect(safeColor('red;background:url(x)', '#fff')).toBe('#fff');
    expect(toastDuration('5000')).toBe(5000);
    expect(toastDuration('')).toBe(30000);
    expect(toastDuration('1e12')).toBe(3_600_000);
    expect(toastDuration('10')).toBe(1000);
    expect(toastVolume('2')).toBe(1);
    expect(toastVolume('abc')).toBe(0.5);
  });
});

describe('UrgentToast', () => {
  beforeEach(() => {
    vi.useFakeTimers();
    vi.stubGlobal('requestAnimationFrame', (cb: FrameRequestCallback) => setTimeout(() => cb(0), 0));
  });
  afterEach(() => {
    vi.useRealTimers();
    vi.unstubAllGlobals();
  });

  function make() {
    const media = new MediaManager(1);
    const onReload = vi.fn();
    const t = new UrgentToast({ scheduler: new Scheduler(fakeClock), media, log: new Logger(), defaultSound: 'http://dash.test/res/alert.mp3', onReloadRequest: onReload, allowLegacyScripts: false });
    document.body.append(t.element);
    return { t, media, onReload };
  }

  it('Reload-Toast löst nur den kontrollierten Reload aus', () => {
    const { t, onReload } = make();
    t.show({ ToastBody: '<script>location.reload()</script>', ToastHistory: 'false' } as never);
    expect(onReload).toHaveBeenCalledTimes(1);
    expect(t.isOpen).toBe(false);
  });

  it('zeigt Inhalt bereinigt, schließt nach ToastTime und entlädt Medien', () => {
    const { t, media } = make();
    t.show({ ToastSubject: '<b>Wichtig</b>', ToastBody: 'Text<script>evil()</script>', ToastPicture: 'https://example.test/v.mp4', ToastTime: '5000', ToastColor: '#00f' });
    expect(t.isOpen).toBe(true);
    expect(t.element.querySelector('.urgent-title')!.innerHTML).toBe('<b>Wichtig</b>');
    expect(t.element.querySelector('.urgent-body')!.innerHTML).toBe('Text');
    expect(t.element.querySelector('video')).not.toBeNull();
    expect(media.counts().playingVideo).toBe(1);
    vi.advanceTimersByTime(5000);
    expect(t.isOpen).toBe(false);
    expect(media.registered).toBe(0);
    vi.advanceTimersByTime(300);
    expect(t.element.hidden).toBe(true);
  });

  it('neuer Toast setzt den Schließ-Timer zurück (Legacy-Fehler: alter Timer schloss neuen Toast)', () => {
    const { t } = make();
    t.show({ ToastBody: 'eins', ToastTime: '5000' });
    vi.advanceTimersByTime(4000);
    t.show({ ToastBody: 'zwei', ToastTime: '5000' });
    vi.advanceTimersByTime(2000);
    expect(t.isOpen).toBe(true);
    vi.advanceTimersByTime(3000);
    expect(t.isOpen).toBe(false);
  });

  it('nicht erreichbares Video → Hinweis statt leerer Fläche', () => {
    const { t } = make();
    t.show({ ToastBody: 'x', ToastPicture: 'https://example.test/kaputt.webm' });
    t.element.querySelector('video')!.dispatchEvent(new Event('error'));
    expect(t.element.textContent).toContain('Video nicht verfügbar');
    t.hide();
  });
});

describe('Radio', () => {
  it('handelt nur bei Zustandswechseln (kein src="" jede Sekunde)', () => {
    const media = new MediaManager(1);
    const r = new RadioController(media, new Logger());
    const playSpy = HTMLMediaElement.prototype.play as unknown as ReturnType<typeof vi.fn>;
    playSpy.mockClear();
    for (let i = 0; i < 10; i++) r.apply(false, '', 0.1);
    expect(playSpy).not.toHaveBeenCalled();
    for (let i = 0; i < 10; i++) r.apply(true, 'https://radio.test/stream.mp3', 0.2);
    expect(playSpy).toHaveBeenCalledTimes(1);
    r.apply(true, 'https://radio.test/other.mp3', 0.2);
    expect(playSpy).toHaveBeenCalledTimes(2);
    r.apply(false, '', 0.2);
    expect(r.isPlaying).toBe(false);
    expect(media.counts().playingAudio).toBe(0);
  });
  it('ungültige Sender-URLs werden ignoriert', () => {
    const r = new RadioController(new MediaManager(1), new Logger());
    r.apply(true, 'javascript:alert(1)', 0.5);
    expect(r.isPlaying).toBe(false);
  });
});

describe('MediaManager', () => {
  it('höchstens ein Video gleichzeitig', async () => {
    const m = new MediaManager(1);
    const a = document.createElement('video');
    const b = document.createElement('video');
    await m.play(a);
    await m.play(b);
    expect(a.paused).toBe(true);
    expect(b.paused).toBe(false);
    expect(m.counts().playingVideo).toBe(1);
  });
});
