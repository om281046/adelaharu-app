import { describe, expect, it } from 'vitest';
import { azi0, citesteJson, data, faze, lung, normalizeaza, situatie, zi0, ziua } from './ciclu';

const Z = zi0;

describe('faze (regula din sn235)', () => {
  it('ciclul obișnuit 28/5', () => {
    expect(faze(28, 5)).toEqual([
      { f: 'iarna', a: 1, b: 5 },
      { f: 'primavara', a: 6, b: 11 },
      { f: 'vara', a: 12, b: 16 },
      { f: 'toamna', a: 17, b: 28 },
    ]);
  });
  it('ciclu scurt cu menstruație lungă: ovulația începe la m+2', () => {
    expect(faze(21, 9)).toEqual([
      { f: 'iarna', a: 1, b: 9 },
      { f: 'primavara', a: 10, b: 10 },
      { f: 'vara', a: 11, b: 15 },
      { f: 'toamna', a: 16, b: 21 },
    ]);
  });
  it('ciclu lung 40/3', () => {
    expect(faze(40, 3)[2]).toEqual({ f: 'vara', a: 24, b: 28 });
  });
});

describe('lung', () => {
  it('media rotunjită a ultimelor cicluri de 21–40 zile, dacă sunt cel puțin 2', () => {
    expect(lung({ starturi: ['2026-11-01', '2026-10-02', '2026-09-03'], lung: 28 })).toBe(30);
    expect(lung({ starturi: ['2026-11-01', '2026-10-02'], lung: 26 })).toBe(26);
    // 50 de zile nu intră la socoteală
    expect(lung({ starturi: ['2026-11-01', '2026-09-12', '2026-08-15', '2026-07-20'], lung: 28 })).toBe(27);
  });
});

describe('normalizeaza', () => {
  it('ordonează, scoate dublurile și datele greșite, păstrează cele mai noi 13', () => {
    const st = Array.from({ length: 15 }, (_, i) => `2026-${String(i % 12 + 1).padStart(2, '0')}-0${i < 12 ? 1 : 2}`);
    const n = normalizeaza({ starturi: [...st, st[0], 'ieri'], lung: 50, men: 1 });
    expect(n?.starturi.length).toBe(13);
    expect(n?.starturi[0]).toBe('2026-12-01');
    expect(n?.lung).toBe(28);
    expect(n?.men).toBe(5);
  });
});

describe('ziua', () => {
  const x = { starturi: ['2026-11-01'], lung: 28, men: 5 };
  it('ziua 1 = iarna', () => {
    expect(ziua(x, Z('2026-11-01'))).toMatchObject({ zi: 1, f: 'iarna', L: 28, peste: false });
  });
  it('ziua 12 = vara, următoarea menstruație pe 29 noiembrie', () => {
    const z = ziua(x, Z('2026-11-12'))!;
    expect(z.f).toBe('vara');
    expect(data(z.urm)).toBe('29 noiembrie');
  });
  it('după ziua L: întârziere, rămâne în toamnă (fără proiecție înainte, ca pe site)', () => {
    expect(ziua(x, Z('2026-12-05'))).toMatchObject({ zi: 35, f: 'toamna', peste: true });
  });
  it('înainte de start: null', () => {
    expect(ziua(x, Z('2026-10-31'))).toBeNull();
  });
});

describe('diverse', () => {
  it('situatie cunoaște doar valorile de pe site', () => {
    expect(situatie({ sit: 'hormonal' })).toBe('hormonal');
    expect(situatie({ sit: 'toString' })).toBe('natural');
    expect(situatie(null)).toBe('natural');
  });
  it('azi0 folosește ziua locală', () => {
    expect(azi0(new Date(2026, 10, 18, 23, 59))).toBe(Z('2026-11-18'));
    expect(azi0(new Date(2026, 10, 18, 0, 1))).toBe(Z('2026-11-18'));
  });
  it('citesteJson nu aruncă', () => {
    expect(citesteJson('{"a":1}')).toEqual({ a: 1 });
    expect(citesteJson('{')).toBeNull();
    expect(citesteJson(null)).toBeNull();
  });
});
