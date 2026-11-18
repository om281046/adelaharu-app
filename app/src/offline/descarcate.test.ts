import { describe, expect, it } from 'vitest';
import { caleSigura, ceas, citesteDescarcate, durataScurta, secunde } from './descarcate';

describe('ah_descarcate', () => {
  it('păstrează rândurile bune, cele mai noi întâi, fără dubluri', () => {
    const l = citesteDescarcate([
      { id: 1, titlu: 'A', durata: 600, fisier: 'meditatii/1.mp3', poza: 'meditatii/1.jpg', descarcat: '2026-11-01T00:00:00Z' },
      { id: '2', titlu: 'B', durata: '12:30', fisier: 'meditatii/2.m4a', descarcat: 1794000000000 },
      { id: '2', titlu: 'dublură', fisier: 'meditatii/2.m4a' },
      { id: '3', titlu: 'rău', fisier: '../../etc/passwd' },
      { id: '4', titlu: 'schemă', fisier: 'javascript:alert(1)' },
      { titlu: 'fără id', fisier: 'x.mp3' },
      null,
      'text',
    ]);
    expect(l.map((x) => x.id)).toEqual(['2', '1']);
    expect(l[0]).toMatchObject({ durata: 750, poza: '', dir: 'DATA' });
    expect(l[1]).toMatchObject({ id: '1', durata: 600, poza: 'meditatii/1.jpg' });
  });
  it('nu aruncă pe date stricate', () => {
    expect(citesteDescarcate(null)).toEqual([]);
    expect(citesteDescarcate({})).toEqual([]);
  });
  it('căi', () => {
    expect(caleSigura('meditatii/1.mp3')).toBe(true);
    expect(caleSigura('file:///var/x/Library/meditatii/1.mp3')).toBe(true);
    expect(caleSigura('https://x.ro/a.mp3')).toBe(false);
    expect(caleSigura('a/../b')).toBe(false);
  });
  it('durate', () => {
    expect(secunde('1:02:03')).toBe(3723);
    expect(secunde('12 min')).toBe(720);
    expect(secunde('abc')).toBe(0);
    expect(durataScurta(754)).toBe('13 min');
    expect(durataScurta(3900)).toBe('1 h 5 min');
    expect(ceas(754.3)).toBe('12:34');
    expect(ceas(3723)).toBe('1:02:03');
  });
});
