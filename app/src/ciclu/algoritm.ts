/**
 * Calculul fazelor ciclului, portat EXACT din reference/sn41.php
 * (scriptul paginii /fazele-ciclului-feminin/: utc, ymd, plus, dif, lim, fazaZilei,
 * unde, medie, startNou, validarea formularului, „următoarele faze”, luna).
 * Funcții pure: nu ating stocarea, nu citesc ceasul decât prin parametrul `azi`.
 * Documentat în docs/ciclu-algoritm.md.
 */

export type CheieFaza = 'menstruala' | 'foliculara' | 'ovulatorie' | 'premenstruala';

/** Ordinea fazelor, ca ORD din sn41. */
export const ORD: readonly CheieFaza[] = ['menstruala', 'foliculara', 'ovulatorie', 'premenstruala'];

/** Starea ciclului, aceeași formă ca obiectul S din sn41 (fără jurnal). */
export interface StareCiclu {
  /** prima zi a ultimei menstruații cunoscute (Y-m-d) */
  s: string;
  /** câte zile are ciclul (21–45) */
  c: number;
  /** câte zile sângerează (2–10) */
  m: number;
  /** ultimele starturi cunoscute, sortate, cel mult 6 */
  starturi: string[];
}

export type Limite = Record<CheieFaza, [number, number]>;

export interface Unde {
  /** ziua din ciclu, de la 1 */
  zi: number;
  /** al câtelea ciclu de la startul aplicat (0 = ciclul startului) */
  nr: number;
  faza: CheieFaza;
  /** prima zi a ciclului curent (Y-m-d) */
  inceput: string;
}

const ZI_MS = 864e5;

/* ---- date (identice cu sn41) ---- */
export function utc(ymdStr: string): number {
  const p = ymdStr.split('-');
  return Date.UTC(+p[0], +p[1] - 1, +p[2]);
}
export function ymd(t: number): string {
  const d = new Date(t);
  return d.getUTCFullYear() + '-' + ('0' + (d.getUTCMonth() + 1)).slice(-2) + '-' + ('0' + d.getUTCDate()).slice(-2);
}
/** Data de azi în fusul orar al telefonului (ca azi() din sn41). */
export function aziLocal(d: Date = new Date()): string {
  return d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2);
}
export function plus(s: string, n: number): string {
  return ymd(utc(s) + n * ZI_MS);
}
export function dif(a: string, b: string): number {
  return Math.round((utc(b) - utc(a)) / ZI_MS);
}
export function eDataYmd(s: unknown): s is string {
  return typeof s === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(s);
}

/* ---- fazele ---- */
/** Ziua din ciclu în care începe și se termină fiecare fază (lim / ah_ciclu_limite). */
export function lim(c: number, m: number): Limite {
  c = Math.max(21, Math.min(45, c | 0));
  m = Math.max(2, Math.min(10, m | 0));
  const ovS = Math.max(c - 16, m + 2); // ovulația, cam cu 14 zile înaintea menstruației următoare
  const ovE = Math.min(ovS + 4, c - 2);
  return {
    menstruala: [1, m],
    foliculara: [m + 1, ovS - 1],
    ovulatorie: [ovS, ovE],
    premenstruala: [ovE + 1, c],
  };
}

export function fazaZilei(zi: number, c: number, m: number): CheieFaza {
  const l = lim(c, m);
  for (const k of ORD) {
    const z = l[k];
    if (zi >= z[0] && zi <= z[1]) return k;
  }
  return 'premenstruala';
}

/**
 * Startul care se aplică unei zile: ultimul start cunoscut de dinaintea ei,
 * proiectat înainte cu lungimea ciclului. null dacă ziua e înaintea oricărui start.
 */
export function unde(zi: string, S: StareCiclu): Unde | null {
  const st = (S.starturi || []).slice().sort();
  let s: string | undefined = S.s;
  const c = S.c;
  const m = S.m;
  for (let i = st.length - 1; i >= 0; i--) {
    if (st[i] <= zi) {
      s = st[i];
      break;
    }
  }
  if (!s || zi < s) return null;
  const d = dif(s, zi);
  const nr = Math.floor(d / c);
  const z = (d % c) + 1;
  return { zi: z, nr, faza: fazaZilei(z, c, m), inceput: plus(s, nr * c) };
}

/** Lungimea medie din ultimele starturi (doar pauzele plauzibile, 21–45 de zile; ultimele 3). */
export function medie(starturi: string[], implicit: number): number {
  const st = starturi.slice().sort();
  let p: number[] = [];
  for (let i = 1; i < st.length; i++) {
    const d = dif(st[i - 1], st[i]);
    if (d >= 21 && d <= 45) p.push(d);
  }
  p = p.slice(-3);
  return p.length ? Math.round(p.reduce((a, b) => a + b, 0) / p.length) : implicit;
}

/* ---- formularul „În ce fază ești azi?” ---- */
export type EroareCalcul = 'fara_data' | 'in_viitor' | 'prea_veche';

export const MESAJE_EROARE: Record<EroareCalcul, string> = {
  fara_data: 'Alege prima zi a ultimei menstruații.',
  in_viitor: 'Data aleasă e în viitor. Alege, te rog, prima zi a ultimei menstruații.',
  prea_veche: 'Data e de acum mai bine de patru luni. Alege prima zi a ultimei menstruații, chiar și aproximativ.',
};

export function valideazaStart(s: string, azi: string): EroareCalcul | null {
  if (!eDataYmd(s)) return 'fara_data';
  if (s > azi) return 'in_viitor';
  if (dif(s, azi) > 120) return 'prea_veche';
  return null;
}

/**
 * Trimiterea formularului: validează, limitează c și m, păstrează starturile mai vechi
 * aflate la cel puțin 15 zile înainte și adaugă startul nou (cel mult 6).
 * Câmpurile goale (0/NaN) devin 28 și 5, ca `+input.value || 28`.
 */
export function calculeaza(
  anterior: StareCiclu | null,
  s: string,
  cIn: number,
  mIn: number,
  azi: string,
): { ok: true; stare: StareCiclu } | { ok: false; eroare: EroareCalcul } {
  let c = +cIn || 28;
  let m = +mIn || 5;
  const er = valideazaStart(s, azi);
  if (er) return { ok: false, eroare: er };
  c = Math.max(21, Math.min(45, c));
  m = Math.max(2, Math.min(10, m));
  const st = ((anterior && anterior.starturi) || []).filter((x) => x < s && dif(x, s) >= 15);
  st.push(s);
  return { ok: true, stare: { s, c, m, starturi: st.sort().slice(-6) } };
}

/**
 * „Mi-a venit menstruația”: un start apropiat (< 15 zile) de ultimul îl înlocuiește,
 * altfel se adaugă; se păstrează ultimele 6, iar lungimea ciclului devine media.
 * Întoarce null dacă data nu e validă (în viitor sau mai veche de 120 de zile), ca în sn41.
 */
export function startNou(S: StareCiclu, d: string, azi: string): StareCiclu | null {
  if (!(eDataYmd(d) && d <= azi && dif(d, azi) <= 120)) return null;
  let st = (S.starturi || []).slice().sort();
  const ult = st[st.length - 1];
  if (ult && Math.abs(dif(ult, d)) < 15) st.pop();
  st.push(d);
  st = st.filter((x, i, a) => a.indexOf(x) === i).sort().slice(-6);
  return { ...S, starturi: st, s: st[st.length - 1], c: medie(st, S.c) };
}

/** Următoarele începuturi de fază după azi (strict în viitor), sortate. */
export function urmatoareleFaze(S: StareCiclu, azi: string): { k: CheieFaza; d: string }[] {
  const u = unde(azi, S);
  if (!u) return [];
  const l = lim(S.c, S.m);
  const urm = ORD.map((k) => {
    let d = plus(u.inceput, l[k][0] - 1);
    if (d <= azi) d = plus(d, S.c);
    return { k, d };
  });
  urm.sort((a, b) => (a.d < b.d ? -1 : 1));
  return urm;
}

/**
 * Începuturile de fază pe `cicluri` cicluri, începând cu ciclul curent
 * (ca fișierul .ics din sn41: 6 cicluri × 4 faze). Folosit pentru notificările locale.
 */
export function inceputuriFaze(S: StareCiclu, azi: string, cicluri = 6): { k: CheieFaza; d: string; nr: number }[] {
  const u = unde(azi, S);
  if (!u) return [];
  const l = lim(S.c, S.m);
  const out: { k: CheieFaza; d: string; nr: number }[] = [];
  for (let n = 0; n < cicluri; n++) {
    for (const k of ORD) {
      out.push({ k, d: plus(u.inceput, n * S.c + l[k][0] - 1), nr: u.nr + n });
    }
  }
  return out;
}

/* ---- Luna pe cer (luna din sn41) ---- */
export type NumeLuna = 'Luna nouă' | 'Luna în creștere' | 'Luna plină' | 'Luna în descreștere';
export function luna(s: string): { f: number; nume: NumeLuna } {
  const P = 29.530588853;
  const zile = (utc(s) + 432e5 - Date.UTC(2000, 0, 6, 18, 14)) / ZI_MS;
  const v = ((zile % P) + P) % P;
  const f = v / P;
  let nume: NumeLuna;
  if (v < 1.85 || v > 27.7) nume = 'Luna nouă';
  else if (v < 13.8) nume = 'Luna în creștere';
  else if (v < 15.8) nume = 'Luna plină';
  else nume = 'Luna în descreștere';
  return { f, nume };
}

/* ---- textele datelor ---- */
export const LUNI = ['ianuarie', 'februarie', 'martie', 'aprilie', 'mai', 'iunie', 'iulie', 'august', 'septembrie', 'octombrie', 'noiembrie', 'decembrie'];
export const ZILE = ['duminică', 'luni', 'marți', 'miercuri', 'joi', 'vineri', 'sâmbătă'];
export function frumos(s: string, cuZi = false): string {
  const d = new Date(utc(s));
  return (cuZi ? ZILE[d.getUTCDay()] + ', ' : '') + d.getUTCDate() + ' ' + LUNI[d.getUTCMonth()];
}
