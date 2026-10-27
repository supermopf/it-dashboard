import type { DashboardConfig } from '../config';
import type { Scope } from '../core/scope';
import { iconEl } from '../icons';
import { el, setClass, setText } from '../util/dom';
import { formatClock, formatTime } from '../util/format';

/**
 * Kopfzeile: Marke, aktuelle Ansicht, Seiten-Navigation mit Status, Countdown, Datenstand, Health, Uhr.
 * Schreibt nur bei Änderungen ins DOM. Der Countdown-Balken nutzt transform: scaleX (keine Layout-Animation).
 */

export type Health = 'ok' | 'degraded' | 'down';

export class Topbar {
  readonly element: HTMLElement;
  private readonly title: HTMLElement;
  private readonly countdown: HTMLElement;
  private readonly freshness: HTMLElement;
  private readonly clock: HTMLElement;
  private readonly progress: HTMLElement;
  private readonly critterTrack: HTMLElement;
  private readonly wsChip: HTMLElement;
  private readonly navItems = new Map<string, HTMLElement>();

  constructor(
    private readonly cfg: DashboardConfig,
    scope: Scope,
    onNavigate: (page: string) => void,
  ) {
    this.title = el('span', { class: 'topbar-title' });
    this.countdown = el('span', { class: 'topbar-countdown' });
    this.freshness = el('span', { class: 'topbar-freshness' });
    this.clock = el('time', { class: 'topbar-clock' });
    this.wsChip = el('span', { class: 'chip chip-ok', role: 'status' });

    const nav = el('nav', { class: 'topbar-nav', 'aria-label': 'Ansichten' });
    for (const p of cfg.pages) {
      const btn = el(
        'button',
        { class: 'nav-item', type: 'button', 'data-page': p.id, title: p.title },
        iconEl(p.icon, 'icon nav-icon'),
        el('span', { class: 'nav-label', text: p.title }),
        el('span', { class: 'nav-status', 'aria-hidden': 'true' }),
      );
      this.navItems.set(p.id, btn);
      nav.append(btn);
    }
    scope.listen(nav, 'click', (ev: Event) => {
      const btn = (ev.target as Element).closest<HTMLElement>('[data-page]');
      if (btn?.dataset.page) onNavigate(btn.dataset.page);
    });

    this.progress = el('div', { class: 'cycle-progress-bar' });
    this.critterTrack = el('div', { class: 'critter-track', 'aria-hidden': 'true' });

    this.element = el(
      'header',
      { class: 'topbar' },
      el(
        'div',
        { class: 'topbar-row' },
        el('div', { class: 'topbar-brand' }, iconEl('gauge', 'icon brand-icon'), el('span', { text: 'IT-Dashboard' })),
        el('div', { class: 'topbar-context' }, this.title, this.freshness),
        el('div', { class: 'topbar-meta' }, this.wsChip, this.countdown, this.clock),
      ),
      nav,
      el('div', { class: 'cycle-progress', 'aria-hidden': 'true' }, this.progress, this.critterTrack),
    );
    this.setHealth('degraded', 'Verbinde …');
  }

  get critterHost(): HTMLElement {
    return this.critterTrack;
  }

  tickClock(now: Date): void {
    setText(this.clock, formatClock(now));
  }

  setActivePage(page: string | null, label: string): void {
    setText(this.title, label);
    for (const [id, item] of this.navItems) {
      const on = id === page;
      setClass(item, 'is-active', on);
      if (on) item.setAttribute('aria-current', 'page');
      else item.removeAttribute('aria-current');
    }
  }

  /** Legacy: „Seitenwechsel in: CD“ bzw. „Deaktiviert“; Balken = CD / CycleTime. */
  setCountdown(cd: number, cycle: boolean): void {
    setText(this.countdown, cycle ? `Wechsel in ${Math.max(0, Math.round(cd))} s` : 'Rotation aus');
    setClass(this.countdown, 'is-off', !cycle);
    const ratio = cycle ? Math.max(0, Math.min(1, cd / this.cfg.cycleSeconds)) : 0;
    const value = ratio.toFixed(3);
    if (this.progress.style.getPropertyValue('--p') !== value) {
      this.progress.style.setProperty('--p', value);
      this.critterTrack.style.setProperty('--p', value);
    }
  }

  setFreshness(ts: number | null, stale: boolean): void {
    if (ts === null) {
      setText(this.freshness, '');
      return;
    }
    setText(this.freshness, stale ? `Veraltet · Stand ${formatTime(ts)}` : `Stand ${formatTime(ts)}`);
    setClass(this.freshness, 'is-stale', stale);
  }

  setHealth(h: Health, label: string): void {
    const cls = h === 'ok' ? 'chip chip-ok' : h === 'degraded' ? 'chip chip-warn' : 'chip chip-error';
    if (this.wsChip.className !== cls) this.wsChip.className = cls;
    if (this.wsChip.dataset.label !== label) {
      this.wsChip.dataset.label = label;
      this.wsChip.replaceChildren(iconEl(h === 'ok' ? 'check' : h === 'degraded' ? 'clock' : 'wifi-off'), label);
    }
  }

  /** Status je Seite. Nur Alarmseiten zeigen „warning“ als Warnung (AUDIT §5.1). */
  setPageStatus(status: Record<string, string>): void {
    for (const [id, item] of this.navItems) {
      const alerting = this.cfg.alertPages.includes(id) && status[id] === 'warning';
      const ok = this.cfg.alertPages.includes(id) && status[id] === 'ok';
      setClass(item, 'has-alert', alerting);
      setClass(item, 'is-ok', ok);
      const s = alerting ? 'Warnung' : ok ? 'OK' : '';
      const label = item.querySelector('.nav-status')!;
      setText(label, alerting ? '!' : '');
      const title = s ? `${this.pageTitle(id)} – ${s}` : this.pageTitle(id);
      if (item.title !== title) item.title = title;
    }
  }

  private pageTitle(id: string): string {
    return this.cfg.pages.find((p) => p.id === id)?.title ?? id;
  }
}
