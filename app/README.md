# Aplicația Adela Haru (Android + iPhone)

Vite + React + TypeScript + Capacitor 8. App id `ro.adelaharu.app`, nume „Adela Haru”.
Contractul cu serverul: `../docs/api-contract.md`. Calculul ciclului: `../docs/ciclu-algoritm.md`.

## Comenzi

```bash
npm install
npm run dev:mock        # în browser, cu date de probă (cod de intrare: 123456)
npm run dev             # în browser, cu API-ul real (VITE_API_BASE)
npm test                # vitest (ciclul, inclusiv testul diferențial cu codul original din sn41; linkuri)
npm run typecheck
npm run build           # dist/ pentru Capacitor (fără datele de probă)
npm run screenshots     # build demo + capturi Playwright în ../docs/screenshots/
npx cap sync            # după build, copiază în android/ și ios/
npx cap open android    # Android Studio
npx cap open ios        # Xcode (macOS)
```

Variabile (`.env`, vezi `.env.example`): `VITE_MOCK`, `VITE_API_BASE`, `VITE_PUSH`.

## Structura

- `src/api/` — `client.ts` (CapacitorHttp, erorile `{"eroare":{"cod","mesaj"}}` ⇒ `ApiError`), `types.ts`, `mock.ts` (modul demo).
- `src/ciclu/` — `algoritm.ts` (portare exactă din sn41, pură), `faze.ts` (textele fazelor din sn41, merg fără internet), `notificari-faze.ts`.
- `src/lib/` — `player.ts` (un singur `<audio>`, Media Session, reluare poziție), `offline.ts` (descărcări în `Directory.LibraryNoCloud`, fără backup iCloud, cu extensia reală .mp3/.m4a), `notificari.ts` (locale), `push.ts` (FCM, protejat), `sesiune.tsx`, `ciclu-local.ts`, `legaturi.ts` (universal/app links), `ruta.ts` (rute pe hash, butonul Înapoi).
- `src/screens/` — Intrare, Acasă, Inner Temple + Meditație (player), Cursurile mele + Curs + Lecție, Ciclul meu, Profil.

## Reguli de magazin (aplicație „reader”)

Aplicația nu vinde nimic: fără prețuri, fără butoane sau linkuri de cumpărare, fără link spre portalul Stripe. Meditațiile fără acces apar cu lacăt și textul neutru „Această meditație nu e inclusă în accesul tău.”, fără link și fără trimitere spre site. Ștergerea contului: Profil › „Șterge contul din aplicație” (`DELETE /cont`).

## Nativ

- **iOS** (`ios/`, Swift Package Manager, fără CocoaPods): `Info.plist` are `UIBackgroundModes` = `audio` (doar atât în v1); `App.entitlements` are `applinks:www.adelaharu.ro` (legat prin `CODE_SIGN_ENTITLEMENTS`). `PrivacyInfo.xcprivacy` (în ținta App) declară UserDefaults (CA92.1), datele fișierelor (C617.1) și datele colectate, toate legate de cont, pentru funcționarea aplicației, fără urmărire: emailul, datele ciclului (doar cu bifa ei), id-ul telefonului, poziția/minutele ascultate și progresul la cursuri. Disk space (E174.1) nu e declarat: `@capacitor/device` 8 nu citește spațiul liber. Etichetele de confidențialitate din App Store Connect trebuie completate la fel. `AppDelegate` pune sesiunea audio pe `.playback` și trimite tokenul APNs către Capacitor. În Xcode: alege echipa (Team ID ⇒ opțiunea `ah_app_ios_team` de pe server), activează capabilitățile Push Notifications și Associated Domains, Background Modes.
- **Android** (`android/`): `intent-filter android:autoVerify="true"` pentru `https://www.adelaharu.ro/app/*` (+ `/meditatiile-mele`, `/sala-de-curs`, `/cursurile-mele`), `POST_NOTIFICATIONS`, `allowBackup=false`. Amprenta SHA-256 a cheii de semnare (Play App Signing) ⇒ opțiunea `ah_app_android_sha256`.
- Iconițe și splash generate din `assets/` cu `npx @capacitor/assets generate` (lotus provizoriu, de înlocuit cu logo-ul real; `node scripts/icoane.cjs` le regenerează).

## Push

Oprit implicit. Pentru pornire: proiect Firebase, `google-services.json` în `android/app/`, `GoogleService-Info.plist` + SDK-ul FirebaseMessaging în iOS, apoi build cu `VITE_PUSH=1`. Tot atunci se adaugă la loc în iOS `remote-notification` în `UIBackgroundModes` (doar dacă folosim notificări silențioase) și `aps-environment` în `App.entitlements`, cu Push activat pe App ID. Fără `VITE_PUSH=1` aplicația nu cheamă `PushNotifications.register()` (pe Android, fără Firebase, aplicația s-ar închide).

## Securitate (v1)

Tokenul stă în `@capacitor/preferences` (UserDefaults / SharedPreferences, private aplicației; `allowBackup=false` pe Android). Pentru v1.1: Keychain/Keystore, de ex. `@aparajita/capacitor-secure-storage` (are versiune pentru Capacitor 8).
