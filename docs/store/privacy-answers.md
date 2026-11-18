# Confidențialitatea: răspunsurile pentru Apple și Google (v2)

Bazate pe `docs/arhitectura.md` (v2), pe Spațiul meu (`reference/v2/sn235.php`) și pe manifestul iOS
`app/ios/App/App/PrivacyInfo.xcprivacy`. **Etichetele trebuie să spună același lucru ca manifestul**; dacă se schimbă
unul, se schimbă și celălalt. Dacă se adaugă vreodată un SDK sau un script terț în aplicație (analiză, rapoarte de
erori, pixel), răspunsurile se refac înainte de versiunea nouă.

## Condiția de care depind toate răspunsurile de mai jos

În aplicație (doar când `ah_spa_e_aplicatie()`), fragmentul **nu încarcă** Google Analytics (`gtag`, G-52BCW9FS1V),
scriptul `facebook-for-woocommerce` (pixelul Meta), atribuirea comenzilor WooCommerce (`sourcebuster`,
`order-attribution`) și bara de cookie-uri Complianz; rămân doar cookie-urile funcționale (`ah_sp`, `ah_app`,
`ah_med`, cookie-ul sălii). Pe pagina publică de azi, GA și pixelul așteaptă acordul din bara Complianz; dacă în
aplicație s-ar accepta „Marketing”, pixelul Meta ar însemna **urmărire** (Apple: ATT obligatoriu; Google: date
partajate cu Meta). **De confirmat cu colegul de la fragment înainte de completarea formularelor.** Dacă nu se
poate, la Apple: „Used for tracking: Yes” pentru Device ID + Product Interaction + Advertising Data, iar aplicația
trebuie să ceară ATT; la Google: partajare cu Meta în scop publicitar. Recomand varianta fără.

## Ce date pleacă de pe telefon (inventarul)

| Dată | Când | Unde ajunge | De ce |
|---|---|---|---|
| Adresa de email | la intrare (cod pe email) | serverul adelaharu.ro | contul (cookie-ul `ah_sp`), accesul la ce are în cont |
| Prenumele și ce are în cont (comenzile: cursuri, Inner Temple, cărți, starea abonamentelor) | la fiecare deschidere (citite de pe server, arătate în pagină) | există deja pe server, din cumpărarea de pe site | să-i arate conținutul ei. Aplicația nu le colectează nou, dar le afișează legate de cont: le declarăm, prudent |
| Progresul la cursuri (lecții terminate), minutele ascultate, înscrierile la întâlniri | când le folosește | serverul adelaharu.ro | progresul, mementoul întâlnirii |
| **Datele ciclului**: zilele menstruației, lungimea, situația (ex. sarcină), **notițele zilnice**, cheia linkului pentru partener | **doar dacă pornește păstrarea în cont și bifează acordul** | serverul adelaharu.ro, **criptate AES-256-GCM**, tabel separat (`ah_sp_ciclu`), legate de adresă printr-un hash | sincronizare între telefon și calculator; le șterge oricând |
| Datele ciclului fără acord | mereu | **doar pe telefon** (`localStorage` al paginii + `Preferences` `ah_ciclu` pentru pagina offline și mementouri) | fazele, mementourile locale |
| Linkul pentru partener | când ea îl trimite cuiva | **datele stau doar în fragmentul URL-ului** (`?ciclul-ei#d=…`), pe care browserul nu îl trimite la server; serverul vede doar dacă cheia a fost oprită (`partener-stare`) | partenerul vede fazele |
| Tokenul de notificări (FCM) + platforma + versiunea aplicației | doar dacă permite notificările | serverul adelaharu.ro (tabelul `ah_spa_push`); Google Firebase (livrează notificarea) | notificări: meditație nouă, înregistrare, întâlnire în 15 minute |
| Identificatorul telefonului pentru limita de dispozitive (meditații 51, sala 44: un cod aleator în cookie + User-Agent-ul scurtat) | la prima deschidere a meditațiilor / sălii | serverul adelaharu.ro | limita de 5 dispozitive, „Golește dispozitivele” |
| Fișierele meditațiilor descărcate | la „Descarcă” | **de la** server spre telefon (stocare privată, exclusă din backup) | ascultat fără internet |

Nu pleacă: locația, contactele, pozele, microfonul, identificatorul de reclame (IDFA/AAID), date de plată, istoricul
de navigare. Mementourile fazelor sunt **notificări locale** (calculate și programate pe telefon), nu trec prin server.
Fără reclame, fără urmărire, fără vânzare de date.

Firebase Cloud Messaging (Google) e singurul serviciu terț din aplicație (tokenul / ID-ul instalării Firebase).
Google îl tratează ca „furnizor de servicii”, deci la Google Play nu e „partajare”. **Google Analytics pentru
Firebase rămâne oprit** (vezi `release.md`, pasul Firebase).

---

## Apple: App Privacy („etichetele nutriționale”), App Store Connect › App Privacy

**Do you or your third-party partners collect data from this app?** → **Yes**

| Tip de date (Apple) | Colectat | Scopuri | Legat de identitate | Folosit pentru urmărire |
|---|---|---|---|---|
| Contact Info › **Email Address** | Da | App Functionality | Da | Nu |
| Contact Info › **Name** (prenumele din comenzi, afișat în cont) | Da | App Functionality | Da | Nu |
| Health & Fitness › **Health** (ciclul și notițele, doar cu păstrarea în cont pornită) | Da | App Functionality | Da | Nu |
| Purchases › **Purchase History** (ce are în cont: cursuri, Inner Temple, cărți) | Da | App Functionality | Da | Nu |
| Usage Data › **Product Interaction** (lecții terminate, minute ascultate) | Da | App Functionality | Da | Nu |
| Identifiers › **Device ID** (tokenul de notificări, identificatorul dispozitivului din cookie) | Da | App Functionality | Da | Nu |

Toate celelalte: **Nu** (Phone, Physical Address, Other Contact Info, Fitness, Payment Info, Credit Info, Other
Financial Info, Location, Sensitive Info, Contacts, Emails or Text Messages, Photos or Videos, Audio, Gameplay,
Customer Support, Other User Content, Browsing History, Search History, User ID, Advertising Data, Other Usage Data,
Crash Data, Performance Data, Other Diagnostic Data, Environment Scanning, Hands, Head, Other Data Types).

Același set de 6 tipuri este în `PrivacyInfo.xcprivacy` (verificat 18 nov. 2026), toate cu
`AppFunctionality`, `Linked = true`, `Tracking = false`; `NSPrivacyTracking = false`.

Explicații și puncte nesigure:
- **Health:** notițele zilnice sunt despre ciclu, deci intră la Health, nu la „Other User Content”.
- **Health opțional:** Apple nu are câmp „opțional”; spunem asta în politica de confidențialitate.
- **Emailurile fazelor:** dacă vor conține promovări (cursuri, produse), la Email Address se adaugă scopul
  „Developer’s Advertising or Marketing”. Dacă sunt strict textul fazei, rămâne App Functionality. **De hotărât
  de Adela.** (În aplicație produsele din Ciclul meu sunt ascunse; emailurile pleacă de pe site.)
- **Tracking:** Nu, cu condiția de la început. Aplicația nu cere ATT.
- **Required reason API** în manifest: `UserDefaults` (CA92.1, Preferences), `FileTimestamp` (C617.1, Filesystem).
  Dacă se adaugă verificarea spațiului liber înainte de descărcare: `DiskSpace` (E174.1).

---

## Google Play: Data safety (Play Console › Conținutul aplicației › Siguranța datelor)

**Colectează sau partajează aplicația vreun tip de date al utilizatorilor?** → Da
**Sunt toate datele criptate în timpul transferului?** → Da (doar HTTPS, `cleartext: false`)
**Oferiți o modalitate prin care utilizatorii pot solicita ștergerea datelor?** → Da
(în aplicație: „Mai mult” › „Aplicația” › „Șterge contul”; pe web: URL-ul de mai jos)
**Conturi:** aplicația permite intrarea într-un cont (email + cod), deci se cere **URL de ștergere a contului**.

| Categoria › tipul | Colectat | Partajat | Prelucrat efemer | Obligatoriu / opțional | Scopuri |
|---|---|---|---|---|---|
| Informații personale › **Nume** | Da | Nu | Nu | Obligatoriu | Funcționalitatea aplicației; Gestionarea contului |
| Informații personale › **Adresă de email** | Da | Nu | Nu | Obligatoriu | Funcționalitatea aplicației; Gestionarea contului |
| Informații financiare › **Istoricul achizițiilor** | Da | Nu | Nu | Obligatoriu | Funcționalitatea aplicației |
| Sănătate și fitness › **Informații despre sănătate** | Da | Nu | Nu | **Opțional** | Funcționalitatea aplicației |
| Activitatea în aplicație › **Interacțiuni cu aplicația** | Da | Nu | Nu | Obligatoriu | Funcționalitatea aplicației |
| ID-uri ale dispozitivului sau alte ID-uri | Da | Nu | Nu | Obligatoriu | Funcționalitatea aplicației |

Restul: nu. (Firebase = furnizor de servicii, nu „partajare”.) „Linkul pentru partener” nu e partajare făcută de
noi: îl trimite ea, iar datele nu trec prin serverul nostru. Dacă emailurile fazelor conțin marketing, se adaugă
scopul „Comunicări ale dezvoltatorului” la adresa de email.

**URL pentru ștergerea contului** (obligatoriu, public, fără login): **de creat** pe site, de ex.
`https://www.adelaharu.ro/stergere-cont-aplicatie/`, cu textul (CIORNĂ, pentru Adela):
> Ca să îți ștergi contul din aplicația Adela Haru, deschide aplicația › Mai mult › Aplicația › Șterge contul.
> Dacă nu mai ai aplicația, scrie-ne de pe adresa contului la contact@adelaharu.ro, cu subiectul „Ștergere cont
> aplicație”, și o facem în cel mult 30 de zile. Se șterg: datele ciclului și notițele păstrate în cont (și
> abonarea la emailurile fazelor), înscrierea în Spațiul meu, telefoanele conectate și tokenurile de notificări.
> Comenzile și facturile se păstrează cât cere legea (obligații contabile și fiscale).

---

## Google Play: declarația „Aplicații de sănătate” (Conținutul aplicației › Health apps)

Obligatorie pentru aplicațiile cu funcții de sănătate (și pentru categoria Sănătate și fitness).
Bifăm (denumirile din formular se pot schimba; alegeți cea mai apropiată):
- **Urmărirea ciclului menstrual / sănătatea reproducerii** (period tracking): Da, Ciclul meu (faze, notițe,
  mementouri, linkul pentru partener).
- **Gestionarea stresului, relaxare, meditație** (mindfulness): Da, Inner Temple.
- **Educație pentru sănătate / wellness** (dacă formularul o oferă): Da, cursurile.
- Health Connect: **Nu**. Dispozitiv medical, diagnostic, tratament: **Nu**. Cercetare pe oameni: **Nu**.

Ce mai cere politica Google pentru aceste aplicații, și cum stăm:
- politică de confidențialitate publică, care descrie datele de sănătate: **trebuie adăugat paragraful din
  `privacy-policy-addendum.md`** înainte de trimitere;
- avertisment că nu e sfat medical: există în Ciclul meu și în descriere;
- datele de sănătate nu se folosesc pentru reclame, nu se vând, nu se dau altcuiva: adevărat (cu condiția de sus).

## Google Play: alte secțiuni din „Conținutul aplicației”

| Secțiune | Răspuns |
|---|---|
| Reclame | Nu conține reclame |
| Acces la aplicație | Unele funcții sunt restricționate → instrucțiunile din `review-notes.md` |
| Public țintă | **18 ani și peste** (doar). Nu e destinată copiilor. |
| Clasificarea conținutului | chestionarul IARC, vezi `content-rating.md` |
| Aplicație guvernamentală / funcții financiare / știri | Nu / Nu / Nu |
| **Permisiuni pentru servicii în prim-plan** | **Da**: `FOREGROUND_SERVICE_MEDIA_PLAYBACK` (pluginul `AhMedia`, redarea meditațiilor cu ecranul blocat). Tipul: „Redare media”. Play cere o descriere și **un video** (link YouTube nelistat / Drive): pornești o meditație, blochezi ecranul, comenzile apar în notificare, sunetul continuă. |
| Alarme exacte | `SCHEDULE_EXACT_ALARM` doar până la Android 12L (`maxSdkVersion 32`); de verificat în manifestul final (merged) că nu apare pentru versiuni noi. Dacă apare, Play cere declarație. |
| Pornire la boot | `RECEIVE_BOOT_COMPLETED` (pluginul de notificări locale reprogramează mementourile după repornire): nu cere declarație. |
