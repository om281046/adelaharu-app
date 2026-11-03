/** Ce notificări de fază se programează (pur, testat). Ora 9:00, ca alarma PT9H din .ics-ul din sn41. */
import { aziLocal, inceputuriFaze, type StareCiclu } from './algoritm';
import { FAZE } from './faze';

export const ID_MIN = 4100;
export const ID_MAX = 4199;
export const ORA = 9;

/** Calculul pur al notificărilor de programat (exportat pentru teste). */
export function notificariDeProgramat(S: StareCiclu, acum: Date) {
  const azi = aziLocal(acum);
  return inceputuriFaze(S, azi, 6)
    .map((x) => {
      const [y, m, d] = x.d.split('-').map(Number);
      return { ...x, la: new Date(y, m - 1, d, ORA, 0, 0) };
    })
    .filter((x) => x.la.getTime() > acum.getTime())
    .slice(0, ID_MAX - ID_MIN + 1)
    .map((x, i) => {
      const f = FAZE[x.k];
      return {
        id: ID_MIN + i,
        title: 'Începe ' + f.nume.toLowerCase() + ' · ' + f.arhetip + (x.k === 'menstruala' ? ' (estimare)' : ''),
        body: f.pe_scurt,
        schedule: { at: x.la, allowWhileIdle: true },
        extra: { ruta: '#/ciclu' },
      };
    });
}

