/** Rute simple pe hash (#/acasa, #/inner-temple/m/330 …), ca butonul Înapoi să meargă singur. */
import { useEffect, useState } from 'react';

export type Tab = 'acasa' | 'inner-temple' | 'cursuri' | 'ciclu' | 'profil';
export const TABURI: Tab[] = ['acasa', 'inner-temple', 'cursuri', 'ciclu', 'profil'];

export function rutaCurenta(): string[] {
  const h = (typeof location !== 'undefined' ? location.hash : '').replace(/^#\/?/, '');
  const p = h.split('?')[0].split('/').filter(Boolean).map(decodeURIComponent);
  return p.length ? p : ['acasa'];
}

export function useRuta(): string[] {
  const [r, setR] = useState(rutaCurenta);
  useEffect(() => {
    const f = () => setR(rutaCurenta());
    window.addEventListener('hashchange', f);
    return () => window.removeEventListener('hashchange', f);
  }, []);
  return r;
}

function idx(): number {
  const st = history.state as { ah?: number } | null;
  return st && typeof st.ah === 'number' ? st.ah : 0;
}

/** Navighează în aplicație; `inlocuieste` nu lasă pagina curentă în istoric. */
export function mergi(cale: string, inlocuieste = false) {
  const h = '#/' + cale.replace(/^#?\/?/, '');
  if (location.hash === h) return;
  if (inlocuieste) history.replaceState({ ah: idx() }, '', h);
  else history.pushState({ ah: idx() + 1 }, '', h);
  window.dispatchEvent(new HashChangeEvent('hashchange'));
}

/** Înapoi în istoricul aplicației sau, dacă nu există, la `implicit`. */
export function inapoi(implicit: string) {
  if (idx() > 0) history.back();
  else mergi(implicit, true);
}

/** Pentru butonul Înapoi de pe Android: true dacă a avut unde să meargă. */
export function inapoiSistem(): boolean {
  if (idx() > 0) {
    history.back();
    return true;
  }
  const r = rutaCurenta();
  if (r.length > 1) {
    mergi(r[0], true);
    return true;
  }
  if (r[0] !== 'acasa') {
    mergi('acasa', true);
    return true;
  }
  return false;
}
