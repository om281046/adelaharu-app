# Cum publicăm aplicația Adela Haru în App Store și Google Play

Ghid pas cu pas pentru proprietară (HARU TRAINING SRL). Nu e nevoie de cunoștințe tehnice; unde e nevoie de un
calculator cu programe speciale, scrie clar. Partea pentru programator e la final („Pentru programator”).

Situația la 3 nov. 2026. Regulile magazinelor se schimbă des: unde am scris **(de verificat)** nu sunt sigur că
informația e încă exactă și merită o privire pe pagina oficială în ziua în care faceți pasul.

## Pe scurt: ce urmează și cât durează

| Pas | Cine | Cât durează (estimat) | Cost |
|---|---|---|---|
| 1. Numărul D-U-N-S al firmei | proprietara | 0 zile (dacă există) până la 1–2 săptămâni | gratuit |
| 2. Apple Developer Program (organizație) | proprietara | 2–14 zile după D-U-N-S | 99 USD pe an (sau echivalentul local; în UE de obicei 99 EUR, cu TVA) **(de verificat)** |
| 3. Google Play Console (organizație) | proprietara | câteva zile până la 2 săptămâni (verificarea firmei) | 25 USD o singură dată |
| 4. Fișele aplicației în magazine | proprietara (+ programatorul) | 1–2 ore | gratuit |
| 5. Cheia de încărcare Android | programatorul, cu proprietara | 15 minute | gratuit |
| 6. Cheia App Store Connect API + repo-ul de certificate | proprietara (+ programatorul) | 30 de minute | gratuit |
| 7. Firebase (notificări) | proprietara (+ programatorul) | 30 de minute | gratuit |
| 8. Secretele în GitHub | proprietara sau programatorul | 20 de minute | gratuit (vezi nota despre minutele GitHub) |
| 9. Prima versiune și recenzia | programatorul + proprietara | Apple 1–3 zile, Google 1–7 zile (prima dată poate dura mai mult) | gratuit |

Realist, de la zero până în magazine: **3–6 săptămâni**, cele mai multe fiind așteptare (D-U-N-S, verificări).
Pașii 1, 2 și 3 se pot începe azi, în paralel cu construirea aplicației.

**Ce NU trebuie:** contract de plăți cu Apple („Paid Apps Agreement”), date bancare sau fiscale în magazine,
Google Play Billing. Aplicația e gratuită și nu vinde nimic, deci ajunge acordul gratuit, care e implicit.

**Păstrați într-un manager de parole** (1Password, Bitwarden, iCloud Keychain): toate parolele și fișierele de mai
jos (cheia Android, fișierele `.p8`, parola match). Unele se pot descărca **o singură dată**.

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
   și în admin-ul site-ului (câmpul „iOS Team ID”).

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
| `store/screenshots.md` | ce capturi de ecran, la ce dimensiuni |
| `store/privacy-answers.md` | App Privacy (Apple), Data safety și Health apps (Google), alte declarații Play |
| `store/content-rating.md` | vârsta (Apple) și chestionarul IARC (Google) |
| `store/review-notes.md` | contul de recenzie și notele pentru recenzori |
| `store/privacy-policy-addendum.md` | paragraful de pus în Politica de confidențialitate a site-ului |

### Apple
1. https://developer.apple.com/account › Certificates, IDs & Profiles › **Identifiers** › „+” › App IDs › App.
   - Description: `Adela Haru`, Bundle ID **Explicit**: `ro.adelaharu.app`.
   - La Capabilities bifați: **Associated Domains** și **Push Notifications**. Register.
2. https://appstoreconnect.apple.com › Apps › „+” › **New App**:
   - Platforms: iOS · Name: `Adela Haru` · Primary Language: **Romanian** · Bundle ID: `ro.adelaharu.app` ·
     SKU: `ADELAHARU-APP` · User Access: Full Access.
3. App Information: categoria Health & Fitness (secundară Lifestyle), „Content Rights”: aplicația **nu** conține
   conținut al altora (meditațiile și videourile sunt ale voastre), Age Rating (vezi `content-rating.md`).
4. Pricing and Availability: **Free**, toate țările (românce sunt peste tot). App Privacy: din `privacy-answers.md`,
   plus linkul politicii de confidențialitate.
5. La versiunea 1.0: textele, capturile, „App Review Information” (vezi `review-notes.md`). Build-ul vine din GitHub (pasul 9).

### Google
1. Play Console › **Creați o aplicație**: numele `Adela Haru`, limba prestabilită **română (ro)**, Aplicație (nu joc),
   **Gratuită**, bifați declarațiile.
2. Panoul aplicației vă arată lista „Configurați aplicația”. Completați toate secțiunile din **Conținutul aplicației**
   (politica de confidențialitate, reclame: nu, acces la aplicație, clasificare, public țintă 18+, siguranța datelor,
   aplicații de sănătate, ștergerea contului) din `privacy-answers.md` și `content-rating.md`.
3. **Fișa magazinului principală**: din `listing.md` și `screenshots.md`.
4. **Semnarea aplicației de către Google Play** (Play App Signing): lăsați varianta implicită (Google păstrează cheia
   de semnare). Voi păstrați doar **cheia de încărcare** (pasul 5).

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
4. **Cheia APNs** (ca Apple să livreze notificările prin Firebase):
   - https://developer.apple.com/account › Certificates, IDs & Profiles › **Keys** › „+” › Name `Firebase APNs`,
     bifați **Apple Push Notifications service (APNs)** › Configure: „Sandbox & Production” › Continue › Register ›
     **Download** (fișier `.p8`, o singură dată). Notați Key ID.
   - Firebase › Project settings (rotița) › **Cloud Messaging** › Apple app configuration › APNs Authentication Key ›
     Upload: fișierul `.p8`, Key ID și Team ID.
5. **Cheia pentru server** (cu ea site-ul trimite notificările):
   Firebase › Project settings › **Service accounts** › „Generate new private key” › se descarcă un fișier JSON.
   Deschideți-l cu un editor de text, copiați tot și lipiți-l în **WordPress › Meditațiile mele › Aplicația mobilă ›
   „Cheia Firebase”** (opțiunea `ah_app_fcm`). **Nu îl puneți în GitHub.** Apoi „Trimite o notificare de probă”.

Secretele GitHub: `GOOGLE_SERVICES_JSON` (conținutul fișierului `google-services.json`, copiat ca text) și
`GOOGLE_SERVICE_INFO_PLIST` (conținutul `GoogleService-Info.plist`, ca text). Fără ele aplicația se construiește
fără notificări push (restul merge).

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

## 8. Secretele în GitHub (unde exact)

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

**Și în admin-ul site-ului** (Meditațiile mele › Aplicația mobilă): iOS Team ID (pasul 2.7); Android SHA-256: din
Play Console › Testare și lansare › Integritatea aplicației › **Semnarea aplicației**, ambele amprente SHA-256
(„cheia de semnare a aplicației” și „cheia de încărcare”), câte una pe rând; Cheia Firebase (pasul 7.5); linkurile
din magazine, după publicare.

## 9. Cum scoatem o versiune

### Prima dată (o singură dată)
1. **Android, primul AAB se încarcă de mână** (Google nu acceptă încărcarea automată pentru o aplicație nouă).
   GitHub › **Actions** › „Android” › **Run workflow** › branch `main`, **debifați „Încarcă…”** › Run.
   După ~15 minute, în pagina rulării, la „Artifacts”, descărcați `adelaharu-1.0.0-…-aab` (zip; dezarhivați).
   Play Console › Testare › **Testare internă** › Creați o versiune › încărcați fișierul `.aab` › Salvați › Lansați
   pentru testare internă. La „Testeri” adăugați-vă adresa (și a programatorului).
2. **iOS, prima rulare creează certificatul**: Actions › „iOS” › Run workflow › bifați **„Prima rulare / capabilități
   noi…”** › Run (~25–40 minute). Build-ul apare în App Store Connect › TestFlight după 10–30 de minute de procesare.
3. Instalați TestFlight pe iPhone și Google Play (link de testare internă) pe Android; încercați aplicația cu contul
   vostru și cu contul de recenzie.

### De fiecare dată
1. Programatorul crește versiunea în `app/package.json` (ex. `1.0.0` → `1.0.1`) și o pune pe `main`.
   (Numărul intern de build crește singur.)
2. **Android:** Actions › Android › Run workflow (bifat „Încarcă…”, stare `completed`) › în Play Console versiunea
   apare la Testare internă › o încercați › **„Promovați versiunea” › Producție** › notele de lansare › Trimiteți la
   recenzie. (Dacă Play răspunde „Only releases with status draft may be created on draft app”, rulați cu stare
   `draft` și lansați-o de mână din consolă.)
3. **iOS:** Actions › iOS › Run workflow (nebifat „Prima rulare…”) › App Store Connect › TestFlight o încercați ›
   tab-ul App Store › „+” versiune nouă (ex. 1.0.1) › alegeți Build-ul › „Ce este nou” › **Add for Review / Submit**.
4. După aprobare: puneți linkurile din magazine în admin (prima dată) și, dacă e cazul, „Versiunea minimă”.

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
- `.github/workflows/ci.yml`: la fiecare PR, aplicația (`npm run lint --if-present`, `typecheck`, `test`, `build`,
  `build:mock`), serverul (`php -l server/*.php server/tests/*.php` pe PHP 8.1 și 8.4, `php server/tests/run.php`)
  și `actionlint` pe workflow-uri.
- `.github/workflows/android.yml`: la push pe `main` ⇒ APK debug ca artefact (14 zile); la `workflow_dispatch` ⇒
  AAB semnat (artefact 30 de zile) + `fastlane android interna` (Play, track `internal`).
- `.github/workflows/ios.yml`: doar `workflow_dispatch` ⇒ `fastlane ios testflight_beta`.
- `fastlane/Fastfile`, `Appfile`, `Gemfile`, `Gemfile.lock` (fastlane 2.241.x; folosit cu
  `BUNDLE_GEMFILE=fastlane/Gemfile`, din rădăcina repo-ului).
- Toate acțiunile sunt fixate pe SHA-ul commit-ului (comentariul arată versiunea). Dependabot/Renovate le poate
  actualiza.

### Detalii
- Node 22, JDK 21 (Temurin), Ruby 3.3, Xcode `latest-stable` pe `macos-latest`. Apple cere SDK-ul iOS 26 (Xcode 26)
  pentru încărcări din aprilie 2026 **(de verificat)**; Google cere target API 35 de la 31 aug. 2025 și API 36 de la
  31 aug. 2026 **(de verificat)**; proiectul are `targetSdkVersion = 36`.
- **Android:** semnarea fără modificări în `build.gradle`, prin `-Pandroid.injected.signing.*` (aceleași proprietăți
  pe care le folosește Android Studio). `versionCode = 100 + github.run_number`, `versionName` = `app/package.json`
  `version`, puse cu `sed` în `app/android/app/build.gradle` (pasul cade dacă nu găsește `versionCode N`, ex. dacă
  proiectul trece la Kotlin DSL). Verificare semnătură pe AAB: ieșirea `jarsigner -verify` trebuie să conțină „jar verified” (codul de ieșire e 0 și pe un AAB nesemnat), iar amprenta SHA-256 a certificatului din AAB (`keytool -printcert -jarfile`) trebuie să fie aceeași cu a cheii de încărcare. Cheia se șterge de pe runner la final.
- **iOS:** `setup_ci` (keychain temporar) › `match(type: appstore, readonly)` › `update_code_signing_settings`
  (manual, „Apple Distribution”, profilul match, doar ținta `App`, doar Release) › `build_app` cu
  `CURRENT_PROJECT_VERSION=<ultimul build TestFlight + 1>` și `MARKETING_VERSION=<package.json>` ca `xcargs`
  (Info.plist folosește deja `$(MARKETING_VERSION)` / `$(CURRENT_PROJECT_VERSION)`) › `upload_to_testflight`
  fără așteptarea procesării. Proiectul folosește SPM (`CapApp-SPM`), fără CocoaPods; dacă apare `App.xcworkspace`,
  Fastfile îl folosește. `aps-environment` din entitlements e rescris în `production` de exportul App Store.
  Inputul „creează certificate” pune `readonly: false, force: true` (profil nou, ex. după o capabilitate nouă).
- Dacă `app/android` sau `app/ios` lipsesc din repo, workflow-urile fac `npx cap add` cu avertisment, dar
  setările native (Info.plist, entitlements, manifest) se pierd; **folderele native trebuie să fie în git.**
- `google-services.json` / `GoogleService-Info.plist` se scriu din secrete doar dacă există, și atunci build-ul web
  primește `VITE_PUSH=1` (vezi `app/src/lib/push.ts`).

### Ce trebuie să mai facă aplicația (nu e în pipeline)
1. **FCM pe iOS:** `@capacitor/push-notifications` dă pe iOS tokenul **APNs**, nu tokenul FCM, iar serverul trimite
   prin FCM HTTP v1. E nevoie de Firebase Messaging pe iOS (ex. `@capacitor-firebase/messaging`, sau `FirebaseMessaging`
   în `AppDelegate` cu `Messaging.messaging().apnsToken`) ca să ajungă la server un token FCM.
2. `GoogleService-Info.plist` trebuie referit în proiectul Xcode (Copy Bundle Resources), altfel fișierul scris de
   workflow nu ajunge în aplicație.
3. `PrivacyInfo.xcprivacy` pentru aplicație (tipurile de date din `store/privacy-answers.md` și motivele pentru API-urile
   „required reason”).
4. `TARGETED_DEVICE_FAMILY` e acum `1,2` (iPhone + iPad) ⇒ Apple cere capturi de iPad și testează pe iPad. Pentru v1
   recomand `1` (doar iPhone).
5. Textul de pe meditațiile blocate pe iOS (vezi `store/review-notes.md`, „Riscuri”).
6. Contul de recenzie pe server: `docs/review-account.md`.

### Rulat local (verificare)
```
bundle exec fastlane lanes                     # cu BUNDLE_GEMFILE=fastlane/Gemfile
actionlint .github/workflows/*.yml
```
