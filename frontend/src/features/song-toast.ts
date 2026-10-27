import type { Scheduler, TimerHandle } from '../core/scheduler';
import { iconEl } from '../icons';
import { el } from '../util/dom';
import { clampText } from '../util/format';
import { safeMediaUrl } from '../util/url';

/** Songtitel-Hinweis unten mittig (Legacy: #toast, 12 s). Nur opacity/transform. */
export class SongToast {
  readonly element: HTMLElement;
  private readonly img: HTMLImageElement;
  private readonly text: HTMLElement;
  private timer: TimerHandle | null = null;

  constructor(private readonly scheduler: Scheduler) {
    this.img = el('img', { class: 'song-icon', alt: '', decoding: 'async' });
    this.img.addEventListener('error', () => (this.img.hidden = true));
    this.text = el('span', { class: 'song-text' });
    this.element = el(
      'div',
      { class: 'song-toast', role: 'status', 'aria-live': 'polite' },
      el('span', { class: 'song-icon-wrap' }, this.img, iconEl('music', 'icon song-fallback')),
      this.text,
    );
  }

  /** Neuer Titel (Legacy launch_toast). */
  show(iconUrl: string, title: string): void {
    const src = safeMediaUrl(iconUrl);
    if (src) {
      this.img.hidden = false;
      if (this.img.src !== src) this.img.src = src;
    } else {
      this.img.hidden = true;
    }
    this.text.textContent = clampText(title, 120);
    this.replay();
  }

  /** Legacy show_toast: letzten Inhalt erneut zeigen (`!songtitle`). */
  replay(): void {
    this.element.classList.add('is-visible');
    this.timer?.cancel();
    this.timer = this.scheduler.timeout(() => this.element.classList.remove('is-visible'), 12000, this, {
      label: 'song-toast',
    });
  }
}
