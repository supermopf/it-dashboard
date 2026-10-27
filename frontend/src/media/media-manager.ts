/**
 * Zentrale Verwaltung aller Audio- und Video-Elemente.
 *  - höchstens `maxActiveVideos` Videos spielen gleichzeitig (Standard 1)
 *  - `unload()` entlädt Medien vollständig (pause, src entfernen, load()) – gibt Decoder-Speicher frei
 */

export class MediaManager {
  private readonly media = new Set<HTMLMediaElement>();

  constructor(private readonly maxActiveVideos = 1) {}

  register(m: HTMLMediaElement): void {
    if (this.media.has(m)) return;
    this.media.add(m);
    m.addEventListener('play', this.onPlay);
  }

  /** Abspielen unter Einhaltung des Video-Limits. Fehler (Autoplay-Policy, 404) werden geschluckt. */
  async play(m: HTMLMediaElement): Promise<boolean> {
    this.register(m);
    if (m instanceof HTMLVideoElement) this.enforceVideoLimit(m);
    try {
      await m.play();
      return true;
    } catch {
      return false;
    }
  }

  pause(m: HTMLMediaElement): void {
    try {
      m.pause();
    } catch {
      /* ignorieren */
    }
  }

  /** Vollständig entladen und abmelden. */
  unload(m: HTMLMediaElement): void {
    this.pause(m);
    m.removeEventListener('play', this.onPlay);
    this.media.delete(m);
    if (m.hasAttribute('src') || m.querySelector('source')) {
      m.removeAttribute('src');
      for (const s of [...m.querySelectorAll('source')]) s.remove();
      try {
        m.load();
      } catch {
        /* jsdom / bereits entfernt */
      }
    }
  }

  unloadWithin(root: ParentNode): void {
    for (const m of [...root.querySelectorAll<HTMLMediaElement>('audio,video')]) this.unload(m);
  }

  pauseWithin(root: ParentNode): void {
    for (const m of root.querySelectorAll<HTMLMediaElement>('audio,video')) this.pause(m);
  }

  get registered(): number {
    return this.media.size;
  }

  counts(): { audio: number; video: number; playingAudio: number; playingVideo: number } {
    let audio = 0;
    let video = 0;
    let playingAudio = 0;
    let playingVideo = 0;
    for (const m of this.media) {
      const playing = !m.paused && !m.ended;
      if (m instanceof HTMLVideoElement) {
        video++;
        if (playing) playingVideo++;
      } else {
        audio++;
        if (playing) playingAudio++;
      }
    }
    return { audio, video, playingAudio, playingVideo };
  }

  private enforceVideoLimit(starting: HTMLMediaElement): void {
    const playing = [...this.media].filter(
      (m): m is HTMLVideoElement => m instanceof HTMLVideoElement && m !== starting && !m.paused && !m.ended,
    );
    const excess = playing.length - (this.maxActiveVideos - 1);
    for (let i = 0; i < excess; i++) this.pause(playing[i]!);
  }

  private readonly onPlay = (ev: Event): void => {
    const m = ev.currentTarget as HTMLMediaElement;
    if (m instanceof HTMLVideoElement) this.enforceVideoLimit(m);
  };
}
