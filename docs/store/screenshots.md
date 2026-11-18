# Planul capturilor de ecran (v2)

În v2 ecranele sunt chiar Spațiul meu de pe site, deci capturile se fac **din aplicația reală**, intrată cu
**contul de recenzie** (`docs/review-account.md`), nu cu date de probă: așa nu apar date ale clientelor și capturile
arată exact ce vede recenzorul (Apple 2.3.3). Capturile vechi din `docs/screenshots/` sunt ale ecranelor React din
v1 și **nu se mai folosesc**. `docs/screenshots-v2/` are pagina offline (făcute de colega de la aplicație cu
`npm run screenshots`, 390×844, bune pentru verificare).

## Cum le facem

- **iPhone:** simulatorul din Xcode „iPhone 17 Pro Max” (6,9", 1320×2868) sau un iPhone real de 6,9"; ⌘S în
  simulator salvează captura la dimensiunea bună. Aplicația instalată din TestFlight sau din Xcode, contul de recenzie.
- **Android:** emulatorul din Android Studio (ex. Pixel, 1080×2400) dă capturi mai lungi de 2:1; Google cere raport
  maxim 2:1, deci se decupează la 1080×2160 (sus, bara de stare; jos, nimic important) sau se folosește un profil de
  emulator 1080×2160.
- Pentru tema de seară, a doua serie e opțională.
- Fără bară de cookie-uri, fără banda „Actualizează”, fără notificări de la alte aplicații; ora 9:41, baterie plină
  (simulatorul iOS: `xcrun simctl status_bar booted override --time 9:41 --batteryLevel 100`).

## Dimensiuni cerute (de reverificat în consolă la încărcare)

| Magazin | Ce | Dimensiune |
|---|---|---|
| App Store | iPhone 6,9" (obligatoriu; Apple le micșorează singur pentru ecranele mai mici) | **1320×2868** portret (se acceptă și 1290×2796) |
| App Store | iPad 13" (doar dacă aplicația rulează pe iPad: `TARGETED_DEVICE_FAMILY` 1,2) | 2064×2752 |
| Google Play | Telefon, 2–8 capturi (minim 4 pentru recomandări) | **1080×2160** (raport maxim 2:1) |
| Google Play | Imagine de prezentare (feature graphic) | 1024×500, fără transparență (Canva/Figma, sigla și culorile site-ului) |
| Google Play | Pictograma | 512×512 PNG, din aceeași sursă ca iconița aplicației (`app/scripts/icoane.cjs`) |

## Ce arătăm (în ordinea din magazin)

Textul de deasupra fiecărei capturi (opțional, dar ajută la 4.2: arată funcțiile de telefon) e scurt, CIORNĂ pentru
Adela, pe fundalul crem `#FBF6F3`, cu fonturile Spațiului meu (Gilda Display / Figtree).

| # | Ecran | Textul de deasupra |
|---|---|---|
| 1 | Acasă (Spațiul meu) | Spațiul tău, mereu cu tine |
| 2 | Inner Temple, lista, cu butonul „Descarcă” vizibil | Meditațiile tale, și fără internet |
| 3 | Ecranul blocat al telefonului cu meditația în redare (titlu, poză, comenzi) | Ascultă cu ecranul blocat |
| 4 | Ciclul meu, faza de azi | Harta ciclului tău, zi de zi |
| 5 | Notificarea locală „Începe Primăvara ta interioară” (sau ecranul cu mementourile pornite) | Un semn blând la fiecare fază |
| 6 | Cursurile mele / o lecție | Cursurile tale, la tine în buzunar |
| 7 | Pagina offline (faza de azi + meditațiile descărcate), din `docs/screenshots-v2/` refăcută la dimensiune | Și când nu ai semnal |
| 8 | Întâlnirile / o înregistrare (dacă contul de recenzie are una) | Întâlnirile tale și înregistrările lor |

**Nu folosim** în magazin: ecranul de intrare și codul, Abonamente, „Mai mult”, ștergerea contului, cărțile (copertele
pot fi ale altor edituri; dacă sunt ale voastre, se pot adăuga).

## Reguli de verificat pe fiecare captură

- Fără prețuri, fără „cumpără”, „comandă”, „reînnoiește”, fără link spre plată, fără portalul Stripe, fără
  Black Friday, card cadou sau „Recomandă” (Apple 3.1.1, 2.3.3). Capturile se fac **după** ce fragmentul e activ.
- Fără date reale ale clientelor: doar contul de recenzie (prenumele „Recenzie” se poate schimba pe comenzi în
  ceva firesc, ex. „Ana”, înainte de capturi).
- Bara de stare curată; același conținut în capturile iOS și Android (diferă doar dimensiunile).
- Dacă o funcție nu e gata la lansare (ex. push fără Firebase), nu apare nici în capturi, nici în descriere.
