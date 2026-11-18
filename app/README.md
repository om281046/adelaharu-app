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
| Fără internet | `server.errorPath: 'offline.html'` | Capacitor încarcă pagina locală când pagina principală nu se încarcă (iOS: `didFail`/`didFailProvisionalNavigation`; Android: `onReceivedError` **și** `onReceivedHttpError` pe cadrul principal, deci și la un 404/500 de pe site). |
| Linkuri externe | `server.allowNavigation` | Rămân în aplicație doar `www.adelaharu.ro`, `adelaharu.ro`, `player.vimeo.com`, `*.vimeocdn.com`, `embed.podcasts.apple.com`. Orice altă adresă de nivel superior (Spotify, Apple Podcasts, Zoom, WhatsApp, `mailto:`, `tel:`) o deschide **Capacitor singur** în aplicația/browserul sistemului — nu e nevoie de script: iOS `WebViewDelegationHandler.decidePolicyFor` → `UIApplication.shared.open`; Android `Bridge.launchIntent` → `Intent.ACTION_VIEW`. iframe-urile (Vimeo, YouTube) nu sunt afectate. |
| Pluginuri | `package.json` | App, PushNotifications, LocalNotifications, Preferences, Filesystem, FileTransfer (`@capacitor/file-transfer`), Browser, Device, SplashScreen, StatusBar + `AhApp` și `AhMedia` (ale noastre). Pagina site-ului le folosește prin `window.Capacitor.Plugins.*` (Capacitor injectează puntea pe originea `https://www.adelaharu.ro`). |
| Splash, bara de sus | `plugins.SplashScreen`, `plugins.StatusBar`, `SystemBars` | Fond `#FBF6F3` (Spațiul meu), splash ascuns singur după 1,5 s (puntea îl poate ascunde mai devreme cu `SplashScreen.hide()`); seara: `StatusBar.setStyle({style:'DARK'})` din punte. |
| Cereri HTTP | `CapacitorHttp` / `CapacitorCookies` **oprite** | Site-ul își face singur cererile cu cookie-ul `ah_sp`; interceptarea lor ar strica sesiunea. |

### Detalii Capacitor 8 verificate în surse (`node_modules/@capacitor/{ios,android}`)

- **iOS**: o navigare e „a aplicației” dacă începe cu `server.url` **întreg** (`https://www.adelaharu.ro/spatiul-meu/`); de aceea
  `www.adelaharu.ro` trebuie să fie în `allowNavigation`, altfel `/sala-de-curs/` s-ar deschide în Safari.
  Linkurile cu `target="_blank"` (și `window.open`) se deschid **mereu** în Safari, chiar spre adelaharu.ro
  (`createWebViewWith`): fragmentul trebuie să scoată `target=_blank` de pe linkurile interne, în aplicație.
- **Android**: puntea JS (`window.Capacitor`) se injectează doar pe originea din `server.url` (`addDocumentStartJavaScript`),
  nu și pe Vimeo/Apple. Pagina offline e servită local la `https://www.adelaharu.ro/offline.html`
  (`WebViewLocalServer.isErrorUrl`), dar **orice alt fișier** de la acea adresă ar fi cerut de la site — de aceea
  pagina offline e un singur HTML cu JS, CSS și fonturi incluse (vezi `vite.config.ts`).
- iOS: pagina offline e `capacitor://localhost/offline.html`; fișierele descărcate se citesc prin
  `capacitor://localhost/_capacitor_file_/…` (`Capacitor.convertFileSrc`).
- `contentInset: 'automatic'` pe iOS, pentru că Spațiul meu nu are `viewport-fit=cover`. Dacă fragmentul îl adaugă
  în aplicație (sn235 folosește deja `env(safe-area-inset-*)`), se trece pe `'never'`.

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
| `ah_ciclu` | la fiecare schimbare în Ciclul meu | JSON `{starturi:["YYYY-MM-DD",…], lung, men, sit, actualizat}` (aceleași câmpuri ca `localStorage.ahsp_ciclu`; ideal rezultatul lui `CI.d()`, care unește și `ah_ciclu_v1`) |
| `ah_descarcate` | la descărcare / ștergere | JSON `[{id, titlu, durata, fisier, poza, descarcat, dir?}]`; `fisier`/`poza` = căi relative (`meditatii/12.mp3`) în `dir` (implicit `LIBRARY`; acceptă `LIBRARY_NO_CLOUD`, `DATA`, `CACHE`) sau `file://…`; `durata` în secunde (sau „12:30”) |

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

## Push

Ghidat la rulare, fără variabile de build: `AhApp.info().push` e `true` doar dacă
- Android: există `android/app/google-services.json` (pluginul Gradle generează resursa `google_app_id`);
- iOS: aplicația e construită cu Firebase Messaging (pachet SPM `firebase-ios-sdk`, produsele `FirebaseCore` +
  `FirebaseMessaging` adăugate în ținta App) **și** există `GoogleService-Info.plist`. `AppDelegate` folosește
  `#if canImport(FirebaseMessaging)`: fără SDK se compilează ca acum; cu SDK trimite la Capacitor tokenul **FCM**
  (serverul trimite prin FCM HTTP v1). Tot atunci: capabilitatea Push Notifications + `aps-environment` în `App.entitlements`.

Fără Firebase, aplicația merge normal; puntea doar nu cere permisiunea de notificări.

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
   și `adb shell am start -a android.intent.action.VIEW -d "https://www.adelaharu.ro/spatiul-meu/"`.

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
  `App.entitlements` — `applinks:www.adelaharu.ro`. `PrivacyInfo.xcprivacy` — UserDefaults (CA92.1, Preferences),
  FileTimestamp (C617.1, Filesystem), date colectate legate de cont, doar pentru funcționare, fără urmărire:
  email, nume, sănătate (ciclul, doar dacă îl păstrează în cont), istoricul accesului/abonamentelor, interacțiunea
  (progres), DeviceID (tokenul push). `AhAppPlugin.swift` (+ `AhBridgeViewController`, folosit în `SceneDelegate`).
- **Android**: `AndroidManifest.xml` — App Links `autoVerify` pentru `/spatiul-meu*` și `/app/intra*`, `POST_NOTIFICATIONS`,
  `FOREGROUND_SERVICE` + `FOREGROUND_SERVICE_MEDIA_PLAYBACK`, serviciul `AhMediaService`, `allowBackup=false`.
  `androidx.media:media` pentru MediaSession. Iconița de notificare `ic_stat_lotus` (folosită și de LocalNotifications).
