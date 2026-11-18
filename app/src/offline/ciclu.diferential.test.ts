/**
 * Test diferențial: modulul CI ORIGINAL din sn235 (copiat neschimbat în fixtures/sn235-ci.js de
 * scripts/extrage-ci.mjs) și portarea din ciclu.ts trebuie să dea exact aceleași rezultate.
 */
import { existsSync, readFileSync } from 'node:fs';
import { afterAll, describe, expect, it, vi } from 'vitest';
// @ts-expect-error fișier JS generat, fără tipuri
import { creeazaCI } from './fixtures/sn235-ci.js';
// @ts-expect-error script Node fără tipuri
import { extrage, SURSA, TINTA } from '../../scripts/extrage-ci.mjs';
import * as C from './ciclu';

type Stocare = Record<string, unknown>;
const ci = (S: Stocare) => creeazaCI((k: string) => (k in S ? JSON.parse(JSON.stringify(S[k])) : null));

function rng(seed: number) {
  let x = seed >>> 0;
  return () => {
    x = (x * 1664525 + 1013904223) >>> 0;
    return x / 2 ** 32;
  };
}

afterAll(() => {
  vi.useRealTimers();
});

describe('fixtures/sn235-ci.js', () => {
  it.skipIf(!existsSync(SURSA))('e la zi cu reference/v2/sn235.php', () => {
    expect(readFileSync(TINTA, 'utf8')).toBe(extrage(readFileSync(SURSA, 'utf8')));
  });
});

describe('identic cu CI din sn235', () => {
  it('faze pentru L 15–50 și m 0–12', () => {
    const O = ci({});
    for (let L = 15; L <= 50; L++) for (let m = 0; m <= 12; m++) expect(C.faze(L, m)).toEqual(O.faze(L, m));
  });

  it('textele fazelor și ale situațiilor', () => {
    const O = ci({});
    expect(C.F).toEqual(O.F);
    expect(C.SIT).toEqual(O.SIT);
    expect(Object.keys(C.F)).toEqual(O.ORD);
  });

  it('zi0, iso și data pe 3 ani', () => {
    const O = ci({});
    for (let z = C.zi0('2025-01-01'); z < C.zi0('2028-01-01'); z++) {
      expect(C.iso(z)).toBe(O.iso(z));
      expect(C.data(z)).toBe(O.data(z));
      expect(C.zi0(C.iso(z))).toBe(O.zi0(O.iso(z)));
    }
  });

  it('normalizeaza, lung, situatie și ziua pe 4000 de stări și date aleatoare', () => {
    const r = rng(20261118);
    const int = (a: number, b: number) => a + Math.floor(r() * (b - a + 1));
    const alege = <T,>(v: T[]) => v[int(0, v.length - 1)];
    vi.useFakeTimers();
    for (let i = 0; i < 4000; i++) {
      const baza = C.zi0('2025-01-01') + int(0, 600);
      const n = int(0, 16);
      const starturi: unknown[] = [];
      let d = baza;
      for (let k = 0; k < n; k++) {
        d += alege([int(18, 45), int(21, 40), int(25, 32), int(1, 90)]);
        starturi.push(C.iso(d));
      }
      // ordine amestecată, dubluri și valori greșite, ca în datele reale vechi
      if (r() < 0.2 && starturi.length) starturi.push(starturi[0]);
      if (r() < 0.1) starturi.push('2026-13-45', 'ieri', 20261101, null);
      if (r() < 0.5) starturi.sort(() => r() - 0.5);
      const x: Record<string, unknown> = { starturi };
      const lungi = [undefined, 0, 21, 28, 35, 40, 41, 20, '30', '29.5', 27.6, 'x', null];
      const meni = [undefined, 0, 2, 5, 9, 10, 1, '7', 3.4, 'y', null];
      const l = alege(lungi);
      const m = alege(meni);
      if (l !== undefined) x.lung = l;
      if (m !== undefined) x.men = m;
      const sit = alege([undefined, 'natural', 'hormonal', 'sarcina', 'alaptare', 'perimenopauza', 'altceva', '']);
      if (sit !== undefined) x.sit = sit;

      const O = ci({ ahsp_ciclu: x });
      const N = C.normalizeaza(x);
      expect(N).toEqual(O.d());
      expect(C.situatie(x)).toBe(O.sit());
      if (N) expect(C.lung(N)).toBe(O.lung(O.d()));

      for (let j = 0; j < 4; j++) {
        const azi = baza + int(-10, n * 40 + 60);
        const [Y, M, D] = C.iso(azi).split('-').map(Number);
        // ora locală oarecare din ziua respectivă
        vi.setSystemTime(new Date(Y, M - 1, D, int(0, 23), int(0, 59)));
        expect(C.azi0()).toBe(O.azi0());
        expect(C.azi0()).toBe(azi);
        expect(C.ziua(x, azi)).toEqual(O.azi());
      }
    }
    vi.useRealTimers();
  });

  it('fără date sau cu date stricate: null, ca pe site', () => {
    for (const x of [null, undefined, {}, { starturi: [] }, { starturi: 'abc' }, { starturi: ['2026/11/01'] }, 'text', 5]) {
      const O = ci(x === undefined ? {} : { ahsp_ciclu: x });
      expect(C.normalizeaza(x)).toEqual(O.d());
      expect(C.ziua(x, C.zi0('2026-11-18'))).toBeNull();
    }
  });
});
