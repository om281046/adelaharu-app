import { describe, expect, it, vi } from 'vitest';
vi.mock('@capacitor/app', () => ({ App: {} }));
vi.mock('@capacitor/core', () => ({ Capacitor: { isNativePlatform: () => false } }));
import { analizeaza } from './legaturi';

describe('linkurile universale', () => {
  it('/app/intra completează emailul și codul', () => {
    expect(analizeaza('https://www.adelaharu.ro/app/intra?e=ana%40ex.ro&c=123456')).toEqual({ cod: { email: 'ana@ex.ro', cod: '123456' } });
    expect(analizeaza('https://www.adelaharu.ro/app/intra/?e=ana@ex.ro&c=12 34 56xx')).toEqual({ cod: { email: 'ana@ex.ro', cod: '123456' } });
  });
  it('fără email nu face nimic', () => {
    expect(analizeaza('https://www.adelaharu.ro/app/intra?c=123456')).toEqual({});
  });
  it('căile vechi duc la taburi', () => {
    expect(analizeaza('https://www.adelaharu.ro/meditatiile-mele/')).toEqual({ ruta: 'inner-temple' });
    expect(analizeaza('https://www.adelaharu.ro/sala-de-curs/wild-love/')).toEqual({ ruta: 'cursuri' });
    expect(analizeaza('nu e url')).toEqual({});
  });
});
