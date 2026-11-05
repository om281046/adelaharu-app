/** Datele ciclului pe telefon (merg fără cont) și sincronizarea opțională cu adelaharu.ro. */
import { api } from '../api';
import type { StareCiclu } from '../ciclu/algoritm';
import { anuleazaToate, programeazaFaze } from './notificari';
import { CHEI, citeste, scrie, sterge } from './stocare';

export interface AcordCiclu {
  /** bifa separată, explicită: datele de sănătate se pot păstra pe adelaharu.ro */
  sanatate: boolean;
  emailuri: boolean;
  /** ultima sincronizare reușită (ms) */
  sincronizatLa: number | null;
}

export interface Setari {
  notifFaze: boolean;
  subiecte: { meditatii_noi: boolean; cursuri: boolean };
}

export const SETARI_IMPLICITE: Setari = { notifFaze: false, subiecte: { meditatii_noi: true, cursuri: true } };
export const ACORD_IMPLICIT: AcordCiclu = { sanatate: false, emailuri: false, sincronizatLa: null };

export const citesteCiclu = () => citeste<StareCiclu | null>(CHEI.ciclu, null);
export const citesteAcord = () => citeste<AcordCiclu>(CHEI.acordCiclu, ACORD_IMPLICIT);
export async function citesteSetari(): Promise<Setari> {
  const s = await citeste<Partial<Setari>>(CHEI.setari, {});
  return { ...SETARI_IMPLICITE, ...s, subiecte: { ...SETARI_IMPLICITE.subiecte, ...(s.subiecte || {}) } };
}
export const scrieSetari = (s: Setari) => scrie(CHEI.setari, s);
export const scrieAcord = (a: AcordCiclu) => scrie(CHEI.acordCiclu, a);

/** Salvează local, reprogramează notificările și, DOAR cu acordul, sincronizează. */
export async function salveazaCiclu(S: StareCiclu, autentificata: boolean): Promise<{ sincronizat: boolean }> {
  await scrie(CHEI.ciclu, S);
  const setari = await citesteSetari();
  await programeazaFaze(S, setari.notifFaze);
  const acord = await citesteAcord();
  if (autentificata && acord.sanatate) {
    try {
      await sincronizeaza(S, acord);
      return { sincronizat: true };
    } catch {
      // ținem minte și reîncercăm la pornire, la revenirea în aplicație și la intrarea în cont
      await scrie(CHEI.cicluDeSincronizat, true);
      return { sincronizat: false };
    }
  }
  return { sincronizat: false };
}

export async function sincronizeaza(S: StareCiclu, acord: AcordCiclu) {
  if (!acord.sanatate) throw new Error('fără acord');
  await api.salveazaCiclu({ prima_zi: S.s, zile_sangerare: S.m, zile_ciclu: S.c, emailuri_faze: acord.emailuri, acord_date_sanatate: true });
  await scrieAcord({ ...acord, sincronizatLa: Date.now() });
  await sterge(CHEI.cicluDeSincronizat);
}

/** Oprește sincronizarea și șterge rândul de pe site (DELETE /ciclu). */
export async function opresteSincronizarea() {
  await api.stergeCiclu();
  await scrieAcord(ACORD_IMPLICIT);
  await sterge(CHEI.cicluDeSincronizat);
  await sterge(CHEI.cicluDeSters);
}

export type RezultatStergere =
  /** șters și de pe site (sau nu era nimic acolo) */
  | 'sters'
  /** pe telefon s-a șters; site-ul nu a răspuns, reîncercăm singuri */
  | 'site_in_asteptare'
  /** nu e în cont: de pe site nu putem șterge de aici */
  | 'fara_cont';

/**
 * „Șterge datele mele”: telefonul, notificările și site-ul.
 * În cont trimitem MEREU DELETE /ciclu (nu contează dacă le-a salvat de pe acest telefon, de pe site sau de pe alt telefon;
 * dacă nu e nimic acolo, nu strică). Dacă nu merge, ținem minte și reîncercăm.
 */
export async function stergeDateleCiclului(autentificata: boolean): Promise<RezultatStergere> {
  let rezultat: RezultatStergere = 'fara_cont';
  if (autentificata) {
    try {
      await api.stergeCiclu();
      await sterge(CHEI.cicluDeSters);
      rezultat = 'sters';
    } catch {
      await scrie(CHEI.cicluDeSters, true);
      rezultat = 'site_in_asteptare';
    }
  }
  await sterge(CHEI.ciclu);
  await sterge(CHEI.acordCiclu);
  await sterge(CHEI.cicluDeSincronizat);
  await anuleazaToate();
  return rezultat;
}

/** Reîncearcă ce a rămas neterminat cu site-ul (ștergere sau sincronizare). Nu aruncă. */
export async function reincearcaCuSite(autentificata: boolean): Promise<void> {
  if (!autentificata) return;
  if (await citeste<boolean>(CHEI.cicluDeSters, false)) {
    try {
      await api.stergeCiclu();
      await sterge(CHEI.cicluDeSters);
    } catch {
      return; // tot fără internet: mai încercăm data viitoare
    }
  }
  if (await citeste<boolean>(CHEI.cicluDeSincronizat, false)) {
    const S = await citesteCiclu();
    const acord = await citesteAcord();
    if (!S || !acord.sanatate) {
      await sterge(CHEI.cicluDeSincronizat);
      return;
    }
    try {
      await sincronizeaza(S, acord);
    } catch {
      /* mai încercăm data viitoare */
    }
  }
}

/**
 * Acordul de pe telefon urmează ce știe site-ul despre contul ei (/eu.ciclu), ca să nu rămână
 * acordul altei persoane sau al altui telefon. Nu atinge nimic cât timp avem ceva în așteptare.
 */
export async function potrivesteAcordCuSite(ciclu: { salvat_pe_site: boolean; emailuri_faze: boolean }): Promise<void> {
  if (await citeste<boolean>(CHEI.cicluDeSters, false)) return;
  const acord = await citesteAcord();
  if (ciclu.salvat_pe_site) {
    if (!acord.sanatate || acord.emailuri !== ciclu.emailuri_faze) {
      await scrieAcord({ sanatate: true, emailuri: ciclu.emailuri_faze, sincronizatLa: acord.sanatate ? acord.sincronizatLa : null });
    }
  } else if (acord.sanatate && !(await citeste<boolean>(CHEI.cicluDeSincronizat, false))) {
    // pe site nu mai e nimic (a oprit din email sau de pe site): acordul de aici nu mai e valabil
    await scrieAcord(ACORD_IMPLICIT);
  }
}

/** La ieșirea din cont: acordul și ce era în așteptare țin de cont, nu de telefon. Datele ciclului rămân pe telefon. */
export async function uitaAcordul(): Promise<void> {
  await sterge(CHEI.acordCiclu);
  await sterge(CHEI.cicluDeSincronizat);
  await sterge(CHEI.cicluDeSters);
}
