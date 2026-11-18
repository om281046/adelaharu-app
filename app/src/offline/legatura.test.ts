import { describe, expect, it } from 'vitest';
import { CONFIG, TEXTE, dinRaspuns, intoarceri, motivInitial, pauza, verifica, versiuneWebView } from './legatura';

describe('pagina offline: fără internet sau site-ul în lucru', () => {
  it('răspunsul /config: ok → ok; orice altceva → site', () => {
    expect(dinRaspuns({ ok: true, status: 200 })).toBe('ok');
    for (const s of [500, 502, 503, 403, 429, 404]) expect(dinRaspuns({ ok: false, status: s })).toBe('site');
    expect(dinRaspuns(null)).toBe('fara_internet');
  });
  it('verifica: cere /config cu CORS, fără cookie, fără cache', async () => {
    const cereri: [string, RequestInit | undefined][] = [];
    const r = await verifica(async (u, o) => (cereri.push([u, o]), { ok: true, status: 200 }), 1000, true);
    expect(r).toBe('ok');
    expect(cereri[0][0].startsWith(CONFIG + '?t=')).toBe(true);
    expect([cereri[0][1]?.mode, cereri[0][1]?.credentials, cereri[0][1]?.cache]).toEqual(['cors', 'omit', 'no-store']);
  });
  it('verifica: 503 → site; navigator.onLine=false → fără internet, fără cerere', async () => {
    expect(await verifica(async () => ({ ok: false, status: 503 }), 1000, true)).toBe('site');
    let n = 0;
    expect(await verifica(async () => (n++, { ok: true, status: 200 }), 1000, false)).toBe('fara_internet');
    expect(n).toBe(0);
  });
  it('verifica: rețeaua cade → fără internet; doar CORS cade (no-cors merge) → ok', async () => {
    expect(await verifica(async () => { throw new TypeError('Failed to fetch'); }, 1000, true)).toBe('fara_internet');
    expect(await verifica(async (_u, o) => { if (o?.mode === 'cors') throw new TypeError('CORS'); return { ok: false, status: 0 }; }, 1000, true)).toBe('ok');
  });
  it('verifica: un site care nu răspunde deloc (timp depășit) → fără internet', async () => {
    const r = await verifica((_u, o) => new Promise((_r, j) => o?.signal?.addEventListener('abort', () => j(new Error('abort')))), 20, true);
    expect(r).toBe('fara_internet');
  });
  it('fără bucle: pauza crește cu întoarcerile rapide', () => {
    expect([0, 1, 2, 3, 4, 5, 9].map(pauza)).toEqual([5, 15, 30, 60, 120, 300, 300]);
    const acum = 1_000_000;
    expect(intoarceri(acum - 4000, 0, acum)).toBe(1);
    expect(intoarceri(acum - 4000, 3, acum)).toBe(4);
    expect(intoarceri(acum - 45000, 3, acum)).toBe(0);
    expect(intoarceri(0, 3, acum)).toBe(0);
  });
  it('motivul inițial: ?cod= (iOS) sau eroarea din AhApp (Android, cel mult de acum un minut)', () => {
    const acum = 5_000_000;
    expect(motivInitial('?cod=503', null, acum)).toBe('site');
    expect(motivInitial('', null, acum)).toBe('fara_internet');
    expect(motivInitial('', { cod: 502, la: acum - 2000 }, acum)).toBe('site');
    expect(motivInitial('', { cod: 502, la: acum - 120000 }, acum)).toBe('fara_internet');
    expect(motivInitial('', { cod: 0, la: acum }, acum)).toBe('fara_internet');
  });
  it('WebView-ul Android din User-Agent', () => {
    expect(versiuneWebView('Mozilla/5.0 (Linux; Android 9; wv) AppleWebKit/537.36 Chrome/83.0.4103.106 Mobile Safari/537.36')).toBe(83);
    expect(versiuneWebView('Mozilla/5.0 (iPhone) AppleWebKit/605.1.15')).toBe(0);
  });
  it('textele: site-ul în lucru spune „revino în câteva minute”', () => {
    expect(TEXTE.site.text).toContain('Site-ul e în lucru, revino în câteva minute');
    expect(TEXTE.fara_internet.titlu).toBe('Nu avem internet acum');
  });
});
