import type { Logger } from '../core/log';
import { afterTransition, el, prefersReducedMotion } from '../util/dom';
import type { Panel } from './panel';

/**
 * Hält genau eine aktive Ansicht. Beim Wechsel wird die neue Ansicht zuerst unsichtbar (opacity 0) gemountet –
 * die teure Arbeit (DOM, Charts) passiert, während nichts animiert –, danach folgt ein kurzer Crossfade
 * (nur opacity/transform). Die alte Ansicht wird anschließend zerstört. Fehler beim Mounten werden
 * abgefangen (Fehlergrenze) und durch eine Fallback-Ansicht ersetzt.
 */

export interface PanelHostEvents {
  onShown?(panel: Panel, ms: number): void;
  onError?(id: string, err: unknown): void;
}

const ENTER_MS = 180;
const EXIT_MS = 140;

export class PanelHost {
  private current: { panel: Panel; view: HTMLElement } | null = null;
  private seq = 0;
  private chain: Promise<void> = Promise.resolve();
  private inFlight = 0;

  constructor(
    private readonly container: HTMLElement,
    private readonly log: Logger,
    private readonly events: PanelHostEvents = {},
  ) {}

  get active(): Panel | null {
    return this.current?.panel ?? null;
  }

  /** true, solange ein Ansichtswechsel (inkl. Übergang und Zerstören der alten Ansicht) läuft. */
  get busy(): boolean {
    return this.inFlight > 0;
  }

  /**
   * Zeigt eine Ansicht. Aufrufe werden serialisiert; kommt während des Mountens ein neuer Wechsel,
   * gewinnt der neueste (die veraltete Ansicht wird verworfen, ohne sichtbar zu werden).
   */
  show(factory: () => Panel, fallback: (err: unknown) => Panel): Promise<void> {
    const token = ++this.seq;
    this.inFlight++;
    this.chain = this.chain
      .then(() => this.swap(token, factory, fallback))
      .catch((err) => {
        this.log.error(`Ansichtswechsel fehlgeschlagen: ${String(err)}`);
      })
      .finally(() => {
        this.inFlight--;
      });
    return this.chain;
  }

  /** Aktuelle Ansicht deaktivieren (z. B. wenn ein Vollbild-Overlay wie YouTube übernimmt). */
  pauseActive(): void {
    this.current?.panel.deactivate();
  }

  resumeActive(): void {
    this.current?.panel.activate();
  }

  destroyAll(): void {
    this.current?.panel.destroy();
    this.current?.view.remove();
    this.current = null;
  }

  private async swap(token: number, factory: () => Panel, fallback: (err: unknown) => Panel): Promise<void> {
    if (token !== this.seq) return;
    const t0 = performance.now();
    const view = el('section', { class: 'view is-entering', 'aria-live': 'polite' });
    // Neue Ansicht VOR der alten einfügen: getElementById/$("#id") der Legacy-Skripte finden so die
    // Elemente der neuen Ansicht, auch wenn beide kurzzeitig gleiche IDs enthalten.
    this.container.prepend(view);

    let panel: Panel;
    try {
      panel = factory();
      await panel.mount(view);
    } catch (err) {
      this.events.onError?.('mount', err);
      this.log.error(`Ansicht konnte nicht gemountet werden: ${String(err)}`);
      view.replaceChildren();
      panel = fallback(err);
      await panel.mount(view);
    }

    if (token !== this.seq) {
      // Inzwischen wurde ein neuerer Wechsel angefordert.
      panel.destroy();
      view.remove();
      return;
    }

    const prev = this.current;
    this.current = { panel, view };
    prev?.panel.deactivate();
    panel.activate();

    const reduced = prefersReducedMotion();
    // Zwei Frames abwarten, damit der Startzustand (opacity 0) sicher gerendert ist.
    await new Promise<void>((r) => requestAnimationFrame(() => requestAnimationFrame(() => r())));
    view.classList.remove('is-entering');
    view.classList.add('is-active');
    if (prev) {
      prev.view.classList.remove('is-active');
      prev.view.classList.add('is-leaving');
    }
    await Promise.all([
      reduced ? Promise.resolve() : afterTransition(view, ENTER_MS + 80),
      prev && !reduced ? afterTransition(prev.view, EXIT_MS + 80) : Promise.resolve(),
    ]);
    if (prev) {
      prev.panel.destroy();
      prev.view.remove();
    }
    this.events.onShown?.(panel, performance.now() - t0);
  }
}
