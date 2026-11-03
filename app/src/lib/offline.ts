/**
 * Descărcarea meditațiilor pentru ascultare fără internet (@capacitor/file-transfer + @capacitor/filesystem).
 * Fișierele stau în Directory.Data (private aplicației, nu apar în galerie / Fișiere)
 * și se șterg la ieșirea din cont și la ștergerea contului.
 */
import { Capacitor } from '@capacitor/core';
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
      const cale = `meditatii/${id}.audio`;
      try {
        await Filesystem.mkdir({ path: 'meditatii', directory: Directory.Data, recursive: true });
      } catch {
        /* există deja */
      }
      const { uri } = await Filesystem.getUri({ path: cale, directory: Directory.Data });
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
    await Filesystem.stat({ path: d.cale, directory: Directory.Data });
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
      await Filesystem.deleteFile({ path: d.cale, directory: Directory.Data });
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
      await Filesystem.rmdir({ path: 'meditatii', directory: Directory.Data, recursive: true });
    } catch {
      /* nu exista */
    }
  }
  harta = {};
  await scrie(CHEI.descarcari, {});
  anunta();
}
