import type { Scope } from '../core/scope';
import { el } from '../util/dom';

/**
 * Bus/Brötchen (Legacy app.js:238-287): `!bus start` → Fahrt nach rechts (8,5 s) → `!bus middle` senden;
 * `!bus end` → Rückfahrt → `!var BusActive False`. Zwischen 10 und 12 Uhr fährt das Brötchen.
 * Bewegung per transform (linear, da konstante Fahrt) statt jQuery.animate({left}).
 */

const TRIP_MS = 8500;

export class Bus {
  readonly element: HTMLElement;
  private readonly img: HTMLImageElement;
  private busy = false;

  constructor(
    private readonly scope: Scope,
    private readonly asset: (p: string) => string,
    private readonly send: (m: string) => void,
  ) {
    this.img = el('img', { class: 'bus-img', alt: '', decoding: 'async' });
    this.element = el('div', { class: 'bus-lane', hidden: true, 'aria-hidden': 'true' }, el('div', { class: 'bus-car' }, this.img));
  }

  handle(arg: string, busActive: boolean, now: Date = new Date()): void {
    if (arg === 'start') this.start(busActive, now);
    else if (arg === 'end') this.end();
  }

  private start(busActive: boolean, now: Date): void {
    if (busActive || this.busy) return; // Legacy: „BUS IS BUSY“
    this.busy = true;
    this.send('!var BusActive True');
    const h = now.getHours();
    const broetchen = h >= 10 && h < 12;
    this.img.src = this.asset(broetchen ? 'img/Broetchen.png' : 'img/bus.gif');
    // Legacy: der Bus fährt gespiegelt (class „flip“) nach rechts, das Brötchen ungespiegelt.
    this.img.classList.toggle('flip', !broetchen);
    this.element.classList.remove('to-right', 'to-left');
    this.element.hidden = false;
    requestAnimationFrame(() => requestAnimationFrame(() => this.element.classList.add('to-right')));
    this.scope.timeout(
      () => {
        this.img.classList.toggle('flip');
        this.send('!bus middle');
      },
      TRIP_MS,
      { label: 'bus', pauseWhenHidden: false },
    );
  }

  private end(): void {
    if (this.element.hidden) {
      this.busy = false;
      this.send('!var BusActive False');
      return;
    }
    this.element.classList.remove('to-right');
    this.element.classList.add('to-left');
    this.scope.timeout(
      () => {
        this.element.hidden = true;
        this.element.classList.remove('to-left');
        this.img.classList.add('flip');
        this.busy = false;
        this.send('!var BusActive False');
      },
      TRIP_MS,
      { label: 'bus', pauseWhenHidden: false },
    );
  }
}
