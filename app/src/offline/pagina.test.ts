import { describe, expect, it } from 'vitest';
import { zi0 } from './ciclu';
import { htmlCiclu, htmlMeditatii, peste } from './main';

const azi = zi0('2026-11-18');

describe('blocul Ciclul meu din pagina offline', () => {
  it('fără date: îndemn, fără calcul', () => {
    expect(htmlCiclu(null, azi)).toContain('Când notezi în Ciclul meu');
  });
  it('ciclu natural: anotimpul și următoarea menstruație', () => {
    const h = htmlCiclu({ starturi: ['2026-11-07'], lung: 28, men: 5 }, azi);
    expect(h).toContain('Ziua 12');
    expect(h).toContain('Vara interioară');
    expect(h).toContain('în jur de 5 decembrie (peste 17 zile)');
  });
  it('„peste N zile” cu „de” de la 20 în sus', () => {
    expect(peste(2)).toBe('peste 2 zile');
    expect(peste(19)).toBe('peste 19 zile');
    expect(peste(20)).toBe('peste 20 de zile');
    expect(peste(25)).toBe('peste 25 de zile');
    const h = htmlCiclu({ starturi: ['2026-11-17'], lung: 35, men: 5 }, azi);
    expect(h).toContain('(peste 34 de zile)');
  });
  it('întârziere', () => {
    expect(htmlCiclu({ starturi: ['2026-10-01'], lung: 28, men: 5 }, azi)).toContain('Menstruația ar fi trebuit să înceapă');
  });
  it('contracepție hormonală și sarcină: textele de pe site, fără anotimpuri', () => {
    const h = htmlCiclu({ starturi: ['2026-11-07'], sit: 'hormonal' }, azi);
    expect(h).toContain('Contracepție hormonală');
    expect(h).not.toContain('interioară');
    expect(htmlCiclu({ starturi: ['2026-11-07'], sit: 'sarcina' }, azi)).toContain('Ciclul tău e în pauză');
  });
  it('alăptare: nota de prudență', () => {
    expect(htmlCiclu({ starturi: ['2026-11-07'], sit: 'alaptare' }, azi)).toContain('estimările sunt mai puțin sigure');
  });
  it('titlurile meditațiilor sunt escapate', () => {
    const h = htmlMeditatii([
      { d: { id: '1', titlu: '<img src=x onerror=alert(1)>', durata: 60, fisier: 'a.mp3', poza: '', dir: 'LIBRARY', descarcat: 0 }, src: 'x', poza: null, pozaNativ: null, lipseste: false },
    ]);
    expect(h).not.toContain('<img src=x');
    expect(h).toContain('&lt;img');
  });
});
