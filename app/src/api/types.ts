/** Tipurile din docs/api-contract.md (v1). */

export type CodEroare =
  | 'neautentificat' | 'fara_acces' | 'negasit' | 'prea_multe' | 'cod_gresit'
  | 'cod_expirat' | 'dispozitive' | 'invalid' | 'server' | 'retea';

export interface Utilizatoare {
  email: string;
  prenume: string;
}

export interface Dispozitiv {
  id: string;
  nume: string;
  platforma: 'ios' | 'android' | 'web';
}

export interface VerificaRaspuns {
  token: string;
  utilizatoare: Utilizatoare;
}

export interface Config {
  versiune_minima: string;
  contact: { email: string; whatsapp: string };
  linkuri: { site: string; confidentialitate: string; termeni: string };
  mesaj: { titlu: string; text: string } | null;
}

export interface CursScurt {
  slug: string;
  nume: string;
  imagine: string;
  pana_la: string | null;
  progres: number;
  module_total?: number;
  module_terminate?: number;
}

export interface Eu {
  email: string;
  prenume: string;
  inner_temple: { activ: boolean; pana_la: string | null; tip: 'lunar' | 'anual' | null; se_reinnoieste: boolean };
  meditatii_cumparate: number;
  cursuri: CursScurt[];
  ciclu: { salvat_pe_site: boolean; emailuri_faze: boolean };
}

export interface Categorie {
  slug: string;
  nume: string;
}

export interface Meditatie {
  id: number;
  titlu: string;
  text: string;
  poza: string;
  durata: number;
  categorie: string;
  acces: 'abonament' | 'cumparata' | 'gratuita';
  poate_asculta: boolean;
  video: { tip: 'vimeo'; embed: string } | null;
  pozitie: number;
  noua: boolean;
}

export interface MeditatiiRaspuns {
  categorii: Categorie[];
  meditatii: Meditatie[];
}

export interface Redare {
  url: string;
  expira: string;
}

export type TipLectie = 'video' | 'audio' | 'pdf' | 'text';

export interface Lectie {
  cheie: string;
  titlu: string;
  tip: TipLectie;
  durata: number | null;
  player: string | null;
  fisier: string | null;
  text: string | null;
  terminata: boolean;
}

export interface Modul {
  id: string;
  titlu: string;
  deschis: boolean;
  se_deschide_la: string | null;
  lectii: Lectie[];
}

export interface Curs {
  slug: string;
  nume: string;
  imagine: string;
  descriere: string;
  module: Modul[];
}

export interface FazaApi {
  slug: string;
  arhetip: string;
  anotimp: string;
  culoare: string;
  ce_se_intampla: string;
  ce_e_potrivit: string;
  meditatie_id: number | null;
  curs_slug: string | null;
}

export interface FazeRaspuns {
  faze: FazaApi[];
  regula: string;
  avertisment: string;
}

export interface CicluServer {
  prima_zi: string;
  zile_sangerare: number;
  zile_ciclu: number;
  emailuri_faze: boolean;
}

export interface Ok {
  ok: true;
}

export interface SubiectePush {
  meditatii_noi: boolean;
  cursuri: boolean;
}
