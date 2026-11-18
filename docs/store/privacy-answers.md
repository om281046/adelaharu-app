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
partajate cu Meta). Evenimentele din pagină (`ev()` din 235: `sp_intrare`, `sp_ciclu_emailuri`…; `masoara()` din
266) pleacă spre GA ori de câte ori există `gtag`, deci condiția le acoperă și pe ele.

**Stare 18 nov. 2026 (seara): condiția e în fragment, cu teste** (`server/ah-sp-aplicatie.php`, secțiunea 10;
`server/README.md`, „Fără analiză și urmărire”): în aplicație nu se încarcă și nu se cer GA, Clarity, pixelul Meta,
atribuirea WooCommerce, bara Complianz, iar Conversions API e oprit în timpul cererilor din aplicație. Pagina lui
`/pentru-el/` (contul pentru el) e HTML propriu, fără niciunul dintre acestea. **Rămâne de verificat pe un telefon
real**, cu fragmentul activ (Safari Web Inspector / `chrome://inspect`: nicio cerere spre `google-analytics.com`,
`googletagmanager.com`, `connect.facebook.net`, `facebook.com/tr`, `clarity.ms`), înainte de completarea formularelor.
Dacă verificarea nu iese: la Apple „Used for tracking: Yes” pentru Device ID + Product Interaction + Advertising
Data și ATT; la Google, partajare cu Meta în scop publicitar.

## Ce date pleacă de pe telefon (inventarul)

| Dată | Când | Unde ajunge | De ce |
|---|---|---|---|
| Adresa de email | la intrare (cod pe email) | serverul adelaharu.ro | contul (cookie-ul `ah_sp`), accesul la ce are în cont |
| Prenumele și ce are în cont (comenzile: cursuri, Inner Temple, cărți, starea abonamentelor) | la fiecare deschidere (citite de pe server, arătate în pagină) | există deja pe server, din cumpărarea de pe site | să-i arate conținutul ei. Aplicația nu le colectează nou, dar le afișează legate de cont: le declarăm, prudent |
| Progresul la cursuri (lecții terminate), minutele ascultate, înscrierile la întâlniri | când le folosește | serverul adelaharu.ro | progresul, mementoul întâlnirii |
| **Datele ciclului**: zilele menstruației, lungimea, situația (ex. sarcină), **notițele zilnice**, cheia linkului pentru partener | **doar dacă pornește păstrarea în cont și bifează acordul** | serverul adelaharu.ro, **criptate AES-256-GCM**, tabel separat (`ah_sp_ciclu`), legate de adresă printr-un hash | sincronizare între telefon și calculator; le șterge oricând |
| **Abonarea la emailurile fazelor** (Ciclul meu › emailurile Adelei la începutul fiecărei faze, cu bifa de acord; tabelul `ah_ciclu` din Harta ciclului, 41) | **doar dacă se abonează**, cu acord | serverul adelaharu.ro, **necriptate**: emailul, prenumele, data primei zile a ultimei menstruații, lungimea ciclului și a menstruației (și starturile notate), legate direct de adresa de email | emailul de la începutul fiecărei faze; dezabonare din orice email; „Șterge contul” șterge rândul |
| Datele ciclului fără acord | mereu | **doar pe telefon** (`localStorage` al paginii + `Preferences` `ah_ciclu` pentru pagina offline și mementouri) | fazele, mementourile locale |
| Linkul pentru partener | când ea îl trimite cuiva | **datele stau doar în fragmentul URL-ului** (`?ciclul-ei#d=…`), pe care browserul nu îl trimite la server; serverul vede doar dacă cheia a fost oprită (`partener-stare`) | partenerul vede fazele |
| **Contul „Pentru el”** (`ah-sp-partener.php`): prenumele și emailul lui (emailul criptat AES-256-GCM + HMAC pentru căutare), numele ei cum îl vede el, momentul acordului ei, tokenul linkului (doar sha256), datele invitației, prima și ultima vizită, alegerea emailurilor fazelor, tokenurile push ale lui (cel mult 5, tabel separat de al ei) | când ea îl invită, cu bifa separată de acord; când el folosește pagina sau pornește notificările (doar în aplicație, doar pe un telefon pe care nu e intrată și ea) | serverul adelaharu.ro; tokenul push și la Firebase (livrarea) | să-i arate faza ei de azi și ce urmează, notificare / email la fiecare fază. **El nu primește** notițele, simptomele, situația sau zilele notate de ea; serverul calculează doar faza. Rândul lui o leagă de ea printr-o amprentă (cheia ciclului), nu prin emailul ei. Se șterge: de el, pe loc („Șterge accesul meu”); la 30 de zile după ce ea oprește accesul; pe loc când ea își șterge ciclul din cont sau contul |
| Tokenul de notificări (FCM) + platforma + versiunea aplicației | doar dacă permite notificările | serverul adelaharu.ro (tabelul `ah_spa_push`; al partenerului: `ah_spp_push`); Google Firebase (livrează notificarea) | notificări: meditație nouă, înregistrare, întâlnire în 15 minute; la el, începutul fiecărei faze. Un telefon are un singur token și el aparține unui singur cont (al ei sau al lui), niciodată amândurora |
| Identificatorul telefonului pentru limita de dispozitive (meditații 51, sala 44: un cod aleator în cookie + User-Agent-ul scurtat) | la prima deschidere a meditațiilor / sălii | serverul adelaharu.ro | limita de 5 dispozitive, „Golește dispozitivele” |
| Fișierele meditațiilor descărcate | la „Descarcă” | **de la** server spre telefon (stocare privată, exclusă din backup) | ascultat fără internet |

Nu pleacă: locația, contactele, pozele, microfonul, identificatorul de reclame (IDFA/AAID), date de plată, istoricul
de navigare. Mementourile fazelor sunt **notificări locale** (calculate și programate pe telefon), nu trec prin server.
Fără reclame, fără urmărire, fără vânzare de date.

Servicii terțe care primesc ceva din aplicație:
- **Firebase Cloud Messaging** (Google): tokenul / ID-ul instalării Firebase, doar cu notificările permise. Google
  îl tratează ca „furnizor de servicii”, deci la Google Play nu e „partajare”. **Google Analytics pentru Firebase
  rămâne oprit** (vezi `release.md`, pasul Firebase).
- **Vimeo** (`player.vimeo.com`, `*.vimeocdn.com`): playerele video din lecții și explicații primesc adresa IP și
  datele tehnice ale redării, ca orice video livrat. Embed-urile site-ului au deja `dnt=1` (235, 44, 51, 265): în
  acest mod Vimeo nu pune cookie-uri și nu păstrează statistici de sesiune; `arhitectura.md` cere ca fragmentul să
  adauge `dnt=1` și oricărui iframe Vimeo care nu-l are. Cu `dnt=1` nu declarăm colectare de către Vimeo (date
  folosite doar ca să livreze videoul). **Fără `dnt=1`**, Vimeo colectează date de utilizare ⇒ Apple: „Product
  Interaction” / „Other Usage Data” colectate de un partener terț, scop Analytics; Google: idem.
- **Apple Podcasts**: în aplicație playerul episoadelor **nu se mai încarcă** (fragmentul pune `embed = about:blank` la
  sursă și, în locul lui, butonul „Ascultă în Apple Podcasts”, care deschide aplicația Podcasts, în afara aplicației
  noastre). Deci nu declarăm nimic pentru Apple Podcasts. Pe pagina lui, linkurile „Ascultă episodul” se deschid tot
  în afara aplicației.

---

## Apple: App Privacy („etichetele nutriționale”), App Store Connect › App Privacy

**Do you or your third-party partners collect data from this app?** → **Yes**

| Tip de date (Apple) | Colectat | Scopuri | Legat de identitate | Folosit pentru urmărire |
|---|---|---|---|---|
| Contact Info › **Email Address** | Da | App Functionality | Da | Nu |
| Contact Info › **Name** (prenumele din comenzi, afișat în cont) | Da | App Functionality | Da | Nu |
| Health & Fitness › **Health** (ciclul și notițele, doar cu păstrarea în cont pornită; data menstruației și lungimile, doar cu abonarea la emailurile fazelor) | Da | App Functionality | Da | Nu |
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
- **Partenerul (el):** datele lui (prenume, email, token push) intră la Name, Email Address și Device ID, deja
  bifate (contul lui e tot în aplicația aceasta, fără parolă și fără cumpărături; ce vede el despre ea e Health,
  deja bifat). Faza ei arătată lui nu e „partajare cu terți” în sensul Apple (nu e un partener comercial; o face ea,
  cu acord explicit), deci nu schimbă etichetele. Politica de confidențialitate o descrie.
- **Health:** notițele zilnice sunt despre ciclu, deci intră la Health, nu la „Other User Content”. Două căi duc
  date de ciclu pe server, ambele opționale și cu acord: păstrarea în cont (`ah_sp_ciclu`, **criptată AES-256-GCM**)
  și abonarea la emailurile fazelor (`ah_ciclu` din 41, **necriptată**, legată de email). Nota pentru Apple spune
  exact asta; nu scrieți nicăieri că „toate datele ciclului sunt criptate”. (Criptarea tabelului `ah_ciclu` ar cere
  schimbarea lui 41, care nu se modifică acum; de discutat cu Mircea pentru o versiune viitoare.)
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
| Sănătate și fitness › **Informații despre sănătate** (păstrarea ciclului în cont; abonarea la emailurile fazelor) | Da | Nu | Nu | **Opțional** | Funcționalitatea aplicației |
| Activitatea în aplicație › **Interacțiuni cu aplicația** | Da | Nu | Nu | Obligatoriu | Funcționalitatea aplicației |
| ID-uri ale dispozitivului sau alte ID-uri | Da | Nu | Nu | Obligatoriu | Funcționalitatea aplicației |

Restul: nu. (Firebase = furnizor de servicii, nu „partajare”.) **Partenerul:** Google nu consideră „partajare”
transferul pornit de utilizatoare printr-o acțiune explicită, cu acordul ei, către cine alege ea (invitația cu
bifa separată); de aceea „Partajat” rămâne „Nu”. *Nesigur:* dacă vreți varianta cea mai prudentă, la „Informații
despre sănătate” bifați „Partajat”, scop „Funcționalitatea aplicației”. Linkul vechi „Pentru el” (`?ciclul-ei#d=…`)
nu trece prin serverul nostru. Dacă emailurile fazelor conțin marketing, se adaugă
scopul „Comunicări ale dezvoltatorului” la adresa de email.

**URL pentru ștergerea contului** (obligatoriu, public, fără login): **de creat** pe site, de ex.
`https://www.adelaharu.ro/stergere-cont-aplicatie/`, cu textul (CIORNĂ, pentru Adela):
> Ca să îți ștergi contul din aplicația Adela Haru, deschide aplicația › Mai mult › Aplicația › Șterge contul.
> Dacă nu mai ai aplicația, scrie-ne de pe adresa contului la contact@adelaharu.ro, cu subiectul „Ștergere cont
> aplicație”, și o facem în cel mult 30 de zile. Se șterg: datele ciclului și notițele păstrate în cont (și
> abonarea la emailurile fazelor), înscrierea în Spațiul meu, telefoanele conectate, tokenurile de notificări și
> accesurile date partenerului. Un partener își șterge accesul din pagina lui, în aplicație, sau ne scrie la aceeași
> adresă.
> Comenzile și facturile se păstrează cât cere legea (obligații contabile și fiscale).
> Dacă intri din nou cu aceeași adresă, vezi din nou ce ai cumpărat (accesul vine din comenzi), fără datele șterse.

Propozițiile despre partener („accesurile date partenerului”, „Un partener își șterge accesul…”) se publică doar
odată cu `ah-sp-partener.php` activ pe site. Condiția despre ștergere e îndeplinită (18 nov., seara): „Șterge contul”
șterge pe loc partenerii ei și tokenurile lor push (`ah_spa_sterge_cont`, cu test în `server/tests/integrare/run.php`).

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
| **Permisiuni pentru servicii în prim-plan** | **Da**: `FOREGROUND_SERVICE_MEDIA_PLAYBACK` (pluginul `AhMedia`, redarea meditațiilor Inner Temple cu ecranul blocat: online, prin puntea din fragment, §6, și descărcate, din pagina offline). Tipul: „Redare media”. Play cere o descriere și **un video** (link YouTube nelistat / Drive): pornești o meditație în Inner Temple (online), blochezi ecranul, comenzile apar în notificare, sunetul continuă; apoi la fel cu o meditație descărcată, în mod avion. **Înainte de înregistrare, verificați pe un Android real că notificarea apare și pentru redarea online** (fragmentul activ, build cu `AhMedia`); dacă nu apare, declarația și videoul se limitează la meditațiile descărcate. |
| Alarme exacte | `SCHEDULE_EXACT_ALARM` doar până la Android 12L (`maxSdkVersion 32`); de verificat în manifestul final (merged) că nu apare pentru versiuni noi. Dacă apare, Play cere declarație. |
| Pornire la boot | `RECEIVE_BOOT_COMPLETED` (pluginul de notificări locale reprogramează mementourile după repornire): nu cere declarație. |
