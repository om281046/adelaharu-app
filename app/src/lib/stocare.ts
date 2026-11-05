/**
 * Stocare locală. v1: @capacitor/preferences (UserDefaults pe iOS, SharedPreferences pe Android,
 * localStorage în browser). Tokenul stă tot aici în v1; vezi README („Securitate”) pentru trecerea
 * la Keychain/Keystore (de ex. @aparajita/capacitor-secure-storage).
 */
import { Preferences } from '@capacitor/preferences';

export async function citeste<T>(cheie: string, implicit: T): Promise<T> {
  try {
    const { value } = await Preferences.get({ key: cheie });
    return value == null ? implicit : (JSON.parse(value) as T);
  } catch {
    return implicit;
  }
}

export async function scrie<T>(cheie: string, valoare: T): Promise<void> {
  try {
    await Preferences.set({ key: cheie, value: JSON.stringify(valoare) });
  } catch {
    /* fără stocare (navigare privată): aplicația merge mai departe */
  }
}

export async function sterge(cheie: string): Promise<void> {
  try {
    await Preferences.remove({ key: cheie });
  } catch {
    /* nimic */
  }
}

/** Șterge toate cheile care încep cu prefixul dat (de ex. cache-urile cursurilor). */
export async function stergePrefix(prefix: string): Promise<void> {
  try {
    const { keys } = await Preferences.keys();
    await Promise.all(keys.filter((k) => k.startsWith(prefix)).map((k) => Preferences.remove({ key: k })));
  } catch {
    /* nimic */
  }
}

export const CHEI = {
  token: 'ah_token',
  utilizatoare: 'ah_utilizatoare',
  dispozitiv: 'ah_dispozitiv_id',
  ciclu: 'ah_ciclu_v1',
  acordCiclu: 'ah_ciclu_acord',
  setari: 'ah_setari',
  pozitii: 'ah_pozitii',
  ultima: 'ah_ultima_redare',
  ultimaLectie: 'ah_ultima_redare_lectie',
  descarcari: 'ah_descarcari',
  pushToken: 'ah_push_token',
  cacheMeditatii: 'ah_cache_meditatii',
  cacheCursuri: 'ah_cache_cursuri',
  /** + slug */
  cacheCursPrefix: 'ah_cache_curs_',
  faraCont: 'ah_fara_cont',
  /** sincronizarea ciclului cu site-ul a eșuat: reîncercăm la pornire / revenire / intrare */
  cicluDeSincronizat: 'ah_ciclu_de_sincronizat',
  /** ștergerea ciclului de pe site a eșuat: reîncercăm la pornire / revenire / intrare */
  cicluDeSters: 'ah_ciclu_de_sters',
} as const;
