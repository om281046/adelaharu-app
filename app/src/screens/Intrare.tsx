import { useEffect, useRef, useState, type FormEvent } from 'react';
import { api, ApiError, mesajEroare, MOCK } from '../api';
import { dispozitiv } from '../lib/dispozitiv';
import { candVineCod, type CodDinLink } from '../lib/legaturi';
import { useSesiune } from '../lib/sesiune';

export function Intrare() {
  const { intra, continuaFaraCont } = useSesiune();
  const [pas, setPas] = useState<'email' | 'cod'>('email');
  const [email, setEmail] = useState('');
  const [cod, setCod] = useState('');
  const [eroare, setEroare] = useState<string | null>(null);
  const [info, setInfo] = useState<string | null>(null);
  const [lucreaza, setLucreaza] = useState(false);
  const codRef = useRef<HTMLInputElement>(null);
  const trimis = useRef(false);

  async function verifica(e: string, c: string) {
    if (trimis.current) return;
    setEroare(null);
    if (!/^\d{6}$/.test(c)) {
      setEroare('Codul are 6 cifre. Îl găsești în emailul de la noi.');
      return;
    }
    trimis.current = true;
    setLucreaza(true);
    try {
      const r = await api.verifica(e.trim(), c, await dispozitiv());
      await intra(r.token, r.utilizatoare);
    } catch (err) {
      if (err instanceof ApiError && err.cod === 'cod_expirat') {
        setCod('');
        setPas('email');
      }
      setEroare(mesajEroare(err));
    } finally {
      trimis.current = false;
      setLucreaza(false);
    }
  }

  // linkul din email (https://www.adelaharu.ro/app/intra?e=&c=) completează singur
  useEffect(() => {
    candVineCod((x: CodDinLink) => {
      setEmail(x.email);
      setPas('cod');
      setCod(x.cod);
      if (/^\d{6}$/.test(x.cod)) void verifica(x.email, x.cod);
    });
    return () => candVineCod(null);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  useEffect(() => {
    if (pas === 'cod') codRef.current?.focus();
  }, [pas]);

  async function cereCod(ev?: FormEvent) {
    ev?.preventDefault();
    setEroare(null);
    setInfo(null);
    const e = email.trim();
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(e)) {
      setEroare('Adresa de email nu pare corectă. Verifică-o, te rog.');
      return;
    }
    setLucreaza(true);
    try {
      await api.cod(e);
      setPas('cod');
      setInfo('Ți-am trimis un cod de 6 cifre la ' + e + '. Uită-te și în Promoții sau Spam, dacă nu-l vezi.');
    } catch (err) {
      setEroare(mesajEroare(err));
    } finally {
      setLucreaza(false);
    }
  }

  return (
    <main className="intrare-ecran">
      <div className="logo">
        Adela Haru
        <small>Spre feminitate</small>
      </div>

      {pas === 'email' ? (
        <form className="card" style={{ display: 'flex', flexDirection: 'column', gap: 18 }} onSubmit={cereCod} noValidate>
          <div className="antet">
            <h1 style={{ fontSize: 28 }}>Bine ai venit</h1>
            <p className="sub">Intră cu adresa de email pe care o folosești pe adelaharu.ro. Îți trimitem un cod, fără parolă.</p>
          </div>
          <div className="camp">
            <label htmlFor="email">Adresa de email</label>
            <input id="email" className="intrare" type="email" inputMode="email" autoComplete="email" autoCapitalize="none" spellCheck={false} placeholder="numele@exemplu.ro" value={email} onChange={(e) => setEmail(e.target.value)} />
          </div>
          {eroare && <div className="eroare" role="alert">{eroare}</div>}
          <button className="buton lat" type="submit" disabled={lucreaza}>
            {lucreaza ? 'Se trimite…' : 'Trimite-mi codul'}
          </button>
        </form>
      ) : (
        <form
          className="card"
          style={{ display: 'flex', flexDirection: 'column', gap: 18 }}
          onSubmit={(e) => {
            e.preventDefault();
            void verifica(email, cod);
          }}
          noValidate
        >
          <div className="antet">
            <h1 style={{ fontSize: 28 }}>Codul din email</h1>
            {info ? <p className="sub">{info}</p> : <p className="sub">Scrie codul de 6 cifre trimis la {email}.</p>}
          </div>
          <div className="camp">
            <label htmlFor="cod">Codul de 6 cifre</label>
            <input
              id="cod"
              ref={codRef}
              className="intrare cod"
              type="text"
              inputMode="numeric"
              autoComplete="one-time-code"
              pattern="\d{6}"
              maxLength={6}
              value={cod}
              onChange={(e) => {
                const v = e.target.value.replace(/\D/g, '').slice(0, 6);
                setCod(v);
                if (v.length === 6) void verifica(email, v);
              }}
            />
            {MOCK && <p className="ajutor">Demo: codul este 123456.</p>}
          </div>
          {eroare && <div className="eroare" role="alert">{eroare}</div>}
          <button className="buton lat" type="submit" disabled={lucreaza}>
            {lucreaza ? 'Intru…' : 'Intră'}
          </button>
          <div style={{ display: 'flex', justifyContent: 'space-between', flexWrap: 'wrap', gap: 8 }}>
            <button type="button" className="legatura" onClick={() => { setPas('email'); setCod(''); setEroare(null); }}>
              Altă adresă
            </button>
            <button type="button" className="legatura" disabled={lucreaza} onClick={() => void cereCod()}>
              Trimite din nou
            </button>
          </div>
        </form>
      )}

      <div style={{ textAlign: 'center', display: 'flex', flexDirection: 'column', gap: 4, alignItems: 'center' }}>
        <button type="button" className="buton secundar" onClick={continuaFaraCont}>
          Continuă fără cont
        </button>
        <p className="ajutor" style={{ maxWidth: 320 }}>Harta ciclului merge și fără cont, doar pe telefonul tău.</p>
      </div>
    </main>
  );
}
