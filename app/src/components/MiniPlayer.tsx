import { useEffect, useState } from 'react';
import { asculta, comuta, opreste, type StarePlayer } from '../lib/player';
import { mergi } from '../lib/ruta';
import { ceas } from '../lib/format';
import { Coperta } from './Comune';
import { IcInchide, IcPauza, IcPlay } from './Icoane';

export function useStarePlayer(): StarePlayer {
  const [s, setS] = useState<StarePlayer>({ curent: null, canta: false, timp: 0, durata: 0, seIncarca: false, eroare: null });
  useEffect(() => {
    const dezab = asculta(setS);
    return () => {
      dezab();
    };
  }, []);
  return s;
}

export function MiniPlayer() {
  const s = useStarePlayer();
  const e = s.curent;
  if (!e) return null;
  const p = s.durata ? Math.min(100, (s.timp / s.durata) * 100) : 0;
  return (
    <div className="mini" role="region" aria-label="Se ascultă acum">
      <Coperta poza={e.poza} cheie={e.subtitlu || e.titlu} marimeIcoana={20} />
      <button type="button" className="t" onClick={() => mergi(e.ruta.replace(/^#\//, ''))}>
        <b>{e.titlu}</b>
        <small>
          {ceas(s.timp)}
          {s.durata ? ' / ' + ceas(s.durata) : ''}
        </small>
      </button>
      <button type="button" className="icon-buton" onClick={comuta} aria-label={s.canta ? 'Pauză' : 'Pornește'}>
        {s.canta ? <IcPauza /> : <IcPlay />}
      </button>
      {!s.canta && (
        <button type="button" className="icon-buton" onClick={opreste} aria-label="Închide playerul">
          <IcInchide marime={20} />
        </button>
      )}
      <div className="linie" aria-hidden="true">
        <span style={{ width: p + '%' }} />
      </div>
    </div>
  );
}
