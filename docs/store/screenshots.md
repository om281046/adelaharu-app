# Planul capturilor de ecran

Capturile se fac din aplicație în modul demo (`VITE_MOCK=1`), cu scriptul colegei:
`cd app && npm run screenshots` (rezultatul: `docs/screenshots/`, acum la 390×844 @2x = 780×1688, bune pentru
verificare, **nu** pentru magazine). Pentru magazine trebuie randate la dimensiunile de mai jos (schimbat
`viewport`/`deviceScaleFactor` în `app/scripts/screenshots.cjs` sau o a doua rulare cu alți parametri).

## Dimensiuni cerute (2026, de reverificat în consolă la încărcare)

| Magazin | Ce | Dimensiune | Cum o obținem din script |
|---|---|---|---|
| App Store | iPhone 6,9" (obligatoriu; Apple le micșorează singur pentru ecranele mai mici) | **1320×2868** portret (se acceptă și 1290×2796) | viewport 440×956, `deviceScaleFactor: 3` |
| App Store | iPad 13" (obligatoriu **doar dacă** aplicația rulează pe iPad) | 2064×2752 | viewport 1032×1376, scale 2 |
| Google Play | Telefon, 2–8 capturi (minim 4 pentru recomandări) | **1080×2160** (raport maxim 2:1; 1320×2868 NU trece, e mai lung de 2:1) | viewport 360×720, scale 3 |
| Google Play | Imagine de prezentare (feature graphic) | 1024×500, fără transparență | se face separat (Canva/Figma), cu sigla și culorile site-ului |
| Google Play | Pictograma | 512×512 PNG | din aceeași sursă ca pictograma aplicației (`app/scripts/icoane.cjs`) |

**Recomandare pentru v1:** aplicația doar pentru iPhone (`TARGETED_DEVICE_FAMILY = 1` în proiectul Xcode; acum este
`1,2`, adică și iPad). Altfel Apple cere capturi de iPad și testează aspectul pe iPad la recenzie. Decizia e a
colegei de la aplicație și a proprietarei. (Pe iPad aplicația de iPhone tot se poate instala, în modul compatibil.)

## Ce arătăm (în ordinea din magazin)

Tema luminoasă pentru magazine (cea întunecată poate fi a doua serie, opțional). Textul de deasupra fiecărei
capturi (opțional, dar ajută) e scurt, cu fontul site-ului, pe fundalul crem `#FFF7F0`.

| # | Ecran (fișierul din `docs/screenshots/`) | Textul de deasupra |
|---|---|---|
| 1 | Acasă (`09-acasa`) | Spațiul tău de liniște |
| 2 | Inner Temple, lista (`05-inner-temple`) | Toate meditațiile tale, pe categorii |
| 3 | Playerul (`06-player`) | Ascultă și cu ecranul blocat |
| 4 | Video cu explicații (`07-video`) | Explicații video, pas cu pas |
| 5 | Ciclul meu, roata (`04-ciclu`) | Harta ciclului tău, zi de zi |
| 6 | Ciclul, detaliile fazei (`04b-ciclu-detalii`) | Ce îți este potrivit acum |
| 7 | Cursurile mele (`10-cursuri`) sau lecția (`12-lectie`) | Cursurile tale, la tine în buzunar |

**Nu folosim** în magazin: ecranul de intrare și codul (`01`, `02`), meditația blocată (`08`, are textul despre
Inner Temple; nu e interzis, dar nu vrem nimic ce seamănă a îndemn la cumpărare), ștergerea contului (`14`).

## Reguli de verificat pe fiecare captură

- Fără prețuri, fără „cumpără”, fără link spre plată, fără butonul portalului Stripe (Apple 3.1.1, 2.3.3).
- Fără date reale ale clientelor: doar datele demo (nume, email). Pozele și titlurile meditațiilor din demo să fie
  reale și aprobate de Adela (Apple 2.3.3: capturile arată aplicația reală).
- Bara de stare curată (oră, baterie plină) sau fără bară; fără notificări de la alte aplicații.
- Același conținut în capturile iOS și Android (diferă doar dimensiunile).
- Dacă o funcție nu e gata la lansare (ex. descărcarea offline), nu apare nici în capturi, nici în descriere.
