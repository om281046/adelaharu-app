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
| Pluginuri | `package.json` | App, PushNotifications, LocalNotifications, Preferences, Filesystem, FileTransfer (`@capacitor/file-transfer`), Browser, Device, Network (`@capacitor/network`), SplashScreen, StatusBar + `AhApp` și `AhMedia` (ale noastre). Pagina site-ului le folosește prin `window.Capacitor.Plugins.*` (Capacitor injectează puntea pe originea `https://www.adelaharu.ro`). |
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
- `legatura.ts` (2.3.0) — de ce s-a deschis pagina și când se întoarce: `GET /wp-json/ah-spa/v1/config` cu CORS și `r.ok`
  cerut: eroare de rețea = **„Nu avem internet acum”**; un răspuns care nu e ok (5xx, 403/429 de la paravan) = **„Site-ul e în
  lucru, revino în câteva minute”**; doar CORS-ul căzut (o cerere no-cors trece) = rețeaua merge, se încearcă site-ul.
  Motivul de la deschidere: `?cod=503` (iOS, `AhNavigare`) sau `AhApp.info().eroare = {cod, la}` (Android, `MainActivity`;
  adresa paginii offline nu poate avea `?cod=` pe Android: `WebViewLocalServer.isErrorUrl` o compară exact). Fără bucle:
  plecarea spre site se ține în `sessionStorage`; o întoarcere în mai puțin de 30 s mărește pauza încercării automate
  (5 s, 15 s, 30 s, 1 min, 2 min, 5 min). Pe Android cu WebView sub 90 (`minWebViewVersion`): „Telefonul are nevoie de o
  actualizare” (Android System WebView / Chrome), fără încercări automate; sub ~80, unde scriptul nici nu rulează,
  rămâne textul static din `offline.html`.
- `main.ts` + `stil.css` — pagina: titlul după motiv, **Încearcă din nou** (prin `legatura.ts`, apoi `location.replace`
  spre Spațiul meu; reîncearcă singur după pauză și la evenimentul `online`), Ciclul meu, meditațiile descărcate.
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

## Setările aplicației — partea nativă

Pagina de setări e pe site (`ah-sp-aplicatie.php`); aici sunt doar metodele native pe care le cheamă, **detectate**
(`typeof AhApp.widget === 'function'`), deci o aplicație mai veche nu se strică. Toate răspund cu `resolve`, niciodată
`reject` pentru cazuri obișnuite (refuz, anulare, lipsă).

```js
const { AhApp } = window.Capacitor.Plugins;
await AhApp.widget({ mod: 'meditatie'|'faza'|'nimic', titlu, sub, culoare: '#964E46', imagine: 'https://…'|'', url });
//   → {ok, imagine}  ({ok:false, eroare:'grup'} pe iPhone dacă build-ul nu are App Group-ul în semnătură)
await AhApp.biometrie();               // → {disponibil, tip: 'faceid'|'touchid'|'amprenta'|'fata'|'cod'}
await AhApp.autentifica({ motiv });    // → {ok:true} | {ok:false, eroare:'anulat'|'blocat'|'indisponibil'|'eroare'}
await AhApp.insigna({ n });            // → {ok}   iOS: cifra pe pictogramă; Android: n=0 șterge notificările afișate
await AhApp.tema({ seara });           // → {ok}   textul barelor + fondul din spatele paginii
```

| Ce | iPhone | Android |
|---|---|---|
| **Widget** | Extensia WidgetKit `AdelaHaruWidget` (`ios/App/AdelaHaruWidget/`, ținta e în `project.pbxproj`, adăugată de mână și verificată cu pachetul Python `pbxproj`): mic, mediu, ecranul blocat (dreptunghiular, iOS 16+). Datele: `AhWidgetDate.swift` (compilat în ambele ținte) în App Group `group.ro.adelaharu.app` (UserDefaults + poza în containerul grupului). | `AhWidget` (AppWidgetProvider, `RemoteViews`): 2×2, redimensionabil la 4×2 (Android 12+: aspectul după mărime, `RemoteViews(Map<SizeF,…>)`; înainte, după lățimea din opțiuni). Datele: `AhWidgetDate.java` în SharedPreferences `ah_widget`, poza în `files/widget/imagine.jpg`. Lista de widgeturi: „Meditația zilei sau faza ciclului”, previzualizarea `drawable-nodpi/ah_widget_previzualizare.png` (lotusul). |
| Poza | descărcată de aplicație (doar `https`, `image/*`, ≤ 3 MB, oprită de cum trece), micșorată la 600 px (ImageIO), JPEG; widgetul doar o citește | la fel (`HttpURLConnection`, `inSampleSize`, ≤ 600 px) |
| Atingerea | `widgetURL` → `scene(_:openURLContexts:)` → `appUrlOpen` | `ACTION_VIEW` explicit spre `MainActivity` (singleTask) → `appUrlOpen` / `getLaunchUrl` |
| Reguli comune | `mod` necunoscut = `nimic`; `url` doar `https://www.adelaharu.ro` / `adelaharu.ro` (altfel `/spatiul-meu/`); `culoare` doar `#RGB`/`#RRGGBB`; titlu ≤ 80, sub ≤ 140; după **36 h** fără o nouă scriere widgetul trece singur pe starea neutră (o fază de alaltăieri ar fi greșită). **Faza**: doar numele fazei și textul dat, fără poză (poza veche se șterge); pe iPhone cu `.privacySensitive()` (ascunsă pe ecranul blocat cu telefonul blocat și în StandBy). **Nimic**: doar lotusul și „Adela Haru”. | aceleași (testate în `android/app/src/test/…/AhWidgetDateTest.java`) |
| Aspect | crem `#FBF6F3`, titlul serif (Gilda Display nu e inclus ca TTF în aplicație ⇒ serif-ul sistemului: New York / `serif`), lotusul în culoarea dată, „Adela Haru” mic | la fel |
| **Blocarea** | `LocalAuthentication`, `.deviceOwnerAuthentication` (Face ID / Touch ID, apoi codul telefonului); `NSFaceIDUsageDescription` în `Info.plist` | `androidx.biometric` 1.1.0, `BiometricPrompt` cu `BIOMETRIC_WEAK \| DEVICE_CREDENTIAL` (WEAK include STRONG; combinația merge pe toate versiunile, API 24+); `MainActivity` e `AppCompatActivity` ⇒ `FragmentActivity` |
| **Insigna** | `setBadgeCount` (iOS 16+) / `applicationIconBadgeNumber`, doar cu notificările permise și insigna pornită | lansatorul o ia din notificări; `n = 0` șterge notificările afișate, fără cea a meditației (în curs) |
| **Tema** | `bridge.statusBarStyle` (text deschis seara) + fondul controlerului și al WebView-ului (`#FBF6F3` / `#221922`) | `WindowInsetsControllerCompat` (text deschis seara, bara de sus și cea de jos); culoarea barelor doar sub Android 15 (edge-to-edge); fondul ferestrei și al WebView-ului. Poate înlocui `StatusBar.setStyle` din punte. |

### Sunetele notificărilor

Clopoțelul e **sintetizat** de `scripts/clopotel.py` (numpy; un bol simplificat: 528 Hz + 1320 Hz, cu „bătăi” lente,
atac 12 ms, stingere exponențială, 2,5 s, −3 dBFS, mono 44,1 kHz, 16 biți), fără nicio înregistrare străină:
- Android: `android/app/src/main/res/raw/clopotel.wav` (≈ 215 KB). Canalele le face puntea din JS:
  `LocalNotifications.createChannel({ id: 'ah_clopotel', sound: 'clopotel.wav', … })` (Capacitor caută `res/raw/clopotel`).
  Atenție: pe Android sunetul unui canal **nu se mai poate schimba** după creare; pentru alt sunet, alt `id` de canal.
- iOS: `ios/App/App/clopotel.caf` (PCM 16 biți, 2,5 s; în resursele țintei App): `sound: 'clopotel.caf'` la
  `LocalNotifications.schedule`, iar în push-ul APNs (prin FCM) `apns.payload.aps.sound = "clopotel.caf"`.
- Push pe Android: mesajul FCM poartă `android.notification.channel_id`. Fără el, `AndroidManifest.xml` trimite la
  `ah_clopotel` (`default_notification_channel_id`); dacă acel canal încă nu există (aplicația abia instalată, puntea nu
  l-a creat), Firebase folosește singur canalul lui de rezervă, deci nu se pierde nimic.

**Alt sunet** (de ex. un clopoțel înregistrat de Adela): un fișier WAV mono, sub 5 s, liniștit la capăt, pus peste
`clopotel.wav`; pentru iPhone `afconvert -f caff -d LEI16 clopotel.wav ios/App/App/clopotel.caf` (macOS) sau
`ffmpeg -i clopotel.wav -c:a pcm_s16le -f caf ios/App/App/clopotel.caf`. Sau se schimbă `PARTIALI` / `DURATA` în
`scripts/clopotel.py` și se rulează `python3 scripts/clopotel.py`. Limite: Android < 300 KB, iOS < 30 s. O versiune nouă
a aplicației e necesară (sunetele sunt în pachet). Pe Android, schimbarea sunetului cere și un `id` nou de canal (puntea).

### Video pe tot ecranul (Vimeo)

- **Android**: `BridgeWebChromeClient` din Capacitor 8 **refuza** ecranul complet (`onShowCustomView` cheamă imediat
  `onCustomViewHidden()`). `MainActivity.AhWebChromeClient` (extinde clasa lui Capacitor, deci fișierele și permisiunile
  rămân) pune vederea videoului peste tot ecranul, ascunde barele (revin la glisare), ține ecranul aprins
  (`FLAG_KEEP_SCREEN_ON`) și trece pe peisaj după senzor (`SCREEN_ORIENTATION_SENSOR_LANDSCAPE`, și cu rotirea blocată),
  apoi pune totul la loc. Înapoi închide întâi ecranul complet (callback prioritar față de cel al pluginului App).
  Aplicația în sine nu e blocată pe vertical (manifestul nu are `screenOrientation`), ca până acum.
- **iPhone**: Capacitor pune deja `allowsInlineMediaPlayback`, AirPlay, redarea fără atingere și
  `isElementFullscreenEnabled`; `AhBridgeViewController.webViewConfiguration` adaugă explicit Picture in Picture.
  Ecranul aplicației rămâne **vertical** (`supportedInterfaceOrientations = .portrait` pe iPhone), dar `Info.plist`
  păstrează peisajul: playerul pe tot ecranul al WebKit e un controler separat și se rotește singur. Sunetul continuă
  cu ecranul blocat (`UIBackgroundModes = audio`, verificat, existent). AirPlay: din playerul Vimeo / ecranul complet.
  **Chromecast nu există** într-un WebView (nici pe Android); doar prin aplicația Vimeo.

### Widgetul pe iPhone: ce trebuie făcut o dată (proprietara)

1. **Apple Developer → Identifiers → App Groups → „+”**: `group.ro.adelaharu.app` (descriere „Adela Haru”).
2. **Identifiers → `ro.adelaharu.app`** → bifați **App Groups** → Configure → alegeți `group.ro.adelaharu.app` → Save
   (Apple invalidează profilul vechi).
3. **Identifiers → „+” → App IDs → App**: Bundle ID explicit **`ro.adelaharu.app.widget`**, descriere „Adela Haru
   Widget”, capabilitatea **App Groups** cu același grup. (Fără Push, fără Associated Domains: widgetul nu le folosește.)
4. GitHub → Actions → **iOS** → Run workflow cu **„creează certificate”** bifat o dată: `fastlane match` face profilul
   App Store și pentru `ro.adelaharu.app.widget` și îl reface pe cel al aplicației (cu App Groups). Fastfile semnează
   acum ambele ținte (`App` și `AdelaHaruWidget`) și le dă ambele profiluri la export. Match **nu** creează App ID-uri:
   pașii 1–3 trebuie făcuți înainte, altfel rularea se oprește la `sigh_ro.adelaharu.app.widget_appstore_profile-name`.
5. Local (Xcode, semnare automată): ținta **AdelaHaruWidget** → Signing & Capabilities → aceeași echipă; App Groups
   apare din `AdelaHaruWidget.entitlements`. Același lucru la ținta **App**.

Pe Android nu e nimic de configurat (receiverul e în manifest).

### De încercat pe telefoane reale (nu se pot verifica aici: nu există Xcode / Android SDK)

1. **Widget**: deschideți aplicația (puntea cheamă `AhApp.widget`), apoi adăugați widgetul (iPhone: apăsare lungă pe
   ecranul principal → „+” → Adela Haru; Android: lista de widgeturi). Verificați mic / mediu (cu poza) / ecranul blocat
   (iPhone), modul „faza” (fără poză; pe iPhone, ascuns pe ecranul blocat cât telefonul e blocat), „nimic” (doar lotusul),
   atingerea (se deschide `/spatiul-meu/#…` din aplicație, și cu aplicația închisă), redimensionarea pe Android, trecerea
   pe starea neutră după 36 h (ora telefonului mutată înainte). Din Safari/Chrome inspector:
   `await Capacitor.Plugins.AhApp.widget({mod:'meditatie',titlu:'Test',sub:'Text',culoare:'#5B7F6A',imagine:'https://www.adelaharu.ro/wp-content/uploads/…jpg',url:'https://www.adelaharu.ro/spatiul-meu/#inner-temple'})`.
2. **Blocarea**: `AhApp.biometrie()` pe un telefon cu Face ID / Touch ID / amprentă / doar cod / fără cod;
   `AhApp.autentifica({motiv:'Deblochează Ciclul meu'})`: reușit, anulat, greșit de 5 ori (iPhone cere codul), fără
   biometrie înrolată (cere codul). Prima dată iPhone arată textul `NSFaceIDUsageDescription`.
3. **Insigna**: iPhone `AhApp.insigna({n:3})` cu notificările permise (cifra 3), apoi `{n:0}`; fără permisiune → `ok:false`.
   Android: cu o meditație care cântă și o notificare de fază afișată, `{n:0}` șterge doar notificarea fazei.
4. **Tema**: `AhApp.tema({seara:true})` → textul barei albă pe fond închis; `{seara:false}` → înapoi.
5. **Sunetele**: o notificare locală pe canalul `ah_clopotel` (Android) / cu `sound:'clopotel.caf'` (iPhone) sună
   clopoțelul; un push FCM fără `channel_id` cade pe `ah_clopotel` (sau pe canalul de rezervă, dacă nu există încă).
6. **Video**: o lecție Vimeo din sala de curs → butonul ecran complet: Android trece pe peisaj, fără bare, ecranul nu se
   stinge; Înapoi iese din ecranul complet (nu din pagină); la ieșire, aplicația revine cum era. iPhone: ecranul complet
   se rotește, aplicația rămâne verticală după ieșire; Picture in Picture (glisare spre ecranul principal în timpul
   redării); AirPlay spre un Apple TV.
7. **Network**: `Capacitor.Plugins.Network.getStatus()` și evenimentul `networkStatusChange` (mod avion).

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
  (cameră, galerie, microfon: pentru eventualele `<input type="file">` de pe site; Face ID: Ciclul meu blocat), `arm64`.
  Ținte: `App` și extensia `AdelaHaruWidget` (`ro.adelaharu.app.widget`, încorporată prin „Embed Foundation
  Extensions”); ambele au App Group-ul `group.ro.adelaharu.app` în entitlements. Resurse în plus: `clopotel.caf`.
  `App.entitlements` (Release) / `AppDebug.entitlements` (Debug) — `applinks:www.adelaharu.ro`, `aps-environment` production / development. `PrivacyInfo.xcprivacy` — UserDefaults (CA92.1, Preferences),
  FileTimestamp (C617.1, Filesystem), date colectate legate de cont, doar pentru funcționare, fără urmărire:
  email, nume, sănătate (ciclul, doar dacă îl păstrează în cont), istoricul accesului/abonamentelor, interacțiunea
  (progres), DeviceID (tokenul push). `AhAppPlugin.swift` (+ `AhBridgeViewController`, folosit în `SceneDelegate`).
  `AhNavigare` (2.3.0) implementează și `decidePolicyFor navigationResponse`: un răspuns ≥ 500 al cadrului principal de pe
  site se anulează și se deschide `offline.html?cod=<cod>` (serverul local ia doar calea); anularea dă WebKit 102, deja
  filtrat. Capacitor 8 nu are metoda (la o actualizare: `grep navigationResponse` în `WebViewDelegationHandler.swift`).
  Codul și momentul stau și în `AhNavigare.ultimaEroare`, citite prin `AhApp.info().eroare`.
- **Android**: `AhWidget` (receiver neexportat: actualizările vin de la sistem), `androidx.biometric`, `res/raw/clopotel.wav`,
  `MainActivity.AhWebChromeClient` (video pe tot ecranul). `AndroidManifest.xml` — App Links `autoVerify` pentru `/spatiul-meu*`, `/app/intra*` și `/pentru-el*` (aceleași căi ca AASA; testul `server/tests/integrare/run.php` le compară), `POST_NOTIFICATIONS`,
  `FOREGROUND_SERVICE` + `FOREGROUND_SERVICE_MEDIA_PLAYBACK`, serviciul `AhMediaService`, `allowBackup=false`.
  `androidx.media:media` pentru MediaSession. Iconița de notificare `ic_stat_lotus` (folosită și de LocalNotifications).
  `MainActivity.AhWebViewClient` (2.3.0, pus cu `bridge.setWebViewClient` după `super.onCreate`, extinde
  `BridgeWebViewClient`): ține minte eroarea HTTP a cadrului principal (`AhAppPlugin.noteazaEroare`, apoi Capacitor
  deschide pagina offline ca înainte, la orice ≥ 400) și tratează `onRenderProcessGone` (întoarce `true`, scoate WebView-ul
  și reface activitatea; a treia oprire într-un minut închide ecranul, fără buclă). Fără tratare, Android ar închide toată
  aplicația. `capacitor.config.ts`: `android.minWebViewVersion: 90` (= ținta `chrome90` din `vite.config.ts`); sub ea,
  `Bridge.isMinimumWebViewInstalled()` e fals și Capacitor încarcă direct `errorPath` (pagina offline), care cere
  actualizarea WebView-ului. Implicit, Capacitor 8 cere doar 55 (`Bridge.MINIMUM_ANDROID_WEBVIEW_VERSION`).
