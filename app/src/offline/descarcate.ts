/**
 * Meditațiile descărcate, din Preferences `ah_descarcate` (scris de puntea din ah-sp-aplicatie.php):
 *   [{id, titlu, durata, fisier, poza, dir, descarcat}]
 * `fisier` și `poza` sunt căi relative la folderul `dir` (ex. „meditatii/12.mp3”, „meditatii/12.jpg”);
 * acceptăm și o adresă completă file://… sau o cale absolută, dacă puntea o scrie așa.
 * `dir`: folderul Capacitor. Puntea scrie LIBRARY_NO_CLOUD pe iOS (fără copie în iCloud) și DATA pe Android
 * (docs/arhitectura.md §4); dacă lipsește, folosim același folder ca puntea (dosarImplicit).
 */

export type Dosar = 'LIBRARY' | 'LIBRARY_NO_CLOUD' | 'DATA' | 'CACHE';
const DOSARE: Dosar[] = ['LIBRARY', 'LIBRARY_NO_CLOUD', 'DATA', 'CACHE'];

/** Folderul în care scrie puntea (var DIR din ah-sp-aplicatie.php): iOS LIBRARY_NO_CLOUD, Android DATA. */
export function dosarImplicit(platforma: string): Dosar {
  return platforma === 'ios' ? 'LIBRARY_NO_CLOUD' : 'DATA';
}

export interface Descarcata {
  id: string;
  titlu: string;
  /** secunde (0 = necunoscută) */
  durata: number;
  fisier: string;
  poza: string;
  dir: Dosar;
  /** ISO sau milisecunde; doar pentru ordonare */
  descarcat: number;
}

const sir = (v: unknown) => (typeof v === 'string' ? v.trim() : typeof v === 'number' && isFinite(v) ? String(v) : '');

/** „12:30”, „750”, 750 sau „12 min” → secunde */
export function secunde(v: unknown): number {
  if (typeof v === 'number') return isFinite(v) && v > 0 ? Math.round(v) : 0;
  const s = sir(v);
  if (!s) return 0;
  const p = s.split(':');
  if (p.length >= 2 && p.every((q) => /^\d+$/.test(q))) return p.reduce((a, q) => a * 60 + +q, 0);
  const m = /^(\d+(?:[.,]\d+)?)\s*(min|m)\b/i.exec(s);
  if (m) return Math.round(parseFloat(m[1].replace(',', '.')) * 60);
  return /^\d+$/.test(s) ? +s : 0;
}

/** Valori sigure pentru o cale de fișier: fără „..”, fără scheme ciudate. */
export function caleSigura(c: string): boolean {
  if (!c) return false;
  if (/^file:\/\//i.test(c)) return !/\.\.(\/|$)/.test(c);
  if (/^[a-z][a-z0-9+.-]*:/i.test(c)) return false;
  return !c.split('/').includes('..');
}

export function citesteDescarcate(brut: unknown, implicit: Dosar = 'DATA'): Descarcata[] {
  if (!Array.isArray(brut)) return [];
  const vazute = new Set<string>();
  const out: Descarcata[] = [];
  for (const r of brut) {
    if (!r || typeof r !== 'object') continue;
    const o = r as Record<string, unknown>;
    const id = sir(o.id);
    const fisier = sir(o.fisier);
    if (!id || vazute.has(id) || !caleSigura(fisier)) continue;
    vazute.add(id);
    const poza = sir(o.poza);
    const d = o.descarcat;
    out.push({
      id,
      titlu: sir(o.titlu) || 'Meditație',
      durata: secunde(o.durata),
      fisier,
      poza: caleSigura(poza) ? poza : '',
      dir: DOSARE.includes(o.dir as Dosar) ? (o.dir as Dosar) : implicit,
      descarcat: typeof d === 'number' ? d : Date.parse(sir(d)) || 0,
    });
  }
  // cele mai noi întâi
  return out.sort((a, b) => b.descarcat - a.descarcat);
}

/** 754 → „12 min”; 40 → „1 min”; 0 → „” */
export function durataScurta(s: number): string {
  if (!s) return '';
  const m = Math.max(1, Math.round(s / 60));
  return m >= 60 ? `${Math.floor(m / 60)} h ${m % 60 ? (m % 60) + ' min' : ''}`.trim() : `${m} min`;
}

/** 754.3 → „12:34” */
export function ceas(s: number): string {
  if (!isFinite(s) || s < 0) s = 0;
  s = Math.floor(s);
  const h = Math.floor(s / 3600);
  const m = Math.floor((s % 3600) / 60);
  const ss = String(s % 60).padStart(2, '0');
  return h ? `${h}:${String(m).padStart(2, '0')}:${ss}` : `${m}:${ss}`;
}
