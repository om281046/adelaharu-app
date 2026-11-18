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

/* axe-core (server/tests/partener/package.json): 0 încălcări „serious” sau „critical” pe ecranele lui și pe cardul ei */
const AXE = fs.readFileSync(require.resolve('axe-core/axe.min.js', { paths: [__dirname] }), 'utf8');
const axe = async (page, nume, include) => {
  await page.evaluate(AXE);
  const r = await page.evaluate(async (inc) => { const x = await window.axe.run(inc ? { include: [inc] } : document, { resultTypes: ['violations'] }); return x.violations.filter((v) => v.impact === 'serious' || v.impact === 'critical').map((v) => v.id + ' (' + v.impact + '): ' + v.nodes.slice(0, 3).map((n) => n.target.join(' ')).join(' | ')); }, include || null);
  egal(r, [], 'axe: ' + nume + ' — 0 serious/critical');
};
/* zoom 200% pe un telefon de 390 px = 195 px CSS: fără derulare orizontală */
const faraOrizontal = async (page, nume) => {
  const w = await page.evaluate(() => [document.documentElement.scrollWidth, document.documentElement.clientWidth, [...document.querySelectorAll('body *')].filter((e) => { const r = e.getBoundingClientRect(); return r.width && r.right > document.documentElement.clientWidth + 1 && getComputedStyle(e).position !== 'fixed' && !e.closest('[aria-hidden=true],[style*="-9999px"]'); }).slice(0, 3).map((e) => e.tagName + '.' + e.className)]);
  verifica(w[0] <= w[1] && !w[2].length, 'zoom 200% (195 px): ' + nume + ' — fără derulare orizontală (' + w[0] + '/' + w[1] + (w[2].length ? ', ies: ' + w[2].join(', ') : '') + ')');
};

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
const S = { ciclu: false, parteneri: [], cereri: [], el: 'ok', extern: [], esec: {} };
async function ruta(ctx) {
  await ctx.route('**/*', async (route) => {
    const req = route.request(), u = new URL(req.url());
    const json = (o, s) => route.fulfill({ status: s || 200, contentType: 'application/json', body: JSON.stringify(o) });
    if (u.hostname === 'fonts.googleapis.com') { S.extern.push(req.url()); return route.fulfill({ status: 200, contentType: 'text/css', body: fontCss(['cormorant-garamond/400-italic.css']) }); }
    if (u.hostname !== 'www.adelaharu.ro') { S.extern.push(req.url()); return route.abort(); }
    const p = u.pathname;
    if (FONTS[p]) return route.fulfill({ status: 200, contentType: 'text/css', body: fontCss(FONTS[p]) });
    if (p.startsWith('/fonts/')) { const f = path.join(FONT, p.slice(7)); return fs.existsSync(f) ? route.fulfill({ status: 200, path: f, headers: { 'Access-Control-Allow-Origin': '*' } }) : route.fulfill({ status: 404, body: '' }); }
    if (p.startsWith('/wp-json/')) S.cereri.push({ m: req.method(), p, corp: req.postData() ? JSON.parse(req.postData()) : null, antet: req.headers()['x-ah-spp'] || '' });
    if (p === '/spatiul-meu/') return route.fulfill({ status: 200, contentType: 'text/html; charset=utf-8', body: fs.readFileSync(path.join(TMP, S.cont === false ? 'sp-intrare.html' : 'sp-ea.html')) });
    if (p === '/pentru-el/' && S.navigari) S.navigari.push(u.search);
    if (p === '/pentru-el/') return route.fulfill({ status: 200, contentType: 'text/html; charset=utf-8', body: fs.readFileSync(path.join(TMP, `el-${S.el}.html`)) });
    if (p === '/wp-json/ah-sp/v1/ciclu-date' && req.method() === 'POST' && S.esec.ciclu) return route.abort('internetdisconnected');
    if (p === '/wp-json/ah-sp/v1/ciclu-date') { if (req.method() === 'POST') { S.ciclu = true; return json({ ok: true, actualizat: zi(0) + ' 10:00:00' }); } return json({ ok: true, acord: S.ciclu }); }
    if (p === '/wp-json/ah-spp/v1/parteneri') return json({ ok: true, ciclu: S.ciclu, max: 2, parteneri: S.parteneri });
    if (p === '/wp-json/ah-spp/v1/invita' && S.esec.invita) { await asteapta(400); return json({ ok: false, motiv: 'ocupat', mesaj: 'N-a mers. Mai încearcă o dată.' }, 503); }
    if (p === '/wp-json/ah-spp/v1/trimite-link' && S.esec.link) { await asteapta(400); return json({ ok: false, motiv: 'prea des', mesaj: 'Prea multe cereri. Mai încearcă peste o oră.' }, 429); }
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
    App: { addListener: (n, f) => { (asc['app:' + n] = asc['app:' + n] || []).push(f); return Promise.resolve({ remove() {} }); }, minimizeApp: async () => { rec('App', 'minimizeApp'); } },
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
    await axe(page, 'ea, cardul (ciclul nu e în cont)', '#spp-loc');
    /* acordul pentru ciclul în cont nu merge (fără internet): după 8 s, mesajul, butonul din nou activ și cu focus */
    S.esec.ciclu = true;
    await page.click('[data-spp="sync"]'); await asteapta(400);
    egal(await page.locator('#spp-msg').innerText(), 'Se salvează…', 'ea: „Se salvează…” cât așteaptă');
    await asteapta(8600);
    egal(await page.evaluate(() => [document.getElementById('spp-msg').textContent, document.querySelector('[data-spp="sync"]').disabled, document.activeElement && document.activeElement.getAttribute('data-spp')]), ['Nu am putut salva. Verifică internetul și mai încearcă.', false, 'sync'], 'ea: salvarea n-a mers → mesajul, butonul activ, cu focus (nu se resetează pe tăcute)');
    await laVedere(page, '#spp-loc'); await poza(page, 'ea-1b-nu-am-putut-salva');
    S.esec.ciclu = false;
    S.cereri = [];
    await page.click('[data-spp="sync"]'); await asteapta(2600);
    verifica(S.cereri.some((c) => c.m === 'POST' && c.p === '/wp-json/ah-sp/v1/ciclu-date' && c.corp && c.corp.acord === 1 && c.corp.date && c.corp.date.ciclu), 'ea: acordul trece prin ciclu-date din 235 (acord:1, datele ei)');
    verifica(await page.locator('#spp-form').isVisible(), 'ea: după acord, formularul invitației');
    egal(await page.locator('#spp-acord-t').innerText(), 'Sunt de acord ca [prenume] să vadă faza mea de azi și datele estimate ale fazelor următoare. Pot opri oricând.', 'ea: textul acordului, înainte de prenume');
    await page.fill('#spp-nume', 'Mihai'); await page.fill('#spp-email', 'Mihai@exemplu.ro');
    egal(await page.locator('#spp-acord-t').innerText(), 'Sunt de acord ca Mihai să vadă faza mea de azi și datele estimate ale fazelor următoare. Pot opri oricând.', 'ea: textul exact al acordului, cu prenumele lui');
    egal(await page.inputValue('#spp-nume-ea'), 'Ana', 'ea: numele ei, din cont, de schimbat dacă vrea');
    S.cereri = [];
    await page.evaluate(() => { document.getElementById('spp-msg').__acelasi = 1; });
    await page.click('#spp-trimite'); await asteapta(300);
    egal(await page.locator('#spp-msg').innerText(), 'Bifează acordul, ca să putem trimite invitația.', 'ea: fără bifă nu pleacă nimic');
    egal(await page.evaluate(() => [document.getElementById('spp-acord').getAttribute('aria-invalid'), document.activeElement && document.activeElement.id]), ['true', 'spp-acord'], 'ea: câmpul greșit: aria-invalid și focus');
    egal(await page.evaluate(() => { const m = document.getElementById('spp-msg'); return [m.getAttribute('role'), m.getAttribute('aria-live'), !!m.closest('.spp-a')]; }), ['status', 'polite', false], 'ea: un singur rând de mesaj, role=status, în afara părții redesenate');
    verifica(await page.evaluate(() => { const m = document.getElementById('spp-msg'), f = document.getElementById('spp-form'); return !!(f && (f.compareDocumentPosition(m) & Node.DOCUMENT_POSITION_FOLLOWING)); }), 'ea: mesajul stă sub formular');
    egal(await page.evaluate(() => { const l = ['spp-nume', 'spp-email', 'spp-nume-ea'].map((i) => document.getElementById(i)); return [l[0].getAttribute('autocapitalize'), l[1].getAttribute('autocapitalize'), l[1].getAttribute('autocorrect'), l[1].getAttribute('spellcheck'), l[2].getAttribute('autocapitalize')]; }), ['words', 'off', 'off', 'false', 'words'], 'ea: tastatura: prenumele cu majusculă, emailul fără corectare');
    verifica(!S.cereri.some((c) => c.p.endsWith('/invita')), 'ea: fără bifă, nicio cerere');
    await page.check('#spp-acord');
    await laVedere(page, '#spp-loc'); await poza(page, 'ea-2-invitatie');
    await axe(page, 'ea, formularul invitației', '#spp-loc');
    /* H1: adresa cu diacritice sau spații se oprește în pagină (nu pleacă spre o adresă „curățată”) */
    await page.fill('#spp-email', 'andrei.ștefan@gmail.com'); S.cereri = [];
    await page.click('#spp-trimite'); await asteapta(200);
    egal(await page.evaluate(() => [document.getElementById('spp-msg').textContent, document.activeElement.id, document.getElementById('spp-email').getAttribute('aria-invalid')]), ['Adresa conține caractere nepermise (diacritice sau spații).', 'spp-email', 'true'], 'ea: adresă cu diacritice → mesaj clar, focus pe câmp');
    await page.fill('#spp-email', 'mihai @exemplu.ro'); await page.click('#spp-trimite'); await asteapta(150);
    egal(await page.locator('#spp-msg').innerText(), 'Adresa conține caractere nepermise (diacritice sau spații).', 'ea: adresă cu spațiu → același mesaj');
    verifica(!S.cereri.some((c) => c.p.endsWith('/invita')), 'ea: nicio cerere cu adresa greșită');
    /* cererea nu merge (503): aria-busy cât așteaptă, apoi butonul din nou activ, cu focus */
    await page.fill('#spp-email', 'Mihai@exemplu.ro'); S.esec.invita = true;
    await page.click('#spp-trimite'); await asteapta(120);
    egal(await page.evaluate(() => [document.getElementById('spp-form').getAttribute('aria-busy'), document.getElementById('spp-trimite').disabled]), ['true', true], 'ea: cât pleacă invitația: aria-busy pe formular, butonul oprit');
    await asteapta(700);
    egal(await page.evaluate(() => [document.getElementById('spp-form').getAttribute('aria-busy'), document.getElementById('spp-trimite').disabled, document.activeElement.id, document.getElementById('spp-msg').textContent]), [null, false, 'spp-trimite', 'N-a mers. Mai încearcă o dată.'], 'ea: invitația n-a mers → butonul activ, cu focus, mesajul');
    S.esec.invita = false; S.cereri = [];
    await page.click('#spp-trimite'); await asteapta(700);
    const inv = S.cereri.find((c) => c.p.endsWith('/invita'));
    egal(inv && inv.corp, { nume: 'Mihai', email: 'Mihai@exemplu.ro', nume_ea: 'Ana', acord: true }, 'ea: invitația trimite acordul explicit');
    verifica(/Gata\. Mihai primește acum emailul/.test(await page.locator('#spp-loc').innerText()), 'ea: confirmarea');
    egal(await page.evaluate(() => [document.getElementById('spp-msg').__acelasi, document.getElementById('spp-msg').classList.contains('ok')]), [1, true], 'ea: același element de mesaj după redesenare; confirmarea în culoarea „ok”');
    verifica(inv && inv.antet === '1', 'ea: invitația trimite antetul X-AH-SPP');
    verifica(/Invitat pe .* încă nu a intrat/.test(await page.locator('.spp-p').first().innerText()), 'ea: starea „invitat”');
    await laVedere(page, '#spp-loc'); await poza(page, 'ea-3-invitat');
    await axe(page, 'ea, lista (invitat)', '#spp-loc');
    /* el a intrat, ea a invitat și pe Radu: limita de 2 */
    S.parteneri = [{ id: 10, nume: 'Mihai', email: 'mihai@exemplu.ro', stare: 'a_intrat', vizita: zi(0), creat: zi(3) }, { id: 11, nume: 'Radu', email: 'radu@exemplu.ro', stare: 'invitat', vizita: '', creat: zi(1) }];
    await page.reload(); await asteapta(1300);
    const txt = await page.locator('#spp-loc').innerText();
    verifica(/A intrat · ultima vizită:/.test(txt) && /Ai invitat 2 persoane, cât se poate/.test(txt), 'ea: „a intrat”, ultima vizită și limita de 2');
    verifica(!(await page.locator('#spp-form').count()), 'ea: la limită, fără formular');
    await laVedere(page, '#spp-loc'); await poza(page, 'ea-4-doi-parteneri');
    egal(await page.evaluate(() => [...document.querySelectorAll('.spp-p [data-spp="retrimite"]')].map((b) => b.textContent)), ['Trimite-i din nou linkul', 'Trimite din nou invitația'], 'ea: cine a intrat deja: „Trimite-i din nou linkul”; cine nu: „Trimite din nou invitația”');
    await axe(page, 'ea, doi parteneri', '#spp-loc');
    egal(await page.evaluate(() => { const st = document.querySelector('.spp-st'), i = st.querySelector('i'); return [getComputedStyle(st).alignItems, getComputedStyle(i).marginTop !== '0px']; }), ['flex-start', true], 'ea: punctul de stare aliniat la primul rând');
    const h44 = await page.evaluate(() => [...document.querySelectorAll('#spp-loc .legatura')].map((e) => Math.round(e.getBoundingClientRect().height)));
    verifica(h44.length >= 3 && h44.every((h) => h >= 44), 'ea: „Oprește accesul” și „Oprește linkul vechi”: cel puțin 44 px (' + h44.join(', ') + ')');
    S.cereri = [];
    await page.locator('.spp-p[data-id="11"] [data-spp="opreste"]').click(); await asteapta(300);
    egal(await page.evaluate(() => { const f = document.querySelector('.spp-foaie [role=dialog]'); return f ? [f.getAttribute('aria-modal'), document.getElementById(f.getAttribute('aria-labelledby')).textContent, document.activeElement.textContent] : null; }), ['true', 'Oprești accesul lui Radu?', 'Renunț'], 'ea: „Oprește accesul” deschide foaia (role=dialog, aria-labelledby), cu focusul pe „Renunț”');
    await poza(page, 'ea-4b-foaie-opreste');
    egal(await page.evaluate(() => { const c = document.querySelector('.spp-foaie .ci-but'), b = [...c.querySelectorAll('.btn')].map((x) => x.getBoundingClientRect()); return [b.length, b.every((r) => Math.abs(r.width - c.getBoundingClientRect().width) < 1), b[0].bottom <= b[1].top, b.every((r) => r.height < 60)]; }), [2, true, true, true], 'ea: foaia — butoanele unul sub altul, pe toată lățimea, fiecare pe un rând');
    await axe(page, 'ea, foaia „Oprești accesul”', '.spp-foaie');
    await page.click('.spp-foaie [data-f="nu"]'); await asteapta(200);
    verifica(!(await page.locator('.spp-foaie').count()) && !S.cereri.some((c) => c.p.endsWith('/opreste')) && (await page.locator('.spp-p').count()) === 2, 'ea: „Renunț” închide foaia, nimic oprit');
    await page.locator('.spp-p[data-id="11"] [data-spp="opreste"]').click(); await asteapta(250);
    await page.click('.spp-foaie [data-f="da"]'); await asteapta(600);
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
    egal(await page.evaluate(() => [document.getElementById('spp-lpb').classList.contains('lite'), document.getElementById('spp-lb').classList.contains('lite'), getComputedStyle(document.getElementById('spp-lm')).display]), [false, true, 'none'], 'intrare: „Deschide linkul” principal, „Trimite-mi linkul” secundar; mesajul gol nu ocupă loc');
    await axe(page, 'intrare, panoul lui', '#spp-cutie-el');
    await page.fill('#spp-le', 'andrei.ștefan@gmail.com'); await page.click('#spp-lb'); await asteapta(150);
    egal(await page.evaluate(() => [document.getElementById('spp-lm').textContent, document.activeElement.id]), ['Adresa conține caractere nepermise (diacritice sau spații).', 'spp-le'], 'intrare: „Trimite-mi linkul” cu diacritice → mesaj, focus pe câmp');
    S.esec.link = true;
    await page.fill('#spp-le', 'mihai@exemplu.ro'); await page.click('#spp-lb'); await asteapta(120);
    egal(await page.evaluate(() => [document.getElementById('spp-lf').getAttribute('aria-busy'), document.getElementById('spp-lb').disabled]), ['true', true], 'intrare: aria-busy cât așteaptă');
    await asteapta(700);
    egal(await page.evaluate(() => [document.getElementById('spp-lf').getAttribute('aria-busy'), document.getElementById('spp-lb').disabled, document.activeElement.id]), [null, false, 'spp-lb'], 'intrare: cererea n-a mers → butonul activ, cu focus');
    S.esec.link = false;
    await page.fill('#spp-le', 'mihai@exemplu.ro'); await page.click('#spp-lb'); await asteapta(500);
    verifica(/Dacă adresa a fost invitată/.test(await page.locator('#spp-lm').innerText()), 'intrare: același răspuns, oricare ar fi adresa');
    verifica(await page.evaluate(() => document.getElementById('spp-lm').classList.contains('ok')), 'intrare: răspunsul în culoarea „ok”');
    await poza(page, 'ea-8-intrare-el');
    await page.click('#spp-inapoi'); await asteapta(200);
    verifica(await page.locator('#ahsp-f').isVisible(), 'intrare: înapoi la formularul ei');
    /* linkul lipit (recenzia Apple: linkul tastat în Safari nu deschide aplicația) */
    await page.click('#spp-el'); await asteapta(200);
    await page.fill('#spp-lpi', 'https://www.adelaharu.ro/pentru-el/?t=scurt'); await page.click('#spp-lpb'); await asteapta(200);
    verifica(/nu pare întreg/.test(await page.locator('#spp-lpm').innerText()) && /\/spatiul-meu\//.test(page.url()), 'intrare: link lipit greșit → mesaj, fără navigare');
    const TOK = 'A'.repeat(20) + '-_' + 'b'.repeat(21);
    await page.fill('#spp-lpi', ' Deschide pagina ei: https://www.adelaharu.ro/pentru-el/?t=' + TOK + ' ');
    S.navigari = [];
    await Promise.all([page.waitForURL(/\/pentru-el\//), page.click('#spp-lpb')]);
    egal(S.navigari, ['?t=' + TOK], 'intrare: linkul lipit deschide /pentru-el/?t= în aceeași pagină (în aplicație)');
    await asteapta(300);
    egal(new URL(page.url()).search, '', 'el: adresa din bară se curăță (fără ?t=, nici în istoric)');
    S.navigari = null;
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
    verifica(/Ce urmează/.test(t) && /Vara ei interioară · Mama, din jurul datei de \d+ \S+\./.test(t) && (await page.locator('.luni .r').count()) === 2, 'el: „Ce urmează”, cu faza următoare și începuturile până la iarna următoare');
    verifica(/^(\d+–\d+ \S+|\d+ \S+ – \d+ \S+)$/.test(await page.locator('.luni .r').last().locator('span').last().innerText()) && /Iarna ei interioară/.test(await page.locator('.luni .r').last().innerText()), 'el: iarna următoare ca fereastră, nu o zi');
    verifica(/Primești un email la începutul fiecărei faze\./.test(await page.locator('#spp-anunt').innerText()), 'el (browser): „Da, anunță-mă” spune că vin emailuri');
    verifica(/Fazele sunt o estimare: nu sunt o metodă de contracepție\./.test(t), 'el: nota „estimare, nu contracepție”, cu cuvintele de pe site');
    verifica(!/NOTITA|mihai@|ana@/.test(await page.content()), 'el: nicio notiță sau adresă în pagină');
    await poza(page, 'el-1-azi');
    await poza(page, 'el-1-azi-toata', true);
    egal(await page.evaluate(() => [document.querySelectorAll('main').length, !!document.querySelector('main > .ecran'), document.querySelectorAll('ul.luni > li.r').length, document.title]), [1, true, 2, 'Azi · Ciclul ei · Adela Haru'], 'el: <main> în jurul ecranului, „Ce urmează” ca listă (ul/li), titlul paginii');
    await axe(page, 'el, Azi');
    const ecran = async (nume, h1, titlu) => {
      egal(await page.evaluate(() => [document.activeElement && document.activeElement.tagName, document.activeElement && document.activeElement.getAttribute('tabindex'), document.activeElement && document.activeElement.textContent, document.title, Math.round(window.scrollY)]), ['H1', '-1', h1, titlu + ' · Ciclul ei · Adela Haru', 0], 'el: ' + nume + ' — focus pe h1 (tabindex=-1), titlul documentului, sus');
    };
    await page.click('.bara a[href="#invata"]'); await asteapta(400);
    await ecran('Învață', 'Învață', 'Învață');
    egal(await page.locator('.lista a').count(), 6, 'el: Învață — 6 pagini');
    await axe(page, 'el, Învață');
    verifica(await page.locator('a.spp-carte').first().isVisible(), 'el (browser): cărțile se văd (rândul paginii ?ciclul-ei)');
    await poza(page, 'el-2-invata', true);
    await page.click('.lista a[href="#invata/faze"]'); await asteapta(400);
    await ecran('pagina „Cele 4 faze”', 'Cele 4 faze ale ciclului feminin', 'Cele 4 faze ale ciclului feminin');
    verifica(/Cele 4 faze ale ciclului feminin/.test(await page.evaluate(() => document.body.innerText)), 'el: pagina „Cele 4 faze”');
    await poza(page, 'el-3-invata-faze', true);
    await page.evaluate(() => { location.hash = '#invata/toamna'; }); await asteapta(400);
    const tt = await page.evaluate(() => document.body.innerText);
    verifica(/Toamna ei interioară/.test(tt) && /crește nivelul de intuiție/.test(tt) && /Cum o poți sprijini/.test(tt) && !/Zilele dinaintea menstruației/.test(tt), 'el: pagina „Toamna ei interioară”, doar din textele site-ului');
    await poza(page, 'el-3-invata-toamna', true);
    egal(await page.evaluate(() => [document.querySelectorAll('.pag h3').length, document.querySelectorAll('.pag section h2').length > 0, document.querySelector('.inapoi[data-a="inapoi-invata"]').getAttribute('aria-label')]), [0, true, 'Înapoi la Învață'], 'el: în paginile din Învață, titlurile secțiunilor sunt h2 (sub h1); „Înapoi la Învață”');
    await axe(page, 'el, Învață — Toamna');
    await page.evaluate(() => { location.hash = '#invata/podcast'; }); await asteapta(400);
    egal(await page.locator('.pod').count(), 5, 'el: podcastul — episoadele 2–6');
    verifica(await page.evaluate(() => [...document.querySelectorAll('.pod a')].every((a) => a.getAttribute('aria-label') === 'Ascultă „' + a.parentNode.querySelector('b').textContent + '” în Apple Podcasts')), 'el: podcast — fiecare „Ascultă episodul” spune ce episod (aria-label)');
    await axe(page, 'el, Învață — podcastul');
    await poza(page, 'el-4-invata-podcast', true);
    await page.click('#spp-set'); await asteapta(400);
    await ecran('Setări', 'Bună, Mihai', 'Setări');
    verifica(await page.locator('#spp-sw-email').isVisible() && !(await page.locator('#spp-sw-push').count()), 'el (browser): emailurile, fără notificări push');
    egal(await page.evaluate(() => { const c = document.querySelector('.but.col'), i = c.querySelector('[data-a="iesi"]').getBoundingClientRect(), d = c.querySelector('[data-a="sterge"]').getBoundingClientRect(); return [Math.abs(i.width - c.getBoundingClientRect().width) < 1, i.bottom <= d.top]; }), [true, true], 'el: Setări — „Ieși” pe toată lățimea, „Șterge accesul meu” dedesubt (la fel în browser și în aplicație)');
    await axe(page, 'el, Setări');
    S.cereri = [];
    egal(await page.locator('[data-a="iesi"]').innerText(), 'Ieși din cont', 'el (browser): „Ieși din cont” (fără „pe acest telefon”)');
    egal(await page.evaluate(() => getComputedStyle(document.getElementById('spp-set-m')).display), 'none', 'el: mesajul gol din setări nu ocupă loc');
    const h44 = await page.evaluate(() => [...document.querySelectorAll('.leg, .mic a, .inapoi')].filter((e) => e.offsetParent).map((e) => Math.round(e.getBoundingClientRect().height)));
    verifica(h44.length >= 2 && h44.every((h) => h >= 44), 'el: legăturile din setări, cel puțin 44 px (' + h44.join(', ') + ')');
    await page.click('#spp-sw-email'); await asteapta(300);
    verifica(S.cereri.some((c) => c.p.endsWith('/email') && c.corp.on === true && c.antet === '1'), 'el: pornește emailurile fazelor (cu antetul X-AH-SPP)');
    egal(await page.getAttribute('#spp-sw-email', 'aria-checked'), 'true', 'el: comutatorul pornit');
    await poza(page, 'el-5-setari');
    egal(erori, [], 'el (browser): fără erori JS');
    egal(S.extern.filter((u) => !u.startsWith('https://fonts.googleapis.com/')), [], 'el (browser): doar fonturile Cormorant în afara site-ului');
    /* foaia „Șterge accesul meu” (în loc de confirm) */
    S.cereri = [];
    await page.click('.leg[data-a="sterge"]'); await asteapta(250);
    verifica(await page.locator('.foaie [role=dialog][aria-labelledby="spp-foaie-t"]').isVisible(), 'el: „Șterge accesul meu” deschide foaia');
    await poza(page, 'el-5b-foaie-sterge');
    egal(await page.evaluate(() => { const c = document.querySelector('.foaie .but'), b = [...c.querySelectorAll('.btn')].map((x) => x.getBoundingClientRect()); return [Math.abs(b[0].width - c.getBoundingClientRect().width) < 1, b[0].bottom <= b[1].top, b.every((r) => r.height < 60)]; }), [true, true, true], 'el: foaia — „Șterge accesul meu” și „Renunț” unul sub altul, pe toată lățimea, fără rânduri rupte');
    await axe(page, 'el, foaia „Ștergi accesul”', '.foaie');
    await page.keyboard.press('Escape'); await asteapta(150);
    verifica(!(await page.locator('.foaie').count()) && !S.cereri.some((c) => c.p.endsWith('/sterge')), 'el: Escape închide foaia, nimic șters');
    /* telefonul culcat: bara rămâne jos; pe tabletă / laptop, sus */
    await page.setViewportSize({ width: 844, height: 390 }); await page.evaluate(() => { location.hash = '#azi'; }); await asteapta(300);
    egal(await page.evaluate(() => Math.round(document.querySelector('.bara').getBoundingClientRect().bottom) === window.innerHeight), true, 'el: telefon culcat (844×390): bara jos');
    await poza(page, 'el-15-culcat');
    await page.setViewportSize({ width: 1024, height: 768 }); await asteapta(200);
    egal(await page.evaluate(() => Math.round(document.querySelector('.bara').getBoundingClientRect().top)), 0, 'el: ecran mare (1024×768): bara sus');
    await ctx.close();
  }

  /* ===== 3b. el: seara (telefonul în modul întunecat, sau „Aspect de seară” ales în Spațiul meu) ===== */
  for (const [nume, opt, init] of [['telefonul', { colorScheme: 'dark' }, ''], ['Spațiul meu', { colorScheme: 'light' }, "localStorage.setItem('ahspx_seara','1');"]]) {
    S.el = 'ok';
    const ctx = await browser.newContext({ ...ctxOpt, ...opt, userAgent: UA_SAFARI });
    await ruta(ctx); if (init) await ctx.addInitScript(init);
    const page = await ctx.newPage(); const erori = []; page.on('pageerror', (e) => erori.push(e.message));
    await page.goto(SITE + '/pentru-el/'); await asteapta(900);
    const c = await page.evaluate(() => { const lum = (s) => { const m = s.match(/[\d.]+/g).map(Number); const f = (v) => { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); }; return 0.2126 * f(m[0]) + 0.7152 * f(m[1]) + 0.0722 * f(m[2]); };
      const r = (a, b) => { const x = lum(a), y = lum(b); return (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05); };
      const fond = getComputedStyle(document.documentElement).backgroundColor, p = document.querySelector('section p'), h = document.querySelector('.sus h1'), mic = document.querySelector('.nota');
      return { fond, text: r(getComputedStyle(p).color, fond), titlu: r(getComputedStyle(h).color, fond), mic: r(getComputedStyle(mic).color, fond) }; });
    verifica(c.fond === 'rgb(34, 25, 34)', 'seara (' + nume + '): fundalul închis (' + c.fond + ')');
    verifica(c.text >= 4.5 && c.mic >= 4.5 && c.titlu >= 3, `seara (${nume}): contrast AA (text ${c.text.toFixed(1)}, notă ${c.mic.toFixed(1)}, titlu ${c.titlu.toFixed(1)})`);
    if (!init) await axe(page, 'el, seara (Azi)');
    if (!init) { await poza(page, 'el-13-seara'); await page.evaluate(() => { location.hash = '#invata/toamna'; }); await asteapta(400); await poza(page, 'el-13-seara-invata'); }
    egal(erori, [], 'seara: fără erori JS');
    await ctx.close();
  }
  {
    const ctx = await browser.newContext({ ...ctxOpt, colorScheme: 'dark', userAgent: UA_SAFARI });
    await ruta(ctx); await ctx.addInitScript("localStorage.setItem('ahspx_seara','0');");
    const page = await ctx.newPage();
    await page.goto(SITE + '/pentru-el/'); await asteapta(700);
    egal(await page.evaluate(() => getComputedStyle(document.documentElement).backgroundColor), 'rgb(251, 246, 243)', 'seara oprită în Spațiul meu: pagina lui rămâne luminoasă, și cu telefonul pe întunecat');
    await ctx.close();
  }

  /* ===== 3c. el: toamna, cu iarna următoare ca fereastră ===== */
  {
    S.el = 'toamna';
    const ctx = await browser.newContext({ ...ctxOpt, userAgent: UA_SAFARI });
    await ruta(ctx);
    const page = await ctx.newPage(); const erori = []; page.on('pageerror', (e) => erori.push(e.message));
    await page.goto(SITE + '/pentru-el/'); await asteapta(900);
    const t = await page.evaluate(() => document.body.innerText);
    verifica(/Toamna ei interioară/.test(t) && /până la următoarea menstruație/.test(t) && !/până în jur de/.test(t), 'el, toamna: „până la următoarea menstruație”, fără dată');
    verifica(/Iarna ei interioară · \S+, între \d+( \S+)? și \d+ \S+\./.test(t), 'el, toamna: „Ce urmează”: iarna între două date');
    egal(await page.locator('.luni .r').count(), 0, 'el, toamna: fără alte începuturi (după iarnă depinde de ziua în care începe)');
    await poza(page, 'el-14-toamna-fereastra');
    egal(erori, [], 'el toamna: fără erori JS');
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
    await axe(page, 'el, aplicație (Azi, cu anunțul)');
    await page.click('#spp-anunt [data-a="push"]'); await asteapta(500);
    const pu = S.cereri.find((c) => c.p === '/wp-json/ah-spp/v1/push' && c.m === 'POST');
    egal(pu && pu.corp.platforma, 'ios', 'aplicație: tokenul FCM ajunge la /ah-spp/v1/push');
    verifica(/Primești notificările fazelor/.test(await page.locator('#spp-anunt').innerText()), 'aplicație: confirmarea');
    await page.evaluate(() => { location.hash = '#invata'; }); await asteapta(400);
    egal(await page.locator('.spp-carte').count(), 0, 'aplicație: fără linkuri spre cărți');
    await page.click('#spp-set'); await asteapta(300);
    verifica(await page.locator('#spp-sw-push').isVisible(), 'aplicație: comutatorul de notificări');
    egal(await page.locator('[data-a="iesi"]').innerText(), 'Ieși din cont pe acest telefon', 'aplicație: „Ieși din cont pe acest telefon”');
    await poza(page, 'el-7-aplicatie-setari');
    await axe(page, 'el, aplicație (Setări)');
    egal(await page.evaluate(() => { const c = document.querySelector('.but.col'), i = c.querySelector('[data-a="iesi"]').getBoundingClientRect(), d = c.querySelector('[data-a="sterge"]').getBoundingClientRect(); return [Math.abs(i.width - c.getBoundingClientRect().width) < 1, i.bottom <= d.top, i.height < 60]; }), [true, true, true], 'aplicație: Setări — „Ieși din cont pe acest telefon” pe un rând, „Șterge accesul meu” dedesubt (ca în browser)');
    egal(S.extern, [], 'aplicație: nicio cerere în afara site-ului (fără Google Fonts)');
    await page.evaluate(() => { location.hash = '#azi'; }); await asteapta(300);
    verifica(await page.evaluate(() => [...document.querySelectorAll('blockquote, .arh, .cap .marca small')].every((e) => !/Cormorant/.test(getComputedStyle(e).fontFamily) && /Figtree/.test(getComputedStyle(e).fontFamily) && getComputedStyle(e).fontStyle === 'italic')), 'aplicație: citatele în Figtree cursiv (fără Cormorant, care ar cădea pe Georgia)');
    verifica(await page.evaluate(() => /Gilda Display/.test(getComputedStyle(document.querySelector('h1')).fontFamily)), 'aplicație: titlurile în Gilda Display');
    egal(await page.locator('[data-a="iesi"]').count(), 0, 'aplicație: (Azi) fără butonul de ieșire');
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
    /* butonul Înapoi de pe Android */
    const inapoi = async () => { await page.evaluate(() => window.__emit('app:backButton', { canGoBack: true })); await asteapta(250); };
    await page.evaluate(() => { location.hash = '#invata/toamna'; }); await asteapta(300);
    await inapoi(); egal(await page.evaluate(() => location.hash), '#invata', 'Înapoi (Android): dintr-o pagină din Învață → Învață');
    await inapoi(); egal(await page.evaluate(() => location.hash), '#azi', 'Înapoi (Android): din Învață → Azi');
    await page.click('#spp-set'); await asteapta(300);
    await page.click('.leg[data-a="sterge"]'); await asteapta(200);
    await inapoi(); verifica(!(await page.locator('.foaie').count()) && (await page.evaluate(() => location.hash)) === '#setari', 'Înapoi (Android): închide întâi foaia');
    await inapoi(); egal(await page.evaluate(() => location.hash), '#azi', 'Înapoi (Android): din Setări → Azi');
    await inapoi(); verifica((await hApel(page)).includes('App.minimizeApp'), 'Înapoi (Android): din Azi, aplicația trece în fundal');
    await page.click('#spp-set'); await asteapta(300);
    egal(await page.getAttribute('#spp-sw-push', 'aria-checked'), 'true', 'aplicație: comutatorul arată notificările de pe acest telefon');
    S.cereri = [];
    await page.click('[data-a="iesi"]'); await asteapta(900);
    const pasi = S.cereri.map((c) => c.m + ' ' + c.p);
    verifica(pasi.indexOf('POST /wp-json/ah-spp/v1/push/sterge') > -1 && pasi.indexOf('POST /wp-json/ah-spp/v1/iesi') > pasi.indexOf('POST /wp-json/ah-spp/v1/push/sterge'), '„Ieși”: întâi tokenul lui (POST /push/sterge, fără DELETE: WAF), apoi /iesi (' + pasi.join(', ') + ')');
    verifica(!S.cereri.some((c) => c.m === 'DELETE'), '„Ieși”: nicio cerere DELETE');
    const del = S.cereri.find((c) => c.p.endsWith('/push/sterge'));
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
    await page.click('[data-a="sterge"]'); await asteapta(250);
    await page.click('.foaie [data-f="da"]'); await asteapta(900);
    verifica(S.cereri.some((c) => c.p === '/wp-json/ah-spp/v1/sterge' && c.antet === '1'), '„Șterge accesul meu” (din foaie): cererea pleacă, cu antetul');
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
    if (st === 'pauza') {
      verifica(!/Primăvara|Iarna ei|Vara ei|Toamna ei|Ziua \d/.test(t) && !/hormonal|contracepție hormonală/i.test(await page.content()), 'el: pauza nu arată faze și nici situația ei');
      verifica(await page.locator('.lista a[href="#invata"]').isVisible() && /Cele 4 faze ale ciclului feminin/.test(t), 'el: pauza: un card spre Învață, sub mesaj');
    }
    if (st === 'iesit' || st === 'link') {
      egal(await page.evaluate(() => [...document.querySelectorAll('h1')].map((h) => h.textContent)), ['Intră în pagina ei'], 'el ' + st + ': un h1, „Intră în pagina ei” (fără „Ciclul ei” dublat)');
      egal(await page.evaluate(() => [document.getElementById('spp-lpb').classList.contains('lite'), document.getElementById('spp-b').classList.contains('lite')]), [false, true], 'el ' + st + ': „Deschide linkul” principal, „Trimite-mi linkul” secundar');
    }
    if (st === 'link') egal(await page.evaluate(() => document.querySelector('main .msg').getAttribute('role')), 'alert', 'el, link expirat: mesajul e role=alert');
    if (st === 'iesit') {
      await page.fill('#spp-e', 'andrei.ștefan@gmail.com'); await page.click('#spp-b'); await asteapta(150);
      egal(await page.evaluate(() => [document.getElementById('spp-m').textContent, document.activeElement.id]), ['Adresa conține caractere nepermise (diacritice sau spații).', 'spp-e'], 'el: „Trimite-mi linkul” cu diacritice → mesaj, focus pe câmp');
      S.esec.link = true;
      await page.fill('#spp-e', 'mihai@exemplu.ro'); await page.click('#spp-b'); await asteapta(120);
      egal(await page.evaluate(() => [document.getElementById('spp-f').getAttribute('aria-busy'), document.getElementById('spp-b').disabled]), ['true', true], 'el: „Trimite-mi linkul” — aria-busy cât așteaptă');
      await asteapta(700);
      egal(await page.evaluate(() => [document.getElementById('spp-f').getAttribute('aria-busy'), document.getElementById('spp-b').disabled, document.activeElement.id, document.getElementById('spp-m').textContent]), [null, false, 'spp-b', 'Prea multe cereri. Mai încearcă peste o oră.'], 'el: cererea n-a mers → butonul activ, cu focus, mesajul');
      S.esec.link = false;
      await page.fill('#spp-e', ''); await page.evaluate(() => { document.getElementById('spp-m').textContent = ''; document.getElementById('spp-e').removeAttribute('aria-invalid'); });
      await page.fill('#spp-lpi', 'nu e un link'); await page.click('#spp-lpb'); await asteapta(150);
      verifica(/nu pare întreg/.test(await page.locator('#spp-lpm').innerText()), 'el fără cont: câmpul pentru linkul lipit, cu mesaj la greșeală');
      await page.fill('#spp-lpi', ''); await page.evaluate(() => { document.getElementById('spp-lpm').textContent = ''; document.getElementById('spp-lpi').removeAttribute('aria-invalid'); document.activeElement.blur(); });
    }
    await poza(page, nume);
    await axe(page, 'el, ' + st);
    egal(erori, [], 'el ' + st + ': fără erori JS');
    await ctx.close();
  }

  /* ===== 6. el: linkul unei invitații oprite, deschis cu o sesiune activă (L3) ===== */
  {
    S.el = 'ok-oprit';
    const ctx = await browser.newContext({ ...ctxOpt, userAgent: UA_SAFARI });
    await ruta(ctx);
    const page = await ctx.newPage(); const erori = []; page.on('pageerror', (e) => erori.push(e.message));
    await page.goto(SITE + '/pentru-el/?t=' + 'Z'.repeat(43)); await asteapta(700);
    egal(await page.evaluate(() => { const m = document.querySelector('main .msg'); return [m && m.textContent, m && m.getAttribute('role'), !!document.querySelector('.sus h1'), location.search]; }), ['Linkul acesta nu mai e activ.', 'status', true, ''], 'el: link oprit, sesiune activă — pagina lui rămâne, cu un rând scurt; adresa curată');
    await poza(page, 'el-16-link-oprit-sesiune-activa');
    await page.evaluate(() => { location.hash = '#invata'; }); await asteapta(300); await page.evaluate(() => { location.hash = '#azi'; }); await asteapta(300);
    egal(await page.evaluate(() => [...document.querySelectorAll('main .msg')].filter((m) => /nu mai e activ/.test(m.textContent)).length), 0, 'el: rândul apare o singură dată');
    egal(erori, [], 'el, link oprit: fără erori JS');
    await ctx.close();
  }

  /* ===== 7. zoom 200% (390 px → 195 px CSS): fără derulare orizontală, titlurile se rup ===== */
  {
    S.el = 'ok';
    const ctx = await browser.newContext({ ...ctxOpt, viewport: { width: 195, height: 422 }, deviceScaleFactor: 4, userAgent: UA_SAFARI });
    await ruta(ctx);
    const page = await ctx.newPage(); const erori = []; page.on('pageerror', (e) => erori.push(e.message));
    await page.goto(SITE + '/pentru-el/'); await asteapta(900);
    await faraOrizontal(page, 'Azi');
    egal(await page.evaluate(() => [getComputedStyle(document.querySelector('h1')).overflowWrap, getComputedStyle(document.querySelector('.luni .r')).flexWrap]), ['anywhere', 'wrap'], 'zoom 200%: h1 overflow-wrap:anywhere, rândurile „Ce urmează” se rup');
    await poza(page, 'el-17-zoom200-azi', true);
    for (const h of ['invata', 'invata/faze', 'invata/podcast', 'setari']) {
      await page.evaluate((x) => { location.hash = '#' + x; }, h); await asteapta(350);
      await faraOrizontal(page, h);
    }
    await poza(page, 'el-17-zoom200-setari', true);
    await page.click('.leg[data-a="sterge"]'); await asteapta(250);
    await faraOrizontal(page, 'foaia „Șterge accesul meu”');
    verifica(await page.evaluate(() => [...document.querySelectorAll('.foaie .btn')].every((b) => b.scrollWidth <= b.clientWidth + 1)), 'zoom 200%: butoanele foii nu taie textul');
    await poza(page, 'el-17-zoom200-foaie');
    await ctx.close();
    S.el = 'iesit';
    const ctx2 = await browser.newContext({ ...ctxOpt, viewport: { width: 195, height: 422 }, deviceScaleFactor: 4, userAgent: UA_SAFARI });
    await ruta(ctx2);
    const p2 = await ctx2.newPage(); p2.on('pageerror', (e) => erori.push(e.message));
    await p2.goto(SITE + '/pentru-el/'); await asteapta(700);
    await faraOrizontal(p2, 'fără cont (Intră în pagina ei)');
    await poza(p2, 'el-17-zoom200-fara-cont', true);
    await ctx2.close();
    egal(erori, [], 'zoom 200%: fără erori JS');
  }

  await browser.close();
  fs.rmSync(TMP, { recursive: true, force: true });
  console.log(`${ok} verificări trecute, ${rau} picate. Pozele: docs/screenshots-v2/partener-*.png`);
  process.exit(rau ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(1); });
