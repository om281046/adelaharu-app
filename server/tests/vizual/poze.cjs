/**
 * Harnașul vizual al modului aplicație. Rulare (din rădăcina proiectului): node server/tests/vizual/poze.cjs
 *
 * 1. construieste.php face paginile: Spațiul meu (CSS/JS din 235 și 266, datele de probă) și o sală de curs, fiecare
 *    în două variante: browser și aplicație (aceasta trecută prin cârligele reale ale fragmentului ah-sp-aplicatie.php).
 * 2. Chromium (Playwright), 390×844, servește totul ca https://www.adelaharu.ro (nimic nu pleacă pe internet; nimic nu se
 *    scrie pe site), cu window.Capacitor imitat în aplicație (Preferences, push, notificări locale, fișiere, descărcări).
 * 3. Pozele fiecărei secțiuni, în ambele variante, merg în docs/screenshots-v2/.
 * 4. Verifică: în aplicație nu se vede nimic de cumpărat (texte, prețuri, linkuri), puntea programează mementourile
 *    ciclului exact după regula din 235, descărcarea, Media Session, notificările, ieșirea și ștergerea contului.
 * Iese cu cod 1 dacă o verificare pică.
 */
'use strict';
process.env.TZ = 'Europe/Bucharest';
const fs = require('fs');
const os = require('os');
const path = require('path');
const { execFileSync } = require('child_process');
const { chromium } = require('playwright');
const CI235 = require('../sn235-ciclu.cjs');

const RAD = path.join(__dirname, '..', '..', '..');
const OUT = path.join(RAD, 'docs', 'screenshots-v2');
const TMP = fs.mkdtempSync(path.join(process.env.AHSPA_TMP || os.tmpdir(), 'ahspa-viz-'));
const FONT = path.join(RAD, 'app', 'node_modules', '@fontsource');
fs.mkdirSync(OUT, { recursive: true });
for (const f of fs.readdirSync(OUT)) if (f.endsWith('.png')) fs.unlinkSync(path.join(OUT, f));
execFileSync('php', [path.join(__dirname, 'construieste.php'), TMP], { stdio: 'inherit' });

const UA_SAFARI = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.1 Mobile/15E148 Safari/604.1';
const UA_APP = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 AdelaHaruApp/1.0.0 (ios)';
const UA_ANDROID = 'Mozilla/5.0 (Linux; Android 14; Pixel 7 Build/AP2A; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/131.0.0.0 Mobile Safari/537.36 AdelaHaruApp/2.0.0 (android)';
const SITE = 'https://www.adelaharu.ro';

let ok = 0, rau = 0;
const verifica = (c, nume) => { if (c) ok++; else { rau++; console.log('PICAT: ' + nume); } };
const egal = (a, b, nume) => verifica(JSON.stringify(a) === JSON.stringify(b), nume + (JSON.stringify(a) === JSON.stringify(b) ? '' : `\n   am:       ${JSON.stringify(a)}\n   așteptam: ${JSON.stringify(b)}`));

/* ---------- ce servește „site-ul” ---------- */
const CUL = [['#964E46', '#D9A08F'], ['#7A5A75', '#C7A9C9'], ['#56704D', '#A9C29A'], ['#85622E', '#D8B880'], ['#6B5B8E', '#B5A6D8'], ['#9E533A', '#E3A98F'], ['#3E6876', '#9CC0CB']];
function svg(nume) {
  let h = 0; for (const c of nume) h = (h * 31 + c.charCodeAt(0)) >>> 0;
  const [a, b] = CUL[h % CUL.length];
  return `<svg xmlns="http://www.w3.org/2000/svg" width="600" height="600" viewBox="0 0 600 600"><defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="${b}"/><stop offset="1" stop-color="${a}"/></linearGradient></defs><rect width="600" height="600" fill="url(#g)"/><circle cx="430" cy="170" r="120" fill="#fff" opacity=".18"/><circle cx="200" cy="420" r="160" fill="#fff" opacity=".1"/></svg>`;
}
function wav(sec) {
  const rate = 8000, n = rate * sec, b = Buffer.alloc(44 + n * 2);
  b.write('RIFF', 0); b.writeUInt32LE(36 + n * 2, 4); b.write('WAVEfmt ', 8); b.writeUInt32LE(16, 16); b.writeUInt16LE(1, 20); b.writeUInt16LE(1, 22);
  b.writeUInt32LE(rate, 24); b.writeUInt32LE(rate * 2, 28); b.writeUInt16LE(2, 32); b.writeUInt16LE(16, 34); b.write('data', 36); b.writeUInt32LE(n * 2, 40);
  return b;
}
const WAV = wav(30);
const FONTS_CSS = ['figtree/400.css', 'figtree/500.css', 'figtree/600.css', 'gilda-display/400.css', 'cormorant-garamond/400-italic.css', 'cormorant-garamond/500-italic.css'].map((f) => `@import url("/fonts/${f}");`).join('\n');

async function ruta(context, varianta, jurnal) {
  await context.route('**/*', async (route) => {
    const req = route.request(), u = new URL(req.url());
    if (u.hostname !== 'www.adelaharu.ro') return route.abort();
    const p = u.pathname, json = (o, s) => route.fulfill({ status: s || 200, contentType: 'application/json', body: JSON.stringify(o) });
    if (!p.startsWith('/fonts/') && !p.startsWith('/img/')) jurnal.push({ m: req.method(), u: req.url(), corp: req.postData(), h: req.headers()['x-ah-app'] || '' });
    if (p === '/spatiul-meu/') return route.fulfill({ status: 200, contentType: 'text/html; charset=utf-8', body: fs.readFileSync(path.join(TMP, `sp-${varianta}.html`)) });
    if (p === '/sala-de-curs/') return route.fulfill({ status: 200, contentType: 'text/html; charset=utf-8', body: fs.readFileSync(path.join(TMP, `sala-${varianta}.html`)) });
    if (p === '/termeni-si-conditii/') return route.fulfill({ status: 200, contentType: 'text/html; charset=utf-8', body: fs.readFileSync(path.join(TMP, `termeni-${varianta}.html`)) });
    if (p === '/' && u.searchParams.has('ah_sc_ics')) return route.fulfill({ status: 200, contentType: 'text/calendar; charset=utf-8', headers: { 'Content-Disposition': 'attachment; filename="ritmul-meu.ics"' }, body: 'BEGIN:VCALENDAR\r\nEND:VCALENDAR\r\n' });
    if (p.startsWith('/img/')) return route.fulfill({ status: 200, contentType: 'image/svg+xml', body: svg(p) });
    if (p === '/fonts/fonts.css') return route.fulfill({ status: 200, contentType: 'text/css', body: FONTS_CSS });
    if (p.startsWith('/fonts/')) { const f = path.join(FONT, p.slice(7)); return fs.existsSync(f) ? route.fulfill({ status: 200, path: f }) : route.fulfill({ status: 404, body: '' }); }
    if (p.startsWith('/audio/')) return route.fulfill({ status: 200, contentType: 'audio/wav', body: WAV });
    if (p === '/' && u.searchParams.get('ah_med_api') === 'biblioteca') return json({ ok: true, meditatii: [501, 502, 503, 504].map((id) => ({ id, audio: `${SITE}/audio/${id}.wav?ah_med_api=audio` })) });
    if (p === '/wp-json/ah-spa/v1/descarca') { const id = +u.searchParams.get('id'); return json({ ok: true, url: `${SITE}/wp-json/ah-spa/v1/fisier?id=${id}&exp=1795000900&c=0123456789abcdef&s=semnat`, expira: 1795000900, titlu: { 501: 'Respirația inimii', 502: 'Pântecul, centrul tău', 503: 'Somn liniștit', 504: 'Un dar pentru tine' }[id], durata: 960, imagine: `${SITE}/img/med-${id - 500}.svg`, ext: 'mp3' }); }
    if (p.startsWith('/wp-json/ah-spa/v1/')) return json({ ok: true });
    if (p === '/wp-json/ah-sp/v1/ciclu-date') return json(req.method() === 'GET' ? { ok: true, acord: false } : { ok: true, actualizat: '2026-11-18 10:00:00' });
    if (p.startsWith('/wp-json/')) return json({ ok: true });
    return route.fulfill({ status: 404, contentType: 'text/html; charset=utf-8', body: '<h1>Pagina nu e în harnaș: ' + p + '</h1>' });
  });
}

/* ---------- window.Capacitor imitat (Preferences, push, notificări, fișiere) ---------- */
function capacitor(opt) {
  return `(() => { try { if (window.top !== window || location.protocol !== 'https:') return; } catch (e) { return; }
  if (!sessionStorage.getItem('harnas_init')) {
    sessionStorage.setItem('harnas_init', '1');
    sessionStorage.setItem('harnas_prefs', JSON.stringify(${JSON.stringify(opt.prefs || {})}));
    sessionStorage.setItem('harnas_perm', ${JSON.stringify(opt.perm || 'prompt')});
    sessionStorage.setItem('harnas_apeluri', '[]');
    sessionStorage.setItem('harnas_pending', '[]');
  }
  const ss = (k, v) => v === undefined ? JSON.parse(sessionStorage.getItem(k) || 'null') : sessionStorage.setItem(k, JSON.stringify(v));
  const rec = (n, m, a) => { const l = ss('harnas_apeluri') || []; l.push([n, m, a === undefined ? null : JSON.parse(JSON.stringify(a))]); ss('harnas_apeluri', l); };
  const L = {};
  const on = (n) => (e, f) => { (L[n + '.' + e] = L[n + '.' + e] || []).push(f); return Promise.resolve({ remove() {} }); };
  const emit = (k, d) => (L[k] || []).forEach((f) => f(d));
  const perm = () => sessionStorage.getItem('harnas_perm');
  const prefs = () => JSON.parse(sessionStorage.getItem('harnas_prefs') || '{}');
  const P = {
    App: { addListener: on('App'), getLaunchUrl: () => Promise.resolve({}) },
    Device: { getId: () => Promise.resolve({ identifier: 'iphone-de-proba-1' }) },
    AhApp: { info: () => Promise.resolve({ push: ${JSON.stringify(opt.push !== false)}, versiune: '2.0.0', build: '2', mediaNativ: ${JSON.stringify(opt.platforma === 'android')} }) },
    AhMedia: ${JSON.stringify(opt.platforma === 'android')} ? {
      addListener: on('AhMedia'),
      actualizeaza: (a) => { rec('AhMedia', 'actualizeaza', a); return Promise.resolve(); },
      opreste: () => { rec('AhMedia', 'opreste'); return Promise.resolve(); },
    } : undefined,
    PushNotifications: {
      addListener: on('PushNotifications'),
      checkPermissions: () => Promise.resolve({ receive: perm() }),
      requestPermissions: () => { rec('PushNotifications', 'requestPermissions'); sessionStorage.setItem('harnas_perm', 'granted'); return Promise.resolve({ receive: 'granted' }); },
      register: () => { rec('PushNotifications', 'register'); setTimeout(() => emit('PushNotifications.registration', { value: 'fcmProba:APA91b' + 'x'.repeat(120) }), 30); return Promise.resolve(); },
      unregister: () => { rec('PushNotifications', 'unregister'); return Promise.resolve(); },
    },
    LocalNotifications: {
      addListener: on('LocalNotifications'),
      checkPermissions: () => Promise.resolve({ display: perm() === 'granted' ? 'granted' : 'prompt' }),
      requestPermissions: () => { rec('LocalNotifications', 'requestPermissions'); sessionStorage.setItem('harnas_perm', 'granted'); return Promise.resolve({ display: 'granted' }); },
      schedule: (a) => { rec('LocalNotifications', 'schedule', a); ss('harnas_pending', (ss('harnas_pending') || []).concat(a.notifications.map((n) => ({ id: n.id })))); return Promise.resolve({ notifications: a.notifications.map((n) => ({ id: n.id })) }); },
      getPending: () => Promise.resolve({ notifications: ss('harnas_pending') || [] }),
      cancel: (a) => { rec('LocalNotifications', 'cancel', a); const ids = a.notifications.map((n) => n.id); ss('harnas_pending', (ss('harnas_pending') || []).filter((n) => ids.indexOf(n.id) < 0)); return Promise.resolve(); },
    },
    Preferences: {
      get: ({ key }) => Promise.resolve({ value: key in prefs() ? prefs()[key] : null }),
      set: ({ key, value }) => { const p = prefs(); p[key] = value; sessionStorage.setItem('harnas_prefs', JSON.stringify(p)); return Promise.resolve(); },
      remove: ({ key }) => { const p = prefs(); delete p[key]; sessionStorage.setItem('harnas_prefs', JSON.stringify(p)); rec('Preferences', 'remove', { key }); return Promise.resolve(); },
    },
    Filesystem: {
      getUri: ({ path, directory }) => Promise.resolve({ uri: 'file:///var/mobile/Containers/Data/' + directory + '/' + path }),
      mkdir: (a) => { rec('Filesystem', 'mkdir', a); return Promise.resolve(); },
      deleteFile: (a) => { rec('Filesystem', 'deleteFile', a); return Promise.resolve(); },
      rmdir: (a) => { rec('Filesystem', 'rmdir', a); return Promise.resolve(); },
    },
    Browser: { open: (a) => { rec('Browser', 'open', a); return Promise.resolve(); } },
    FileTransfer: {
      addListener: on('FileTransfer'),
      downloadFile: (a) => { rec('FileTransfer', 'downloadFile', a); setTimeout(() => emit('FileTransfer.progress', { type: 'download', url: a.url, bytes: 420000, contentLength: 1000000, lengthComputable: true }), 80); return new Promise((r) => setTimeout(() => r({ path: a.path }), 700)); },
    },
  };
  window.Capacitor = { isNativePlatform: () => true, getPlatform: () => ${JSON.stringify(opt.platforma || 'ios')}, Plugins: P };
  window.__emit = emit;
})();`;
}

/* datele ciclului: ultima menstruație acum 8 zile (azi = ziua 9), cicluri de 28 de zile */
const azi = new Date(); azi.setHours(12, 0, 0, 0);
const zi = (n) => { const d = new Date(azi); d.setDate(d.getDate() - n); return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); };
const CICLU = { starturi: [zi(8), zi(36), zi(64)], lung: 28, men: 5, sit: 'natural' };
const LOCAL = `(() => { try { if (window.top !== window || location.protocol !== 'https:') return; } catch (e) { return; } if (sessionStorage.getItem('harnas_ls')) return; sessionStorage.setItem('harnas_ls', '1');
  localStorage.setItem('ahsp_ciclu', ${JSON.stringify(JSON.stringify(CICLU))});
  localStorage.setItem('ahsp_med_502', JSON.stringify({ t: 420, f: 0 }));
  localStorage.setItem('ahspx_viz', JSON.stringify({ n: 1, zi: '' }));
  localStorage.setItem('ahsp_partener', JSON.stringify({ k: 'k0123456789', s: ${JSON.stringify(zi(8))}, c: 28, m: 5, n: 'Ana', creat: ${JSON.stringify(zi(1))} }));
})();`;

/* mementourile așteptate, calculate cu Ciclul meu din 235 (nu cu puntea) */
function asteptate() {
  const acum = new Date(); const CI = CI235({ ahsp_ciclu: CICLU }, acum.getTime()), z = CI.azi(), fz = CI.faze(z.L, 5), o = [];
  const aziZ = CI.azi0(), dupa9 = acum.getHours() >= 9;
  for (let k = 0; k <= 4; k++) fz.forEach((p) => { const d = z.s0 + k * z.L + p.a - 1; if (d < aziZ || d > aziZ + 3 * z.L || (d === aziZ && dupa9)) return; o.push(CI.iso(d) + ' 09:00 ' + p.f); });
  return o.sort();
}
const FT = { 'Iarna ta interioară': 'iarna', 'Primăvara ta interioară': 'primavara', 'Vara ta interioară': 'vara', 'Toamna ta interioară': 'toamna' };
const ziOra = (iso) => { const d = new Date(iso); return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0') + ' ' + String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0'); };

/* ce nu are voie să se vadă în aplicație (texte și prețuri) */
const INTERZIS = [/\d+\s?lei\b/i, /Plătește/, /plătit/i, /Card cadou/i, /Dă mai departe/, /Women Empower/i, /\bComenzi\b/, /Comenzile mele/, /Gestionează/, /Black Friday/i, /Încă închise/, /Încă nedeschise/, /Vezi cursurile/, /Descoperă cursul/, /Ai o plată/, /Înapoi pe site/, /Mai departe:/, /Revino/, /Abonamentul meu/, /Oprești abonamentul/, /Vezi detalii/, /te poți abona/i, /Pune pe ecranul telefonului/, /Practicile pentru fiecare fază/, /^Mai departe$/m, /Vezi programul/, /Vreau să continui/, /te invit să aduci respirația/, /Vezi ofertele/, /\bShop\b/, /\bCart\b/, /Magazin/, /Cursuri online/];
async function nimicDeCumparat(page, unde) {
  const r = await page.evaluate((re) => {
    const txt = document.body.innerText;
    const gasite = re.map((s) => new RegExp(s.src, s.f)).filter((x) => x.test(txt)).map(String);
    const linkuri = [];
    document.querySelectorAll('a[href]').forEach((a) => { const b = a.getBoundingClientRect(), cs = getComputedStyle(a); if (b.width > 0 && b.height > 0 && cs.visibility !== 'hidden' && window.AHApp.blocat(a.getAttribute('href'))) linkuri.push(a.getAttribute('href') + ' «' + a.textContent.trim().slice(0, 40) + '»'); });
    const vazut = (x) => { const b = x.getBoundingClientRect(); return b.width > 0 && b.height > 0 && getComputedStyle(x).visibility !== 'hidden'; };
    const el = [...document.querySelectorAll('#ahsp .cov.inchis, #ahsp .cov-pret, #ahsp .curs.inchis, #ahsb, #rsg, .ah-bf-banda, #ah-bf-bara, #ahs-urmator, #ah-dar7-curs, .elementor-location-header, .elementor-location-footer, #ahbq-banda')].filter(vazut).length;
    return { gasite, linkuri, el };
  }, INTERZIS.map((x) => ({ src: x.source, f: x.flags })));
  egal(r.gasite, [], `aplicație ${unde}: niciun text de cumpărare`);
  egal(r.linkuri, [], `aplicație ${unde}: niciun link spre cumpărare`);
  egal(r.el, 0, `aplicație ${unde}: niciun element de vânzare`);
}
const apeluri = (page) => page.evaluate(() => JSON.parse(sessionStorage.getItem('harnas_apeluri') || '[]'));
const prefs = (page) => page.evaluate(() => JSON.parse(sessionStorage.getItem('harnas_prefs') || '{}'));
const asteapta = (ms) => new Promise((r) => setTimeout(r, ms));
const SECT = [['acasa', 'Acasă'], ['ciclu', 'Ciclul meu'], ['cursuri', 'Cursurile mele'], ['meditatii', 'Inner Temple'], ['carti', 'Cărțile mele'], ['abonamente', 'Abonamente'], ['comenzi', 'Comenzile mele'], ['podcast', 'Podcast'], ['mai', 'Mai mult']];
let nr = 0;
const poza = async (page, nume) => { nr++; await page.screenshot({ path: path.join(OUT, `${nume}.png`), fullPage: true }); };

(async () => {
  const browser = await chromium.launch();
  const ctxOpt = { viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true, locale: 'ro-RO', timezoneId: 'Europe/Bucharest' };

  /* ===== 1. browserul: Spațiul meu exact ca acum (cu tot ce se vinde) ===== */
  {
    const jurnal = [], ctx = await browser.newContext({ ...ctxOpt, userAgent: UA_SAFARI });
    await ruta(ctx, 'browser', jurnal); await ctx.addInitScript(LOCAL);
    const page = await ctx.newPage(); const erori = []; page.on('pageerror', (e) => erori.push(e.message));
    await page.goto(SITE + '/spatiul-meu/#acasa'); await asteapta(900);
    verifica(await page.evaluate(() => !window.AHApp && !document.getElementById('ahspa-js')), 'browser: fără puntea aplicației');
    let tot = '';
    for (let i = 0; i < SECT.length; i++) {
      await page.evaluate((h) => { location.hash = h; }, '#' + SECT[i][0]); await asteapta(500);
      tot += await page.evaluate(() => document.body.innerText);
      await poza(page, `browser-${String(i + 1).padStart(2, '0')}-${SECT[i][0]}`);
    }
    for (const x of [/\d+\s?lei\b/, /Plătește/, /Card cadou/, /Comenzile mele/, /Gestionează/, /Încă închise/, /Încă nedeschise/]) verifica(x.test(tot), 'browser (martor): se vede ' + x);
    await page.evaluate(() => { location.hash = '#podcast'; }); await asteapta(400); await page.click('[data-ep="0"]'); await asteapta(400);
    verifica(/Mai departe: Wild Love/.test(await page.evaluate(() => document.body.innerText)), 'browser (martor): podcastul trimite la curs');
    await poza(page, 'browser-10-podcast-episod');
    await page.goto(SITE + '/sala-de-curs/?comanda=14770&cheie=wc_order_r'); await asteapta(500);
    await poza(page, 'browser-11-sala-de-curs');
    await page.goto(SITE + '/termeni-si-conditii/'); await asteapta(400);
    verifica(/\bShop\b/.test(await page.evaluate(() => document.body.innerText)) && await page.locator('#ah-bf-banda').isVisible(), 'browser (martor): pe Termeni se văd magazinul și Black Friday');
    await poza(page, 'browser-12-termeni');
    egal(erori, [], 'browser: fără erori JS');
    await ctx.close();
  }

  /* ===== 2. aplicația: fiecare secțiune, notificările, descărcarea, ștergerea contului ===== */
  {
    const jurnal = [], ctx = await browser.newContext({ ...ctxOpt, userAgent: UA_APP });
    await ruta(ctx, 'aplicatie', jurnal); await ctx.addInitScript(capacitor({ prefs: { ah_deschideri: '1' }, perm: 'prompt' })); await ctx.addInitScript(LOCAL);
    const page = await ctx.newPage(); const erori = []; page.on('pageerror', (e) => erori.push(e.message));
    await page.goto(SITE + '/spatiul-meu/#acasa'); await asteapta(3400);
    verifica(await page.evaluate(() => typeof window.AHApp === 'object'), 'aplicație: puntea e pornită');
    verifica(await page.locator('.ahspa-foaie').isVisible(), 'aplicație: la a doua deschidere apare explicația notificărilor');
    await poza(page, 'aplicatie-00-intrebare-notificari');
    await page.click('.ahspa-foaie [data-a="da"]'); await asteapta(1500);
    const a1 = await apeluri(page);
    verifica(a1.some((x) => x[0] === 'PushNotifications' && x[1] === 'requestPermissions'), 'notificări: permisiunea cerută după „Da”');
    verifica(a1.some((x) => x[0] === 'PushNotifications' && x[1] === 'register'), 'notificări: telefonul se înregistrează');
    const pj = jurnal.filter((x) => x.m === 'POST' && x.u.endsWith('/wp-json/ah-spa/v1/push'));
    verifica(pj.length === 1 && JSON.parse(pj[0].corp).token.startsWith('fcmProba:') && JSON.parse(pj[0].corp).platforma === 'ios' && JSON.parse(pj[0].corp).disp === 'iphone-de-proba-1' && pj[0].h === '1', 'notificări: tokenul ajunge la POST /push (cu antetul X-AH-App)');
    const p1 = await prefs(page);
    egal(JSON.parse(p1.ah_push_intrebat || '0'), 1, 'notificări: întrebarea nu se mai repetă');
    const ac = JSON.parse(p1.ah_ciclu || 'null');
    egal(ac && [ac.starturi, ac.lung, ac.men, ac.sit], [CICLU.starturi, 28, 5, 'natural'], 'Preferences ah_ciclu: datele pentru pagina offline');
    verifica(ac && !isNaN(Date.parse(ac.actualizat)), 'Preferences ah_ciclu: actualizat');
    const sch = a1.filter((x) => x[0] === 'LocalNotifications' && x[1] === 'schedule').pop();
    const prog = sch ? sch[2].notifications.map((n) => ziOra(n.schedule.at) + ' ' + FT[n.title]).sort() : [];
    egal(prog, asteptate(), 'mementourile ciclului: exact începuturile fazelor din Ciclul meu (235), pe 3 cicluri, la 9:00');
    verifica(sch && sch[2].notifications.every((n, i) => n.id === 41000 + i && n.extra.url === SITE + '/spatiul-meu/#ciclu' && n.schedule.allowWhileIdle), 'mementourile: id-uri 41000+, deschid #ciclu');
    console.log('   mementouri: ' + prog.slice(0, 4).join(' · ') + ' …');
    /* toate secțiunile */
    for (let i = 0; i < SECT.length; i++) {
      await page.evaluate((h) => { location.hash = h; }, '#' + SECT[i][0]); await asteapta(700);
      const tit = await page.evaluate(() => document.getElementById('ahsp-tit').textContent);
      if (SECT[i][0] === 'comenzi') egal(tit, 'Acasă', 'aplicație: #comenzi nu există, se deschide Acasă');
      else egal(tit, SECT[i][1], 'aplicație: secțiunea ' + SECT[i][0]);
      await nimicDeCumparat(page, '#' + SECT[i][0]);
      await poza(page, `aplicatie-${String(i + 1).padStart(2, '0')}-${SECT[i][0]}${SECT[i][0] === 'comenzi' ? '-ascunsa' : ''}`);
    }
    egal(await page.evaluate(() => [...document.querySelectorAll('#ahsp .bara a')].map((a) => a.textContent.trim())), ['Acasă', 'Ciclul', 'Cursuri', 'Inner Temple', 'Mai mult'], 'aplicație: bara de jos fără Comenzi');
    /* Ciclul meu › Pentru partenerul tău: linkul e întreg (CI.paLink = D.linkuri.site + '?ciclul-ei#d=…') */
    await page.evaluate(() => { location.hash = '#ciclu'; }); await asteapta(600);
    const pa = await page.evaluate(() => ({ v: (document.getElementById('ci-pa-link') || {}).value || '', wa: (document.getElementById('ci-pa-wa') || { getAttribute: () => '' }).getAttribute('href') || '' }));
    verifica(pa.v.startsWith(SITE + '/?ciclul-ei#d='), 'Ciclul meu: „Linkul pentru el” începe cu https:// (' + pa.v.slice(0, 50) + ')');
    verifica(decodeURIComponent(pa.wa.split('text=')[1] || '').includes(' ' + SITE + '/?ciclul-ei#d='), 'Ciclul meu: „Trimite pe WhatsApp” poartă linkul întreg');
    verifica(await page.evaluate(() => !window.AHApp.blocat(document.getElementById('ci-pa-link').value)), 'Ciclul meu: linkul pentru partener e permis și în aplicație');
    /* descărcarea unei meditații */
    await page.evaluate(() => { location.hash = '#meditatii'; }); await asteapta(600);
    egal(await page.locator('#ahsp .cov-grila .spa-dl').count(), 4, 'Descarcă: lângă fiecare meditație pe care o poate asculta');
    await page.click('.cov-grila [data-spa-desc="501"]'); await asteapta(250);
    await poza(page, 'aplicatie-12-meditatie-se-descarca');
    await page.waitForSelector('.cov-grila .spa-dl[data-dl="501"] .gata', { timeout: 5000 });
    await poza(page, 'aplicatie-13-meditatie-descarcata');
    const a2 = await apeluri(page), dl = a2.filter((x) => x[0] === 'FileTransfer');
    verifica(dl.length === 2 && /\/wp-json\/ah-spa\/v1\/fisier\?id=501&/.test(dl[0][2].url) && dl[0][2].path.endsWith('LIBRARY_NO_CLOUD/meditatii/501.mp3') && dl[1][2].path.endsWith('meditatii/501.jpg'), 'Descarcă: sunetul (link semnat) și poza, în Library fără iCloud');
    const idx = JSON.parse((await prefs(page)).ah_descarcate || '[]');
    egal(idx.map((r) => [r.id, r.titlu, r.durata, r.fisier, r.poza, r.dir]), [[501, 'Respirația inimii', 960, 'meditatii/501.mp3', 'meditatii/501.jpg', 'LIBRARY_NO_CLOUD']], 'Preferences ah_descarcate: rândul meditației');
    /* Media Session pe redare */
    await page.click('[data-play="502"]'); await asteapta(1200);
    const ms = await page.evaluate(() => ({ t: navigator.mediaSession.metadata && navigator.mediaSession.metadata.title, a: navigator.mediaSession.metadata && navigator.mediaSession.metadata.artist, art: navigator.mediaSession.metadata && navigator.mediaSession.metadata.artwork.length, st: navigator.mediaSession.playbackState }));
    egal([ms.t, ms.a, ms.art > 0], ['Pântecul, centrul tău', 'Adela Haru', true], 'ecranul blocat: titlul, „Adela Haru”, poza');
    await poza(page, 'aplicatie-14-player');
    await page.click('#ahsp .player .pl-x');
    /* podcastul: episodul fără „Mai departe” */
    await page.evaluate(() => { location.hash = '#podcast'; }); await asteapta(500); await page.click('[data-ep="0"]'); await asteapta(400);
    await nimicDeCumparat(page, 'episod podcast');
    await poza(page, 'aplicatie-15-podcast-episod');
    await page.click('#ahsp .fo-x');
    /* Mai mult › Aplicația */
    await page.evaluate(() => { location.hash = '#mai'; }); await asteapta(900);
    egal(await page.evaluate(() => [...document.querySelectorAll('#ahsp .spa-mai .rand b')].map((b) => b.textContent)), ['Notificări', 'Mementourile ciclului', 'Meditații descărcate', 'Șterge contul'], 'Mai mult › Aplicația: rândurile punții');
    egal(await page.evaluate(() => [document.getElementById('spa-push').getAttribute('aria-checked'), document.getElementById('spa-ciclu').getAttribute('aria-checked')]), ['true', 'true'], 'comutatoarele: pornite');
    egal(await page.evaluate(() => document.querySelectorAll('#ahsp h2').length && [...document.querySelectorAll('#ahsp h2')].filter((h) => h.textContent === 'Aplicația').length), 1, 'o singură secțiune „Aplicația” (cu 266)');
    await poza(page, 'aplicatie-16-mai-mult-aplicatia');
    /* ștergerea contului */
    await page.click('#spa-sterge'); await asteapta(400);
    await poza(page, 'aplicatie-17-sterge-contul');
    await page.click('.ahspa-foaie [data-a="da"]'); await asteapta(200);
    verifica(/Bifează/.test(await page.locator('#spa-st-m').textContent()) && !jurnal.some((x) => x.u.endsWith('/sterge-cont')), 'ștergerea: fără bifă nu pleacă nimic');
    await page.check('#spa-st-ok'); await page.click('.ahspa-foaie [data-a="da"]'); await asteapta(900);
    const st = jurnal.filter((x) => x.u.endsWith('/wp-json/ah-spa/v1/sterge-cont'));
    verifica(st.length === 1 && st[0].m === 'POST' && JSON.parse(st[0].corp).confirmare === true && st[0].h === '1', 'ștergerea: POST /sterge-cont cu confirmare');
    verifica(jurnal.some((x) => x.m === 'DELETE' && x.u.endsWith('/wp-json/ah-spa/v1/push')), 'ștergerea: tokenul push scos de pe server');
    const p3 = await prefs(page), a3 = await apeluri(page);
    egal(Object.keys(p3).sort(), [], 'ștergerea: Preferences golite (ah_ciclu, ah_descarcate, ah_push…)');
    verifica(a3.some((x) => x[0] === 'Filesystem' && x[1] === 'deleteFile' && x[2].path === 'meditatii/501.mp3'), 'ștergerea: fișierul meditației șters de pe telefon');
    verifica(a3.some((x) => x[0] === 'LocalNotifications' && x[1] === 'cancel'), 'ștergerea: mementourile anulate');
    egal(await page.evaluate(() => [localStorage.getItem('ahsp_ciclu'), localStorage.getItem('ahsp_med_502')]), [null, null], 'ștergerea: datele ciclului de pe telefon');
    await poza(page, 'aplicatie-18-cont-sters');
    egal(erori, [], 'aplicație: fără erori JS');
    await ctx.close();
  }

  /* ===== 3. aplicația: linkurile care o deschid și ieșirea din cont ===== */
  {
    const jurnal = [], ctx = await browser.newContext({ ...ctxOpt, userAgent: UA_APP });
    const tok = 'fcmVechi:APA91b' + 'y'.repeat(120);
    await ruta(ctx, 'aplicatie', jurnal);
    await ctx.addInitScript(capacitor({ perm: 'granted', prefs: { ah_push_intrebat: '1', ah_push: JSON.stringify({ token: tok, cine: 'x', la: 0 }), ah_descarcate: JSON.stringify([{ id: 503, titlu: 'Somn liniștit', durata: 1800, fisier: 'meditatii/503.mp3', poza: '', dir: 'LIBRARY_NO_CLOUD', descarcat: '2026-11-17T10:00:00Z' }]) } }));
    await ctx.addInitScript(LOCAL);
    const page = await ctx.newPage(); const erori = []; page.on('pageerror', (e) => erori.push(e.message));
    await page.goto(SITE + '/spatiul-meu/#acasa'); await asteapta(1200);
    await page.evaluate(() => window.__emit('App.appUrlOpen', { url: 'https://www.adelaharu.ro/product/magia-ciclului-feminin/' })); await asteapta(400);
    egal(new URL(page.url()).pathname, '/spatiul-meu/', 'link spre produs (din afara aplicației): ignorat');
    await page.evaluate(() => window.__emit('App.appUrlOpen', { url: 'https://www.adelaharu.ro/spatiul-meu/?cont=YW5h.abc#ciclu' })); await page.waitForURL(/cont=YW5h/, { timeout: 4000 }).catch(() => {});
    verifica(/\/spatiul-meu\/\?cont=YW5h\.abc#ciclu$/.test(page.url()), 'linkul personal din email deschide contul în aplicație');
    await asteapta(900);
    await page.evaluate(() => window.__emit('PushNotifications.pushNotificationActionPerformed', { notification: { data: { url: 'https://www.adelaharu.ro/spatiul-meu/#meditatii' } } })); await asteapta(700);
    egal(await page.evaluate(() => document.getElementById('ahsp-tit').textContent), 'Inner Temple', 'atingerea unei notificări deschide data.url');
    await page.evaluate(() => { location.hash = '#mai'; }); await asteapta(600);
    const n0 = jurnal.length;
    await page.click('#ahsp-iesi'); await asteapta(1500);
    const dupa = jurnal.slice(n0).map((x) => x.m + ' ' + new URL(x.u).pathname);
    const iDel = dupa.indexOf('DELETE /wp-json/ah-spa/v1/push'), iIesi = dupa.indexOf('POST /wp-json/ah-sp/v1/iesi');
    verifica(iDel > -1 && iIesi > iDel, 'ieșirea din cont: întâi tokenul push, apoi /ah-sp/v1/iesi (' + dupa.join(', ') + ')');
    const del = jurnal.slice(n0).find((x) => x.m === 'DELETE'), post = jurnal.filter((x) => x.m === 'POST' && x.u.endsWith('/ah-spa/v1/push')).pop();
    verifica(post && JSON.parse(post.corp).token !== tok, 'tokenul nou al telefonului (altul decât cel ținut minte) ajunge din nou la server');
    egal(del && JSON.parse(del.corp).token, post && JSON.parse(post.corp).token, 'ieșirea: se scoate tokenul de acum al telefonului');
    const a4 = await apeluri(page);
    verifica(a4.some((x) => x[0] === 'Filesystem' && x[1] === 'deleteFile' && x[2].path === 'meditatii/503.mp3'), 'ieșirea: meditațiile descărcate se șterg');
    egal(erori, [], 'aplicație (linkuri, ieșire): fără erori JS');
    await ctx.close();
  }

  /* ===== 4. sala de curs în aplicație ===== */
  {
    const jurnal = [], ctx = await browser.newContext({ ...ctxOpt, userAgent: UA_APP });
    await ruta(ctx, 'aplicatie', jurnal); await ctx.addInitScript(capacitor({ perm: 'granted', prefs: { ah_push_intrebat: '1' } }));
    const page = await ctx.newPage(); const erori = []; page.on('pageerror', (e) => erori.push(e.message));
    await page.goto(SITE + '/sala-de-curs/?comanda=14770&cheie=wc_order_r'); await asteapta(800);
    await nimicDeCumparat(page, 'sala de curs');
    const t = await page.evaluate(() => document.body.innerText);
    verifica(/Abonament activ până pe 18 decembrie 2026\s*$/m.test(t), 'sala: starea abonamentului rămâne („Abonament activ până pe …”), fără „Abonamentul meu”');
    verifica(/Tot în comanda ta: Wild Love/.test(t) && /Ana, practicile te așteaptă/.test(t), 'sala: lecțiile și cursurile ei rămân');
    egal(await page.evaluate(() => [...document.querySelectorAll('#ahspc-bara a')].filter((a) => a.offsetParent).map((a) => a.textContent.trim())), ['Acasă', 'Ciclul', 'Cursuri', 'Mai mult'], 'sala: bara de jos fără Comenzi');
    await page.evaluate(() => document.querySelector('audio').play().catch(() => {})); await asteapta(600);
    egal(await page.evaluate(() => navigator.mediaSession.metadata && navigator.mediaSession.metadata.artist), 'Adela Haru', 'sala: ecranul blocat și pentru sunetul lecției');
    await poza(page, 'aplicatie-19-sala-de-curs');
    /* „Adaugă în calendarul meu” (44): rămâne și se deschide în browserul din aplicație */
    const ics = page.locator('a[href*="ah_sc_ics="]');
    verifica(await ics.isVisible(), 'sala: „Adaugă în calendarul meu” rămâne');
    await ics.click(); await asteapta(400);
    const bo = (await apeluri(page)).filter((x) => x[0] === 'Browser' && x[1] === 'open');
    verifica(bo.length === 1 && /^https:\/\/www\.adelaharu\.ro\/\?ah_sc_ics=14770&s=/.test(bo[0][2].url), 'sala: fișierul de calendar se deschide cu Browser.open');
    verifica(new URL(page.url()).pathname === '/sala-de-curs/', 'sala: pagina rămâne pe loc');
    egal(erori, [], 'sala în aplicație: fără erori JS');
    await ctx.close();
  }
  /* ===== 5. Android fără Firebase: fără push (register ar opri aplicația); ecranul blocat prin AhMedia ===== */
  {
    const jurnal = [], ctx = await browser.newContext({ ...ctxOpt, userAgent: UA_ANDROID });
    await ruta(ctx, 'aplicatie', jurnal); await ctx.addInitScript(capacitor({ platforma: 'android', push: false, perm: 'granted', prefs: { ah_deschideri: '3', ah_push_on: '1' } })); await ctx.addInitScript(LOCAL);
    const page = await ctx.newPage(); const erori = []; page.on('pageerror', (e) => erori.push(e.message));
    await page.goto(SITE + '/spatiul-meu/#acasa'); await asteapta(3400);
    verifica(!(await page.locator('.ahspa-foaie').count()), 'Android fără Firebase: nu întreabă de notificări');
    await page.evaluate(() => { location.hash = '#mai'; }); await asteapta(900);
    egal(await page.evaluate(() => [...document.querySelectorAll('#ahsp .spa-mai .rand b')].map((b) => b.textContent)), ['Mementourile ciclului', 'Șterge contul'], 'Android fără Firebase: fără comutatorul Notificări');
    await page.evaluate(() => window.AHApp.push.porneste()); await asteapta(300);
    await page.evaluate(() => { location.hash = '#meditatii'; }); await asteapta(600);
    await page.click('[data-play="502"]'); await asteapta(1200);
    let a5 = await apeluri(page);
    verifica(!a5.some((x) => x[0] === 'PushNotifications' && (x[1] === 'register' || x[1] === 'unregister')), 'Android fără Firebase: niciun register/unregister');
    const am = a5.filter((x) => x[0] === 'AhMedia' && x[1] === 'actualizeaza').pop();
    verifica(am && am[2].titlu === 'Pântecul, centrul tău' && am[2].artist === 'Adela Haru' && am[2].canta === true && /^https:\/\//.test(am[2].poza || '') && am[2].durata > 0, 'Android: AhMedia primește titlul, poza, durata și „cântă” (' + JSON.stringify(am && am[2]) + ')');
    await page.evaluate(() => window.__emit('AhMedia.actiune', { tip: 'pause' })); await asteapta(300);
    verifica(await page.evaluate(() => { const a = window.AHApp.mediaNativ.el; return !!a && a.paused; }), 'Android: „pauză” de pe ecranul blocat oprește sunetul din pagină');
    a5 = await apeluri(page);
    egal(a5.filter((x) => x[0] === 'AhMedia' && x[1] === 'actualizeaza').pop()[2].canta, false, 'Android: AhMedia află de pauză');
    // serverul harnașului nu răspunde la „Range”, deci <audio> nu se poate muta: verificăm comenzile pe un element imitat
    const sk = await page.evaluate(() => { const am = window.AHApp.mediaNativ, real = am.el, f = { currentTime: 20, duration: 30, paused: true, ended: false, play() {}, pause() {} };
      am.el = f; window.__emit('AhMedia.actiune', { tip: 'seek', pozitie: 12 }); const r = [f.currentTime];
      window.__emit('AhMedia.actiune', { tip: 'inainte' }); r.push(f.currentTime); window.__emit('AhMedia.actiune', { tip: 'inapoi' }); window.__emit('AhMedia.actiune', { tip: 'inapoi' }); r.push(f.currentTime);
      am.el = real; return r; });
    egal(sk, [12, 27, 0], 'Android: „seek”, +15 s și −15 s de pe ecranul blocat mută sunetul');
    await page.evaluate(() => window.dispatchEvent(new PageTransitionEvent('pagehide'))); await asteapta(200);
    a5 = await apeluri(page);
    verifica(a5.some((x) => x[0] === 'AhMedia' && x[1] === 'opreste'), 'Android: la plecarea din pagină, notificarea AhMedia se oprește');
    egal(erori, [], 'Android: fără erori JS');
    await ctx.close();
  }
  /* ===== 5. Termeni și condiții în aplicație (din ecranul de înscriere și din bannerul de cookie-uri) ===== */
  {
    const jurnal = [], ctx = await browser.newContext({ ...ctxOpt, userAgent: UA_APP });
    await ruta(ctx, 'aplicatie', jurnal); await ctx.addInitScript(capacitor({ perm: 'granted', prefs: { ah_push_intrebat: '1' } }));
    const page = await ctx.newPage(); const erori = []; page.on('pageerror', (e) => erori.push(e.message));
    await page.goto(SITE + '/termeni-si-conditii/'); await asteapta(700);
    await nimicDeCumparat(page, 'Termeni și condiții');
    const t = await page.evaluate(() => document.body.innerText);
    verifica(/Dreptul de retragere/.test(t) && /de exemplu, Magia Ciclului Feminin\)/.test(t), 'Termeni: textul legal rămâne întreg (linkul spre produs devine text)');
    egal(await page.evaluate(() => [...document.querySelectorAll('.entry-content a')].map((a) => a.textContent)), ['contact@adelaharu.ro', 'Politica de confidențialitate', 'Politica de cookie-uri'], 'Termeni: rămân doar linkurile permise');
    verifica(await page.locator('.ahspa-inapoi a').isVisible(), 'Termeni: bara „Înapoi în Spațiul meu”');
    await poza(page, 'aplicatie-20-termeni');
    await page.click('.ahspa-inapoi a'); await page.waitForURL(/\/spatiul-meu\//, { timeout: 4000 }).catch(() => {});
    verifica(new URL(page.url()).pathname === '/spatiul-meu/', 'Termeni: „Înapoi” duce în Spațiul meu');
    egal(erori, [], 'Termeni în aplicație: fără erori JS');
    await ctx.close();
  }
  await browser.close();
  fs.rmSync(TMP, { recursive: true, force: true });
  console.log(`\n${nr} poze în docs/screenshots-v2. ${ok} verificări trecute, ${rau} picate.`);
  process.exit(rau ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(1); });
