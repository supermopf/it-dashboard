import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import { LegacyRuntime } from '../../src/legacy/legacy-runtime';
import { FragmentPanel } from '../../src/panels/fragment-panel';
import { BasePanel, panelStats, type Panel } from '../../src/panels/panel';
import { PanelHost } from '../../src/panels/panel-host';
import { MessagePanel } from '../../src/panels/message-panel';
import { Logger } from '../../src/core/log';
import { installMediaStubs, makeCtx } from './helpers';

beforeAll(() => installMediaStubs());

class ProbePanel extends BasePanel {
  ticks = 0;
  updates: unknown[] = [];
  protected onMount(root: HTMLElement): void {
    const btn = document.createElement('button');
    root.append(btn);
    this.scope.listen(btn, 'click', () => this.ticks++);
    const v = document.createElement('video');
    v.src = 'http://dash.test/x.webm';
    root.append(v);
    this.ctx.media.register(v);
  }
  protected override onActivate(): void {
    this.activeScope.interval(() => this.ticks++, 1000, { label: 'probe' });
    this.activeScope.listen(window, 'resize', () => this.ticks++);
  }
  protected override onUpdate(d: unknown): void {
    this.updates.push(d);
  }
}

describe('Panel-Lifecycle', () => {
  beforeEach(() => vi.useFakeTimers());
  afterEach(() => vi.useRealTimers());

  it('update() wirkt nur im aktiven Zustand', async () => {
    const ctx = makeCtx();
    const p = new ProbePanel('p', ctx);
    await p.mount(document.createElement('div'));
    p.update(1);
    p.activate();
    p.update(2);
    p.deactivate();
    p.update(3);
    expect(p.updates).toEqual([2]);
    p.destroy();
  });

  it('deactivate() pausiert Timer und entfernt nur aktive Listener', async () => {
    const ctx = makeCtx();
    const p = new ProbePanel('p', ctx);
    await p.mount(document.createElement('div'));
    p.activate();
    expect(ctx.scheduler.activeCount).toBe(1);
    expect(p.listenerCount).toBe(2);
    p.deactivate();
    expect(ctx.scheduler.activeCount).toBe(0);
    expect(p.listenerCount).toBe(1);
    vi.advanceTimersByTime(5000);
    expect(p.ticks).toBe(0);
    p.activate();
    p.activate(); // doppelt → kein zweiter Timer
    expect(ctx.scheduler.activeCount).toBe(1);
    p.destroy();
  });

  it('destroy() entfernt Timer, Listener, Medien und DOM und zählt Instanzen herunter', async () => {
    const ctx = makeCtx();
    const before = panelStats.live;
    const root = document.createElement('div');
    const p = new ProbePanel('p', ctx);
    expect(panelStats.live).toBe(before + 1);
    await p.mount(root);
    p.activate();
    const video = root.querySelector('video')!;
    const btn = root.querySelector('button')!;
    p.destroy();
    p.destroy(); // idempotent
    expect(panelStats.live).toBe(before);
    expect(ctx.scheduler.activeCount).toBe(0);
    expect(p.listenerCount).toBe(0);
    expect(root.childElementCount).toBe(0);
    expect(video.hasAttribute('src')).toBe(false);
    expect(ctx.media.registered).toBe(0);
    btn.click();
    expect(p.ticks).toBe(0);
  });
});

/** Legacy-Runtime ohne echtes Chart.js: Chart-Attrappe mit Instanzregister wie Chart 2.3. */
function fakeLegacy(): LegacyRuntime {
  const instances: Record<string, { id: number; chart: { canvas: HTMLCanvasElement }; destroy(): void }> = {};
  let id = 0;
  class Chart {
    static instances = instances;
    static defaults = { global: { animation: {}, hover: {}, tooltips: { enabled: true } }, scale: { gridLines: {} } };
    static plugins = { list: [] as unknown[], register(p: unknown) { this.list.push(p); } };
    static pluginService = Chart.plugins;
    id: number;
    chart: { canvas: HTMLCanvasElement };
    constructor(ctx: { canvas: HTMLCanvasElement }) {
      this.id = id++;
      this.chart = { canvas: ctx.canvas };
      instances[this.id] = this;
    }
    destroy() {
      delete instances[this.id];
    }
  }
  // Minimales jQuery für $('#id').get(0).getContext('2d') und .html()
  const $ = (sel: string) => {
    const el = document.querySelector(sel) as HTMLElement | null;
    return {
      get: () => (el instanceof HTMLCanvasElement ? { getContext: () => ({ canvas: el }) } : el),
      html: (v: unknown) => el && (el.textContent = String(v)),
    };
  };
  Object.assign(window, { Chart, jQuery: $, $ });
  return new LegacyRuntime({ vendorUrl: 'x', chartAnimation: false, fontFamily: 'sans', colors: { text: '#fff', grid: '#333' } });
}

const PERF = (n: number) =>
  Array.from({ length: n }, (_, i) => `<canvas id="perf-${i}"></canvas>`).join('') +
  Array.from(
    { length: n },
    (_, i) => `<script>const ctx${i} = $("#perf-${i}").get(0).getContext("2d"); const chart${i} = new Chart(ctx${i}, {});</script>`,
  ).join('');

describe('FragmentPanel (Legacy-Ansichten)', () => {
  beforeEach(() => vi.useFakeTimers());
  afterEach(() => {
    vi.useRealTimers();
    document.body.replaceChildren();
  });

  it('führt Skripte mehrfach aus, ohne const-Redeklarationsfehler (AUDIT P4)', async () => {
    const legacy = fakeLegacy();
    const ctx = makeCtx();
    for (let round = 0; round < 3; round++) {
      const root = document.body.appendChild(document.createElement('section'));
      const p = new FragmentPanel('3', ctx, { html: PERF(12), legacy, fetchedAt: 0, stale: false });
      await p.mount(root);
      expect(p.scriptError).toBeNull();
      expect(legacy.chartCount()).toBe(12);
      p.activate();
      p.destroy();
      root.remove();
      expect(legacy.chartCount()).toBe(0);
    }
  });

  it('zerstört Charts beim Verlassen – kein Wachstum über viele Wechsel (AUDIT P1)', async () => {
    const legacy = fakeLegacy();
    const ctx = makeCtx();
    for (let i = 0; i < 50; i++) {
      const root = document.body.appendChild(document.createElement('section'));
      const p = new FragmentPanel(String(i % 10), ctx, { html: PERF(4), legacy, fetchedAt: 0, stale: false });
      await p.mount(root);
      p.activate();
      p.destroy();
      root.remove();
    }
    expect(legacy.chartCount()).toBe(0);
    expect(ctx.scheduler.activeCount).toBe(0);
  });

  it('dedupliziert pluginService.register über wiederholte Seitenaufrufe (AUDIT P3)', async () => {
    const legacy = fakeLegacy();
    const ctx = makeCtx();
    const html = '<script>Chart.pluginService.register({ beforeDraw: function (c) { return c; } });</script>';
    for (let i = 0; i < 20; i++) {
      const root = document.body.appendChild(document.createElement('section'));
      const p = new FragmentPanel('6', ctx, { html, legacy, fetchedAt: 0, stale: false });
      await p.mount(root);
      p.destroy();
    }
    const W = window as unknown as { Chart: { plugins: { list: unknown[] } } };
    expect(W.Chart.plugins.list.length).toBe(1);
    expect(legacy.stats.pluginDuplicatesSkipped).toBe(19);
  });

  it('entfernt Timer, die Fragment-Skripte anlegen', async () => {
    const legacy = fakeLegacy();
    const ctx = makeCtx();
    const W = window as unknown as { __hits: number };
    W.__hits = 0;
    const html = '<script>setInterval(function(){ window.__hits++; }, 1000); setTimeout(function(){ window.__hits += 100; }, 5000);</script>';
    const root = document.body.appendChild(document.createElement('section'));
    const p = new FragmentPanel('x', ctx, { html, legacy, fetchedAt: 0, stale: false });
    await p.mount(root);
    expect(p.timerCount).toBe(2);
    vi.advanceTimersByTime(2000);
    expect(W.__hits).toBe(2);
    p.destroy();
    vi.advanceTimersByTime(10000);
    expect(W.__hits).toBe(2);
    expect(p.timerCount).toBe(0);
  });

  it('Skriptfehler: Inhalt bleibt sichtbar, Hinweis wird angezeigt (Fehlergrenze)', async () => {
    const legacy = fakeLegacy();
    const root = document.body.appendChild(document.createElement('section'));
    const p = new FragmentPanel('x', makeCtx(), { html: '<p>Daten</p><script>throw new Error("kaputt")</script>', legacy, fetchedAt: 0, stale: false });
    await p.mount(root);
    expect(p.scriptError).toContain('kaputt');
    expect(root.textContent).toContain('Daten');
    expect(root.querySelector('.panel-error-badge')).not.toBeNull();
    p.destroy();
  });

  it('ein fehlerhaftes Skript bricht die folgenden nicht ab (wie Legacy)', async () => {
    const legacy = fakeLegacy();
    const W = window as unknown as { __ran: string[] };
    W.__ran = [];
    const html = '<script>var shared = 1; window.__ran.push("a");</script><script>undefinedFn();</script><script>window.__ran.push("c" + shared);</script>';
    const root = document.body.appendChild(document.createElement('section'));
    const p = new FragmentPanel('x', makeCtx(), { html, legacy, fetchedAt: 0, stale: false });
    await p.mount(root);
    expect(W.__ran).toEqual(['a', 'c1']);
    expect(p.scriptError).toMatch(/undefinedFn/);
    p.destroy();
  });

  it('SCOM-Slider rotieren alle 10 s nur im aktiven Zustand (AUDIT P22)', async () => {
    const legacy = fakeLegacy();
    const html = `Seite <span id="CurrentPage"></span> von <span id="PageCount"></span>
      <div id="slider1" class="content" style="display:none">A</div><div id="slider2" class="content" style="display:none">B</div>
      <div id="slider3" class="content" style="display:none">C</div><script>var i = 0; CyclePages();</script>`;
    const root = document.body.appendChild(document.createElement('section'));
    const p = new FragmentPanel('5', makeCtx(), { html, legacy, fetchedAt: 0, stale: false });
    await p.mount(root);
    const visible = () => [...root.querySelectorAll<HTMLElement>('[id^=slider]')].filter((s) => s.style.display !== 'none').map((s) => s.id);
    expect(visible()).toEqual(['slider1']);
    p.activate();
    vi.advanceTimersByTime(10000);
    expect(visible()).toEqual(['slider2']);
    expect(root.querySelector('#CurrentPage')!.textContent).toBe('2');
    expect(root.querySelector('#PageCount')!.textContent).toBe('3');
    p.deactivate();
    vi.advanceTimersByTime(30000);
    expect(visible()).toEqual(['slider2']);
    p.destroy();
  });

  it('leere Daten → Hinweis „Keine Einträge“', async () => {
    const root = document.body.appendChild(document.createElement('section'));
    const p = new FragmentPanel('9', makeCtx(), { html: '<div class="row"><div class="col-lg-12"></div></div>', legacy: fakeLegacy(), fetchedAt: 0, stale: false });
    await p.mount(root);
    expect(root.textContent).toContain('Keine Einträge');
    p.destroy();
  });

  it('fehlende Bilder werden durch Platzhalter ersetzt', async () => {
    const root = document.body.appendChild(document.createElement('section'));
    const p = new FragmentPanel('1', makeCtx(), { html: '<img src="missing.png"><p>x</p>', legacy: fakeLegacy(), fetchedAt: 0, stale: false });
    await p.mount(root);
    root.querySelector('img')!.dispatchEvent(new Event('error'));
    expect(root.querySelector('img')).toBeNull();
    expect(root.querySelector('.img-missing')!.textContent).toContain('Bild nicht verfügbar');
    p.destroy();
  });

  it('Videos: erst bei activate(), max. 1 gleichzeitig, bei destroy() entladen', async () => {
    const ctx = makeCtx();
    const root = document.body.appendChild(document.createElement('section'));
    const html = '<video autoplay loop src="http://dash.test/a.webm"></video><video autoplay loop src="http://dash.test/b.webm"></video>';
    const p = new FragmentPanel('FUN', ctx, { html, legacy: fakeLegacy(), fetchedAt: 0, stale: false });
    await p.mount(root);
    const vids = [...root.querySelectorAll('video')];
    expect(vids.every((v) => !v.hasAttribute('autoplay') && v.muted)).toBe(true);
    expect(ctx.media.counts().playingVideo).toBe(0);
    p.activate();
    expect(ctx.media.counts().playingVideo).toBe(1);
    p.deactivate();
    expect(ctx.media.counts().playingVideo).toBe(0);
    p.destroy();
    expect(vids.every((v) => !v.hasAttribute('src'))).toBe(true);
  });

  it('ersetzt Font-Awesome-Icons durch Inline-SVG', async () => {
    const root = document.body.appendChild(document.createElement('section'));
    const p = new FragmentPanel('5', makeCtx(), { html: '<i class="fa fa-exclamation-triangle"></i> Warnung <i class="fa fa-unbekannt"></i>', legacy: fakeLegacy(), fetchedAt: 0, stale: false });
    await p.mount(root);
    expect(root.querySelectorAll('svg.icon').length).toBe(1);
    expect(root.querySelector('i')).toBeNull();
    p.destroy();
  });
});

describe('PanelHost', () => {
  beforeEach(() => {
    vi.stubGlobal('requestAnimationFrame', (cb: FrameRequestCallback) => setTimeout(() => cb(0), 0));
    vi.stubGlobal('matchMedia', (q: string) => ({ matches: q.includes('reduce'), media: q, addEventListener() {}, removeEventListener() {} }));
  });
  afterEach(() => vi.unstubAllGlobals());

  it('wechselt Ansichten, zerstört die alte und hält genau eine aktive (reduced motion)', async () => {
    const container = document.createElement('main');
    const host = new PanelHost(container, new Logger());
    const ctx = makeCtx();
    const shown: string[] = [];
    const mk = (id: string): Panel => new MessagePanel(id, ctx, 'empty', id);
    await host.show(() => mk('a'), () => mk('err'));
    await host.show(() => mk('b'), () => mk('err'));
    shown.push(host.active!.id);
    expect(shown).toEqual(['b']);
    expect(container.querySelectorAll('.view').length).toBe(1);
    host.destroyAll();
  });

  it('neuester Wechsel gewinnt, veraltete Ansichten werden verworfen', async () => {
    const container = document.createElement('main');
    const host = new PanelHost(container, new Logger());
    const ctx = makeCtx();
    const before = panelStats.live;
    const p1 = host.show(() => new MessagePanel('1', ctx, 'empty', '1'), () => new MessagePanel('e', ctx, 'error', 'e'));
    const p2 = host.show(() => new MessagePanel('2', ctx, 'empty', '2'), () => new MessagePanel('e', ctx, 'error', 'e'));
    const p3 = host.show(() => new MessagePanel('3', ctx, 'empty', '3'), () => new MessagePanel('e', ctx, 'error', 'e'));
    expect(host.busy).toBe(true);
    await Promise.all([p1, p2, p3]);
    expect(host.busy).toBe(false);
    expect(host.active!.id).toBe('3');
    expect(panelStats.live).toBe(before + 1);
    host.destroyAll();
  });

  it('Fehler beim Mounten → Fallback-Ansicht', async () => {
    const container = document.createElement('main');
    const host = new PanelHost(container, new Logger());
    const ctx = makeCtx();
    class Broken extends BasePanel {
      protected onMount(): void {
        throw new Error('mount kaputt');
      }
    }
    await host.show(() => new Broken('x', ctx), (err) => new MessagePanel('fallback', ctx, 'error', 'Fehler', String(err)));
    expect(host.active!.id).toBe('fallback');
    expect(container.textContent).toContain('mount kaputt');
    host.destroyAll();
  });
});
