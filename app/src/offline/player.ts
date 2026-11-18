/**
 * Playerul paginii offline: un singur <audio>, cu
 *  - Media Session (iOS/WKWebView: titlu, poză și butoane pe ecranul blocat; sunetul merge în fundal
 *    datorită UIBackgroundModes=audio și sesiunii AVAudioSession .playback din AppDelegate);
 *  - pluginul nativ AhMedia pe Android (WebView-ul nu are comenzi pe ecranul blocat și nici serviciu
 *    de prim-plan; AhMedia le face pe amândouă și trimite înapoi apăsările).
 */
import { areAhMedia, mediaActualizeaza, mediaAsculta, mediaOpreste, type ActiuneMedia } from './cap';

export const SALT = 15;

export interface Piesa {
  id: string;
  titlu: string;
  /** adresa pentru <audio> (serverul local Capacitor) */
  src: string;
  /** adresa pozei pentru pagină și Media Session */
  poza?: string;
  /** file://… pentru notificarea Android */
  pozaNativ?: string;
  durata: number;
}

export interface StarePlayer {
  piesa: Piesa | null;
  canta: boolean;
  timp: number;
  durata: number;
  seIncarca: boolean;
  eroare: boolean;
}

type Ascultator = (s: StarePlayer) => void;

export class Player {
  readonly a: HTMLAudioElement;
  private s: StarePlayer = { piesa: null, canta: false, timp: 0, durata: 0, seIncarca: false, eroare: false };
  private ascultatori = new Set<Ascultator>();
  private ultimaPoz = 0;
  private ultimaNativ = 0;

  constructor(a: HTMLAudioElement = new Audio()) {
    this.a = a;
    a.preload = 'metadata';
    const d = () => (isFinite(a.duration) && a.duration > 0 ? a.duration : this.s.piesa?.durata || 0);
    a.addEventListener('timeupdate', () => {
      this.emite({ timp: a.currentTime, durata: d() });
      const acum = Date.now();
      if (acum - this.ultimaPoz > 5000) {
        this.ultimaPoz = acum;
        this.pozitieSesiune();
      }
      if (acum - this.ultimaNativ > 15000) {
        this.ultimaNativ = acum;
        this.nativ();
      }
    });
    a.addEventListener('loadedmetadata', () => {
      this.emite({ durata: d() });
      this.pozitieSesiune();
      this.nativ();
    });
    a.addEventListener('play', () => this.emite({ canta: true, eroare: false }));
    a.addEventListener('playing', () => {
      this.emite({ canta: true, seIncarca: false });
      this.stareSesiune('playing');
      this.nativ();
    });
    a.addEventListener('waiting', () => this.emite({ seIncarca: true }));
    a.addEventListener('pause', () => {
      this.emite({ canta: false });
      this.stareSesiune('paused');
      this.nativ();
    });
    a.addEventListener('seeked', () => {
      this.pozitieSesiune();
      this.nativ();
    });
    a.addEventListener('ended', () => {
      this.emite({ canta: false, timp: 0 });
      this.stareSesiune('paused');
      this.nativ();
    });
    a.addEventListener('error', () => {
      if (!this.s.piesa) return;
      this.emite({ canta: false, seIncarca: false, eroare: true });
      this.stareSesiune('none');
      mediaOpreste();
    });
    this.butoaneSesiune();
    void mediaAsculta((x) => this.actiune(x));
  }

  get stare(): StarePlayer {
    return this.s;
  }

  asculta(f: Ascultator): () => void {
    this.ascultatori.add(f);
    f(this.s);
    return () => this.ascultatori.delete(f);
  }

  private emite(p: Partial<StarePlayer>) {
    this.s = { ...this.s, ...p };
    this.ascultatori.forEach((f) => f(this.s));
  }

  /** Pornește piesa (sau o reia, dacă e deja aleasă). */
  async reda(p: Piesa): Promise<void> {
    if (this.s.piesa?.id !== p.id) {
      this.emite({ piesa: p, timp: 0, durata: p.durata, seIncarca: true, eroare: false });
      this.a.src = p.src;
      this.a.load();
      this.metadateSesiune(p);
    }
    await this.porneste();
  }

  async porneste(): Promise<void> {
    if (!this.s.piesa) return;
    try {
      await this.a.play();
    } catch (e) {
      // NotAllowedError: pornește la următoarea atingere; altfel e o eroare de fișier
      if ((e as Error)?.name !== 'NotAllowedError' && (e as Error)?.name !== 'AbortError') this.emite({ eroare: true });
      this.emite({ seIncarca: false });
    }
  }

  pauza(): void {
    this.a.pause();
  }

  comuta(): void {
    if (this.a.paused) void this.porneste();
    else this.pauza();
  }

  sari(sec: number): void {
    const d = this.s.durata || this.a.duration || 0;
    let t = (this.a.currentTime || 0) + sec;
    if (d) t = Math.min(t, Math.max(0, d - 0.5));
    this.a.currentTime = Math.max(0, t);
    this.emite({ timp: this.a.currentTime });
  }

  mergiLa(t: number): void {
    if (!isFinite(t)) return;
    this.a.currentTime = Math.max(0, t);
    this.emite({ timp: this.a.currentTime });
  }

  /** Oprește tot (și notificarea de pe Android). */
  opreste(): void {
    this.a.pause();
    this.a.removeAttribute('src');
    this.a.load();
    this.emite({ piesa: null, canta: false, timp: 0, durata: 0, seIncarca: false, eroare: false });
    this.stareSesiune('none');
    try {
      if ('mediaSession' in navigator) navigator.mediaSession.metadata = null;
    } catch {
      /* nimic */
    }
    mediaOpreste();
  }

  /** Apăsări venite din afara paginii (notificarea Android, căștile). */
  actiune(x: ActiuneMedia): void {
    switch (x.tip) {
      case 'play':
        void this.porneste();
        break;
      case 'pause':
        this.pauza();
        break;
      case 'inainte':
        this.sari(SALT);
        break;
      case 'inapoi':
        this.sari(-SALT);
        break;
      case 'seek':
        this.mergiLa(x.pozitie);
        break;
      case 'stop':
        this.opreste();
        break;
    }
  }

  /* ---------- Media Session (W3C) ---------- */

  private metadateSesiune(p: Piesa) {
    if (!('mediaSession' in navigator) || typeof MediaMetadata === 'undefined') return;
    try {
      navigator.mediaSession.metadata = new MediaMetadata({
        title: p.titlu,
        artist: 'Adela Haru',
        album: 'Inner Temple',
        artwork: p.poza ? [{ src: p.poza, sizes: '512x512', type: 'image/jpeg' }] : [],
      });
    } catch {
      /* nimic */
    }
  }

  private butoaneSesiune() {
    if (!('mediaSession' in navigator)) return;
    const ms = navigator.mediaSession;
    const pune = (a: MediaSessionAction, f: MediaSessionActionHandler) => {
      try {
        ms.setActionHandler(a, f);
      } catch {
        /* acțiune nesuportată de acest WebView */
      }
    };
    pune('play', () => void this.porneste());
    pune('pause', () => this.pauza());
    pune('stop', () => this.opreste());
    pune('seekbackward', (d) => this.sari(-(d.seekOffset || SALT)));
    pune('seekforward', (d) => this.sari(d.seekOffset || SALT));
    pune('seekto', (d) => {
      if (typeof d.seekTime === 'number') this.mergiLa(d.seekTime);
    });
    // fără piesa anterioară/următoare: pe iOS apar astfel butoanele de ±15 s
    pune('previoustrack', null as unknown as MediaSessionActionHandler);
    pune('nexttrack', null as unknown as MediaSessionActionHandler);
  }

  private stareSesiune(s: MediaSessionPlaybackState) {
    try {
      if ('mediaSession' in navigator) navigator.mediaSession.playbackState = s;
    } catch {
      /* nimic */
    }
  }

  private pozitieSesiune() {
    const d = this.a.duration;
    if (!('mediaSession' in navigator) || !isFinite(d) || d <= 0) return;
    try {
      navigator.mediaSession.setPositionState?.({ duration: d, playbackRate: this.a.playbackRate || 1, position: Math.min(this.a.currentTime || 0, d) });
    } catch {
      /* nimic */
    }
  }

  /* ---------- Android: AhMedia ---------- */

  private nativ() {
    const p = this.s.piesa;
    if (!p || !areAhMedia()) return;
    const d = isFinite(this.a.duration) && this.a.duration > 0 ? this.a.duration : p.durata;
    mediaActualizeaza({
      titlu: p.titlu,
      artist: 'Adela Haru',
      poza: p.pozaNativ,
      durata: d || 0,
      pozitie: this.a.currentTime || 0,
      canta: !this.a.paused && !this.a.ended,
    });
  }
}
