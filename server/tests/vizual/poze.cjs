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
/* doar pozele acestui harnaș: partener-*.png (server/tests/partener/poze.cjs) și offline-*.png (app/) rămân */
for (const f of fs.readdirSync(OUT)) if (/^(browser|aplicatie)-.*\.png$/.test(f)) fs.unlinkSync(path.join(OUT, f));
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

/* window.AH_SPA din pagina construită, schimbat pentru un scenariu (ex. contul pentru el: el, api_el, pagina_el) */
function cuSpa(html, ch) {
  if (!ch) return html;
  const a = html.indexOf('window.AH_SPA='), b = html.indexOf(';(function(){', a);
  if (a < 0 || b < 0) throw new Error('AH_SPA nu e în pagină');
  return html.slice(0, a + 14) + JSON.stringify(Object.assign(JSON.parse(html.slice(a + 14, b)), ch)) + html.slice(b);
}
async function ruta(context, varianta, jurnal, spa) {
  await context.route('**/*', async (route) => {
    const req = route.request(), u = new URL(req.url());
    if (u.hostname !== 'www.adelaharu.ro') { jurnal.push({ m: req.method(), u: req.url(), extern: true }); return route.abort(); } /* nimic nu pleacă pe internet; ținem minte ce s-ar fi cerut */
    const p = u.pathname, json = (o, s) => route.fulfill({ status: s || 200, contentType: 'application/json', body: JSON.stringify(o) });
    if (!p.startsWith('/fonts/') && !p.startsWith('/img/')) jurnal.push({ m: req.method(), u: req.url(), corp: req.postData(), h: req.headers()['x-ah-app'] || '', spp: req.headers()['x-ah-spp'] || '' });
    if (p === '/spatiul-meu/') return route.fulfill({ status: 200, contentType: 'text/html; charset=utf-8', body: cuSpa(fs.readFileSync(path.join(TMP, `sp-${u.searchParams.has('harnas_intrare') ? 'intrare-' : ''}${varianta}${u.searchParams.has('harnas_tema') ? '-tema' : ''}.html`), 'utf8'), spa) });
    if (p === '/wp-json/ah-spa/v1/punte') { const css = u.searchParams.get('f') === 'css'; return route.fulfill({ status: 200, contentType: css ? 'text/css; charset=utf-8' : 'application/javascript; charset=utf-8', headers: { 'Cache-Control': 'public, max-age=31536000, immutable' }, body: fs.readFileSync(path.join(TMP, css ? 'punte.css' : 'punte.js')) }); }
    if (p === '/tema/site.css') return route.fulfill({ status: 200, contentType: 'text/css', body: fs.readFileSync(path.join(RAD, 'server', 'tests', 'date', 'tema', 'site.css')) });
    if (p === '/pentru-el/') return route.fulfill({ status: 200, contentType: 'text/html; charset=utf-8', body: '<!doctype html><title>Ciclul ei</title><h1>Pagina lui (harnașul partenerului o testează)</h1>' });
    if (p === '/sala-de-curs/') return route.fulfill({ status: 200, contentType: 'text/html; charset=utf-8', body: fs.readFileSync(path.join(TMP, `sala-${varianta}.html`)) });
    if (p === '/termeni-si-conditii/') return route.fulfill({ status: 200, contentType: 'text/html; charset=utf-8', body: cuSpa(fs.readFileSync(path.join(TMP, `termeni-${varianta}.html`), 'utf8'), spa) });
    if (p === '/' && u.searchParams.has('ah_sc_ics')) return route.fulfill({ status: 200, contentType: 'text/calendar; charset=utf-8', headers: { 'Content-Disposition': 'attachment; filename="ritmul-meu.ics"' }, body: 'BEGIN:VCALENDAR\r\nEND:VCALENDAR\r\n' });
    if (p.startsWith('/img/')) return route.fulfill({ status: 200, contentType: 'image/svg+xml', body: svg(p) });
    if (p === '/fonts/fonts.css') return route.fulfill({ status: 200, contentType: 'text/css', body: FONTS_CSS });
    if (p.startsWith('/fonts/')) { const f = path.join(FONT, p.slice(7)); return fs.existsSync(f) ? route.fulfill({ status: 200, path: f }) : route.fulfill({ status: 404, body: '' }); }
    if (p.startsWith('/audio/')) return route.fulfill({ status: 200, contentType: 'audio/wav', body: WAV });
    /* fișierele pluginurilor de urmărire (Complianz, WooCommerce, Meta) de pe site: goale, dar cerute (le vedem în jurnal) */
    if (/^\/wp-content\/(plugins|uploads)\//.test(p)) return route.fulfill({ status: 200, contentType: p.endsWith('.css') ? 'text/css' : 'application/javascript', body: p.endsWith('.css') ? '.cmplz-hidden{display:none!important}' : '' }); /* bara rămâne ascunsă, ca pe site înainte de complianz.min.js */
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
    App: { addListener: on('App'), getLaunchUrl: () => Promise.resolve({}), minimizeApp: () => { rec('App', 'minimizeApp'); return Promise.resolve(); } },
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
      createChannel: (a) => { rec('LocalNotifications', 'createChannel', a); return Promise.resolve(); },
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
      stat: (a) => (${JSON.stringify(opt.lipsa || [])}.indexOf(a.path) > -1 ? Promise.reject(new Error('File does not exist')) : Promise.resolve({ type: 'file', size: 1 })),
      rmdir: (a) => { rec('Filesystem', 'rmdir', a); return Promise.resolve(); },
    },
    Browser: { open: (a) => { rec('Browser', 'open', a); return Promise.resolve(); } },
    SplashScreen: { hide: (a) => { rec('SplashScreen', 'hide', a); return Promise.resolve(); } },
    FileTransfer: {
      addListener: on('FileTransfer'),
      downloadFile: (a) => { rec('FileTransfer', 'downloadFile', a); if (${JSON.stringify(opt.dlEsec || [])}.some((id) => a.url.includes('id=' + id + '&'))) return new Promise((r, j) => setTimeout(() => j(new Error('OS-PLUG-FLTR-0010: Permission denied')), 200)); setTimeout(() => emit('FileTransfer.progress', { type: 'download', url: a.url, bytes: 420000, contentLength: 1000000, lengthComputable: true }), 80); return new Promise((r) => setTimeout(() => r({ path: a.path }), 700)); },
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
/* urmărirea: cereri spre serviciile de analiză / reclame / bara de cookie-uri și ce a rămas în pagină */
const URM = /googletagmanager\.com|google-analytics\.com|connect\.facebook\.net|facebook\.com\/tr|clarity\.ms|sourcebuster|order-attribution|facebook-for-woocommerce|complianz/i;
const cereriUrm = (jurnal) => jurnal.filter((x) => URM.test(x.u)).map((x) => x.u.replace(/\?.*$/, ''));
const urmPagina = (page) => page.evaluate(() => ({
  gtag: typeof window.gtag, fbq: typeof window.fbq, dataLayer: Array.isArray(window.dataLayer), clarity: typeof window.clarity, complianz: typeof window.complianz,
  scripturi: [...document.scripts].filter((x) => /googletagmanager|connect\.facebook|clarity\.ms|sourcebuster|order-attribution|facebook-for-woocommerce|complianz|cmplz-cookiebanner/i.test((x.src || '') + (x.id || '') + (x.getAttribute('data-cmplz-src') || '')) || /function gtag\(|fbq\('init'|cmplzCallClarity/.test(x.textContent)).length,
  bara: !!document.getElementById('cmplz-cookiebanner-container'),
  stiluri: [...document.querySelectorAll('link[rel=stylesheet]')].filter((l) => /cmplz-(?!document)/.test(l.id)).length,
}));
const apeluri = (page) => page.evaluate(() => JSON.parse(sessionStorage.getItem('harnas_apeluri') || '[]'));
const prefs = (page) => page.evaluate(() => JSON.parse(sessionStorage.getItem('harnas_prefs') || '{}'));
const asteapta = (ms) => new Promise((r) => setTimeout(r, ms));
/* axe-core (app/node_modules/axe-core): doar problemele serioase și critice, cu ținta lor (fără Comenzi, ascunse) */
const AXE = path.join(RAD, 'app', 'node_modules', 'axe-core', 'axe.min.js');
async function axe(page) {
  if (!(await page.evaluate(() => !!window.axe))) await page.addScriptTag({ path: AXE });
  return page.evaluate(async () => {
    const r = await window.axe.run(document, { resultTypes: ['violations'] });
    return r.violations.filter((v) => v.impact === 'serious' || v.impact === 'critical').map((v) => v.id + ': ' + v.nodes.slice(0, 3).map((n) => n.target.join(' ')).join(' | '));
  });
}
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
    verifica(await page.evaluate(() => /^https:\/\/embed\.podcasts\.apple\.com\//.test((document.querySelector('#ahsp iframe.pod-ifr') || {}).src || '')), 'browser (martor): episodul are playerul Apple Podcasts');
    verifica(jurnal.some((x) => x.extern && /^https:\/\/embed\.podcasts\.apple\.com\//.test(x.u)), 'browser (martor): playerul Apple Podcasts e cerut');
    /* urmărirea rămâne exact ca pe site */
    const ub = await urmPagina(page), cb = cereriUrm(jurnal);
    verifica(ub.gtag === 'function' && ub.fbq === 'function' && ub.dataLayer && ub.scripturi >= 12 && ub.bara && ub.stiluri === 2, 'browser (martor): GA, pixelul Meta, Clarity, Complianz în pagină (' + JSON.stringify(ub) + ')');
    for (const x of ['connect.facebook.net/en_US/fbevents.js', '/complianz-gdpr/cookiebanner/js/complianz.min.js', '/sourcebuster/sourcebuster.min.js', '/order-attribution.min.js', 'facebook-for-woocommerce-signals.js']) verifica(cb.some((u) => u.includes(x)), 'browser (martor): se cere ' + x);
    await poza(page, 'browser-10-podcast-episod');
    await page.goto(SITE + '/sala-de-curs/?comanda=14770&cheie=wc_order_r'); await asteapta(500);
    verifica(jurnal.some((x) => x.extern && /^https:\/\/player\.vimeo\.com\/video\/1197728140\?h=cff7aa9195&title=0$/.test(x.u)), 'browser (martor): iframe-ul Vimeo al sălii, neschimbat (fără dnt)');
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
    /* un token APNs brut (iPhone fără Firebase Messaging) nu ajunge la server: serverul trimite doar prin FCM */
    await page.evaluate(() => window.__emit('PushNotifications.registration', { value: 'ab'.repeat(32) })); await asteapta(400);
    egal(jurnal.filter((x) => x.m === 'POST' && x.u.endsWith('/wp-json/ah-spa/v1/push')).length, 1, 'notificări: tokenul APNs (64 hexa) nu se trimite la server');
    /* fără analiză și urmărire în aplicație */
    const ua1 = await urmPagina(page);
    egal(ua1, { gtag: 'undefined', fbq: 'undefined', dataLayer: false, clarity: 'undefined', complianz: 'undefined', scripturi: 0, bara: false, stiluri: 0 }, 'aplicație: fără GA, pixel Meta, Clarity, Complianz (scripturi, stiluri, bară)');
    egal(cereriUrm(jurnal), [], 'aplicație: nicio cerere spre Google Analytics, Meta, Clarity, atribuire sau Complianz');
    verifica(await page.evaluate(() => !!document.querySelector('#ahsp') && getComputedStyle(document.querySelector('#ahsp')).visibility === 'visible' && !document.documentElement.classList.contains('ahspa-refac')), 'aplicație: Spațiul meu vizibil (fără refacere de ciclu, datele erau în pagină)');
    const p1 = await prefs(page);
    egal(JSON.parse(p1.ah_push_intrebat || '0'), 1, 'notificări: întrebarea nu se mai repetă');
    const ac = JSON.parse(p1.ah_ciclu || 'null');
    egal(ac && [ac.starturi, ac.lung, ac.men, ac.sit], [CICLU.starturi, 28, 5, 'natural'], 'Preferences ah_ciclu: datele pentru pagina offline');
    egal(ac && ac.cine, await page.evaluate(() => window.AH_SPA.cine), 'Preferences ah_ciclu: amprenta contului (pentru refacere)');
    verifica(ac && !isNaN(Date.parse(ac.actualizat)), 'Preferences ah_ciclu: actualizat');
    const sch = a1.filter((x) => x[0] === 'LocalNotifications' && x[1] === 'schedule').pop();
    const prog = sch ? sch[2].notifications.map((n) => ziOra(n.schedule.at) + ' ' + n.extra.faza).sort() : [];
    egal(prog, asteptate(), 'mementourile ciclului: exact începuturile fazelor din Ciclul meu (235), pe 3 cicluri, la 9:00');
    verifica(sch && sch[2].notifications.every((n, i) => n.id === 41000 + i && n.extra.url === SITE + '/spatiul-meu/#ciclu' && n.schedule.allowWhileIdle), 'mementourile: id-uri 41000+, deschid #ciclu');
    verifica(sch && sch[2].notifications.every((n) => n.title === 'Ciclul meu' && n.body === 'Începe o fază nouă. Vezi ce îți aduce.' && !/menstrua|iarna|vara|toamna|primăvara|ovula|luteal|folicular/i.test(n.title + n.body)), 'mementourile: text neutru pe ecranul blocat');
    egal(JSON.parse(p1.ah_ciclu_notif || 'null'), 1, 'mementourile: „Da, anunță-mă” le pornește (ah_ciclu_notif = 1)');
    egal(await page.evaluate(() => document.querySelector('meta[name=viewport]').content.includes('viewport-fit=cover')), true, 'aplicație: viewport-fit=cover');
    verifica(await page.evaluate(() => !!document.getElementById('ahspa-js') && !!document.getElementById('ahspa-js').src && document.getElementById('ahspa-js').defer && !document.getElementById('ahspa-cfg').textContent.includes('window.AHApp=')), 'aplicație: puntea din fișierul cu versiune (defer), în pagină doar configurația');
    verifica(a1.some((x) => x[0] === 'SplashScreen' && x[1] === 'hide'), 'splash: ascuns de punte după prima desenare');
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
    const pod = await page.evaluate(() => ({ ifr: [...document.querySelectorAll('iframe')].filter((f) => /podcasts\.apple/.test(f.src) || f.classList.contains('pod-ifr')).length, a: [...document.querySelectorAll('#ahsp .spa-pod a')].map((a) => [a.textContent.trim(), a.getAttribute('href')]) }));
    egal(pod, { ifr: 0, a: [['Ascultă în Apple Podcasts', 'https://podcasts.apple.com/ro/podcast/x/id1486663684?i=1000456094034'], ['Ascultă pe Spotify', 'https://open.spotify.com/show/36dsYnxW0VgGyrN9bDFKo2']] }, 'podcast pe iPhone: fără playerul Apple; Apple Podcasts (episodul) și Spotify');
    verifica(!jurnal.some((x) => /podcasts\.apple\.com/.test(x.u)), 'podcast: nicio cerere spre embed.podcasts.apple.com');
    verifica(!(await page.evaluate(() => window.AHApp.blocat('https://podcasts.apple.com/ro/podcast/x/id1486663684?i=1'))), 'podcast: linkul Apple Podcasts nu e blocat (iese din aplicație)');
    await poza(page, 'aplicatie-15-podcast-episod');
    await page.click('#ahsp .fo-x');
    /* Mai mult › Aplicația */
    await page.evaluate(() => { location.hash = '#mai'; }); await asteapta(900);
    egal(await page.evaluate(() => [...document.querySelectorAll('#ahsp .spa-mai .rand b')].map((b) => b.textContent)), ['Notificări', 'Mementourile ciclului', 'Meditații descărcate', 'Șterge contul'], 'Mai mult › Aplicația: rândurile punții');
    egal(await page.evaluate(() => [document.getElementById('spa-push').getAttribute('aria-checked'), document.getElementById('spa-ciclu').getAttribute('aria-checked')]), ['true', 'true'], 'comutatoarele: pornite');
    egal(await page.evaluate(() => document.querySelectorAll('#ahsp h2').length && [...document.querySelectorAll('#ahsp h2')].filter((h) => h.textContent === 'Aplicația').length), 1, 'o singură secțiune „Aplicația” (cu 266)');
    verifica(await page.evaluate(() => /Cursurile, cărțile și meditațiile de pe această adresă apar aici\./.test(document.body.innerText) && !/Toate comenzile/.test(document.body.innerText)), 'Mai mult › Datele mele: fără „comenzi”');
    egal(await page.evaluate(() => { const a = document.querySelector('#spa-ciclu path'), b = document.querySelector('#spx-seara path'); return !!a && (!b || a.getAttribute('d') !== b.getAttribute('d')); }), true, 'Mementourile ciclului: altă iconiță decât Aspect de seară');
    await poza(page, 'aplicatie-16-mai-mult-aplicatia');
    /* „Meditații descărcate”: o foaie a noastră (nu confirm()), cu „Renunț” */
    await page.click('#spa-dl-tot'); await asteapta(300);
    egal(await page.evaluate(() => { const f = document.querySelector('.ahspa-foaie .c'); return f && [f.getAttribute('aria-labelledby') === f.querySelector('h2').id, [...f.querySelectorAll('button')].map((b) => b.textContent)]; }), [true, ['Șterge', 'Renunț']], 'Meditații descărcate: foaie cu „Șterge” și „Renunț”, etichetată de titlu');
    await page.click('.ahspa-foaie [data-a="nu"]'); await asteapta(200);
    verifica(await page.locator('#spa-dl-tot').count() === 1 && !(await apeluri(page)).some((x) => x[0] === 'Filesystem' && x[1] === 'rmdir'), '„Renunț”: nimic șters');
    /* ștergerea contului */
    await page.click('#spa-sterge'); await asteapta(400);
    await poza(page, 'aplicatie-17-sterge-contul');
    verifica(await page.locator('.ahspa-foaie [data-a="da"]').isDisabled(), 'ștergerea: „Șterge contul” inactiv până la bifă');
    egal(await page.evaluate(() => { const l = document.querySelector('.ahspa-foaie label'), i = l.querySelector('input'); return [l.getBoundingClientRect().height >= 44, Math.round(i.getBoundingClientRect().width)]; }), [true, 24], 'ștergerea: bifa de 24 px, rândul de cel puțin 44 px');
    verifica(!jurnal.some((x) => x.u.endsWith('/sterge-cont')), 'ștergerea: fără bifă nu pleacă nimic');
    await page.check('#spa-st-ok');
    verifica(!(await page.locator('.ahspa-foaie [data-a="da"]').isDisabled()), 'ștergerea: după bifă, butonul merge');
    await page.click('.ahspa-foaie [data-a="da"]'); await asteapta(900);
    const st = jurnal.filter((x) => x.u.endsWith('/wp-json/ah-spa/v1/sterge-cont'));
    verifica(st.length === 1 && st[0].m === 'POST' && JSON.parse(st[0].corp).confirmare === true && st[0].h === '1', 'ștergerea: POST /sterge-cont cu confirmare');
    verifica(jurnal.some((x) => x.m === 'POST' && x.u.endsWith('/wp-json/ah-spa/v1/push/sterge') && x.h === '1'), 'ștergerea: tokenul push scos de pe server (POST /push/sterge)');
    verifica(!('ah_sterge' in await prefs(page)), 'ștergerea: semnul ah_sterge scos după curățenie');
    const p3 = await prefs(page), a3 = await apeluri(page);
    egal(Object.keys(p3).sort(), [], 'ștergerea: Preferences golite (ah_ciclu, ah_descarcate, ah_push…)');
    verifica(a3.some((x) => x[0] === 'Filesystem' && x[1] === 'deleteFile' && x[2].path === 'meditatii/501.mp3'), 'ștergerea: fișierul meditației șters de pe telefon');
    verifica(a3.some((x) => x[0] === 'LocalNotifications' && x[1] === 'cancel'), 'ștergerea: mementourile anulate');
    egal(await page.evaluate(() => [localStorage.getItem('ahsp_ciclu'), localStorage.getItem('ahsp_med_502')]), [null, null], 'ștergerea: datele ciclului de pe telefon');
    egal(await page.evaluate(() => [getComputedStyle(document.getElementById('ahsp')).visibility, getComputedStyle(document.querySelector('.ahspa-foaie')).visibility, (document.querySelector('.ahspa-foaie [data-a="inchide"]') || {}).textContent]), ['hidden', 'visible', 'Închide'], 'ștergerea: în spate nu se mai vede contul; butonul „Închide”');
    await poza(page, 'aplicatie-18-cont-sters');
    egal(erori, [], 'aplicație: fără erori JS');
    await ctx.close();
  }

  /* ===== 3. aplicația: linkurile care o deschid și ieșirea din cont ===== */
  {
    const jurnal = [], ctx = await browser.newContext({ ...ctxOpt, userAgent: UA_APP });
    const tok = 'fcmVechi:APA91b' + 'y'.repeat(120);
    await ruta(ctx, 'aplicatie', jurnal);
    await ctx.addInitScript(capacitor({ perm: 'granted', prefs: { ah_push_intrebat: '1', ah_push_on: '1', ah_push: JSON.stringify({ token: tok, cine: 'x', la: 0 }), ah_descarcate: JSON.stringify([{ id: 503, titlu: 'Somn liniștit', durata: 1800, fisier: 'meditatii/503.mp3', poza: '', dir: 'LIBRARY_NO_CLOUD', descarcat: '2026-11-17T10:00:00Z' }]) } }));
    await ctx.addInitScript(LOCAL);
    const page = await ctx.newPage(); const erori = []; page.on('pageerror', (e) => erori.push(e.message));
    await page.goto(SITE + '/spatiul-meu/#acasa'); await asteapta(1200);
    await page.evaluate(() => window.__emit('App.appUrlOpen', { url: 'https://www.adelaharu.ro/product/magia-ciclului-feminin/' })); await asteapta(400);
    egal(new URL(page.url()).pathname, '/spatiul-meu/', 'link spre produs (din afara aplicației): ignorat');
    await page.evaluate(() => window.__emit('App.appUrlOpen', { url: 'https://www.adelaharu.ro/spatiul-meu/?cont=YW5h.abc#ciclu' })); await page.waitForURL(/cont=YW5h/, { timeout: 4000 }).catch(() => {});
    verifica(/\/spatiul-meu\/\?cont=YW5h\.abc#ciclu$/.test(page.url()), 'linkul personal din email deschide contul în aplicație');
    await asteapta(900);
    /* /app/intra?cont= (linkul pentru cine n-are aplicația): direct în cont, fără drumul prin server */
    await page.evaluate(() => window.__emit('App.appUrlOpen', { url: 'https://www.adelaharu.ro/app/intra?cont=YW5h.def' })); await page.waitForURL(/cont=YW5h\.def/, { timeout: 4000 }).catch(() => {});
    verifica(/\/spatiul-meu\/\?cont=YW5h\.def$/.test(page.url()) && !jurnal.some((x) => /\/app\/intra/.test(x.u)), '/app/intra?cont= se deschide direct ca /spatiul-meu/?cont=');
    await asteapta(900);
    await page.evaluate(() => window.__emit('PushNotifications.pushNotificationActionPerformed', { notification: { data: { url: 'https://www.adelaharu.ro/spatiul-meu/#meditatii' } } })); await asteapta(700);
    egal(await page.evaluate(() => document.getElementById('ahsp-tit').textContent), 'Inner Temple', 'atingerea unei notificări deschide data.url');
    await page.evaluate(() => { location.hash = '#mai'; }); await asteapta(600);
    const n0 = jurnal.length;
    await page.click('#ahsp-iesi'); await asteapta(1500);
    const dupa = jurnal.slice(n0).map((x) => x.m + ' ' + new URL(x.u).pathname);
    const iDel = dupa.indexOf('POST /wp-json/ah-spa/v1/push/sterge'), iIesi = dupa.indexOf('POST /wp-json/ah-sp/v1/iesi');
    verifica(iDel > -1 && iIesi > iDel, 'ieșirea din cont: întâi tokenul push, apoi /ah-sp/v1/iesi (' + dupa.join(', ') + ')');
    const del = jurnal.slice(n0).find((x) => x.m === 'POST' && x.u.endsWith('/push/sterge')), post = jurnal.filter((x) => x.m === 'POST' && x.u.endsWith('/ah-spa/v1/push')).pop();
    verifica(post && JSON.parse(post.corp).token !== tok, 'tokenul nou al telefonului (altul decât cel ținut minte) ajunge din nou la server');
    egal(del && JSON.parse(del.corp).token, post && JSON.parse(post.corp).token, 'ieșirea: se scoate tokenul de acum al telefonului');
    const a4 = await apeluri(page);
    verifica(a4.some((x) => x[0] === 'Filesystem' && x[1] === 'deleteFile' && x[2].path === 'meditatii/503.mp3'), 'ieșirea: meditațiile descărcate se șterg');
    const p4 = await prefs(page);
    /* (harnașul redeschide pagina tot cu ea intrată, iar 266 își scrie din nou ahspx_viz: verificăm doar ce nu se rescrie singur) */
    egal(await page.evaluate(() => ['ahsp_ciclu', 'ahsp_partener', 'ahsp_med_502'].map((k) => localStorage.getItem(k))), [null, null, null], 'ieșirea cerută: și datele paginii (ciclul, partenerul, progresul) pleacă de pe telefon');
    verifica(!('ah_ciclu_notif' in p4) && !('ah_push_on' in p4) && !('ah_deschideri' in p4), 'ieșirea cerută: Preferences ale contului golite');
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
    const vim = jurnal.filter((x) => x.extern && /player\.vimeo\.com/.test(x.u)).map((x) => x.u);
    egal(vim, ['https://player.vimeo.com/video/1197728140?h=cff7aa9195&title=0&dnt=1'], 'sala: iframe-ul Vimeo pleacă din server cu dnt=1');
    await page.evaluate(() => { const f = document.createElement('iframe'); f.id = 'vim2'; f.src = 'https://player.vimeo.com/video/42?h=z'; document.getElementById('ahs').appendChild(f); }); await asteapta(400);
    egal(await page.evaluate(() => document.getElementById('vim2').getAttribute('src')), 'https://player.vimeo.com/video/42?h=z&dnt=1', 'sala: un iframe Vimeo adăugat mai târziu primește și el dnt=1');
    egal(cereriUrm(jurnal), [], 'sala în aplicație: nicio cerere de urmărire');
    const t = await page.evaluate(() => document.body.innerText);
    verifica(/Abonament activ până pe 18 decembrie 2026\s*$/m.test(t), 'sala: starea abonamentului rămâne („Abonament activ până pe …”), fără „Abonamentul meu”');
    verifica(/Tot de la tine: Wild Love/.test(t) && /Ziua 1 · Eliberarea/.test(t), 'sala: lecțiile și cursurile ei rămân („Tot de la tine:”)');
    verifica(!/practicile te așteaptă|Abonamentul îl oprești|Accesul s-a încheiat/.test(t) && !(await page.locator('.ahhb-lacat').count()), 'sala: fără „Revino” (HER BREATH) și fără cutia goală rămasă');
    egal(await page.evaluate(() => [...document.querySelectorAll('.ahs-cutie')].filter((c) => [...c.children].every((x) => /^H\d$/.test(x.tagName))).length), 0, 'sala: nicio cutie rămasă doar cu titlul');
    egal(await page.evaluate(() => [...document.querySelectorAll('#ahspc-bara a')].filter((a) => a.offsetParent).map((a) => a.textContent.trim())), ['Acasă', 'Ciclul', 'Cursuri', 'Inner Temple', 'Mai mult'], 'sala: aceeași bară de jos ca în Spațiul meu (fără Comenzi)');
    egal(await page.evaluate(() => { const a = document.querySelector('.ahspa-inapoi a'); return a && [a.textContent, a.getAttribute('href')]; }), ['Înapoi în Spațiul meu', SITE + '/spatiul-meu/'], 'sala: bara „Înapoi în Spațiul meu” sus');
    await page.evaluate(() => document.querySelector('audio').play().catch(() => {})); await asteapta(600);
    egal(await page.evaluate(() => navigator.mediaSession.metadata && navigator.mediaSession.metadata.artist), 'Adela Haru', 'sala: ecranul blocat și pentru sunetul lecției');
    await page.evaluate(() => { const f = document.getElementById('vim2'); if (f) f.remove(); });
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
    const desc = [{ id: 503, titlu: 'Somn liniștit', durata: 1800, fisier: 'meditatii/503.mp3', poza: '', dir: 'DATA', descarcat: '2026-11-17T10:00:00Z' }, { id: 504, titlu: 'Un dar pentru tine', durata: 420, fisier: 'meditatii/504.mp3', poza: 'meditatii/504.jpg', dir: 'DATA', descarcat: '2026-11-17T10:00:00Z' }];
    await ruta(ctx, 'aplicatie', jurnal); await ctx.addInitScript(capacitor({ platforma: 'android', push: false, perm: 'granted', lipsa: ['meditatii/504.mp3'], dlEsec: [501], prefs: { ah_deschideri: '3', ah_push_on: '1', ah_descarcate: JSON.stringify(desc) } })); await ctx.addInitScript(LOCAL);
    const page = await ctx.newPage(); const erori = []; page.on('pageerror', (e) => erori.push(e.message));
    await page.goto(SITE + '/spatiul-meu/#acasa'); await asteapta(3400);
    verifica(!(await page.locator('.ahspa-foaie').count()), 'Android fără Firebase: nu întreabă de notificări');
    await page.evaluate(() => { location.hash = '#mai'; }); await asteapta(900);
    egal(await page.evaluate(() => [...document.querySelectorAll('#ahsp .spa-mai .rand b')].map((b) => b.textContent)), ['Mementourile ciclului', 'Meditații descărcate', 'Șterge contul'], 'Android fără Firebase: fără comutatorul Notificări');
    /* butonul Înapoi: întâi foaia deschisă, apoi (fără istoric) aplicația trece în fundal */
    await page.click('#spa-sterge'); await asteapta(300);
    await page.evaluate(() => window.__emit('App.backButton', { canGoBack: true })); await asteapta(200);
    egal(await page.locator('.ahspa-foaie').count(), 0, 'Android, Înapoi: închide foaia deschisă');
    await page.evaluate(() => window.__emit('App.backButton', { canGoBack: false })); await asteapta(200);
    verifica((await apeluri(page)).some((x) => x[0] === 'App' && x[1] === 'minimizeApp'), 'Android, Înapoi la început: aplicația trece în fundal');
    await page.evaluate(() => window.AHApp.push.porneste()); await asteapta(300);
    await page.evaluate(() => { location.hash = '#meditatii'; }); await asteapta(900);
    egal(JSON.parse((await prefs(page)).ah_descarcate || '[]').map((r) => r.id), [503], 'descărcări: rândul cu fișierul lipsă scos din index');
    verifica((await apeluri(page)).some((x) => x[0] === 'Filesystem' && x[1] === 'deleteFile' && x[2].path === 'meditatii/504.jpg'), 'descărcări: și poza rândului lipsă');
    await page.click('.cov-grila [data-spa-desc="501"]'); await asteapta(900);
    egal(await page.evaluate(() => (document.querySelector('.cov-grila .spa-dl[data-dl="501"] .err') || {}).textContent), 'N-a mers descărcarea. Verifică internetul și încearcă din nou.', 'descărcare eșuată: textul în română, nu eroarea pluginului');
    verifica((await apeluri(page)).some((x) => x[0] === 'Filesystem' && x[1] === 'deleteFile' && x[2].path === 'meditatii/501.mp3'), 'descărcare eșuată: fișierul neterminat se șterge');
    verifica(!JSON.parse((await prefs(page)).ah_descarcate || '[]').some((r) => r.id === 501), 'descărcare eșuată: fără rând în index');
    egal(await page.evaluate(() => [...document.querySelectorAll('#ahsp .cov-inst')].map((b) => [b.getAttribute('aria-label'), b.textContent.trim(), Math.round(b.getBoundingClientRect().width)])[0]), ['Instrucțiuni pentru „Pântecul, centrul tău”', '', 44], 'Inner Temple: „Instrucțiuni” ca iconiță de 44 px, cu numele meditației');
    await poza(page, 'aplicatie-22-android-meditatii');
    await page.evaluate(() => { location.hash = '#podcast'; }); await asteapta(500); await page.click('[data-ep="0"]'); await asteapta(400);
    egal(await page.evaluate(() => [...document.querySelectorAll('#ahsp .spa-pod a')].map((a) => a.textContent.trim())), ['Ascultă pe Spotify'], 'podcast pe Android: doar Spotify');
    verifica(await page.evaluate(() => ![...document.querySelectorAll('#ahsp a')].some((a) => /podcasts\.apple\.com/.test(a.href) && a.offsetParent)), 'podcast pe Android: fără linkuri Apple Podcasts');
    await page.click('#ahsp .fo-x');
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
    const ch = a5.filter((x) => x[0] === 'LocalNotifications' && x[1] === 'createChannel').pop(), sc5 = a5.filter((x) => x[0] === 'LocalNotifications' && x[1] === 'schedule').pop();
    verifica(ch && ch[2].id === 'ah_ciclu' && ch[2].visibility === 0 && sc5 && sc5[2].notifications.every((n) => n.channelId === 'ah_ciclu'), 'Android: mementourile pe canalul „privat” (fără conținut pe ecranul blocat)');
    egal(erori, [], 'Android: fără erori JS');
    await ctx.close();
  }
  /* ===== 6. Ciclul: refacerea din Preferences când iOS golește localStorage (WKWebView, ITP) ===== */
  {
    const jurnal = [], ctx = await browser.newContext({ ...ctxOpt, userAgent: UA_APP });
    await ruta(ctx, 'aplicatie', jurnal); await ctx.addInitScript(capacitor({ perm: 'granted', prefs: { ah_push_intrebat: '1', ah_ciclu_notif: '1' } })); await ctx.addInitScript(LOCAL);
    const page = await ctx.newPage(); const erori = []; page.on('pageerror', (e) => erori.push(e.message));
    const pending = () => page.evaluate(() => JSON.parse(sessionStorage.getItem('harnas_pending') || '[]').length);
    const stare = () => page.evaluate(() => ({ ls: localStorage.getItem('ahsp_ciclu'), sterge: !!document.getElementById('ci-sterge'), vizibil: getComputedStyle(document.getElementById('ahsp')).visibility, refac: document.documentElement.classList.contains('ahspa-refac') }));
    await page.goto(SITE + '/spatiul-meu/#ciclu'); await asteapta(2600);
    const copie = JSON.parse((await prefs(page)).ah_ciclu || 'null');
    verifica(copie && copie.cine === await page.evaluate(() => window.AH_SPA.cine) && copie.starturi.join() === CICLU.starturi.join(), 'refacere: copia ah_ciclu are datele și amprenta contului');
    const n1 = await pending();
    verifica(n1 > 0, 'refacere: mementourile programate (' + n1 + ')');
    /* iOS golește stocarea paginii (ca „Clear” din Web Inspector): fără removeItem, deci fără o ștergere cerută */
    await page.evaluate(() => localStorage.clear());
    await page.reload(); await asteapta(2600);
    const s1 = await stare();
    egal(JSON.parse(s1.ls || 'null'), { starturi: CICLU.starturi, lung: 28, men: 5, sit: 'natural' }, 'refacere: localStorage.ahsp_ciclu pus înapoi din Preferences.ah_ciclu');
    verifica(s1.sterge && s1.vizibil === 'visible' && !s1.refac, 'refacere: Ciclul meu arată datele ei (nu formularul gol), pagina e vizibilă');
    verifica(!!JSON.parse((await prefs(page)).ah_ciclu || 'null'), 'refacere: copia din Preferences NU s-a șters');
    verifica(!(await apeluri(page)).some((x) => x[0] === 'Preferences' && x[1] === 'remove' && x[2].key === 'ah_ciclu'), 'refacere: nicio ștergere a lui ah_ciclu');
    egal(await pending(), n1, 'refacere: mementourile rămân programate');
    await poza(page, 'aplicatie-21-ciclu-refacut');
    /* „Șterge datele de pe acest telefon” (235): ștergere cerută ⇒ copia și mementourile pleacă pe loc */
    page.once('dialog', (dl) => dl.accept());
    await page.evaluate(() => { const b = document.getElementById('ci-sterge'), x = b && b.closest('details'); if (x) x.open = true; });
    await page.click('#ci-sterge'); await asteapta(300);
    verifica(!('ah_ciclu' in await prefs(page)), 'ștergere cerută: Preferences.ah_ciclu șters imediat');
    egal(await pending(), 0, 'ștergere cerută: mementourile anulate');
    await page.reload(); await asteapta(2600);
    const s2 = await stare();
    verifica(s2.ls === null && !s2.sterge && !s2.refac, 'ștergere cerută: după redeschidere ciclul nu revine');
    /* copia altui cont de pe același telefon: nu se reface și se șterge */
    await page.evaluate((c) => { const p = JSON.parse(sessionStorage.getItem('harnas_prefs') || '{}'); p.ah_ciclu = JSON.stringify({ ...c, cine: '0000000000000000' }); sessionStorage.setItem('harnas_prefs', JSON.stringify(p)); }, copie);
    await page.reload(); await asteapta(2600);
    const s3 = await stare();
    verifica(s3.ls === null && !s3.sterge && !('ah_ciclu' in await prefs(page)), 'refacere: copia altui cont nu se pune în pagină și se șterge');
    egal(erori, [], 'refacere: fără erori JS');
    await ctx.close();
  }
  /* ===== 5. Termeni și condiții în aplicație (din ecranul de înscriere și din bannerul de cookie-uri) ===== */
  {
    const jurnal = [], ctx = await browser.newContext({ ...ctxOpt, userAgent: UA_APP });
    await ruta(ctx, 'aplicatie', jurnal); await ctx.addInitScript(capacitor({ perm: 'granted', prefs: { ah_push_intrebat: '1' } }));
    const page = await ctx.newPage(); const erori = []; page.on('pageerror', (e) => erori.push(e.message));
    await page.goto(SITE + '/termeni-si-conditii/'); await asteapta(700);
    await nimicDeCumparat(page, 'Termeni și condiții');
    egal([(await urmPagina(page)).scripturi, (await urmPagina(page)).bara, cereriUrm(jurnal)], [0, false, []], 'Termeni în aplicație: fără urmărire și fără bara de cookie-uri');
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

  /* ===== 7. Contul pentru el pe același telefon: tokenul push e al unui singur cont (Preferences.ah_push.cont) ===== */
  {
    const EL = { el: true, api_el: SITE + '/wp-json/ah-spp/v1/', pagina_el: SITE + '/pentru-el/' };
    const tokEl = 'fcmEl:APA91b' + 'z'.repeat(120);
    /* a) ea e intrată, notificările ei pornite: tokenul telefonului (ținut de el) trece la ea */
    {
      const jurnal = [], ctx = await browser.newContext({ ...ctxOpt, userAgent: UA_APP });
      await ruta(ctx, 'aplicatie', jurnal, EL);
      await ctx.addInitScript(capacitor({ perm: 'granted', prefs: { ah_push_intrebat: '1', ah_push_on: '1', ah_push: JSON.stringify({ token: tokEl, cont: 'el', la: Date.now() }) } })); await ctx.addInitScript(LOCAL);
      const page = await ctx.newPage(); const erori = []; page.on('pageerror', (e) => erori.push(e.message));
      await page.goto(SITE + '/spatiul-meu/#acasa'); await asteapta(1500);
      const post = jurnal.filter((x) => x.m === 'POST' && x.u.endsWith('/ah-spa/v1/push')).pop();
      verifica(!!post, 'el+ea: pe telefonul ei, tokenul ajunge în contul ei (POST /ah-spa/v1/push)');
      const ap = JSON.parse((await prefs(page)).ah_push || 'null');
      egal(ap && [ap.cont, ap.token], ['ea', post && JSON.parse(post.corp).token], 'el+ea: Preferences.ah_push.cont = „ea”');
      egal(erori, [], 'el+ea: fără erori JS');
      await ctx.close();
    }
    /* b) ea iese din cont, dar tokenul telefonului e al lui (el e intrat): nu se atinge */
    {
      const jurnal = [], ctx = await browser.newContext({ ...ctxOpt, userAgent: UA_APP });
      await ruta(ctx, 'aplicatie', jurnal, EL);
      await ctx.addInitScript(capacitor({ perm: 'granted', prefs: { ah_push_intrebat: '1', ah_push_on: '0', ah_push: JSON.stringify({ token: tokEl, cont: 'el', la: Date.now() }) } })); await ctx.addInitScript(LOCAL);
      const page = await ctx.newPage(); const erori = []; page.on('pageerror', (e) => erori.push(e.message));
      await page.goto(SITE + '/spatiul-meu/#mai'); await asteapta(1300);
      const n0 = jurnal.length;
      await page.click('#ahsp-iesi'); await asteapta(1500);
      const dupa = jurnal.slice(n0).map((x) => x.m + ' ' + new URL(x.u).pathname);
      verifica(!dupa.some((x) => /\/push$/.test(x)), 'el+ea: ieșirea ei nu scoate tokenul lui (' + dupa.join(', ') + ')');
      verifica(dupa.indexOf('POST /wp-json/ah-sp/v1/iesi') > -1, 'el+ea: ieșirea ei merge mai departe');
      verifica(!(await apeluri(page)).some((x) => x[0] === 'PushNotifications' && x[1] === 'unregister'), 'el+ea: fără unregister (tokenul e al lui)');
      egal(JSON.parse((await prefs(page)).ah_push || 'null').cont, 'el', 'el+ea: Preferences.ah_push rămâne al lui');
      egal(erori, [], 'el+ea (ieșire): fără erori JS');
      await ctx.close();
    }
    /* c) pagină fără niciun cont (nici ea, nici el): tokenul rămas al lui pleacă de la /ah-spp/v1/push */
    {
      const jurnal = [], ctx = await browser.newContext({ ...ctxOpt, userAgent: UA_APP });
      await ruta(ctx, 'aplicatie', jurnal, { cont: false, cine: '', el: false, api_el: EL.api_el, pagina_el: EL.pagina_el });
      await ctx.addInitScript(capacitor({ perm: 'granted', prefs: { ah_push_intrebat: '1', ah_push: JSON.stringify({ token: tokEl, cont: 'el', la: 0 }) } }));
      const page = await ctx.newPage(); const erori = []; page.on('pageerror', (e) => erori.push(e.message));
      await page.goto(SITE + '/spatiul-meu/'); await asteapta(1500);
      const del = jurnal.filter((x) => /\/push(\/sterge)?$/.test(new URL(x.u).pathname) && x.m !== 'GET');
      egal(del.map((x) => [x.m, new URL(x.u).pathname, JSON.parse(x.corp).token]), [['POST', '/wp-json/ah-spp/v1/push/sterge', tokEl]], 'fără cont: tokenul lui rămas pe telefon e scos de la el (POST /push/sterge), nu de la ea');
      verifica(jurnal.some((x) => x.u.endsWith('/ah-spp/v1/push/sterge') && x.spp === '1'), 'fără cont: cu antetul lui X-AH-SPP');
      verifica(!('ah_push' in await prefs(page)), 'fără cont: Preferences.ah_push șters');
      verifica((await apeluri(page)).some((x) => x[0] === 'PushNotifications' && x[1] === 'unregister'), 'fără cont: unregister');
      egal(erori, [], 'fără cont: fără erori JS');
      await ctx.close();
    }
    /* d) el, pe o politică deschisă din pagina lui: „Înapoi la ciclul ei” */
    {
      const jurnal = [], ctx = await browser.newContext({ ...ctxOpt, userAgent: UA_APP });
      await ruta(ctx, 'aplicatie', jurnal, { cont: false, cine: '', el: true, api_el: EL.api_el, pagina_el: EL.pagina_el });
      await ctx.addInitScript(capacitor({ perm: 'granted', prefs: { ah_push_intrebat: '1' } }));
      const page = await ctx.newPage(); const erori = []; page.on('pageerror', (e) => erori.push(e.message));
      await page.goto(SITE + '/termeni-si-conditii/'); await asteapta(700);
      egal(await page.evaluate(() => { const a = document.querySelector('.ahspa-inapoi a'); return a && [a.textContent, a.getAttribute('href')]; }), ['Înapoi la ciclul ei', SITE + '/pentru-el/'], 'el: pe politici, bara duce înapoi la pagina lui');
      await page.click('.ahspa-inapoi a'); await page.waitForURL(/\/pentru-el\//, { timeout: 4000 }).catch(() => {});
      egal(new URL(page.url()).pathname, '/pentru-el/', 'el: „Înapoi la ciclul ei”');
      egal(erori, [], 'el pe politici: fără erori JS');
      await ctx.close();
    }
  }
  /* ===== 8. Alt cont pe același telefon: datele celei dinainte nu trec la cea de acum ===== */
  {
    const ALTA = 'aaaaaaaaaaaaaaaa';
    const prefsA = { ah_push_intrebat: '1', ah_push_on: '1', ah_ciclu_notif: '1', ah_deschideri: '7', ah_ciclu: JSON.stringify({ ...CICLU, cine: ALTA, actualizat: '2026-11-17T10:00:00Z' }), ah_descarcate: JSON.stringify([{ id: 503, titlu: 'Somn liniștit', durata: 1800, fisier: 'meditatii/503.mp3', poza: '', dir: 'LIBRARY_NO_CLOUD', descarcat: '2026-11-17T10:00:00Z' }]) };
    for (const [caz, cine] of [['alt cont', ALTA], ['prima pornire după actualizare', null]]) {
      const jurnal = [], ctx = await browser.newContext({ ...ctxOpt, userAgent: UA_APP });
      await ruta(ctx, 'aplicatie', jurnal);
      await ctx.addInitScript(capacitor({ perm: 'granted', prefs: caz === 'alt cont' ? prefsA : { ah_push_intrebat: '1', ah_push_on: '1', ah_ciclu_notif: '1' } }));
      await ctx.addInitScript(LOCAL);
      await ctx.addInitScript(`(() => { if (location.protocol !== 'https:' || sessionStorage.getItem('harnas_cine')) return; sessionStorage.setItem('harnas_cine', '1'); ${cine ? `localStorage.setItem('ahspa_cine', ${JSON.stringify(cine)}); sessionStorage.setItem('harnas_pending', JSON.stringify([{ id: 41000 }, { id: 41001 }, { id: 41002 }]));` : ''} })();`);
      const page = await ctx.newPage(); const erori = []; page.on('pageerror', (e) => erori.push(e.message));
      await page.goto(SITE + '/spatiul-meu/#ciclu'); await asteapta(2600);
      const eu = await page.evaluate(() => window.AH_SPA.cine), p8 = await prefs(page), a8 = await apeluri(page);
      const ls8 = await page.evaluate(() => ({ ciclu: localStorage.getItem('ahsp_ciclu'), partener: localStorage.getItem('ahsp_partener'), med: localStorage.getItem('ahsp_med_502'), viz: localStorage.getItem('ahspx_viz'), cine: localStorage.getItem('ahspa_cine'), sterge: !!document.getElementById('ci-sterge') }));
      if (caz === 'alt cont') {
        egal([ls8.ciclu, ls8.partener, ls8.med, ls8.viz, ls8.sterge], [null, null, null, null, false], 'alt cont: ciclul, partenerul și progresul celei dinainte nu ajung la ea (Ciclul meu gol)');
        egal(ls8.cine, eu, 'alt cont: amprenta contului de acum ținută minte');
        verifica(!('ah_ciclu' in p8) && !('ah_ciclu_notif' in p8) && !('ah_push_on' in p8) && JSON.parse(p8.ah_deschideri || '0') === 1 && !('ah_descarcate' in p8), 'alt cont: Preferences ale celei dinainte golite, deschiderile numărate de la ea (' + JSON.stringify(p8) + ')');
        egal(await page.evaluate(() => JSON.parse(sessionStorage.getItem('harnas_pending') || '[]').length), 0, 'alt cont: mementourile celei dinainte anulate');
        verifica(!a8.some((x) => x[0] === 'LocalNotifications' && x[1] === 'schedule'), 'alt cont: niciun memento programat cu datele celei dinainte');
        verifica(a8.some((x) => x[0] === 'Filesystem' && x[1] === 'deleteFile' && x[2].path === 'meditatii/503.mp3'), 'alt cont: descărcările celei dinainte șterse');
        verifica(!jurnal.some((x) => x.m === 'POST' && /ciclu-date/.test(x.u) && (x.corp || '').includes(CICLU.starturi[0])), 'alt cont: nimic din ciclul celei dinainte urcat în contul ei');
        await poza(page, 'aplicatie-23-alt-cont-ciclu-gol');
      } else {
        egal([ls8.cine, !!ls8.ciclu, ls8.sterge], [eu, true, true], 'prima pornire după actualizare: doar notăm contul, datele ei rămân');
        verifica(a8.some((x) => x[0] === 'LocalNotifications' && x[1] === 'schedule'), 'prima pornire după actualizare: mementourile ei rămân');
      }
      egal(erori, [], caz + ': fără erori JS');
      await ctx.close();
    }
  }
  /* ===== 9. Android ≤ 12: permisiunea e „granted” fără întrebare; nimic nu pornește până nu spune ea „da” ===== */
  {
    const jurnal = [], ctx = await browser.newContext({ ...ctxOpt, userAgent: UA_ANDROID });
    await ruta(ctx, 'aplicatie', jurnal); await ctx.addInitScript(capacitor({ platforma: 'android', perm: 'granted', prefs: { ah_deschideri: '1' } })); await ctx.addInitScript(LOCAL);
    const page = await ctx.newPage(); const erori = []; page.on('pageerror', (e) => erori.push(e.message));
    await page.goto(SITE + '/spatiul-meu/#acasa'); await asteapta(3400);
    let a9 = await apeluri(page);
    verifica(!a9.some((x) => (x[0] === 'PushNotifications' && x[1] === 'register') || (x[0] === 'LocalNotifications' && x[1] === 'schedule')), 'Android ≤ 12: fără „da”, nici push, nici mementouri (deși permisiunea e „granted”)');
    verifica(await page.locator('.ahspa-foaie').isVisible(), 'Android ≤ 12: o întrebăm noi, cu explicația');
    await page.click('.ahspa-foaie [data-a="da"]'); await asteapta(1500);
    a9 = await apeluri(page);
    verifica(a9.some((x) => x[0] === 'PushNotifications' && x[1] === 'register') && a9.some((x) => x[0] === 'LocalNotifications' && x[1] === 'schedule'), 'Android ≤ 12: după „da”, push și mementouri');
    egal(erori, [], 'Android ≤ 12: fără erori JS');
    await ctx.close();
  }
  /* ===== 10. Stilurile temei: Spațiul meu în aplicație arată la fel fără ele (ah_spa_scoate_tema) ===== */
  {
    const ctx = await browser.newContext({ ...ctxOpt, userAgent: UA_APP });
    await ruta(ctx, 'aplicatie', []); await ctx.addInitScript(capacitor({ perm: 'granted', prefs: { ah_push_intrebat: '1' } })); await ctx.addInitScript(LOCAL);
    const page = await ctx.newPage();
    const diferente = [];
    for (const h of ['acasa', 'ciclu', 'meditatii', 'mai']) {
      const buf = [];
      for (const q of ['?harnas_tema=1', '']) { await page.goto(SITE + '/spatiul-meu/' + q + '#' + h); await asteapta(1300); await page.evaluate(() => { document.querySelectorAll('.ahspa-foaie').forEach((f) => f.remove()); }); buf.push((await page.screenshot({ fullPage: true })).toString('base64')); }
      const dif = await page.evaluate(async ([x, y]) => {
        const im = (b) => new Promise((r) => { const i = new Image(); i.onload = () => r(i); i.src = 'data:image/png;base64,' + b; });
        const [A, B] = [await im(x), await im(y)]; if (A.width !== B.width || A.height !== B.height) return 1 + A.height / 1e5 + B.height / 1e10;
        const c = document.createElement('canvas'); c.width = A.width; c.height = A.height; const g = c.getContext('2d');
        g.drawImage(A, 0, 0); const da = g.getImageData(0, 0, c.width, c.height).data; g.clearRect(0, 0, c.width, c.height); g.drawImage(B, 0, 0); const db = g.getImageData(0, 0, c.width, c.height).data;
        let n = 0; for (let i = 0; i < da.length; i += 4) if (Math.abs(da[i] - db[i]) + Math.abs(da[i + 1] - db[i + 1]) + Math.abs(da[i + 2] - db[i + 2]) > 24) n++;
        return n / (da.length / 4);
      }, buf);
      diferente.push(h + ' ' + (dif * 100).toFixed(3) + '%');
      if (process.env.AHSPA_TEMA_DIR) buf.forEach((b, i) => fs.writeFileSync(path.join(process.env.AHSPA_TEMA_DIR, h + (i ? '-fara' : '-tema') + '.png'), Buffer.from(b, 'base64')));
      verifica(dif < 0.002, `fără stilurile temei, #${h} arată la fel (diferență ${(dif * 100).toFixed(3)}% din pixeli)`);
    }
    console.log('   tema vs fără temă: ' + diferente.join(' · '));
    await ctx.close();
  }
  /* ===== 11. 2.3.0: amprenta contului din baza de date (sărurile schimbate nu înseamnă „alt cont”) ===== */
  {
    const VECHE = 'bbbbbbbbbbbbbbbb';
    const jurnal = [], ctx = await browser.newContext({ ...ctxOpt, userAgent: UA_APP });
    await ruta(ctx, 'aplicatie', jurnal, { cine_vechi: [VECHE] });
    const desc = [{ id: 503, titlu: 'Somn liniștit', durata: 1800, fisier: 'meditatii/503.mp3', poza: '', dir: 'LIBRARY_NO_CLOUD', descarcat: '2026-11-17T10:00:00Z' }];
    await ctx.addInitScript(capacitor({ perm: 'granted', prefs: { ah_push_intrebat: '1', ah_push_on: '1', ah_ciclu_notif: '1', ah_descarcate: JSON.stringify(desc), ah_ciclu: JSON.stringify({ ...CICLU, cine: VECHE, actualizat: '2026-11-17T10:00:00Z' }) } }));
    await ctx.addInitScript(LOCAL);
    await ctx.addInitScript(`(() => { if (location.protocol !== 'https:' || sessionStorage.getItem('harnas_cine')) return; sessionStorage.setItem('harnas_cine', '1'); localStorage.setItem('ahspa_cine', ${JSON.stringify(VECHE)}); })();`);
    const page = await ctx.newPage(); const erori = []; page.on('pageerror', (e) => erori.push(e.message));
    await page.goto(SITE + '/spatiul-meu/#ciclu'); await asteapta(2600);
    const eu = await page.evaluate(() => window.AH_SPA.cine);
    const ls = await page.evaluate(() => ({ ciclu: localStorage.getItem('ahsp_ciclu'), partener: localStorage.getItem('ahsp_partener'), cine: localStorage.getItem('ahspa_cine') }));
    verifica(eu !== VECHE && ls.cine === eu, 'săruri schimbate: amprenta nouă ținută minte (rescrisă peste cea veche)');
    verifica(!!ls.ciclu && !!ls.partener, 'săruri schimbate: ciclul și partenerul ei rămân pe telefon');
    const a = await apeluri(page), p = await prefs(page);
    verifica(!a.some((x) => x[0] === 'Filesystem' && x[1] === 'deleteFile'), 'săruri schimbate: descărcările rămân');
    verifica(JSON.parse(p.ah_ciclu || 'null') && JSON.parse(p.ah_ciclu).cine === eu, 'săruri schimbate: copia ah_ciclu rescrisă cu amprenta nouă');
    /* refacerea din copia cu amprenta veche (iOS a golit localStorage): tot a ei */
    await page.evaluate((v) => { localStorage.clear(); localStorage.setItem('ahspa_cine', v); const q = JSON.parse(sessionStorage.getItem('harnas_prefs')); q.ah_ciclu = JSON.stringify({ ...JSON.parse(q.ah_ciclu), cine: v }); sessionStorage.setItem('harnas_prefs', JSON.stringify(q)); }, VECHE);
    await page.reload(); await asteapta(2600);
    verifica(!!(await page.evaluate(() => localStorage.getItem('ahsp_ciclu'))), 'săruri schimbate: refacerea primește și copia cu amprenta veche');
    egal(erori, [], 'săruri schimbate: fără erori JS');
    await ctx.close();
  }
  /* ===== 12. pagină fără cont (cookie expirat): descărcările și copia ciclului rămân ===== */
  {
    const jurnal = [], ctx = await browser.newContext({ ...ctxOpt, userAgent: UA_APP });
    await ruta(ctx, 'aplicatie', jurnal, { cont: false, cine: '', cine_vechi: [] });
    const desc = [{ id: 503, titlu: 'Somn liniștit', durata: 1800, fisier: 'meditatii/503.mp3', poza: '', dir: 'LIBRARY_NO_CLOUD', descarcat: '2026-11-17T10:00:00Z' }];
    await ctx.addInitScript(capacitor({ perm: 'granted', prefs: { ah_push_intrebat: '1', ah_push_on: '1', ah_push: JSON.stringify({ token: 'fcmEa:APA91b' + 'q'.repeat(120), cont: 'ea', cine: 'x', la: 0 }), ah_descarcate: JSON.stringify(desc), ah_ciclu: JSON.stringify({ ...CICLU, cine: 'cccccccccccccccc' }) } }));
    const page = await ctx.newPage(); const erori = []; page.on('pageerror', (e) => erori.push(e.message));
    await page.goto(SITE + '/spatiul-meu/'); await asteapta(1600);
    const a = await apeluri(page), p = await prefs(page);
    verifica(!a.some((x) => (x[0] === 'Filesystem' && /delete|rmdir/.test(x[1])) || (x[0] === 'LocalNotifications' && x[1] === 'cancel')), 'fără cont: nimic șters de pe telefon (descărcări, mementouri)');
    verifica('ah_descarcate' in p && 'ah_ciclu' in p && 'ah_push' in p, 'fără cont: Preferences ah_descarcate, ah_ciclu și tokenul ei rămân');
    verifica(!jurnal.some((x) => /\/push(\/sterge)?$/.test(new URL(x.u).pathname) && x.m !== 'GET'), 'fără cont: tokenul ei nu se scoate de pe server');
    egal(erori, [], 'fără cont (păstrare): fără erori JS');
    await ctx.close();
  }
  /* ===== 13. „Șterge contul” întrerupt: Preferences.ah_sterge ⇒ curățenie la pornire ===== */
  {
    const jurnal = [], ctx = await browser.newContext({ ...ctxOpt, userAgent: UA_APP });
    await ruta(ctx, 'aplicatie', jurnal, { cont: false, cine: '' });
    const desc = [{ id: 503, titlu: 'Somn liniștit', durata: 1800, fisier: 'meditatii/503.mp3', poza: '', dir: 'LIBRARY_NO_CLOUD', descarcat: '2026-11-17T10:00:00Z' }];
    await ctx.addInitScript(capacitor({ perm: 'granted', prefs: { ah_sterge: JSON.stringify('dddddddddddddddd'), ah_push_intrebat: '1', ah_ciclu_notif: '1', ah_descarcate: JSON.stringify(desc), ah_ciclu: JSON.stringify({ ...CICLU, cine: 'dddddddddddddddd' }) } }));
    await ctx.addInitScript(LOCAL);
    const page = await ctx.newPage(); const erori = []; page.on('pageerror', (e) => erori.push(e.message));
    await page.goto(SITE + '/spatiul-meu/'); await asteapta(2200);
    const p = await prefs(page), a = await apeluri(page);
    egal(Object.keys(p).sort(), [], 'ștergere întreruptă: la pornire Preferences golite, inclusiv ah_sterge');
    verifica(a.some((x) => x[0] === 'Filesystem' && x[1] === 'deleteFile' && x[2].path === 'meditatii/503.mp3'), 'ștergere întreruptă: descărcările șterse');
    egal(await page.evaluate(() => [localStorage.getItem('ahsp_ciclu'), localStorage.getItem('ahsp_partener')]), [null, null], 'ștergere întreruptă: datele paginii șterse');
    egal(erori, [], 'ștergere întreruptă: fără erori JS');
    await ctx.close();
  }
  /* ===== 14. ecranul de intrare: codul (235 neschimbat, puntea îl face accesibil) ===== */
  {
    const jurnal = [], ctx = await browser.newContext({ ...ctxOpt, userAgent: UA_APP });
    await ruta(ctx, 'aplicatie', jurnal, { cont: false, cine: '' });
    let raspIntra = { status: 403, body: { ok: false, mesaj: 'Codul nu se potrivește. Verifică cifrele.' } };
    await ctx.route('**/wp-json/ah-sp/v1/intra', (r) => { jurnal.push({ m: 'POST', u: r.request().url(), corp: r.request().postData() }); return r.fulfill({ status: raspIntra.status, contentType: 'application/json', body: JSON.stringify(raspIntra.body) }); });
    await ctx.route('**/wp-json/ah-sp/v1/cod', (r) => { jurnal.push({ m: 'POST', u: r.request().url(), corp: r.request().postData() }); return r.fulfill({ status: 200, contentType: 'application/json', body: '{"ok":true}' }); });
    await ctx.addInitScript(capacitor({ perm: 'granted', prefs: { ah_push_intrebat: '1' } }));
    const page = await ctx.newPage(); const erori = []; page.on('pageerror', (e) => erori.push(e.message));
    await page.goto(SITE + '/spatiul-meu/?harnas_intrare=1'); await asteapta(900);
    /* emailul gol: câmpul marcat și cu focus */
    await page.click('#ahsp-b'); await asteapta(200);
    egal(await page.evaluate(() => [document.getElementById('ahsp-e').getAttribute('aria-invalid'), document.activeElement && document.activeElement.id]), ['true', 'ahsp-e'], 'intrare: emailul gol → aria-invalid și focus în câmp');
    await page.fill('#ahsp-e', 'ana@exemplu.ro'); await page.click('#ahsp-b'); await asteapta(500);
    const st = await page.evaluate(() => { const c = document.getElementById('ahsp-c'); return { max: c && c.getAttribute('maxlength'), nou: !!document.getElementById('spa-cod-nou'), alt: (document.getElementById('ahsp-alt') || {}).textContent, foc: document.activeElement && document.activeElement.id }; });
    egal(st, { max: null, nou: true, alt: 'Altă adresă', foc: 'ahsp-c' }, 'intrare: codul fără maxlength, „Trimite-mi un cod nou” separat de „Altă adresă”');
    await poza(page, 'aplicatie-24-intrare-cod');
    /* 5 cifre + Enter: nu pleacă nimic */
    const n0 = jurnal.filter((x) => /\/intra$/.test(x.u)).length;
    await page.type('#ahsp-c', '12345'); await page.press('#ahsp-c', 'Enter'); await asteapta(300);
    egal([jurnal.filter((x) => /\/intra$/.test(x.u)).length - n0, await page.evaluate(() => document.getElementById('ahsp-m').textContent), await page.evaluate(() => document.getElementById('ahsp-c').getAttribute('aria-invalid'))], [0, 'Codul are 6 cifre.', 'true'], 'intrare: un cod de 5 cifre nu pleacă („Codul are 6 cifre.”)');
    /* lipit cu spațiu: 6 cifre, pleacă o dată; greșit → marcat, selectat, cu focus */
    await page.fill('#ahsp-c', ''); await page.evaluate(() => { const c = document.getElementById('ahsp-c'); c.value = '123 456'; c.dispatchEvent(new Event('input', { bubbles: true })); }); await asteapta(700);
    const intra = jurnal.filter((x) => /\/intra$/.test(x.u));
    egal([intra.length - n0, intra.length && JSON.parse(intra[intra.length - 1].corp).cod], [1, '123456'], 'intrare: „123 456” lipit pleacă o singură dată, ca 123456');
    egal(await page.evaluate(() => [document.getElementById('ahsp-m').textContent, document.getElementById('ahsp-c').getAttribute('aria-invalid'), document.activeElement.id]), ['Codul nu se potrivește. Verifică cifrele.', 'true', 'ahsp-c'], 'intrare: cod greșit → aria-invalid, focus în câmp');
    /* serverul în lucru (5xx) */
    raspIntra = { status: 503, body: '<html>' };
    await page.fill('#ahsp-c', '654321'); await asteapta(700);
    egal(await page.evaluate(() => document.getElementById('ahsp-m').textContent), 'Nu am putut verifica codul acum. Mai încearcă puțin.', 'intrare: 5xx → „Nu am putut verifica codul acum”');
    /* prea multe încercări: câmp gol, focus pe „Trimite-mi un cod nou”, care cere un cod nou */
    raspIntra = { status: 429, body: { ok: false, mesaj: 'Prea multe încercări. Cere un cod nou.' } };
    await page.fill('#ahsp-c', '111222'); await asteapta(700);
    egal(await page.evaluate(() => [document.getElementById('ahsp-c').value, document.activeElement.id]), ['', 'spa-cod-nou'], 'intrare: 429 → câmpul golit, focus pe „Trimite-mi un cod nou”');
    await poza(page, 'aplicatie-25-intrare-prea-multe');
    await page.click('#spa-cod-nou'); await asteapta(400);
    const cod = jurnal.filter((x) => /\/ah-sp\/v1\/cod$/.test(x.u)).pop();
    egal([cod && JSON.parse(cod.corp).email, await page.evaluate(() => document.activeElement.id)], ['ana@exemplu.ro', 'ahsp-c'], 'intrare: „Trimite-mi un cod nou” cere codul pe aceeași adresă, focus înapoi în câmp');
    await page.click('#ahsp-alt'); await asteapta(300);
    egal(await page.evaluate(() => document.activeElement && document.activeElement.id), 'ahsp-e', 'intrare: „Altă adresă” → focus în câmpul adresei');
    const ax = await axe(page); egal(ax, [], 'intrare: axe fără probleme grave');
    egal(erori, [], 'intrare: fără erori JS');
    await ctx.close();
  }
  /* ===== 15. accesibilitate: axe (fără probleme serioase/critice), foile, titlul secțiunii, „Mai mult”, playerul ===== */
  {
    const jurnal = [], ctx = await browser.newContext({ ...ctxOpt, userAgent: UA_APP });
    await ruta(ctx, 'aplicatie', jurnal, { el: true, api_el: SITE + '/wp-json/ah-spp/v1/', pagina_el: SITE + '/pentru-el/' });
    const desc = [{ id: 503, titlu: 'Somn liniștit', durata: 1800, fisier: 'meditatii/503.mp3', poza: '', dir: 'LIBRARY_NO_CLOUD', descarcat: '2026-11-17T10:00:00Z' }];
    await ctx.addInitScript(capacitor({ perm: 'granted', prefs: { ah_push_intrebat: '1', ah_push_on: '1', ah_ciclu_notif: '1', ah_descarcate: JSON.stringify(desc) } })); await ctx.addInitScript(LOCAL);
    const page = await ctx.newPage(); const erori = []; page.on('pageerror', (e) => erori.push(e.message));
    await page.goto(SITE + '/spatiul-meu/#acasa'); await asteapta(1500);
    for (const h of ['acasa', 'ciclu', 'cursuri', 'meditatii', 'carti', 'abonamente', 'podcast', 'mai']) {
      await page.evaluate((x) => { location.hash = x; }, '#' + h); await asteapta(700);
      egal(await axe(page), [], 'axe #' + h + ': fără probleme serioase sau critice');
    }
    /* titlul secțiunii: <h1 tabindex=-1> cu focus la schimbare, titlul documentului */
    await page.evaluate(() => { location.hash = '#ciclu'; }); await asteapta(600);
    egal(await page.evaluate(() => { const t = document.getElementById('ahsp-tit'); return [t.tagName, t.getAttribute('tabindex'), document.activeElement === t, document.title]; }), ['H1', '-1', true, 'Ciclul meu · Spațiul meu'], 'secțiunea: <h1> cu focus și titlul documentului');
    verifica(await page.evaluate(() => { const e = document.querySelector('#ahsp-main .et.spa-dublu'); return !!e && getComputedStyle(e).display === 'none'; }), 'secțiunea: eticheta „Ciclul meu” care repetă titlul e ascunsă');
    /* Mai mult: butoane adevărate, rândul „Ciclul ei” */
    await page.evaluate(() => { location.hash = '#mai'; }); await asteapta(900);
    egal(await page.evaluate(() => ['spa-push', 'spa-ciclu', 'spa-dl-tot', 'spa-sterge'].map((id) => { const b = document.getElementById(id); return b && [b.tagName, b.type, b.getAttribute('role')]; })), [['BUTTON', 'button', 'switch'], ['BUTTON', 'button', 'switch'], ['BUTTON', 'button', null], ['BUTTON', 'button', null]], 'Mai mult: comutatoarele și rândurile sunt <button> (role=switch unde e cazul)');
    egal(await page.evaluate(() => { const a = document.getElementById('spa-el'); return a && [a.textContent.includes('Ciclul ei'), a.getAttribute('href')]; }), [true, SITE + '/pentru-el/'], 'Mai mult: „Ciclul ei” spre /pentru-el/ (ah_spa_are_acces_el)');
    verifica(await page.evaluate(() => /O meditație pe acest telefon\. Atinge ca s-o ștergi\./.test(document.getElementById('spa-dl-tot').textContent)), 'Mai mult: „Atinge ca s-o ștergi.” la o singură descărcare');
    await page.focus('#spa-ciclu'); await page.keyboard.press('Space'); await asteapta(400);
    egal(await page.evaluate(() => document.getElementById('spa-ciclu').getAttribute('aria-checked')), 'false', 'Mai mult: comutatorul merge și din tastatură (Spațiu)');
    await poza(page, 'aplicatie-26-mai-mult-butoane');
    /* foaia „Șterge contul”: focus prins, fundal inert, Escape închide, focusul se întoarce */
    await page.focus('#spa-sterge'); await page.keyboard.press('Enter'); await asteapta(400);
    const fo = await page.evaluate(() => ({ inert: [...document.querySelectorAll('#ahsp > :not(.ahspa-foaie)')].every((x) => x.hasAttribute('inert')), foc: !!document.activeElement.closest('.ahspa-foaie') }));
    egal(fo, { inert: true, foc: true }, 'foaia: fundalul inert, focusul în foaie');
    egal(await axe(page), [], 'axe foaia „Șterge contul”: fără probleme serioase sau critice');
    const iesit = [];
    for (let i = 0; i < 8; i++) { await page.keyboard.press('Tab'); iesit.push(await page.evaluate(() => !!document.activeElement.closest('.ahspa-foaie'))); }
    for (let i = 0; i < 4; i++) { await page.keyboard.press('Shift+Tab'); iesit.push(await page.evaluate(() => !!document.activeElement.closest('.ahspa-foaie'))); }
    verifica(iesit.every(Boolean), 'foaia: Tab și Shift+Tab rămân în foaie (' + iesit.join(',') + ')');
    const lipit = await page.evaluate(() => { const b = document.querySelector('.ahspa-foaie .b.lipit').getBoundingClientRect(); return b.bottom <= innerHeight + 1 && b.top < innerHeight; });
    verifica(lipit, 'foaia „Șterge contul”: butoanele se văd jos fără derulare');
    await poza(page, 'aplicatie-27-sterge-butoane-jos');
    await page.keyboard.press('Escape'); await asteapta(300);
    egal(await page.evaluate(() => [document.querySelectorAll('.ahspa-foaie').length, document.activeElement.id, [...document.querySelectorAll('#ahsp [inert]')].length]), [0, 'spa-sterge', 0], 'foaia: Escape închide, focusul revine pe „Șterge contul”, fundalul nu mai e inert');
    /* playerul: regiune cu nume, poziția ca timp, loc dedesubt */
    await page.evaluate(() => { location.hash = '#meditatii'; }); await asteapta(700);
    egal(await page.evaluate(() => (document.querySelector('[data-spa-desc]') || {}).getAttribute && document.querySelector('[data-spa-desc]').getAttribute('aria-label')), 'Descarcă „Pântecul, centrul tău” pe telefon', 'Descarcă: numele meditației în eticheta butonului');
    await page.click('.cov-grila [data-spa-desc="501"]'); await asteapta(150);
    verifica(/Respirația inimii/.test(await page.evaluate(() => (document.getElementById('ahspa-sr') || {}).textContent || '')), 'Descarcă: anunțat în regiunea role=status');
    await page.waitForSelector('.cov-grila .spa-dl[data-dl="501"] .gata', { timeout: 5000 }); await asteapta(200);
    egal(await page.evaluate(() => { const g = document.querySelector('.cov-grila .spa-dl[data-dl="501"] .gata'), b = document.querySelector('.cov-grila .spa-dl[data-dl="501"] [data-spa-sterge]'); return [Math.abs(g.getBoundingClientRect().top - b.getBoundingClientRect().top) < 14, b.getAttribute('aria-label'), (document.getElementById('ahspa-sr') || {}).textContent]; }), [true, 'Șterge descărcarea „Respirația inimii”', '„Respirația inimii” e descărcată pe telefon.'], 'Descărcat + Șterge pe un rând; anunțul de final');
    await page.click('[data-play="502"]'); await asteapta(1300);
    const pl = await page.evaluate(() => { const p = document.querySelector('#ahsp .player'), r = p && p.querySelector('input[type=range]'); return [p && p.getAttribute('role'), p && p.getAttribute('aria-label'), r && /^\d+:\d\d( din \d+:\d\d)?$/.test(r.getAttribute('aria-valuetext') || ''), document.documentElement.classList.contains('ahspa-cu-player')]; });
    egal(pl, ['region', 'Player', true, true], 'playerul: role=region „Player”, poziția ca „0:07 din 0:30”, loc dedesubt');
    egal(await axe(page), [], 'axe #meditatii cu playerul: fără probleme serioase sau critice');
    egal(erori, [], 'accesibilitate: fără erori JS');
    await ctx.close();
  }
  /* ===== 16. zoom 200% (ecran de 195 px) și modul întunecat al telefonului ===== */
  {
    const ctx = await browser.newContext({ ...ctxOpt, viewport: { width: 195, height: 422 }, userAgent: UA_APP, colorScheme: 'dark' });
    await ruta(ctx, 'aplicatie', []); await ctx.addInitScript(capacitor({ perm: 'granted', prefs: { ah_push_intrebat: '1' } })); await ctx.addInitScript(LOCAL);
    const page = await ctx.newPage(); const erori = []; page.on('pageerror', (e) => erori.push(e.message));
    await page.goto(SITE + '/spatiul-meu/#acasa'); await asteapta(1300);
    verifica(await page.evaluate(() => document.documentElement.classList.contains('ahsp-seara') && localStorage.getItem('ahspx_seara') === null), 'modul întunecat al telefonului: Spațiul meu de seară, fără să scrie alegerea');
    const lat = [];
    for (const h of ['acasa', 'ciclu', 'cursuri', 'meditatii', 'podcast', 'mai']) {
      await page.evaluate((x) => { location.hash = x; }, '#' + h); await asteapta(600);
      const w = await page.evaluate(() => document.documentElement.scrollWidth); if (w > 195) lat.push(h + ':' + w);
      if (h === 'acasa' || h === 'ciclu' || h === 'mai') await poza(page, 'aplicatie-28-zoom-' + h);
    }
    egal(lat, [], 'zoom 200%: nicio derulare în lateral la 195 px');
    egal(await page.evaluate(() => [...document.querySelectorAll('#ahsp .bara a')].filter((a) => a.offsetParent).map((a) => a.getAttribute('aria-label'))), ['Acasă', 'Ciclul', 'Cursuri', 'Inner Temple', 'Mai mult'], 'zoom 200%: bara păstrează numele (aria-label)');
    await page.evaluate(() => { location.hash = '#mai'; }); await asteapta(500);
    egal(await page.evaluate(() => { const w = document.getElementById('spx-seara'); return w ? w.getAttribute('aria-checked') : 'true'; }), 'true', 'modul întunecat: comutatorul „Aspect de seară” arată pornit');
    egal(erori, [], 'zoom / întunecat: fără erori JS');
    await ctx.close();
  }
  await browser.close();
  fs.rmSync(TMP, { recursive: true, force: true });
  console.log(`\n${nr} poze în docs/screenshots-v2. ${ok} verificări trecute, ${rau} picate.`);
  process.exit(rau ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(1); });
