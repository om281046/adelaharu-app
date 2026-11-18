/**
 * Pagina offline a aplicației (server.errorPath din capacitor.config.ts).
 * Se deschide când Spațiul meu nu se poate încărca. Arată, fără cont și fără rețea:
 *   1. faza de azi din Preferences `ah_ciclu` (aceeași regulă ca Ciclul meu din sn235);
 *   2. meditațiile descărcate din Preferences `ah_descarcate`, cu player;
 *   3. butonul „Încearcă din nou”, cu motivul (fără internet / site-ul în lucru / WebView vechi): legatura.ts.
 * Textele noi sunt CIORNĂ (de citit de Adela) și sunt marcate așa mai jos.
 */
import './fonturi.css';
import './stil.css';

import { adresaFisier, ascunde_splash, bara_stare, eroareNativa, existaFisier, laInapoi, mediaOpreste, platforma, preferinta, uriNativ } from './cap';
import { TEXTE, intoarceri, motivInitial, pauza, verifica, versiuneWebView, type Motiv } from './legatura';
import { F, SIT, azi0, citesteJson, data, situatie, ziua, type ZiCiclu } from './ciclu';
import { ceas, citesteDescarcate, dosarImplicit, durataScurta, type Descarcata } from './descarcate';
import { Player, SALT, type Piesa } from './player';

export const SITE = 'https://www.adelaharu.ro/spatiul-meu/';

const esc = (s: unknown) =>
  String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c] as string);

const g = (id: string) => document.getElementById(id);

/* ---------- iconițe (aceeași linie subțire ca pe site) ---------- */
const IC: Record<string, string> = {
  play: '<path d="M8 5.5v13l10.5-6.5z" fill="currentColor" stroke="none"/>',
  pauza: '<rect x="7" y="5.5" width="3.4" height="13" rx="1" fill="currentColor" stroke="none"/><rect x="13.6" y="5.5" width="3.4" height="13" rx="1" fill="currentColor" stroke="none"/>',
  inapoi: '<path d="M4.5 12a7.5 7.5 0 1 0 2.2-5.3"/><path d="M4.5 3.5v3.7h3.7"/><text x="12" y="15.2" text-anchor="middle" font-size="7.2" fill="currentColor" stroke="none" font-family="Figtree,system-ui,sans-serif" font-weight="500">15</text>',
  inainte: '<path d="M19.5 12a7.5 7.5 0 1 1-2.2-5.3"/><path d="M19.5 3.5v3.7h-3.7"/><text x="12" y="15.2" text-anchor="middle" font-size="7.2" fill="currentColor" stroke="none" font-family="Figtree,system-ui,sans-serif" font-weight="500">15</text>',
  inchide: '<path d="M6 6l12 12M18 6L6 18"/>',
  fararetea: '<path d="M1.8 8.6a15 15 0 0 1 20.4 0"/><path d="M5.2 12.2a10 10 0 0 1 13.6 0"/><path d="M8.6 15.8a5 5 0 0 1 6.8 0"/><circle cx="12" cy="19.4" r="1.1" fill="currentColor" stroke="none"/><path d="M4 3.5l16 17" stroke-width="1.8"/>',
  meditatii:
    '<path d="M12 20c-4.5 0-8-2.4-9-6 2.6-.4 5.6.4 9 6Zm0 0c4.5 0 8-2.4 9-6-2.6-.4-5.6.4-9 6Z"/><path d="M12 20c-2.2-2.6-3-5.6-2.4-9C11 12 12 13.8 12 20Zm0 0c2.2-2.6 3-5.6 2.4-9C13 12 12 13.8 12 20Z"/><path d="M12 15.5c-1.4-3-1.3-6.7 0-11 1.3 4.3 1.4 8 0 11Z"/>',
  ciclu: '<circle cx="12" cy="12" r="8"/><path d="M12 4a8 8 0 0 1 0 16" fill="currentColor" stroke="none" opacity=".18"/><circle cx="12" cy="4" r="1.6" fill="currentColor" stroke="none"/>',
};
const ic = (n: string, cls = 'ic') => `<svg class="${cls}" viewBox="0 0 24 24" aria-hidden="true">${IC[n] || ''}</svg>`;

/* ---------- Ciclul: inelul, la fel ca în Ciclul meu ---------- */
function inel(z: ZiCiclu): string {
  const Fz = F[z.f];
  const R0 = 86;
  const C0 = 110;
  const unghi = (zi: number) => ((zi - 1) / z.L) * 2 * Math.PI - Math.PI / 2;
  const pt = (zi: number, r: number) => {
    const a = unghi(zi);
    return [C0 + r * Math.cos(a), C0 + r * Math.sin(a)];
  };
  const arcuri = z.fz
    .map((p) => {
      const a = pt(p.a, R0);
      const b = pt(p.b + 1, R0);
      const mare = (p.b + 1 - p.a) / z.L > 0.5 ? 1 : 0;
      return `<path d="M${a[0].toFixed(1)} ${a[1].toFixed(1)}A${R0} ${R0} 0 ${mare} 1 ${b[0].toFixed(1)} ${b[1].toFixed(1)}" stroke="${F[p.f].c}" stroke-width="14" fill="none" stroke-linecap="butt" opacity="${p.f === z.f ? 1 : 0.35}"/>`;
    })
    .join('');
  const m = pt(Math.min(z.zi, z.L) + 0.5, R0);
  return `<svg viewBox="0 0 220 220" class="ci-inel" role="img" aria-label="Ziua ${z.zi} din ${z.L}, ${esc(Fz.nume)}">${arcuri}<circle cx="${m[0].toFixed(1)}" cy="${m[1].toFixed(1)}" r="11" fill="var(--card)" stroke="${Fz.c}" stroke-width="4"/><text x="110" y="100" text-anchor="middle" class="ci-zi">Ziua ${z.zi}</text><text x="110" y="126" text-anchor="middle" class="ci-din">din ${z.L}</text></svg>`;
}

/** „peste 5 zile”, „peste 25 de zile”: de la 20 în sus româna cere „de” (urm ≤ 39 aici, deci fără cazul 101–119). */
export const peste = (n: number) => `peste ${n} ${n >= 20 ? 'de ' : ''}zile`;

/** Blocul „Ciclul meu”, din datele salvate de punte în Preferences. */
export function htmlCiclu(brut: unknown, azi: number): string {
  const sit = situatie(brut);
  const S = SIT[sit];
  const z = ziua(brut, azi);
  const cap = (h: string) => `<section class="sectiune" aria-labelledby="t-ciclu"><p class="et" id="t-ciclu">${ic('ciclu', 'ic mic')}Ciclul meu</p>${h}</section>`;
  if (sit === 'sarcina') return cap(`<div class="dar ci-pauza"><h2>Ciclul tău e în pauză</h2><p>${esc(S.txt)}</p></div>`);
  if (!z) {
    // CIORNĂ (text nou pentru Adela)
    return cap(
      `<div class="dar gol"><p>Când notezi în Ciclul meu prima zi a menstruației, aici vei vedea anotimpul tău interior, chiar și fără internet.</p></div>`,
    );
  }
  if (sit === 'hormonal') {
    return cap(
      `<div class="dar ci-azi ci-hormonal" style="--ci:#964E46"><div class="ci-txt"><p class="ci-faz">Contracepție hormonală</p><h2>Ziua ${z.zi}</h2><p class="ci-tema">de la începutul ultimei sângerări</p><p class="ci-urm">${esc(S.txt)}</p></div></div>`,
    );
  }
  const Fz = F[z.f];
  const urm = z.urm - azi;
  const urmTxt = z.peste
    ? 'Menstruația ar fi trebuit să înceapă. Când începe, o poți nota în Ciclul meu.' // CIORNĂ (adaptat: aici nu e butonul de pe site)
    : `Următoarea menstruație: în jur de ${data(z.urm)} (${urm === 1 ? 'mâine' : peste(urm)}).`;
  const nota = S.nota ? `<p class="ci-sit">${esc(S.nota)}</p>` : '';
  return cap(
    `<div class="dar ci-azi" style="--ci:${Fz.c}">${inel(z)}<div class="ci-txt"><p class="ci-faz">${esc(Fz.faza)}</p><h2>${esc(Fz.nume)}</h2><p class="ci-tema">${esc(Fz.tema)}</p><p class="ci-urm">${esc(urmTxt)}</p></div></div>${nota}`,
  );
}

/* ---------- Meditațiile descărcate ---------- */

interface Rand {
  d: Descarcata;
  src: string | null;
  poza: string | null;
  pozaNativ: string | null;
  lipseste: boolean;
}

function htmlRand(r: Rand): string {
  const d = r.d;
  const durata = durataScurta(d.durata);
  const img = r.poza ? `<span class="med-img" style="background-image:url('${esc(r.poza)}')"></span>` : `<span class="med-img gol">${ic('meditatii')}</span>`;
  if (r.lipseste || !r.src) {
    // CIORNĂ
    return `<li class="med lipsa">${img}<span class="t"><b>${esc(d.titlu)}</b><span>Nu mai găsim fișierul pe telefon. Îl poți descărca din nou când ai internet.</span></span></li>`;
  }
  return `<li class="med"><button type="button" class="med-b" data-id="${esc(d.id)}" aria-label="Ascultă ${esc(d.titlu)}">${img}<span class="t"><b>${esc(d.titlu)}</b><span>${durata ? esc(durata) + ' · ' : ''}descărcată pe telefon</span></span><span class="med-pp" aria-hidden="true">${ic('play')}</span></button></li>`;
}

export function htmlMeditatii(randuri: Rand[]): string {
  const cap = (h: string) =>
    `<section class="sectiune" aria-labelledby="t-med"><p class="et" id="t-med">${ic('meditatii', 'ic mic')}Inner Temple</p><h2 class="sub">Meditațiile tale descărcate</h2>${h}</section>`;
  if (!randuri.length) {
    // CIORNĂ
    return cap(
      `<div class="dar gol"><p>Nu ai încă meditații descărcate. Când ai internet, apasă „Descarcă” lângă o meditație din Inner Temple și o vei putea asculta oriunde, chiar și fără conexiune.</p></div>`,
    );
  }
  return cap(`<ul class="lista" id="med-lista">${randuri.map(htmlRand).join('')}</ul>`);
}

function htmlPlayer(): string {
  return `<div class="player" id="player" hidden role="region" aria-label="Player">
  <div class="pl-sus"><span class="pl-img" id="pl-img"></span><span class="pl-t"><b id="pl-titlu"></b><span id="pl-msg" aria-live="polite"></span></span>
  <button type="button" class="pl-x" id="pl-x" aria-label="Închide playerul">${ic('inchide')}</button></div>
  <input type="range" id="pl-poz" min="0" max="100" step="1" value="0" aria-label="Poziția în meditație">
  <div class="pl-timp"><span id="pl-t0">0:00</span><span id="pl-t1">0:00</span></div>
  <p class="pl-revenit" id="pl-revenit" hidden><span>Conexiunea a revenit.</span><button type="button" id="pl-deschide">Deschide Spațiul meu</button></p>
  <div class="pl-but">
   <button type="button" id="pl-inapoi" aria-label="Înapoi ${SALT} secunde">${ic('inapoi')}</button>
   <button type="button" id="pl-pp" class="pl-pp" aria-label="Ascultă">${ic('play')}</button>
   <button type="button" id="pl-inainte" aria-label="Înainte ${SALT} secunde">${ic('inainte')}</button>
  </div></div>`;
}

/* ---------- „Încearcă din nou”, fără bucle (legatura.ts) ---------- */

/** Playerul paginii (unul singur); îl ținem aici ca revenirea conexiunii să nu întrerupă o meditație. */
let player: Player | null = null;

const ss = {
  ia(k: string): number {
    try {
      return Number(sessionStorage.getItem(k) || 0) || 0;
    } catch {
      return 0;
    }
  },
  pune(k: string, v: number) {
    try {
      sessionStorage.setItem(k, String(v));
    } catch {
      /* fără sessionStorage: fără pauze ținute minte */
    }
  },
};

/** Câte întoarceri rapide (pagina s-a deschis iar la scurt timp după plecarea spre site): pauza automată crește. */
let nIntors = 0;
let motivAcum: Exclude<Motiv, 'ok'> = 'fara_internet';

/** Plecăm spre Spațiul meu: întâi oprim sunetul și notificarea AhMedia (site-ul nu le mai ascultă). */
function deschideSite() {
  player?.opreste();
  mediaOpreste();
  ss.pune('ah_off_plecat', Date.now());
  ss.pune('ah_off_n', nIntors);
  location.replace(SITE);
}

/** Textele de sus după motiv (fără internet / site în lucru / WebView vechi). */
function arataMotiv(m: Exclude<Motiv, 'ok'>) {
  motivAcum = m;
  const t = TEXTE[m];
  const h = g('off-titlu');
  if (h && h.textContent !== t.titlu) h.textContent = t.titlu;
  const p = g('off-text');
  if (p && p.textContent !== t.text) p.textContent = t.text;
  document.documentElement.dataset.motiv = m;
}

/** Conexiunea a revenit în timp ce ascultă: nu o scoatem din meditație, doar îi spunem, discret. CIORNĂ */
function arataRevenit() {
  const r = g('pl-revenit');
  if (r) r.hidden = false;
  const b = g('reincearca');
  if (b) b.textContent = 'Deschide Spațiul meu';
  const m = g('re-msg');
  if (m) m.textContent = 'Conexiunea a revenit.';
}

let ceasAuto: ReturnType<typeof setTimeout> | null = null;
/** Următoarea încercare automată, după pauza curentă (crește la fiecare eșec și la fiecare întoarcere rapidă). */
function programeaza() {
  if (ceasAuto) clearTimeout(ceasAuto);
  if (motivAcum === 'webview') return; // telefonul are nevoie de o actualizare: fără încercări automate
  ceasAuto = setTimeout(() => void incearcaDinNou(true), pauza(nIntors) * 1000);
}

let incerca: Promise<void> | null = null;
function incearcaDinNou(automat = false): Promise<void> {
  if (incerca) return incerca;
  const b = g('reincearca') as HTMLButtonElement | null;
  const m = g('re-msg');
  if (b) b.disabled = true;
  if (m && !automat) m.textContent = 'Verificăm conexiunea…'; // CIORNĂ
  incerca = verifica((u, o) => fetch(u, o)).then((r) => {
    incerca = null;
    if (b) b.disabled = false;
    if (r === 'ok' && automat && player?.stare.piesa) {
      arataRevenit();
      return;
    }
    if (r === 'ok' && motivAcum !== 'webview') {
      deschideSite();
      return;
    }
    if (r !== 'ok') arataMotiv(r);
    nIntors++;
    programeaza();
    // CIORNĂ
    if (m) m.textContent = automat ? '' : r === 'site' ? 'Site-ul încă nu răspunde. Încercăm din nou singuri, în câteva minute.' : r === 'ok' ? '' : 'Încă nu avem conexiune. Mai încearcă puțin mai târziu.';
  });
  return incerca;
}

/* ---------- pornire ---------- */

export async function porneste(): Promise<void> {
  const rad = g('app');
  if (!rad) return;
  const intunecat = !!window.matchMedia?.('(prefers-color-scheme: dark)').matches;
  bara_stare(intunecat);
  document.documentElement.dataset.platforma = platforma();

  const [cicluBrut, descBrut] = await Promise.all([preferinta('ah_ciclu'), preferinta('ah_descarcate')]);
  const lista = citesteDescarcate(citesteJson(descBrut), dosarImplicit(platforma()));
  const randuri: Rand[] = await Promise.all(
    lista.map(async (d) => {
      const [src, poza, pozaNativ, exista] = await Promise.all([
        adresaFisier(d.fisier, d.dir),
        d.poza ? adresaFisier(d.poza, d.dir) : Promise.resolve(null),
        d.poza ? uriNativ(d.poza, d.dir) : Promise.resolve(null),
        existaFisier(d.fisier, d.dir),
      ]);
      return { d, src, poza, pozaNativ, lipseste: exista === false };
    }),
  );

  const ciclu = citesteJson(cicluBrut);
  // de ce suntem aici: ?cod= (iOS) / eroarea ținută de AhApp (Android) / WebView prea vechi; apoi întoarcerile rapide
  const acum = Date.now();
  nIntors = intoarceri(ss.ia('ah_off_plecat'), ss.ia('ah_off_n'), acum);
  ss.pune('ah_off_plecat', 0);
  const vWv = versiuneWebView(navigator.userAgent);
  const m0: Exclude<Motiv, 'ok'> = platforma() === 'android' && vWv > 0 && vWv < 90 ? 'webview' : motivInitial(location.search, await eroareNativa(), acum);
  const t0 = TEXTE[m0];
  // CIORNĂ: titlul și textul de sus
  rad.innerHTML = `
  <header class="antet">
   <p class="marca">Adela Haru</p>
   <div class="dar offline">
    <span class="ic-mare">${ic('fararetea')}</span>
    <h1 id="off-titlu">${esc(t0.titlu)}</h1>
    <p id="off-text">${esc(t0.text)}</p>
    <button type="button" class="btn" id="reincearca">${esc(t0.buton)}</button>
    <p class="mic" id="re-msg" aria-live="polite"></p>
   </div>
  </header>
  <main>
   ${htmlCiclu(ciclu, azi0())}
   ${htmlMeditatii(randuri)}
  </main>
  ${htmlPlayer()}`;

  arataMotiv(m0);
  g('reincearca')?.addEventListener('click', () => void incearcaDinNou());
  window.addEventListener('online', () => void incearcaDinNou(true));
  // prima încercare automată după pauză (5 s; mai lung după întoarceri rapide): fără buclă site ↔ pagina offline
  programeaza();
  // la plecarea din pagină (orice navigare), notificarea Android a playerului nu trebuie să rămână „în redare”
  window.addEventListener('pagehide', () => mediaOpreste());

  legaPlayer(randuri);
  // Android, Înapoi: întâi închide playerul deschis, altfel aplicația trece în fundal (fără bucla spre site)
  void laInapoi(() => {
    if (player?.stare.piesa) {
      player.opreste();
      return true;
    }
    return false;
  });
  ascunde_splash();
}

function legaPlayer(randuri: Rand[]) {
  const el = g('player');
  if (!el) return;
  const P = new Player();
  player = P;
  (window as unknown as { ahOfflinePlayer?: Player }).ahOfflinePlayer = P;
  const poz = g('pl-poz') as HTMLInputElement;
  let tragere = false;

  document.querySelectorAll<HTMLButtonElement>('.med-b').forEach((b) => {
    b.addEventListener('click', () => {
      const r = randuri.find((x) => x.d.id === b.dataset.id);
      if (!r || !r.src) return;
      if (P.stare.piesa?.id === r.d.id) {
        P.comuta();
        return;
      }
      const p: Piesa = { id: r.d.id, titlu: r.d.titlu, src: r.src, poza: r.poza || undefined, pozaNativ: r.pozaNativ || undefined, durata: r.d.durata };
      void P.reda(p);
    });
  });
  g('pl-pp')?.addEventListener('click', () => P.comuta());
  g('pl-inapoi')?.addEventListener('click', () => P.sari(-SALT));
  g('pl-inainte')?.addEventListener('click', () => P.sari(SALT));
  g('pl-x')?.addEventListener('click', () => P.opreste());
  g('pl-deschide')?.addEventListener('click', () => deschideSite());
  poz.addEventListener('input', () => {
    tragere = true;
    const t0 = g('pl-t0');
    if (t0) t0.textContent = ceas(+poz.value);
  });
  poz.addEventListener('change', () => {
    tragere = false;
    P.mergiLa(+poz.value);
  });

  P.asculta((s) => {
    el.hidden = !s.piesa;
    document.body.classList.toggle('cu-player', !!s.piesa);
    document.querySelectorAll<HTMLButtonElement>('.med-b').forEach((b) => {
      const acum = !!s.piesa && b.dataset.id === s.piesa.id;
      b.classList.toggle('acum', acum);
      const pp = b.querySelector('.med-pp');
      if (pp) pp.innerHTML = ic(acum && s.canta ? 'pauza' : 'play');
      b.setAttribute('aria-pressed', acum && s.canta ? 'true' : 'false');
    });
    if (!s.piesa) return;
    const titlu = g('pl-titlu');
    if (titlu && titlu.textContent !== s.piesa.titlu) titlu.textContent = s.piesa.titlu;
    const img = g('pl-img');
    if (img) img.style.backgroundImage = s.piesa.poza ? `url('${s.piesa.poza.replace(/'/g, '%27')}')` : '';
    const pp = g('pl-pp');
    if (pp) {
      const html = ic(s.canta ? 'pauza' : 'play');
      if (pp.innerHTML !== html) pp.innerHTML = html;
      pp.setAttribute('aria-label', s.canta ? 'Pauză' : 'Ascultă');
    }
    const msg = g('pl-msg');
    // CIORNĂ
    if (msg) msg.textContent = s.eroare ? 'Nu am putut porni meditația. Încearcă din nou sau descarc-o încă o dată când ai internet.' : s.seIncarca && !s.canta ? 'Se pregătește…' : '';
    el.classList.toggle('eroare', s.eroare);
    const d = Math.max(0, Math.floor(s.durata || 0));
    if (+poz.max !== (d || 100)) poz.max = String(d || 100);
    if (!tragere) poz.value = String(Math.floor(s.timp));
    poz.style.setProperty('--p', d ? `${Math.min(100, (s.timp / d) * 100)}%` : '0%');
    const t0 = g('pl-t0');
    const t1 = g('pl-t1');
    if (t0 && !tragere) t0.textContent = ceas(s.timp);
    if (t1) t1.textContent = d ? ceas(d) : '–:––';
  });
}


if (typeof document !== 'undefined' && document.getElementById('app')) void porneste();
