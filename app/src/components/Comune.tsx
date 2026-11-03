import { useEffect, useRef, useState, type ReactNode } from 'react';
import { Browser } from '@capacitor/browser';
import { IcInchide, IcLotus, IcSageataSt } from './Icoane';
import { inapoi } from '../lib/ruta';
import { useSesiune } from '../lib/sesiune';

const NUANTE = [
  ['#C98B6B', '#8C4A3A'],
  ['#D9A97E', '#A8573A'],
  ['#B8A27A', '#7A6A48'],
  ['#C7957F', '#7A4A38'],
  ['#A9B28C', '#6F7A50'],
  ['#D2A091', '#96593F'],
];
function nuanta(cheie: string) {
  let h = 0;
  for (const ch of cheie) h = (h * 31 + ch.charCodeAt(0)) >>> 0;
  return NUANTE[h % NUANTE.length];
}

/** Coperta: poza, dacă există; altfel un gradient cald cu lotus. */
export function Coperta({ poza, cheie, clasa = '', marimeIcoana = 26 }: { poza?: string; cheie: string; clasa?: string; marimeIcoana?: number }) {
  const [a, b] = nuanta(cheie);
  const [rupta, setRupta] = useState(false);
  return (
    <div className={'coperta ' + clasa} style={{ background: `radial-gradient(120% 120% at 20% 10%, ${a}, ${b})` }}>
      {poza && !rupta ? <img src={poza} alt="" loading="lazy" onError={() => setRupta(true)} /> : <IcLotus marime={marimeIcoana} />}
    </div>
  );
}

export function BaraSus({ implicit, titlu, dreapta }: { implicit: string; titlu?: string; dreapta?: ReactNode }) {
  return (
    <div className="bara-sus">
      <button type="button" className="icon-buton" onClick={() => inapoi(implicit)} aria-label="Înapoi">
        <IcSageataSt />
      </button>
      {titlu && <span className="titlu-mic">{titlu}</span>}
      <span style={{ flex: 1 }} />
      {dreapta}
    </div>
  );
}

export function Incarcare({ text = 'Se încarcă…' }: { text?: string }) {
  return (
    <div className="incarcare" role="status">
      <span />
      <b className="ascuns-vizual">{text}</b>
    </div>
  );
}

export function Eroare({ text, reincearca }: { text: string; reincearca?: () => void }) {
  return (
    <div className="eroare" role="alert">
      {text}
      {reincearca && (
        <div>
          <button type="button" className="legatura" onClick={reincearca}>
            Încearcă din nou
          </button>
        </div>
      )}
    </div>
  );
}

export function Dialog({ titlu, children, butoane, inchide }: { titlu: string; children?: ReactNode; butoane: ReactNode; inchide: () => void }) {
  const ref = useRef<HTMLDivElement>(null);
  useEffect(() => {
    ref.current?.focus();
    const f = (e: KeyboardEvent) => e.key === 'Escape' && inchide();
    window.addEventListener('keydown', f);
    return () => window.removeEventListener('keydown', f);
  }, [inchide]);
  return (
    <div className="dialog-fundal" onClick={(e) => e.target === e.currentTarget && inchide()}>
      <div className="dialog" role="dialog" aria-modal="true" aria-label={titlu} tabIndex={-1} ref={ref}>
        <h2>{titlu}</h2>
        {children}
        <div className="butoane">{butoane}</div>
      </div>
    </div>
  );
}

/** Videoul (pagina semnată /app/player de pe adelaharu.ro) într-un strat peste aplicație. */
export function StratVideo({ url, titlu, inchide }: { url: string; titlu: string; inchide: () => void }) {
  useEffect(() => {
    const f = (e: KeyboardEvent) => e.key === 'Escape' && inchide();
    window.addEventListener('keydown', f);
    return () => window.removeEventListener('keydown', f);
  }, [inchide]);
  return (
    <div className="strat" role="dialog" aria-modal="true" aria-label={titlu}>
      <div className="sus">
        <b>{titlu}</b>
        <button type="button" className="icon-buton" onClick={inchide} aria-label="Închide videoul">
          <IcInchide />
        </button>
      </div>
      <div className="cadru-video">
        <iframe src={url} title={titlu} allow="autoplay; fullscreen; picture-in-picture; encrypted-media" allowFullScreen referrerPolicy="strict-origin-when-cross-origin" />
      </div>
      {/^https:/.test(url) && (
        <button type="button" className="buton discret" onClick={() => void Browser.open({ url, presentationStyle: 'fullscreen' })}>
          Nu pornește? Deschide videoul pe tot ecranul
        </button>
      )}
    </div>
  );
}

/** Pentru tabul care are nevoie de cont. */
export function CereCont({ titlu, text }: { titlu: string; text: string }) {
  const { vreiSaIntri } = useSesiune();
  return (
    <div className="card piersica gol" style={{ color: 'var(--text)' }}>
      <h2>{titlu}</h2>
      <p>{text}</p>
      <button type="button" className="buton" onClick={vreiSaIntri}>
        Intră în cont
      </button>
    </div>
  );
}

export function deschideLink(url: string) {
  if (/^mailto:|^tel:/.test(url)) {
    window.location.href = url;
    return;
  }
  void Browser.open({ url });
}
