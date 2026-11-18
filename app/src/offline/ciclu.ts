/**
 * Faza de azi pentru pagina offline: EXACT regulile modulului CI din Spațiul meu (sn235),
 * scrise ca funcții pure (fără localStorage, fără „azi” ascuns). Testul diferențial
 * (ciclu.diferential.test.ts) le compară cu codul original copiat în fixtures/sn235-ci.js.
 *
 * Datele vin din Preferences `ah_ciclu` = {starturi:[YYYY-MM-DD…], lung, men, sit, actualizat},
 * scrise de puntea din fragmentul ah-sp-aplicatie.php (aceleași câmpuri ca localStorage.ahsp_ciclu).
 */

export type Anotimp = 'iarna' | 'primavara' | 'vara' | 'toamna';
export type Situatie = 'natural' | 'hormonal' | 'sarcina' | 'alaptare' | 'perimenopauza';

export interface Faza {
  f: Anotimp;
  /** prima zi a fazei (1 = prima zi a menstruației) */
  a: number;
  /** ultima zi a fazei */
  b: number;
}

export interface CicluNormalizat {
  /** cel mult 13 date, de la cea mai nouă la cea mai veche */
  starturi: string[];
  lung: number;
  men: number;
  sit?: unknown;
}

/** Ce întoarce CI.azi(): ziua de azi în ciclul curent. */
export interface ZiCiclu {
  zi: number;
  L: number;
  m: number;
  f: Anotimp;
  /** prima zi a ciclului curent, în zile de la 1970-01-01 (UTC) */
  s0: number;
  fz: Faza[];
  /** ziua estimată a următoarei menstruații (zile de la 1970-01-01) */
  urm: number;
  /** ziua de azi a trecut de lungimea ciclului (menstruația întârzie) */
  peste: boolean;
}

/** CI.F: numele anotimpurilor, culorile și temele (aceleași texte ca pe site). */
export const F: Record<Anotimp, { nume: string; faza: string; tema: string; c: string }> = {
  iarna: { nume: 'Iarna interioară', faza: 'Faza menstruală', tema: 'Odihnă, eliberare și regenerare', c: '#7A5A75' },
  primavara: { nume: 'Primăvara interioară', faza: 'Faza foliculară', tema: 'Hrănirea resurselor care susțin creșterea', c: '#56704D' },
  vara: { nume: 'Vara interioară', faza: 'Faza ovulatorie', tema: 'Cum ne păstrăm Yin-ul în perioadele de expansiune Yang', c: '#9E533A' },
  toamna: { nume: 'Toamna interioară', faza: 'Faza luteală', tema: 'Întoarcerea către interior și conservarea energiei', c: '#85622E' },
};

/** CI.SIT: situațiile (textele de pe site). */
export const SIT: Record<Situatie, { t: string; d: string; txt?: string; nota?: string }> = {
  natural: { t: 'Ciclu natural', d: 'Fără contracepție hormonală.' },
  hormonal: {
    t: 'Contracepție hormonală',
    d: 'Pastile, inel, plasture, implant, sterilet hormonal.',
    txt: 'Cu contracepția hormonală nu ai ovulație, așa că anotimpurile și curbele hormonilor nu se aplică corpului tău. Poți nota în continuare sângerarea și cum te simți.',
  },
  sarcina: {
    t: 'Sunt însărcinată',
    d: 'Oprim estimările.',
    txt: 'Cât timp ești însărcinată nu calculăm fazele. Poți nota în continuare cum te simți, iar când ciclul revine, alegi din nou „Ciclu natural”.',
  },
  alaptare: {
    t: 'Alăptez',
    d: 'Ciclul poate lipsi sau fi neregulat.',
    nota: 'Alăptezi: ciclul poate lipsi sau fi neregulat, așa că estimările sunt mai puțin sigure.',
  },
  perimenopauza: {
    t: 'Perimenopauză',
    d: 'Ciclurile se pot schimba de la o lună la alta.',
    nota: 'Perimenopauză: ciclurile se pot lungi sau scurta, așa că estimările sunt mai puțin sigure.',
  },
};

const LUNI = ['ianuarie', 'februarie', 'martie', 'aprilie', 'mai', 'iunie', 'iulie', 'august', 'septembrie', 'octombrie', 'noiembrie', 'decembrie'];

/** CI.zi0: 'YYYY-MM-DD' → zile de la 1970-01-01 (UTC). */
export function zi0(s: string): number {
  const p = s.split('-');
  return Date.UTC(+p[0], +p[1] - 1, +p[2]) / 864e5;
}

/** CI.azi0: ziua calendaristică locală a lui `d`, în zile de la 1970-01-01. */
export function azi0(d: Date = new Date()): number {
  return Date.UTC(d.getFullYear(), d.getMonth(), d.getDate()) / 864e5;
}

/** CI.iso */
export function iso(z: number): string {
  return new Date(z * 864e5).toISOString().slice(0, 10);
}

/** CI.data: „3 noiembrie” */
export function data(z: number): string {
  const d = new Date(z * 864e5);
  return d.getUTCDate() + ' ' + LUNI[d.getUTCMonth()];
}

/**
 * CI.d, fără cheia veche ah_ciclu_v1 (puntea a unit-o deja când a scris ah_ciclu):
 * date valide, fără dubluri, cele mai noi 13; lung în 21–40 (altfel 28), men în 2–9 (altfel 5).
 */
export function normalizeaza(x: unknown): CicluNormalizat | null {
  const o = (x && typeof x === 'object' ? x : null) as Record<string, unknown> | null;
  let st: unknown[] = [];
  if (o && o.starturi) st = st.concat(o.starturi as never);
  st = st
    .filter((q, i) => /^\d{4}-\d{2}-\d{2}$/.test(q as string) && st.indexOf(q) === i)
    .sort()
    .reverse()
    .slice(0, 13);
  if (!st.length) return null;
  const cl = (n: unknown, a: number, b: number, d: number) => {
    const v = +(n as number);
    return v >= a && v <= b ? v : d;
  };
  return {
    starturi: st as string[],
    lung: cl((o && o.lung) || undefined, 21, 40, 28),
    men: cl((o && o.men) || undefined, 2, 9, 5),
    sit: o ? o.sit : undefined,
  };
}

/** CI.lung: media ultimelor (cel mult 6) cicluri de 21–40 zile, dacă sunt cel puțin 2; altfel lungimea dată. */
export function lung(x: { starturi: string[]; lung?: number }): number {
  const st = x.starturi.map(zi0);
  const dif: number[] = [];
  for (let i = 0; i + 1 < st.length && dif.length < 6; i++) {
    const v = st[i] - st[i + 1];
    if (v >= 21 && v <= 40) dif.push(v);
  }
  return dif.length >= 2 ? Math.round(dif.reduce((a, b) => a + b, 0) / dif.length) : x.lung || 28;
}

/** CI.faze: oS = max(L-16, m+2), oE = min(oS+4, L-2). */
export function faze(L: number, m: number): Faza[] {
  const oS = Math.max(L - 16, m + 2);
  const oE = Math.min(oS + 4, L - 2);
  return [
    { f: 'iarna', a: 1, b: m },
    { f: 'primavara', a: m + 1, b: oS - 1 },
    { f: 'vara', a: oS, b: oE },
    { f: 'toamna', a: oE + 1, b: L },
  ];
}

/** CI.sit: situația salvată, dacă e una cunoscută; altfel „natural”. */
export function situatie(x: unknown): Situatie {
  const s = x && typeof x === 'object' ? (x as Record<string, unknown>).sit : undefined;
  return typeof s === 'string' && Object.prototype.hasOwnProperty.call(SIT, s) ? (s as Situatie) : 'natural';
}

/** CI.azi: unde e ziua `azi` (zile de la 1970-01-01) în ciclul care a început la cel mai nou start. */
export function ziua(xBrut: unknown, azi: number): ZiCiclu | null {
  const x = normalizeaza(xBrut);
  if (!x) return null;
  const L = lung(x);
  const m = x.men || 5;
  const s0 = zi0(x.starturi[0]);
  const zi = azi - s0 + 1;
  if (zi < 1) return null;
  const fz = faze(L, m);
  let f: Anotimp = 'toamna';
  fz.forEach((p) => {
    if (zi >= p.a && zi <= p.b) f = p.f;
  });
  return { zi, L, m, f, s0, fz, urm: s0 + L, peste: zi > L };
}

/** Citește valoarea din Preferences (un șir JSON) fără să arunce. */
export function citesteJson(v: string | null | undefined): unknown {
  if (!v) return null;
  try {
    return JSON.parse(v);
  } catch {
    return null;
  }
}
