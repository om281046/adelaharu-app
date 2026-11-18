/**
 * Pagina offline: de ce s-a deschis și când se întoarce la Spațiul meu (2.3.0).
 *
 * Trei motive, cu trei texte diferite:
 *  - „fara_internet”: telefonul nu ajunge la site (fără rețea, avion, DNS);
 *  - „site”: internetul merge, dar site-ul răspunde cu o eroare (5xx în timpul unei actualizări, 403/429 de la paravan),
 *    deci „Site-ul e în lucru, revino în câteva minute”;
 *  - „webview”: pe Android, componenta WebView e mai veche decât cere aplicația (capacitor.config.ts,
 *    android.minWebViewVersion = 90): Capacitor deschide atunci direct pagina aceasta, iar site-ul nu s-ar desena bine.
 *
 * Verificarea: GET /wp-json/ah-spa/v1/config (public, mic), cu CORS și r.ok cerut. Un răspuns care nu e „ok” = site.
 * O eroare de rețea = fără internet, cu o singură excepție: dacă cererea CORS cade, dar o cerere „no-cors” spre aceeași
 * adresă trece, rețeaua merge (CORS-ul unui cache, de exemplu): „ok”, iar pagina lasă WebView-ul să încerce.
 *
 * Fără bucle: plecarea spre site se ține minte (sessionStorage); o întoarcere aici în mai puțin de 30 s mărește pauza
 * până la următoarea încercare automată (5 s, 15 s, 30 s, 1 min, 2 min, apoi 5 min). Butonul „Încearcă din nou” merge oricând.
 */

export const CONFIG = 'https://www.adelaharu.ro/wp-json/ah-spa/v1/config';
export type Motiv = 'ok' | 'fara_internet' | 'site' | 'webview';

/** Rezultatul unei verificări, din răspuns (sau lipsa lui). Pură. */
export function dinRaspuns(r: { ok: boolean; status: number } | null): Motiv {
  if (!r) return 'fara_internet';
  return r.ok ? 'ok' : 'site';
}

type Fetch = (u: string, o?: RequestInit) => Promise<{ ok: boolean; status: number }>;

/** Verifică legătura cu site-ul. `f` = fetch (în teste, imitat). */
export async function verifica(f: Fetch, ms = 8000, online: boolean | undefined = typeof navigator !== 'undefined' ? navigator.onLine : undefined): Promise<Motiv> {
  if (online === false) return 'fara_internet';
  const cu = async (o: RequestInit) => {
    const c = typeof AbortController !== 'undefined' ? new AbortController() : null;
    const t = setTimeout(() => c?.abort(), ms);
    try {
      return await f(CONFIG + '?t=' + Date.now(), { method: 'GET', cache: 'no-store', credentials: 'omit', signal: c?.signal, ...o });
    } finally {
      clearTimeout(t);
    }
  };
  try {
    return dinRaspuns(await cu({ mode: 'cors' }));
  } catch {
    try {
      await cu({ mode: 'no-cors' });
      return 'ok'; // rețeaua merge; doar CORS-ul n-a lăsat să citim starea
    } catch {
      return 'fara_internet';
    }
  }
}

const PAUZE = [5, 15, 30, 60, 120, 300];
/** Secundele până la încercarea automată numărul n (0, 1, …). Pură. */
export function pauza(n: number): number {
  return PAUZE[Math.max(0, Math.min(PAUZE.length - 1, Math.floor(n) || 0))];
}

/**
 * De câte ori s-a întors aplicația aici repede (în 30 s) după ce am trimis-o la site: n vechi + 1, altfel 0. Pură.
 * plecat = momentul ultimei plecări spre site (ms) sau 0.
 */
export function intoarceri(plecat: number, nVechi: number, acum: number): number {
  return plecat > 0 && acum - plecat >= 0 && acum - plecat < 30000 ? nVechi + 1 : 0;
}

/** Motivul cu care s-a deschis pagina: ?cod=503 (iOS, AhNavigare) sau eroarea ținută de AhApp (Android). Pură. */
export function motivInitial(cautare: string, eroareNativa: { cod?: number; la?: number } | null, acum: number): 'fara_internet' | 'site' {
  const cod = Number(new URLSearchParams(cautare).get('cod') || 0);
  if (cod >= 400) return 'site';
  if (eroareNativa && (eroareNativa.cod || 0) >= 400 && acum - (eroareNativa.la || 0) < 60000) return 'site';
  return 'fara_internet';
}

/** Versiunea principală a WebView-ului (Chrome/NN din User-Agent); 0 dacă nu se știe. Pură. */
export function versiuneWebView(ua: string): number {
  const m = /Chrome\/(\d+)/.exec(ua || '');
  return m ? +m[1] : 0;
}

/** Textele de sus, după motiv. CIORNĂ (de citit de Adela). */
export const TEXTE: Record<Exclude<Motiv, 'ok'>, { titlu: string; text: string; buton: string }> = {
  fara_internet: {
    titlu: 'Nu avem internet acum',
    text: 'Spațiul tău se deschide din nou când revine conexiunea. Până atunci, te așteaptă aici ziua ta din ciclu și meditațiile descărcate pe telefon.',
    buton: 'Încearcă din nou',
  },
  site: {
    titlu: 'Site-ul e în lucru',
    text: 'Site-ul e în lucru, revino în câteva minute. Între timp, te așteaptă aici ziua ta din ciclu și meditațiile descărcate pe telefon.',
    buton: 'Încearcă din nou',
  },
  webview: {
    titlu: 'Telefonul are nevoie de o actualizare',
    text: 'Ca să se deschidă Spațiul meu, actualizează din Magazin Play „Android System WebView” și „Chrome”, apoi deschide din nou aplicația. Meditațiile descărcate și ziua ta din ciclu te așteaptă și aici.',
    buton: 'Încearcă din nou',
  },
};
