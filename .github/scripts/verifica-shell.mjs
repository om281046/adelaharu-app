#!/usr/bin/env node
/**
 * Verifică configurația shell-ului Capacitor înainte de build (v2, docs/arhitectura.md).
 * Prinde greșelile care nu se văd la compilare: un server.url lăsat pe un calculator de test,
 * pagina offline lipsă, un User-Agent fără versiune (fragmentul n-ar mai recunoaște aplicația),
 * o gazdă de plată lăsată în aplicație.
 *
 * Folosire (din app/):
 *   node ../.github/scripts/verifica-shell.mjs                 # config rezolvat de `npx cap config --json`
 *   node ../.github/scripts/verifica-shell.mjs <cale.json> ... # capacitor.config.json generat de `cap sync`
 */
import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';

const URL_CONT = 'https://www.adelaharu.ro/spatiul-meu/';
const GAZDE_PERMISE = new Set(['www.adelaharu.ro', 'adelaharu.ro', 'player.vimeo.com', '*.vimeocdn.com', 'embed.podcasts.apple.com']);
const versiune = JSON.parse(readFileSync('package.json', 'utf8')).version;

function citeste(cale) {
  if (cale) return [cale, JSON.parse(readFileSync(cale, 'utf8'))];
  const iesire = execFileSync('npx', ['--no-install', 'cap', 'config', '--json'], { encoding: 'utf8', maxBuffer: 1 << 24 });
  return ['capacitor.config.ts', JSON.parse(iesire).app.extConfig];
}

function verifica(nume, c) {
  const erori = [];
  const s = c.server || {};
  if (s.url !== URL_CONT) erori.push(`server.url = ${JSON.stringify(s.url)}, trebuie ${URL_CONT}`);
  if (s.errorPath !== 'offline.html') erori.push(`server.errorPath = ${JSON.stringify(s.errorPath)}, trebuie "offline.html"`);
  if (s.cleartext === true) erori.push('server.cleartext e pornit (doar HTTPS în aplicație)');
  if (s.hostname && s.hostname !== 'localhost') erori.push(`server.hostname = ${JSON.stringify(s.hostname)} (de scos)`);
  for (const g of s.allowNavigation || []) {
    if (!GAZDE_PERMISE.has(g)) erori.push(`allowNavigation are "${g}", care nu e în docs/arhitectura.md`);
  }
  for (const p of ['ios', 'android']) {
    const ua = c[p] && c[p].appendUserAgent;
    const asteptat = `AdelaHaruApp/${versiune} (${p})`;
    if (ua !== asteptat) erori.push(`${p}.appendUserAgent = ${JSON.stringify(ua)}, trebuie "${asteptat}"`);
  }
  if (c.android && c.android.allowMixedContent) erori.push('android.allowMixedContent e pornit');
  if (c.appId !== 'ro.adelaharu.app') erori.push(`appId = ${JSON.stringify(c.appId)}`);
  return erori.map((e) => `${nume}: ${e}`);
}

const cai = process.argv.slice(2);
const toate = (cai.length ? cai : [null]).flatMap((cale) => verifica(...citeste(cale)));
if (toate.length) {
  for (const e of toate) console.log(`::error::${e}`);
  process.exit(1);
}
console.log(`Shell-ul e în regulă: ${URL_CONT}, offline.html, AdelaHaruApp/${versiune}.`);
