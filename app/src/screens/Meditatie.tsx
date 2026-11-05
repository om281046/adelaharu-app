import { useEffect, useState } from 'react';
import { api, mesajEroare } from '../api';
import { BaraSus, Coperta, Dialog, Eroare, Incarcare, StratVideo } from '../components/Comune';
import { IcCos, IcDescarca, IcInainte15, IcInapoi15, IcLacat, IcPauza, IcPlay, IcVideo, IcBifa } from '../components/Icoane';
import { useStarePlayer } from '../components/MiniPlayer';
import { useMeditatii } from '../lib/date';
import { ceas, durata } from '../lib/format';
import { ascultaDescarcari, descarca, progresDescarcare, stergeDescarcare, urlLocal } from '../lib/offline';
import { cauta, comuta, pauza, pozitieSalvata, reda, sari } from '../lib/player';
import { useSesiune } from '../lib/sesiune';
import { TEXT_LACAT, useDescarcate } from './InnerTemple';

export function Meditatie({ id }: { id: number }) {
  const { token } = useSesiune();
  const { date, eroare, reincarca } = useMeditatii(!!token);
  const s = useStarePlayer();
  const desc = useDescarcate();
  const [video, setVideo] = useState(false);
  const [err, setErr] = useState<string | null>(null);
  const [progres, setProgres] = useState<number | undefined>(progresDescarcare(id));
  const [intreabaSterge, setIntreabaSterge] = useState(false);
  const [trage, setTrage] = useState<number | null>(null);

  useEffect(() => ascultaDescarcari(() => setProgres(progresDescarcare(id))), [id]);

  const m = date?.meditatii.find((x) => x.id === id);
  const numeCat = date?.categorii.find((c) => c.slug === m?.categorie)?.nume || '';
  const aceasta = s.curent?.tip === 'meditatie' && s.curent.id === String(id);
  const descarcata = desc.has(id);

  if (!m) {
    return (
      <>
        <BaraSus implicit="inner-temple" />
        {eroare ? <Eroare text={eroare} reincearca={reincarca} /> : date ? <Eroare text="Nu am găsit meditația." /> : <Incarcare />}
      </>
    );
  }

  async function porneste() {
    if (!m) return;
    setErr(null);
    if (aceasta) {
      comuta();
      return;
    }
    try {
      const local = await urlLocal(m.id);
      const url = local || (await api.redare(m.id)).url;
      const start = pozitieSalvata('meditatie', String(m.id)) || m.pozitie || 0;
      await reda({ tip: 'meditatie', id: String(m.id), titlu: m.titlu, subtitlu: numeCat || 'Inner Temple', poza: m.poza, durata: m.durata, ruta: '#/inner-temple/m/' + m.id }, url, start);
    } catch (e) {
      setErr(mesajEroare(e));
    }
  }

  async function descarcaAcum() {
    if (!m) return;
    setErr(null);
    try {
      await descarca(m.id, m.titlu);
    } catch (e) {
      setErr(e instanceof Error && e.name === 'ApiError' ? mesajEroare(e) : 'Descărcarea nu a mers. Verifică, te rog, conexiunea și spațiul liber de pe telefon.');
    }
  }

  const dur = aceasta ? s.durata || m.durata : m.durata;
  const timp = trage ?? (aceasta ? s.timp : pozitieSalvata('meditatie', String(m.id)) || m.pozitie || 0);
  const pr = dur ? Math.min(100, (timp / dur) * 100) : 0;

  return (
    <>
      <BaraSus implicit="inner-temple" titlu={numeCat || 'Inner Temple'} />
      <div className="player">
        <Coperta poza={m.poza} cheie={m.categorie || m.titlu} clasa="mare" marimeIcoana={72} />
        <div className="titluri">
          <h1>{m.titlu}</h1>
          <div className="meta" style={{ justifyContent: 'center' }}>
            {[numeCat, durata(m.durata)].filter(Boolean).join(' · ')}
            {m.noua && <span className="insigna noua">Nouă</span>}
          </div>
        </div>

        {m.poate_asculta ? (
          <>
            <div>
              <input
                className="cursor"
                type="range"
                min={0}
                max={Math.max(1, Math.floor(dur))}
                step={1}
                value={Math.floor(timp)}
                style={{ ['--p' as string]: pr + '%' }}
                aria-label="Poziția în meditație"
                disabled={!aceasta}
                onChange={(e) => setTrage(+e.target.value)}
                onPointerUp={() => { if (trage != null) cauta(trage); setTrage(null); }}
                onKeyUp={() => { if (trage != null) cauta(trage); setTrage(null); }}
              />
              <div className="timpi">
                <span>{ceas(timp)}</span>
                <span>-{ceas(Math.max(0, dur - timp))}</span>
              </div>
            </div>
            <div className="comenzi">
              <button type="button" className="icon-buton" onClick={() => sari(-15)} disabled={!aceasta} aria-label="Înapoi 15 secunde">
                <IcInapoi15 marime={30} />
              </button>
              <button type="button" className="mare" onClick={() => void porneste()} aria-label={aceasta && s.canta ? 'Pauză' : 'Ascultă'}>
                {aceasta && s.canta ? <IcPauza marime={30} /> : <IcPlay marime={32} />}
              </button>
              <button type="button" className="icon-buton" onClick={() => sari(15)} disabled={!aceasta} aria-label="Înainte 15 secunde">
                <IcInainte15 marime={30} />
              </button>
            </div>
            {!aceasta && timp > 5 && <p className="ajutor" style={{ textAlign: 'center', marginTop: -10 }}>Continui de la {ceas(timp)}.</p>}
            {(err || (aceasta && s.eroare)) && <Eroare text={err || s.eroare || ''} />}

            <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
              {m.video && (
                <button type="button" className="buton secundar lat" onClick={() => { pauza(); setVideo(true); }}>
                  <IcVideo marime={20} /> Vezi explicația video
                </button>
              )}
              {progres !== undefined ? (
                <button type="button" className="buton secundar lat" disabled>
                  Se descarcă… {Math.round(progres * 100)}%
                </button>
              ) : descarcata ? (
                <button type="button" className="buton secundar lat" onClick={() => setIntreabaSterge(true)}>
                  <IcBifa marime={20} /> Descărcată pe telefon
                </button>
              ) : (
                <button type="button" className="buton secundar lat" onClick={() => void descarcaAcum()}>
                  <IcDescarca marime={20} /> Descarcă pe telefon
                </button>
              )}
            </div>
          </>
        ) : (
          <div className="nota-lacat">
            <IcLacat marime={22} style={{ flex: 'none' }} />
            <p>{TEXT_LACAT}</p>
          </div>
        )}

        {m.text && (
          <div className="text-lung">
            {m.text.split(/\n{2,}/).map((p, i) => (
              <p key={i}>{p}</p>
            ))}
          </div>
        )}
      </div>

      {video && m.video && <StratVideo url={m.video.embed} titlu={m.titlu} inchide={() => setVideo(false)} />}
      {intreabaSterge && (
        <Dialog
          titlu="Ștergi descărcarea?"
          inchide={() => setIntreabaSterge(false)}
          butoane={
            <>
              <button type="button" className="buton lat" onClick={() => { void stergeDescarcare(m.id); setIntreabaSterge(false); }}>
                <IcCos marime={20} /> Șterge de pe telefon
              </button>
              <button type="button" className="buton secundar lat" onClick={() => setIntreabaSterge(false)}>
                Păstreaz-o
              </button>
            </>
          }
        >
          <p>Meditația rămâne în Inner Temple; doar fișierul de pe telefon se șterge. O poți descărca din nou oricând.</p>
        </Dialog>
      )}
    </>
  );
}
