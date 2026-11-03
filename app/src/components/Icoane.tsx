import type { SVGProps } from 'react';

type P = SVGProps<SVGSVGElement> & { marime?: number };
const baza = ({ marime = 24, ...p }: P) => ({
  width: marime, height: marime, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor',
  strokeWidth: 1.6, strokeLinecap: 'round' as const, strokeLinejoin: 'round' as const, 'aria-hidden': true, ...p,
});

export const IcAcasa = (p: P) => <svg {...baza(p)}><path d="M3.5 10.5 12 4l8.5 6.5" /><path d="M5.5 9v10.5h13V9" /><path d="M10 19.5v-5h4v5" /></svg>;
export const IcLotus = (p: P) => <svg {...baza(p)}><path d="M12 20c-4.5 0-8-2.4-9-6 2.6-.4 5.6.4 9 6Zm0 0c4.5 0 8-2.4 9-6-2.6-.4-5.6.4-9 6Z" /><path d="M12 20c-2.2-2.6-3-5.6-2.4-9C11 12 12 13.8 12 20Zm0 0c2.2-2.6 3-5.6 2.4-9C13 12 12 13.8 12 20Z" /><path d="M12 15.5c-1.4-3-1.3-6.7 0-11 1.3 4.3 1.4 8 0 11Z" /></svg>;
export const IcCarte = (p: P) => <svg {...baza(p)}><path d="M12 6.5C10 5 7 4.5 3.5 5v13c3.5-.5 6.5 0 8.5 1.5 2-1.5 5-2 8.5-1.5V5C17 4.5 14 5 12 6.5Z" /><path d="M12 6.5v13" /></svg>;
export const IcLuna = (p: P) => <svg {...baza(p)}><circle cx="12" cy="12" r="8" /><path d="M12 4a8 8 0 0 1 0 16 5.5 8 0 0 0 0-16Z" fill="currentColor" stroke="none" opacity=".35" /></svg>;
export const IcProfil = (p: P) => <svg {...baza(p)}><circle cx="12" cy="8.5" r="3.8" /><path d="M4.5 20c1.2-3.8 4-5.5 7.5-5.5s6.3 1.7 7.5 5.5" /></svg>;
export const IcPlay = (p: P) => <svg {...baza(p)}><path d="M8 5.5v13l10.5-6.5Z" fill="currentColor" /></svg>;
export const IcPauza = (p: P) => <svg {...baza(p)}><path d="M8 5.5v13M16 5.5v13" strokeWidth={3} /></svg>;
export const IcInapoi15 = (p: P) => <svg {...baza(p)}><path d="M4.5 12a7.5 7.5 0 1 0 2.2-5.3" /><path d="M4 4v3.5h3.5" /><text x="12" y="15.3" fontSize="7.5" textAnchor="middle" fill="currentColor" stroke="none" fontFamily="Figtree, sans-serif" fontWeight="600">15</text></svg>;
export const IcInainte15 = (p: P) => <svg {...baza(p)}><path d="M19.5 12a7.5 7.5 0 1 1-2.2-5.3" /><path d="M20 4v3.5h-3.5" /><text x="12" y="15.3" fontSize="7.5" textAnchor="middle" fill="currentColor" stroke="none" fontFamily="Figtree, sans-serif" fontWeight="600">15</text></svg>;
export const IcLacat = (p: P) => <svg {...baza(p)}><rect x="5" y="10.5" width="14" height="9.5" rx="2" /><path d="M8 10.5V8a4 4 0 0 1 8 0v2.5" /></svg>;
export const IcVideo = (p: P) => <svg {...baza(p)}><rect x="3" y="6" width="13" height="12" rx="2" /><path d="m16 10.5 5-3v9l-5-3" /></svg>;
export const IcDescarca = (p: P) => <svg {...baza(p)}><path d="M12 4v11M7.5 10.5 12 15l4.5-4.5" /><path d="M5 19.5h14" /></svg>;
export const IcBifa = (p: P) => <svg {...baza(p)}><path d="m5 12.5 4.5 4.5L19 7.5" /></svg>;
export const IcSageataSt = (p: P) => <svg {...baza(p)}><path d="M15 5 8 12l7 7" /></svg>;
export const IcSageataDr = (p: P) => <svg {...baza(p)}><path d="m9 5 7 7-7 7" /></svg>;
export const IcInchide = (p: P) => <svg {...baza(p)}><path d="M6 6l12 12M18 6 6 18" /></svg>;
export const IcPdf = (p: P) => <svg {...baza(p)}><path d="M6 3.5h8l4.5 4.5v12.5H6Z" /><path d="M14 3.5V8h4.5" /><path d="M9 13h6M9 16.5h4" /></svg>;
export const IcText = (p: P) => <svg {...baza(p)}><path d="M5 6h14M5 10h14M5 14h10M5 18h7" /></svg>;
export const IcSunet = (p: P) => <svg {...baza(p)}><path d="M4 9.5v5h3.5L12 18.5v-13L7.5 9.5Z" /><path d="M15.5 9a4 4 0 0 1 0 6M18 6.5a7.5 7.5 0 0 1 0 11" /></svg>;
export const IcClopot = (p: P) => <svg {...baza(p)}><path d="M6 16.5V11a6 6 0 0 1 12 0v5.5l1.5 2h-15Z" /><path d="M10 20.5a2 2 0 0 0 4 0" /></svg>;
export const IcCos = (p: P) => <svg {...baza(p)}><path d="M4.5 7h15M9.5 7V4.5h5V7M6.5 7l1 13h9l1-13" /></svg>;
