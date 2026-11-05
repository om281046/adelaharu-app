import { beforeEach, describe, expect, it, vi } from 'vitest';

const mem = new Map<string, string>();
vi.mock('@capacitor/preferences', () => ({
  Preferences: {
    get: async ({ key }: { key: string }) => ({ value: mem.has(key) ? mem.get(key)! : null }),
    set: async ({ key, value }: { key: string; value: string }) => void mem.set(key, value),
    remove: async ({ key }: { key: string }) => void mem.delete(key),
    keys: async () => ({ keys: [...mem.keys()] }),
  },
}));
vi.mock('./notificari', () => ({ anuleazaToate: vi.fn(async () => {}), programeazaFaze: vi.fn(async () => 0) }));
const api = { stergeCiclu: vi.fn(), salveazaCiclu: vi.fn() };
vi.mock('../api', () => ({ api }));

const L = await import('./ciclu-local');
const { CHEI, citeste, scrie, stergePrefix } = await import('./stocare');
const S = { s: '2026-10-20', c: 28, m: 5, starturi: ['2026-10-20'] };
const retea = () => Promise.reject(new Error('retea'));

beforeEach(() => {
  mem.clear();
  api.stergeCiclu.mockReset().mockResolvedValue({ ok: true });
  api.salveazaCiclu.mockReset().mockResolvedValue({ ok: true });
});

describe('Șterge datele mele', () => {
  it('în cont trimite mereu DELETE /ciclu, chiar fără acord pe telefon', async () => {
    await scrie(CHEI.ciclu, S);
    expect(await L.stergeDateleCiclului(true)).toBe('sters');
    expect(api.stergeCiclu).toHaveBeenCalledTimes(1);
    expect(await L.citesteCiclu()).toBeNull();
  });
  it('dacă DELETE nu merge, ține minte și reîncearcă mai târziu', async () => {
    api.stergeCiclu.mockImplementationOnce(retea);
    expect(await L.stergeDateleCiclului(true)).toBe('site_in_asteptare');
    expect(await citeste(CHEI.cicluDeSters, false)).toBe(true);
    await L.reincearcaCuSite(true);
    expect(api.stergeCiclu).toHaveBeenCalledTimes(2);
    expect(await citeste(CHEI.cicluDeSters, false)).toBe(false);
  });
  it('fără cont nu trimite nimic și spune asta', async () => {
    expect(await L.stergeDateleCiclului(false)).toBe('fara_cont');
    expect(api.stergeCiclu).not.toHaveBeenCalled();
  });
  it('cu ștergere în așteptare, acordul nu se repornește din /eu', async () => {
    await scrie(CHEI.cicluDeSters, true);
    await L.potrivesteAcordCuSite({ salvat_pe_site: true, emailuri_faze: true });
    expect((await L.citesteAcord()).sanatate).toBe(false);
  });
});

describe('sincronizarea', () => {
  it('fără acord nu trimite nimic la site', async () => {
    await L.salveazaCiclu(S, true);
    expect(api.salveazaCiclu).not.toHaveBeenCalled();
  });
  it('dacă PUT nu merge, ține minte și reîncearcă', async () => {
    await L.scrieAcord({ sanatate: true, emailuri: false, sincronizatLa: null });
    api.salveazaCiclu.mockImplementationOnce(retea);
    expect((await L.salveazaCiclu(S, true)).sincronizat).toBe(false);
    expect(await citeste(CHEI.cicluDeSincronizat, false)).toBe(true);
    await L.reincearcaCuSite(true);
    expect(api.salveazaCiclu).toHaveBeenCalledTimes(2);
    expect(await citeste(CHEI.cicluDeSincronizat, false)).toBe(false);
  });
});

describe('acordul ține de cont', () => {
  it('uitaAcordul șterge acordul și ce era în așteptare, dar nu și ciclul de pe telefon', async () => {
    await scrie(CHEI.ciclu, S);
    await L.scrieAcord({ sanatate: true, emailuri: true, sincronizatLa: 1 });
    await scrie(CHEI.cicluDeSincronizat, true);
    await L.uitaAcordul();
    expect(await L.citesteAcord()).toEqual(L.ACORD_IMPLICIT);
    expect(await citeste(CHEI.cicluDeSincronizat, false)).toBe(false);
    expect(await L.citesteCiclu()).toEqual(S);
    await L.salveazaCiclu(S, true);
    expect(api.salveazaCiclu).not.toHaveBeenCalled();
  });
  it('se aliniază cu /eu.ciclu', async () => {
    await L.potrivesteAcordCuSite({ salvat_pe_site: true, emailuri_faze: true });
    expect(await L.citesteAcord()).toMatchObject({ sanatate: true, emailuri: true });
    await L.potrivesteAcordCuSite({ salvat_pe_site: false, emailuri_faze: false });
    expect((await L.citesteAcord()).sanatate).toBe(false);
  });
  it('stergePrefix șterge toate cache-urile cursurilor', async () => {
    await scrie(CHEI.cacheCursPrefix + 'a', 1);
    await scrie(CHEI.cacheCursPrefix + 'b', 2);
    await scrie(CHEI.cacheCursuri, 3);
    await stergePrefix(CHEI.cacheCursPrefix);
    expect([...mem.keys()]).toEqual([CHEI.cacheCursuri]);
  });
});
