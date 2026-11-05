/**
 * Capturi de ecran în modul demo (VITE_MOCK=1), la mărimea unui telefon.
 *   npm run build:mock && node scripts/screenshots.cjs
 * Pornește `vite preview` pe dist-mock și salvează în ../docs/screenshots/.
 */
const { spawn } = require('node:child_process');
const path = require('node:path');
const fs = require('node:fs');
const { chromium } = require('playwright');

const RAD = path.resolve(__dirname, '..');
const OUT = path.resolve(RAD, '../docs/screenshots');
const PORT = 4789;
const URL = `http://localhost:${PORT}/`;

async function asteaptaServer() {
  for (let i = 0; i < 60; i++) {
    try {
      const r = await fetch(URL);
      if (r.ok) return;
    } catch {}
    await new Promise((r) => setTimeout(r, 250));
  }
  throw new Error('serverul nu a pornit');
}

(async () => {
  fs.mkdirSync(OUT, { recursive: true });
  const srv = spawn('npx', ['vite', 'preview', '--outDir', 'dist-mock', '--port', String(PORT), '--strictPort'], { cwd: RAD, stdio: 'ignore' });
  const erori = [];
  try {
    await asteaptaServer();
    const browser = await chromium.launch();
    const scheme = process.env.SCHEMA === 'dark' ? 'dark' : 'light';
    const ctx = await browser.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true, locale: 'ro-RO', colorScheme: scheme });
    const p = await ctx.newPage();
    p.on('pageerror', (e) => erori.push(String(e)));
    p.on('console', (m) => m.type() === 'error' && erori.push(m.text()));
    const sufix = scheme === 'dark' ? '-dark' : '';
    const poza = async (nume, full = false) => {
      await p.waitForTimeout(450);
      await p.screenshot({ path: path.join(OUT, nume + sufix + '.png'), fullPage: full });
      console.log('✓', nume + sufix);
    };

    await p.goto(URL);
    await p.waitForSelector('#email');
    await poza('01-intrare', false);
    await p.fill('#email', 'ana@exemplu.ro');
    await p.click('button[type=submit]');
    await p.waitForSelector('#cod');
    await poza('02-cod', false);
    await p.fill('#cod', '123456');
    await p.waitForSelector('nav.taburi');

    // ciclul: completăm datele (prima zi = acum 14 zile)
    await p.click('nav.taburi button:has-text("Ciclul meu")');
    await p.waitForSelector('#start');
    await poza('03-ciclu-formular', false);
    const d = new Date(Date.now() - 14 * 864e5);
    const ymd = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    await p.fill('#start', ymd);
    await p.click('button:has-text("Vezi în ce fază ești")');
    await p.waitForSelector('.roata');
    await poza('04-ciclu');
    await p.evaluate(() => window.scrollTo(0, 820));
    await poza('04b-ciclu-detalii');
    await p.evaluate(() => window.scrollTo(0, 99999));
    await poza('04c-ciclu-sincronizare');

    await p.click('nav.taburi button:has-text("Inner Temple")');
    await p.waitForSelector('.rand');
    await poza('05-inner-temple');

    await p.click('.rand:has-text("Curățarea pântecului")');
    await p.waitForSelector('.player');
    await p.click('.comenzi .mare');
    await p.waitForTimeout(1200);
    await poza('06-player', false);
    await p.click('button:has-text("Vezi explicația video")');
    await p.waitForSelector('.strat iframe');
    await poza('07-video', false);
    await p.click('button[aria-label="Închide videoul"]');

    await p.click('nav.taburi button:has-text("Inner Temple")');
    await p.waitForSelector('.rand.blocat');
    await p.click('.rand.blocat');
    await p.waitForSelector('.nota-lacat');
    await poza('08-meditatie-blocata', false);

    await p.click('nav.taburi button:has-text("Acasă")');
    await p.waitForSelector('.faza-card');
    await poza('09-acasa');

    await p.click('nav.taburi button:has-text("Cursurile mele")');
    await p.waitForSelector('.card h2');
    await poza('10-cursuri');
    await p.click('.card:has-text("Tainele Menstruației")');
    await p.waitForSelector('.modul');
    await poza('11-curs');
    await p.click('.lectie:has-text("Mesajele corpului")');
    await p.waitForSelector('.cadru-lectie iframe');
    await poza('12-lectie');

    await p.click('nav.taburi button:has-text("Profil")');
    await p.waitForSelector('text=Ai nevoie de ajutor?');
    await poza('13-profil');
    await p.evaluate(() => window.scrollTo(0, 99999));
    await poza('13b-profil-jos');
    await p.click('button:has-text("Șterge contul din aplicație")');
    await p.waitForSelector('.dialog');
    await poza('14-sterge-cont', false);

    await browser.close();
  } finally {
    srv.kill();
  }
  if (erori.length) {
    console.log('Erori în pagină:\n' + erori.join('\n'));
    process.exitCode = 1;
  }
})().catch((e) => {
  console.error(e);
  process.exit(1);
});
