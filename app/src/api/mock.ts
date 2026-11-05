/**
 * Modul demo (VITE_MOCK=1): date de probă, fără server. Codul de intrare e 123456.
 * Nu face nicio cerere spre adelaharu.ro.
 */
import { ApiError, MESAJE_IMPLICITE, type Api } from './client';
import type { CicluServer, Curs, CursScurt, Eu, Meditatie, MeditatiiRaspuns } from './types';

const asteapta = (ms = 250) => new Promise((r) => setTimeout(r, ms));
const ok = { ok: true as const };

/** Un sunet calm de clopoțel, generat local (WAV), ca playerul să aibă ce reda în demo. */
let urlSunet: string | null = null;
function sunetDemo(): string {
  if (urlSunet) return urlSunet;
  const rata = 8000;
  const sec = 40;
  const n = rata * sec;
  const buf = new ArrayBuffer(44 + n);
  const v = new DataView(buf);
  const s = (o: number, t: string) => [...t].forEach((ch, i) => v.setUint8(o + i, ch.charCodeAt(0)));
  s(0, 'RIFF'); v.setUint32(4, 36 + n, true); s(8, 'WAVE'); s(12, 'fmt ');
  v.setUint32(16, 16, true); v.setUint16(20, 1, true); v.setUint16(22, 1, true);
  v.setUint32(24, rata, true); v.setUint32(28, rata, true); v.setUint16(32, 1, true); v.setUint16(34, 8, true);
  s(36, 'data'); v.setUint32(40, n, true);
  for (let i = 0; i < n; i++) {
    const t = i / rata;
    const tc = t % 8; // un clopoțel la 8 secunde
    const a = Math.exp(-tc * 0.9) * 0.35;
    const x = a * (Math.sin(2 * Math.PI * 432 * t) + 0.4 * Math.sin(2 * Math.PI * 864 * t)) / 1.4;
    v.setUint8(44 + i, Math.max(0, Math.min(255, Math.round(128 + x * 127))));
  }
  urlSunet = URL.createObjectURL(new Blob([buf], { type: 'audio/wav' }));
  return urlSunet;
}

function paginaVideo(titlu: string): string {
  const html = `<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width"><body style="margin:0;display:grid;place-items:center;height:100vh;background:#4E3D34;color:#FBE6D9;font:16px/1.5 Figtree,system-ui,sans-serif;text-align:center;padding:24px;box-sizing:border-box"><div><div style="font-size:42px">▶</div><p style="margin:8px 0 0"><b>${titlu}</b></p><p style="opacity:.8;margin:6px 0 0">Demo: aici se încarcă videoul de pe Vimeo, prin pagina adelaharu.ro/app/player.</p></div></body>`;
  return 'data:text/html;charset=utf-8,' + encodeURIComponent(html);
}

const CATEGORII = [
  { slug: 'pantec', nume: 'Pântec' },
  { slug: 'respiratie', nume: 'Respirație' },
  { slug: 'relaxare', nume: 'Relaxare' },
  { slug: 'somn', nume: 'Somn' },
  { slug: 'feminitate', nume: 'Feminitate' },
];

const MEDITATII: Meditatie[] = [
  { id: 330, titlu: 'Curățarea pântecului', text: 'O meditație ghidată pentru zilele tale de răgaz, în care doar asculți și respiri. Lași să plece ce nu-ți mai folosește și faci loc pentru ce vine.', poza: '', durata: 1260, categorie: 'pantec', acces: 'abonament', poate_asculta: true, video: { tip: 'vimeo', embed: paginaVideo('Curățarea pântecului: explicația Adelei') }, pozitie: 14, noua: false },
  { id: 328, titlu: 'Relaxare și reconectare cu energia feminină', text: 'Pentru toamna ta: o meditație de relaxare și reconectare cu energia feminină din corp.', poza: '', durata: 1080, categorie: 'relaxare', acces: 'abonament', poate_asculta: true, video: null, pozitie: 0, noua: false },
  { id: 401, titlu: 'Respirația care te liniștește', text: 'Zece minute de respirație lentă, pentru momentele în care simți că alergi prea repede. O poți face oriunde, cu ochii închiși.', poza: '', durata: 600, categorie: 'respiratie', acces: 'gratuita', poate_asculta: true, video: null, pozitie: 0, noua: true },
  { id: 402, titlu: 'Respirația pântecului', text: 'Cobori respirația în pântec și simți cum corpul se înmoaie. Bună dimineața sau seara, înainte de somn.', poza: '', durata: 900, categorie: 'respiratie', acces: 'abonament', poate_asculta: true, video: { tip: 'vimeo', embed: paginaVideo('Respirația pântecului: cum se face') }, pozitie: 0, noua: true },
  { id: 403, titlu: 'Somn liniștit', text: 'O călătorie blândă spre odihnă. Te întinzi, asculți și lași ziua să se așeze.', poza: '', durata: 1500, categorie: 'somn', acces: 'abonament', poate_asculta: true, video: null, pozitie: 0, noua: false },
  { id: 404, titlu: 'Iubirea de sine', text: 'Te întâlnești cu tine, cu blândețe. O meditație despre a te privi cu ochii cu care privești pe cineva drag.', poza: '', durata: 1140, categorie: 'feminitate', acces: 'cumparata', poate_asculta: true, video: null, pozitie: 0, noua: false },
  { id: 405, titlu: 'Femeia sălbatică', text: 'Reconectarea cu instinctul tău, cu partea din tine care știe singură de ce are nevoie.', poza: '', durata: 1320, categorie: 'feminitate', acces: 'abonament', poate_asculta: false, video: null, pozitie: 0, noua: true },
  { id: 406, titlu: 'Scanarea corpului', text: 'Treci prin corp, parte cu parte, și observi ce simți, fără să judeci.', poza: '', durata: 780, categorie: 'relaxare', acces: 'abonament', poate_asculta: false, video: null, pozitie: 0, noua: false },
  { id: 407, titlu: 'Intenții pentru luna nouă', text: 'Spre finele menstruației, un moment liniștit în care îți așezi intențiile pentru luna care vine.', poza: '', durata: 720, categorie: 'pantec', acces: 'abonament', poate_asculta: true, video: null, pozitie: 0, noua: false },
];

function cursuriScurt(): CursScurt[] {
  return Object.values(CURSURI).map((c) => {
    const total = c.module.length;
    const term = c.module.filter((m) => m.lectii.length && m.lectii.every((l) => l.terminata)).length;
    const lectii = c.module.flatMap((m) => m.lectii);
    return {
      slug: c.slug, nume: c.nume, imagine: c.imagine, pana_la: c.slug === 'alchimia-relatiei-cu-masculinul' ? '2027-05-01' : null,
      progres: lectii.length ? lectii.filter((l) => l.terminata).length / lectii.length : 0,
      module_total: total, module_terminate: term,
    };
  });
}

const L = (cheie: string, titlu: string, tip: 'video' | 'audio' | 'pdf' | 'text', durata: number | null, terminata = false) => ({
  cheie, titlu, tip, durata,
  player: tip === 'video' ? paginaVideo(titlu) : null,
  fisier: tip === 'pdf' ? 'data:text/plain;charset=utf-8,' + encodeURIComponent('Demo: aici se deschide PDF-ul lecției „' + titlu + '”.') : tip === 'audio' ? '__sunet__' : null,
  text: tip === 'text' ? '<p>Ia-ți un jurnal și notează, timp de 28 de zile, în ce fază a ciclului te afli și cum te simți.</p><p>La final, observă singură tiparele care se repetă.</p>' : null,
  terminata,
});

const CURSURI: Record<string, Curs> = {
  'tainele-menstruatiei-constiente': {
    slug: 'tainele-menstruatiei-constiente', nume: 'Tainele Menstruației Conștiente', imagine: '',
    descriere: 'Un drum blând prin ciclul tău: ce îți spune corpul la menstruație, ritualuri de îngrijire și practici feminine.',
    module: [
      { id: 'm1', titlu: 'Practici feminine', deschis: true, se_deschide_la: null, lectii: [
        L('tmc-1', 'Ciclul feminin și menstruația', 'video', 2280, true),
        L('tmc-2', 'Instrucțiuni respirație', 'video', 420, true),
        L('tmc-3', 'Respirația Rebirth', 'audio', 1500, true),
        L('tmc-4', 'Mesajele corpului la menstruație', 'video', 1860),
      ] },
      { id: 'm2', titlu: 'Ritualuri la menstruație', deschis: true, se_deschide_la: null, lectii: [
        L('tmc-5', 'Dansul menstruației', 'video', 1260),
        L('tmc-6', 'Ritualuri de auto-îngrijire la menstruație', 'video', 1980),
        L('tmc-7', 'Fișa de lucru: jurnalul ciclului', 'pdf', null),
        L('tmc-8', 'Exercițiul de 28 de zile', 'text', null),
      ] },
      { id: 'm3', titlu: 'Ciclul lunii', deschis: false, se_deschide_la: '2026-11-10', lectii: [
        L('tmc-9', 'Ciclul lunii', 'video', 1740),
        L('tmc-10', 'Ce le spunem fetelor despre menstruație?', 'video', 1320),
      ] },
    ],
  },
  'alchimia-relatiei-cu-masculinul': {
    slug: 'alchimia-relatiei-cu-masculinul', nume: 'Alchimia Relației cu Masculinul', imagine: '',
    descriere: 'Patru sesiuni despre povestea ta cu masculinul, vindecarea rănilor emoționale și o relație nouă, mai blândă.',
    module: [
      { id: 'm1', titlu: 'Sesiunea 1', deschis: true, se_deschide_la: null, lectii: [L('arm-1', 'Povestea ta cu masculinul', 'video', 3600)] },
      { id: 'm2', titlu: 'Sesiunea 2', deschis: true, se_deschide_la: null, lectii: [L('arm-2', 'Vindecarea rănilor emoționale', 'video', 3420)] },
      { id: 'm3', titlu: 'Sesiunea 3', deschis: true, se_deschide_la: null, lectii: [L('arm-3', 'Activarea masculinului interior sănătos', 'video', 3300), L('arm-4', 'Respirația', 'audio', 900)] },
    ],
  },
};

let cicluServer: CicluServer | null = null;

function cuSunet(c: Curs): Curs {
  return { ...c, module: c.module.map((m) => ({ ...m, lectii: m.lectii.map((l) => (l.fisier === '__sunet__' ? { ...l, fisier: sunetDemo() } : l)) })) };
}

export const apiMock: Api = {
  async cod(email) {
    await asteapta(500);
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) throw new ApiError('invalid', 'Adresa de email nu pare corectă. Verifică-o, te rog.', 400);
    return ok;
  },
  async verifica(email, cod) {
    await asteapta(500);
    if (cod !== '123456') throw new ApiError('cod_gresit', MESAJE_IMPLICITE.cod_gresit, 400);
    return { token: 'f'.repeat(64), utilizatoare: { email, prenume: 'Ana' } };
  },
  async iesire() { await asteapta(); return ok; },
  async stergeCont() { await asteapta(); cicluServer = null; return ok; },
  async config() {
    return {
      versiune_minima: '1.0.0',
      contact: { email: 'contact@adelaharu.ro', whatsapp: '+40742709306' },
      linkuri: { site: 'https://www.adelaharu.ro/', confidentialitate: 'https://www.adelaharu.ro/politica-de-confidentialitate/', termeni: 'https://www.adelaharu.ro/termeni-si-conditii/' },
      mesaj: null,
    };
  },
  async eu(): Promise<Eu> {
    await asteapta();
    return {
      email: 'ana@exemplu.ro', prenume: 'Ana',
      inner_temple: { activ: true, pana_la: '2027-01-15', tip: 'anual', se_reinnoieste: true },
      meditatii_cumparate: 1,
      cursuri: cursuriScurt(),
      ciclu: { salvat_pe_site: !!cicluServer, emailuri_faze: !!cicluServer?.emailuri_faze },
    };
  },
  async meditatii(): Promise<MeditatiiRaspuns> {
    await asteapta();
    return { categorii: CATEGORII, meditatii: MEDITATII.map((m) => ({ ...m })) };
  },
  async redare(id) {
    await asteapta(150);
    const m = MEDITATII.find((x) => x.id === id);
    if (!m) throw new ApiError('negasit', MESAJE_IMPLICITE.negasit, 404);
    if (!m.poate_asculta) throw new ApiError('fara_acces', 'Această meditație nu e inclusă în accesul tău.', 403);
    return { url: sunetDemo(), expira: new Date(Date.now() + 6 * 3600e3).toISOString() };
  },
  async pozitie(id, secunde, terminat) {
    const m = MEDITATII.find((x) => x.id === id);
    if (m) m.pozitie = terminat ? 0 : Math.floor(secunde);
    return ok;
  },
  async cursuri() { await asteapta(); return cursuriScurt(); },
  async curs(slug) {
    await asteapta();
    const c = CURSURI[slug];
    if (!c) throw new ApiError('negasit', MESAJE_IMPLICITE.negasit, 404);
    return cuSunet(structuredClone(c));
  },
  async progres(slug, cheie, terminata) {
    const l = CURSURI[slug]?.module.flatMap((m) => m.lectii).find((x) => x.cheie === cheie);
    if (l) l.terminata = terminata;
    return ok;
  },
  async faze() {
    return {
      faze: [
        { slug: 'iarna', arhetip: 'Alchimista', anotimp: 'Iarna', culoare: '#8C4A3A', ce_se_intampla: '', ce_e_potrivit: '', meditatie_id: 330, curs_slug: null },
        { slug: 'primavara', arhetip: 'Fecioara', anotimp: 'Primăvara', culoare: '#8F9A6E', ce_se_intampla: '', ce_e_potrivit: '', meditatie_id: 401, curs_slug: null },
        { slug: 'vara', arhetip: 'Mama', anotimp: 'Vara', culoare: '#C29A4A', ce_se_intampla: '', ce_e_potrivit: '', meditatie_id: 404, curs_slug: null },
        { slug: 'toamna', arhetip: 'Fermecătoreasa', anotimp: 'Toamna', culoare: '#A9714F', ce_se_intampla: '', ce_e_potrivit: '', meditatie_id: 328, curs_slug: null },
      ],
      regula: '',
      avertisment: 'Fazele sunt o estimare, nu o metodă de contracepție.',
    };
  },
  async ciclu() { await asteapta(); return cicluServer; },
  async salveazaCiclu(c) {
    await asteapta();
    if (!c.acord_date_sanatate) throw new ApiError('invalid', MESAJE_IMPLICITE.invalid, 400);
    cicluServer = { prima_zi: c.prima_zi, zile_ciclu: c.zile_ciclu, zile_sangerare: c.zile_sangerare, emailuri_faze: c.emailuri_faze };
    return ok;
  },
  async stergeCiclu() { await asteapta(); cicluServer = null; return ok; },
  async push() { return ok; },
  async stergePush() { return ok; },
};
