import { useEffect, useState } from 'react';
import { mesajEroare, MOCK } from '../api';
import { Dialog, deschideLink } from '../components/Comune';
import { citesteSetari, scrieSetari, SETARI_IMPLICITE, type Setari } from '../lib/ciclu-local';
import { dataLunga } from '../lib/format';
import { cerePermisiune, eNativ, programeazaFaze } from '../lib/notificari';
import { actualizeazaSubiecte, inregistreazaPush, pushDisponibil } from '../lib/push';
import { useSesiune } from '../lib/sesiune';
import { citesteCiclu } from '../lib/ciclu-local';
import { platforma } from '../lib/dispozitiv';

const VERSIUNE = '1.0.0';

export function Profil() {
  const { token, utilizatoare, eu, config, iesi, stergeCont, vreiSaIntri } = useSesiune();
  const [setari, setSetari] = useState<Setari>(SETARI_IMPLICITE);
  const [dialog, setDialog] = useState<'iesire' | 'sterge' | null>(null);
  const [eroare, setEroare] = useState<string | null>(null);
  const [nota, setNota] = useState<string | null>(null);
  const [lucreaza, setLucreaza] = useState(false);

  useEffect(() => void citesteSetari().then(setSetari), []);

  async function schimba(s: Setari) {
    setSetari(s);
    await scrieSetari(s);
  }

  const it = eu?.inner_temple;
  const c = config?.contact || { email: 'contact@adelaharu.ro', whatsapp: '+40742709306' };
  const l = config?.linkuri || { site: 'https://www.adelaharu.ro/', confidentialitate: 'https://www.adelaharu.ro/politica-de-confidentialitate/', termeni: 'https://www.adelaharu.ro/termeni-si-conditii/' };

  return (
    <>
      <header className="antet">
        <span className="et">Profil</span>
        <h1>{utilizatoare?.prenume || eu?.prenume || 'Contul meu'}</h1>
        {token && <p className="sub">{eu?.email || utilizatoare?.email}</p>}
      </header>

      {!token ? (
        <div className="card piersica gol" style={{ color: 'var(--text)' }}>
          <p>Ești în aplicație fără cont. Ciclul tău rămâne doar pe telefon.</p>
          <button type="button" className="buton" onClick={vreiSaIntri}>Intră în cont</button>
        </div>
      ) : (
        <section className="card sectiune">
          <h2>Accesul meu</h2>
          <p>
            {it?.activ ? <>Inner Temple: activ{it.pana_la ? <> până la {dataLunga(it.pana_la)}</> : null}.</> : 'Inner Temple: nu e activ pe această adresă.'}
          </p>
          {it?.activ && platforma() === 'android' && <p className="ajutor">Abonamentul se gestionează pe adelaharu.ro, din contul tău de pe site.</p>}
          {!!eu?.cursuri.length && <p>Cursuri: {eu.cursuri.map((x) => x.nume).join(', ')}.</p>}
          {!!eu?.meditatii_cumparate && <p>Meditații separate: {eu.meditatii_cumparate}.</p>}
        </section>
      )}

      <section className="card sectiune">
        <h2>Notificări</h2>
        <label className="comutator">
          <span className="txt">
            <span>Începutul fiecărei faze</span>
            <small>Din harta ciclului, la 9 dimineața. Stau doar pe telefon.</small>
          </span>
          <input
            type="checkbox"
            checked={setari.notifFaze}
            onChange={async (e) => {
              const v = e.target.checked;
              setNota(null);
              if (v && !(await cerePermisiune())) {
                setNota('Notificările sunt oprite pentru aplicație. Le poți porni din Setările telefonului.');
                return;
              }
              await schimba({ ...setari, notifFaze: v });
              const S = await citesteCiclu();
              await programeazaFaze(S, v);
              if (v && !S) setNota('Pornesc după ce îți completezi ciclul în „Ciclul meu”.');
            }}
          />
        </label>
        {token && (
          <>
            <label className="comutator">
              <span className="txt">
                <span>Meditații noi</span>
                <small>Când apare o meditație nouă în Inner Temple.</small>
              </span>
              <input
                type="checkbox"
                checked={setari.subiecte.meditatii_noi}
                onChange={async (e) => {
                  const s = { ...setari, subiecte: { ...setari.subiecte, meditatii_noi: e.target.checked } };
                  await schimba(s);
                  if (pushDisponibil()) {
                    await inregistreazaPush(s.subiecte);
                    void actualizeazaSubiecte(s.subiecte).catch(() => {});
                  }
                }}
              />
            </label>
            <label className="comutator">
              <span className="txt">
                <span>Cursuri</span>
                <small>Când se deschide un modul nou într-un curs al tău.</small>
              </span>
              <input
                type="checkbox"
                checked={setari.subiecte.cursuri}
                onChange={async (e) => {
                  const s = { ...setari, subiecte: { ...setari.subiecte, cursuri: e.target.checked } };
                  await schimba(s);
                  if (pushDisponibil()) {
                    await inregistreazaPush(s.subiecte);
                    void actualizeazaSubiecte(s.subiecte).catch(() => {});
                  }
                }}
              />
            </label>
            {!pushDisponibil() && <p className="ajutor">{eNativ() ? 'Notificările despre meditații și cursuri pornesc într-o versiune următoare.' : 'Notificările despre meditații și cursuri merg în aplicația de pe telefon.'}</p>}
          </>
        )}
        {nota && <p className="ajutor">{nota}</p>}
      </section>

      <section className="card sectiune">
        <h2>Ai nevoie de ajutor?</h2>
        <p>Îmi scrii oricând și îți răspund cu drag.</p>
        <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
          <button type="button" className="buton secundar lat" onClick={() => deschideLink('mailto:' + c.email)}>{c.email}</button>
          <button type="button" className="buton secundar lat" onClick={() => deschideLink('https://wa.me/' + c.whatsapp.replace(/\D/g, ''))}>WhatsApp</button>
        </div>
      </section>

      <section className="card sectiune">
        <h2>Despre date</h2>
        <div style={{ display: 'flex', flexDirection: 'column' }}>
          <button type="button" className="legatura" style={{ textAlign: 'left' }} onClick={() => deschideLink(l.confidentialitate)}>Politica de confidențialitate</button>
          <button type="button" className="legatura" style={{ textAlign: 'left' }} onClick={() => deschideLink(l.termeni)}>Termeni și condiții</button>
        </div>
      </section>

      {token && (
        <section className="sectiune">
          <button type="button" className="buton secundar lat" onClick={() => setDialog('iesire')}>Ieși din cont</button>
          <button type="button" className="buton pericol lat" onClick={() => setDialog('sterge')}>Șterge contul din aplicație</button>
        </section>
      )}
      {eroare && <div className="eroare">{eroare}</div>}
      <p className="avertisment">Adela Haru · versiunea {VERSIUNE}{MOCK ? ' · demo' : ''}</p>

      {dialog === 'iesire' && (
        <Dialog
          titlu="Ieși din cont?"
          inchide={() => setDialog(null)}
          butoane={
            <>
              <button type="button" className="buton lat" disabled={lucreaza} onClick={async () => { setLucreaza(true); await iesi(); setLucreaza(false); setDialog(null); }}>Ieși din cont</button>
              <button type="button" className="buton secundar lat" onClick={() => setDialog(null)}>Rămân</button>
            </>
          }
        >
          <p>Meditațiile descărcate se șterg de pe telefon. Ciclul tău rămâne aici. Intri din nou oricând, cu un cod pe email.</p>
        </Dialog>
      )}
      {dialog === 'sterge' && (
        <Dialog
          titlu="Ștergi contul din aplicație?"
          inchide={() => setDialog(null)}
          butoane={
            <>
              <button
                type="button"
                className="buton lat"
                disabled={lucreaza}
                onClick={async () => {
                  setLucreaza(true);
                  setEroare(null);
                  try {
                    await stergeCont();
                    setDialog(null);
                  } catch (er) {
                    setEroare(mesajEroare(er));
                    setDialog(null);
                  } finally {
                    setLucreaza(false);
                  }
                }}
              >
                {lucreaza ? 'Se șterge…' : 'Da, șterge'}
              </button>
              <button type="button" className="buton secundar lat" onClick={() => setDialog(null)}>Renunță</button>
            </>
          }
        >
          <p>Ștergem tot ce ține de aplicație pentru {eu?.email || utilizatoare?.email}: conectările pe telefoane, notificările, datele ciclului (și emailurile fazelor) și locul unde ai rămas în meditații.</p>
          <p>Comenzile, facturile și accesul tău la meditații și cursuri rămân neatinse; te poți conecta din nou oricând.</p>
        </Dialog>
      )}
    </>
  );
}
