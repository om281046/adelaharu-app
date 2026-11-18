/**
 * Harnașul vizual „Pentru el”. Rulare (din rădăcina proiectului): node server/tests/partener/poze.cjs
 *
 * construieste.php face paginile (Spațiul meu cu 235 + 266 + partea ei din ah-sp-partener.php; pagina lui tipărită de
 * fragment). Chromium (Playwright), 390×844, totul servit local ca https://www.adelaharu.ro (nimic nu pleacă pe internet,
 * nimic nu se scrie pe site); API-ul ah-spp/v1 și ciclu-date din 235 sunt imitate aici, cu starea ținută în memorie.
 * Pozele: docs/screenshots-v2/partener-*.png (doar acestea se șterg și se refac).
 * Verifică: textul exact al acordului, bifa obligatorie, acordul pentru ciclul în cont (prin butonul lui 235), lista,
 * oprirea, limita de 2, generatorul vechi ascuns, „Sunt partenerul ei”; la el: Azi/Învață/Setări, notificările în
 * aplicație, fără cărți și fără Google Fonts în aplicație, nicio notiță a ei în pagină. Iese cu cod 1 dacă ceva pică.
 */
'use strict';
process.env.TZ = 'Europe/Bucharest';
const fs = require('fs');
const os = require('os');
const path = require('path');
const { execFileSync } = require('child_process');
const { chromium } = require('playwright');

const RAD = path.join(__dirname, '..', '..', '..');
const OUT = path.join(RAD, 'docs', 'screenshots-v2');
const TMP = fs.mkdtempSync(path.join(process.env.AHSPA_TMP || os.tmpdir(), 'ahspp-viz-'));
const FONT = path.join(RAD, 'app', 'node_modules', '@fontsource');
fs.mkdirSync(OUT, { recursive: true });
for (const f of fs.readdirSync(OUT)) if (/^partener-.*\.png$/.test(f)) fs.unlinkSync(path.join(OUT, f));
execFileSync('php', [path.join(__dirname, 'construieste.php'), TMP], { stdio: 'inherit' });

const UA_SAFARI = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.1 Mobile/15E148 Safari/604.1';
const UA_APP = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 AdelaHaruApp/2.0.0 (ios)';
const SITE = 'https://www.adelaharu.ro';
let ok = 0, rau = 0;
const verifica = (c, nume) => { if (c) ok++; else { rau++; console.log('PICAT: ' + nume); } };
const egal = (a, b, nume) => verifica(JSON.stringify(a) === JSON.stringify(b), nume + (JSON.stringify(a) === JSON.stringify(b) ? '' : `\n   am:       ${JSON.stringify(a)}\n   așteptam: ${JSON.stringify(b)}`));
const asteapta = (ms) => new Promise((r) => setTimeout(r, ms));
const poza = async (page, nume, tot) => { await page.screenshot({ path: path.join(OUT, `partener-${nume}.png`), fullPage: !!tot }); };
const laVedere = async (page, sel) => { await page.evaluate((s) => { const e = document.querySelector(s); if (e) { e.scrollIntoView({ block: 'start' }); window.scrollBy(0, -84); } }, sel); await asteapta(250); };

const azi = new Date(); azi.setHours(12, 0, 0, 0);
const zi = (n) => { const d = new Date(azi); d.setDate(d.getDate() - n); return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); };
const CICLU = { starturi: [zi(8), zi(36), zi(64)], lung: 28, men: 5, sit: 'natural' };
const LOCAL = (extra) => `(() => { try { if (window.top !== window) return; } catch (e) { return; } if (sessionStorage.getItem('harnas_ls')) return; sessionStorage.setItem('harnas_ls', '1');
  localStorage.setItem('ahsp_ciclu', ${JSON.stringify(JSON.stringify(CICLU))});
  localStorage.setItem('ahspx_viz', JSON.stringify({ n: 1, zi: '' }));
  ${extra || ''}
})();`;
const VECHI = `localStorage.setItem('ahsp_partener', JSON.stringify({ k: 'k0123456789abcdef', s: ${JSON.stringify(zi(8))}, c: 28, m: 5, n: 'Ana', creat: ${JSON.stringify(zi(20))} }));`;

const FONTS = {
  '/fonts/fonts.css': ['figtree/400.css', 'figtree/500.css', 'figtree/600.css', 'gilda-display/400.css', 'cormorant-garamond/400-italic.css', 'cormorant-garamond/500-italic.css'],
  '/wp-content/uploads/elementor/google-fonts/css/figtree.css': ['figtree/400.css', 'figtree/500.css'],
  '/wp-content/uploads/elementor/google-fonts/css/gildadisplay.css': ['gilda-display/400.css'],
};
const fontCss = (l) => l.map((f) => `@import url("${SITE}/fonts/${f}");`).join('\n');

/* starea „serverului” pentru ea și pentru el */
const S = { ciclu: false, parteneri: [], cereri: [], el: 'ok', extern: [] };
async function ruta(ctx) {
  await ctx.route('**/*', async (route) => {
    const req = route.request(), u = new URL(req.url());
    const json = (o, s) => route.fulfill({ status: s || 200, contentType: 'application/json', body: JSON.stringify(o) });
    if (u.hostname === 'fonts.googleapis.com') { S.extern.push(req.url()); return route.fulfill({ status: 200, contentType: 'text/css', body: fontCss(['cormorant-garamond/400-italic.css']) }); }
    if (u.hostname !== 'www.adelaharu.ro') { S.extern.push(req.url()); return route.abort(); }
    const p = u.pathname;
    if (FONTS[p]) return route.fulfill({ status: 200, contentType: 'text/css', body: fontCss(FONTS[p]) });
    if (p.startsWith('/fonts/')) { const f = path.join(FONT, p.slice(7)); return fs.existsSync(f) ? route.fulfill({ status: 200, path: f, headers: { 'Access-Control-Allow-Origin': '*' } }) : route.fulfill({ status: 404, body: '' }); }
    if (p.startsWith('/wp-json/')) S.cereri.push({ m: req.method(), p, corp: req.postData() ? JSON.parse(req.postData()) : null });
    if (p === '/spatiul-meu/') return route.fulfill({ status: 200, contentType: 'text/html; charset=utf-8', body: fs.readFileSync(path.join(TMP, S.cont === false ? 'sp-intrare.html' : 'sp-ea.html')) });
    if (p === '/pentru-el/') return route.fulfill({ status: 200, contentType: 'text/html; charset=utf-8', body: fs.readFileSync(path.join(TMP, `el-${S.el}.html`)) });
    if (p === '/wp-json/ah-sp/v1/ciclu-date') { if (req.method() === 'POST') { S.ciclu = true; return json({ ok: true, actualizat: zi(0) + ' 10:00:00' }); } return json({ ok: true, acord: S.ciclu }); }
    if (p === '/wp-json/ah-spp/v1/parteneri') return json({ ok: true, ciclu: S.ciclu, max: 2, parteneri: S.parteneri });
    if (p === '/wp-json/ah-spp/v1/invita') { const b = JSON.parse(req.postData()); S.parteneri.push({ id: 10 + S.parteneri.length, nume: b.nume, email: b.email.toLowerCase(), stare: 'invitat', vizita: '', creat: zi(0) }); return json({ ok: true, email: true, parteneri: S.parteneri }); }
    if (p === '/wp-json/ah-spp/v1/opreste') { const b = JSON.parse(req.postData()); S.parteneri = S.parteneri.filter((x) => x.id !== b.id); return json({ ok: true, parteneri: S.parteneri }); }
    if (p === '/wp-json/ah-spp/v1/retrimite') return json({ ok: true, email: true, parteneri: S.parteneri });
    if (p === '/wp-json/ah-spp/v1/azi') return route.fulfill({ status: S.el === 'iesit' || S.el === 'link' ? 401 : 200, contentType: 'application/json', body: fs.readFileSync(path.join(TMP, `el-${S.el}.json`)) });
    if (p === '/wp-json/ah-spp/v1/trimite-link') return json({ ok: true, mesaj: 'Dacă adresa a fost invitată, primești în câteva minute un email cu un link nou. Uită-te și în Promoții sau Spam.' });
    if (p.startsWith('/wp-json/')) return json({ ok: true });
    return route.fulfill({ status: 404, contentType: 'text/html; charset=utf-8', body: '<h1>Nu e în harnaș: ' + p + '</h1>' });
  });
}
/* window.Capacitor imitat (pentru pagina lui în aplicație) */
const CAPACITOR = `(() => { if (window.top !== window) return; const asc = {}; window.__push = [];
  window.Capacitor = { getPlatform: () => 'ios', isNativePlatform: () => true, Plugins: {
    PushNotifications: { checkPermissions: async () => ({ receive: 'prompt' }), requestPermissions: async () => ({ receive: 'granted' }),
      register: async () => { setTimeout(() => (asc.registration || []).forEach((f) => f({ value: 'fcm-token-el-' + 'x'.repeat(40) })), 50); },
      addListener: (n, f) => { (asc[n] = asc[n] || []).push(f); window.__push.push(n); return Promise.resolve({ remove() {} }); } },
    App: { addListener: (n, f) => Promise.resolve({ remove() {} }) },
    AhApp: { info: async () => ({ push: true }) } } }; })();`;

/* window.Capacitor mai complet (Preferences, unregister, AhApp.info după build), pentru tokenul push al telefonului:
   Preferences.ah_push = {token, cont:'el'|'ea'} e aceeași cheie ca în puntea ei (ah-sp-aplicatie.php) */
const CAP2 = (o) => `(() => { if (window.top !== window) return; const asc = {}; const ss = (k, v) => v === undefined ? JSON.parse(sessionStorage.getItem(k) || 'null') : sessionStorage.setItem(k, JSON.stringify(v));
  if (!sessionStorage.getItem('h_init')) { sessionStorage.setItem('h_init', '1'); ss('h_prefs', ${JSON.stringify(o.prefs || {})}); ss('h_apel', []); ${o.ls ? `localStorage.setItem('spp_push', ${JSON.stringify(JSON.stringify(o.ls))});` : ''} }
  const rec = (n, m) => { const l = ss('h_apel'); l.push(n + '.' + m); ss('h_apel', l); };
  window.__emit = (n, d) => (asc[n] || []).forEach((f) => f(d));
  window.Capacitor = { getPlatform: () => 'android', isNativePlatform: () => true, Plugins: {
    PushNotifications: { checkPermissions: async () => ({ receive: 'granted' }), requestPermissions: async () => { rec('PushNotifications', 'requestPermissions'); return { receive: 'granted' }; },
      register: async () => { rec('PushNotifications', 'register'); setTimeout(() => window.__emit('registration', { value: 'fcm-token-el-' + 'y'.repeat(40) }), 50); },
      unregister: async () => { rec('PushNotifications', 'unregister'); },
      addListener: (n, f) => { (asc[n] = asc[n] || []).push(f); return Promise.resolve({ remove() {} }); } },
    Preferences: { get: async ({ key }) => ({ value: key in ss('h_prefs') ? ss('h_prefs')[key] : null }),
      set: async ({ key, value }) => { const p = ss('h_prefs'); p[key] = value; ss('h_prefs', p); },
      remove: async ({ key }) => { const p = ss('h_prefs'); delete p[key]; ss('h_prefs', p); rec('Preferences', 'remove:' + key); } },
    App: { addListener: (n, f) => Promise.resolve({ remove() {} }) },
    AhApp: { info: async () => (${JSON.stringify(o.info || { push: true, token: 'fcm' })}) } } }; })();`;
const hPrefs = (page) => page.evaluate(() => JSON.parse(sessionStorage.getItem('h_prefs') || '{}'));
const hApel = (page) => page.evaluate(() => JSON.parse(sessionStorage.getItem('h_apel') || '[]'));

(async () => {
  const browser = await chromium.launch();
  const ctxOpt = { viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true, locale: 'ro-RO', timezoneId: 'Europe/Bucharest' };

  /* ===== 1. ea: Ciclul meu, cardul „Pentru el” ===== */
  {
    const ctx = await browser.newContext({ ...ctxOpt, userAgent: UA_SAFARI });
    await ruta(ctx); await ctx.addInitScript(LOCAL(VECHI));
    const page = await ctx.newPage(); const erori = []; page.on('pageerror', (e) => erori.push(e.message)); page.on('dialog', (d) => d.accept());
    await page.goto(SITE + '/spatiul-meu/#ciclu'); await asteapta(1200);
    verifica(await page.locator('#spp-loc').isVisible(), 'ea: cardul „Pentru el” apare în Ciclul meu');
    verifica(await page.evaluate(() => { const s = document.getElementById('spp-loc'), set = document.getElementById('ci-setari'); return !!(s && set && (s.compareDocumentPosition(set) & Node.DOCUMENT_POSITION_FOLLOWING)); }), 'ea: cardul stă înaintea setărilor ciclului');
    verifica(await page.evaluate(() => { const l = document.getElementById('ci-pa-link'); return !!l && l.closest('.ci-bloc').offsetParent === null; }), 'ea: generatorul vechi de linkuri e ascuns (dar există)');
    verifica(await page.locator('[data-spp="sync"]').isVisible(), 'ea: ciclul nu e în cont → întâi acordul pentru asta');
    verifica(!(await page.locator('#spp-form').count()), 'ea: fără formular până nu e ciclul în cont');
    verifica(/link vechi „Pentru el”/.test(await page.locator('#spp-loc').innerText()), 'ea: linkul vechi e amintit, cu „Oprește linkul vechi”');
    await laVedere(page, '#spp-loc'); await poza(page, 'ea-1-ciclul-in-cont');
    S.cereri = [];
    await page.click('[data-spp="sync"]'); await asteapta(2600);
    verifica(S.cereri.some((c) => c.m === 'POST' && c.p === '/wp-json/ah-sp/v1/ciclu-date' && c.corp && c.corp.acord === 1 && c.corp.date && c.corp.date.ciclu), 'ea: acordul trece prin ciclu-date din 235 (acord:1, datele ei)');
    verifica(await page.locator('#spp-form').isVisible(), 'ea: după acord, formularul invitației');
    egal(await page.locator('#spp-acord-t').innerText(), 'Sunt de acord ca [prenume] să vadă faza mea de azi și datele estimate ale fazelor următoare. Pot opri oricând.', 'ea: textul acordului, înainte de prenume');
    await page.fill('#spp-nume', 'Mihai'); await page.fill('#spp-email', 'Mihai@exemplu.ro');
    egal(await page.locator('#spp-acord-t').innerText(), 'Sunt de acord ca Mihai să vadă faza mea de azi și datele estimate ale fazelor următoare. Pot opri oricând.', 'ea: textul exact al acordului, cu prenumele lui');
    egal(await page.inputValue('#spp-nume-ea'), 'Ana', 'ea: numele ei, din cont, de schimbat dacă vrea');
    S.cereri = [];
    await page.click('#spp-trimite'); await asteapta(300);
    egal(await page.locator('#spp-msg').innerText(), 'Bifează acordul, ca să putem trimite invitația.', 'ea: fără bifă nu pleacă nimic');
    verifica(!S.cereri.some((c) => c.p.endsWith('/invita')), 'ea: fără bifă, nicio cerere');
    await page.check('#spp-acord');
    await laVedere(page, '#spp-loc'); await poza(page, 'ea-2-invitatie');
    await page.click('#spp-trimite'); await asteapta(700);
    const inv = S.cereri.find((c) => c.p.endsWith('/invita'));
    egal(inv && inv.corp, { nume: 'Mihai', email: 'Mihai@exemplu.ro', nume_ea: 'Ana', acord: true }, 'ea: invitația trimite acordul explicit');
    verifica(/Gata\. Mihai primește acum emailul/.test(await page.locator('#spp-loc').innerText()), 'ea: confirmarea');
    verifica(/Invitat pe .* încă nu a intrat/.test(await page.locator('.spp-p').first().innerText()), 'ea: starea „invitat”');
    await laVedere(page, '#spp-loc'); await poza(page, 'ea-3-invitat');
    /* el a intrat, ea a invitat și pe Radu: limita de 2 */
    S.parteneri = [{ id: 10, nume: 'Mihai', email: 'mihai@exemplu.ro', stare: 'a_intrat', vizita: zi(0), creat: zi(3) }, { id: 11, nume: 'Radu', email: 'radu@exemplu.ro', stare: 'invitat', vizita: '', creat: zi(1) }];
    await page.reload(); await asteapta(1300);
    const txt = await page.locator('#spp-loc').innerText();
    verifica(/A intrat · ultima vizită:/.test(txt) && /Ai invitat 2 persoane, cât se poate/.test(txt), 'ea: „a intrat”, ultima vizită și limita de 2');
    verifica(!(await page.locator('#spp-form').count()), 'ea: la limită, fără formular');
    await laVedere(page, '#spp-loc'); await poza(page, 'ea-4-doi-parteneri');
    await page.locator('.spp-p[data-id="11"] [data-spp="opreste"]').click(); await asteapta(600);
    verifica(/Accesul lui Radu s-a oprit/.test(await page.locator('#spp-loc').innerText()) && (await page.locator('.spp-p').count()) === 1, 'ea: „Oprește accesul”, pe loc');
    verifica(await page.locator('[data-spp="form"]').isVisible(), 'ea: „Invită pe altcineva”');
    S.cereri = [];
    await page.locator('.spp-p[data-id="10"] [data-spp="retrimite"]').click(); await asteapta(500);
    verifica(S.cereri.some((c) => c.p.endsWith('/retrimite') && c.corp.id === 10), 'ea: „Trimite din nou invitația”');
    verifica(/cu un link nou\. Linkul vechi nu mai merge, iar telefoanele sau browserele pe care l-a deschis sunt deconectate/.test(await page.locator('#spp-loc').innerText()), 'ea: retrimiterea spune că linkul vechi nu mai merge');
    await laVedere(page, '#spp-loc'); await poza(page, 'ea-5-oprit-retrimis');
    /* aspect de seară (266): cardul se potrivește */
    await page.evaluate(() => document.documentElement.classList.add('ahsp-seara')); await asteapta(200);
    await laVedere(page, '#spp-loc'); await poza(page, 'ea-6-seara');
    await page.evaluate(() => document.documentElement.classList.remove('ahsp-seara'));
    /* situația „hormonal”, fără parteneri: cardul nu apare (ca generatorul vechi din 235) */
    S.parteneri = [];
    await page.evaluate(() => { const x = JSON.parse(localStorage.getItem('ahsp_ciclu')); x.sit = 'hormonal'; localStorage.setItem('ahsp_ciclu', JSON.stringify(x)); });
    await page.reload(); await asteapta(1200);
    verifica(!(await page.locator('#spp-loc').isVisible()), 'ea: contracepție hormonală și fără parteneri → cardul nu apare');
    egal(erori, [], 'ea: fără erori JS');
    await ctx.close();
  }

  /* ===== 2. ecranul de intrare: „Sunt partenerul ei” ===== */
  {
    S.cont = false;
    const ctx = await browser.newContext({ ...ctxOpt, userAgent: UA_APP });
    await ruta(ctx);
    const page = await ctx.newPage(); const erori = []; page.on('pageerror', (e) => erori.push(e.message));
    await page.goto(SITE + '/spatiul-meu/'); await asteapta(900);
    verifica(await page.locator('#spp-el').isVisible(), 'intrare: „Sunt partenerul ei”');
    await poza(page, 'ea-7-intrare');
    await page.click('#spp-el'); await asteapta(300);
    verifica(!(await page.locator('#ahsp-f').isVisible()) && await page.locator('#spp-cutie-el').isVisible(), 'intrare: panoul lui');
    const t = await page.locator('#spp-cutie-el').innerText();
    verifica(/Deschide linkul primit pe email/.test(t) && /Trimite-mi din nou linkul/.test(t), 'intrare: cele două drumuri');
    await page.fill('#spp-le', 'mihai@exemplu.ro'); await page.click('#spp-lb'); await asteapta(500);
    verifica(/Dacă adresa a fost invitată/.test(await page.locator('#spp-lm').innerText()), 'intrare: același răspuns, oricare ar fi adresa');
    await poza(page, 'ea-8-intrare-el');
    await page.click('#spp-inapoi'); await asteapta(200);
    verifica(await page.locator('#ahsp-f').isVisible(), 'intrare: înapoi la formularul ei');
    /* linkul lipit (recenzia Apple: linkul tastat în Safari nu deschide aplicația) */
    await page.click('#spp-el'); await asteapta(200);
    await page.fill('#spp-lpi', 'https://www.adelaharu.ro/pentru-el/?t=scurt'); await page.click('#spp-lpb'); await asteapta(200);
    verifica(/nu pare întreg/.test(await page.locator('#spp-lpm').innerText()) && /\/spatiul-meu\//.test(page.url()), 'intrare: link lipit greșit → mesaj, fără navigare');
    const TOK = 'A'.repeat(20) + '-_' + 'b'.repeat(21);
    await page.fill('#spp-lpi', ' Deschide pagina ei: https://www.adelaharu.ro/pentru-el/?t=' + TOK + ' ');
    await Promise.all([page.waitForURL(/\/pentru-el\//), page.click('#spp-lpb')]);
    egal(new URL(page.url()).search, '?t=' + TOK, 'intrare: linkul lipit deschide /pentru-el/?t= în aceeași pagină (în aplicație)');
    egal(erori, [], 'intrare: fără erori JS');
    await ctx.close();
    S.cont = true;
  }

  /* ===== 3. el, în browser ===== */
  {
    S.el = 'ok'; S.extern = [];
    const ctx = await browser.newContext({ ...ctxOpt, userAgent: UA_SAFARI });
    await ruta(ctx);
    const page = await ctx.newPage(); const erori = []; page.on('pageerror', (e) => erori.push(e.message)); page.on('dialog', (d) => d.accept());
    await page.goto(SITE + '/pentru-el/'); await asteapta(900);
    const t = await page.evaluate(() => document.body.innerText);
    verifica(/Azi, Ana e în/i.test(t) && /Primăvara ei interioară/.test(t) && /Fecioara · Luna în creștere/.test(t) && /până în jur de \d+ \S+/.test(t), 'el: Azi — primăvara, arhetipul, luna, până în jur de');
    verifica(!(await page.locator('svg.inel').count()) && !/Ziua \d|din 28/.test(t), 'el: fără inel și fără „Ziua N din L”');
    verifica(/Ce trăiește acum/.test(t) && /Cum o poți sprijini/.test(t) && /Ce le spune Adela femeilor, în Magia Ciclului Feminin/i.test(t), 'el: ce trăiește, sprijin, citatul');
    verifica(/Ce urmează/.test(t) && /Vara ei interioară · Mama, din jurul datei de \d+ \S+\./.test(t) && (await page.locator('.luni .r').count()) >= 3, 'el: „Ce urmează”, cu faza următoare și începuturile următoarelor săptămâni');
    verifica(/Fazele sunt o estimare: nu sunt o metodă de contracepție\./.test(t), 'el: nota „estimare, nu contracepție”, cu cuvintele de pe site');
    verifica(!/NOTITA|mihai@|ana@/.test(await page.content()), 'el: nicio notiță sau adresă în pagină');
    await poza(page, 'el-1-azi');
    await poza(page, 'el-1-azi-toata', true);
    await page.click('.bara a[href="#invata"]'); await asteapta(400);
    egal(await page.locator('.lista a').count(), 6, 'el: Învață — 6 pagini');
    verifica(await page.locator('a.spp-carte').first().isVisible(), 'el (browser): cărțile se văd (rândul paginii ?ciclul-ei)');
    await poza(page, 'el-2-invata', true);
    await page.click('.lista a[href="#invata/faze"]'); await asteapta(400);
    verifica(/Cele 4 faze ale ciclului feminin/.test(await page.evaluate(() => document.body.innerText)), 'el: pagina „Cele 4 faze”');
    await poza(page, 'el-3-invata-faze', true);
    await page.evaluate(() => { location.hash = '#invata/toamna'; }); await asteapta(400);
    const tt = await page.evaluate(() => document.body.innerText);
    verifica(/Toamna ei interioară/.test(tt) && /crește nivelul de intuiție/.test(tt) && /Cum o poți sprijini/.test(tt) && !/Zilele dinaintea menstruației/.test(tt), 'el: pagina „Toamna ei interioară”, doar din textele site-ului');
    await poza(page, 'el-3-invata-toamna', true);
    await page.evaluate(() => { location.hash = '#invata/podcast'; }); await asteapta(400);
    egal(await page.locator('.pod').count(), 5, 'el: podcastul — episoadele 2–6');
    await poza(page, 'el-4-invata-podcast', true);
    await page.click('#spp-set'); await asteapta(400);
    verifica(await page.locator('#spp-sw-email').isVisible() && !(await page.locator('#spp-sw-push').count()), 'el (browser): emailurile, fără notificări push');
    S.cereri = [];
    await page.click('#spp-sw-email'); await asteapta(300);
    verifica(S.cereri.some((c) => c.p.endsWith('/email') && c.corp.on === true), 'el: pornește emailurile fazelor');
    egal(await page.getAttribute('#spp-sw-email', 'aria-checked'), 'true', 'el: comutatorul pornit');
    await poza(page, 'el-5-setari');
    egal(erori, [], 'el (browser): fără erori JS');
    egal(S.extern.filter((u) => !u.startsWith('https://fonts.googleapis.com/')), [], 'el (browser): doar fonturile Cormorant în afara site-ului');
    await ctx.close();
  }

  /* ===== 4. el, în aplicație: notificările, fără cărți, fără Google ===== */
  {
    S.el = 'aplicatie'; S.extern = []; S.cereri = [];
    const ctx = await browser.newContext({ ...ctxOpt, userAgent: UA_APP });
    await ruta(ctx); await ctx.addInitScript(CAPACITOR);
    const page = await ctx.newPage(); const erori = []; page.on('pageerror', (e) => erori.push(e.message));
    await page.goto(SITE + '/pentru-el/'); await asteapta(900);
    verifica(await page.locator('#spp-anunt [data-a="push"]').isVisible(), 'aplicație: „Vrei să afli când începe o fază nouă?” (push)');
    await poza(page, 'el-6-aplicatie-azi');
    await page.click('#spp-anunt [data-a="push"]'); await asteapta(500);
    const pu = S.cereri.find((c) => c.p === '/wp-json/ah-spp/v1/push' && c.m === 'POST');
    egal(pu && pu.corp.platforma, 'ios', 'aplicație: tokenul FCM ajunge la /ah-spp/v1/push');
    verifica(/Primești notificările fazelor/.test(await page.locator('#spp-anunt').innerText()), 'aplicație: confirmarea');
    await page.evaluate(() => { location.hash = '#invata'; }); await asteapta(400);
    egal(await page.locator('.spp-carte').count(), 0, 'aplicație: fără linkuri spre cărți');
    await page.click('#spp-set'); await asteapta(300);
    verifica(await page.locator('#spp-sw-push').isVisible(), 'aplicație: comutatorul de notificări');
    await poza(page, 'el-7-aplicatie-setari');
    egal(S.extern, [], 'aplicație: nicio cerere în afara site-ului (fără Google Fonts)');
    egal(erori, [], 'aplicație: fără erori JS');
    await ctx.close();
  }

  /* ===== 4b. el, în aplicație: tokenul push e al lui (Preferences.ah_push.cont = 'el'), „Ieși” îl scoate doar pe al lui ===== */
  {
    S.el = 'aplicatie'; S.cereri = [];
    const ctx = await browser.newContext({ ...ctxOpt, userAgent: UA_APP.replace('(ios)', '(android)') });
    await ruta(ctx); await ctx.addInitScript(CAP2({}));
    const page = await ctx.newPage(); const erori = []; page.on('pageerror', (e) => erori.push(e.message)); page.on('dialog', (d) => d.accept());
    await page.goto(SITE + '/pentru-el/'); await asteapta(900);
    await page.click('#spp-anunt [data-a="push"]'); await asteapta(500);
    const pu = S.cereri.find((c) => c.p === '/wp-json/ah-spp/v1/push' && c.m === 'POST');
    egal(pu && [pu.corp.platforma, pu.corp.token.slice(0, 13)], ['android', 'fcm-token-el-'], 'aplicație: tokenul lui la /ah-spp/v1/push (nu la ea)');
    verifica(!S.cereri.some((c) => c.p.startsWith('/wp-json/ah-spa/')), 'aplicație: nicio cerere spre push-ul ei (ah-spa)');
    const ap = JSON.parse((await hPrefs(page)).ah_push || 'null');
    egal(ap && [ap.cont, ap.token], ['el', pu && pu.corp.token], 'aplicație: Preferences.ah_push = {cont:"el", token}');
    /* o atingere pe notificarea lui (data.url = pagina lui): rămâne pe Azi, reîncarcă faza */
    S.cereri = [];
    await page.evaluate(() => { location.hash = '#invata'; }); await asteapta(300);
    await page.evaluate(() => window.__emit('pushNotificationActionPerformed', { notification: { data: { url: 'https://www.adelaharu.ro/pentru-el/', tip: 'pentru-el' } } })); await asteapta(500);
    verifica(new URL(page.url()).pathname === '/pentru-el/' && S.cereri.some((c) => c.p === '/wp-json/ah-spp/v1/azi'), 'aplicație: atingerea notificării lui deschide Azi, fără reîncărcarea paginii');
    await page.click('#spp-set'); await asteapta(300);
    egal(await page.getAttribute('#spp-sw-push', 'aria-checked'), 'true', 'aplicație: comutatorul arată notificările de pe acest telefon');
    S.cereri = [];
    await page.click('[data-a="iesi"]'); await asteapta(900);
    const pasi = S.cereri.map((c) => c.m + ' ' + c.p);
    verifica(pasi.indexOf('DELETE /wp-json/ah-spp/v1/push') > -1 && pasi.indexOf('POST /wp-json/ah-spp/v1/iesi') > pasi.indexOf('DELETE /wp-json/ah-spp/v1/push'), '„Ieși”: întâi tokenul lui, apoi /iesi (' + pasi.join(', ') + ')');
    const del = S.cereri.find((c) => c.m === 'DELETE');
    egal(del && del.corp.token, pu && pu.corp.token, '„Ieși”: se scoate exact tokenul lui');
    verifica(!('ah_push' in await hPrefs(page)) && (await hApel(page)).includes('PushNotifications.unregister'), '„Ieși”: Preferences.ah_push șters, unregister');
    egal(erori, [], 'aplicație (push, ieși): fără erori JS');
    await ctx.close();
  }
  /* ===== 4c. tokenul telefonului a trecut între timp la ea: „Șterge accesul meu” nu-l atinge ===== */
  {
    S.el = 'aplicatie'; S.cereri = [];
    const tokEa = 'fcm-token-ea-' + 'q'.repeat(40);
    const ctx = await browser.newContext({ ...ctxOpt, userAgent: UA_APP });
    await ruta(ctx); await ctx.addInitScript(CAP2({ prefs: { ah_push: JSON.stringify({ token: tokEa, cont: 'ea', cine: 'x', la: Date.now() }) }, ls: tokEa }));
    const page = await ctx.newPage(); const erori = []; page.on('pageerror', (e) => erori.push(e.message)); page.on('dialog', (d) => d.accept());
    await page.goto(SITE + '/pentru-el/#setari'); await asteapta(900);
    egal(await page.getAttribute('#spp-sw-push', 'aria-checked'), 'false', 'telefonul ei: notificările lui apar oprite aici');
    await page.click('[data-a="sterge"]'); await asteapta(900);
    verifica(S.cereri.some((c) => c.p === '/wp-json/ah-spp/v1/sterge'), '„Șterge accesul meu”: cererea pleacă');
    verifica(!(await hApel(page)).includes('PushNotifications.unregister'), 'telefonul ei: fără unregister (i-ar opri ei notificările)');
    egal(JSON.parse((await hPrefs(page)).ah_push || 'null').cont, 'ea', 'telefonul ei: Preferences.ah_push rămâne al ei');
    verifica(!S.cereri.some((c) => c.p.startsWith('/wp-json/ah-spa/')), 'telefonul ei: nicio cerere spre push-ul ei');
    egal(erori, [], 'telefonul ei: fără erori JS');
    await ctx.close();
  }
  /* ===== 4d. fără notificări pentru el: build fără Firebase, iPhone cu token APNs, sau ea intrată pe telefon ===== */
  for (const [nume, info, pag] of [['fără Firebase', { push: false, token: 'fcm' }, 'aplicatie'], ['iPhone cu token APNs', { push: false, token: 'apns' }, 'aplicatie'], ['ea intrată pe telefon', { push: true, token: 'fcm' }, 'aplicatie-ea']]) {
    S.el = pag; S.cereri = [];
    const ctx = await browser.newContext({ ...ctxOpt, userAgent: UA_APP });
    await ruta(ctx); await ctx.addInitScript(CAP2({ info }));
    const page = await ctx.newPage(); const erori = []; page.on('pageerror', (e) => erori.push(e.message));
    await page.goto(SITE + '/pentru-el/'); await asteapta(900);
    egal(await page.getAttribute('#spp-anunt [data-a]', 'data-a'), 'email', nume + ': anunțul oferă emailul, nu notificări');
    await page.click('#spp-set'); await asteapta(300);
    egal(await page.locator('#spp-sw-push').count(), 0, nume + ': fără comutatorul de notificări');
    verifica(!(await hApel(page)).some((x) => /^PushNotifications\.(register|requestPermissions|unregister)/.test(x)), nume + ': nicio cerere de permisiune / register');
    egal(erori, [], nume + ': fără erori JS');
    if (pag === 'aplicatie-ea') await poza(page, 'el-12-aplicatie-telefonul-ei');
    await ctx.close();
  }

  /* ===== 5. el: pauză, oprit, fără cont, link expirat ===== */
  for (const [st, nume, re] of [['pauza', 'el-8-pauza', /Pentru moment, aici nu e nicio estimare\./], ['oprit', 'el-9-oprit', /Linkul a fost oprit[\s\S]*Linkul acesta nu mai e activ\./], ['iesit', 'el-10-fara-cont', /Deschide linkul primit pe email/], ['link', 'el-11-link-expirat', /Linkul acesta nu mai e valabil/]]) {
    S.el = st;
    const ctx = await browser.newContext({ ...ctxOpt, userAgent: UA_SAFARI });
    await ruta(ctx);
    const page = await ctx.newPage(); const erori = []; page.on('pageerror', (e) => erori.push(e.message));
    await page.goto(SITE + '/pentru-el/'); await asteapta(700);
    const t = await page.evaluate(() => document.body.innerText);
    verifica(re.test(t), 'el: ' + st);
    if (st === 'pauza') verifica(!/Primăvara|Iarna ei|Vara ei|Toamna ei|Ziua \d/.test(t) && !/hormonal|contracepție hormonală/i.test(await page.content()), 'el: pauza nu arată faze și nici situația ei');
    if (st === 'iesit') {
      await page.fill('#spp-lpi', 'nu e un link'); await page.click('#spp-lpb'); await asteapta(150);
      verifica(/nu pare întreg/.test(await page.locator('#spp-lpm').innerText()), 'el fără cont: câmpul pentru linkul lipit, cu mesaj la greșeală');
      await page.fill('#spp-lpi', ''); await page.evaluate(() => { document.getElementById('spp-lpm').textContent = ''; document.activeElement.blur(); });
    }
    await poza(page, nume);
    egal(erori, [], 'el ' + st + ': fără erori JS');
    await ctx.close();
  }

  await browser.close();
  fs.rmSync(TMP, { recursive: true, force: true });
  console.log(`${ok} verificări trecute, ${rau} picate. Pozele: docs/screenshots-v2/partener-*.png`);
  process.exit(rau ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(1); });
