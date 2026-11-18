/**
 * Capturi ale paginii offline (dist/offline.html) la 390×844, cu un `window.Capacitor` simulat
 * (Preferences, Filesystem, convertFileSrc) și date de probă. Salvează în ../docs/screenshots-v2/.
 *   npm run build && node scripts/capturi.cjs
 * Verifică și comportamentul: fără erori în consolă, playerul pornește, „Încearcă din nou” arată mesajul.
 */
const http = require('node:http');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const RAD = path.resolve(__dirname, '..');
const OUT = path.resolve(RAD, '../docs/screenshots-v2');
const DIST = path.join(RAD, 'dist');

/** un WAV de 3 minute 20 s (8 kHz, liniște cu un ton scurt la început), ca playerul să aibă o durată */
function wav(sec) {
  const rate = 8000;
  const n = rate * sec;
  const b = Buffer.alloc(44 + n);
  b.write('RIFF', 0); b.writeUInt32LE(36 + n, 4); b.write('WAVE', 8); b.write('fmt ', 12);
  b.writeUInt32LE(16, 16); b.writeUInt16LE(1, 20); b.writeUInt16LE(1, 22); b.writeUInt32LE(rate, 24);
  b.writeUInt32LE(rate, 28); b.writeUInt16LE(1, 32); b.writeUInt16LE(8, 34); b.write('data', 36); b.writeUInt32LE(n, 40);
  for (let i = 0; i < n; i++) b[44 + i] = i < rate ? 128 + Math.round(20 * Math.sin(i / 6)) : 128;
  return b;
}
const POZE = {
  'luna.svg': ['#E9C9D8', '#9C6B8E'],
  'rasarit.svg': ['#F6D3BE', '#C9735A'],
  'padure.svg': ['#CFE0C8', '#56704D'],
};
const svg = ([a, b]) =>
  `<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200"><defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="${a}"/><stop offset="1" stop-color="${b}"/></linearGradient></defs><rect width="200" height="200" fill="url(#g)"/><circle cx="130" cy="70" r="28" fill="#fff" opacity=".55"/></svg>`;

function server() {
  const audio = wav(200);
  return http
    .createServer((req, res) => {
      const u = decodeURIComponent(req.url.split('?')[0]);
      if (u.startsWith('/_capacitor_file_/')) {
        const nume = path.basename(u);
        if (nume.endsWith('.wav') || nume.endsWith('.mp3') || nume.endsWith('.m4a')) {
          // cu Range, ca sărirea în fișier să meargă ca pe telefon
          const m = /bytes=(\d+)-(\d*)/.exec(req.headers.range || '');
          if (m) {
            const a = +m[1];
            const z = m[2] ? Math.min(+m[2], audio.length - 1) : audio.length - 1;
            res.writeHead(206, { 'Content-Type': 'audio/wav', 'Content-Length': z - a + 1, 'Content-Range': `bytes ${a}-${z}/${audio.length}`, 'Accept-Ranges': 'bytes' });
            return res.end(audio.subarray(a, z + 1));
          }
          res.writeHead(200, { 'Content-Type': 'audio/wav', 'Content-Length': audio.length, 'Accept-Ranges': 'bytes' });
          return res.end(audio);
        }
        const p = POZE[nume];
        if (p) {
          res.writeHead(200, { 'Content-Type': 'image/svg+xml' });
          return res.end(svg(p));
        }
        res.writeHead(404);
        return res.end();
      }
      const f = path.join(DIST, u === '/' ? 'offline.html' : u);
      if (!f.startsWith(DIST) || !fs.existsSync(f)) {
        res.writeHead(404);
        return res.end();
      }
      res.writeHead(200, { 'Content-Type': f.endsWith('.html') ? 'text/html; charset=utf-8' : 'application/octet-stream' });
      fs.createReadStream(f).pipe(res);
    })
    .listen(0);
}

/** window.Capacitor simulat; `date` = cheile din Preferences, `lipsa` = fișiere care nu mai există */
function initCapacitor({ date, lipsa }) {
  const LIB = 'file:///var/mobile/Containers/Data/Application/X/Library/';
  window.__apeluri = [];
  window.Capacitor = {
    getPlatform: () => 'ios',
    isNativePlatform: () => true,
    convertFileSrc: (u) => u.replace('file://', location.origin + '/_capacitor_file_'),
    Plugins: {
      Preferences: { get: async ({ key }) => ({ value: key in date ? date[key] : null }) },
      Filesystem: {
        getUri: async ({ path: p }) => ({ uri: LIB + p }),
        stat: async ({ path: p }) => {
          if (lipsa.includes(p)) throw new Error('File does not exist');
          return { type: 'file', size: 1 };
        },
      },
      SplashScreen: { hide: async () => window.__apeluri.push('SplashScreen.hide') },
      StatusBar: { setStyle: async (o) => window.__apeluri.push('StatusBar.setStyle:' + o.style) },
    },
  };
}

const DESC = JSON.stringify([
  { id: '101', titlu: 'Întoarcerea acasă, în corp', durata: 754, fisier: 'meditatii/101.mp3', poza: 'meditatii/rasarit.svg', descarcat: '2026-11-12T08:00:00Z' },
  { id: '102', titlu: 'Somn liniștit sub lună plină', durata: '18:05', fisier: 'meditatii/102.m4a', poza: 'meditatii/luna.svg', descarcat: '2026-11-15T08:00:00Z' },
  { id: '103', titlu: 'Respirația pădurii', durata: 600, fisier: 'meditatii/103.mp3', poza: 'meditatii/padure.svg', descarcat: '2026-11-01T08:00:00Z' },
  { id: '104', titlu: 'Yoga Nidra pentru iarna interioară', durata: 1500, fisier: 'meditatii/104.mp3', poza: '', descarcat: '2026-10-20T08:00:00Z' },
]);

const SCENARII = [
  { nume: 'offline-gol', date: {}, lipsa: [] },
  {
    nume: 'offline-ciclu-descarcari',
    date: { ah_ciclu: JSON.stringify({ starturi: ['2026-11-07', '2026-10-09', '2026-09-10'], lung: 28, men: 5, sit: 'natural', actualizat: '2026-11-17T19:00:00Z' }), ah_descarcate: DESC },
    lipsa: ['meditatii/103.mp3'],
  },
  {
    nume: 'offline-player',
    date: { ah_ciclu: JSON.stringify({ starturi: ['2026-11-15'], lung: 30, men: 6 }), ah_descarcate: DESC },
    lipsa: [],
    reda: true,
  },
  { nume: 'offline-ciclu-fara-descarcari', date: { ah_ciclu: JSON.stringify({ starturi: ['2026-10-16'], lung: 28, men: 5, sit: 'alaptare' }) }, lipsa: [] },
  { nume: 'offline-hormonal', date: { ah_ciclu: JSON.stringify({ starturi: ['2026-11-04'], lung: 28, men: 4, sit: 'hormonal' }), ah_descarcate: DESC }, lipsa: [] },
  { nume: 'offline-sarcina-intunecat', date: { ah_ciclu: JSON.stringify({ starturi: ['2026-08-01'], sit: 'sarcina' }), ah_descarcate: DESC }, lipsa: [], intunecat: true },
  { nume: 'offline-ciclu-intunecat', date: { ah_ciclu: JSON.stringify({ starturi: ['2026-10-31'], lung: 28, men: 5 }), ah_descarcate: DESC }, lipsa: [], intunecat: true, reda: true },
  { nume: 'offline-reincearca', date: {}, lipsa: [], reincearca: true },
];

(async () => {
  if (!fs.existsSync(path.join(DIST, 'offline.html'))) throw new Error('rulează întâi npm run build');
  fs.mkdirSync(OUT, { recursive: true });
  const srv = server();
  const port = srv.address().port;
  const b = await chromium.launch();
  let probleme = 0;
  for (const s of SCENARII) {
    const ctx = await b.newContext({
      viewport: { width: 390, height: 844 },
      deviceScaleFactor: 2,
      isMobile: true,
      hasTouch: true,
      colorScheme: s.intunecat ? 'dark' : 'light',
      locale: 'ro-RO',
      timezoneId: 'Europe/Bucharest',
    });
    const p = await ctx.newPage();
    const erori = [];
    p.on('pageerror', (e) => erori.push(String(e)));
    p.on('console', (m) => m.type() === 'error' && !/_capacitor_file_.*404|ERR_INTERNET_DISCONNECTED/.test(m.text()) && erori.push(m.text()));
    await p.clock.setFixedTime(new Date('2026-11-18T10:00:00+02:00'));
    await p.addInitScript(initCapacitor, { date: s.date, lipsa: s.lipsa });
    // site-ul „nu răspunde”: cererea de verificare a butonului eșuează
    await p.route('https://www.adelaharu.ro/**', (r) => r.abort('internetdisconnected'));
    await p.goto(`http://localhost:${port}/offline.html`);
    await p.waitForSelector('#reincearca');
    await p.evaluate(() => document.fonts.ready);
    if (s.reda) {
      await p.click('.med-b');
      await p.waitForFunction(() => {
        const a = window.ahOfflinePlayer;
        return a && a.stare.durata > 0 && !document.getElementById('player').hidden;
      });
      // poziție de mijloc, pentru o captură realistă
      await p.evaluate(() => {
        window.ahOfflinePlayer.mergiLa(74);
        window.ahOfflinePlayer.pauza();
      });
      await p.waitForFunction(() => window.ahOfflinePlayer.stare.timp > 70);
      await p.waitForTimeout(200);
    }
    if (s.reincearca) {
      await p.click('#reincearca');
      await p.waitForFunction(() => /Încă nu avem conexiune/.test(document.getElementById('re-msg').textContent));
    }
    await p.waitForTimeout(150);
    const apeluri = await p.evaluate(() => window.__apeluri);
    if (!apeluri.includes('SplashScreen.hide')) erori.push('SplashScreen.hide nu a fost chemat');
    const lat = await p.evaluate(() => document.documentElement.scrollWidth);
    if (lat > 390) erori.push('pagina e mai lată decât ecranul: ' + lat);
    await p.screenshot({ path: path.join(OUT, s.nume + '.png'), fullPage: !s.reda });
    if (erori.length) {
      probleme++;
      console.error(s.nume + ':\n  ' + erori.join('\n  '));
    } else console.log('ok ' + s.nume);
    await ctx.close();
  }
  await b.close();
  srv.close();
  if (probleme) process.exit(1);
})();
