# Aplicația Adela Haru, v2: planul pe o pagină

Pentru Mircea, 18 nov. 2026. Detaliile tehnice: `arhitectura.md`. Pașii de lansare: `release.md`.

## Ce s-a schimbat față de v1 și de ce

| | v1 (3 nov.) | v2 (acum) |
|---|---|---|
| Ecranele | refăcute în aplicație (React): meditații, cursuri, ciclu, profil | **Spațiul meu de pe site**, deschis în aplicație |
| Serverul | un API nou (`ah-app/v1`), intrare proprie, tabele noi | un singur fragment nou, `ah-sp-aplicatie.php`, care lucrează **doar** pentru aplicație |
| Conținut nou (meditații, module, înregistrări) | trebuia legat în API și uneori în aplicație | apare singur, fără versiune nouă |
| Ce întreținem | două interfețe (site + aplicație) care se despart în timp | una singură: Spațiul meu |

De ce: între timp Spațiul meu (235 + 266, 281, 265, 295) a ajuns să facă tot ce făcea v1 și mai mult (Ciclul meu cu
notițe și linkul pentru el, podcast, întâlniri, abonamente). O a doua interfață ar fi rămas mereu în urmă. v2 păstrează
din v1 doar ce merge: proiectul nativ, construirea automată, documentele pentru magazine, trimiterea FCM.

## Ce face fiecare parte

- **Aplicația (shell-ul Capacitor):** deschide `www.adelaharu.ro/spatiul-meu/`, se recunoaște prin User-Agent
  (`AdelaHaruApp/2.0.0`), ține în aplicație doar paginile contului și Vimeo; restul (Zoom, WhatsApp, Spotify) se
  deschide în aplicațiile telefonului. Are o **pagină offline locală** (faza de azi + meditațiile descărcate) și două
  pluginuri mici (sunet pe ecranul blocat pe Android, pentru meditațiile ascultate online și pentru cele descărcate;
  informații despre build).
- **Fragmentul de pe site:** în aplicație ascunde tot ce ține de cumpărare (prețuri, Comenzi, portalul Stripe, card
  cadou, campanii), trimite înapoi în cont orice pagină din afară și adaugă „puntea” spre telefon: notificări push,
  „Descarcă” la meditații, mementourile fazelor ciclului, ecranul blocat, „Șterge contul”. Plus setările, linkurile
  universale și trimiterea notificărilor în loturi. **În browser nu schimbă nimic**; se oprește prin dezactivare.
- **Fragmentul „Pentru el”** (`ah-sp-partener.php`, decizia din 18 nov.): un cont simplu pentru partenerul ei, în
  aceeași aplicație și în browser: faza ei de azi, cum o poate sprijini, pagini scurte „Învață”, un memento la fiecare
  fază. Doar cu invitația și acordul ei separat; fără notițe, simptome sau situație; ea oprește accesul pe loc.
- **Documentele pentru magazine și construirea automată:** `docs/store/`, `.github/`, `fastlane/`.

## Riscuri și ce facem cu ele

1. **Respingere Apple 4.2 („site împachetat”).** Cel mai mare risc. Apărare: funcții care nu există pe web și se văd
   ușor (descărcare offline, pagină offline, ecran blocat, mementouri locale, push, linkuri universale), zonă de
   membru fără meniul și magazinul site-ului, note de recenzie clare și un video de 1–2 minute (`store/review-notes.md`).
   Dacă vine totuși respingerea: răspuns în Resolution Center; apoi planul B, în ordinea costului: pagina offline
   folosită și online ca „Acasă” nativă, apoi un player nativ pentru Inner Temple. Google e mult mai îngăduitor.
2. **Respingere 3.1.1 (cumpărare).** O singură urmă (un preț, „Reînnoiește”, banda de Black Friday) ajunge. Lista de
   verificat înainte de fiecare trimitere e în `release.md` (pasul 9) și `store/review-notes.md`. Risc permanent: o
   campanie nouă pe site poate apărea în aplicație dacă nu e în lista fragmentului; la fiecare campanie, o privire
   în aplicație.
3. **Urmărire în WebView.** Pagina are Google Analytics și pixelul Meta (după acord, prin Complianz), iar 235/266
   trimit evenimente spre GA când există `gtag`. În aplicație ar însemna ATT și alte etichete. **Cerință fermă**
   (`arhitectura.md`, „Ce ascunde fragmentul”): în aplicație nu se încarcă GA, pixelul Meta, atribuirea comenzilor
   WooCommerce și bara Complianz. **18 nov., seara: implementată în fragment** (`ah-sp-aplicatie.php` 2.1.0, secțiunea 10, cu teste; de verificat pe build cu Web Inspector / `chrome://inspect`); până la verificare nu se completează etichetele
   de confidențialitate și nu se trimite la recenzie. Vimeo: embed-urile site-ului au deja `dnt=1` (235, 44, 51,
   265); fragmentul îl adaugă în aplicație oricărui iframe Vimeo care nu-l are (cerință în `arhitectura.md`).
4. **Vimeo în WebView.** Videourile au restricție de domeniu: merg, pentru că pagina e chiar `www.adelaharu.ro`.
   De încercat pe telefoane reale: pornire, ecran complet, ieșire din ecran complet, rotire. Sunetul unui video **nu**
   continuă cu ecranul blocat (doar audio-ul Inner Temple); e normal și nu promitem altceva în magazin.
5. **Cât rămâne conectată pe iPhone (WKWebView, ITP).** Cookie-ul `ah_sp` e pus de server (HttpOnly, un an), pe
   domeniul principal al aplicației, deci nu intră sub limita ITP de 7 zile (care lovește cookie-urile scrise din
   JavaScript și `localStorage`). Riscul real e **`localStorage`**, unde Ciclul meu își ține datele când nu sunt
   păstrate în cont: iOS îl poate goli după câteva zile fără folosire. Măsura (cerință în `arhitectura.md`, Puntea,
   punctul 3): puntea scrie ciclul și în `Preferences` (`ah_ciclu`, stocare nativă) și, când `localStorage` e gol dar
   `ah_ciclu` există pentru același cont, **îl pune înapoi** în `localStorage.ahsp_ciclu`; copia nativă și
   mementourile se șterg doar la acțiuni explicite („Șterge datele de pe acest telefon” din Ciclul meu, ieșirea din
   cont, „Șterge contul”). **18 nov.: în fragment, `ciclu()` încă face invers** (când `localStorage` lipsește, șterge
   `ah_ciclu` și anulează mementourile), deci exact golirea de către iOS ar pierde și copia; de reparat de colegul de
   la fragment, cu test, înainte de TestFlight. Păstrarea în cont, cu acord, rămâne varianta sigură. De încercat:
   intrare, aplicația închisă 10 zile, redeschidere (sau, mai repede, `localStorage` golit din Web Inspector).
6. **Câte acceptă notificările.** Pe iPhone, de obicei cam jumătate (mai puțin dacă întrebarea vine la prima
   deschidere). Măsuri: o singură întrebare, după un moment bun (de ex. după prima meditație ascultată), cu un ecran
   care spune concret ce primește; comutator în „Mai mult”; emailul rămâne canalul principal, push-ul e un bonus.
   Măsurăm din pagina de setări (câte telefoane primesc notificări) față de câte au intrat.
7. **Site căzut = aplicație fără conținut.** Pagina offline acoperă meditațiile descărcate și ciclul; restul așteaptă
   site-ul. Orice schimbare a lui 235 se vede imediat în aplicație, în bine și în rău: după fiecare schimbare, o
   privire și în aplicație.
8. **Partenerul și datele de sănătate.** Apple și Google pot întreba de ce o altă persoană vede faza ei. Răspunsul:
   acord explicit separat, minim (doar faza și datele estimate), oprit pe loc, ștergere și pentru el în aplicație
   (`store/review-notes.md`). Recenzorul primește un link de partener de probă (`review-account.md`).
9. **Contul de recenzie.** **18 nov., seara: în fragment** (`ah-sp-aplicatie.php` 2.1.0, secțiunea 9, cu teste; de verificat pe build; cerința: `review-account.md`). Fără el
   recenzorii nu pot intra (codul pleacă pe email) ⇒ respingere sigură (2.1): **blochează trimiterea**. După ce
   există, depinde de comenzi reale pe adresa lui; dacă expiră Inner Temple, recenzia următoare vede totul închis.
   Memento anual.
10. **Push pe iPhone.** Build-ul iOS are nevoie de Firebase Messaging, `GoogleService-Info.plist` și
    `aps-environment`; fără ele aplicația nu cere deloc notificări pe iPhone (`AhApp.info().push` = false), iar notele
    și fișa App Store nu au voie să promită push. Workflow-ul iOS oprește încărcarea în TestFlight când lipsesc, cât
    timp e bifat `push_ios`.
11. **Ștergerea contului „revine”.** Accesul vine din comenzi, păstrate legal; după „Șterge contul”, o nouă intrare cu
    același email arată din nou conținutul cumpărat (fără datele șterse). Notele de recenzie spun asta dinainte.

## Etape

| Etapă | Ce | Cine | Gata când |
|---|---|---|---|
| 0. Acum | arhitectura, fragmentul, shell-ul, documentele, CI | echipa | CI verde, testele fragmentului trec |
| 1. Conturi | D-U-N-S, Apple Developer, Play Console, Firebase | proprietara | conturile aprobate (poate dura 2–3 săptămâni: de pornit acum) |
| 2. Fragmentele pe site | copie de siguranță, „modul aplicație” apoi „Pentru el”, fiecare urcat **inactiv**, OK Mircea, activat unul câte unul, verificat în browser | Mircea + programatorul | site-ul identic în browser; setările completate |
| 3. Testare internă | TestFlight + Play testare internă, echipa + 5–10 cliente de încredere, o săptămână | toți | lista din `release.md` pasul 9 trecută pe iPhone și Android |
| 4. Recenzie | contul de recenzie, fișele, capturile, notele, trimiterea | proprietara + programatorul | aprobat de Apple și Google |
| 5. Lansare | publicare manuală, linkurile în setări, anunț către cliente | Mircea, Adela | aplicația în magazine |
| 6. După 30 de zile | instalări, câte au permis notificările, câte meditații descărcate, mesaje de la cliente | Mircea | decizia pentru v2.1 |

**Blochează trimiterea la recenzie (18 nov.):** contul de recenzie, oprirea analizei/pixelului în aplicație, push-ul
pe iPhone (sau scos din note și fișă), refacerea ciclului din `Preferences` (vezi riscurile 3, 5, 9, 10).

**De hotărât de oameni:** textele CIORNĂ (Adela); dacă emailurile fazelor conțin promovări (schimbă etichetele de
confidențialitate); telefonul public al firmei în magazine; doar iPhone sau și iPad la prima versiune.
