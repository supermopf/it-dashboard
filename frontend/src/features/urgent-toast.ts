import type { Logger } from '../core/log';
import type { Scheduler, TimerHandle } from '../core/scheduler';
import type { MediaManager } from '../media/media-manager';
import { el } from '../util/dom';
import { isReloadPayload, sanitizeHtml, textOnly } from '../util/sanitize';
import { mediaKind, safeMediaUrl } from '../util/url';

/**
 * Urgent-Toast (`!urgent {json}`), funktional wie monitor/js/app.js:305-373:
 * ToastColor, ToastTextColor, ToastSubject, ToastBody, ToastPicture (Bild oder mp4/webm),
 * ToastVideoNoRepeat, ToastSound, ToastVolume, ToastTime.
 *
 * Abweichungen (dokumentiert in docs/MIGRATION.md):
 *  - Subject/Body werden über eine HTML-Allowlist bereinigt; Skripte werden nicht ausgeführt.
 *    Ausnahme: der Legacy-Reload-Toast `<script>location.reload()</script>` löst einen kontrollierten Reload aus.
 *  - Ein neuer Toast setzt den Schließ-Timer zurück (Legacy: alter Timer schloss den neuen Toast vorzeitig).
 */

export interface UrgentPayload {
  ToastSubject?: unknown;
  ToastBody?: unknown;
  ToastPicture?: unknown;
  ToastSound?: unknown;
  ToastVolume?: unknown;
  ToastTime?: unknown;
  ToastColor?: unknown;
  ToastTextColor?: unknown;
  ToastVideoNoRepeat?: unknown;
}

const COLOR = /^(#[0-9a-f]{3,8}|rgba?\([\d\s.,%]+\)|hsla?\([\d\s.,%deg]+\)|[a-z]{3,20})$/i;

export function safeColor(v: unknown, fallback: string): string {
  const s = typeof v === 'string' ? v.trim() : '';
  return s && COLOR.test(s) ? s : fallback;
}

export function toastDuration(v: unknown): number {
  const n = typeof v === 'number' ? v : typeof v === 'string' && v.trim() !== '' ? Number(v) : NaN;
  if (!Number.isFinite(n) || n <= 0) return 30000;
  return Math.min(60 * 60 * 1000, Math.max(1000, n));
}

export function toastVolume(v: unknown): number {
  const n = typeof v === 'number' ? v : typeof v === 'string' && v.trim() !== '' ? Number(v) : NaN;
  if (!Number.isFinite(n)) return 0.5;
  return Math.min(1, Math.max(0, n));
}

export interface UrgentToastDeps {
  scheduler: Scheduler;
  media: MediaManager;
  log: Logger;
  defaultSound: string;
  /** Wird für den Legacy-Reload-Toast aufgerufen. */
  onReloadRequest: () => void;
  allowLegacyScripts: boolean;
}

export class UrgentToast {
  readonly element: HTMLElement;
  private readonly header: HTMLElement;
  private readonly titleEl: HTMLElement;
  private readonly mediaEl: HTMLElement;
  private readonly bodyEl: HTMLElement;
  private closeTimer: TimerHandle | null = null;
  private sound: HTMLAudioElement | null = null;
  private video: HTMLVideoElement | null = null;
  private open = false;

  constructor(private readonly d: UrgentToastDeps) {
    this.titleEl = el('h2', { class: 'urgent-title', id: 'urgent-title' });
    this.header = el('div', { class: 'urgent-header' }, this.titleEl);
    this.mediaEl = el('div', { class: 'urgent-media' });
    this.bodyEl = el('div', { class: 'urgent-body' });
    this.element = el(
      'div',
      { class: 'urgent', role: 'alertdialog', 'aria-modal': 'true', 'aria-labelledby': 'urgent-title', hidden: true },
      el('div', { class: 'urgent-backdrop' }),
      el('div', { class: 'urgent-card' }, this.header, el('div', { class: 'urgent-content' }, this.mediaEl, this.bodyEl)),
    );
  }

  get isOpen(): boolean {
    return this.open;
  }

  show(p: UrgentPayload): void {
    const rawBody = typeof p.ToastBody === 'string' ? p.ToastBody : '';
    if (rawBody && isReloadPayload(rawBody)) {
      this.d.log.info('Reload-Toast empfangen');
      this.d.onReloadRequest();
      return;
    }
    this.clearMedia();
    this.closeTimer?.cancel();

    this.header.style.backgroundColor = safeColor(p.ToastColor, '#FA2A00');
    this.header.style.color = safeColor(p.ToastTextColor, '#ffffff');

    const subject = typeof p.ToastSubject === 'string' ? p.ToastSubject : '';
    this.titleEl.replaceChildren(sanitizeHtml(subject));
    this.bodyEl.replaceChildren(sanitizeHtml(rawBody));
    if (this.d.allowLegacyScripts && /<script/i.test(rawBody)) this.runLegacyScripts(rawBody);

    const pic = typeof p.ToastPicture === 'string' ? safeMediaUrl(p.ToastPicture) : null;
    const noRepeat = p.ToastVideoNoRepeat === 'true' || p.ToastVideoNoRepeat === true;
    if (pic && mediaKind(pic) === 'video') {
      const v = el('video', { class: 'urgent-video', playsinline: true, preload: 'auto' });
      v.src = pic;
      v.loop = !noRepeat;
      if (noRepeat) {
        v.addEventListener('play', () => this.closeTimer?.cancel(), { once: true });
        v.addEventListener('ended', () => this.hide(), { once: true });
      }
      v.addEventListener('error', () => v.replaceWith(el('p', { class: 'urgent-media-error', text: 'Video nicht verfügbar' })), {
        once: true,
      });
      this.video = v;
      this.mediaEl.append(v);
      void this.d.media.play(v).then((ok) => {
        // Autoplay mit Ton blockiert? Dann stumm starten.
        if (!ok && this.video === v) {
          v.muted = true;
          void this.d.media.play(v);
        }
      });
    } else if (pic) {
      const img = el('img', { class: 'urgent-image', alt: '', decoding: 'async' });
      img.addEventListener('error', () => img.remove(), { once: true });
      img.src = pic;
      this.mediaEl.append(img);
    }

    const soundUrl = (typeof p.ToastSound === 'string' && safeMediaUrl(p.ToastSound)) || this.d.defaultSound;
    const a = new Audio();
    a.src = soundUrl;
    a.volume = toastVolume(p.ToastVolume);
    this.sound = a;
    void this.d.media.play(a);

    this.element.hidden = false;
    this.open = true;
    requestAnimationFrame(() => this.element.classList.add('is-open'));
    this.closeTimer = this.d.scheduler.timeout(() => this.hide(), toastDuration(p.ToastTime), this, {
      pauseWhenHidden: false,
      label: 'urgent-close',
    });
    this.d.log.info(`Toast: ${textOnly(subject).slice(0, 60)}`);
  }

  hide(): void {
    if (!this.open) return;
    this.open = false;
    this.closeTimer?.cancel();
    this.closeTimer = null;
    this.element.classList.remove('is-open');
    this.clearMedia();
    this.d.scheduler.timeout(
      () => {
        if (!this.open) {
          this.element.hidden = true;
          this.mediaEl.replaceChildren();
        }
      },
      200,
      this,
      { pauseWhenHidden: false, label: 'urgent-hide' },
    );
  }

  private clearMedia(): void {
    if (this.video) this.d.media.unload(this.video);
    if (this.sound) this.d.media.unload(this.sound);
    this.video = null;
    this.sound = null;
    this.mediaEl.replaceChildren();
  }

  /** Nur bei toastScripts = 'legacy' (unsicher, für Übergangszeit). */
  private runLegacyScripts(body: string): void {
    for (const m of body.matchAll(/<script\b[^>]*>([\s\S]*?)<\/script\s*>/gi)) {
      try {
        new Function(m[1] ?? '')();
      } catch (err) {
        this.d.log.warn(`Toast-Skriptfehler: ${String(err)}`);
      }
    }
  }
}
