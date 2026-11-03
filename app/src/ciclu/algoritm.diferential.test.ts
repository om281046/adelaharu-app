/**
 * Test diferențial: rulează codul ORIGINAL din sn41 (copiat verbatim în fixtures/)
 * și portarea TS pe mii de intrări pseudo-aleatoare; rezultatele trebuie să fie identice.
 */
import { describe, expect, it } from 'vitest';
import { creeaza } from './fixtures/sn41-original.js';
import * as A from './algoritm';

function rng(seed: number) {
  let x = seed >>> 0;
  return () => {
    x = (x * 1664525 + 1013904223) >>> 0;
    return x / 2 ** 32;
  };
}

describe('identic cu sn41 (cod original)', () => {
  const r = rng(20261103);
  const int = (a: number, b: number) => a + Math.floor(r() * (b - a + 1));

  it('lim și fazaZilei pentru toate c 0–60, m 0–15', () => {
    const O = creeaza({});
    for (let c = 0; c <= 60; c++) {
      for (let m = 0; m <= 15; m++) {
        expect(A.lim(c, m)).toEqual(O.lim(c, m));
        for (let zi = 0; zi <= 47; zi++) expect(A.fazaZilei(zi, c, m)).toBe(O.fazaZilei(zi, c, m));
      }
    }
  });

  it('unde, medie, startNou, luna pe 3000 de stări aleatoare', () => {
    for (let i = 0; i < 3000; i++) {
      const baza = A.plus('2025-01-01', int(0, 700));
      const n = int(0, 6);
      const starturi: string[] = [];
      let d = baza;
      for (let k = 0; k < n; k++) {
        d = A.plus(d, int(10, 50));
        starturi.push(d);
      }
      const s = starturi.length ? starturi[starturi.length - 1] : baza;
      const S: A.StareCiclu = { s, c: int(21, 45), m: int(2, 10), starturi: starturi.slice().reverse() };
      const O = creeaza(JSON.parse(JSON.stringify(S)));
      for (let j = 0; j < 5; j++) {
        const zi = A.plus(baza, int(-20, 500));
        expect(A.unde(zi, S)).toEqual(O.unde(zi));
        expect(A.luna(zi)).toEqual(O.luna(zi));
      }
      expect(A.medie(starturi, S.c)).toBe(O.medie(starturi, S.c));
      const nou = A.plus(s, int(-20, 60));
      const azi = A.plus(nou, int(0, 10));
      const t = A.startNou(S, nou, azi);
      O.startNou(nou);
      expect(t).not.toBeNull();
      expect(t).toEqual({ s: O.S.s, c: O.S.c, m: O.S.m, starturi: O.S.starturi });
    }
  });
});
