/**
 * Calculul ciclului din puntea aplicației (ah-sp-aplicatie.php, între /*CICLU-INCEPUT*\/ și /*CICLU-SFARSIT*\/)
 * comparat cu Ciclul meu din Spațiul meu (CI.d, CI.lung, CI.faze, CI.azi din reference/v2/sn235.php, citite din fișier).
 * Rulare: node server/tests/ciclu.test.cjs   (iese cu cod 1 dacă ceva diferă)
 */
'use strict';
process.env.TZ = 'UTC'; // „ora locală” din test = UTC (Ciclul meu lucrează pe zile locale)
const path = require('path');
const { execFileSync } = require('child_process');

const RAD = path.join(__dirname, '..', '..');
const php = execFileSync('php', ['-r', 'require $argv[1]; require $argv[2]; echo ah_spa_js();', path.join(__dirname, 'stubs.php'), path.join(RAD, 'server', 'ah-sp-aplicatie.php')], { encoding: 'utf8' });
const a = php.indexOf('/*CICLU-INCEPUT*/'), b = php.indexOf('/*CICLU-SFARSIT*/');
if (a < 0 || b < 0) throw new Error('Nu găsesc blocul ciclului în puntea JS');
const CX = new Function(php.slice(a, b) + '\nreturn CX;')();

/* Ciclul meu (235), citit din fragment */
const CI235 = require('./sn235-ciclu.cjs');

let ok = 0, rau = 0;
const eq = (x, y, nume) => { const s1 = JSON.stringify(x), s2 = JSON.stringify(y); if (s1 === s2) ok++; else { rau++; if (rau < 25) console.log('PICAT: ' + nume + '\n  am:      ' + s1 + '\n  așteptam: ' + s2); } };
const da = (c, nume) => eq(!!c, true, nume);

/* număr pseudo-aleator repetabil */
let seed = 20261118;
const rnd = () => { seed = (seed * 1103515245 + 12345) % 2147483648; return seed / 2147483648; };
const ri = (lo, hi) => lo + Math.floor(rnd() * (hi - lo + 1));
const iso = (z) => new Date(z * 864e5).toISOString().slice(0, 10);
const AZI = Date.UTC(2026, 10, 18) / 864e5; // 18 nov. 2026

/* 1. fazele: identice pentru ciclu 21–45 și menstruație 2–10 */
const C0 = CI235({}, Date.UTC(2026, 10, 18, 10));
for (let L = 21; L <= 45; L++) for (let m = 2; m <= 10; m++) eq(CX.faze(L, m), C0.faze(L, m), `faze(${L},${m})`);
/* și regula din arhitectură: oS=max(L-16,m+2), oE=min(oS+4,L-2) */
for (let L = 21; L <= 45; L++) for (let m = 2; m <= 10; m++) {
  const f = CX.faze(L, m), oS = Math.max(L - 16, m + 2), oE = Math.min(oS + 4, L - 2);
  eq([f[0].a, f[0].b, f[1].a, f[1].b, f[2].a, f[2].b, f[3].a, f[3].b], [1, m, m + 1, oS - 1, oS, oE, oE + 1, L], `regula arhitecturii L=${L} m=${m}`);
}

/* 2. datele (CI.d) și lungimea (CI.lung): istorii aleatoare, inclusiv date stricate, dubluri și limite depășite */
function istorie() {
  const n = ri(0, 15), st = [];
  let z = AZI - ri(0, 50);
  for (let i = 0; i < n; i++) { st.push(iso(z)); z -= ri(17, 48); }
  if (rnd() < 0.2) st.push(st[0] || '2026-11-01');           // dublură
  if (rnd() < 0.15) st.push('2026-13-45x');                    // stricată
  if (rnd() < 0.3) st.sort(() => rnd() - 0.5);
  const x = n || rnd() < 0.5 ? { starturi: st } : null;
  if (x && rnd() < 0.8) x.lung = ri(18, 46);
  if (x && rnd() < 0.8) x.men = ri(1, 11);
  if (x && rnd() < 0.5) x.sit = ['natural', 'hormonal', 'sarcina', 'alaptare', 'perimenopauza'][ri(0, 4)];
  const v = rnd() < 0.5 ? null : { s: rnd() < 0.5 ? iso(AZI - ri(0, 30)) : undefined, c: rnd() < 0.5 ? ri(20, 42) : undefined, m: rnd() < 0.5 ? ri(1, 10) : undefined, starturi: rnd() < 0.4 ? [iso(AZI - ri(30, 60)), iso(AZI - ri(61, 90))] : undefined };
  return { x, v };
}
for (let i = 0; i < 3000; i++) {
  const { x, v } = istorie(), store = {};
  if (x) store.ahsp_ciclu = x;
  if (v) store.ah_ciclu_v1 = JSON.parse(JSON.stringify(v));
  const CI = CI235(store, Date.UTC(2026, 10, 18, 10));
  const d1 = CI.d(), d2 = CX.d(x ? JSON.parse(JSON.stringify(x)) : null, v ? JSON.parse(JSON.stringify(v)) : null);
  eq(d2, d1, 'CI.d #' + i);
  if (d1) eq(CX.lung(d2), CI.lung(d1), 'CI.lung #' + i);
}

/* 3. notificările: fiecare memento cade exact la începutul unei faze, după regula Ciclul meu, pe 3 cicluri */
let cuNotif = 0, total = 0;
for (let i = 0; i < 3000; i++) {
  const { x, v } = istorie(), store = {};
  if (x) store.ahsp_ciclu = x;
  if (v) store.ah_ciclu_v1 = v;
  const ora = ri(0, 23), acum = Date.UTC(2026, 10, 18, ora); // fus UTC în test: ora locală = ora UTC
  const CI = CI235(store, acum), dd = CI.d();
  const lst = CX.viitoare(dd, AZI, ora * 60, 3, 9);
  if (!dd) { eq(lst, [], 'fără date: nimic #' + i); continue; }
  const z = CI.azi(), sit = dd.sit;
  if (sit === 'hormonal' || sit === 'sarcina') { eq(lst, [], 'pastile/sarcină: nimic #' + i); continue; }
  if (!z || z.peste) { eq(lst, [], 'înainte de prima zi sau menstruație întârziată: nimic #' + i); continue; }
  const L = z.L, fz = CI.faze(L, dd.men || 5);
  /* așteptat: toate începuturile de fază din [azi, azi+3L], proiectate din ultima menstruație, cu ciclul de L zile */
  const ast = [];
  for (let k = 0; k <= 4; k++) fz.forEach((p) => { if (p.b < p.a) return; const zz = z.s0 + k * L + p.a - 1; if (zz < AZI || zz > AZI + 3 * L || (zz === AZI && ora >= 9)) return; ast.push({ f: p.f, zi: zz }); });
  ast.sort((p, q) => p.zi - q.zi);
  eq(lst.map((q) => ({ f: q.f, zi: q.zi })), ast, 'mementourile #' + i);
  /* și, zi cu zi, faza după regula lui CI.azi (ziua din ciclu → faza) */
  lst.forEach((q) => { const d = ((q.zi - z.s0) % L) + 1; let f = 'toamna'; fz.forEach((p) => { if (d >= p.a && d <= p.b) f = p.f; }); eq(f, q.f, 'faza zilei #' + i); eq(fz.find((p) => p.f === f).a, d, 'prima zi a fazei #' + i); });
  /* primul memento de după azi e faza care urmează celei de azi (sau chiar cea de azi, dacă începe azi înainte de 9) */
  if (lst.length) { const ord = ['iarna', 'primavara', 'vara', 'toamna']; const urm = lst[0].zi === AZI ? z.f : ord[(ord.indexOf(z.f) + 1) % 4]; eq(lst[0].f, urm, 'faza următoare #' + i); }
  cuNotif += lst.length ? 1 : 0; total++;
}
da(cuNotif > 500, 'cazurile cu mementouri sunt destule (' + cuNotif + ' din ' + total + ')');

/* 4. un caz scris de mână: ciclu 28, menstruație 5, ultima pe 10 nov. 2026; azi 18 nov., ora 8 */
const x1 = { starturi: ['2026-11-10', '2026-10-13', '2026-09-15'], lung: 28, men: 5 };
const l1 = CX.viitoare(CX.d(x1, null), AZI, 8 * 60, 3, 9).map((q) => iso(q.zi) + ' ' + q.f);
eq(l1.slice(0, 5), ['2026-11-21 vara', '2026-11-26 toamna', '2026-12-08 iarna', '2026-12-13 primavara', '2026-12-19 vara'], 'caz de mână: vara pe 21 nov. (ziua 12), toamna pe 26 nov.');
eq(CX.faze(28, 5), [{ f: 'iarna', a: 1, b: 5 }, { f: 'primavara', a: 6, b: 11 }, { f: 'vara', a: 12, b: 16 }, { f: 'toamna', a: 17, b: 28 }], 'caz de mână: fazele 28/5');

console.log(`\n${ok} verificări trecute, ${rau} picate.`);
process.exit(rau ? 1 : 0);
