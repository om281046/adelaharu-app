/**
 * Linkurile universale / App Links: https://www.adelaharu.ro/app/intra?e=&c= deschide aplicația
 * și completează codul. Celelalte căi din apple-app-site-association duc la tabul potrivit.
 */
import { App } from '@capacitor/app';
import { Capacitor } from '@capacitor/core';
import { mergi } from './ruta';

export interface CodDinLink {
  email: string;
  cod: string;
}

export function analizeaza(url: string): { cod?: CodDinLink; ruta?: string } {
  let u: URL;
  try {
    u = new URL(url);
  } catch {
    return {};
  }
  const cale = u.pathname.replace(/\/+$/, '');
  if (cale === '/app/intra') {
    const email = (u.searchParams.get('e') || '').trim();
    const cod = (u.searchParams.get('c') || '').replace(/\D/g, '').slice(0, 6);
    if (email) return { cod: { email, cod } };
    return {};
  }
  if (cale.startsWith('/meditatiile-mele')) return { ruta: 'inner-temple' };
  if (cale.startsWith('/sala-de-curs') || cale.startsWith('/cursurile-mele')) return { ruta: 'cursuri' };
  return {};
}

let laCod: ((c: CodDinLink) => void) | null = null;
let codInAsteptare: CodDinLink | null = null;

/** Ecranul de intrare se abonează aici; dacă linkul a venit înainte, îl primește imediat. */
export function candVineCod(f: ((c: CodDinLink) => void) | null) {
  laCod = f;
  if (f && codInAsteptare) {
    f(codInAsteptare);
    codInAsteptare = null;
  }
}
export function codPrimit(): CodDinLink | null {
  return codInAsteptare;
}

function trateaza(url: string) {
  const r = analizeaza(url);
  if (r.cod) {
    if (laCod) laCod(r.cod);
    else {
      codInAsteptare = r.cod;
      window.dispatchEvent(new Event('ah-cod'));
    }
  } else if (r.ruta) {
    mergi(r.ruta);
  }
}

export async function initLegaturi() {
  if (Capacitor.isNativePlatform()) {
    await App.addListener('appUrlOpen', (e) => trateaza(e.url));
    try {
      const l = await App.getLaunchUrl();
      if (l?.url) trateaza(l.url);
    } catch {
      /* nimic */
    }
  } else if (typeof location !== 'undefined') {
    // în browser (dezvoltare): ?e=&c= în adresă
    const q = new URLSearchParams(location.search);
    if (q.get('e')) trateaza('https://www.adelaharu.ro/app/intra' + location.search);
  }
}
