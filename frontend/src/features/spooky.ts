import type { Scheduler, TimerHandle } from '../core/scheduler';
import type { SpookyMode } from '../protocol/messages';
import { el, prefersReducedMotion } from '../util/dom';

const SVG_NS = 'http://www.w3.org/2000/svg';

/** true, wenn `d` im Nachtfenster liegt (from/to in Stunden, über Mitternacht möglich: 20 → 6). */
export function isNight(d: Date, from: number, to: number): boolean {
  const h = d.getHours() + d.getMinutes() / 60;
  return from <= to ? h >= from && h < to : h >= from || h < to;
}

function rand(min: number, max: number): number {
  return min + Math.random() * (max - min);
}

function svg(tag: string, attrs: Record<string, string>): SVGElement {
  const node = document.createElementNS(SVG_NS, tag);
  for (const [k, v] of Object.entries(attrs)) node.setAttribute(k, v);
  return node;
}

/** Spinnennetz für eine Ecke: Speichen ab der Ecke, dazwischen durchhängende Querfäden. */
function cobweb(corner: 'left' | 'right'): SVGElement {
  const size = 240;
  const spokes = [0, 15, 30, 45, 60, 75, 90].map((a) => (a * Math.PI) / 180);
  let d = '';
  for (const a of spokes) d += `M0 0L${(Math.cos(a) * size).toFixed(1)} ${(Math.sin(a) * size).toFixed(1)}`;
  for (const r of [40, 80, 125, 175, 225]) {
    spokes.forEach((a, i) => {
      const x = Math.cos(a) * r;
      const y = Math.sin(a) * r;
      if (i === 0) {
        d += `M${x.toFixed(1)} ${y.toFixed(1)}`;
        return;
      }
      // Querfaden hängt zur Ecke hin durch
      const mid = (a + spokes[i - 1]!) / 2;
      const sag = r * 0.82;
      d += `Q${(Math.cos(mid) * sag).toFixed(1)} ${(Math.sin(mid) * sag).toFixed(1)} ${x.toFixed(1)} ${y.toFixed(1)}`;
    });
  }
  const node = svg('svg', { class: `spooky-web spooky-web-${corner}`, viewBox: `0 0 ${size} ${size}` });
  node.append(svg('path', { d }));
  return node;
}

/** Spinne (von oben gesehen) mit Faden. */
function spider(): HTMLElement {
  const body = svg('svg', { class: 'spooky-spider-body', viewBox: '-30 -30 60 60' });
  const legs = svg('g', { class: 'spooky-legs' });
  for (const side of [-1, 1]) {
    for (const [y, bend] of [[-6, -14], [-2, -6], [2, 6], [6, 14]] as const) {
      legs.append(
        svg('path', { d: `M${side * 4} ${y}Q${side * 16} ${y + bend / 2 - 6} ${side * 24} ${y + bend}` }),
      );
    }
  }
  body.append(
    legs,
    svg('ellipse', { cx: '0', cy: '7', rx: '8', ry: '10', class: 'spooky-abdomen' }),
    svg('circle', { cx: '0', cy: '-5', r: '5.5', class: 'spooky-head' }),
    svg('circle', { cx: '-2', cy: '-7', r: '1.2', class: 'spooky-eye' }),
    svg('circle', { cx: '2', cy: '-7', r: '1.2', class: 'spooky-eye' }),
  );
  return el('div', { class: 'spooky-spider' }, el('div', { class: 'spooky-thread' }), body);
}

/**
 * Gruselmodus für die Nacht: Abdunklung zum Rand hin, Spinnweben in den oberen Ecken, gelegentlich eine Spinne,
 * die sich abseilt und wieder hochklettert, und seltene Blitze.
 *
 *  - Nur transform/opacity-Animationen (Raspberry Pi), höchstens zwei Spinnen gleichzeitig.
 *  - Inhalte bleiben lesbar: die Mitte wird kaum abgedunkelt, Toasts liegen darüber.
 *  - Ein-/Ausblenden weich (keine harten Helligkeitssprünge); Blitze kurz, gedämpft und selten.
 *  - prefers-reduced-motion: nur Abdunklung und Spinnweben, keine Bewegung, keine Blitze.
 */
export class Spooky {
  readonly element: HTMLElement;
  private mode: SpookyMode = 'auto';
  private on = false;
  private readonly fx = {}; // Besitzer der Effekt-Timer (getrennt von der Uhr-Prüfung)
  private readonly flash: HTMLElement;
  private readonly bolt: SVGElement;
  private spiders = 0;
  private boltTimer: TimerHandle | null = null;

  constructor(
    private readonly scheduler: Scheduler,
    private readonly night: { from: number; to: number },
  ) {
    this.flash = el('div', { class: 'spooky-flash' });
    this.bolt = svg('svg', { class: 'spooky-bolt', viewBox: '0 0 100 300', preserveAspectRatio: 'none' });
    this.bolt.append(svg('path', { d: '' }));
    this.element = el(
      'div',
      { class: 'spooky', 'aria-hidden': 'true' },
      el('div', { class: 'spooky-dark' }),
      cobweb('left'),
      cobweb('right'),
      this.flash,
      this.bolt,
    );
    this.scheduler.interval(() => this.evaluate(), 60_000, this, { label: 'spooky-clock', pauseWhenHidden: false });
    this.evaluate();
  }

  get active(): boolean {
    return this.on;
  }

  setMode(mode: SpookyMode): void {
    this.mode = mode;
    this.evaluate();
  }

  evaluate(now: Date = new Date()): void {
    const want = this.mode === 'on' || (this.mode === 'auto' && isNight(now, this.night.from, this.night.to));
    if (want === this.on) return;
    this.on = want;
    this.element.classList.toggle('is-on', want);
    document.documentElement.classList.toggle('is-spooky', want);
    if (want && !prefersReducedMotion()) {
      this.scheduleSpider(rand(4_000, 10_000));
      this.scheduleLightning(rand(15_000, 30_000));
    } else if (!want) {
      this.scheduler.clearOwner(this.fx);
      for (const s of this.element.querySelectorAll('.spooky-spider')) s.remove();
      this.spiders = 0;
    }
  }

  private scheduleSpider(ms: number): void {
    this.scheduler.timeout(() => {
      this.spawnSpider();
      this.scheduleSpider(rand(18_000, 40_000));
    }, ms, this.fx, { label: 'spooky-spider' });
  }

  private spawnSpider(): void {
    if (this.spiders >= 2) return;
    const s = spider();
    const duration = rand(14, 20);
    s.style.setProperty('--x', `${rand(6, 94).toFixed(1)}vw`);
    s.style.setProperty('--drop', `${rand(18, 55).toFixed(1)}vh`);
    s.style.setProperty('--dur', `${duration.toFixed(1)}s`);
    s.style.setProperty('--scale', rand(0.8, 1.25).toFixed(2));
    this.element.append(s);
    this.spiders++;
    this.scheduler.timeout(() => {
      s.remove();
      this.spiders--;
    }, duration * 1000 + 500, this.fx, { label: 'spooky-spider-end' });
  }

  private scheduleLightning(ms: number): void {
    this.scheduler.timeout(() => {
      this.strike();
      this.scheduleLightning(rand(45_000, 120_000));
    }, ms, this.fx, { label: 'spooky-lightning' });
  }

  private strike(): void {
    // Zickzack-Blitz an zufälliger Stelle im oberen Drittel
    let x = rand(30, 70);
    let d = `M${x.toFixed(1)} 0`;
    for (let y = 30; y <= 300; y += rand(25, 45)) {
      x += rand(-14, 14);
      d += `L${x.toFixed(1)} ${Math.min(300, y).toFixed(1)}`;
    }
    this.bolt.firstElementChild?.setAttribute('d', d);
    this.bolt.style.setProperty('--x', `${rand(5, 75).toFixed(1)}vw`);
    this.element.classList.remove('is-striking');
    // Reflow erzwingen, damit die Animation erneut startet
    void this.element.offsetWidth;
    this.element.classList.add('is-striking');
    this.boltTimer?.cancel();
    this.boltTimer = this.scheduler.timeout(() => this.element.classList.remove('is-striking'), 900, this.fx, {
      label: 'spooky-lightning-end',
    });
  }
}
