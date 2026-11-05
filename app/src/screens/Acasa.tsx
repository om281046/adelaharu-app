import { useEffect, useState } from 'react';
import { aziLocal, frumos, unde, type CheieFaza } from '../ciclu/algoritm';
import { FAZE } from '../ciclu/faze';
import { Coperta } from '../components/Comune';
import { IcLacat, IcPlay } from '../components/Icoane';
import { useFazeApi, useMeditatii } from '../lib/date';
import { ceas, durata } from '../lib/format';
import { ultimaRedare, type UltimaRedare } from '../lib/player';
import { mergi } from '../lib/ruta';
import { useSesiune } from '../lib/sesiune';
import { CHEI, citeste } from '../lib/stocare';
import { useCiclu } from './Ciclu';
import { TEXT_LACAT } from './InnerTemple';

function salut(): string {
  const h = new Date().getHours();
  if (h < 11) return 'Bună dimineața';
  if (h < 18) return 'Bună ziua';
  return 'Bună seara';
}

/** Practica potrivită fazei: meditația din GET /ciclu/faze (sau, dacă nu e, nimic). */
export function SugestieFaza({ faza }: { faza: CheieFaza }) {
  const { token } = useSesiune();
  const fazeApi = useFazeApi();
  const { date } = useMeditatii(!!token);
  const id = fazeApi[faza]?.meditatie_id;
  const m = id ? date?.meditatii.find((x) => x.id === id) : undefined;
  if (!m) return null;
  const f = FAZE[faza];
  return (
    <section className="sectiune">
      <div className="cap"><h2>Pentru faza asta</h2></div>
      <button type="button" className="rand" onClick={() => mergi('inner-temple/m/' + m.id)} style={{ ['--fz' as string]: f.culoare }}>
        <Coperta poza={m.poza} cheie={m.categorie || m.titlu} />
        <span className="corp">
          <b>{m.titlu}</b>
          <small>{m.poate_asculta ? 'Meditație · ' + durata(m.durata) : TEXT_LACAT}</small>
        </span>
        {m.poate_asculta ? <IcPlay marime={22} style={{ color: 'var(--accent)' }} /> : <IcLacat marime={20} style={{ color: 'var(--text-2)' }} />}
      </button>
    </section>
  );
}

interface UltimaLectie {
  slug: string;
  cheie: string;
  titlu: string;
  curs: string;
  la: number;
}

export function Acasa() {
  const { token, utilizatoare, eu, config } = useSesiune();
  const S = useCiclu();
  const { date } = useMeditatii(!!token);
  const [ultima, setUltima] = useState<UltimaRedare | null>(null);
  const [lectie, setLectie] = useState<UltimaLectie | null>(null);

  useEffect(() => {
    void ultimaRedare().then(setUltima);
    void citeste<UltimaLectie | null>(CHEI.ultimaLectie, null).then(setLectie);
  }, []);

  const azi = aziLocal();
  const u = S ? unde(azi, S) : null;
  const f = u ? FAZE[u.faza] : null;
  const noi = (date?.meditatii || []).filter((m) => m.noua);
  const prenume = eu?.prenume || utilizatoare?.prenume;

  // „Continuă”: ce a fost mai recent dintre meditație/lecția audio și lecția deschisă
  const continua: { titlu: string; sub: string; ruta: string; progres?: number }[] = [];
  if (token && ultima && !ultima.terminat && (ultima.tip === 'meditatie' || ultima.curs)) {
    continua.push({ titlu: ultima.titlu, sub: (ultima.subtitlu || '') + (ultima.timp > 5 ? ' · de la ' + ceas(ultima.timp) : ''), ruta: ultima.ruta.replace(/^#\//, ''), progres: ultima.durata ? ultima.timp / ultima.durata : undefined });
  }
  if (token && lectie && !(ultima && ultima.tip === 'lectie' && ultima.id === lectie.cheie)) {
    const item = { titlu: lectie.titlu, sub: lectie.curs, ruta: `cursuri/${encodeURIComponent(lectie.slug)}/l/${encodeURIComponent(lectie.cheie)}` };
    if (!ultima || lectie.la > ultima.la) continua.unshift(item);
    else continua.push(item);
  }

  return (
    <>
      <header className="antet">
        <span className="et" style={{ textTransform: 'none', letterSpacing: '0.02em', fontSize: 15, fontWeight: 500, color: 'var(--text-2)' }}>
          {frumos(azi, true).replace(/^./, (c) => c.toUpperCase())}
        </span>
        <h1>{salut()}{prenume ? ', ' + prenume : ''}</h1>
      </header>

      {config?.mesaj && (
        <div className="card anunt">
          <h3>{config.mesaj.titlu}</h3>
          <p style={{ marginTop: 6 }}>{config.mesaj.text}</p>
        </div>
      )}

      {u && f ? (
        <button type="button" className="faza-card" style={{ ['--fz' as string]: f.culoare }} onClick={() => mergi('ciclu')}>
          <span className="et">Azi · ziua {u.zi} din {S!.c}</span>
          <span className="mare">
            {f.arhetip} <em>· {f.anotimp}</em>
          </span>
          <span>{f.pe_scurt}</span>
          <span style={{ color: 'var(--accent)', fontWeight: 600 }}>Vezi harta ciclului</span>
        </button>
      ) : (
        <button type="button" className="faza-card" style={{ ['--fz' as string]: '#C29A4A' }} onClick={() => mergi('ciclu')}>
          <span className="et">Ciclul meu</span>
          <span className="mare">În ce fază ești azi?</span>
          <span>Pornind de la data menstruației, îți arăt faza ta și ce te ajută în ea. Datele rămân pe telefon.</span>
          <span style={{ color: 'var(--accent)', fontWeight: 600 }}>Află faza ta</span>
        </button>
      )}

      {u && <SugestieFaza faza={u.faza} />}

      {continua.length > 0 && (
        <section className="sectiune">
          <div className="cap"><h2>Continuă</h2></div>
          <div className="lista">
            {continua.slice(0, 2).map((c) => (
              <button key={c.ruta} type="button" className="rand" onClick={() => mergi(c.ruta)}>
                <Coperta cheie={c.sub || c.titlu} />
                <span className="corp">
                  <b>{c.titlu}</b>
                  <small>{c.sub}</small>
                  {c.progres !== undefined && (
                    <span className="progres" style={{ marginTop: 6 }}><span style={{ width: Math.round(c.progres * 100) + '%' }} /></span>
                  )}
                </span>
                <IcPlay marime={22} style={{ color: 'var(--accent)' }} />
              </button>
            ))}
          </div>
        </section>
      )}

      {noi.length > 0 && (
        <section className="sectiune">
          <div className="cap">
            <h2>Meditații noi</h2>
            <button type="button" className="buton discret" onClick={() => mergi('inner-temple')}>Toate</button>
          </div>
          <div className="carusel">
            {noi.map((m) => (
              <button key={m.id} type="button" className="placa" onClick={() => mergi('inner-temple/m/' + m.id)}>
                <Coperta poza={m.poza} cheie={m.categorie || m.titlu} marimeIcoana={40} />
                <b>{m.titlu}</b>
                <small>{m.poate_asculta ? durata(m.durata) : TEXT_LACAT}</small>
              </button>
            ))}
          </div>
        </section>
      )}

      {!token && (
        <div className="card piersica gol" style={{ color: 'var(--text)' }}>
          <h2>Meditațiile și cursurile tale</h2>
          <p>Intră în cont cu adresa ta de email ca să le găsești aici.</p>
          <IntraButon />
        </div>
      )}
    </>
  );
}

function IntraButon() {
  const { vreiSaIntri } = useSesiune();
  return (
    <button type="button" className="buton" onClick={vreiSaIntri}>
      Intră în cont
    </button>
  );
}
