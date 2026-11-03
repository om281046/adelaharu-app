import { LUNI } from '../ciclu/algoritm';

/** 600 ⇒ „10 min”, 3900 ⇒ „1 h 5 min”. */
export function durata(sec: number | null | undefined): string {
  if (!sec || sec <= 0) return '';
  const min = Math.max(1, Math.round(sec / 60));
  if (min < 60) return min + ' min';
  const h = Math.floor(min / 60);
  const r = min % 60;
  return h + ' h' + (r ? ' ' + r + ' min' : '');
}

/** 75 ⇒ „1:15”. */
export function ceas(sec: number): string {
  if (!isFinite(sec) || sec < 0) sec = 0;
  const s = Math.floor(sec);
  const h = Math.floor(s / 3600);
  const m = Math.floor((s % 3600) / 60);
  const r = ('0' + (s % 60)).slice(-2);
  return h ? h + ':' + ('0' + m).slice(-2) + ':' + r : m + ':' + r;
}

/** „2027-01-15” ⇒ „15 ianuarie 2027”. */
export function dataLunga(ymd: string | null | undefined): string {
  if (!ymd) return '';
  const p = ymd.slice(0, 10).split('-');
  if (p.length < 3) return ymd;
  return +p[2] + ' ' + LUNI[+p[1] - 1] + ' ' + p[0];
}

/** Plural românesc: 1 lecție, 2 lecții, 20 de lecții. */
export function nr(n: number, unu: string, multe: string): string {
  if (n === 1) return '1 ' + unu;
  const de = n % 100 >= 20 || (n !== 0 && n % 100 === 0) ? ' de ' : ' ';
  return n + de + multe;
}
