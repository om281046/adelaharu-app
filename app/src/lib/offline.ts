/**
 * Descărcarea meditațiilor pentru ascultare fără internet (@capacitor/file-transfer + @capacitor/filesystem).
 * Fișierele stau în Directory.LibraryNoCloud (pe iOS Library/NoCloud: privat, fără backup în iCloud,
 * conform regulilor Apple de stocare; pe Android fișierele private ale aplicației, cu backup oprit)
 * și se șterg la ieșirea din cont și la ștergerea contului.
 * Se salvează cu extensia reală (.mp3 / .m4a …): pe iOS Capacitor alege tipul conținutului după extensie,
 * iar <audio> din WKWebView poate refuza un fișier servit ca application/octet-stream.
 */
import { Capacitor, CapacitorHttp } from '@capacitor/core';
import { Directory, Filesystem } from '@capacitor/filesystem';
import { FileTransfer } from '@capacitor/file-transfer';
import { api } from '../api';
import { CHEI, citeste, scrie } from './stocare';

export interface Descarcare {
  id: number;
  titlu: string;
  cale: string;
  uri: string;
  la: number;
  /** în browser (demo) nu salvăm fișierul, doar marcăm */
  web?: boolean;
}

type Harta = Record<string, Descarcare>;
const DIR = Directory.LibraryNoCloud;

const EXT_DUPA_TIP: Record<string, string> = {
  'audio/mpeg': 'mp3', 'audio/mp3': 'mp3', 'audio/mpeg3': 'mp3',
  'audio/mp4': 'm4a', 'audio/x-m4a': 'm4a', 'audio/m4a': 'm4a', 'audio/aac': 'aac', 'audio/x-aac': 'aac',
  'audio/wav': 'wav', 'audio/x-wav': 'wav', 'audio/wave': 'wav',
};
const EXT_CUNOSCUTE = new Set(['mp3', 'm4a', 'aac', 'wav', 'mp4']);

/** Extensia după Content-Type (sau după adresă, dacă are una); implicit mp3, ca pe site (audio/mpeg). */
export function extensieAudio(contentType: string | null | undefined, url: string): string {
  const tip = (contentType || '').split(';')[0].trim().toLowerCase();
  if (EXT_DUPA_TIP[tip]) return EXT_DUPA_TIP[tip];
  try {
    const m = /\.([a-z0-9]{2,4})$/i.exec(new URL(url).pathname);
    if (m && EXT_CUNOSCUTE.has(m[1].toLowerCase())) return m[1].toLowerCase();
  } catch {
    /* adresă ciudată */
  }
  return 'mp3';
}

/** Cere doar primul octet (adresa suportă Range), ca să aflăm Content-Type fără să descărcăm tot. */
async function tipContinut(url: string): Promise<string | null> {
  try {
    const r = await CapacitorHttp.get({ url, headers: { Range: 'bytes=0-0' }, responseType: 'blob' });
    const h = r.headers || {};
    const k = Object.keys(h).find((x) => x.toLowerCase() === 'content-type');
    return k ? String(h[k]) : null;
  } catch {
    return null;
  }
}
let harta: Harta | null = null;
const ascultatori = new Set<() => void>();
const progres = new Map<number, number>();

async function incarca(): Promise<Harta> {
  if (!harta) harta = await citeste<Harta>(CHEI.descarcari, {});
  return harta;
}
function anunta() {
  ascultatori.forEach((f) => f());
}
export function ascultaDescarcari(f: () => void): () => void {
  ascultatori.add(f);
  return () => ascultatori.delete(f);
}

export async function descarcate(): Promise<Harta> {
  return { ...(await incarca()) };
}
export function progresDescarcare(id: number): number | undefined {
  return progres.get(id);
}

export async function descarca(id: number, titlu: string): Promise<void> {
  const h = await incarca();
  progres.set(id, 0);
  anunta();
  try {
    const { url } = await api.redare(id);
    let d: Descarcare;
    if (!Capacitor.isNativePlatform()) {
      // în browser: doar simulăm (demo); redarea folosește tot adresa semnată
      for (let p = 0.2; p <= 1; p += 0.2) {
        progres.set(id, p);
        anunta();
        await new Promise((r) => setTimeout(r, 120));
      }
      d = { id, titlu, cale: '', uri: '', la: Date.now(), web: true };
    } else {
      const cale = `meditatii/${id}.${extensieAudio(await tipContinut(url), url)}`;
      try {
        await Filesystem.mkdir({ path: 'meditatii', directory: DIR, recursive: true });
      } catch {
        /* există deja */
      }
      const { uri } = await Filesystem.getUri({ path: cale, directory: DIR });
      const asc = await FileTransfer.addListener('progress', (e) => {
        if (e.url === url && e.contentLength) {
          progres.set(id, e.bytes / e.contentLength);
          anunta();
        }
      });
      try {
        await FileTransfer.downloadFile({ url, path: uri, progress: true });
        d = { id, titlu, cale, uri, la: Date.now() };
      } finally {
        await asc.remove();
      }
    }
    h[String(id)] = d;
    await scrie(CHEI.descarcari, h);
  } finally {
    progres.delete(id);
    anunta();
  }
}

/** Adresa locală de redare, dacă meditația e descărcată (null altfel). */
export async function urlLocal(id: number): Promise<string | null> {
  const d = (await incarca())[String(id)];
  if (!d || d.web || !d.uri) return null;
  try {
    await Filesystem.stat({ path: d.cale, directory: DIR });
  } catch {
    return null;
  }
  return Capacitor.convertFileSrc(d.uri);
}

export async function stergeDescarcare(id: number): Promise<void> {
  const h = await incarca();
  const d = h[String(id)];
  if (d && !d.web && d.cale) {
    try {
      await Filesystem.deleteFile({ path: d.cale, directory: DIR });
    } catch {
      /* deja șters */
    }
  }
  delete h[String(id)];
  await scrie(CHEI.descarcari, h);
  anunta();
}

export async function stergeToateDescarcarile(): Promise<void> {
  if (Capacitor.isNativePlatform()) {
    try {
      await Filesystem.rmdir({ path: 'meditatii', directory: DIR, recursive: true });
    } catch {
      /* nu exista */
    }
  }
  harta = {};
  await scrie(CHEI.descarcari, {});
  anunta();
}
