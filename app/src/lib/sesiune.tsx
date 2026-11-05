import { createContext, useCallback, useContext, useEffect, useMemo, useState, type ReactNode } from 'react';
import { api } from '../api';
import { candExpiraSesiunea, seteazaToken } from '../api/client';
import type { Config, Eu, Utilizatoare } from '../api/types';
import { citesteCiclu, potrivesteAcordCuSite, reincearcaCuSite, scrieAcord, uitaAcordul } from './ciclu-local';
import { codPrimit } from './legaturi';
import { App as CapApp } from '@capacitor/app';
import { Capacitor } from '@capacitor/core';
import { dezinregistreazaPush } from './push';
import { stergeToateDescarcarile } from './offline';
import { uitaTot } from './player';
import { CHEI, citeste, scrie, sterge, stergePrefix } from './stocare';
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
  await stergePrefix(CHEI.cacheCursPrefix);
  await sterge(CHEI.ultimaLectie);
  // acordul pentru datele de sănătate ține de cont: următoarea persoană de pe telefon nu îl moștenește
  await uitaAcordul();
  seteazaToken(null);
}

/** Ce am primit de la /eu: acordul ciclului se aliniază cu contul, apoi reîncercăm ce a rămas în așteptare. */
async function dupaEu(e: Eu) {
  await potrivesteAcordCuSite(e.ciclu);
  await reincearcaCuSite(true);
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
      const e = await api.eu();
      setEu(e);
      await dupaEu(e);
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
      const fc = await citeste<boolean>(CHEI.faraCont, false);
      if (t) {
        seteazaToken(t);
        setToken(t);
        setU(u);
      }
      // dacă a venit un cod din linkul din email, nu ne întoarcem la „fără cont”: ecranul de intrare îl folosește
      setFaraCont(fc && !codPrimit());
      setGata(true);
      api.config().then(setConfig).catch(() => {});
      if (t) void reincarcaEu();
    })();
  }, [reincarcaEu]);

  // la revenirea în aplicație (și când revine internetul) reîncercăm sincronizarea / ștergerea ciclului
  useEffect(() => {
    if (!token) return;
    const f = () => void reincarcaEu();
    window.addEventListener('online', f);
    const asc = Capacitor.isNativePlatform() ? CapApp.addListener('resume', f) : null;
    return () => {
      window.removeEventListener('online', f);
      void asc?.then((a) => a.remove());
    };
  }, [token, reincarcaEu]);

  const intra = useCallback(async (t: string, u: Utilizatoare) => {
    seteazaToken(t);
    await scrie(CHEI.token, t);
    await scrie(CHEI.utilizatoare, u);
    setToken(t);
    setU(u);
    setFaraCont(false);
    await scrie(CHEI.faraCont, false);
    // acordul altei persoane nu trece la contul ăsta (ar trebui să fie deja șters la ieșire; aici pentru siguranță)
    await uitaAcordul();
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
      // altfel (telefonul are deja date): acordul se ia tot din /eu.ciclu.salvat_pe_site
      await potrivesteAcordCuSite(e.ciclu);
    } catch {
      /* nimic */
    }
  }, []);

  const continuaFaraCont = useCallback(() => {
    setFaraCont(true);
    void scrie(CHEI.faraCont, true);
  }, []);

  const vreiSaIntri = useCallback(() => {
    setFaraCont(false);
    void scrie(CHEI.faraCont, false);
  }, []);

  const iesi = useCallback(async () => {
    // o ultimă încercare pentru ce a rămas în așteptare (de ex. ștergerea ciclului de pe site), cât încă e în cont
    await reincearcaCuSite(true);
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
