import { Capacitor } from '@capacitor/core';
import { Device } from '@capacitor/device';
import type { Dispozitiv } from '../api/types';
import { CHEI, citeste, scrie } from './stocare';

function uuid(): string {
  if (typeof crypto !== 'undefined' && 'randomUUID' in crypto) return crypto.randomUUID();
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
    const r = (Math.random() * 16) | 0;
    return (c === 'x' ? r : (r & 0x3) | 0x8).toString(16);
  });
}

export function platforma(): 'ios' | 'android' | 'web' {
  const p = Capacitor.getPlatform();
  return p === 'ios' || p === 'android' ? p : 'web';
}

export async function dispozitiv(): Promise<Dispozitiv> {
  let id = await citeste<string | null>(CHEI.dispozitiv, null);
  if (!id) {
    id = uuid();
    await scrie(CHEI.dispozitiv, id);
  }
  let nume = 'Telefon';
  try {
    const info = await Device.getInfo();
    nume = info.name || info.model || nume;
  } catch {
    /* nimic */
  }
  return { id, nume: nume.slice(0, 60), platforma: platforma() };
}
