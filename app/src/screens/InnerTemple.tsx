import { useEffect, useMemo, useState } from 'react';
import type { Meditatie } from '../api/types';
import { CereCont, Coperta, Eroare, Incarcare } from '../components/Comune';
import { IcBifa, IcLacat, IcVideo } from '../components/Icoane';
import { useMeditatii } from '../lib/date';
import { dataLunga, durata } from '../lib/format';
import { ascultaDescarcari, descarcate } from '../lib/offline';
import { mergi } from '../lib/ruta';
import { useSesiune } from '../lib/sesiune';

/** Textul din contract pentru meditațiile fără acces: FĂRĂ link și fără preț (Apple 3.1.1 / 3.1.3(a)). */
export const TEXT_LACAT = 'Această meditație nu e inclusă în accesul tău.';

export function useDescarcate(): Set<number> {
  const [s, setS] = useState<Set<number>>(new Set());
  useEffect(() => {
    const f = () => void descarcate().then((h) => setS(new Set(Object.values(h).map((d) => d.id))));
    f();
    return ascultaDescarcari(f);
  }, []);
  return s;
}

export function RandMeditatie({ m, descarcata, numeCategorie }: { m: Meditatie; descarcata: boolean; numeCategorie?: string }) {
  return (
    <button type="button" className={'rand' + (m.poate_asculta ? '' : ' blocat')} onClick={() => mergi('inner-temple/m/' + m.id)}>
      <Coperta poza={m.poza} cheie={m.categorie || m.titlu} />
      <span className="corp">
        <b>{m.titlu}</b>
        <span className="meta">
          {[numeCategorie, durata(m.durata)].filter(Boolean).join(' · ')}
          {m.video && <IcVideo marime={16} aria-label="Are video explicativ" />}
          {descarcata && <span className="insigna"><IcBifa marime={13} /> Descărcată</span>}
        </span>
        {!m.poate_asculta && <small>{TEXT_LACAT}</small>}
      </span>
      {m.noua && m.poate_asculta && <span className="insigna noua">Nouă</span>}
      {!m.poate_asculta && <IcLacat marime={20} style={{ color: 'var(--text-2)' }} />}
    </button>
  );
}

export function InnerTemple() {
  const { token, eu } = useSesiune();
  const { date, eroare, seIncarca, dinCache, reincarca } = useMeditatii(!!token);
  const [filtru, setFiltru] = useState<string>('toate');
  const desc = useDescarcate();

  const lista = useMemo(() => {
    const m = date?.meditatii || [];
    if (filtru === 'toate') return m;
    if (filtru === 'descarcate') return m.filter((x) => desc.has(x.id));
    return m.filter((x) => x.categorie === filtru);
  }, [date, filtru, desc]);
  const numeCat = useMemo(() => Object.fromEntries((date?.categorii || []).map((c) => [c.slug, c.nume])), [date]);

  const it = eu?.inner_temple;
  return (
    <>
      <header className="antet">
        <span className="et">Meditații</span>
        <h1>Inner Temple</h1>
        <p className="sub">Meditații ghidate pentru tine, unele cu o explicație video de la Adela.</p>
      </header>

      {!token ? (
        <CereCont titlu="Meditațiile tale" text="Intră în cont cu adresa ta de email ca să-ți asculți meditațiile, și fără internet." />
      ) : (
        <>
          {it?.activ && (
            <div className="card piersica" style={{ padding: 16 }}>
              <p>
                <b style={{ fontWeight: 600 }}>Inner Temple e activ</b>
                {it.pana_la ? <> până la {dataLunga(it.pana_la)}.</> : '.'}
              </p>
            </div>
          )}
          {dinCache && <p className="ajutor">Ești fără internet. Vezi lista salvată; meditațiile descărcate merg oricând.</p>}

          {date && (
            <div className="filtre" role="group" aria-label="Categorii">
              <button type="button" className="filtru" aria-pressed={filtru === 'toate'} onClick={() => setFiltru('toate')}>
                Toate
              </button>
              {date.categorii.map((c) => (
                <button key={c.slug} type="button" className="filtru" aria-pressed={filtru === c.slug} onClick={() => setFiltru(c.slug)}>
                  {c.nume}
                </button>
              ))}
              <button type="button" className="filtru" aria-pressed={filtru === 'descarcate'} onClick={() => setFiltru('descarcate')}>
                Descărcate
              </button>
            </div>
          )}

          {seIncarca && !date && <Incarcare />}
          {eroare && !date && <Eroare text={eroare} reincearca={reincarca} />}
          {date && (
            <div className="lista">
              {lista.map((m) => (
                <RandMeditatie key={m.id} m={m} descarcata={desc.has(m.id)} numeCategorie={filtru === 'toate' ? numeCat[m.categorie] : undefined} />
              ))}
              {!lista.length && (
                <div className="gol">
                  {filtru === 'descarcate' ? 'Nu ai descărcat încă nicio meditație. Deschide una și apasă „Descarcă”, ca s-o asculți și fără internet.' : 'Aici nu e nimic deocamdată.'}
                </div>
              )}
            </div>
          )}
        </>
      )}
    </>
  );
}
