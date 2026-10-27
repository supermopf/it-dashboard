import { el } from '../util/dom';
import type { YouTubeTarget } from '../util/youtube';
import { BasePanel, type PanelContext } from './panel';

/* eslint-disable @typescript-eslint/no-explicit-any */

/**
 * YouTube-Wiedergabe (ersetzt monitor/cache/pageYT.php). Die IFrame-API ist zwingend ein iFrame
 * (Vorgabe von YouTube); es existiert immer höchstens eines und es wird bei destroy() entfernt.
 */

declare global {
  interface Window {
    YT?: any;
    onYouTubeIframeAPIReady?: () => void;
  }
}

let apiPromise: Promise<void> | null = null;

function loadApi(timeoutMs: number): Promise<void> {
  if (window.YT?.Player) return Promise.resolve();
  if (apiPromise) return apiPromise;
  apiPromise = new Promise<void>((resolve, reject) => {
    const t = setTimeout(() => {
      apiPromise = null;
      reject(new Error('YouTube-API nicht erreichbar'));
    }, timeoutMs);
    const prev = window.onYouTubeIframeAPIReady;
    window.onYouTubeIframeAPIReady = () => {
      clearTimeout(t);
      prev?.();
      resolve();
    };
    const s = document.createElement('script');
    s.src = 'https://www.youtube.com/iframe_api';
    s.async = true;
    s.onerror = () => {
      clearTimeout(t);
      apiPromise = null;
      s.remove();
      reject(new Error('YouTube-API konnte nicht geladen werden'));
    };
    document.head.append(s);
  });
  return apiPromise;
}

export interface YouTubePanelOptions {
  target: YouTubeTarget;
  volume: number;
  /** Wird bei Ende, Fehler oder Nichterreichbarkeit aufgerufen (Legacy: LoadPage(CurrentPage)). */
  onDone: (reason: 'ended' | 'error') => void;
  apiTimeoutMs?: number;
}

export class YouTubePanel extends BasePanel {
  private player: any = null;
  private done = false;

  constructor(
    ctx: PanelContext,
    private readonly opts: YouTubePanelOptions,
  ) {
    super('YT', ctx);
    this.dataTimestamp = Date.now();
  }

  protected async onMount(root: HTMLElement): Promise<void> {
    const host = el('div', { class: 'yt-frame' });
    const slot = el('div', { class: 'yt-slot' });
    host.append(slot);
    root.append(el('div', { class: 'yt-panel' }, host));
    try {
      await loadApi(this.opts.apiTimeoutMs ?? 15000);
    } catch (err) {
      this.ctx.log.warn(String(err));
      this.finish('error');
      return;
    }
    if (this.destroyed) return;
    const YT = window.YT;
    this.player = new YT.Player(slot, {
      width: '100%',
      height: '100%',
      videoId: this.opts.target.videoId,
      playerVars: {
        start: this.opts.target.start,
        autoplay: 1,
        controls: 1,
        rel: 0,
        iv_load_policy: 3,
        playsinline: 1,
      },
      events: {
        onReady: (ev: any) => {
          try {
            ev.target.setVolume(Math.round(this.opts.volume * 100));
            ev.target.playVideo();
          } catch {
            /* Player bereits zerstört */
          }
        },
        onStateChange: (ev: any) => {
          if (ev.data === YT.PlayerState.ENDED) this.finish('ended');
        },
        onError: () => this.finish('error'),
      },
    });
  }

  /** Lautstärke aus `update.YTvolume` (0…1). */
  protected override onUpdate(data: unknown): void {
    const v = typeof data === 'number' ? data : NaN;
    if (!Number.isFinite(v)) return;
    try {
      this.player?.setVolume?.(Math.round(v * 100));
    } catch {
      /* ignorieren */
    }
  }

  protected override onDestroy(): void {
    try {
      this.player?.destroy?.();
    } catch {
      /* ignorieren */
    }
    this.player = null;
  }

  private finish(reason: 'ended' | 'error'): void {
    if (this.done) return;
    this.done = true;
    this.opts.onDone(reason);
  }
}
