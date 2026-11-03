/** Roata ciclului, ca roata(u) din sn41: un segment pe zi, colorat după fază; ziua de azi iese în față. */
import { fazaZilei, frumos, lim, ORD, plus, type StareCiclu, type Unde } from '../ciclu/algoritm';
import { FAZE } from '../ciclu/faze';

function arc(r1: number, r2: number, a1: number, a2: number) {
  const pt = (r: number, a: number) => (r * Math.cos(a)).toFixed(2) + ',' + (r * Math.sin(a)).toFixed(2);
  const mare = a2 - a1 > Math.PI ? 1 : 0;
  return 'M' + pt(r2, a1) + ' A' + r2 + ',' + r2 + ' 0 ' + mare + ' 1 ' + pt(r2, a2) + ' L' + pt(r1, a2) + ' A' + r1 + ',' + r1 + ' 0 ' + mare + ' 0 ' + pt(r1, a1) + 'Z';
}

export function Roata({ S, u, azi }: { S: StareCiclu; u: Unde; azi: string }) {
  const c = S.c;
  const l = lim(S.c, S.m);
  const pas = (2 * Math.PI) / c;
  const g = 0.012;
  return (
    <div className="roata">
      <svg viewBox="-172 -172 344 344" role="img" aria-label={`Roata ciclului tău, ${c} de zile. Azi e ziua ${u.zi}, ${FAZE[u.faza].nume.toLowerCase()}.`}>
        {Array.from({ length: c }, (_, i) => {
          const z = i + 1;
          const fz = fazaZilei(z, c, S.m);
          const data = plus(u.inceput, z - 1);
          const a1 = -Math.PI / 2 + (z - 1) * pas + g;
          const a2 = -Math.PI / 2 + z * pas - g;
          const eAzi = z === u.zi;
          const trecut = data < azi;
          const op = eAzi ? 1 : trecut ? 0.78 : 0.42;
          return (
            <path key={z} d={arc(eAzi ? 98 : 104, eAzi ? 150 : 140, a1, a2)} fill={FAZE[fz].culoare} opacity={op}>
              <title>{frumos(data, true) + ', ziua ' + z + ', ' + FAZE[fz].nume}</title>
            </path>
          );
        })}
        {ORD.map((k) => {
          const z = l[k][0];
          const a = -Math.PI / 2 + (z - 1 + 0.5) * pas;
          return (
            <text key={k} x={(161 * Math.cos(a)).toFixed(1)} y={(161 * Math.sin(a) + 4).toFixed(1)} textAnchor="middle" fontFamily="Figtree, sans-serif" fontSize="12.5" fontWeight="600" fill="currentColor" opacity=".8">
              {z}
            </text>
          );
        })}
        <text y="-14" textAnchor="middle" fontFamily="Figtree, sans-serif" fontSize="12" fontWeight="600" letterSpacing="1.6" fill="var(--accent)">
          ZIUA
        </text>
        <text y="30" textAnchor="middle" fontFamily="Cormorant Garamond, Georgia, serif" fontSize="58" fill="var(--titlu)">
          {u.zi}
        </text>
        <text y="54" textAnchor="middle" fontFamily="Figtree, sans-serif" fontSize="13" fill="currentColor">
          din {c}
        </text>
      </svg>
    </div>
  );
}

/** Luna pe cer (lunaSVG din sn41). */
export function LunaMica({ f, r = 13 }: { f: number; r?: number }) {
  const k = Math.cos(2 * Math.PI * f);
  const rx = Math.abs(k) * r;
  const cresc = f < 0.5;
  const s1 = cresc ? 1 : 0;
  const s2 = k > 0 ? (cresc ? 0 : 1) : cresc ? 1 : 0;
  return (
    <svg width={2 * r + 2} height={2 * r + 2} viewBox={`${-r - 1} ${-r - 1} ${2 * r + 2} ${2 * r + 2}`} aria-hidden="true" style={{ flex: 'none' }}>
      <circle r={r} fill="#EFE6DD" stroke="#A08648" strokeWidth=".8" />
      <path d={`M0,${-r} A${r},${r} 0 0 ${s1} 0,${r} A${rx.toFixed(2)},${r} 0 0 ${s2} 0,${-r}Z`} fill="#C29A4A" />
    </svg>
  );
}
