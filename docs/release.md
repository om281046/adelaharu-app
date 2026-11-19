# Cum publicăm aplicația Adela Haru în App Store și Google Play (v2)

Ghid pas cu pas pentru proprietară (HARU TRAINING SRL) și pentru Mircea. Nu e nevoie de cunoștințe tehnice; unde
e nevoie de un calculator cu programe speciale, scrie clar. Partea pentru programator e la final.

Situația la 18 nov. 2026. Regulile magazinelor se schimbă des: unde am scris **(de verificat)** merită o privire pe
pagina oficială în ziua în care faceți pasul.

## Ce e nou în v2 (pe scurt)

Aplicația v2 **deschide Spațiul meu de pe site** (`https://www.adelaharu.ro/spatiul-meu/`) și adaugă doar ce
site-ul nu poate face: notificări, meditații descărcate pentru ascultat fără internet, sunet cu ecranul blocat,
mementouri pentru fazele ciclului, linkurile din emailuri care deschid aplicația, o pagină pentru când nu e internet.
De aceea are **două părți** care se lansează în ordine:

1. **Fragmentul de pe site** `server/ah-sp-aplicatie.php` („Spațiul meu – modul aplicație”, Code Snippets). Lucrează
   doar când pagina e deschisă din aplicație; în browser site-ul rămâne exact cum e. Are pagina lui de setări:
   **Setări › Aplicația mobilă** (pasul 8).
2. **Aplicația** (Android și iPhone), construită automat în GitHub (pașii 5–6, 9–10).

Al doilea fragment, `server/ah-sp-partener.php` („Spațiul meu – Pentru el”, contul simplu pentru partenerul ei),
se lansează la fel, separat, **după** modulul aplicație (pasul 10, aceeași regulă: inactiv, OK, activat, verificat).

Ce adaugă echipa pe site (meditații, cursuri, înregistrări) apare singur în aplicație, **fără versiune nouă**.
O versiune nouă a aplicației e nevoie doar când se schimbă partea de telefon.

**Fragmentul 2.4.0 și aplicația** (19 nov.): Setările aplicației în „Mai mult” (notificări pe categorii, sunet,
ore de liniște, ecranul blocat, insignă, mementoul zilnic, aspectul după apus și răsărit, widgetul, Wi-Fi, temporizatorul
de somn, Ciclul meu cu Face ID, Despre) și statisticile proprii (Setări › Aplicația mobilă › Statistici). Fragmentul
merge și cu aplicațiile de dinainte: ce cere partea nativă nouă (widgetul, Face ID, clopoțelul, insigna, bara de stare)
apare doar pe build-ul nou; restul (categoriile, liniștea, ecranul blocat, aspectul, statisticile) merge și pe cel vechi.
Paginile de pe site: paragrafele noi din Politica de confidențialitate („Setările aplicației”, „Statistici anonime”,
`docs/store/privacy-policy-addendum.md`) se publică **înainte** de activarea 2.4.0; în App Store Connect, la App Privacy,
Product Interaction primește scopul **Analytics** (`docs/store/privacy-answers.md`), la Google Data safety scopul
„Analiză”.

**Fragmentul 2.3.0 și aplicația** (review 18 nov., runda 2): fragmentul merge și cu aplicațiile construite înainte
(`DELETE /push` rămâne). Partea nativă nouă (iPhone: un 5xx al site-ului deschide pagina offline cu „Site-ul e în lucru”;
Android: motivul erorii pentru pagina offline, refacerea ecranului când procesul WebView e oprit, `minWebViewVersion` 90;
pagina offline care deosebește „fără internet” de „site în lucru”) cere un build nou al aplicației.

Detaliile tehnice: `docs/arhitectura.md`. Planul și riscurile: `docs/strategie-v2.md`.

## Pe scurt: ce urmează și cât durează

| Pas | Cine | Cât durează (estimat) | Cost |
|---|---|---|---|
| 1. Numărul D-U-N-S al firmei | proprietara | 0 zile (dacă există) până la 1–2 săptămâni | gratuit |
| 2. Apple Developer Program (organizație) | proprietara | 2–14 zile după D-U-N-S | 99 USD pe an **(de verificat)** |
| 3. Google Play Console (organizație) | proprietara | câteva zile până la 2 săptămâni | 25 USD o singură dată |
| 4. Fișele aplicației în magazine + paginile de pe site (suport, ștergerea contului, politica) | proprietara (+ Adela pentru texte) | 2–3 ore | gratuit |
| 5. Cheia de încărcare Android | programatorul, cu proprietara | 15 minute | gratuit |
| 6. Cheia App Store Connect API + repo-ul de certificate | proprietara (+ programatorul) | 30 de minute | gratuit |
| 7. Firebase (notificări) | proprietara (+ programatorul) | 30 de minute | gratuit |
| 8. Secretele în GitHub și setările fragmentului | proprietara sau programatorul | 30 de minute | gratuit |
| 9. Proba pe telefon | programatorul + Mircea | o zi de încercări | gratuit |
| 10. Lansarea, în ordine | Mircea (OK), programatorul | Apple 1–3 zile, Google 1–7 zile de recenzie | gratuit |

Realist, de la zero până în magazine: **3–6 săptămâni**, cele mai multe fiind așteptare (D-U-N-S, verificări).
Pașii 1, 2 și 3 se pot începe azi.

**Ce NU trebuie:** contract de plăți cu Apple („Paid Apps Agreement”), date bancare sau fiscale în magazine,
Google Play Billing. Aplicația e gratuită și nu vinde nimic, deci ajunge acordul gratuit, care e implicit.

**Păstrați într-un manager de parole** (1Password, Bitwarden, iCloud Keychain): toate parolele și fișierele de mai
jos (cheia Android, fișierele `.p8`, parola match, cheia Firebase, codul contului de recenzie). Unele se pot descărca
**o singură dată**.

---

## 1. Numărul D-U-N-S (Dun & Bradstreet)

E un număr de 9 cifre care identifică firma. Îl cer și Apple, și Google pentru conturile de organizație.

1. Verificați întâi dacă HARU TRAINING SRL are deja unul: https://developer.apple.com/enroll/duns-lookup/
   (căutați după numele exact din registrul comerțului și adresa sediului social).
2. Dacă nu există, din aceeași pagină se cere unul nou, **gratuit**. D&B poate suna sau scrie pentru confirmare.
   Durata: de obicei până la 5 zile lucrătoare, uneori mai mult; apoi Apple are nevoie de încă 1–2 zile ca să îl
   „vadă”.
3. **Nu plătiți** ofertele D&B de „înregistrare rapidă”; nu sunt necesare.
4. Numele și adresa din D-U-N-S trebuie să fie **identice** cu cele pe care le scrieți la Apple și la Google
   (inclusiv „SRL”, diacriticele pot fi problematice: dacă D&B a scris fără diacritice, scrieți și voi la fel).

## 2. Apple Developer Program, ca organizație

Ce trebuie dinainte:
- un **Apple Account** (fost Apple ID) cu verificarea în doi pași pornită, pe numele persoanei care are dreptul să
  semneze pentru firmă (administratorul). Recomand o adresă a firmei, de ex. `contact@adelaharu.ro`, nu una personală;
- numărul D-U-N-S;
- site-ul public al firmei (www.adelaharu.ro) și o adresă de email pe același domeniu;
- un telefon la care se răspunde: Apple poate suna pentru verificare (în engleză).

Pași:
1. https://developer.apple.com/programs/enroll/ › „Start your enrollment” (sau aplicația Apple Developer de pe iPhone).
2. Alegeți **Organization**. Completați numele legal „HARU TRAINING SRL”, D-U-N-S, site-ul, datele administratorului.
3. Plătiți cei 99 USD/an (abonament anual, se reînnoiește automat; dacă expiră, aplicația dispare din App Store).
4. Așteptați emailul de confirmare (2–14 zile). Rolul vostru va fi **Account Holder**.
5. După aprobare, în **App Store Connect › Business** (sau „Agreements, Tax, and Banking”): acceptați acordul
   „Free Apps” dacă vi se cere. Nu completați nimic la Paid Apps.
6. **Statutul de comerciant în UE (Digital Services Act)**: App Store Connect › Business › „Trader status” ›
   **sunteți comerciant** (firmă). Apple cere adresa, telefonul și emailul, le verifică și le **afișează public** în
   App Store în UE. Fără acest pas aplicația nu poate fi publicată în UE.
7. Notați **Team ID** (10 caractere): https://developer.apple.com/account › Membership details. Îl folosiți la pașii 6 și 8
   și în Setări › Aplicația mobilă pe site (câmpul „Apple Team ID”).

## 3. Google Play Console, cont de organizație

1. https://play.google.com/console/signup cu un cont Google al firmei (recomandat unul dedicat, nu cel personal).
2. Alegeți **„O organizație sau o firmă”** (nu cont personal). Cont personal = reguli mai grele (12 testeri timp de
   14 zile înainte de publicare); contul de organizație nu are această regulă.
3. Completați: numele organizației (identic cu D-U-N-S), D-U-N-S, adresa, site-ul, telefonul și emailul de contact
   (Google le verifică prin cod), plătiți **25 USD** (o singură dată).
4. Verificarea identității: Google cere documente (de ex. certificatul de înregistrare al firmei) și verifică
   persoana. Durează de la câteva zile la 2 săptămâni. **(de verificat)** Google poate cere și dovedirea
   site-ului (prin Google Search Console).
5. Numele dezvoltatorului, adresa și emailul apar public în Google Play (pentru organizații).
6. **(de verificat)** Din 2026 Google introduce „verificarea dezvoltatorilor Android” pentru toate aplicațiile;
   conturile Play Console verificate la pasul 4 o îndeplinesc deja, dar urmăriți emailurile Google.

## 4. Fișele aplicației în magazine

Textele de lipit, răspunsurile la întrebări și planul pozelor sunt în folderul `docs/store/`:

| Fișier | Ce conține |
|---|---|
| `store/listing.md` | nume, subtitlu, descrieri, cuvinte cheie, noutăți, categorii, linkuri |
| `store/screenshots.md` | ce capturi de ecran, la ce dimensiuni (din aplicația reală, cu contul de recenzie) |
| `store/privacy-answers.md` | App Privacy (Apple), Data safety și Health apps (Google), servicii în prim-plan, alte declarații Play |
| `store/content-rating.md` | vârsta (Apple) și chestionarul IARC (Google) |
| `store/review-notes.md` | notele pentru recenzori (de ce nu e „site într-o ramă”, nimic de cumpărat), riscurile |
| `review-account.md` | contul de recenzie: ce face fragmentul și ce comenzi pregătiți |
| `store/privacy-policy-addendum.md` | paragraful de pus în Politica de confidențialitate a site-ului |

**Pe site, înainte de trimitere** (fără ele recenzia se oprește): paragraful din Politica de confidențialitate,
pagina de suport (ex. `/contact/`) și pagina publică despre ștergerea contului (textul în `privacy-answers.md`).

### Apple
1. https://developer.apple.com/account › Certificates, IDs & Profiles › **Identifiers** › „+” › App IDs › App.
   - Description: `Adela Haru`, Bundle ID **Explicit**: `ro.adelaharu.app`.
   - La Capabilities bifați: **Associated Domains** (linkurile din emailuri) și **Push Notifications**. Register.
2. https://appstoreconnect.apple.com › Apps › „+” › **New App**:
   - Platforms: iOS · Name: `Adela Haru` · Primary Language: **Romanian** · Bundle ID: `ro.adelaharu.app` ·
     SKU: `ADELAHARU-APP` · User Access: Full Access.
3. App Information: categoria Health & Fitness (secundară Lifestyle), „Content Rights”: aplicația **nu** conține
   conținut al altora (meditațiile, cursurile și videourile sunt ale voastre), Age Rating (`content-rating.md`).
4. Pricing and Availability: **Free**. App Privacy: din `privacy-answers.md`, plus linkul politicii.
5. La versiune: textele, capturile, „App Review Information” (`review-notes.md`). Build-ul vine din GitHub (pasul 10).

### Google
1. Play Console › **Creați o aplicație**: numele `Adela Haru`, limba prestabilită **română (ro)**, Aplicație (nu joc),
   **Gratuită**, bifați declarațiile.
2. Completați toate secțiunile din **Conținutul aplicației** (politica de confidențialitate, reclame: nu, acces la
   aplicație, clasificare, public țintă 18+, siguranța datelor, aplicații de sănătate, ștergerea contului,
   **permisiuni pentru servicii în prim-plan** cu videoul cerut) din `privacy-answers.md` și `content-rating.md`.
3. **Fișa magazinului principală**: din `listing.md` și `screenshots.md`.
4. **Semnarea aplicației de către Google Play** (Play App Signing): lăsați varianta implicită. Voi păstrați doar
   **cheia de încărcare** (pasul 5).

## 5. Cheia de încărcare Android (upload keystore)

Cu ea se semnează fiecare versiune trimisă la Google. Se face **o singură dată**, pe un calculator (nu în GitHub).
Dacă o pierdeți, Google o poate reseta la cerere (câteva zile), dar e mai simplu să nu o pierdeți.

Programul `keytool` vine cu Java sau cu Android Studio:
- Mac: `/Applications/Android Studio.app/Contents/jbr/Contents/Home/bin/keytool`
- Windows: `C:\Program Files\Android\Android Studio\jbr\bin\keytool.exe`

Comanda (în Terminal / PowerShell):
```
keytool -genkeypair -v -keystore adelaharu-upload.jks -alias upload -keyalg RSA -keysize 2048 -validity 10000
```
Vă cere o parolă (folosiți o parolă lungă, nouă) și câteva date (nume, organizație „HARU TRAINING SRL”, oraș,
țara „RO”). La „parola cheii”, apăsați Enter ca să fie aceeași parolă.

Transformați fișierul în text (pentru secretul GitHub):
- Mac: `base64 -i adelaharu-upload.jks | pbcopy` (textul ajunge în clipboard)
- Windows (PowerShell): `[Convert]::ToBase64String([IO.File]::ReadAllBytes("adelaharu-upload.jks")) | Set-Clipboard`

Păstrați `adelaharu-upload.jks` și parola în managerul de parole **și** pe un stick, nu în e-mail, nu în repo.

Secretele rezultate: `ANDROID_KEYSTORE_BASE64` (textul lung), `ANDROID_KEYSTORE_PASSWORD` (parola),
`ANDROID_KEY_ALIAS` (`upload`), `ANDROID_KEY_PASSWORD` (aceeași parolă).

## 6. iOS: cheia App Store Connect API și semnarea (fastlane match)

### De ce așa (pentru o singură persoană, fără Mac)
Variante posibile: (a) certificat + profil puse manual ca secrete: trebuie un Mac pentru a le crea și exporta, se
refac de mână în fiecare an, 4 secrete în plus; (b) semnarea automată din Xcode pe serverul GitHub: creează
certificate noi la fiecare rulare și se strică des; (c) **fastlane match**: prima rulare din GitHub creează singură,
prin cheia API, certificatul și profilul, le **criptează** cu parola voastră și le păstrează într-un repo GitHub
privat; rulările următoare doar le citesc. **Nu e nevoie de Mac niciodată**, iar reînnoirea anuală e un clic.
Am ales (c).

### Cheia API
1. https://appstoreconnect.apple.com › Users and Access › **Integrations** › App Store Connect API.
   (Prima dată, Account Holder-ul apasă „Request Access” și acceptă.)
2. Team Keys › „+” › Name: `GitHub Actions`, Access: **Admin**. (Cu rol mai mic, match nu poate crea certificatul.
   **(de verificat)** după prima rulare reușită s-ar putea folosi o cheie „App Manager”.)
3. **Download API Key** (fișierul `AuthKey_XXXXXXXXXX.p8`): **se poate descărca o singură dată**.
4. Notați **Key ID** (în tabel) și **Issuer ID** (deasupra tabelului).

Secretele: `ASC_KEY_ID`, `ASC_ISSUER_ID`, `ASC_KEY_P8` (deschideți `.p8` cu un editor de text și copiați tot,
inclusiv liniile `-----BEGIN PRIVATE KEY-----` / `-----END PRIVATE KEY-----`), `APPLE_TEAM_ID` (pasul 2.7).

### Repo-ul de certificate (match)
1. Pe GitHub: un repo **nou, privat, gol**, de ex. `adelaharu-certificate`.
2. Un token doar pentru el: GitHub › Settings › Developer settings › Personal access tokens › **Fine-grained tokens** ›
   Generate: Repository access = „Only select repositories” › `adelaharu-certificate`; Permissions › Contents:
   **Read and write**; Expiration: 1 an (**puneți-vă un memento să îl reînnoiți**, altfel build-urile iOS cad).
3. Faceți textul de autentificare (înlocuiți `utilizator` cu numele contului GitHub și `token` cu tokenul):
   - Mac: `printf 'utilizator:token' | base64`
   - Windows (PowerShell): `[Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes("utilizator:token"))`
   (Nu folosiți site-uri de „base64 online” pentru asta.)
4. Alegeți o **parolă match** lungă (o frază de 5–6 cuvinte), care criptează certificatele.

Secretele: `MATCH_GIT_URL` (ex. `https://github.com/utilizator/adelaharu-certificate.git`),
`MATCH_GIT_BASIC_AUTHORIZATION` (textul de la punctul 3), `MATCH_PASSWORD` (parola de la punctul 4).

## 7. Firebase (notificările push)

1. https://console.firebase.google.com › **Add project** › `Adela Haru`. **Google Analytics: dezactivat** (altfel
   răspunsurile de confidențialitate din magazine nu mai sunt corecte).
2. **Aplicația Android**: Project Overview › Add app › Android › package `ro.adelaharu.app`, nume `Adela Haru Android`
   › Register › descărcați `google-services.json`. Restul pașilor din asistent („Add SDK”) îi sare programatorul.
3. **Aplicația iOS**: Add app › iOS › Bundle ID `ro.adelaharu.app` › Register › descărcați `GoogleService-Info.plist`.
   Pașii „Add Firebase SDK” / „Add initialization code” din asistent **se sar**: proiectul are deja pachetul
   `firebase-ios-sdk` (doar `FirebaseCore` + `FirebaseMessaging`, fără Analytics) și codul din `AppDelegate`.
   Pe developer.apple.com › Identifiers › `ro.adelaharu.app`: bifați **Push Notifications** (capabilitatea; fără ea
   semnarea cu `aps-environment` din `App.entitlements` nu trece). După bifare, profilele se refac (match cu
   „creează certificate”).
4. **Cheia APNs** (ca Apple să livreze notificările prin Firebase):
   - https://developer.apple.com/account › Certificates, IDs & Profiles › **Keys** › „+” › Name `Firebase APNs`,
     bifați **Apple Push Notifications service (APNs)** › Configure: „Sandbox & Production” › Continue › Register ›
     **Download** (fișier `.p8`, o singură dată). Notați Key ID.
   - Firebase › Project settings (rotița) › **Cloud Messaging** › Apple app configuration › APNs Authentication Key ›
     Upload: fișierul `.p8`, Key ID și Team ID.
5. **Cheia pentru site** (cu ea fragmentul trimite notificările): Firebase › Project settings › **Service accounts** ›
   „Generate new private key” › se descarcă un fișier JSON. Îl lipiți în **Setări › Aplicația mobilă › Cheia
   Firebase** (pasul 8), nu altundeva. **Nu îl puneți în GitHub, în emailuri sau în chat.**

Secretele GitHub: `GOOGLE_SERVICES_JSON` (conținutul `google-services.json`, ca text) și `GOOGLE_SERVICE_INFO_PLIST`
(conținutul `GoogleService-Info.plist`, ca text). Fără ele aplicația se construiește **fără notificări** (restul merge,
iar puntea nici nu cere permisiunea). Pe iPhone, workflow-ul scrie fișierul în `app/ios/App/App/GoogleService-Info.plist`
(ignorat de git); faza de build „GoogleService-Info.plist (dacă există)” îl copiază în aplicație. Local (Xcode), îl
puneți voi în același loc. Fără el build-ul iOS merge, dar **fără push**: atunci debifați „Versiunea promite notificări
push pe iPhone” (`push_ios`) și scoateți push-ul din notele Apple și din fișa App Store (`store/review-notes.md`,
`store/listing.md`). Cu `push_ios` bifat, verificarea din workflow cere `aps-environment` = `production`, pachetul
`firebase-ios-sdk`, faza care copiază plist-ul și **fișierul** plist (valid): fără secret, încărcarea în TestFlight
cade (fără încărcare, doar avertisment). La fel pe Android, cu `push_android` și `GOOGLE_SERVICES_JSON`.
Proba: un telefon real cu build din TestFlight, Mai mult › Notificări, apoi „Probă” din Setări › Aplicația
mobilă (tokenul trimis e FCM, de forma `…:APA91b…`; un token de 64 de cifre hexa ar însemna că Firebase nu a pornit).

### Contul de serviciu pentru Google Play (încărcarea automată a versiunilor)
1. https://console.cloud.google.com › alegeți proiectul Firebase de mai sus (e și proiect Google Cloud).
2. APIs & Services › Library › **Google Play Android Developer API** › Enable.
3. IAM & Admin › **Service Accounts** › Create: nume `github-play-upload`, fără roluri › Done. Deschideți-l › Keys ›
   Add key › Create new key › **JSON** › se descarcă fișierul. **(de verificat)** Dacă apare eroarea că organizația
   nu permite chei, contul Google Workspace are politica `iam.disableServiceAccountKeyCreation`; un administrator
   o poate opri pentru acest proiect.
4. Play Console › **Utilizatori și permisiuni** › Invitați utilizatori noi › emailul contului de serviciu
   (`github-play-upload@….iam.gserviceaccount.com`) › Permisiuni pentru aplicația Adela Haru: „Lansați aplicații
   pe canalele de testare” (și, dacă vreți, „…în producție”) › Invitați. Poate dura până la o zi să devină activ.

Secretul: `PLAY_SERVICE_ACCOUNT_JSON` (conținutul fișierului JSON, ca text).

## 8. Secretele în GitHub și setările fragmentului

### Secretele în GitHub (unde exact)

În repo-ul aplicației pe GitHub: **Settings › Secrets and variables › Actions › tab „Secrets” ›
„New repository secret”**. Numele exact (cu majuscule), valoarea lipită, „Add secret”. GitHub nu le mai arată
niciodată după salvare (doar le poate înlocui), iar ele nu apar în jurnalele build-urilor.

| Secret | De unde | Pentru |
|---|---|---|
| `ANDROID_KEYSTORE_BASE64` | pasul 5 | Android |
| `ANDROID_KEYSTORE_PASSWORD` | pasul 5 | Android |
| `ANDROID_KEY_ALIAS` | pasul 5 (`upload`) | Android |
| `ANDROID_KEY_PASSWORD` | pasul 5 | Android |
| `PLAY_SERVICE_ACCOUNT_JSON` | pasul 7, contul de serviciu | Android, încărcarea în Play |
| `GOOGLE_SERVICES_JSON` | pasul 7.2 | Android, notificări (opțional) |
| `ASC_KEY_ID` | pasul 6 | iOS |
| `ASC_ISSUER_ID` | pasul 6 | iOS |
| `ASC_KEY_P8` | pasul 6 (textul `.p8`) | iOS |
| `APPLE_TEAM_ID` | pasul 2.7 | iOS |
| `MATCH_GIT_URL` | pasul 6 | iOS |
| `MATCH_GIT_BASIC_AUTHORIZATION` | pasul 6 | iOS |
| `MATCH_PASSWORD` | pasul 6 | iOS |
| `GOOGLE_SERVICE_INFO_PLIST` | pasul 7.3 | iOS, notificări (opțional) |

### Setările fragmentului de pe site (Setări › Aplicația mobilă)

Pagina de setări apare doar când fragmentul e activ, deci le completați imediat după activare (pasul 10.3),
în aceeași sesiune.

| Câmp | De unde | La ce folosește |
|---|---|---|
| Apple Team ID | pasul 2.7 (10 caractere) | `/.well-known/apple-app-site-association`: linkurile din emailuri deschid aplicația pe iPhone |
| Amprentele SHA-256 (Android) | Play Console › Testare și lansare › Integritatea aplicației › **Semnarea aplicației**: „cheia de semnare a aplicației” și „cheia de încărcare”, câte una pe rând | `/.well-known/assetlinks.json`: același lucru pe Android |
| Link App Store / Link Google Play | după publicare (pasul 10.7) | pagina `/app/intra` (pentru cine deschide un link fără aplicație) și banda „Actualizează” |
| Versiunea minimă iPhone / Android | gol la început | sub ea, aplicația arată o bandă cu „Actualizează” (de folosit doar dacă o versiune veche se strică) |
| Mesajul din bandă | opțional | textul benzii de actualizare (CIORNĂ, Adela) |
| Cheia Firebase | pasul 7.5 (tot fișierul JSON) | notificările. Fără ea nu pleacă nimic; apar doar în jurnalul de jos al paginii |
| Ce anunțăm | bifele: meditație nouă, înregistrare „Respiră-te acasă”, întâlnire în 15 minute | ce notificări pleacă |
| Emailul echipei | implicit `contact@adelaharu.ro` | aici vine anunțul când o clientă își șterge contul din aplicație |
| Probă | adresa voastră | trimite o notificare de probă telefoanelor acelei adrese (trebuie să fi intrat în aplicație și să fi permis notificările) |
| Contul de recenzie (în fragment din 18 nov., seara: `ah-sp-aplicatie.php` 2.1.0, secțiunea 9, cu teste; de verificat pe build-ul din TestFlight / testul intern) | adresa și codul fix (`docs/review-account.md`) | ca Apple și Google să poată intra fără email. Din 2.2.0: după 20 de coduri greșite se **oprește singur** și vine un email la Emailul echipei; îl porniți din nou de aici (bifa „Pornește din nou contul de recenzie” sau un cod nou) |

**Fila „Statistici”** (2.4.0, sus pe aceeași pagină): utilizatoare active (azi, 7, 30 de zile), deschideri pe zi
(grafic + tabel), iPhone / Android, versiunile folosite, telefoanele cu notificări și ce au ales, notificările
deschise pe categorie, meditațiile pornite și ascultate până la capăt, descărcările, Ciclul meu, pagina „Pentru el”,
videourile; butonul „Descarcă tot (CSV)”. Doar numere, fără adrese. Contoarele se șterg singure după 13 luni.

Sub formular sunt linkurile de verificat (`apple-app-site-association`, `assetlinks.json`, `/app/intra`, `config`):
fiecare trebuie să se deschidă fără eroare. Tot acolo e jurnalul (ultimele rânduri).

**Pe server, o dată** (de verificat cu găzduirea; aceeași listă ca `server/README.md`, pasul 5, și `docs/partener.md`):
- **LiteSpeed Cache › Excludes**, toate trei câmpurile (fragmentul trimite deja „no-cache”, dar o pagină deja în cache
  e servită **înainte** de PHP, deci fără ele aplicația ar putea primi copia de browser a unei pagini, cu bara
  Complianz, Google Analytics și pixelul Meta, adică exact ce etichetele de confidențialitate spun că lipsește, sau o
  pagină de magazin pe care redirecționarea ar fi trebuit s-o ascundă):
  - **„Do Not Cache User Agents”** += `AdelaHaruApp`;
  - **„Do Not Cache Cookies”** += `ah_app`, `ah_spp` și (2.3.0) `ah_sp`;
  - **„Do Not Cache URIs”** += `/wp-json/ah-` (2.3.0: toate rutele Spațiului meu, ale extensiilor, ale lui și ale aplicației;
    înlocuiește `/wp-json/ah-spa/`), `/app/`, `/.well-known/`, `/pentru-el/`.
  Din 2.3.0 fragmentul trimite și singur, pe toate rutele `/wp-json/ah-sp*/v1/` (în afară de `punte` și `config`),
  `Cache-Control: no-store, private` și `X-LiteSpeed-Cache-Control: no-cache`.
- **`/wp-json/ah-spa/v1/config`** (2.3.0) trebuie să rămână public: pagina offline a aplicației îl întreabă ca să știe dacă
  lipsește internetul sau site-ul e în lucru (un plugin de securitate care îl blochează face ca aplicația să spună mereu
  „Site-ul e în lucru”).
  Pagina `/spatiul-meu/` nu se cachează nici azi.
- **`/.well-known/`**: dacă există un folder real (de la certificatul SSL), `apple-app-site-association` și
  `assetlinks.json` trebuie să ajungă la WordPress. Verificare: `https://www.adelaharu.ro/.well-known/assetlinks.json`
  se deschide (cod 200, JSON) **fără redirect** (Apple nu urmează redirecturi).
- Pluginurile de securitate care blochează REST API-ul pentru vizitatori trebuie să lase liber `ah-spa/v1` și `ah-sp/v1`.
  Din 2.2.0 **și puntea aplicației vine de acolo**: `GET /wp-json/ah-spa/v1/punte?f=js|css&v=<versiune>` (același fișier
  pentru toate, fără date personale; `Cache-Control: public, max-age=31536000, immutable`). Dacă e blocată, aplicația
  arată Spațiul meu ca în browser, fără funcțiile de telefon. Verificare: adresa din `<script id="ahspa-js" src=…>` se
  deschide în browser (cod 200, JavaScript). Excluderea `/wp-json/ah-spa/` din LiteSpeed (mai sus) poate rămâne: fișierul
  se ține în cache-ul WebView-ului (un an, adresa se schimbă la fiecare versiune), nu în LiteSpeed.
- **Copia de siguranță (backup) a site-ului**: 2.2.0 creează la activare un tabel mic nou, `wp_ah_spa_incercari`
  (contoarele încercărilor de cod; doar amprente, fără adrese). Nu trebuie păstrat într-o restaurare; poate fi gol.
- **Copia telefonului**: pe Android nimic din aplicație nu intră în copia Google și nici la mutarea pe alt telefon
  (`allowBackup=false` + `data_extraction_rules.xml` / `backup_rules.xml`). Pe iPhone, meditațiile descărcate, datele
  WebView (localStorage-ul Ciclului meu) și cookie-urile sunt marcate „fără backup” (`AppDelegate.faraBackup()`);
  **Preferences** (UserDefaults: copia `ah_ciclu` a ciclului și lista descărcărilor) rămân în copia iCloud / din computer a
  telefonului (criptată dacă ea a ales așa) — iOS nu permite excluderea pe chei. De spus în politica de confidențialitate
  dacă e nevoie (`docs/juridic.md`).

## 9. Proba pe telefon (înainte de orice trimitere)

Aplicația încarcă **site-ul live**, deci proba se face cu fragmentul activ (pasul 10.3). Nimic din aplicație nu
scrie pe site altceva decât ar scrie o clientă (intrare, ciclu, notificări).

**Cum ajunge aplicația pe telefon:**
- **Android:** GitHub › Actions › „Android” › ultima rulare verde de pe `main` › Artifacts › `adelaharu-debug-…`
  (zip cu un `.apk`). Pe telefon: deschideți fișierul, permiteți „instalarea din surse necunoscute” pentru acea
  aplicație, Instalați. Sau, după primul pas din 10.4, din Google Play (testare internă).
- **iPhone:** doar prin **TestFlight** (pasul 10.4) sau, pentru programator, din Xcode pe un Mac.

**Lista de încercat** (iPhone și Android, cu contul vostru și cu contul de recenzie):
1. Intrarea cu emailul și codul; închideți aplicația de tot și redeschideți-o a doua zi: trebuie să fie tot în cont.
2. Fiecare secțiune: Acasă, Ciclul, Cursuri, Inner Temple, Cărți, Abonamente, Podcast, Mai mult. **Nicăieri** preț,
   „cumpără”, „reînnoiește”, portalul de plată, card cadou, „Recomandă”, Black Friday, sugestii de produse, fila
   Comenzi (lista completă: `store/review-notes.md`, „Riscuri”, punctul 1). Orice link spre restul site-ului trebuie
   să vă întoarcă în cont.
3. Inner Temple: „Descarcă” la o meditație; mod avion; redeschideți aplicația: apare pagina „Nu avem internet acum”
   cu faza de azi și meditația descărcată; o ascultați; blocați ecranul: titlul, poza și butoanele apar pe ecranul
   blocat, sunetul continuă. „Șterge descărcarea” o scoate.
4. Ciclul: în „Mai mult” › „Notificări” porniți „Mementourile ciclului” și permiteți notificările; puneți prima zi
   în Ciclul meu (programatorul poate muta data ca să vedeți o notificare a doua zi). Apoi: Web Inspector /
   `chrome://inspect` › ștergeți `localStorage` (cum face iOS după zile fără folosire) › redeschideți: ciclul
   trebuie să fie tot acolo, refăcut din copia de pe telefon, iar mementourile programate (**18 nov.: încă nu**, vezi
   „Ce mai trebuie”).
5. Notificări: de la **a doua** deschidere, aplicația întreabă **o singură dată**, cu un ecran de explicație.
   Acceptați; din Setări › Aplicația mobilă › Probă trimiteți-vă o notificare; atingeți-o: deschide locul potrivit.
   Pe iPhone, doar dacă build-ul are Firebase și `aps-environment`; altfel nu apare nicio întrebare (normal) și
   push-ul se scoate din notele Apple și din fișa App Store.
6. Un video Vimeo dintr-o lecție și din explicațiile meditațiilor pornește în aplicație.
7. Linkul personal din emailul Spațiului meu (`?cont=…`), atins pe telefon, deschide **aplicația**, deja în cont.
8. Zoom, WhatsApp, Spotify / Apple Podcasts se deschid în aplicațiile lor; întoarcerea vă aduce în aplicație.
9. „Mai mult” › „Confidențialitate” › „Șterge contul” pe contul de recenzie. Intrați din nou cu codul fix: conținutul
   cumpărat (cursul, Inner Temple, cartea) apare din nou, pentru că vine din comenzi, dar ciclul, notițele,
   telefoanele și notificările nu mai sunt. Exact asta spun notele pentru Apple (recenzorii fac și ei proba asta).
   Dacă „Pentru el” e activ: invitați o adresă de probă, deschideți linkul ei pe alt telefon (se deschide aplicația,
   ecranul „Azi” al lui), opriți accesul din contul ei (pagina lui arată că accesul e oprit). „Trimite din nou
   invitația” deconectează telefonul lui (la redeschidere, ecranul fără cont). Pe ecranul de intrare, „Sunt
   partenerul ei” › linkul lipit în câmp deschide pagina lui în aplicație (așa intră recenzorul Apple). **După proba de
   ștergere, refaceți ciclul și partenerul de recenzie** (`review-account.md`, „Partenerul de recenzie”, punctul 3).
10. Cache-ul: în aplicație, deschideți din „Mai mult” Politica de confidențialitate și Termenii (pagini care pentru
    browser sunt în cache-ul LiteSpeed): **nu** apare bara de cookie-uri Complianz (`#cmplz-cookiebanner-container`),
    iar în Web Inspector / `chrome://inspect` nu pleacă nicio cerere spre `googletagmanager.com`,
    `google-analytics.com`, `connect.facebook.net`, `facebook.com/tr`. Dacă apare bara, lipsesc excluderile de mai sus
    („Pe server, o dată”): User Agents `AdelaHaruApp`, Cookies `ah_app` / `ah_spp`.
11. În browserul calculatorului și al telefonului, Spațiul meu arată **exact ca înainte** (fragmentul nu atinge
    browserele). Aceasta e cea mai importantă verificare a site-ului.

**De verificat pe telefon, în plus, de la 2.2.0** (harnașul le probează pe imitații, nu pe telefoane reale):
12. **Două conturi pe același telefon**: A intră, notează ciclul, pornește mementourile, descarcă o meditație, iese
    („Ieși din cont pe acest telefon”); B intră: Ciclul meu e gol, nicio notificare a lui A, nicio meditație a lui A,
    iar contul lui B (cu salvarea în cont pornită) nu primește datele lui A. Apoi la fel **fără** ieșire (A rămâne
    cu cookie-ul expirat, B intră prin linkul ei din email).
13. **Android 7–10** (de ex. un telefon vechi sau emulatorul API 29): „Descarcă” o meditație (cere permisiunea de
    stocare o dată; dacă refuzați, mesajul e în română și nu rămâne niciun fișier pe jumătate).
14. **Android ≤ 12**: la prima deschidere nu pornesc nici push, nici mementourile; de la a doua apare explicația;
    după „Da”, pornesc amândouă. **Android 13+ / iPhone**: la fel, cu întrebarea sistemului după „Da”.
15. **Butonul Înapoi pe Android**: cu o foaie deschisă (player, podcast, ștergerea contului) o închide; în Spațiul meu
    merge înapoi prin secțiuni; la început aplicația trece în fundal; pe pagina offline nu revine la site în buclă.
16. **Pictograma notificărilor pe Android**: push-ul de probă și un memento au lotusul alb (nu un pătrat alb).
17. **Ecranul blocat**: mementoul ciclului arată doar „Ciclul meu / Începe o fază nouă…”; pe Android, cu „ascunde
    conținutul sensibil”, nici atât.
18. **Zonele sigure (iPhone cu notch / Dynamic Island, Android cu decupaj, și în landscape)**: antetul, bara de jos,
    bara „Înapoi în Spațiul meu” din sala de curs și politici nu intră sub ceas / bara de gesturi; nicio margine albă.
19. **Pornirea**: ecranul de pornire rămâne până apare Spațiul meu (fără ecran alb); dintr-un link `/app/intra?cont=…`
    aplicația deschide direct contul. Pe pagina lui `/pentru-el/` splash-ul se ascunde cel târziu după 4 s.
20. **iPhone, puntea**: în Web Inspector, dintr-un iframe Vimeo, `window.webkit.messageHandlers.bridge.postMessage(…)`
    nu are efect (consola Xcode: „AhPuntePazita: mesaj ignorat”); din pagina principală totul merge (inclusiv pagina
    offline, `capacitor://www.adelaharu.ro`). Vimeo, Spotify, Apple Podcasts: video-ul pornește în pagină, linkurile se
    deschid în aplicațiile lor.
21. **Rafala de coduri**: pe un cont de probă, 6 coduri greșite trimise repede (de ex. din consolă, `Promise.all`) ⇒ cel
    mult 5 ajung la 235, apoi „Prea multe încercări. Cere un cod nou.”; un cod nou cerut ⇒ din nou 5.

**De verificat pe telefon, în plus, de la 2.3.0** (fragmentul 2.3.0 + aplicația construită din acest `app/`):
22. **Site-ul în lucru** (cu programatorul: un 503 de probă pe `/spatiul-meu/`, de ex. modul de mentenanță): aplicația
    deschide pagina offline cu „Site-ul e în lucru” (nu „Nu avem internet”), pe iPhone și pe Android; în mod avion,
    „Nu avem internet acum”. Încercările automate nu fac buclă (5 s, apoi tot mai rar); după ce site-ul revine, aplicația
    se întoarce singură în Spațiul meu (sau la „Încearcă din nou”).
23. **Cookie-ul expirat** nu mai șterge nimic: cu `ah_sp` șters (Web Inspector), meditațiile descărcate și ciclul rămân;
    după intrarea cu același email, totul e la locul lui.
24. **„Șterge contul” întrerupt**: atingeți „Șterge contul” și închideți aplicația din comutatorul de aplicații imediat
    după „Se șterge…”; la redeschidere nu mai rămâne nimic pe telefon (descărcări, ciclu, mementouri).
25. **Fusul orar**: cu mementourile pornite, schimbați fusul orar al telefonului (Setări › Dată și oră) și reveniți în
    aplicație: notificările programate (Web Inspector: `LocalNotifications.getPending()`) sunt tot la 9:00, ora locului.
26. **Android, procesul WebView oprit**: `adb shell am kill` nu ajunge; cu programatorul,
    `adb shell "ps -A | grep sandboxed_process"` și `adb shell kill <pid>` pe procesul WebView al aplicației: aplicația
    nu se închide, ecranul se reface (Spațiul meu de la capăt).
27. **Accesibilitate**: VoiceOver (iPhone) și TalkBack (Android): la schimbarea secțiunii se citește titlul (ex. „Ciclul
    meu, titlu 1”); în „Mai mult”, comutatoarele se anunță „comutator, activat/dezactivat”; foaia „Șterge contul” ține
    focusul în ea, gestul de ieșire (Escape / scrub cu două degete) o închide; codul de intrare greșit se anunță; zoom-ul
    textului la 200% (Setări › Accesibilitate › Text mai mare) nu taie bara de jos.
28. **Modul întunecat al telefonului**: fără să fi atins „Aspect de seară”, Spațiul meu urmează tema telefonului; după
    ce ea alege din comutator, alegerea ei rămâne.
29. **Android cu WebView vechi** (sub 90, de ex. un emulator API 28 fără actualizări): aplicația deschide pagina
    „Telefonul are nevoie de o actualizare” (Capacitor nu încarcă site-ul sub `android.minWebViewVersion`).

**De verificat pe telefon, în plus, de la 2.4.0** (fragmentul 2.4.0 + build-ul cu partea nativă nouă; harnașul le
probează pe imitații):
30. **Sunetul**: o notificare de probă (Setări › Aplicația mobilă › Probă) cu fiecare alegere din Mai mult › Notificări ›
    Sunet: Clopoțel (sunetul nostru, iPhone și Android), Sunetul telefonului, Fără sunet (fără sunet și fără vibrație; pe
    Android canalul `ah_liniste` are un fișier de sunet inexistent, singurul fel prin care Capacitor face un canal mut:
    **dacă totuși sună, spuneți-ne**). Pe iPhone, mementoul ciclului cu „Fără sunet” nu sună. Pe Android, în Setările
    telefonului › Aplicații › Adela Haru › Notificări apar canalele „Notificări”, „…cu clopoțel”, „…fără sunet” și cele ale ciclului.
31. **Ore de liniște** (ex. de acum până peste 30 de minute): o meditație nouă publicată în timpul ei sosește după capăt
    (cronul la 5 minute); o întâlnire care începe în 15 minute sosește la timp, fără sunet.
32. **Pe ecranul blocat: „Doar Adela Haru”**: push-ul și mementourile arată „Adela Haru / Ai un mesaj nou.”; atingerea
    deschide totuși secțiunea potrivită.
33. **Insigna**: cu ea pornită, două notificări ⇒ 2 pe iconiță (iPhone; pe Android, după lansator); la deschiderea
    aplicației, 0.
34. **Mementoul zilnic de meditație**: la ora aleasă, o notificare; după schimbarea orei, doar una (cea nouă).
35. **Aspect › După apus și răsărit**: seara (după apus, București) Spațiul meu trece singur în culorile de seară, în
    cel mult un minut, și bara de stare se schimbă; „Ca telefonul” urmează tema telefonului.
36. **Widgetul**: din galeria de widgeturi; arată meditația zilei (titlu, copertă), apoi „Faza ciclului de azi” după
    alegere; atingerea deschide aplicația în secțiunea potrivită; după ieșirea din cont, widgetul e gol.
37. **Ciclul meu cu Face ID / amprenta**: pornit, la redeschiderea aplicației direct pe Ciclul meu nu se vede nimic din
    ciclu înainte de deblocare (nici o clipă); „Anulează” la Face ID lasă ecranul închis cu „Deblochează”; după mai mult
    de un minut în fundal se închide din nou; cu Face ID refuzat de sistem, codul telefonului merge.
38. **Temporizatorul de somn**: 10 minute cu ecranul blocat ⇒ meditația se oprește. Pe iPhone volumul unei pagini nu
    se poate micșora din JavaScript, deci acolo sunetul se oprește fără stingere; pe Android se stinge în ultimele 10 s.
39. **Descarcă doar pe Wi-Fi**: pe date mobile, „Descarcă” întreabă întâi; pe Wi-Fi, nu.
40. **Statistici**: după câteva deschideri, Setări › Aplicația mobilă › Statistici arată numerele; cu „Statistici
    anonime” oprit, în Web Inspector nu mai pleacă nicio cerere `/ah-spa/v1/stat`.
41. **Vimeo**: în sala de curs, butonul de ecran întreg al playerului merge (allow="fullscreen" pus de punte), cu rotirea.


## 10. Lansarea, în ordine

Regula: **nimic nu se urcă pe site fără OK-ul lui Mircea**, iar fragmentul se urcă **inactiv**, cu copie de
siguranță. Fiecare pas se face doar după ce cel dinainte e verificat.

1. **Copie de siguranță** a site-ului (fișiere + bază de date), din găzduire sau din pluginul de backup.
2. **Fragmentul, inactiv.** Code Snippets › Add New › PHP: titlul „Spațiul meu – modul aplicație”, conținutul lui
   `server/ah-sp-aplicatie.php` **fără** prima linie `<?php`, „Run everywhere”, **Save Changes** (nu „and Activate”).
   Înainte: `php -l` pe fișier trece (îl face și CI-ul la fiecare schimbare).
3. **OK de la Mircea → Activare**, într-un moment liniștit (dimineața), apoi **imediat**:
   - în browser (calculator și telefon): Spațiul meu, sala de curs, Meditațiile mele, Cărțile mele, pagina de
     cumpărare a unui curs: totul ca înainte (punctul 9.10);
   - Setări › Aplicația mobilă: se completează (pasul 8); linkurile de verificat se deschid;
   - dacă ceva nu e în regulă în browser: **dezactivați fragmentul** (site-ul revine exact cum era) și anunțați
     programatorul.
   - „Pentru el” (`ah-sp-partener.php`), dacă e gata: aceiași pași (copie, inactiv, OK Mircea, activare), **după**
     ce modulul aplicație merge; verificare: invitație de probă, linkul lui în browser și în aplicație, „Oprește
     accesul”.
4. **Testare internă:** Android (primul AAB de mână, apoi automat; vezi mai jos) și iPhone prin TestFlight.
   Proba de la pasul 9, pe cel puțin un iPhone și un Android, cu contul vostru și cu cel de recenzie.
5. **Contul de recenzie** pregătit (`review-account.md`) și încercat în build-ul de testare.
6. **Trimiterea la recenzie** (Apple și Google), cu notele din `store/review-notes.md`. Apple: „Manually release this
   version” (o publicați voi după aprobare), ca să alegeți ziua.
7. **După aprobare:** publicați; puneți linkurile din magazine în Setări › Aplicația mobilă; anunțați clientele
   (email, Instagram) cu linkul `/app/intra`.

### Prima dată (o singură dată)
1. **Android, primul AAB se încarcă de mână** (Google nu acceptă încărcarea automată pentru o aplicație nouă).
   GitHub › **Actions** › „Android” › **Run workflow** › branch `main`, **debifați „Încarcă…”** › Run.
   După ~15 minute, în pagina rulării, la „Artifacts”, descărcați `adelaharu-2.0.0-…-aab` (zip; dezarhivați).
   Play Console › Testare › **Testare internă** › Creați o versiune › încărcați fișierul `.aab` › Salvați › Lansați
   pentru testare internă. La „Testeri” adăugați adresele voastre.
2. **iOS, prima rulare creează certificatul**: Actions › „iOS” › Run workflow › bifați **„Prima rulare / capabilități
   noi…”** › Run (~25–40 minute). Build-ul apare în App Store Connect › TestFlight după 10–30 de minute.
3. Instalați TestFlight pe iPhone și linkul de testare internă pe Android.

### De fiecare dată
1. Programatorul crește versiunea în `app/package.json` (ex. `2.0.0` → `2.0.1`; aceeași apare în User-Agent, după
   care o recunoaște fragmentul) și o pune pe `main`. (Numărul intern de build crește singur.)
2. **Android:** Actions › Android › Run workflow (bifat „Încarcă…”, stare `completed`) › în Play Console versiunea
   apare la Testare internă › o încercați › **„Promovați versiunea” › Producție** › notele de lansare › Trimiteți la
   recenzie. (Dacă Play răspunde „Only releases with status draft may be created on draft app”, rulați cu stare
   `draft` și lansați-o de mână din consolă.)
3. **iOS:** Actions › iOS › Run workflow (nebifat „Prima rulare…”) › App Store Connect › TestFlight o încercați ›
   tab-ul App Store › „+” versiune nouă › alegeți Build-ul › „Ce este nou” › **Add for Review / Submit**.
4. **Schimbări doar pe site** (fragmentul): fără versiune nouă de aplicație. Aceeași regulă: copie de siguranță,
   versiunea nouă a fragmentului urcată ca fragment **nou, inactiv**, OK Mircea, se dezactivează cel vechi și se
   activează cel nou, verificare imediată în browser și în aplicație. (Paza `AH_SPA` oprește două copii active
   deodată, dar nu vă bazați pe ea.)

### O dată pe an
- Apple Developer Program se reînnoiește (99 USD). Certificatul iOS expiră după 1 an: rulați „iOS” cu
  „Prima rulare / capabilități noi…” bifat. Tot așa după ce adăugați o capabilitate nouă la App ID.
- Tokenul GitHub pentru repo-ul de certificate (pasul 6) expiră: generați altul și înlocuiți
  `MATCH_GIT_BASIC_AUTHORIZATION`.
- Apple și Google cer periodic SDK-uri noi (Apple: Xcode/iOS SDK nou în fiecare primăvară; Google: „target API”
  nou în fiecare august). Programatorul actualizează Capacitor înainte de termen.

### Despre minutele GitHub
Pentru un repo privat, GitHub dă un număr de minute gratuite pe lună (2000 pe planul gratuit **(de verificat)**);
minutele de macOS se numără **de 10 ori**. Un build iOS de ~25 de minute consumă ~250, deci ~6–7 build-uri iOS
pe lună intră în gratuit. De aceea iOS și încărcările pornesc doar la cerere („Run workflow”), nu la fiecare
schimbare. Peste limită, GitHub cere card și taxează pe minut.

---

## Pentru programator

### Fișierele
- `.github/workflows/ci.yml`: la fiecare PR: aplicația (`npm run lint --if-present`, `typecheck`, `test`, `build`
  cu verificarea că există `dist/offline.html` și `dist/index.html`, apoi `.github/scripts/verifica-shell.mjs`);
  serverul pe PHP 8.1 și 8.4 (`php -l` pe **toate** fișierele PHP din `server/`, paza `AH_SPA`, prefixul `ah_spa_`
  al tuturor funcțiilor fragmentului (și `ah_spp_` pentru `ah-sp-partener.php`, când există) cu `.github/scripts/prefix-fragment.php` (tokenizer, deci ignoră JavaScript-ul
  din șiruri), apoi testele: `php server/tests/run.php`, `node server/tests/ciclu.test.cjs`,
  `php server/tests/partener/run.php`, `node server/tests/partener/faze.test.cjs`, `php server/tests/integrare/run.php`
  (ca `server/tests/ruleaza.sh`, fără harnașele vizuale `poze.cjs`, care rescriu pozele din `docs/screenshots-v2`).
  `reference/` e în `.gitignore`, deci în CI testele citesc bucățile din 235/41 și pagina publică din
  `server/tests/date/v2/` (`php server/tests/extrage-referinte.php`; `ruleaza.sh` verifică local că sunt la zi);
  `actionlint` pe workflow-uri.
- `.github/workflows/android.yml`: la push pe `main` care atinge `app/**` ⇒ APK debug (14 zile); la
  `workflow_dispatch` ⇒ AAB semnat (30 de zile) + `fastlane android interna` (Play, track `internal`).
- `.github/workflows/ios.yml`: doar `workflow_dispatch` ⇒ `fastlane ios testflight_beta`. Intrarea `push_ios`
  (implicit bifată) pornește `.github/scripts/verifica-push-ios.sh`: la încărcare în TestFlight, cade dacă lipsește
  `aps-environment` = `production` din `App.entitlements`, pachetul `firebase-ios-sdk` din proiect, **fișierul**
  `app/ios/App/App/GoogleService-Info.plist` (nevid, plist valid: `plutil -lint`, pe Linux `plistlib`) sau faza de
  build care îl copiază (după id-ul ei din `project.pbxproj`; numele plist-ului apare mereu în proiect, deci nu ajunge
  ca dovadă). Notele promit push. CI-ul verifică sintaxa scriptului și îl rulează fără încărcare (doar avertismente).
  `.github/workflows/android.yml` are intrarea pereche `push_android` (implicit bifată): la încărcarea în Play, cade
  dacă lipsește sau e stricat `google-services.json` (secretul `GOOGLE_SERVICES_JSON`), pentru că fișa Google Play
  promite notificări.
- `.github/scripts/verifica-shell.mjs`: după `cap sync`, pe `capacitor.config.json` generat (Android:
  `app/src/main/assets/`, iOS: `App/App/`), iar în CI pe configul rezolvat (`npx cap config --json`). Cade dacă
  `server.url` nu e `https://www.adelaharu.ro/spatiul-meu/`, dacă `errorPath` nu e `offline.html`, dacă User-Agent-ul
  nu e `AdelaHaruApp/<versiunea din package.json> (ios|android)`, dacă `allowNavigation` are o gazdă care nu e în
  `docs/arhitectura.md` sau dacă e pornit `cleartext` / `allowMixedContent`. Prinde mai ales un `server.url` lăsat pe
  un calculator de test după o probă locală.
- `fastlane/Fastfile`, `Appfile`, `Gemfile`, `Gemfile.lock` (fastlane 2.241.x, `BUNDLE_GEMFILE=fastlane/Gemfile`).
- Toate acțiunile sunt fixate pe SHA (comentariul arată versiunea).

### Ce s-a schimbat față de v1 în pipeline
- Build-ul web e doar pagina offline (`app/offline.html` → `dist/offline.html` + `dist/index.html`), fără ecranele
  React, fără `build:mock` și fără `VITE_PUSH`: push-ul se hotărăște **la rulare** (`AhApp.info().push`), după
  existența `google-services.json` / Firebase Messaging în build.
- Testele serverului sunt ale fragmentului v2 (`server/tests/run.php`, stuburi în `server/tests/stubs.php`).
- `reference/` e în `.gitignore`: testul diferențial al ciclului (`ciclu.diferential.test.ts`) rulează pe
  `app/src/offline/fixtures/sn235-ci.js` (copiat în repo) și sare doar partea „e la zi cu sn235” în CI.
  După o schimbare a lui 235 pe site: `node scripts/extrage-ci.mjs` local, apoi commit.

### Detalii
- Node 22, JDK 21 (Temurin), Ruby 3.3, Xcode `latest-stable` pe `macos-latest` (Apple cere SDK-ul iOS 26 pentru
  încărcări **(de verificat)**; Google cere target API 36 de la 31 aug. 2026 **(de verificat)**).
- **Android:** semnarea prin `-Pandroid.injected.signing.*`; `versionCode = 100 + github.run_number`, `versionName`
  din `app/package.json`, puse cu `sed` în `app/android/app/build.gradle`. Verificarea semnăturii AAB: textul
  „jar verified” și aceeași amprentă SHA-256 ca a cheii de încărcare. Cheia se șterge de pe runner la final.
- **iOS:** `setup_ci` › `match(appstore, readonly)` › `update_code_signing_settings` (manual, doar ținta `App`,
  Release) › `build_app` cu `CURRENT_PROJECT_VERSION=<ultimul build TestFlight + 1>` și `MARKETING_VERSION=<package.json>`
  › `upload_to_testflight`. Proiectul folosește SPM (`CapApp-SPM`), fără CocoaPods. Inputul „creează certificate”
  pune `readonly: false, force: true` (profil nou, ex. după Push / Associated Domains).
- Folderele native trebuie să fie în git (Info.plist cu `UIBackgroundModes audio`, `App.entitlements` cu
  `applinks:www.adelaharu.ro`, `PrivacyInfo.xcprivacy`, manifestul Android cu App Links și `AhMediaService`).

### Ce mai trebuie (în afara pipeline-ului)
1. **Push pe iOS — făcut în proiect (18 nov.):** pachetul SPM `firebase-ios-sdk` (≥ 12.0, `FirebaseCore` +
   `FirebaseMessaging`) în ținta App; faza „GoogleService-Info.plist (dacă există)” (fără fișier: build fără push, nu
   eroare); `aps-environment` = `production` în `App.entitlements` (Release) și `development` în `AppDebug.entitlements`
   (Debug); `Info.plist`: `FirebaseAppDelegateProxyEnabled` = NO, `FirebaseMessagingAutoInitEnabled` = NO (tokenul
   FCM apare doar după ce ea permite notificările). `AppDelegate` dă tokenul APNs lui Firebase și trimite la Capacitor
   tokenul **FCM**; `AhApp.info()` spune `push`/`token` (`fcm`|`apns`), iar puntea nu trimite niciodată un token APNs.
   **Rămâne:** capabilitatea Push Notifications pe App ID (pasul 7.3), secretul `GOOGLE_SERVICE_INFO_PLIST`, cheia
   APNs în Firebase (7.4) și proba pe un iPhone real. Prima compilare pe macOS descarcă pachetul Firebase (Xcode 16+).
2. **Doar iPhone (18 nov.):** `TARGETED_DEVICE_FAMILY` = `1`, deci fără capturi și teste pe iPad pentru prima
   versiune. Pe iPad rulează în modul de compatibilitate iPhone.
3. **Contul de recenzie:** în fragment (2.1.0, secțiunea 9), cu teste. Rămân: adresa, comenzile manuale și codul în
   Setări › Aplicația mobilă (`docs/review-account.md`, „Ce pregătește proprietara”) și o intrare reușită în TestFlight.
4. **Fără analiză și pixel în aplicație:** în fragment (2.1.0, secțiunea 10), cu teste și harnașul vizual (nicio
   cerere de urmărire în aplicație, neschimbat în browser). De verificat o dată pe telefon cu Web Inspector.
5. **Ciclul refăcut din `Preferences`:** în fragment, cu test (copia nu se mai șterge când iOS golește `localStorage`).
   De încercat pe iPhone: `localStorage` golit din Web Inspector ⇒ Ciclul meu revine.
6. **Vimeo cu `dnt=1`:** în fragment (server + punte). Apple Podcasts: în aplicație, linkul episodului în locul playerului.

### Rulat local (verificare)
```
cd app && npm ci && npm test && npm run build && node ../.github/scripts/verifica-shell.mjs
php -l server/ah-sp-aplicatie.php && php .github/scripts/prefix-fragment.php server/ah-sp-aplicatie.php && php server/tests/run.php
actionlint .github/workflows/*.yml
BUNDLE_GEMFILE=fastlane/Gemfile bundle exec fastlane lanes
```
