# Aplicația Adela Haru v2 (Android + iPhone)

Capacitor 8, app id `ro.adelaharu.app`, nume „Adela Haru”, versiunea 2.0.0.
Sursa de adevăr: [`../docs/arhitectura.md`](../docs/arhitectura.md). Pe scurt: aplicația **deschide Spațiul meu de pe site**
(`https://www.adelaharu.ro/spatiul-meu/`) și adaugă doar ce site-ul nu poate face. Tot ce ține de aplicație pe site
stă în fragmentul `../server/ah-sp-aplicatie.php`, care acționează doar când cererea vine din aplicație.

Aici (în `app/`) sunt doar: configurația shell-ului, pagina offline locală, două pluginuri native mici, iconițele.
Ecranele React, API-ul și datele de probă din v1 au fost scoase (rămân în istoricul git).

## Cum funcționează

| Ce | Unde | Cum |
|---|---|---|
| Pagina principală | `capacitor.config.ts` → `server.url` | WebView-ul încarcă Spațiul meu; ce adaugă echipa pe site apare fără versiune nouă. |
| Recunoașterea aplicației | `ios.appendUserAgent` / `android.appendUserAgent` | UA se termină cu `AdelaHaruApp/2.0.0 (ios)` sau `(android)`; versiunea vine din `package.json`. |
| Fără internet | `server.errorPath: 'offline.html'` | Capacitor încarcă pagina locală când pagina principală nu se încarcă (iOS: `didFail`/`didFailProvisionalNavigation`, mai puțin navigările anulate -999 / WebKit 102, pe care le filtrează `AhNavigare` din `AhAppPlugin.swift`; Android: `onReceivedError` **și** `onReceivedHttpError` pe cadrul principal, deci și la un 404/500 de pe site). |
| Contul pentru el | `server.url` + fragmentul `ah-sp-partener.php` | Aceeași aplicație, alt cont: partenerul ei intră cu linkul `/pentru-el/?t=…` din email (App Link / universal link, căile `/pentru-el*`) și vede doar pagina lui `/pentru-el/` (Azi, Învață, notificările fazelor). Aplicația pornește tot din `/spatiul-meu/`: fără contul ei, cu cookie-ul lui, serverul o duce la `/pentru-el/`. `www.adelaharu.ro` e în `allowNavigation`, deci pagina rămâne în aplicație. Nimic de construit în `app/` pentru asta. |
| Linkuri externe | `server.allowNavigation` | Rămân în aplicație doar `www.adelaharu.ro` și `adelaharu.ro` (2.2.0: fără Vimeo și Apple Podcasts; iframe-urile Vimeo nu sunt navigări de nivel superior și se încarcă oricum, iar pe Android originile din listă primesc puntea, deci lista e și o barieră). Orice altă adresă de nivel superior (Spotify, Apple Podcasts, Zoom, WhatsApp, `mailto:`, `tel:`) o deschide **Capacitor singur** în aplicația/browserul sistemului — nu e nevoie de script: iOS `WebViewDelegationHandler.decidePolicyFor` → `UIApplication.shared.open`; Android `Bridge.launchIntent` → `Intent.ACTION_VIEW`. iframe-urile (Vimeo, YouTube) nu sunt afectate. |
| Pluginuri | `package.json` | App, PushNotifications, LocalNotifications, Preferences, Filesystem, FileTransfer (`@capacitor/file-transfer`), Browser, Device, SplashScreen, StatusBar + `AhApp` și `AhMedia` (ale noastre). Pagina site-ului le folosește prin `window.Capacitor.Plugins.*` (Capacitor injectează puntea pe originea `https://www.adelaharu.ro`). |
| Splash, bara de sus | `plugins.SplashScreen`, `plugins.StatusBar`, `SystemBars` | Fond `#FBF6F3` (Spațiul meu). Splash-ul rămâne până se vede pagina: puntea îl ascunde după prima desenare (`SplashScreen.hide()`), pagina offline la fel; dacă nu (pagina lui, o eroare), se ascunde singur după 4 s (`launchShowDuration`). Seara: `StatusBar.setStyle({style:'DARK'})` din punte. |
| Puntea pe iOS | `AhPuntePazita` în `AhAppPlugin.swift` | mesajele `bridge` (JS → pluginuri) trec doar din cadrul principal, de pe `https://www.adelaharu.ro` sau pagina offline (`capacitor://www.adelaharu.ro`); restul (iframe-uri, alte gazde) se ignoră. Pe Android, Capacitor face singur asta (`addWebMessageListener`, doar cadrul principal, doar originile din `allowNavigation`); pe un WebView foarte vechi, fără `WEB_MESSAGE_LISTENER`, cade pe `addJavascriptInterface`, fără filtru. |
| Butonul Înapoi (Android) | puntea (`App.addListener('backButton')`) și `src/offline/cap.ts` (`laInapoi`) | închide întâi foaia de deasupra / playerul, apoi înapoi în istoric (nu pe pagina offline), iar la început aplicația trece în fundal (`App.minimizeApp`). |
| Copia telefonului | `AndroidManifest.xml` (`allowBackup=false`, `data_extraction_rules.xml`, `backup_rules.xml`), `AppDelegate.faraBackup()` | Android: nimic în copia Google și la mutarea pe alt telefon. iPhone: descărcările, datele WebView și cookie-urile „fără backup”; `Preferences` (UserDefaults) rămân în copia telefonului (vezi `docs/release.md`). |
| Notificări, pictograma | `res/drawable/ic_stat_lotus.xml` | lotusul alb pentru push (meta-data `default_notification_icon` + `icon` în mesajul FCM) și pentru mementouri (`LocalNotifications.smallIcon`). Mementourile ciclului au text neutru și, pe Android, canalul `ah_ciclu` cu vizibilitate „privată”. |
| Descărcări pe Android 7–10 | `READ/WRITE_EXTERNAL_STORAGE` cu `maxSdkVersion="29"` | `@capacitor/file-transfer` le cere acolo chiar și pentru `Directory.DATA`. |
| Cereri HTTP | `CapacitorHttp` / `CapacitorCookies` **oprite** | Site-ul își face singur cererile cu cookie-ul `ah_sp`; interceptarea lor ar strica sesiunea. |

### Detalii Capacitor 8 verificate în surse (`node_modules/@capacitor/{ios,android}`)

- **iOS**: o navigare e „a aplicației” dacă începe cu `server.url` **întreg** (`https://www.adelaharu.ro/spatiul-meu/`); de aceea
  `www.adelaharu.ro` trebuie să fie în `allowNavigation`, altfel `/sala-de-curs/` s-ar deschide în Safari.
  Linkurile cu `target="_blank"` (și `window.open`) se deschid **mereu** în Safari, chiar spre adelaharu.ro
  (`createWebViewWith`): fragmentul trebuie să scoată `target=_blank` de pe linkurile interne, în aplicație.
- **Android**: puntea JS (`window.Capacitor`) se injectează doar pe originea din `server.url` (`addDocumentStartJavaScript`),
  nu și pe Vimeo/Apple, și atunci serverul local nu mai injectează nimic nici în fișierele locale. Adresa paginii offline
  e `scheme://server.hostname/offline.html` (`Bridge.getErrorUrl`); de aceea `server.hostname` e **`www.adelaharu.ro`**:
  pagina offline e `https://www.adelaharu.ro/offline.html`, servită local (`WebViewLocalServer.isErrorUrl`) și pe
  originea punții, deci are `window.Capacitor` (Preferences, Filesystem, AhMedia). Cu `localhost` (implicit) ar fi
  `https://localhost/offline.html`, fără punte: fără ciclu, fără descărcări. **Orice alt fișier** de la acea adresă
  ar fi cerut de la site — de aceea pagina offline e un singur HTML cu JS, CSS și fonturi incluse (vezi `vite.config.ts`).
  De verificat pe telefon: în pagina offline, `window.Capacitor.Plugins.Preferences` există (Chrome › `chrome://inspect`).
- iOS: pagina offline e `capacitor://www.adelaharu.ro/offline.html`; fișierele descărcate se citesc prin
  `capacitor://www.adelaharu.ro/_capacitor_file_/…` (`Capacitor.convertFileSrc`).
- Zonele sigure, un singur model (2.2.0): `ios.contentInset: 'never'`, iar fragmentul pune `viewport-fit=cover` în
  paginile din aplicație (pagina offline îl are deja); antetul și barele țin loc cu `env(safe-area-inset-*)` (sus/jos
  din 235, lateralele din punte). Pagina lui (`/pentru-el/`) folosește același model.

## Pagina offline (`offline.html`, `src/offline/`)

Fără framework, TypeScript + Vite, un singur fișier de ~180 KB (fonturile Gilda Display, Figtree, Cormorant
Garamond italic incluse, doar woff2, latin + latin-ext). CSP strict: doar scriptul ei (prin hash).

- `ciclu.ts` — **exact** regulile din `CI` (sn235): `CI.d` (curățarea datelor), `CI.lung`, `CI.faze`
  (`oS=max(L-16,m+2)`, `oE=min(oS+4,L-2)`), `CI.azi`, `CI.sit`, textele `CI.F` / `CI.SIT`. Funcții pure, „azi” ca parametru.
  Singura abatere voită: `situatie()` acceptă doar chei proprii (sn235 ar accepta și `sit:"toString"`).
- `descarcate.ts` — citește `ah_descarcate`.
- `player.ts` — un `<audio>`, Media Session (titlu, „Adela Haru”, poza, play/pauză/±15 s/seek/poziție) + `AhMedia` pe Android.
- `cap.ts` — acces protejat la `window.Capacitor.Plugins` (fără `@capacitor/core` în pachet).
- `main.ts` + `stil.css` — pagina: „Nu avem internet acum”, **Încearcă din nou** (verifică întâi cu un `fetch` no-cors,
  apoi `location.replace` spre Spațiul meu; reîncearcă singur la evenimentul `online`), Ciclul meu, meditațiile descărcate.
  Același aspect ca Spațiul meu (culori, fonturi, inelul ciclului), cu aspect de seară după tema telefonului.

Textele noi sunt marcate `CIORNĂ` în cod, pentru Adela. Textele fazelor și situațiilor sunt cele de pe site.

### Contractul cu puntea (`ah-sp-aplicatie.php`)

| Cheie Preferences | Scrisă de punte | Formă |
|---|---|---|
| `ah_ciclu` | la fiecare schimbare în Ciclul meu | JSON `{starturi:["YYYY-MM-DD",…], lung, men, sit, cine, actualizat}` (`cine` = amprenta contului: puntea reface din ea `localStorage.ahsp_ciclu` când iOS golește stocarea paginii; pagina offline o ignoră) (aceleași câmpuri ca `localStorage.ahsp_ciclu`; ideal rezultatul lui `CI.d()`, care unește și `ah_ciclu_v1`) |
| `ah_push` | la înregistrarea push (puntea ei sau pagina lui) | JSON `{token, cont:'ea'\|'el', cine?, la}`: tokenul FCM al telefonului și **al cui e** (un telefon = un cont; fără `cont` = al ei). Ieșirea fiecăruia scoate doar tokenul propriu; `unregister()` doar pe tokenul propriu. Pagina offline nu-l citește. |
| `ah_descarcate` | la descărcare / ștergere | JSON `[{id, titlu, durata, fisier, poza, descarcat, dir?}]`; `fisier`/`poza` = căi relative (`meditatii/12.mp3`) în `dir` (puntea scrie `LIBRARY_NO_CLOUD` pe iOS, fără copie în iCloud, și `DATA` pe Android; dacă lipsește, același folder; acceptă și `LIBRARY`, `CACHE`) sau `file://…`; `durata` în secunde (sau „12:30”) |

Pluginurile noastre (le folosește și puntea):

```js
const { AhApp, AhMedia } = window.Capacitor.Plugins;
await AhApp.info();   // {push, versiune, build, mediaNativ}; push=false ⇒ NU chema PushNotifications.register()
// doar Android (pe iOS e suficient navigator.mediaSession):
AhMedia.actualizeaza({titlu, artist:'Adela Haru', poza:'file://…|https://…', durata, pozitie, canta});
AhMedia.opreste();
AhMedia.addListener('actiune', ({tip, pozitie}) => …); // play | pause | inainte | inapoi | seek | stop
```

## Ecranul blocat și sunetul în fundal — decizia

- **iOS**: suficient WebKit. `UIBackgroundModes=audio` + `AVAudioSession` `.playback` (în `AppDelegate`) țin sunetul din
  `<audio>` pornit cu ecranul blocat; WKWebView (iOS 15+) leagă `navigator.mediaSession` de „Now Playing”
  (titlu, poză, play/pauză, ±15 s, bara de poziție). Fără plugin nativ. De verificat pe un iPhone real (vezi mai jos).
- **Android**: **nu** e suficient. WebView-ul Android nu leagă Media Session de sistem (nicio notificare media, nimic pe
  ecranul blocat) și nu are serviciu de prim-plan, așa că Android poate opri aplicația în timpul unei meditații lungi.
  De aceea pluginul minimal **`AhMedia`** (`android/app/src/main/java/ro/adelaharu/app/AhMedia*.java`): serviciu de prim-plan
  `mediaPlayback` + `MediaSessionCompat` + notificare `MediaStyle` (înapoi 15 s, play/pauză, înainte 15 s; pe Android 13+
  ca acțiuni personalizate), căștile/ceasul/mașina trimit și ele comenzile. Sunetul rămâne în WebView; pluginul doar
  arată starea și trimite apăsările înapoi. Pornește doar la o apăsare pe Play (regula Android 12+), iese din prim-plan
  la pauză, dispare la „stop” sau la închiderea aplicației din lista de aplicații.
  Îl folosesc **amândouă**: pagina offline (`player.ts`) și puntea de pe site pentru Inner Temple / sala de curs
  (`AM` în `ah-sp-aplicatie.php`, secțiunea 6: oglindește `<audio>` la play/pauză/seek/metadate + la 15 s, ascultă
  `actiune`, `opreste()` la `pagehide` și la final).
- **Pagina offline și revenirea internetului**: dacă ascultă o meditație descărcată, revenirea conexiunii **nu** o scoate din
  pagină; apare discret „Conexiunea a revenit. Deschide Spațiul meu” (CIORNĂ). La plecare (`deschideSite`, `pagehide`)
  se opresc sunetul și notificarea AhMedia.

## Push

Ghidat la rulare, fără variabile de build: `AhApp.info()` → `{push, token}`.
- **Android**: `push` = există `android/app/google-services.json` (pluginul Gradle generează resursa `google_app_id`);
  `token` = `'fcm'` (`@capacitor/push-notifications` dă mereu tokenul FCM). În manifest: `firebase_messaging_auto_init_enabled`
  = false (tokenul apare abia la `register()`, după ce ea permite) și `firebase_analytics_collection_deactivated` = true.
- **iOS** (varianta aleasă: Firebase Messaging în `AppDelegate`, nu `@capacitor-firebase/messaging`, care pornește
  Firebase singur și ar opri aplicația fără plist): proiectul are pachetul SPM `firebase-ios-sdk` (≥ 12.0, doar `FirebaseCore`
  + `FirebaseMessaging`, adăugat în `App.xcodeproj`, nu în `CapApp-SPM`, pe care îl rescrie `cap sync`). Faza de build
  „GoogleService-Info.plist (dacă există)” copiază `ios/App/App/GoogleService-Info.plist` (ignorat de git; îl scrie CI-ul
  din secret) în aplicație. `AppDelegate` pornește Firebase **doar** dacă fișierul există; la `didRegisterForRemoteNotifications`
  dă tokenul APNs lui Firebase Messaging și trimite la Capacitor tokenul **FCM** (String). Fără plist: aplicația merge normal,
  `push` = false, `token` = `'apns'`, puntea nu cere notificări. `Info.plist`: `FirebaseAppDelegateProxyEnabled` = NO (tokenul îl
  dăm noi), `FirebaseMessagingAutoInitEnabled` = NO (tokenul FCM doar după permisiune). Entitlements: `App.entitlements`
  (Release: `aps-environment` = `production`, plus Associated Domains) și `AppDebug.entitlements` (Debug: `development`).
  App ID-ul are nevoie de capabilitatea Push Notifications (`docs/release.md`, pasul 7).

La încărcarea în magazine, workflow-urile opresc o versiune care promite push fără Firebase: iOS cu `push_ios`
(`.github/scripts/verifica-push-ios.sh` cere fișierul plist valid și faza care îl copiază, nu doar numele în
`project.pbxproj`, unde apare mereu), Android cu `push_android` (`google-services.json` valid).

Puntea (`PUSH` în `ah-sp-aplicatie.php`) cere notificări doar cu `push: true` și `token: 'fcm'`, iar la `registration`
nu trimite serverului un token APNs brut (64 de cifre hexa); platforma trimisă e cea din Capacitor. Fără `AhApp` = fără push.

**Partenerul (contul pentru el):** pagina lui `/pentru-el/` (fragmentul `ah-sp-partener.php`) are scriptul ei, cu aceleași
reguli (`AhApp.info()` `push: true` + `token: 'fcm'`, fără APNs brut) și propriul endpoint (`/wp-json/ah-spp/v1/push`,
separat de al ei). Același telefon are un singur token FCM, deci el aparține unui singur cont: `Preferences.ah_push.cont`,
iar serverul îl mută dintr-un tabel în celălalt. Pe un telefon cu ea intrată, contul ei e cel activ (aplicația o deschide
pe ea), iar pagina lui nu oferă notificări. Atingerea unei notificări a lui (`data.url` = `/pentru-el/`, `data.tip` =
`pentru-el`) deschide Azi pe pagina lui; la pornirea rece, aplicația ajunge acolo prin `/spatiul-meu/` (302).

## Comenzi

```bash
npm install
npm test               # vitest: ciclul (inclusiv diferențial cu CI original din sn235), descărcările, blocurile paginii
npm run typecheck
npm run build          # dist/offline.html (+ index.html, aceeași pagină)
npm run ci:verifica    # sn235.php s-a schimbat față de fixtures/sn235-ci.js? (cere ../reference/v2/sn235.php)
npm run screenshots    # build + capturi Playwright 390×844 în ../docs/screenshots-v2/offline-*.png
npm run dev            # pagina offline în browser (fără Capacitor: arată stările „fără date”)
npm run sync           # build + cap sync (android și ios)
npm run icoane         # regenerează iconițele/splash (lotus provizoriu, paleta site-ului) în android/ și ios/
npx cap open android   # Android Studio
npx cap open ios       # Xcode (macOS)
```

Testul diferențial: `scripts/extrage-ci.mjs` copiază neschimbat `F, ORD, d, zi0, azi0, iso, data, lung, faze, SIT, sit, azi`
din `reference/v2/sn235.php` în `src/offline/fixtures/sn235-ci.js`; testul rulează originalul și portarea pe 4000 de stări
aleatoare (date stricate, dubluri, lungimi/menstruații în afara limitelor, situații) × 4 zile, la ore locale oarecare.
Dacă sn235 se schimbă pe site: copiați-l în `reference/v2/`, rulați `node scripts/extrage-ci.mjs`, apoi `npm test`.

## Cum rulez pe telefon, cu site-ul real

Aplicația încarcă mereu site-ul live (nimic nu se scrie pe el din `app/`). Fragmentul `ah-sp-aplicatie.php` trebuie să fie
activ pe site pentru ce ține de aplicație (ascunderea cumpărăturilor, butonul „Descarcă”, push, App Links); fără el,
aplicația arată Spațiul meu exact ca în browser.

**Android** (Android Studio, JDK 21):
1. `npm install && npm run sync:android && npx cap open android`
2. Telefon cu „Depanare USB” → Run. Inspectare (doar în build-urile debug, implicit Capacitor): `chrome://inspect` pe calculator.
3. Offline: mod avion → închideți și redeschideți aplicația ⇒ pagina offline. Porniți o meditație descărcată, blocați
   ecranul: trebuie să apară comenzile și să meargă ±15 s.
4. App Links: după ce `assetlinks.json` e pe site cu amprenta cheii, `adb shell pm verify-app-links --re-verify ro.adelaharu.app`
   și `adb shell am start -a android.intent.action.VIEW -d "https://www.adelaharu.ro/spatiul-meu/"` (la fel cu
   `https://www.adelaharu.ro/pentru-el/?t=<linkul din invitație>` pentru contul lui).

**iPhone** (macOS + Xcode 16+):
1. `npm install && npm run sync:ios && npx cap open ios`
2. Ținta App → Signing: echipa (Team ID ⇒ opțiunea pentru `apple-app-site-association` din fragment);
   capabilități: Associated Domains (deja în `App.entitlements`), Background Modes → Audio.
3. Run pe telefon. Inspectare: Safari → Develop → [telefon] (în debug, `isInspectable` e pornit de Capacitor).
4. Offline și ecranul blocat ca la Android (Control Center → Now Playing).

`npx cap sync ios` merge și pe Linux (proiectul folosește Swift Package Manager, fără CocoaPods); compilarea iOS cere macOS.

## Versiuni

`package.json` `version` = `versionName` (android/app/build.gradle) = `MARKETING_VERSION` (Xcode) = cea din User-Agent.
La o versiune nouă se schimbă toate trei, plus `versionCode` / `CURRENT_PROJECT_VERSION`.

## Nativ, pe scurt

- **iOS**: `Info.plist` — `UIBackgroundModes` doar `audio`, `CFBundleLocalizations` ro, texte de permisiune în română
  (cameră, galerie, microfon: pentru eventualele `<input type="file">` de pe site), `arm64`.
  `App.entitlements` (Release) / `AppDebug.entitlements` (Debug) — `applinks:www.adelaharu.ro`, `aps-environment` production / development. `PrivacyInfo.xcprivacy` — UserDefaults (CA92.1, Preferences),
  FileTimestamp (C617.1, Filesystem), date colectate legate de cont, doar pentru funcționare, fără urmărire:
  email, nume, sănătate (ciclul, doar dacă îl păstrează în cont), istoricul accesului/abonamentelor, interacțiunea
  (progres), DeviceID (tokenul push). `AhAppPlugin.swift` (+ `AhBridgeViewController`, folosit în `SceneDelegate`).
- **Android**: `AndroidManifest.xml` — App Links `autoVerify` pentru `/spatiul-meu*`, `/app/intra*` și `/pentru-el*` (aceleași căi ca AASA; testul `server/tests/integrare/run.php` le compară), `POST_NOTIFICATIONS`,
  `FOREGROUND_SERVICE` + `FOREGROUND_SERVICE_MEDIA_PLAYBACK`, serviciul `AhMediaService`, `allowBackup=false`.
  `androidx.media:media` pentru MediaSession. Iconița de notificare `ic_stat_lotus` (folosită și de LocalNotifications).
