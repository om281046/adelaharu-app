/**
 * Fazele calculate pe server pentru el (ah-sp-partener.php: ah_spp_d, ah_spp_lung, ah_spp_faze, ah_spp_azi, ah_spp_iso)
 * comparate cu CI din 235, citit direct din reference/v2/sn235.php (tests/sn235-ciclu.cjs), pe:
 *   - toate ciclurile 21–40 × menstruațiile 2–9 × fiecare zi din ciclu (și câteva după);
 *   - mii de istorii aleatoare: date stricate, duplicate, ordine amestecată, peste 13 starturi, numere ca text, zecimale,
 *     valori lipsă sau absurde, ani 0–99, luni/zile peste limită, ani bisecți, schimbarea orei;
 * Rulare (din rădăcina proiectului): node server/tests/partener/faze.test.cjs
 */
'use strict';
process.env.TZ = 'Europe/Bucharest';
const fs = require('fs');
const os = require('os');
const path = require('path');
const { execFileSync } = require('child_process');
const CI235 = require('../sn235-ciclu.cjs');

let ok = 0, rau = 0;
const verifica = (c, nume) => { if (c) ok++; else { rau++; if (rau < 25) console.log('PICAT: ' + nume); } };

/* „azi” ca în telefonul ei: ora locală (12:00 sau aproape de miezul nopții), data dată */
function acumLocal(iso, ora) { const [y, m, d] = iso.split('-').map(Number); return new Date(y, m - 1, d, ora, 30, 0).getTime(); }
const isoZ = (z) => new Date(z * 864e5).toISOString().slice(0, 10);
const zi0 = (s) => { const p = s.split('-'); return Date.UTC(+p[0], p[1] - 1, +p[2]) / 864e5; };

const cazuri = [];
/* 1. grila completă: L 21–40, m 2–9, zilele 1…L+9 (o istorie de 1 start, lungimea din setări) */
const AZI = '2026-11-18';
for (let L = 21; L <= 40; L++) for (let m = 2; m <= 9; m++) for (let zi = 0; zi <= L + 9; zi++) {
  cazuri.push({ ciclu: { starturi: [isoZ(zi0(AZI) - zi + 1)], lung: L, men: m, sit: 'natural' }, azi: AZI, ora: 12 });
}
/* 2. aleatoare, cu semințe fixe */
let s = 20261118; const rnd = () => { s = (s * 1103515245 + 12345) % 2147483648; return s / 2147483648; };
const alege = (a) => a[Math.floor(rnd() * a.length)];
const ciudate = ['2026-02-29', '2024-02-29', '2026-13-01', '2026-00-10', '2026-02-31', '0050-03-01', '0099-12-31', '1999-12-31', '2026-1-05', '2026-11-18\n', ' 2026-11-01', '20261101', 'nu', '', null, 12, {}, [], true];
const lungi = [28, 30, 21, 40, 41, 20, '30', ' 27 ', '0x1E', '1e1', 28.5, 33.4, '', null, 0, -5, 'abc', true, false, [32], ['29'], [], {}, undefined];
for (let i = 0; i < 6000; i++) {
  const azi = isoZ(zi0('2025-01-01') + Math.floor(rnd() * 900));
  const n = Math.floor(rnd() * 16);
  const st = [];
  let z = zi0(azi) - Math.floor(rnd() * 70);
  for (let k = 0; k < n; k++) {
    if (rnd() < 0.08) st.push(alege(ciudate));
    else st.push(isoZ(z));
    if (rnd() < 0.1 && st.length) st.push(st[st.length - 1]); // duplicat
    z -= 15 + Math.floor(rnd() * 35);
  }
  if (rnd() < 0.3) st.sort(() => rnd() - 0.5); // ordine amestecată
  const ciclu = { starturi: st };
  if (rnd() < 0.9) ciclu.lung = alege(lungi);
  if (rnd() < 0.9) ciclu.men = alege([5, 2, 9, 10, 1, '4', 4.5, '', null, 0, 'x', [3], true]);
  if (rnd() < 0.03) ciclu.starturi = alege(['2026-11-01', null, 7, { a: 1 }]);
  if (ciclu.lung === undefined) delete ciclu.lung;
  cazuri.push({ ciclu, azi, ora: rnd() < 0.2 ? 23 : 12 });
}
/* 3. schimbarea orei (martie/octombrie) și anii bisecți */
for (const azi of ['2026-03-29', '2026-03-30', '2026-10-25', '2026-10-26', '2028-02-29', '2028-03-01']) {
  for (let d = 0; d < 45; d++) cazuri.push({ ciclu: { starturi: [isoZ(zi0(azi) - d), isoZ(zi0(azi) - d - 29), isoZ(zi0(azi) - d - 57)], lung: 28, men: 5 }, azi, ora: 0 });
}

const TMP = fs.mkdtempSync(path.join(process.env.AHSPA_TMP || os.tmpdir(), 'ahspp-faze-'));
const f = path.join(TMP, 'cazuri.json');
fs.writeFileSync(f, JSON.stringify(cazuri.map((c) => ({ ciclu: c.ciclu, azi: c.azi }))));
const php = JSON.parse(execFileSync('php', [path.join(__dirname, 'calcul.php'), f], { maxBuffer: 1 << 28 }).toString());
fs.rmSync(TMP, { recursive: true, force: true });
verifica(php.length === cazuri.length, 'PHP a răspuns pentru fiecare caz');

const num = (a, b) => (a === null && b === null) || Math.abs(a - b) < 1e-9;
let cuFaza = 0, fazeVazute = {};
cazuri.forEach((c, i) => {
  const ci = CI235({ ahsp_ciclu: c.ciclu }, acumLocal(c.azi, c.ora));
  let d, z, L;
  try { d = ci.d(); } catch (e) { d = 'eroare'; }
  const p = php[i];
  const desc = '#' + i + ' ' + JSON.stringify(c);
  if (d === 'eroare') { verifica(true, ''); return; } // 235 însuși cade (ex. tablou în starturi): serverul nu are cum fi „egal”
  verifica(JSON.stringify(d && d.starturi) === JSON.stringify(p.d && p.d.starturi), 'CI.d starturi ' + desc);
  if (!d) { verifica(p.d === null && p.azi === null, 'fără date ' + desc); return; }
  verifica(num(d.lung, p.d.lung) && num(d.men, p.d.men), 'CI.d lung/men ' + desc + ' js=' + d.lung + '/' + d.men + ' php=' + p.d.lung + '/' + p.d.men);
  L = ci.lung(d);
  verifica(num(L, p.L), 'CI.lung ' + desc + ' js=' + L + ' php=' + p.L);
  /* CI.azi citește din „localStorage” prin CI.d, la ora telefonului */
  z = ci.azi();
  if (!z) { verifica(p.azi === null, 'CI.azi null ' + desc); return; }
  const a = p.azi;
  if (!a) { verifica(false, 'CI.azi lipsește în PHP ' + desc); return; }
  cuFaza++; fazeVazute[z.f] = (fazeVazute[z.f] || 0) + 1;
  verifica(num(z.zi, a.zi) && num(z.L, a.L) && num(z.m, a.m) && z.f === a.f && num(z.s0, a.s0) && num(z.urm, a.urm) && z.peste === a.peste, 'CI.azi ' + desc + '\n  js=' + JSON.stringify(z) + '\n  php=' + JSON.stringify(a));
  verifica(z.fz.length === 4 && z.fz.every((q, k) => q.f === a.fz[k].f && num(q.a, a.fz[k].a) && num(q.b, a.fz[k].b)), 'CI.faze ' + desc);
  verifica(ci.iso(z.s0) === p.iso[0] && ci.iso(Math.floor(z.urm)) === p.iso[1], 'CI.iso ' + desc);
});
verifica(cuFaza > 10000, 'destule cazuri cu fază: ' + cuFaza);
verifica(['iarna', 'primavara', 'vara', 'toamna'].every((q) => fazeVazute[q] > 1000), 'toate fazele apar des: ' + JSON.stringify(fazeVazute));
console.log(`${ok} verificări trecute, ${rau} picate (${cazuri.length} cazuri, ${cuFaza} cu fază).`);
process.exit(rau ? 1 : 0);
