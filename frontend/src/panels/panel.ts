import type { DashboardConfig } from '../config';
import type { Logger } from '../core/log';
import type { Scheduler } from '../core/scheduler';
import { Scope } from '../core/scope';
import type { MediaManager } from '../media/media-manager';

export interface PanelContext {
  config: DashboardConfig;
  scheduler: Scheduler;
  media: MediaManager;
  log: Logger;
  /** WebSocket-Befehl senden (z. B. `!bus middle`). */
  send(message: string): boolean;
  /** Absolute URL zu einer Datei unterhalb von monitor/. */
  asset(path: string): string;
}

/**
 * Lebenszyklus einer Ansicht:
 *   mount(root) → activate() ⇄ deactivate() → destroy()
 * update(data) wirkt nur im aktiven Zustand.
 */
export interface Panel {
  readonly id: string;
  /** Kurze Beschreibung des Datenstands für die Kopfzeile, z. B. Zeitpunkt der letzten Aktualisierung. */
  readonly dataTimestamp: number | null;
  readonly stale: boolean;
  mount(root: HTMLElement): void | Promise<void>;
  update(data: unknown): void;
  activate(): void;
  deactivate(): void;
  destroy(): void;
}

let liveInstances = 0;
export const panelStats = {
  get live(): number {
    return liveInstances;
  },
};

export abstract class BasePanel implements Panel {
  protected root: HTMLElement | null = null;
  /** Lebt von mount() bis destroy(). */
  protected readonly scope: Scope;
  /** Lebt nur zwischen activate() und deactivate(). */
  protected readonly activeScope: Scope;
  protected active = false;
  protected destroyed = false;
  dataTimestamp: number | null = null;
  stale = false;

  constructor(
    readonly id: string,
    protected readonly ctx: PanelContext,
  ) {
    this.scope = new Scope(ctx.scheduler);
    this.activeScope = new Scope(ctx.scheduler);
    liveInstances++;
  }

  get isActive(): boolean {
    return this.active;
  }

  async mount(root: HTMLElement): Promise<void> {
    this.root = root;
    await this.onMount(root);
  }

  update(data: unknown): void {
    if (!this.active || this.destroyed) return;
    this.onUpdate(data);
  }

  activate(): void {
    if (this.active || this.destroyed) return;
    this.active = true;
    this.onActivate();
  }

  deactivate(): void {
    if (!this.active) return;
    this.active = false;
    this.activeScope.reset();
    this.onDeactivate();
  }

  destroy(): void {
    if (this.destroyed) return;
    this.deactivate();
    this.destroyed = true;
    try {
      this.onDestroy();
    } finally {
      if (this.root) this.ctx.media.unloadWithin(this.root);
      this.activeScope.dispose();
      this.scope.dispose();
      this.root?.replaceChildren();
      this.root = null;
      liveInstances--;
    }
  }

  /** Zahl der aktuell registrierten Listener (Tests/Debug). */
  get listenerCount(): number {
    return this.scope.listeners + this.activeScope.listeners;
  }

  protected abstract onMount(root: HTMLElement): void | Promise<void>;
  protected onUpdate(_data: unknown): void {}
  protected onActivate(): void {}
  protected onDeactivate(): void {}
  protected onDestroy(): void {}
}
