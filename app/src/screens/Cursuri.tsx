import { useEffect, useMemo, useState } from 'react';
import { api, mesajEroare } from '../api';
import type { Lectie as TLectie, Modul } from '../api/types';
import { BaraSus, CereCont, Coperta, deschideLink, Eroare, Incarcare } from '../components/Comune';
import { IcBifa, IcLacat, IcPdf, IcSageataDr, IcSunet, IcText, IcVideo } from '../components/Icoane';
import { useStarePlayer } from '../components/MiniPlayer';
import { useIncarca } from '../lib/date';
import { dataLunga, durata, nr } from '../lib/format';
import { comuta, pauza, pozitieSalvata, reda } from '../lib/player';
import { mergi } from '../lib/ruta';
import { useSesiune } from '../lib/sesiune';
import { CHEI, scrie } from '../lib/stocare';
import { curataHtml } from '../lib/html';

export function Cursuri() {
  const { token } = useSesiune();
  const { date, eroare, seIncarca, dinCache, reincarca } = useIncarca(token ? () => api.cursuri() : null, [token], token ? CHEI.cacheCursuri : undefined);
  return (
    <>
      <header className="antet">
        <span className="et">Sala de curs</span>
        <h1>Cursurile mele</h1>
        <p className="sub">Lecțiile tale, în ritmul tău. Progresul se păstrează pe orice dispozitiv.</p>
      </header>
      {!token ? (
        <CereCont titlu="Cursurile tale" text="Intră în cont ca să vezi cursurile la care ai acces și să continui de unde ai rămas." />
      ) : (
        <>
          {dinCache && <p className="ajutor">Ești fără internet. Vezi lista salvată.</p>}
          {seIncarca && !date && <Incarcare />}
          {eroare && !date && <Eroare text={eroare} reincearca={reincarca} />}
          {date && !date.length && (
            <div className="card piersica gol" style={{ color: 'var(--text)' }}>
              <h2>Încă nu ai cursuri aici</h2>
              <p>Când ai acces la un curs de pe adelaharu.ro, îl găsești aici, cu toate lecțiile.</p>
            </div>
          )}
          <div className="lista" style={{ gap: 18 }}>
            {date?.map((c) => (
              <button key={c.slug} type="button" className="card" style={{ padding: 0, border: 0, textAlign: 'left', overflow: 'hidden', color: 'inherit' }} onClick={() => mergi('cursuri/' + encodeURIComponent(c.slug))}>
                <Coperta poza={c.imagine} cheie={c.slug} clasa="lata" marimeIcoana={44} />
                <div style={{ padding: '16px 20px 20px', display: 'flex', flexDirection: 'column', gap: 10 }}>
                  <h2 style={{ fontSize: 23 }}>{c.nume}</h2>
                  <div className="progres" aria-hidden="true">
                    <span style={{ width: Math.round(c.progres * 100) + '%' }} />
                  </div>
                  <div className="meta" style={{ justifyContent: 'space-between' }}>
                    <span>{Math.round(c.progres * 100)}% parcurs{c.module_total ? ' · ' + c.module_terminate + ' din ' + nr(c.module_total, 'modul', 'module') : ''}</span>
                    {c.pana_la && <span>Acces până la {dataLunga(c.pana_la)}</span>}
                  </div>
                </div>
              </button>
            ))}
          </div>
        </>
      )}
    </>
  );
}

function IcoanaTip({ tip }: { tip: TLectie['tip'] }) {
  const P = { marime: 18 };
  return tip === 'video' ? <IcVideo {...P} /> : tip === 'audio' ? <IcSunet {...P} /> : tip === 'pdf' ? <IcPdf {...P} /> : <IcText {...P} />;
}
const NUME_TIP: Record<TLectie['tip'], string> = { video: 'Video', audio: 'Audio', pdf: 'PDF', text: 'Text' };

function useCurs(slug: string) {
  return useIncarca(() => api.curs(slug), [slug], 'ah_cache_curs_' + slug);
}

function ModulCard({ m, slug, deschisImplicit }: { m: Modul; slug: string; deschisImplicit: boolean }) {
  const [deschis, setDeschis] = useState(deschisImplicit);
  const gata = m.lectii.filter((l) => l.terminata).length;
  return (
    <section className="modul">
      <button type="button" className="cap" aria-expanded={deschis} onClick={() => setDeschis(!deschis)} disabled={!m.deschis}>
        <span className="corp">
          <b>{m.titlu}</b>
          <small>{m.deschis ? `${gata} din ${nr(m.lectii.length, 'lecție', 'lecții')}` : m.se_deschide_la ? 'Se deschide pe ' + dataLunga(m.se_deschide_la) : 'Se deschide în curând'}</small>
        </span>
        {m.deschis ? <IcSageataDr style={{ transform: deschis ? 'rotate(90deg)' : 'none', transition: 'transform .2s', color: 'var(--text-2)' }} /> : <IcLacat marime={20} style={{ color: 'var(--text-2)' }} />}
      </button>
      {m.deschis && deschis &&
        m.lectii.map((l) => (
          <button key={l.cheie} type="button" className="lectie" onClick={() => mergi(`cursuri/${encodeURIComponent(slug)}/l/${encodeURIComponent(l.cheie)}`)}>
            <span className={'cerc-bifa' + (l.terminata ? ' gata' : '')} aria-label={l.terminata ? 'Terminată' : 'Neterminată'}>
              {l.terminata ? <IcBifa marime={16} /> : <IcoanaTip tip={l.tip} />}
            </span>
            <span className="corp">
              <span>{l.titlu}</span>
              <small>{[NUME_TIP[l.tip], durata(l.durata)].filter(Boolean).join(' · ')}</small>
            </span>
          </button>
        ))}
    </section>
  );
}

export function Curs({ slug }: { slug: string }) {
  const { date: c, eroare, seIncarca, reincarca } = useCurs(slug);
  if (!c) {
    return (
      <>
        <BaraSus implicit="cursuri" />
        {seIncarca ? <Incarcare /> : <Eroare text={eroare || 'Nu am găsit cursul.'} reincearca={reincarca} />}
      </>
    );
  }
  const toate = c.module.flatMap((m) => (m.deschis ? m.lectii : []));
  const urm = toate.find((l) => !l.terminata);
  const primulNeterminat = c.module.findIndex((m) => m.deschis && m.lectii.some((l) => !l.terminata));
  return (
    <>
      <BaraSus implicit="cursuri" titlu="Cursurile mele" />
      <header className="antet">
        <h1>{c.nume}</h1>
        {c.descriere && <p className="sub">{c.descriere}</p>}
      </header>
      {urm && (
        <button type="button" className="buton lat" onClick={() => mergi(`cursuri/${encodeURIComponent(slug)}/l/${encodeURIComponent(urm.cheie)}`)}>
          Continuă: {urm.titlu}
        </button>
      )}
      <div className="lista">
        {c.module.map((m, i) => (
          <ModulCard key={m.id} m={m} slug={slug} deschisImplicit={i === (primulNeterminat < 0 ? 0 : primulNeterminat)} />
        ))}
      </div>
    </>
  );
}

export function Lectie({ slug, cheie }: { slug: string; cheie: string }) {
  const { date: c, eroare, seIncarca, reincarca } = useCurs(slug);
  const s = useStarePlayer();
  const [terminata, setTerminata] = useState<boolean | null>(null);
  const [err, setErr] = useState<string | null>(null);
  const [lucreaza, setLucreaza] = useState(false);

  const gasit = useMemo(() => {
    if (!c) return null;
    const lectii = c.module.flatMap((m) => (m.deschis ? m.lectii.map((l) => ({ l, m })) : []));
    const i = lectii.findIndex((x) => x.l.cheie === cheie);
    return i < 0 ? null : { ...lectii[i], urm: lectii[i + 1]?.l, i };
  }, [c, cheie]);

  useEffect(() => {
    setTerminata(null);
    setErr(null);
    window.scrollTo({ top: 0 });
  }, [cheie]);

  useEffect(() => {
    if (!c || !gasit) return;
    void scrie(CHEI.ultima + '_lectie', { slug, cheie, titlu: gasit.l.titlu, curs: c.nume, la: Date.now() });
  }, [c, gasit, slug, cheie]);

  if (!c || !gasit) {
    return (
      <>
        <BaraSus implicit={'cursuri/' + encodeURIComponent(slug)} />
        {seIncarca ? <Incarcare /> : <Eroare text={eroare || 'Lecția nu e disponibilă încă.'} reincearca={reincarca} />}
      </>
    );
  }
  const { l, m, urm } = gasit;
  const eTerminata = terminata ?? l.terminata;
  const aceasta = s.curent?.tip === 'lectie' && s.curent.id === l.cheie;

  async function marcheaza() {
    setErr(null);
    setLucreaza(true);
    try {
      await api.progres(slug, l.cheie, !eTerminata);
      setTerminata(!eTerminata);
      l.terminata = !eTerminata;
    } catch (e) {
      setErr(mesajEroare(e));
    } finally {
      setLucreaza(false);
    }
  }

  async function ascultaLectia() {
    if (!c || !l.fisier) return;
    if (aceasta) {
      comuta();
      return;
    }
    await reda({ tip: 'lectie', id: l.cheie, titlu: l.titlu, subtitlu: c.nume, poza: c.imagine, durata: l.durata || 0, curs: slug, ruta: `#/cursuri/${encodeURIComponent(slug)}/l/${encodeURIComponent(l.cheie)}` }, l.fisier, pozitieSalvata('lectie', l.cheie));
  }

  return (
    <>
      <BaraSus implicit={'cursuri/' + encodeURIComponent(slug)} titlu={c.nume} />
      <header className="antet">
        <span className="et">{m.titlu}</span>
        <h1 style={{ fontSize: 30 }}>{l.titlu}</h1>
        <div className="meta">{[NUME_TIP[l.tip], durata(l.durata)].filter(Boolean).join(' · ')}</div>
      </header>

      {l.tip === 'video' && l.player && (
        <div className="cadru-lectie">
          <iframe key={l.cheie} src={l.player} title={l.titlu} allow="autoplay; fullscreen; picture-in-picture; encrypted-media" allowFullScreen referrerPolicy="strict-origin-when-cross-origin" onLoad={() => pauza()} />
        </div>
      )}
      {l.tip === 'audio' && l.fisier && (
        <button type="button" className="buton lat" onClick={() => void ascultaLectia()}>
          {aceasta && s.canta ? 'Pauză' : aceasta ? 'Continuă ascultarea' : 'Ascultă lecția'}
        </button>
      )}
      {l.tip === 'pdf' && l.fisier && (
        <button type="button" className="buton secundar lat" onClick={() => deschideLink(l.fisier!)}>
          <IcPdf marime={20} /> Deschide PDF-ul
        </button>
      )}
      {l.text && <div className="text-lung" dangerouslySetInnerHTML={{ __html: curataHtml(l.text) }} />}

      {err && <Eroare text={err} />}
      <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
        <button type="button" className={'buton lat' + (eTerminata ? ' secundar' : '')} onClick={() => void marcheaza()} disabled={lucreaza}>
          {eTerminata ? (
            <>
              <IcBifa marime={20} /> Ai terminat lecția
            </>
          ) : (
            'Am terminat lecția'
          )}
        </button>
        {urm && (
          <button type="button" className="buton secundar lat" onClick={() => mergi(`cursuri/${encodeURIComponent(slug)}/l/${encodeURIComponent(urm.cheie)}`, true)}>
            Lecția următoare: {urm.titlu}
          </button>
        )}
      </div>
    </>
  );
}

