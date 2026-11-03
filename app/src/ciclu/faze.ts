/**
 * Textele celor 4 faze, copiate din ah_ciclu_faze() (reference/sn41.php), ca aplicația
 * să meargă fără internet. Dacă GET /ciclu/faze răspunde, se iau de acolo meditația și cursul potrivite.
 */
import type { CheieFaza } from './algoritm';

export interface TextFaza {
  nume: string;
  arhetip: string;
  anotimp: string;
  luna: string;
  culoare: string;
  pe_scurt: string;
  simti: string;
  ajuta: string[];
  practica: string;
  /** slugul din contract (GET /ciclu/faze) */
  slug_api: string;
}

export const FAZE: Record<CheieFaza, TextFaza> = {
  menstruala: {
    nume: 'Faza menstruală',
    arhetip: 'Alchimista',
    anotimp: 'iarna',
    luna: 'Luna nouă',
    culoare: '#8C4A3A',
    pe_scurt: 'Faza de răgaz, de introspecție și profunzime spirituală. Felul în care trăiești această fază îți stabilește cum vei fi în următoarea lună.',
    simti: 'În timpul acestei faze, femeia simte o nevoie mare de a fi singură, de a se retrage, pentru a se regenera energetic, în special în primele două zile.',
    ajuta: [
      'Introspecție, meditație, odihnă.',
      'Curățare energetică și intenții noi pentru luna care vine.',
      'Spre finele acestei perioade, e ideal să-ți setezi intenții sau chiar să-ți planifici luna care urmează.',
    ],
    practica: 'Permite-i să-și îndeplinească funcția și dă-ți voie să renaști în fiecare lună ca femeie.',
    slug_api: 'iarna',
  },
  foliculara: {
    nume: 'Faza foliculară',
    arhetip: 'Fecioara',
    anotimp: 'primăvara',
    luna: 'Luna în creștere',
    culoare: '#8F9A6E',
    pe_scurt: 'Faza cea mai dinamică a lunii, corespunzătoare Lunii în creștere. Ideală pentru noi începuturi, pentru a porni planuri și proiecte.',
    simti: 'După o perioadă de retragere și de regenerare cum este menstruația, femeia primește darul reînnoirii din punct de vedere energetic.',
    ajuta: [
      'Dinamism, claritate mentală și extravertire.',
      'Socializare, dezvoltare profesională și personală, activități sportive.',
      'Energia se orientează mai mult spre exterior, fiind încărcată cu elanul de a acționa, de a schimba lucruri, de a construi ceva nou.',
    ],
    practica: 'După fiecare menstruație, femeia se întoarce din nou la starea de virginitate și puritate.',
    slug_api: 'primavara',
  },
  ovulatorie: {
    nume: 'Faza ovulatorie',
    arhetip: 'Mama',
    anotimp: 'vara',
    luna: 'Luna plină',
    culoare: '#C29A4A',
    pe_scurt: 'Faza expresivității și a comunicării. Perioada ideală pentru a crea, pentru a asculta și a oferi sprijin, pentru profunzime în sexualitate.',
    simti: 'În această etapă femeia simte imboldul de a se dărui mai mult celorlalți. Dacă și-a trăit corespunzător perioada de regenerare, aceste activități nu o vor seca de energie, din contră, o vor încărca.',
    ajuta: [
      'Activități sociale, caritabile: a te dărui mai mult celorlalți.',
      'Este o perioadă ideală pentru a ieși în lume.',
      'Bucură-te din plin de ea: acum ai cel mai mult potențial creator.',
    ],
    practica: 'Rămâne să-ți observi cu atenție stările și să ții cont de ele.',
    slug_api: 'vara',
  },
  premenstruala: {
    nume: 'Faza premenstruală',
    arhetip: 'Fermecătoreasa',
    anotimp: 'toamna',
    luna: 'Luna în descreștere',
    culoare: '#A9714F',
    pe_scurt: 'Faza interiorizării și a inspirației, care corespunde Lunii în descreștere și toamnei. Este o etapă în care energia își schimbă direcția.',
    simti: 'Scade nivelul de concentrare și nevoia de a socializa, dar simultan crește nivelul de intuiție și de inspirație.',
    ajuta: [
      'O perioadă ideală introspecției și comunicării cu sinele superior.',
      'Idei noi îți pot da târcoale, ține doar de tine să le „seduci” și să le aduci în viața ta.',
      'Nu este un moment potrivit pentru a iniția proiecte, cât mai degrabă pentru a termina ceea ce ai început sau amânat.',
    ],
    practica: 'Dacă ne schimbăm puțin perspectiva, putem să ne bucurăm de darurile pe care aceasta le aduce.',
    slug_api: 'toamna',
  },
};

/** Slugul din API (iarna/primavara/vara/toamna sau chiar cheia sn41) ⇒ cheia fazei. */
export function cheieDinSlug(slug: string): CheieFaza | null {
  const s = slug.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
  for (const [k, f] of Object.entries(FAZE) as [CheieFaza, TextFaza][]) {
    if (s === k || s === f.slug_api) return k;
  }
  return null;
}

export const AVERTISMENT = 'Fazele sunt o estimare, nu o metodă de contracepție.';
