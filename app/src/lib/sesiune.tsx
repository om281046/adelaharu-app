import { createContext, useCallback, useContext, useEffect, useMemo, useState, type ReactNode } from 'react';
import { api } from '../api';
import { candExpiraSesiunea, seteazaToken } from '../api/client';
import type { Config, Eu, Utilizatoare } from '../api/types';
import { citesteCiclu, scrieAcord } from './ciclu-local';
import { dezinregistreazaPush } from './push';
import { stergeToateDescarcarile } from './offline';
import { uitaTot } from './player';
import { CHEI, citeste, scrie, sterge } from './stocare';
import { anuleazaToate } from './notificari';

interface Sesiune {
  gata: boolean;
  token: string | null;
  utilizatoare: Utilizatoare | null;
  eu: Eu | null;
  config: Config | null;
  faraCont: boolean;
  intra(token: string, u: Utilizatoare): Promise<void>;
  continuaFaraCont(): void;
  iesi(): Promise<void>;
  stergeCont(): Promise<void>;
  reincarcaEu(): Promise<void>;
  vreiSaIntri(): void;
}

const Ctx = createContext<Sesiune | null>(null);

export function useSesiune(): Sesiune {
  const s = useContext(Ctx);
  if (!s) throw new Error('fără SesiuneProvider');
  return s;
}

const CONFIG_IMPLICIT: Config = {
  versiune_minima: '1.0.0',
  contact: { email: 'contact@adelaharu.ro', whatsapp: '+40742709306' },
  linkuri: { site: 'https://www.adelaharu.ro/', confidentialitate: 'https://www.adelaharu.ro/politica-de-confidentialitate/', termeni: 'https://www.adelaharu.ro/termeni-si-conditii/' },
  mesaj: null,
};

async function curataLocal() {
  await uitaTot();
  await stergeToateDescarcarile();
  await sterge(CHEI.token);
  await sterge(CHEI.utilizatoare);
  await sterge(CHEI.cacheMeditatii);
  await sterge(CHEI.cacheCursuri);
  seteazaToken(null);
}

export function SesiuneProvider({ children }: { children: ReactNode }) {
  const [gata, setGata] = useState(false);
  const [token, setToken] = useState<string | null>(null);
  const [utilizatoare, setU] = useState<Utilizatoare | null>(null);
  const [eu, setEu] = useState<Eu | null>(null);
  const [config, setConfig] = useState<Config | null>(CONFIG_IMPLICIT);
  const [faraCont, setFaraCont] = useState(false);

  const reincarcaEu = useCallback(async () => {
    try {
      setEu(await api.eu());
    } catch {
      /* fără internet: rămâne ce aveam */
    }
  }, []);

  useEffect(() => {
    candExpiraSesiunea(() => {
      void curataLocal().then(() => {
        setToken(null);
        setU(null);
        setEu(null);
      });
    });
    (async () => {
      const t = await citeste<string | null>(CHEI.token, null);
      const u = await citeste<Utilizatoare | null>(CHEI.utilizatoare, null);
      const fc = await citeste<boolean>('ah_fara_cont', false);
      if (t) {
        seteazaToken(t);
        setToken(t);
        setU(u);
      }
      setFaraCont(fc);
      setGata(true);
      api.config().then(setConfig).catch(() => {});
      if (t) void reincarcaEu();
    })();
  }, [reincarcaEu]);

  const intra = useCallback(async (t: string, u: Utilizatoare) => {
    seteazaToken(t);
    await scrie(CHEI.token, t);
    await scrie(CHEI.utilizatoare, u);
    setToken(t);
    setU(u);
    setFaraCont(false);
    await scrie('ah_fara_cont', false);
    try {
      const e = await api.eu();
      setEu(e);
      // dacă pe site are deja datele ciclului (cu acordul dat acolo) și telefonul nu le are, le preluăm
      if (e.ciclu.salvat_pe_site && !(await citesteCiclu())) {
        const c = await api.ciclu();
        if (c) {
          await scrie(CHEI.ciclu, { s: c.prima_zi, c: c.zile_ciclu, m: c.zile_sangerare, starturi: [c.prima_zi] });
          await scrieAcord({ sanatate: true, emailuri: c.emailuri_faze, sincronizatLa: Date.now() });
        }
      }
    } catch {
      /* nimic */
    }
  }, []);

  const continuaFaraCont = useCallback(() => {
    setFaraCont(true);
    void scrie('ah_fara_cont', true);
  }, []);

  const vreiSaIntri = useCallback(() => {
    setFaraCont(false);
    void scrie('ah_fara_cont', false);
  }, []);

  const iesi = useCallback(async () => {
    await dezinregistreazaPush();
    try {
      await api.iesire();
    } catch {
      /* tokenul se curăță oricum local */
    }
    await curataLocal();
    setToken(null);
    setU(null);
    setEu(null);
  }, []);

  const stergeCont = useCallback(async () => {
    await api.stergeCont(); // dacă nu merge, aruncă și ecranul arată mesajul
    await curataLocal();
    await sterge(CHEI.ciclu);
    await sterge(CHEI.acordCiclu);
    await sterge(CHEI.pushToken);
    await anuleazaToate();
    setToken(null);
    setU(null);
    setEu(null);
  }, []);

  const v = useMemo<Sesiune>(
    () => ({ gata, token, utilizatoare, eu, config, faraCont, intra, continuaFaraCont, iesi, stergeCont, reincarcaEu, vreiSaIntri }),
    [gata, token, utilizatoare, eu, config, faraCont, intra, continuaFaraCont, iesi, stergeCont, reincarcaEu, vreiSaIntri],
  );
  return <Ctx.Provider value={v}>{children}</Ctx.Provider>;
}
