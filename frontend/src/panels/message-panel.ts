import { iconEl } from '../icons';
import { el } from '../util/dom';
import { BasePanel, type PanelContext } from './panel';

/** Einfache Status-/Fallback-Ansicht (Laden, Fehler, keine Daten). */
export class MessagePanel extends BasePanel {
  constructor(
    id: string,
    ctx: PanelContext,
    private readonly kind: 'loading' | 'error' | 'empty',
    private readonly title: string,
    private readonly detail = '',
  ) {
    super(id, ctx);
  }

  protected onMount(root: HTMLElement): void {
    const iconName = this.kind === 'error' ? 'alert' : this.kind === 'empty' ? 'check' : 'clock';
    root.append(
      el(
        'div',
        { class: `message-panel message-${this.kind}`, role: this.kind === 'error' ? 'alert' : 'status' },
        iconEl(iconName, 'icon icon-xl'),
        el('h2', { text: this.title }),
        this.detail ? el('p', { text: this.detail }) : null,
      ),
    );
  }
}
