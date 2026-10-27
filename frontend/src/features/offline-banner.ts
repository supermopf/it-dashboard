import { iconEl } from '../icons';
import { el, setText } from '../util/dom';
import { formatTime } from '../util/format';

/** Hinweis bei Verbindungsverlust mit Zeitpunkt der letzten erfolgreichen Aktualisierung. */
export class OfflineBanner {
  readonly element: HTMLElement;
  private readonly text: HTMLElement;
  private visible = false;

  constructor() {
    this.text = el('span', { class: 'offline-text' });
    this.element = el(
      'div',
      { class: 'offline-banner', role: 'status', 'aria-live': 'assertive', hidden: true },
      iconEl('wifi-off'),
      this.text,
    );
  }

  get isVisible(): boolean {
    return this.visible;
  }

  show(reason: string, lastOk: number | null): void {
    const suffix = lastOk ? ` · Letzte erfolgreiche Aktualisierung ${formatTime(lastOk)}` : '';
    setText(this.text, `${reason}${suffix}`);
    if (!this.visible) {
      this.visible = true;
      this.element.hidden = false;
      requestAnimationFrame(() => this.element.classList.add('is-visible'));
    }
  }

  hide(): void {
    if (!this.visible) return;
    this.visible = false;
    this.element.classList.remove('is-visible');
    this.element.hidden = true;
  }
}
