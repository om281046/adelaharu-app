/**
 * Clientul API (docs/api-contract.md), peste CapacitorHttp (HTTP nativ pe telefon; fetch în browser).
 * Erorile vin ca {"eroare":{"cod","mesaj"}} și devin ApiError.
 */
import { CapacitorHttp, type HttpResponse } from '@capacitor/core';
import type {
  CicluServer, CodEroare, Config, Curs, CursScurt, Dispozitiv, Eu, FazeRaspuns,
  MeditatiiRaspuns, Ok, Redare, SubiectePush, VerificaRaspuns,
} from './types';

export const BAZA: string = (import.meta.env.VITE_API_BASE as string | undefined) || 'https://www.adelaharu.ro/wp-json/ah-app/v1';

export class ApiError extends Error {
  constructor(public cod: CodEroare, mesaj: string, public status = 0) {
    super(mesaj);
    this.name = 'ApiError';
  }
}

export const MESAJE_IMPLICITE: Record<CodEroare, string> = {
  neautentificat: 'Te rugăm să intri din nou în cont.',
  fara_acces: 'Nu ai acces la acest conținut.',
  negasit: 'Nu am găsit ce căutai.',
  prea_multe: 'Hai să luăm o mică pauză. Mai încearcă peste o oră.',
  cod_gresit: 'Codul nu e corect. Verifică-l, te rog, și mai încearcă.',
  cod_expirat: 'Codul a expirat. Cere, te rog, un cod nou.',
  dispozitive: 'Contul tău e deschis deja pe prea multe telefoane. Scrie-ne la contact@adelaharu.ro și îți facem loc.',
  invalid: 'Ceva nu e în regulă cu datele trimise.',
  server: 'Ceva nu a mers la noi. Mai încearcă puțin mai târziu.',
  retea: 'Nu am putut ajunge la adelaharu.ro. Verifică, te rog, conexiunea la internet și mai încearcă.',
};

export interface Api {
  cod(email: string): Promise<Ok>;
  verifica(email: string, cod: string, dispozitiv: Dispozitiv): Promise<VerificaRaspuns>;
  iesire(): Promise<Ok>;
  stergeCont(): Promise<Ok>;
  config(): Promise<Config>;
  eu(): Promise<Eu>;
  meditatii(): Promise<MeditatiiRaspuns>;
  redare(id: number): Promise<Redare>;
  pozitie(id: number, secunde: number, terminat: boolean): Promise<Ok>;
  cursuri(): Promise<CursScurt[]>;
  curs(slug: string): Promise<Curs>;
  progres(slug: string, cheie: string, terminata: boolean): Promise<Ok>;
  faze(): Promise<FazeRaspuns>;
  ciclu(): Promise<CicluServer | null>;
  salveazaCiclu(c: CicluServer & { acord_date_sanatate: true }): Promise<Ok>;
  stergeCiclu(): Promise<Ok>;
  push(token: string, platforma: 'ios' | 'android', subiecte: SubiectePush): Promise<Ok>;
  stergePush(token: string): Promise<Ok>;
}

type Metoda = 'GET' | 'POST' | 'PUT' | 'DELETE';
let tokenCurent: string | null = null;
let laNeautentificat: (() => void) | null = null;

export function seteazaToken(t: string | null) {
  tokenCurent = t;
}
export function candExpiraSesiunea(f: () => void) {
  laNeautentificat = f;
}

function codValid(c: unknown): c is CodEroare {
  return typeof c === 'string' && c in MESAJE_IMPLICITE;
}

async function cerere<T>(metoda: Metoda, cale: string, corp?: unknown, auth = true): Promise<T> {
  const headers: Record<string, string> = { Accept: 'application/json' };
  if (corp !== undefined) headers['Content-Type'] = 'application/json';
  if (auth && tokenCurent) headers.Authorization = 'Bearer ' + tokenCurent;
  let r: HttpResponse;
  try {
    r = await CapacitorHttp.request({
      url: BAZA + cale,
      method: metoda,
      headers,
      data: corp,
      responseType: 'json',
      connectTimeout: 15000,
      readTimeout: 20000,
    });
  } catch {
    throw new ApiError('retea', MESAJE_IMPLICITE.retea);
  }
  let date: unknown = r.data;
  if (typeof date === 'string' && date) {
    try {
      date = JSON.parse(date);
    } catch {
      /* nu e JSON */
    }
  }
  if (r.status >= 200 && r.status < 300) return date as T;
  const e = (date as { eroare?: { cod?: unknown; mesaj?: unknown } } | null)?.eroare;
  const cod: CodEroare = codValid(e?.cod) ? e.cod : r.status === 401 ? 'neautentificat' : r.status === 404 ? 'negasit' : r.status === 429 ? 'prea_multe' : 'server';
  const mesaj = typeof e?.mesaj === 'string' && e.mesaj ? e.mesaj : MESAJE_IMPLICITE[cod];
  if (cod === 'neautentificat' && auth && laNeautentificat) laNeautentificat();
  throw new ApiError(cod, mesaj, r.status);
}

const enc = encodeURIComponent;

export const apiReal: Api = {
  cod: (email) => cerere('POST', '/auth/cod', { email }, false),
  verifica: (email, cod, dispozitiv) => cerere('POST', '/auth/verifica', { email, cod, dispozitiv }, false),
  iesire: () => cerere('POST', '/auth/iesire', {}),
  stergeCont: () => cerere('DELETE', '/cont'),
  config: () => cerere('GET', '/config', undefined, false),
  eu: () => cerere('GET', '/eu'),
  meditatii: () => cerere('GET', '/meditatii'),
  redare: (id) => cerere('GET', `/meditatii/${id}/redare`),
  pozitie: (id, secunde, terminat) => cerere('POST', `/meditatii/${id}/pozitie`, { secunde: Math.floor(secunde), terminat }),
  cursuri: () => cerere('GET', '/cursuri'),
  curs: (slug) => cerere('GET', `/cursuri/${enc(slug)}`),
  progres: (slug, cheie, terminata) => cerere('POST', `/cursuri/${enc(slug)}/progres`, { cheie, terminata }),
  faze: () => cerere('GET', '/ciclu/faze', undefined, false),
  ciclu: () => cerere('GET', '/ciclu'),
  salveazaCiclu: (c) => cerere('PUT', '/ciclu', c),
  stergeCiclu: () => cerere('DELETE', '/ciclu'),
  push: (token, platforma, subiecte) => cerere('POST', '/push', { token, platforma, subiecte }),
  stergePush: (token) => cerere('DELETE', '/push', { token }),
};

export function mesajEroare(e: unknown): string {
  if (e instanceof ApiError) return e.message;
  return MESAJE_IMPLICITE.server;
}
