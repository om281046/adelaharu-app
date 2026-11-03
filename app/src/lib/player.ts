/**
 * Playerul audio (un singur <audio> pentru toată aplicația), cu Media Session pentru
 * ecranul blocat și reluarea de unde a rămas (poziția locală + POST /meditatii/{id}/pozitie).
 * Sunetul în fundal: UIBackgroundModes=audio pe iOS; pe Android WebView-ul continuă redarea,
 * dar fără serviciu de prim-plan (vezi README, „Probleme deschise”).
 */
import { api } from '../api';
import { CHEI, citeste, scrie } from './stocare';

export interface ElementRedare {
  tip: 'meditatie' | 'lectie';
  /** id-ul meditației sau cheia lecției */
  id: string;
  titlu: string;
  subtitlu: string;
  poza?: string;
  durata: number;
  /** pentru lecții: slugul cursului */
  curs?: string;
  /** ruta (hash) unde se întoarce mini-playerul */
  ruta: string;
}

export interface StarePlayer {
  curent: ElementRedare | null;
  canta: boolean;
  timp: number;
  durata: number;
  seIncarca: boolean;
  eroare: string | null;
}

type Ascultator = (s: StarePlayer) => void;

const A: HTMLAudioElement = typeof Audio !== 'undefined' ? new Audio() : ({} as HTMLAudioElement);
A.preload = 'metadata';
const ascultatori = new Set<Ascultator>();
let stare: StarePlayer = { curent: null, canta: false, timp: 0, durata: 0, seIncarca: false, eroare: null };
let pozitii: Record<string, number> = {};
let ultimaLocal = 0;
let ultimaServer = 0;
let pornit = false;

function emite(p: Partial<StarePlayer>) {
  stare = { ...stare, ...p };
  ascultatori.forEach((f) => f(stare));
}

export function asculta(f: Ascultator): () => void {
  ascultatori.add(f);
  f(stare);
  return () => ascultatori.delete(f);
}
export function starePlayer(): StarePlayer {
  return stare;
}

const cheiePoz = (e: ElementRedare) => e.tip + ':' + e.id;

export async function initPlayer() {
  if (pornit) return;
  pornit = true;
  pozitii = await citeste<Record<string, number>>(CHEI.pozitii, {});
  if (typeof A.addEventListener !== 'function') return;
  A.addEventListener('timeupdate', () => {
    emite({ timp: A.currentTime, durata: A.duration || stare.curent?.durata || 0 });
    const acum = Date.now();
    if (stare.curent && acum - ultimaLocal > 4000) {
      ultimaLocal = acum;
      salveazaPozitie(false);
    }
    if (stare.curent && acum - ultimaServer > 30000) {
      ultimaServer = acum;
      trimitePozitie(false);
    }
    actualizeazaPozitieSesiune();
  });
  A.addEventListener('play', () => emite({ canta: true }));
  A.addEventListener('playing', () => emite({ canta: true, seIncarca: false }));
  A.addEventListener('waiting', () => emite({ seIncarca: true }));
  A.addEventListener('pause', () => {
    emite({ canta: false });
    salveazaPozitie(false);
    trimitePozitie(false);
  });
  A.addEventListener('loadedmetadata', () => emite({ durata: A.duration || stare.curent?.durata || 0 }));
  A.addEventListener('ended', () => {
    emite({ canta: false });
    salveazaPozitie(true);
    trimitePozitie(true);
  });
  A.addEventListener('error', () => {
    if (!stare.curent) return;
    emite({ canta: false, seIncarca: false, eroare: 'Nu am putut porni sunetul. Verifică, te rog, conexiunea la internet sau descarcă meditația.' });
  });
}

function salveazaPozitie(terminat: boolean) {
  const e = stare.curent;
  if (!e) return;
  pozitii[cheiePoz(e)] = terminat ? 0 : Math.floor(A.currentTime || 0);
  void scrie(CHEI.pozitii, pozitii);
  void scrie(CHEI.ultima, { ...e, timp: terminat ? 0 : Math.floor(A.currentTime || 0), terminat, la: Date.now() });
}

function trimitePozitie(terminat: boolean) {
  const e = stare.curent;
  if (!e || e.tip !== 'meditatie') return;
  api.pozitie(+e.id, terminat ? e.durata : A.currentTime || 0, terminat).catch(() => {
    /* fără internet: rămâne poziția locală */
  });
}

/** Unde a rămas (local); serverul poate da și el o poziție (Meditatie.pozitie). */
export function pozitieSalvata(tip: ElementRedare['tip'], id: string): number {
  return pozitii[tip + ':' + id] || 0;
}

export async function reda(e: ElementRedare, url: string, start = 0) {
  await initPlayer();
  emite({ eroare: null });
  if (stare.curent && cheiePoz(stare.curent) === cheiePoz(e) && A.src) {
    void porneste();
    return;
  }
  if (stare.curent) salveazaPozitie(false);
  emite({ curent: e, timp: 0, durata: e.durata, seIncarca: true });
  A.src = url;
  A.load();
  const s = start > 5 && (!e.durata || start < e.durata - 10) ? start : 0;
  if (s) {
    const o = () => {
      try {
        A.currentTime = s;
      } catch {
        /* nimic */
      }
      A.removeEventListener('loadedmetadata', o);
    };
    A.addEventListener('loadedmetadata', o);
  }
  sesiune(e);
  void scrie(CHEI.ultima, { ...e, timp: s, terminat: false, la: Date.now() });
  await porneste();
}

async function porneste() {
  try {
    await A.play();
  } catch {
    emite({ canta: false, seIncarca: false });
  }
}

export function comuta() {
  if (!stare.curent) return;
  if (A.paused) void porneste();
  else A.pause();
}
export function pauza() {
  if (stare.curent && !A.paused) A.pause();
}
export function sari(sec: number) {
  if (!stare.curent) return;
  const d = A.duration || stare.curent.durata || 1e9;
  A.currentTime = Math.max(0, Math.min(d - 1, (A.currentTime || 0) + sec));
}
export function cauta(t: number) {
  if (!stare.curent) return;
  A.currentTime = Math.max(0, t);
}
export function opreste() {
  if (!stare.curent) return;
  salveazaPozitie(false);
  trimitePozitie(false);
  A.pause();
  A.removeAttribute('src');
  A.load();
  emite({ curent: null, canta: false, timp: 0, durata: 0 });
}
export async function uitaTot() {
  opreste();
  pozitii = {};
  await scrie(CHEI.pozitii, {});
  await scrie(CHEI.ultima, null);
}

/* ---- ecranul blocat ---- */
function sesiune(e: ElementRedare) {
  if (typeof navigator === 'undefined' || !('mediaSession' in navigator)) return;
  try {
    navigator.mediaSession.metadata = new MediaMetadata({
      title: e.titlu,
      artist: 'Adela Haru',
      album: e.subtitlu || 'Inner Temple',
      artwork: e.poza ? [{ src: e.poza, sizes: '512x512' }] : [{ src: 'icon-512.png', sizes: '512x512', type: 'image/png' }],
    });
    const ms = navigator.mediaSession;
    ms.setActionHandler('play', () => void porneste());
    ms.setActionHandler('pause', () => A.pause());
    ms.setActionHandler('seekbackward', () => sari(-15));
    ms.setActionHandler('seekforward', () => sari(15));
    ms.setActionHandler('seekto', (d) => {
      if (d && d.seekTime != null) cauta(d.seekTime);
    });
    ms.setActionHandler('stop', () => opreste());
  } catch {
    /* unele WebView-uri nu au toate acțiunile */
  }
}

function actualizeazaPozitieSesiune() {
  if (typeof navigator === 'undefined' || !('mediaSession' in navigator)) return;
  try {
    if (A.duration && isFinite(A.duration)) {
      navigator.mediaSession.setPositionState({ duration: A.duration, position: Math.min(A.currentTime, A.duration), playbackRate: A.playbackRate || 1 });
    }
    navigator.mediaSession.playbackState = A.paused ? 'paused' : 'playing';
  } catch {
    /* nimic */
  }
}

export interface UltimaRedare extends ElementRedare {
  timp: number;
  terminat: boolean;
  la: number;
}
export function ultimaRedare(): Promise<UltimaRedare | null> {
  return citeste<UltimaRedare | null>(CHEI.ultima, null);
}
