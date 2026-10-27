import { prefersReducedMotion } from '../util/dom';

/**
 * Schnee (Legacy: jQuery-Snowfall mit 150 DOM-Elementen). Hier: ein Canvas, begrenzte Flockenzahl,
 * höchstens 30 fps, keine Arbeit bei verborgenem Dokument, aus bei prefers-reduced-motion.
 */

interface Flake {
  x: number;
  y: number;
  r: number;
  vy: number;
  vx: number;
}

export class Snow {
  private canvas: HTMLCanvasElement | null = null;
  private ctx2d: CanvasRenderingContext2D | null = null;
  private flakes: Flake[] = [];
  private raf = 0;
  private last = 0;
  private w = 0;
  private h = 0;

  constructor(
    private readonly host: HTMLElement,
    private readonly count: number,
  ) {}

  get running(): boolean {
    return this.canvas !== null;
  }

  set(on: boolean): void {
    if (on && !this.canvas && !prefersReducedMotion()) this.start();
    else if (!on && this.canvas) this.stop();
  }

  private start(): void {
    const c = document.createElement('canvas');
    c.className = 'snow';
    c.setAttribute('aria-hidden', 'true');
    this.host.append(c);
    this.canvas = c;
    this.ctx2d = c.getContext('2d');
    this.resize();
    window.addEventListener('resize', this.resize);
    this.flakes = Array.from({ length: Math.max(0, Math.min(200, this.count)) }, () => this.spawn(true));
    this.raf = requestAnimationFrame(this.frame);
  }

  private stop(): void {
    cancelAnimationFrame(this.raf);
    window.removeEventListener('resize', this.resize);
    this.canvas?.remove();
    this.canvas = null;
    this.ctx2d = null;
    this.flakes = [];
  }

  private readonly resize = (): void => {
    if (!this.canvas) return;
    // Bewusst DPR 1: Schnee braucht keine Retina-Auflösung und kostet so ein Viertel der Pixel.
    this.w = this.canvas.width = window.innerWidth;
    this.h = this.canvas.height = window.innerHeight;
  };

  private spawn(anywhere: boolean): Flake {
    return {
      x: Math.random() * this.w,
      y: anywhere ? Math.random() * this.h : -10,
      r: 2 + Math.random() * 2.5,
      vy: 25 + Math.random() * 35,
      vx: -10 + Math.random() * 20,
    };
  }

  private readonly frame = (t: number): void => {
    if (!this.ctx2d) return;
    this.raf = requestAnimationFrame(this.frame);
    const dt = t - this.last;
    if (dt < 33) return; // ~30 fps
    this.last = t;
    const s = Math.min(0.1, dt / 1000);
    const g = this.ctx2d;
    g.clearRect(0, 0, this.w, this.h);
    g.fillStyle = 'rgba(255,255,255,0.8)';
    g.beginPath();
    for (let i = 0; i < this.flakes.length; i++) {
      const f = this.flakes[i]!;
      f.y += f.vy * s;
      f.x += f.vx * s;
      if (f.y > this.h + 10 || f.x < -10 || f.x > this.w + 10) this.flakes[i] = this.spawn(false);
      g.moveTo(f.x + f.r, f.y);
      g.arc(f.x, f.y, f.r, 0, Math.PI * 2);
    }
    g.fill();
  };
}
