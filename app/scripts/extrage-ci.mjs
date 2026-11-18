/**
 * Copiază, neschimbate, bucățile din modulul CI al Spațiului meu (reference/v2/sn235.php) de care are
 * nevoie pagina offline: F, ORD, d, zi0, azi0, iso, data, lung, faze, SIT, sit, azi.
 * Rezultatul, src/offline/fixtures/sn235-ci.js, e folosit de testul diferențial (ciclu.diferential.test.ts).
 *
 *   node scripts/extrage-ci.mjs            # scrie fișierul
 *   node scripts/extrage-ci.mjs --verifica # doar compară (iese cu 1 dacă sn235 s-a schimbat)
 */
import { readFileSync, writeFileSync, existsSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const RAD = resolve(dirname(fileURLToPath(import.meta.url)), '..');
export const SURSA = resolve(RAD, '../reference/v2/sn235.php');
export const TINTA = resolve(RAD, 'src/offline/fixtures/sn235-ci.js');
const CHEI = ['F', 'ORD', 'd', 'zi0', 'azi0', 'iso', 'data', 'lung', 'faze', 'SIT', 'sit', 'azi'];

export function extrage(php) {
  const linii = php.split('\n');
  const start = linii.findIndex((l) => l.startsWith('var CI={'));
  if (start < 0) throw new Error('nu găsesc „var CI={” în sn235.php');
  const bucati = [];
  for (const k of CHEI) {
    const i = linii.findIndex((l, j) => j > start && l.startsWith(' ' + k + ':'));
    if (i < 0) throw new Error('nu găsesc CI.' + k + ' în sn235.php');
    // F și SIT se întind pe mai multe rânduri, până la următoarea cheie (un spațiu + nume + „:”)
    let sf = i;
    if (k === 'F' || k === 'SIT') while (sf + 1 < linii.length && !/^ [A-Za-z0-9_]+:/.test(linii[sf + 1]) && !/^\s*\/\*/.test(linii[sf + 1])) sf++;
    bucati.push(linii.slice(i, sf + 1).join('\n').replace(/,\s*$/, ''));
  }
  return (
    '/* GENERAT de scripts/extrage-ci.mjs din reference/v2/sn235.php (modulul CI, copiat neschimbat).\n' +
    '   Nu se editează de mână: rulează din nou scriptul dacă sn235 se schimbă. */\n' +
    '/* eslint-disable */\n' +
    'export function creeazaCI(ls) {\n' +
    'var CI={\n' +
    bucati.join(',\n') +
    '\n};\nreturn CI;\n}\n'
  );
}

if (process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  if (!existsSync(SURSA)) {
    console.error('Lipsește ' + SURSA);
    process.exit(2);
  }
  const nou = extrage(readFileSync(SURSA, 'utf8'));
  if (process.argv.includes('--verifica')) {
    const vechi = existsSync(TINTA) ? readFileSync(TINTA, 'utf8') : '';
    if (vechi !== nou) {
      console.error('sn235.php s-a schimbat față de fixtures/sn235-ci.js: rulează node scripts/extrage-ci.mjs și verifică ciclu.ts.');
      process.exit(1);
    }
    console.log('fixtures/sn235-ci.js e la zi.');
  } else {
    writeFileSync(TINTA, nou);
    console.log('Scris ' + TINTA);
  }
}
