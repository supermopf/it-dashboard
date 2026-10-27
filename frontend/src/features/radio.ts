import type { Logger } from '../core/log';
import type { MediaManager } from '../media/media-manager';
import { safeMediaUrl } from '../util/url';

/**
 * Internetradio als Zustandsautomat. Legacy (app.js:465-484) setzte jede Sekunde `src=""`, solange das
 * Radio aus war; hier wird nur bei Zustandswechseln gehandelt.
 * Bedingung wie Legacy: Radio an && kein YouTube && benanntes Dashboard.
 */
export class RadioController {
  private readonly audio: HTMLAudioElement;
  private station = '';
  private playing = false;

  constructor(
    private readonly media: MediaManager,
    private readonly log: Logger,
  ) {
    this.audio = new Audio();
    this.audio.preload = 'none';
    this.media.register(this.audio);
    // Stream abgerissen → einmal neu laden (z. B. nach Netzwerkunterbrechung).
    this.audio.addEventListener('error', () => {
      if (this.playing) this.log.warn('Radio-Stream-Fehler');
    });
  }

  get isPlaying(): boolean {
    return this.playing;
  }

  apply(shouldPlay: boolean, station: string, volume: number): void {
    const url = safeMediaUrl(station) ?? '';
    if (Number.isFinite(volume)) this.audio.volume = Math.min(1, Math.max(0, volume));
    if (shouldPlay && url) {
      if (!this.playing || url !== this.station) {
        this.station = url;
        this.playing = true;
        this.audio.src = url;
        void this.media.play(this.audio);
      }
    } else if (this.playing) {
      this.playing = false;
      this.station = '';
      this.media.pause(this.audio);
      this.audio.removeAttribute('src');
      try {
        this.audio.load();
      } catch {
        /* ignorieren */
      }
    }
  }
}
