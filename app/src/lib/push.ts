/**
 * Notificările push (FCM). Se înregistrează DOAR dacă aplicația a fost construită cu VITE_PUSH=1,
 * adică după ce google-services.json (Android) și GoogleService-Info.plist + capabilitatea Push (iOS)
 * au fost puse în proiectele native. Fără ele, PushNotifications.register() oprește aplicația pe Android.
 */
import { Capacitor } from '@capacitor/core';
import { PushNotifications } from '@capacitor/push-notifications';
import { api } from '../api';
import type { SubiectePush } from '../api/types';
import { platforma } from './dispozitiv';
import { CHEI, citeste, scrie, sterge } from './stocare';

export const PUSH_ACTIV = import.meta.env.VITE_PUSH === '1';

export function pushDisponibil(): boolean {
  return PUSH_ACTIV && Capacitor.isNativePlatform();
}

let ascultatoriPusi = false;
let laAtingere: ((ruta: string) => void) | null = null;

export function candSeAtingeNotificarea(f: (ruta: string) => void) {
  laAtingere = f;
}

export async function inregistreazaPush(subiecte: SubiectePush): Promise<boolean> {
  if (!pushDisponibil()) return false;
  try {
    let p = await PushNotifications.checkPermissions();
    if (p.receive === 'prompt' || p.receive === 'prompt-with-rationale') p = await PushNotifications.requestPermissions();
    if (p.receive !== 'granted') return false;
    if (!ascultatoriPusi) {
      ascultatoriPusi = true;
      await PushNotifications.addListener('registration', async ({ value }) => {
        await scrie(CHEI.pushToken, value);
        const pl = platforma();
        if (pl === 'web') return;
        const s = await citeste<{ subiecte?: SubiectePush }>(CHEI.setari, {});
        api.push(value, pl, s.subiecte || subiecte).catch(() => {});
      });
      await PushNotifications.addListener('pushNotificationActionPerformed', (a) => {
        const ruta = (a.notification.data as { ruta?: string } | undefined)?.ruta;
        if (ruta && laAtingere) laAtingere(ruta);
      });
    }
    await PushNotifications.register();
    return true;
  } catch {
    return false;
  }
}

/** Trimite din nou subiectele alese (același token). */
export async function actualizeazaSubiecte(subiecte: SubiectePush) {
  const t = await citeste<string | null>(CHEI.pushToken, null);
  const pl = platforma();
  if (!t || pl === 'web') return;
  await api.push(t, pl, subiecte);
}

export async function dezinregistreazaPush() {
  const t = await citeste<string | null>(CHEI.pushToken, null);
  if (t) await api.stergePush(t).catch(() => {});
  await sterge(CHEI.pushToken);
  if (pushDisponibil()) {
    try {
      await PushNotifications.unregister();
    } catch {
      /* nimic */
    }
  }
}
