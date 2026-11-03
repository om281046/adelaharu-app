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
      return { sincronizat: false };
    }
  }
  return { sincronizat: false };
}

export async function sincronizeaza(S: StareCiclu, acord: AcordCiclu) {
  if (!acord.sanatate) throw new Error('fără acord');
  await api.salveazaCiclu({ prima_zi: S.s, zile_sangerare: S.m, zile_ciclu: S.c, emailuri_faze: acord.emailuri, acord_date_sanatate: true });
  await scrieAcord({ ...acord, sincronizatLa: Date.now() });
}

/** Oprește sincronizarea și șterge rândul de pe site (DELETE /ciclu). */
export async function opresteSincronizarea() {
  await api.stergeCiclu();
  await scrieAcord(ACORD_IMPLICIT);
}

/** „Șterge datele mele”: telefonul, notificările și (dacă e cazul) site-ul. */
export async function stergeDateleCiclului(autentificata: boolean): Promise<{ siteOk: boolean }> {
  const acord = await citesteAcord();
  let siteOk = true;
  if (autentificata && (acord.sanatate || acord.sincronizatLa)) {
    try {
      await api.stergeCiclu();
    } catch {
      siteOk = false;
    }
  }
  await sterge(CHEI.ciclu);
  await sterge(CHEI.acordCiclu);
  await anuleazaToate();
  return { siteOk };
}
