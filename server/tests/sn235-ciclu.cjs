/**
 * Ciclul meu, exact din fragmentul 235 (reference/v2/sn235.php): liniile obiectului CI (d, zi0, azi0, iso, lung, faze, azi),
 * citite din fișier și rulate cu un localStorage și un „azi” date de test. Folosit de ciclu.test.cjs și vizual/poze.cjs.
 */
'use strict';
const fs = require('fs');
const path = require('path');
const sn = fs.readFileSync(path.join(__dirname, '..', '..', 'reference', 'v2', 'sn235.php'), 'utf8').split('\n');
const linie = (inc) => { const l = sn.find((x) => x.startsWith(inc)); if (!l) throw new Error('lipsește în 235: ' + inc); return l.replace(/,\s*$/, ''); };
const parti = [' d:function(){', ' zi0:function(s)', ' azi0:function()', ' iso:function(z)', ' lung:function(x)', ' faze:function(L,m)', ' azi:function()'].map(linie);
/** CI din 235, cu store = { cheie: valoare } în loc de localStorage și acum = ms (ora „locală”). */
module.exports = function CI235(store, acum) {
  const ls = (k) => (k in store ? JSON.parse(JSON.stringify(store[k])) : null);
  class D extends Date { constructor(...x) { if (x.length) super(...x); else super(acum); } }
  return new Function('ls', 'Date', 'var CI={' + parti.join(',\n') + '};return CI;')(ls, D);
};
