/**
 * Legătura cu pluginurile native, prin `window.Capacitor.Plugins` (injectat de Capacitor în pagină).
 * Nu importăm @capacitor/core: pagina offline trebuie să fie un singur fișier mic, iar în
 * testele Playwright se poate simula ușor un `window.Capacitor` cu date de probă.
 * Fiecare apel e protejat: în browser (sau dacă un plugin lipsește) pagina merge mai departe.
 */

type Fn = (o?: unknown) => Promise<unknown>;
interface Ascultare {
  remove: () => Promise<void> | void;
}
interface Plugin {
  [metoda: string]: Fn | ((ev: string, cb: (d: unknown) => void) => Promise<Ascultare> | Ascultare) | undefined;
}
interface CapacitorGlobal {
  getPlatform?: () => string;
  isNativePlatform?: () => boolean;
  convertFileSrc?: (u: string) => string;
  Plugins?: Record<string, Plugin | undefined>;
}

declare global {
  interface Window {
    Capacitor?: CapacitorGlobal;
  }
}

const cap = (): CapacitorGlobal => window.Capacitor || {};
const plugin = (n: string): Plugin | undefined => cap().Plugins?.[n];

export function platforma(): 'ios' | 'android' | 'web' {
  const p = cap().getPlatform?.();
  return p === 'ios' || p === 'android' ? p : 'web';
}

async function apel<T>(p: string, metoda: string, o?: unknown): Promise<T | null> {
  const f = plugin(p)?.[metoda] as Fn | undefined;
  if (typeof f !== 'function') return null;
  try {
    return ((await f.call(plugin(p), o)) as T) ?? null;
  } catch {
    return null;
  }
}

/** Preferences.get → valoarea brută (șir) sau null */
export async function preferinta(cheie: string): Promise<string | null> {
  const r = await apel<{ value?: string | null }>('Preferences', 'get', { key: cheie });
  return r && typeof r.value === 'string' ? r.value : null;
}

/** Calea unui fișier descărcat → adresă pe care <audio>/<img> o pot încărca (serverul local Capacitor). */
export async function adresaFisier(cale: string, dir = 'LIBRARY'): Promise<string | null> {
  if (!cale) return null;
  let uri: string | null = null;
  if (/^file:\/\//i.test(cale) || cale.startsWith('/')) uri = cale.startsWith('/') ? 'file://' + cale : cale;
  else {
    const r = await apel<{ uri?: string }>('Filesystem', 'getUri', { path: cale, directory: dir });
    uri = r?.uri || null;
  }
  if (!uri) return null;
  const conv = cap().convertFileSrc;
  return typeof conv === 'function' ? conv(uri) : uri;
}

/** Fișierul mai există pe telefon? (null = nu știm, de ex. în browser) */
export async function existaFisier(cale: string, dir = 'LIBRARY'): Promise<boolean | null> {
  if (!plugin('Filesystem')) return null;
  const o = /^file:\/\//i.test(cale) || cale.startsWith('/') ? { path: cale } : { path: cale, directory: dir };
  const f = plugin('Filesystem')?.stat as Fn | undefined;
  if (typeof f !== 'function') return null;
  try {
    await f.call(plugin('Filesystem'), o);
    return true;
  } catch {
    return false;
  }
}

export function ascunde_splash(): void {
  void apel('SplashScreen', 'hide', { fadeOutDuration: 200 });
}

/** Bara de stare după tema paginii: „DARK” = text deschis (pe fundal închis). */
export function bara_stare(intunecat: boolean): void {
  void apel('StatusBar', 'setStyle', { style: intunecat ? 'DARK' : 'LIGHT' });
  if (platforma() === 'android') void apel('StatusBar', 'setBackgroundColor', { color: intunecat ? '#221922' : '#FBF6F3' });
}

/* ---------- AhMedia (doar Android): comenzile de pe ecranul blocat și din notificare ---------- */

export interface StareMedia {
  titlu: string;
  artist: string;
  /** adresă file://… sau https://… a pozei (opțional) */
  poza?: string;
  /** secunde */
  durata: number;
  pozitie: number;
  canta: boolean;
}

export type ActiuneMedia = { tip: 'play' | 'pause' | 'inainte' | 'inapoi' | 'stop' } | { tip: 'seek'; pozitie: number };

export function areAhMedia(): boolean {
  return platforma() === 'android' && !!plugin('AhMedia');
}

export function mediaActualizeaza(s: StareMedia): void {
  if (areAhMedia()) void apel('AhMedia', 'actualizeaza', s);
}

export function mediaOpreste(): void {
  if (areAhMedia()) void apel('AhMedia', 'opreste');
}

export async function mediaAsculta(cb: (a: ActiuneMedia) => void): Promise<void> {
  const p = plugin('AhMedia');
  const add = p?.addListener as ((ev: string, cb: (d: unknown) => void) => Promise<Ascultare> | Ascultare) | undefined;
  if (!areAhMedia() || typeof add !== 'function') return;
  try {
    await add.call(p, 'actiune', (d) => cb(d as ActiuneMedia));
  } catch {
    /* fără plugin: rămân butoanele din pagină */
  }
}

/** Fișierul local (file://…) al unei căi relative, pentru notificarea nativă (nu adresa convertită). */
export async function uriNativ(cale: string, dir = 'LIBRARY'): Promise<string | null> {
  if (!cale) return null;
  if (/^file:\/\//i.test(cale)) return cale;
  if (cale.startsWith('/')) return 'file://' + cale;
  const r = await apel<{ uri?: string }>('Filesystem', 'getUri', { path: cale, directory: dir });
  return r?.uri || null;
}
