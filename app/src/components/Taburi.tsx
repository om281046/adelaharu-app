import { IcAcasa, IcCarte, IcLotus, IcLuna, IcProfil } from './Icoane';
import { mergi, type Tab } from '../lib/ruta';

const T: { tab: Tab; nume: string; Ic: typeof IcAcasa }[] = [
  { tab: 'acasa', nume: 'Acasă', Ic: IcAcasa },
  { tab: 'inner-temple', nume: 'Inner Temple', Ic: IcLotus },
  { tab: 'cursuri', nume: 'Cursurile mele', Ic: IcCarte },
  { tab: 'ciclu', nume: 'Ciclul meu', Ic: IcLuna },
  { tab: 'profil', nume: 'Profil', Ic: IcProfil },
];

export function Taburi({ activ, adanc }: { activ: string; adanc: boolean }) {
  return (
    <nav className="taburi" aria-label="Meniul principal">
      {T.map(({ tab, nume, Ic }) => (
        <button key={tab} type="button" aria-current={activ === tab ? 'page' : undefined} onClick={() => (activ === tab && !adanc ? window.scrollTo({ top: 0, behavior: 'smooth' }) : mergi(tab, true))}>
          <Ic marime={25} />
          <span>{nume}</span>
        </button>
      ))}
    </nav>
  );
}
