// Copie VERBATIM din reference/sn41.php (scriptul paginii /fazele-ciclului-feminin/: utc…medie, luna, primele 4 rânduri din startNou).
// Folosită doar de testul diferențial (algoritm.diferential.test.ts): algoritm.ts trebuie să dea aceleași rezultate.
export function creeaza(S) {
var ORD = ["menstruala", "foliculara", "ovulatorie", "premenstruala"];
function utc(ymd) { var p = ymd.split("-"); return Date.UTC(+p[0], +p[1] - 1, +p[2]); }
function ymd(t) { var d = new Date(t); return d.getUTCFullYear() + "-" + ("0" + (d.getUTCMonth() + 1)).slice(-2) + "-" + ("0" + d.getUTCDate()).slice(-2); }
function azi() { var d = new Date(); return d.getFullYear() + "-" + ("0" + (d.getMonth() + 1)).slice(-2) + "-" + ("0" + d.getDate()).slice(-2); }
function plus(s, n) { return ymd(utc(s) + n * 864e5); }
function dif(a, b) { return Math.round((utc(b) - utc(a)) / 864e5); }
function frumos(s, cuZi) { var d = new Date(utc(s)); return (cuZi ? ZILE[d.getUTCDay()] + ", " : "") + d.getUTCDate() + " " + LUNI[d.getUTCMonth()]; }
function lim(c, m) {
  c = Math.max(21, Math.min(45, c | 0)); m = Math.max(2, Math.min(10, m | 0));
  var ovS = Math.max(c - 16, m + 2), ovE = Math.min(ovS + 4, c - 2);
  return { menstruala: [1, m], foliculara: [m + 1, ovS - 1], ovulatorie: [ovS, ovE], premenstruala: [ovE + 1, c] };
}
function fazaZilei(zi, c, m) { var l = lim(c, m); for (var i = 0; i < ORD.length; i++) { var z = l[ORD[i]]; if (zi >= z[0] && zi <= z[1]) { return ORD[i]; } } return "premenstruala"; }
/* startul care se aplică unei zile: ultimul start cunoscut de dinaintea ei, proiectat înainte cu lungimea ciclului */
function unde(zi) {
  var st = (S.starturi || []).slice().sort(), s = S.s, c = S.c, m = S.m;
  for (var i = st.length - 1; i >= 0; i--) { if (st[i] <= zi) { s = st[i]; break; } }
  if (!s || zi < s) { return null; }
  var d = dif(s, zi), nr = Math.floor(d / c), z = d % c + 1;
  return { zi: z, nr: nr, faza: fazaZilei(z, c, m), inceput: plus(s, nr * c) };
}
function medie(st, implicit) {
  st = st.slice().sort(); var p = [];
  for (var i = 1; i < st.length; i++) { var d = dif(st[i - 1], st[i]); if (d >= 21 && d <= 45) { p.push(d); } }
  p = p.slice(-3);
  return p.length ? Math.round(p.reduce(function (a, b) { return a + b; }, 0) / p.length) : implicit;
}
function luna(s) {
  var P = 29.530588853, zile = (utc(s) + 432e5 - Date.UTC(2000, 0, 6, 18, 14)) / 864e5;
  var v = ((zile % P) + P) % P, f = v / P, nume;
  if (v < 1.85 || v > 27.7) { nume = "Luna nouă"; } else if (v < 13.8) { nume = "Luna în creștere"; } else if (v < 15.8) { nume = "Luna plină"; } else { nume = "Luna în descreștere"; }
  return { f: f, nume: nume };
}
function startNou(d) {
  var st = (S.starturi || []).slice().sort(), ult = st[st.length - 1];
  if (ult && Math.abs(dif(ult, d)) < 15) { st.pop(); }
  st.push(d); st = st.filter(function (x, i, a) { return a.indexOf(x) === i; }).sort().slice(-6);
  S.starturi = st; S.s = st[st.length - 1]; S.c = medie(st, S.c);
}
return { utc, ymd, plus, dif, lim, fazaZilei, unde, medie, luna, startNou, S };
}
