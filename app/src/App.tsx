import { useEffect } from 'react';
import { App as CapApp } from '@capacitor/app';
import { Capacitor } from '@capacitor/core';
import { LocalNotifications } from '@capacitor/local-notifications';
import { MiniPlayer, useStarePlayer } from './components/MiniPlayer';
import { Taburi } from './components/Taburi';
import { citesteCiclu, citesteSetari } from './lib/ciclu-local';
import { codPrimit } from './lib/legaturi';
import { programeazaFaze } from './lib/notificari';
import { candSeAtingeNotificarea, inregistreazaPush, pushDisponibil } from './lib/push';
import { inapoiSistem, mergi, useRuta } from './lib/ruta';
import { SesiuneProvider, useSesiune } from './lib/sesiune';
import { Acasa } from './screens/Acasa';
import { Ciclu } from './screens/Ciclu';
import { Curs, Cursuri, Lectie } from './screens/Cursuri';
import { InnerTemple } from './screens/InnerTemple';
import { Intrare } from './screens/Intrare';
import { Meditatie } from './screens/Meditatie';
import { Profil } from './screens/Profil';

function Ecran({ r }: { r: string[] }) {
  const [tab, a, b, c] = r;
  switch (tab) {
    case 'inner-temple':
      return a === 'm' && b ? <Meditatie id={+b} /> : <InnerTemple />;
    case 'cursuri':
      if (a && b === 'l' && c) return <Lectie slug={a} cheie={c} />;
      return a ? <Curs slug={a} /> : <Cursuri />;
    case 'ciclu':
      return <Ciclu />;
    case 'profil':
      return <Profil />;
    default:
      return <Acasa />;
  }
}

function Cadru() {
  const { gata, token, faraCont, vreiSaIntri } = useSesiune();
  const r = useRuta();
  const player = useStarePlayer();
  const cheie = r.join('/');

  useEffect(() => {
    window.scrollTo({ top: 0 });
  }, [cheie]);

  // linkul cu codul vine când aplicația e deschisă fără cont: arătăm ecranul de intrare.
  // Se reverifică și când pornirea s-a terminat (gata), ca citirea „fără cont” să nu anuleze linkul.
  useEffect(() => {
    const f = () => {
      if (!token) vreiSaIntri();
    };
    if (codPrimit()) f();
    window.addEventListener('ah-cod', f);
    return () => window.removeEventListener('ah-cod', f);
  }, [token, gata, vreiSaIntri]);

  useEffect(() => {
    if (token && pushDisponibil()) void citesteSetari().then((s) => inregistreazaPush(s.subiecte));
  }, [token]);

  if (!gata) return null;
  if (!token && !faraCont) return <Intrare />;

  const peMeditatie = player.curent && ('#/' + cheie === player.curent.ruta);
  const arataMini = !!player.curent && !peMeditatie;
  return (
    <div className="aplicatie">
      <main className={'ecran' + (arataMini ? ' cu-mini' : '')} key={cheie}>
        <Ecran r={r} />
      </main>
      {arataMini && <MiniPlayer />}
      <Taburi activ={r[0]} adanc={r.length > 1} />
    </div>
  );
}

let pornit = false;
function pornesteOData() {
  if (pornit) return;
  pornit = true;
  if (Capacitor.isNativePlatform()) {
    void CapApp.addListener('backButton', () => {
      if (!inapoiSistem()) void CapApp.minimizeApp();
    });
    void LocalNotifications.addListener('localNotificationActionPerformed', (a) => {
      const ruta = (a.notification.extra as { ruta?: string } | undefined)?.ruta;
      if (ruta) mergi(ruta.replace(/^#\//, ''));
    });
    // la fiecare pornire, notificările fazelor se reprogramează pe următoarele 6 cicluri
    void Promise.all([citesteCiclu(), citesteSetari()]).then(([S, s]) => programeazaFaze(S, s.notifFaze));
  }
  candSeAtingeNotificarea((ruta) => mergi(ruta.replace(/^#\//, '')));
}

export default function App() {
  pornesteOData();
  return (
    <SesiuneProvider>
      <Cadru />
    </SesiuneProvider>
  );
}
