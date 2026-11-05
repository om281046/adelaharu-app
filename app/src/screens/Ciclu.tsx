import { useCallback, useEffect, useState } from 'react';
import { mesajEroare } from '../api';
import {
  aziLocal, calculeaza, frumos, luna, MESAJE_EROARE, startNou, unde, urmatoareleFaze, type StareCiclu,
} from '../ciclu/algoritm';
import { AVERTISMENT, FAZE } from '../ciclu/faze';
import { Dialog, deschideLink } from '../components/Comune';
import { IcClopot } from '../components/Icoane';
import { LunaMica, Roata } from '../components/Roata';
import {
  ACORD_IMPLICIT, citesteAcord, citesteCiclu, citesteSetari, opresteSincronizarea, salveazaCiclu, scrieAcord, scrieSetari,
  sincronizeaza, stergeDateleCiclului, type AcordCiclu,
} from '../lib/ciclu-local';
import { cerePermisiune, eNativ, programeazaFaze } from '../lib/notificari';
import { useSesiune } from '../lib/sesiune';
import { SugestieFaza } from './Acasa';

const DAT: Record<string, string> = { 'Luna nouă': 'Lunii noi', 'Luna în creștere': 'Lunii în creștere', 'Luna plină': 'Lunii pline', 'Luna în descreștere': 'Lunii în descreștere' };

/** Hook comun pentru Acasă și Ciclul meu. */
export function useCiclu() {
  const [S, setS] = useState<StareCiclu | null | undefined>(undefined);
  const reincarca = useCallback(() => void citesteCiclu().then(setS), []);
  useEffect(() => {
    reincarca();
    window.addEventListener('ah-ciclu', reincarca);
    return () => window.removeEventListener('ah-ciclu', reincarca);
  }, [reincarca]);
  return S;
}
const anuntaCiclu = () => window.dispatchEvent(new Event('ah-ciclu'));

function Pas({ eticheta, valoare, min, max, set, minus, plusTxt }: { eticheta: string; valoare: number; min: number; max: number; set: (n: number) => void; minus: string; plusTxt: string }) {
  const id = 'pas-' + eticheta.replace(/\W/g, '');
  return (
    <div className="camp">
      <span className="eticheta" id={id}>{eticheta}</span>
      <div className="pas" role="group" aria-labelledby={id}>
        <button type="button" aria-label={minus} onClick={() => set(Math.max(min, valoare - 1))}>−</button>
        <output aria-live="polite">{valoare}</output>
        <button type="button" aria-label={plusTxt} onClick={() => set(Math.min(max, valoare + 1))}>+</button>
      </div>
    </div>
  );
}

function Formular({ S, gata, renunta }: { S: StareCiclu | null; gata: (s: StareCiclu) => void; renunta?: () => void }) {
  const azi = aziLocal();
  const [start, setStart] = useState(S?.s || '');
  const [m, setM] = useState(S?.m || 5);
  const [c, setC] = useState(S?.c || 28);
  const [eroare, setEroare] = useState<string | null>(null);
  return (
    <form
      className="card"
      style={{ display: 'flex', flexDirection: 'column', gap: 20 }}
      noValidate
      onSubmit={(e) => {
        e.preventDefault();
        const r = calculeaza(S, start, c, m, aziLocal());
        if (!r.ok) {
          setEroare(MESAJE_EROARE[r.eroare]);
          return;
        }
        gata(r.stare);
      }}
    >
      <h2>În ce fază ești azi?</h2>
      <div className="camp">
        <label htmlFor="start">Prima zi a ultimei menstruații</label>
        <input id="start" className="intrare" type="date" max={azi} value={start} onChange={(e) => setStart(e.target.value)} />
      </div>
      <Pas eticheta="Câte zile sângerezi" valoare={m} min={2} max={10} set={setM} minus="Mai puține zile" plusTxt="Mai multe zile" />
      <Pas eticheta="Câte zile are ciclul" valoare={c} min={21} max={45} set={setC} minus="Ciclu mai scurt" plusTxt="Ciclu mai lung" />
      <p className="ajutor">Ciclul se numără din prima zi de sângerare (petele nu se iau în calcul) până în ziua dinaintea următoarei menstruații. Dacă nu știi câte zile are al tău, lasă 28.</p>
      {eroare && <div className="eroare" role="alert">{eroare}</div>}
      <button className="buton lat" type="submit">Vezi în ce fază ești</button>
      {renunta && (
        <button type="button" className="buton discret" onClick={renunta}>Renunță</button>
      )}
      <p className="ajutor">Datele rămân doar pe telefonul tău. {AVERTISMENT}</p>
    </form>
  );
}

function AVenit({ S, gata }: { S: StareCiclu; gata: (s: StareCiclu) => void }) {
  const [deschis, setDeschis] = useState(false);
  const azi = aziLocal();
  const [d, setD] = useState(azi);
  const [er, setEr] = useState<string | null>(null);
  if (!deschis) {
    return (
      <div className="card piersica" style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
        <p>Ți-a venit menstruația? Spune-mi ziua și potrivesc fazele. Cu fiecare lună, socoteala se apropie de ritmul tău.</p>
        <button type="button" className="buton lat" onClick={() => setDeschis(true)}>Mi-a venit menstruația</button>
      </div>
    );
  }
  return (
    <div className="card piersica" style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
      <div className="camp">
        <label htmlFor="noua">Prima zi a menstruației</label>
        <input id="noua" className="intrare" type="date" max={azi} value={d} onChange={(e) => setD(e.target.value)} />
      </div>
      {er && <div className="eroare">{er}</div>}
      <button
        type="button"
        className="buton lat"
        onClick={() => {
          const n = startNou(S, d, aziLocal());
          if (!n) {
            setEr(d > azi ? MESAJE_EROARE.in_viitor : MESAJE_EROARE.prea_veche);
            return;
          }
          setDeschis(false);
          gata(n);
        }}
      >
        Potrivește fazele
      </button>
      <button type="button" className="buton discret" onClick={() => setDeschis(false)}>Renunță</button>
    </div>
  );
}

function Rezultat({ S }: { S: StareCiclu }) {
  const azi = aziLocal();
  const u = unde(azi, S);
  if (!u) return null;
  const f = FAZE[u.faza];
  const L = luna(azi);
  const urm = urmatoareleFaze(S, azi);
  return (
    <>
      <section className="azi-faza" style={{ ['--fz' as string]: f.culoare }}>
        <p className="ajutor">{frumos(azi, true).replace(/^./, (x) => x.toUpperCase())} · ziua {u.zi} din {S.c}</p>
        <p className="faza">
          {f.nume}
          <em>{f.arhetip} · {f.anotimp}</em>
        </p>
        <Roata S={S} u={u} azi={azi} />
        <p className="text-lung" style={{ fontSize: 17 }}>{f.pe_scurt}</p>
        <div className="luna-cer">
          <LunaMica f={L.f} />
          <span>Pe cer e acum {L.nume.charAt(0).toLowerCase() + L.nume.slice(1)}. Faza ta corespunde {DAT[f.luna] || f.luna}.</span>
        </div>
      </section>

      <section className="card sectiune">
        <h2>Ce simți acum</h2>
        <p>{f.simti}</p>
        <h3 style={{ marginTop: 6 }}>Ce te ajută</h3>
        <ul className="ajuta">
          {f.ajuta.map((t) => (
            <li key={t}><span className="punct" style={{ background: f.culoare }} />{t}</li>
          ))}
        </ul>
        <p className="citat">{f.practica}</p>
      </section>

      <SugestieFaza faza={u.faza} />

      <section className="card sectiune">
        <h2>Următoarele faze</h2>
        <div className="urmatoare">
          {urm.map((x) => (
            <div key={x.k}>
              <span><span className="punct" style={{ background: FAZE[x.k].culoare }} />{x.k === 'menstruala' ? 'Menstruația, cam' : FAZE[x.k].arhetip}</span>
              <b style={{ fontWeight: 600 }}>{frumos(x.d)}</b>
            </div>
          ))}
        </div>
      </section>
    </>
  );
}

function Notificari({ S }: { S: StareCiclu }) {
  const [activ, setActiv] = useState(false);
  const [mesaj, setMesaj] = useState<string | null>(null);
  useEffect(() => void citesteSetari().then((s) => setActiv(s.notifFaze)), []);
  return (
    <label className="comutator">
      <span className="txt">
        <span style={{ display: 'flex', gap: 8, alignItems: 'center' }}><IcClopot marime={18} /> Anunță-mă când începe o fază</span>
        <small>{mesaj || 'La 9 dimineața, în ziua în care începe fiecare fază. Notificările se fac pe telefon, nu trimitem nimic nicăieri.'}</small>
      </span>
      <input
        type="checkbox"
        checked={activ}
        onChange={async (e) => {
          const v = e.target.checked;
          setMesaj(null);
          if (v && !(await cerePermisiune())) {
            setMesaj('Notificările sunt oprite pentru aplicație. Le poți porni din Setările telefonului.');
            return;
          }
          setActiv(v);
          const s = await citesteSetari();
          await scrieSetari({ ...s, notifFaze: v });
          const n = await programeazaFaze(S, v);
          if (v && !eNativ()) setMesaj('În aplicația de pe telefon primești notificările. Aici, în browser, doar se salvează alegerea.');
          else if (v) setMesaj(`Gata. Am programat ${n} notificări pentru următoarele luni.`);
        }}
      />
    </label>
  );
}

function Sincronizare({ S }: { S: StareCiclu }) {
  const { token, config } = useSesiune();
  const [acord, setAcord] = useState<AcordCiclu>(ACORD_IMPLICIT);
  const [bifa, setBifa] = useState(false);
  const [emailuri, setEmailuri] = useState(false);
  const [mesaj, setMesaj] = useState<{ ok: boolean; t: string } | null>(null);
  const [lucreaza, setLucreaza] = useState(false);
  useEffect(() => void citesteAcord().then((a) => { setAcord(a); setEmailuri(a.emailuri); }), []);
  if (!token) return null;

  const pornit = acord.sanatate;
  return (
    <section className="card sectiune">
      <h2>Păstrează datele și pe adelaharu.ro</h2>
      {pornit ? (
        <>
          <p>Datele ciclului tău sunt salvate și pe adelaharu.ro{acord.emailuri ? ' și primești emailurile fazelor' : ''}. Când îmi spui că a venit menstruația, se actualizează singure.</p>
          <label className="bifa">
            <input
              type="checkbox"
              checked={emailuri}
              onChange={async (e) => {
                const v = e.target.checked;
                setEmailuri(v);
                setLucreaza(true);
                try {
                  const a = { ...acord, emailuri: v };
                  await sincronizeaza(S, a);
                  await scrieAcord({ ...a, sincronizatLa: Date.now() });
                  setAcord(a);
                  setMesaj({ ok: true, t: v ? 'Primești emailurile fazelor.' : 'Am oprit emailurile fazelor.' });
                } catch (er) {
                  setEmailuri(!v);
                  setMesaj({ ok: false, t: mesajEroare(er) });
                } finally {
                  setLucreaza(false);
                }
              }}
              disabled={lucreaza}
            />
            <span>Vreau și emailurile fazelor (patru pe lună, la începutul fiecărei faze)</span>
          </label>
          <button
            type="button"
            className="buton secundar lat"
            disabled={lucreaza}
            onClick={async () => {
              setLucreaza(true);
              try {
                await opresteSincronizarea();
                setAcord(ACORD_IMPLICIT);
                setBifa(false);
                setMesaj({ ok: true, t: 'Gata. Am șters datele ciclului de pe site. Pe telefon rămân.' });
              } catch (er) {
                setMesaj({ ok: false, t: mesajEroare(er) });
              } finally {
                setLucreaza(false);
              }
            }}
          >
            Oprește și șterge de pe site
          </button>
        </>
      ) : (
        <>
          <p>Opțional. Așa îți găsești harta și pe site, iar dacă vrei, îți scriu la începutul fiecărei faze.</p>
          <label className="bifa">
            <input type="checkbox" checked={bifa} onChange={(e) => setBifa(e.target.checked)} />
            <span>Sunt de acord ca HARU TRAINING SRL să păstreze datele ciclului meu (prima zi a menstruației, câte zile sângerezi și câte zile are ciclul) pe adelaharu.ro. Le pot șterge oricând, din aplicație sau din orice email; dacă nu le actualizez 12 luni, se șterg singure.</span>
          </label>
          <label className="bifa">
            <input type="checkbox" checked={emailuri} onChange={(e) => setEmailuri(e.target.checked)} disabled={!bifa} />
            <span>Vreau și emailurile fazelor (patru pe lună)</span>
          </label>
          <button
            type="button"
            className="buton lat"
            disabled={!bifa || lucreaza}
            onClick={async () => {
              setLucreaza(true);
              setMesaj(null);
              try {
                const a: AcordCiclu = { sanatate: true, emailuri, sincronizatLa: null };
                await sincronizeaza(S, a);
                setAcord({ ...a, sincronizatLa: Date.now() });
                setMesaj({ ok: true, t: 'Gata, datele sunt salvate pe adelaharu.ro.' });
              } catch (er) {
                setMesaj({ ok: false, t: mesajEroare(er) });
              } finally {
                setLucreaza(false);
              }
            }}
          >
            Salvează pe adelaharu.ro
          </button>
          <p className="ajutor">
            Datele despre menstruație sunt date de sănătate. Fără bifa ta, nu pleacă de pe telefon.{' '}
            <button type="button" className="legatura" style={{ padding: 0, minHeight: 0, fontSize: 'inherit' }} onClick={() => deschideLink(config?.linkuri.confidentialitate || 'https://www.adelaharu.ro/politica-de-confidentialitate/')}>
              Politica de confidențialitate
            </button>
          </p>
        </>
      )}
      {mesaj && <div className={mesaj.ok ? 'succes' : 'eroare'}>{mesaj.t}</div>}
    </section>
  );
}

export function Ciclu() {
  const { token } = useSesiune();
  const S = useCiclu();
  const [editez, setEditez] = useState(false);
  const [sterg, setSterg] = useState(false);
  const [mesaj, setMesaj] = useState<string | null>(null);
  const [info, setInfo] = useState<string | null>(null);

  async function salveaza(n: StareCiclu) {
    setMesaj(null);
    setInfo(null);
    const r = await salveazaCiclu(n, !!token);
    const a = await citesteAcord();
    if (token && a.sanatate && !r.sincronizat) setMesaj('Am salvat pe telefon. Pe site se actualizează singur când revine internetul.');
    setEditez(false);
    anuntaCiclu();
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  if (S === undefined) return null;
  const azi = aziLocal();
  const valid = !!(S && unde(azi, S));

  return (
    <>
      <header className="antet">
        <span className="et">Harta ciclului</span>
        <h1>Ciclul meu</h1>
        {!S && <p className="sub">În interiorul ciclului dat de fazele Lunii se află și ciclul tău personal, pe care îl afli pornind de la data menstruației.</p>}
      </header>

      {info && <div className="succes" role="status">{info}</div>}
      {!S || editez || !valid ? (
        <Formular S={S} gata={(n) => void salveaza(n)} renunta={S && valid ? () => setEditez(false) : undefined} />
      ) : (
        <>
          <Rezultat S={S} />
          {mesaj && <div className="succes">{mesaj}</div>}
          <AVenit S={S} gata={(n) => void salveaza(n)} />
          <section className="card sectiune">
            <h2>Notificări</h2>
            <Notificari S={S} />
          </section>
          <Sincronizare S={S} />
          <section className="sectiune" style={{ alignItems: 'center' }}>
            <button type="button" className="buton secundar lat" onClick={() => setEditez(true)}>Modifică datele ciclului</button>
            <button type="button" className="buton pericol lat" onClick={() => setSterg(true)}>Șterge datele mele</button>
          </section>
        </>
      )}
      <p className="avertisment">{AVERTISMENT}</p>

      {sterg && (
        <Dialog
          titlu="Ștergi datele ciclului?"
          inchide={() => setSterg(false)}
          butoane={
            <>
              <button
                type="button"
                className="buton lat"
                onClick={async () => {
                  const r = await stergeDateleCiclului(!!token);
                  setSterg(false);
                  setEditez(false);
                  setMesaj(null);
                  anuntaCiclu();
                  setInfo(
                    r === 'sters'
                      ? 'Gata. Am șters datele ciclului, de pe telefon și de pe adelaharu.ro.'
                      : r === 'site_in_asteptare'
                        ? 'Pe telefon s-au șters. Pe site nu am putut ajunge acum; se șterg de acolo singure, imediat ce ai internet. Dacă vrei, scrie-ne la contact@adelaharu.ro.'
                        : 'Gata. Am șters datele ciclului de pe telefon. Dacă le-ai salvat și pe adelaharu.ro, intră în cont și apasă din nou „Șterge datele mele”, sau folosește linkul de oprire din emailurile fazelor.',
                  );
                }}
              >
                Da, șterge tot
              </button>
              <button type="button" className="buton secundar lat" onClick={() => setSterg(false)}>Nu, păstrează-le</button>
            </>
          }
        >
          <p>
            Se șterg de pe telefon prima zi a menstruației, câte zile sângerezi, câte zile are ciclul și notificările fazelor
            {token
              ? ', iar dacă le-ai salvat și pe adelaharu.ro, și de acolo.'
              : '. Dacă le-ai salvat și pe adelaharu.ro, acolo rămân: ca să le ștergi și de pe site, intră în cont sau folosește linkul de oprire din emailurile fazelor.'}
          </p>
        </Dialog>
      )}
    </>
  );
}
