import { replaceFontAwesome, iconEl } from '../icons';
import type { LegacyRuntime } from '../legacy/legacy-runtime';
import { el } from '../util/dom';
import { BasePanel, type PanelContext } from './panel';

/**
 * Ansicht für serverseitig gerenderte HTML-Fragmente (monitor/cache/pageN.php).
 *
 * Unterschiede zum Legacy-Client (`$("#ajax").html(result)`):
 *  - Inline-Skripte laufen in **einem gemeinsamen Funktions-Scope pro Ansicht** statt global → keine
 *    `const`-Redeklarationsfehler beim zweiten Besuch (AUDIT P4), keine globalen Lecks
 *  - Timer aus den Skripten werden erfasst und beim Zerstören entfernt
 *  - Charts werden beim Zerstören per `destroy()` freigegeben (AUDIT P1/P2)
 *  - Videos starten erst bei activate(), pausieren bei deactivate() und werden bei destroy() entladen
 *  - SCOM-Slider rotieren in der Ansicht, die sie enthält (AUDIT P22)
 */

export interface FragmentPanelOptions {
  html: string;
  legacy: LegacyRuntime;
  fetchedAt: number;
  stale: boolean;
  /** Intervall der Slider-Rotation (Legacy: alle 10 s, gekoppelt an CD % 10). */
  sliderIntervalMs?: number;
}

export class FragmentPanel extends BasePanel {
  scriptError: string | null = null;
  private videos: HTMLVideoElement[] = [];
  private sliders: HTMLElement[] = [];
  private sliderIndex = 0;
  private fragmentTimers = new Set<number>();
  private fragmentIntervals = new Set<number>();
  private content: HTMLElement | null = null;

  constructor(
    id: string,
    ctx: PanelContext,
    private readonly opts: FragmentPanelOptions,
  ) {
    super(id, ctx);
    this.dataTimestamp = opts.fetchedAt;
    this.stale = opts.stale;
  }

  get timerCount(): number {
    return this.fragmentTimers.size + this.fragmentIntervals.size;
  }

  protected async onMount(root: HTMLElement): Promise<void> {
    const tpl = document.createElement('template');
    tpl.innerHTML = this.opts.html;
    const frag = tpl.content;

    const inline: string[] = [];
    const external: string[] = [];
    for (const s of [...frag.querySelectorAll('script')]) {
      const src = s.getAttribute('src');
      if (src) external.push(src);
      else if (!s.type || /javascript|^module$/i.test(s.type)) inline.push(s.textContent ?? '');
      s.remove();
    }

    replaceFontAwesome(frag);
    for (const img of frag.querySelectorAll('img')) img.decoding = 'async';
    for (const v of [...frag.querySelectorAll('video')]) {
      v.removeAttribute('autoplay');
      v.muted = true;
      v.setAttribute('muted', '');
      v.playsInline = true;
      v.preload = 'auto';
      this.videos.push(v);
    }

    const content = el('div', { class: 'legacy-view' });
    content.append(frag);
    root.append(content);
    this.content = content;

    // Fehlende Bilder: Platzhalter statt „kaputtem“ Icon. Ein delegierter Listener für alle Bilder.
    this.scope.listen(
      content,
      'error',
      (ev: Event) => {
        const t = ev.target;
        if (t instanceof HTMLImageElement && !t.dataset.failed) {
          t.dataset.failed = '1';
          const ph = el('div', { class: 'img-missing', role: 'img', 'aria-label': 'Bild nicht verfügbar' });
          ph.append(iconEl('alert-circle'), document.createTextNode('Bild nicht verfügbar'));
          t.replaceWith(ph);
        } else if (t instanceof HTMLVideoElement) {
          t.classList.add('media-failed');
        }
      },
      { capture: true },
    );

    for (const v of this.videos) this.ctx.media.register(v);

    this.sliders = [...content.querySelectorAll<HTMLElement>('[id^="slider"]')].filter((n) =>
      /^slider\d+$/.test(n.id),
    );

    if (inline.length || external.length || content.querySelector('canvas')) {
      await this.opts.legacy.ensure();
      for (const src of external) await this.loadExternal(src);
      this.runScripts(inline);
    }

    this.showSlider(0);

    if (!hasVisibleContent(content)) {
      content.append(
        el('div', { class: 'empty-state' }, iconEl('check'), el('p', { text: 'Keine Einträge' })),
      );
    }
    if (this.scriptError) {
      root.append(
        el(
          'div',
          { class: 'panel-error-badge', role: 'status' },
          iconEl('alert'),
          el('span', { text: 'Diagramme teilweise nicht darstellbar' }),
        ),
      );
    }
  }

  protected override onActivate(): void {
    let started = 0;
    for (const v of this.videos) {
      if (started >= this.ctx.config.maxActiveVideos) break;
      void this.ctx.media.play(v);
      started++;
    }
    if (this.sliders.length > 1) {
      this.activeScope.interval(() => this.showSlider(this.sliderIndex + 1), this.opts.sliderIntervalMs ?? 10000, {
        label: 'fragment-slider',
      });
    }
  }

  protected override onDeactivate(): void {
    for (const v of this.videos) this.ctx.media.pause(v);
  }

  protected override onDestroy(): void {
    for (const t of this.fragmentTimers) window.clearTimeout(t);
    for (const t of this.fragmentIntervals) window.clearInterval(t);
    this.fragmentTimers.clear();
    this.fragmentIntervals.clear();
    if (this.root) this.opts.legacy.destroyChartsWithin(this.root);
    for (const v of this.videos) this.ctx.media.unload(v);
    this.videos = [];
    this.sliders = [];
    this.content = null;
  }

  private showSlider(index: number): void {
    const n = this.sliders.length;
    if (n === 0) return;
    this.sliderIndex = ((index % n) + n) % n;
    this.sliders.forEach((s, i) => {
      const on = i === this.sliderIndex;
      s.style.display = on ? '' : 'none';
      s.classList.toggle('slide-in', on && n > 1);
    });
    const cur = this.content?.querySelector('#CurrentPage');
    const cnt = this.content?.querySelector('#PageCount');
    if (cur) cur.textContent = String(this.sliderIndex + 1);
    if (cnt) cnt.textContent = String(n);
  }

  /**
   * Führt alle Inline-Skripte einer Ansicht in einem gemeinsamen Funktions-Scope aus. Jedes Skript steht in
   * einem eigenen try/catch – wie beim Legacy-Client bricht ein fehlerhaftes Skript die folgenden nicht ab.
   * `var`- und Funktionsdeklarationen bleiben skriptübergreifend sichtbar (Function-Scope).
   */
  private runScripts(scripts: string[]): void {
    const bodies = scripts.filter((s) => s.trim() !== '');
    if (bodies.length === 0) return;
    const code = bodies.map((s) => `try {\n${s}\n} catch (__e) { __fragmentError(__e); }`).join('\n');
    const onError = (err: unknown) => {
      const msg = err instanceof Error ? err.message : String(err);
      this.scriptError ??= msg;
      this.ctx.log.warn(`Skriptfehler in Ansicht ${this.id}: ${msg}`);
    };
    const timers = this.fragmentTimers;
    const intervals = this.fragmentIntervals;
    const st = (fn: TimerHandler, ms?: number, ...a: unknown[]) => {
      const id = window.setTimeout(
        (...args: unknown[]) => {
          timers.delete(id);
          if (typeof fn === 'function') (fn as (...x: unknown[]) => void)(...args);
        },
        ms,
        ...a,
      );
      timers.add(id);
      return id;
    };
    const si = (fn: TimerHandler, ms?: number, ...a: unknown[]) => {
      const id = window.setInterval(fn, Math.max(1000, ms ?? 0), ...a);
      intervals.add(id);
      return id;
    };
    const ct = (id: number) => {
      timers.delete(id);
      window.clearTimeout(id);
    };
    const ci = (id: number) => {
      intervals.delete(id);
      window.clearInterval(id);
    };
    // Legacy-Fragment (page5) ruft CyclePages(); die Rotation übernimmt jetzt das Panel.
    const prevCycle = window.CyclePages;
    window.CyclePages = () => undefined;
    try {
      // Bewusst kein strict mode: die Legacy-Skripte weisen teils implizit globale Variablen zu (ctx = …).
      const fn = new Function('setTimeout', 'setInterval', 'clearTimeout', 'clearInterval', '__fragmentError', code);
      fn.call(window, st, si, ct, ci, onError);
    } catch (err) {
      // Syntaxfehler im zusammengesetzten Code
      onError(err);
    } finally {
      window.CyclePages = prevCycle;
    }
  }

  private loadExternal(src: string): Promise<void> {
    return new Promise((resolve) => {
      const s = document.createElement('script');
      s.src = this.ctx.asset(src);
      s.onload = () => resolve();
      s.onerror = () => resolve();
      this.root?.append(s);
    });
  }
}

function hasVisibleContent(root: HTMLElement): boolean {
  if (root.querySelector('canvas,img,video,svg:not(.icon),table tbody tr,iframe')) return true;
  return (root.textContent ?? '').replace(/\s+/g, '').length > 0;
}
