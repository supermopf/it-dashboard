import type { MediaManager } from '../media/media-manager';
import { el } from '../util/dom';

/**
 * Tier-Easter-Egg (Legacy app.js:381-452): Bei `Cat=true` läuft ein Tier-GIF mit dem Countdown-Balken mit.
 * Auswahl `rnd` (1…6) wird bei jedem Seitenwechsel neu gewürfelt. Auf Seite 9 erscheint der Bus.
 * Tiere nur auf benannten Dashboards; der Bus auf Seite 9 auch ohne Namen (wie Legacy).
 */

interface Critter {
  img: string;
  sound: string | null;
  volume: number;
  cls: string;
}

const CRITTERS: Record<number, Critter> = {
  1: { img: 'img/cat.gif', sound: 'res/meow.mp3', volume: 0.1, cls: 'critter-cat' },
  2: { img: 'img/dog.gif', sound: 'res/bark.mp3', volume: 0.4, cls: 'critter-dog' },
  3: { img: 'img/dino.gif', sound: null, volume: 0, cls: 'critter-dino' },
  4: { img: 'img/dino2.gif', sound: null, volume: 0, cls: 'critter-dino2' },
  5: { img: 'img/dk.gif', sound: null, volume: 0, cls: 'critter-dk' },
  6: { img: 'img/michael.gif', sound: 'res/heehee.mp3', volume: 0.3, cls: 'critter-michael' },
};

export class Critters {
  private rnd = 1;
  private shown = false;
  private sound: HTMLAudioElement | null = null;

  constructor(
    private readonly host: HTMLElement,
    private readonly media: MediaManager,
    private readonly asset: (p: string) => string,
    private readonly named: boolean,
  ) {}

  /** Legacy: rnd = Math.floor(Math.random()*6)+1 bei jedem LoadPage. */
  reroll(random: () => number = Math.random): void {
    this.rnd = Math.floor(random() * 6) + 1;
  }

  apply(cat: boolean, page: number | null): void {
    if (!cat) {
      if (this.shown || this.host.childElementCount) this.clear();
      return;
    }
    if (page === 9) {
      if (!this.host.querySelector('.critter-bus')) {
        this.host.replaceChildren(this.img('img/bus.gif', 'critter-bus'));
      }
      return;
    }
    if (this.shown || !this.named) return;
    this.shown = true;
    const c = CRITTERS[this.rnd] ?? CRITTERS[1]!;
    this.host.replaceChildren(this.img(c.img, c.cls));
    if (c.sound) {
      this.sound?.remove();
      const a = new Audio();
      a.src = this.asset(c.sound);
      a.volume = c.volume;
      this.sound = a;
      void this.media.play(a);
    }
  }

  private clear(): void {
    this.host.replaceChildren();
    this.shown = false;
    if (this.sound) this.media.unload(this.sound);
    this.sound = null;
  }

  private img(src: string, cls: string): HTMLImageElement {
    const i = el('img', { class: `critter ${cls}`, alt: '', decoding: 'async' });
    i.src = this.asset(src);
    return i;
  }
}
