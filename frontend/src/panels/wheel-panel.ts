import { fetchText } from '../core/http';
import { el, prefersReducedMotion } from '../util/dom';
import { BasePanel, type PanelContext } from './panel';

/**
 * Glücksrad „Ausreden-Satzgenerator“ (ersetzt monitor/cache/pageWHEEL.php + js/wheel.js).
 * Daten: monitor/assets/data/WheelOfFortune.json ({wheel1:[…], wheel2:[…], wheel3:[…]}),
 * Ton: monitor/assets/sounds/spin.mp3. Die Räder drehen nacheinander, nur per transform: rotate
 * (kein filter: blur wie im Original, AUDIT P11).
 */

type WheelData = Record<'wheel1' | 'wheel2' | 'wheel3', string[]>;

const LABELS = ['Satzanfang', 'Satzmitte', 'Satzende'];
const EXTRA_TURNS = 8;

export class WheelPanel extends BasePanel {
  private data: WheelData | null = null;
  private wheels: HTMLElement[] = [];
  private angles = [0, 0, 0];
  private result: HTMLElement | null = null;
  private sound: HTMLAudioElement | null = null;
  private spinning = false;
  private queued: [number, number, number] | null = null;

  constructor(ctx: PanelContext) {
    super('WHEEL', ctx);
    this.dataTimestamp = Date.now();
  }

  /** Anzahl Segmente pro Rad (für parseRoll). */
  get segmentCounts(): number[] {
    return this.data ? [this.data.wheel1.length, this.data.wheel2.length, this.data.wheel3.length] : [20, 20, 20];
  }

  protected async onMount(root: HTMLElement): Promise<void> {
    const res = await fetchText(this.ctx.asset('assets/data/WheelOfFortune.json'), {
      timeoutMs: this.ctx.config.fetchTimeoutMs,
    });
    const parsed = JSON.parse(res.text ?? '{}') as Partial<WheelData>;
    if (!Array.isArray(parsed.wheel1) || !Array.isArray(parsed.wheel2) || !Array.isArray(parsed.wheel3)) {
      throw new Error('Glücksrad-Daten ungültig');
    }
    this.data = parsed as WheelData;

    const row = el('div', { class: 'wheel-row' });
    (['wheel1', 'wheel2', 'wheel3'] as const).forEach((key, i) => {
      const items = this.data![key];
      const disc = el('div', { class: 'wheel-disc' });
      disc.style.setProperty('--n', String(items.length));
      items.forEach((text, j) => {
        const seg = el('div', { class: 'wheel-seg', text });
        // Segment j zeigt bei Drehwinkel 0 auf (360/n)·j Grad im Uhrzeigersinn ab „oben“.
        seg.style.transform = `rotate(${(360 / items.length) * j - 90}deg)`;
        disc.append(seg);
      });
      this.wheels.push(disc);
      row.append(
        el(
          'div',
          { class: 'wheel' },
          el('div', { class: 'wheel-window' }, disc, el('div', { class: 'wheel-pointer', 'aria-hidden': 'true' })),
          el('div', { class: 'wheel-label', text: LABELS[i]! }),
        ),
      );
    });
    this.result = el('p', { class: 'wheel-result', 'aria-live': 'polite' });
    root.append(el('div', { class: 'wheel-panel' }, row, el('div', { class: 'wheel-result-box' }, this.result)));

    this.sound = el('audio', { preload: 'auto', src: this.ctx.asset('assets/sounds/spin.mp3') });
    this.ctx.media.register(this.sound);
  }

  /** `!roll` – Indizes sind bereits 0-basiert (siehe protocol/messages.ts parseRoll). */
  spin(indices: [number, number, number]): void {
    if (!this.active || !this.data) return;
    if (this.spinning) {
      this.queued = indices;
      return;
    }
    this.spinning = true;
    if (this.result) {
      this.result.replaceChildren();
      this.result.classList.remove('is-visible');
    }
    this.spinWheel(0, indices);
  }

  protected override onDeactivate(): void {
    if (this.sound) this.ctx.media.pause(this.sound);
    this.spinning = false;
    this.queued = null;
  }

  protected override onDestroy(): void {
    if (this.sound) this.ctx.media.unload(this.sound);
    this.sound = null;
    this.wheels = [];
  }

  private spinWheel(i: number, indices: [number, number, number]): void {
    if (!this.active || !this.data) return;
    if (i > 2) {
      this.spinning = false;
      const next = this.queued;
      this.queued = null;
      if (next) this.spin(next);
      return;
    }
    const key = (['wheel1', 'wheel2', 'wheel3'] as const)[i]!;
    const items = this.data[key];
    const idx = indices[i]!;
    const seg = 360 / items.length;
    const reduced = prefersReducedMotion();
    let duration = 3000;
    if (this.sound) {
      if (Number.isFinite(this.sound.duration) && this.sound.duration > 0) duration = this.sound.duration * 1000;
      this.sound.currentTime = 0;
      this.sound.volume = 0.05;
      void this.ctx.media.play(this.sound);
    }
    if (reduced) duration = 0;
    // Zielwinkel: Segment idx unter den Zeiger (oben). Immer vorwärts drehen.
    const base = Math.ceil(this.angles[i]! / 360) * 360;
    const target = base + (reduced ? 0 : EXTRA_TURNS * 360) + (360 - idx * seg);
    this.angles[i] = target;
    const disc = this.wheels[i]!;
    disc.style.transitionDuration = `${duration}ms`;
    disc.style.transform = `rotate(${target}deg)`;

    this.activeScope.timeout(
      () => {
        this.appendResult(items[idx] ?? '', i);
        this.activeScope.timeout(() => this.spinWheel(i + 1, indices), reduced ? 0 : 300, { label: 'wheel' });
      },
      duration,
      { label: 'wheel' },
    );
  }

  private appendResult(text: string, i: number): void {
    if (!this.result) return;
    this.result.append(el('span', { class: `wheel-part wheel-part-${i + 1}`, text: `${text} ` }));
    this.result.classList.add('is-visible');
  }
}
