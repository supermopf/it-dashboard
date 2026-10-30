import type { Scheduler, TimerHandle } from '../core/scheduler';
import { iconEl } from '../icons';
import { el } from '../util/dom';
import { clampText } from '../util/format';
import { safeMediaUrl } from '../util/url';

export const NO_TITLE_TEXT = 'Kein Songtitel verfügbar';

/** Songtitel-Hinweis unten mittig (Legacy: #toast, 12 s). Nur opacity/transform. */
export class SongToast {
  readonly element: HTMLElement;
  private readonly img: HTMLImageElement;
  private readonly text: HTMLElement;
  private timer: TimerHandle | null = null;

  constructor(private readonly scheduler: Scheduler) {
    this.img = el('img', { class: 'song-icon', alt: '', decoding: 'async' });
    this.img.hidden = true;
    this.img.addEventListener('error', () => (this.img.hidden = true));
    this.text = el('span', { class: 'song-text' });
    this.element = el(
      'div',
      { class: 'song-toast', role: 'status', 'aria-live': 'polite' },
      el('span', { class: 'song-icon-wrap' }, this.img, iconEl('music', 'icon song-fallback')),
      this.text,
    );
  }

  /**
   * Titel anzeigen (Legacy launch_toast / show_toast). Ohne Titel erscheint ein Hinweistext – früher blieb
   * dann nur das (meist weiße) Senderlogo als leeres Feld stehen.
   */
  show(iconUrl: string, title: string): void {
    const src = safeMediaUrl(iconUrl);
    if (src) {
      this.img.hidden = false;
      if (this.img.src !== src) this.img.src = src;
    } else {
      this.img.hidden = true;
    }
    const t = clampText(title, 120);
    this.text.textContent = t || NO_TITLE_TEXT;
    this.text.classList.toggle('is-empty', !t);
    this.element.classList.add('is-visible');
    this.timer?.cancel();
    this.timer = this.scheduler.timeout(() => this.element.classList.remove('is-visible'), 12000, this, {
      label: 'song-toast',
    });
  }
}
