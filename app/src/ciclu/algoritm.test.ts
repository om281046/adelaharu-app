import { describe, expect, it } from 'vitest';
import {
  calculeaza, dif, fazaZilei, frumos, inceputuriFaze, lim, luna, medie, plus,
  startNou, unde, urmatoareleFaze, valideazaStart, type StareCiclu,
} from './algoritm';

const S0: StareCiclu = { s: '2026-10-20', c: 28, m: 5, starturi: ['2026-10-20'] };

describe('date', () => {
  it('plus și dif lucrează pe zile întregi, fără efectul orei de vară', () => {
    expect(plus('2026-10-24', 2)).toBe('2026-10-26');
    expect(dif('2026-10-24', '2026-10-26')).toBe(2);
    expect(dif('2026-03-28', '2026-03-30')).toBe(2);
    expect(plus('2028-02-28', 1)).toBe('2028-02-29');
    expect(plus('2026-12-31', 1)).toBe('2027-01-01');
    expect(dif('2026-11-03', '2026-10-20')).toBe(-14);
  });
  it('frumos scrie data în română', () => {
    expect(frumos('2026-11-03')).toBe('3 noiembrie');
    expect(frumos('2026-11-03', true)).toBe('marți, 3 noiembrie');
  });
});

describe('lim (limitele fazelor)', () => {
  it('ciclul obișnuit 28/5', () => {
    expect(lim(28, 5)).toEqual({ menstruala: [1, 5], foliculara: [6, 11], ovulatorie: [12, 16], premenstruala: [17, 28] });
  });
  it('ciclu scurt cu sângerare lungă: foliculara are o singură zi', () => {
    expect(lim(21, 10)).toEqual({ menstruala: [1, 10], foliculara: [11, 11], ovulatorie: [12, 16], premenstruala: [17, 21] });
  });
  it('ciclu scurt, sângerare scurtă', () => {
    expect(lim(21, 2)).toEqual({ menstruala: [1, 2], foliculara: [3, 4], ovulatorie: [5, 9], premenstruala: [10, 21] });
  });
  it('ciclu lung', () => {
    expect(lim(45, 2)).toEqual({ menstruala: [1, 2], foliculara: [3, 28], ovulatorie: [29, 33], premenstruala: [34, 45] });
  });
  it('limitează c la 21–45 și m la 2–10, cu trunchiere (|0)', () => {
    expect(lim(100, 0)).toEqual(lim(45, 2));
    expect(lim(10, 20)).toEqual(lim(21, 10));
    expect(lim(28.7, 5.9)).toEqual(lim(28, 5));
  });
  it('fazele acoperă fiecare zi a ciclului, fără goluri, pentru toate combinațiile', () => {
    for (let c = 21; c <= 45; c++) {
      for (let m = 2; m <= 10; m++) {
        const l = lim(c, m);
        expect(l.menstruala[0]).toBe(1);
        expect(l.foliculara[0]).toBe(l.menstruala[1] + 1);
        expect(l.ovulatorie[0]).toBe(l.foliculara[1] + 1);
        expect(l.premenstruala[0]).toBe(l.ovulatorie[1] + 1);
        expect(l.premenstruala[1]).toBe(c);
        expect(l.foliculara[1]).toBeGreaterThanOrEqual(l.foliculara[0]);
        expect(l.ovulatorie[1] - l.ovulatorie[0]).toBe(4);
      }
    }
  });
});

describe('fazaZilei', () => {
  it('găsește faza după zi', () => {
    expect(fazaZilei(1, 28, 5)).toBe('menstruala');
    expect(fazaZilei(5, 28, 5)).toBe('menstruala');
    expect(fazaZilei(6, 28, 5)).toBe('foliculara');
    expect(fazaZilei(12, 28, 5)).toBe('ovulatorie');
    expect(fazaZilei(16, 28, 5)).toBe('ovulatorie');
    expect(fazaZilei(17, 28, 5)).toBe('premenstruala');
    expect(fazaZilei(28, 28, 5)).toBe('premenstruala');
  });
  it('în afara ciclului întoarce premenstruala (ca în sn41)', () => {
    expect(fazaZilei(0, 28, 5)).toBe('premenstruala');
    expect(fazaZilei(29, 28, 5)).toBe('premenstruala');
  });
});

describe('unde (ziua de azi în ciclu)', () => {
  it('ziua 15, faza ovulatorie', () => {
    expect(unde('2026-11-03', S0)).toEqual({ zi: 15, nr: 0, faza: 'ovulatorie', inceput: '2026-10-20' });
  });
  it('prima zi a startului', () => {
    expect(unde('2026-10-20', S0)).toEqual({ zi: 1, nr: 0, faza: 'menstruala', inceput: '2026-10-20' });
  });
  it('înaintea startului: null', () => {
    expect(unde('2026-10-19', S0)).toBeNull();
  });
  it('proiectează înainte cu lungimea ciclului', () => {
    expect(unde('2026-11-17', S0)).toEqual({ zi: 1, nr: 1, faza: 'menstruala', inceput: '2026-11-17' });
    expect(unde('2026-11-16', S0)).toEqual({ zi: 28, nr: 0, faza: 'premenstruala', inceput: '2026-10-20' });
    expect(unde('2027-01-15', S0)?.nr).toBe(3);
  });
  it('pentru o zi din trecut folosește startul cunoscut de dinaintea ei', () => {
    const S: StareCiclu = { s: '2026-10-20', c: 28, m: 5, starturi: ['2026-10-20', '2026-09-20'] };
    expect(unde('2026-10-05', S)).toEqual({ zi: 16, nr: 0, faza: 'ovulatorie', inceput: '2026-09-20' });
    expect(unde('2026-10-25', S)).toEqual({ zi: 6, nr: 0, faza: 'foliculara', inceput: '2026-10-20' });
  });
  it('fără starturi folosește s', () => {
    expect(unde('2026-11-03', { ...S0, starturi: [] })?.zi).toBe(15);
  });
});

describe('medie', () => {
  it('media ultimelor 3 pauze plauzibile', () => {
    expect(medie(['2026-05-01', '2026-06-10', '2026-07-09', '2026-08-07', '2026-09-08'], 28)).toBe(30); // 40,29,29,32 → ultimele 3
  });
  it('ignoră pauzele sub 21 sau peste 45 de zile', () => {
    expect(medie(['2026-08-01', '2026-08-11', '2026-09-08'], 28)).toBe(28); // 10 și 28
    expect(medie(['2026-01-01', '2026-03-01'], 27)).toBe(27); // 59: implicit
  });
  it('rotunjește ca Math.round / round din PHP (29,5 → 30)', () => {
    expect(medie(['2026-08-01', '2026-08-30', '2026-09-29'], 28)).toBe(30); // 29, 30
  });
  it('sortează singură', () => {
    expect(medie(['2026-09-29', '2026-08-01', '2026-08-30'], 28)).toBe(30);
  });
});

describe('calculeaza (formularul)', () => {
  const azi = '2026-11-03';
  it('validează data', () => {
    expect(valideazaStart('', azi)).toBe('fara_data');
    expect(valideazaStart('2026-11-04', azi)).toBe('in_viitor');
    expect(valideazaStart('2026-07-05', azi)).toBe('prea_veche'); // 121 de zile
    expect(valideazaStart('2026-07-06', azi)).toBeNull(); // 120 de zile
    expect(valideazaStart(azi, azi)).toBeNull();
  });
  it('limitează valorile și pune implicitele', () => {
    const r = calculeaza(null, '2026-10-20', 50, 1, azi);
    expect(r).toEqual({ ok: true, stare: { s: '2026-10-20', c: 45, m: 2, starturi: ['2026-10-20'] } });
    const r2 = calculeaza(null, '2026-10-20', 0, NaN, azi);
    expect(r2.ok && r2.stare.c).toBe(28);
    expect(r2.ok && r2.stare.m).toBe(5);
  });
  it('păstrează starturile mai vechi cu cel puțin 15 zile', () => {
    const ant: StareCiclu = { s: '2026-10-10', c: 28, m: 5, starturi: ['2026-08-01', '2026-09-01', '2026-10-10', '2026-10-25'] };
    const r = calculeaza(ant, '2026-10-20', 28, 5, azi);
    expect(r.ok && r.stare.starturi).toEqual(['2026-08-01', '2026-09-01', '2026-10-20']);
  });
  it('cel mult 6 starturi', () => {
    const ant: StareCiclu = { s: '2026-07-10', c: 28, m: 5, starturi: ['2026-01-01', '2026-02-01', '2026-03-01', '2026-04-01', '2026-05-01', '2026-06-01', '2026-07-01'] };
    const r = calculeaza(ant, '2026-08-01', 28, 5, '2026-08-02');
    expect(r.ok && r.stare.starturi).toEqual(['2026-03-01', '2026-04-01', '2026-05-01', '2026-06-01', '2026-07-01', '2026-08-01']);
  });
  it('întoarce eroarea', () => {
    expect(calculeaza(null, '2026-12-01', 28, 5, azi)).toEqual({ ok: false, eroare: 'in_viitor' });
  });
});

describe('startNou (a venit menstruația)', () => {
  it('adaugă startul și recalculează lungimea', () => {
    const S: StareCiclu = { s: '2026-10-20', c: 28, m: 5, starturi: ['2026-09-22', '2026-10-20'] };
    expect(startNou(S, '2026-11-18', '2026-11-18')).toEqual({ s: '2026-11-18', c: 29, m: 5, starturi: ['2026-09-22', '2026-10-20', '2026-11-18'] });
  });
  it('un start la mai puțin de 15 zile de ultimul îl înlocuiește', () => {
    expect(startNou(S0, '2026-10-23', '2026-11-03')).toEqual({ s: '2026-10-23', c: 28, m: 5, starturi: ['2026-10-23'] });
    expect(startNou(S0, '2026-10-10', '2026-11-03')?.starturi).toEqual(['2026-10-10']);
  });
  it('același start nu se dublează', () => {
    expect(startNou(S0, '2026-10-20', '2026-11-03')?.starturi).toEqual(['2026-10-20']);
  });
  it('refuză data din viitor sau mai veche de 120 de zile', () => {
    expect(startNou(S0, '2026-11-04', '2026-11-03')).toBeNull();
    expect(startNou(S0, '2026-07-01', '2026-11-03')).toBeNull();
    expect(startNou(S0, 'ieri', '2026-11-03')).toBeNull();
  });
  it('păstrează ultimele 6', () => {
    const S: StareCiclu = { s: '2026-06-26', c: 28, m: 5, starturi: ['2026-01-08', '2026-02-05', '2026-03-05', '2026-04-02', '2026-04-30', '2026-05-28', '2026-06-25'] };
    const r = startNou(S, '2026-07-23', '2026-07-23');
    expect(r?.starturi).toEqual(['2026-03-05', '2026-04-02', '2026-04-30', '2026-05-28', '2026-06-25', '2026-07-23']);
    expect(r?.c).toBe(28);
  });
});

describe('urmatoareleFaze', () => {
  it('doar începuturi după azi, sortate', () => {
    expect(urmatoareleFaze(S0, '2026-11-03')).toEqual([
      { k: 'premenstruala', d: '2026-11-05' },
      { k: 'menstruala', d: '2026-11-17' },
      { k: 'foliculara', d: '2026-11-22' },
      { k: 'ovulatorie', d: '2026-11-28' },
    ]);
  });
  it('faza care începe azi trece la ciclul următor', () => {
    const u = urmatoareleFaze(S0, '2026-10-31');
    expect(u.find((x) => x.k === 'ovulatorie')?.d).toBe('2026-11-28');
  });
  it('fără start valid: listă goală', () => {
    expect(urmatoareleFaze(S0, '2026-10-01')).toEqual([]);
  });
});

describe('inceputuriFaze (notificările, ca .ics: 6 cicluri)', () => {
  it('24 de începuturi din ciclul curent', () => {
    const l = inceputuriFaze(S0, '2026-11-03');
    expect(l).toHaveLength(24);
    expect(l[0]).toEqual({ k: 'menstruala', d: '2026-10-20', nr: 0 });
    expect(l[3]).toEqual({ k: 'premenstruala', d: '2026-11-05', nr: 0 });
    expect(l[4]).toEqual({ k: 'menstruala', d: '2026-11-17', nr: 1 });
    expect(l[23]).toEqual({ k: 'premenstruala', d: plus('2026-10-20', 5 * 28 + 16), nr: 5 });
  });
});

describe('luna', () => {
  it('luna nouă de referință și luna plină la ~14,8 zile', () => {
    expect(luna('2000-01-07').nume).toBe('Luna nouă');
    expect(luna('2000-01-21').nume).toBe('Luna plină');
    expect(luna('2000-01-14').nume).toBe('Luna în creștere');
    expect(luna('2000-01-28').nume).toBe('Luna în descreștere');
  });
  it('fracția e în [0,1)', () => {
    for (let i = 0; i < 60; i++) {
      const f = luna(plus('2026-11-01', i)).f;
      expect(f).toBeGreaterThanOrEqual(0);
      expect(f).toBeLessThan(1);
    }
  });
});

import { notificariDeProgramat } from './notificari-faze';
describe('notificările de fază', () => {
  it('doar cele viitoare, la 9:00, cu titlul din .ics', () => {
    const l = notificariDeProgramat(S0, new Date(2026, 10, 3, 12, 0));
    expect(l[0].title).toBe('Începe faza premenstruală · Fermecătoreasa');
    expect(l[0].schedule.at).toEqual(new Date(2026, 10, 5, 9, 0, 0));
    expect(l[1].title).toBe('Începe faza menstruală · Alchimista (estimare)');
    expect(l).toHaveLength(21); // 24 minus cele 3 deja trecute din ciclul curent
    expect(new Set(l.map((x) => x.id)).size).toBe(l.length);
  });
  it('în ziua fazei, înainte de 9:00, o include', () => {
    const l = notificariDeProgramat(S0, new Date(2026, 10, 5, 8, 0));
    expect(l[0].schedule.at).toEqual(new Date(2026, 10, 5, 9, 0, 0));
  });
});
