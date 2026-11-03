/**
 * Notificările locale de fază (@capacitor/local-notifications): la 9:00, în ziua în care
 * începe fiecare fază, pe următoarele 6 cicluri (ca alarma din fișierul .ics din sn41).
 * Se reprogramează la fiecare schimbare a datelor ciclului și la pornirea aplicației.
 */
import { Capacitor } from '@capacitor/core';
import { LocalNotifications } from '@capacitor/local-notifications';
import type { StareCiclu } from '../ciclu/algoritm';
import { ID_MAX, ID_MIN, notificariDeProgramat } from '../ciclu/notificari-faze';

export function eNativ() {
  return Capacitor.isNativePlatform();
}

export async function cerePermisiune(): Promise<boolean> {
  if (!eNativ()) return true;
  try {
    let p = await LocalNotifications.checkPermissions();
    if (p.display === 'prompt' || p.display === 'prompt-with-rationale') p = await LocalNotifications.requestPermissions();
    return p.display === 'granted';
  } catch {
    return false;
  }
}

async function anuleazaFaze() {
  try {
    const { notifications } = await LocalNotifications.getPending();
    const ale = notifications.filter((n) => n.id >= ID_MIN && n.id <= ID_MAX).map((n) => ({ id: n.id }));
    if (ale.length) await LocalNotifications.cancel({ notifications: ale });
  } catch {
    /* nimic */
  }
}

export async function programeazaFaze(S: StareCiclu | null, activ: boolean): Promise<number> {
  if (!eNativ()) return 0;
  await anuleazaFaze();
  if (!S || !activ) return 0;
  const lista = notificariDeProgramat(S, new Date());
  if (!lista.length) return 0;
  try {
    await LocalNotifications.schedule({ notifications: lista });
  } catch {
    return 0;
  }
  return lista.length;
}

export async function anuleazaToate() {
  if (eNativ()) await anuleazaFaze();
}
