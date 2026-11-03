import { useCallback, useEffect, useRef, useState } from 'react';
import { api, mesajEroare } from '../api';
import type { FazeRaspuns, MeditatiiRaspuns } from '../api/types';
import type { CheieFaza } from '../ciclu/algoritm';
import { cheieDinSlug } from '../ciclu/faze';
import { CHEI, citeste, scrie } from './stocare';

export interface Incarcare<T> {
  date: T | null;
  eroare: string | null;
  seIncarca: boolean;
  /** datele vin din memoria telefonului (fără internet) */
  dinCache: boolean;
  reincarca(): void;
}

/** Încarcă de la server; dacă nu merge și avem `cache`, arată ultima variantă salvată. */
export function useIncarca<T>(f: (() => Promise<T>) | null, chei: unknown[], cache?: string): Incarcare<T> {
  const [date, setDate] = useState<T | null>(null);
  const [eroare, setEroare] = useState<string | null>(null);
  const [seIncarca, setSeIncarca] = useState(true);
  const [dinCache, setDinCache] = useState(false);
  const [n, setN] = useState(0);
  const fRef = useRef(f);
  fRef.current = f;

  useEffect(() => {
    let viu = true;
    const fn = fRef.current;
    if (!fn) {
      setSeIncarca(false);
      return;
    }
    setSeIncarca(true);
    (async () => {
      if (cache) {
        const c = await citeste<T | null>(cache, null);
        if (viu && c) {
          setDate(c);
          setDinCache(true);
        }
      }
      try {
        const d = await fn();
        if (!viu) return;
        setDate(d);
        setEroare(null);
        setDinCache(false);
        if (cache) void scrie(cache, d);
      } catch (e) {
        if (viu) setEroare(mesajEroare(e));
      } finally {
        if (viu) setSeIncarca(false);
      }
    })();
    return () => {
      viu = false;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [...chei, n, cache]);

  const reincarca = useCallback(() => setN((x) => x + 1), []);
  return { date, eroare, seIncarca, dinCache, reincarca };
}

export function useMeditatii(autentificata: boolean): Incarcare<MeditatiiRaspuns> {
  return useIncarca(autentificata ? () => api.meditatii() : null, [autentificata], autentificata ? CHEI.cacheMeditatii : undefined);
}

let fazeCache: Promise<FazeRaspuns | null> | null = null;
/** Meditația și cursul potrivite fiecărei faze (din GET /ciclu/faze, public). */
export function useFazeApi(): Partial<Record<CheieFaza, { meditatie_id: number | null; curs_slug: string | null }>> {
  const [h, setH] = useState<Partial<Record<CheieFaza, { meditatie_id: number | null; curs_slug: string | null }>>>({});
  useEffect(() => {
    if (!fazeCache) fazeCache = api.faze().catch(() => null);
    let viu = true;
    void fazeCache.then((r) => {
      if (!viu || !r) return;
      const o: Partial<Record<CheieFaza, { meditatie_id: number | null; curs_slug: string | null }>> = {};
      for (const f of r.faze) {
        const k = cheieDinSlug(f.slug);
        if (k) o[k] = { meditatie_id: f.meditatie_id, curs_slug: f.curs_slug };
      }
      setH(o);
    });
    return () => {
      viu = false;
    };
  }, []);
  return h;
}
