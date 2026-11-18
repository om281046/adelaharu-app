# Arhitectura v2: aplicația = Spațiul meu + funcții de telefon

Decizie (Mircea, 18 nov. 2026). Înlocuiește `docs/api-contract.md` (v1, păstrat în istoric git).
Orice schimbare a regulilor de mai jos se face întâi aici.

## Principiu

Aplicația (Capacitor, `ro.adelaharu.app`, „Adela Haru”) deschide **Spațiul meu** de pe site
(`https://www.adelaharu.ro/spatiul-meu/`, fragmentul 235 + extensiile 266, 281, 265, 295) și adaugă
doar ce site-ul nu poate face. Conținutul (cursuri, Inner Temple, cărți, ciclu, abonamente, podcast,
întâlniri) vine de pe site: ce adaugă echipa acolo apare singur în aplicație, fără versiune nouă.

Secțiunile din aplicație: Acasă, Ciclul meu, Cursurile mele, Inner Temple, Cărți, Abonamente (doar starea),
Podcast, Mai mult. **Fără Comenzi.** Aplicația NU vinde nimic și nu trimite spre cumpărare (Apple 3.1.1 /
3.1.3(a), Google Payments policy): fără prețuri, coș, plată, portal Stripe, linkuri spre produse sau
pagini de vânzare, card cadou, Recomandă, campanii (Black Friday), sugestii de produse.

Fragmentele existente ale site-ului NU se modifică. Tot ce ține de aplicație stă într-un fragment nou,
`server/ah-sp-aplicatie.php` („Spațiul meu – modul aplicație”), care se oprește prin dezactivare, ca 266.
Nu se urcă nimic pe site fără OK-ul lui Mircea (se urcă inactiv, cu copie de siguranță).

## Cum se recunoaște aplicația

- Capacitor adaugă la User-Agent: `AdelaHaruApp/<versiune> (ios|android)` (`appendUserAgent` în
  `capacitor.config.ts`, setat de aplicație).
- Fragmentul: `ah_spa_e_aplicatie()` = UA conține `AdelaHaruApp/` SAU cookie `ah_app` (`ios|android`), pus de
  fragment la prima cerere cu UA (1 an, Secure, Lax). Platforma: `ah_spa_platforma()`.
- Tot ce face fragmentul pe pagini se aplică DOAR când `ah_spa_e_aplicatie()`; în browser nu se schimbă nimic.

## Shell-ul (app/)

- `server.url` = `https://www.adelaharu.ro/spatiul-meu/`, `server.errorPath` = pagina locală `offline.html`
  (deschisă când site-ul nu răspunde). `allowNavigation`: `www.adelaharu.ro`, `adelaharu.ro`,
  `player.vimeo.com`, `*.vimeocdn.com`, `embed.podcasts.apple.com`, `js.stripe.com` NU (nu e nevoie).
  Orice altă adresă se deschide în browserul sistemului (Spotify, Apple Podcasts, Zoom, WhatsApp, email).
- Pluginuri (accesibile din pagina site-ului prin `window.Capacitor.Plugins.*`): `App`, `PushNotifications`,
  `LocalNotifications`, `Preferences`, `Filesystem`, `FileTransfer` (`@capacitor/file-transfer`), `Browser`,
  `Device`, `SplashScreen`, `StatusBar`, `Haptics` (opțional). Plus un plugin nativ mic, `AhMedia`, DOAR dacă e
  nevoie pentru comenzile de pe ecranul blocat (altfel Media Session din WebView + `UIBackgroundModes audio`).
- Info.plist: `UIBackgroundModes` = `audio` (+ `remote-notification` doar dacă trimitem notificări silențioase:
  NU în v1), Associated Domains `applinks:www.adelaharu.ro`, `PrivacyInfo.xcprivacy`.
  AndroidManifest: App Links `autoVerify` pentru `/spatiul-meu` (cu `?cont=` din emailuri) și `/app/intra`,
  `POST_NOTIFICATIONS`, `FOREGROUND_SERVICE_MEDIA_PLAYBACK` dacă e cazul.

## Puntea (în fragment, JavaScript tipărit doar în aplicație pe /spatiul-meu/, /sala-de-curs/, /cartile-mele/, /meditatiile-mele/)

`window.AHApp` (namespace unic), cu:

1. **Linkuri:** `App.addListener('appUrlOpen')` → dacă e `https://www.adelaharu.ro/...` permis, `location.href = url`
   (așa linkul personal `?cont=` din orice email deschide aplicația deja logată).
2. **Notificări push:** după intrarea în cont, cere permisiunea O SINGURĂ dată, la un moment potrivit (nu la prima
   deschidere), cu un ecran de explicație; tokenul FCM → `POST /wp-json/ah-spa/v1/push` (legat de emailul din
   cookie-ul `ah_sp`). La ieșire din cont → `DELETE`. Atingerea unei notificări deschide `data.url`.
3. **Ciclul:** citește `localStorage.ahsp_ciclu` (`{starturi:[YYYY-MM-DD…], lung, men, sit}`), calculează fazele cu
   EXACT aceeași regulă ca Spațiul meu (`CI.faze` / `CI.lung` din 235: `oS=max(L-16,m+2)`, `oE=min(oS+4,L-2)`), și:
   - programează notificări LOCALE la începutul fiecărei faze, pentru următoarele 3 cicluri (anulează și reprogramează
     la fiecare schimbare; nimic dacă `sit` e `hormonal` sau `sarcina`; respectă comutatorul din „Mai mult”);
   - scrie în `Preferences` cheia `ah_ciclu` = `{starturi, lung, men, sit, actualizat}` pentru pagina offline.
4. **Meditații offline:** buton „Descarcă” lângă fiecare meditație Inner Temple pe care o poate asculta →
   `GET /wp-json/ah-spa/v1/descarca?id=<id>` → `{url, expira, titlu, durata, imagine}` (URL semnat, 15 min) →
   `FileTransfer.downloadFile` în `Directory.Library` (iOS: exclus din backup; Android: Data), calea
   `meditatii/<id>.<ext>` + poza în `meditatii/<id>.jpg`; indexul în `Preferences` `ah_descarcate` =
   `[{id,titlu,durata,fisier,poza,descarcat}]`. „Șterge descărcarea” scoate fișierul și rândul. La ieșirea din cont
   se șterg toate.
5. **Ecran blocat:** pe redarea audio din Inner Temple setează `navigator.mediaSession` (titlu, „Adela Haru”, poza,
   play/pause/înainte 15 s/înapoi 15 s).
6. **Ștergerea contului** (cerință Apple 5.1.1(v) și Google): în „Mai mult” › Aplicația, „Șterge contul” cu confirmare
   → `POST /wp-json/ah-spa/v1/sterge-cont` → șterge datele ciclului (tabelul `ah_sp_ciclu`, ca `ciclu-sterge`),
   intrarea din `ah_sp_conturi`, tokenurile push, dispozitivele, cookie-urile; comenzile și facturile rămân (obligație
   legală, spus clar în text); trimite echipei un email. Apoi aplicația șterge descărcările și `Preferences`.

## Ce ascunde fragmentul în aplicație

- Server: în aplicație, orice pagină în afara listei permise (`/spatiul-meu/`, `/sala-de-curs/`, `/cartile-mele/`,
  `/meditatiile-mele/`, `/app/*`, politica de confidențialitate, termenii, politica de cookie-uri, `/wp-json/`,
  fișierele media) → redirect 302 la `/spatiul-meu/`. Fără bara de cookie-uri în aplicație dacă se poate legal
  (doar cookie-uri funcționale; altfel păstrată).
- Pagini: CSS + JS care urmăresc desenările (ca 266): fila Comenzi și rândurile ei, butoanele/linkurile de plată și
  „Abonamentul meu” (portal Stripe), „Reînnoiește”, prețuri, linkurile `mai` din podcast, produsele din Ciclul meu
  (`D.ciclu.produse`), cursurile închise/expirate care trimit la cumpărare, card cadou, Recomandă, Black Friday,
  Women Empower Women, sugestiile de sub înregistrări (279), butoanele din sala de curs (233), cumpărarea de cărți.
  Ce rămâne: starea abonamentului („Activ până pe …”), fără nicio acțiune.
- Unde e mai sigur la sursă (filtre PHP existente, ex. datele `D` din `ah_sp_date`), se curăță la sursă.

## Pe server, fragmentul nou mai face

- `ah-spa/v1`: `POST/DELETE /push`, `GET /descarca`, `POST /sterge-cont`, `GET /config` (versiunea minimă, mesaj).
  Autentificare = cookie-ul `ah_sp` existent (`ah_sp_email()`), nimic nou. `Cache-Control: no-store`, LiteSpeed nocache.
- Trimiterea push: FCM HTTP v1 (cont de serviciu în opțiunea `ah_spa_fcm`; până atunci doar jurnal). Declanșatori:
  meditație nouă publicată (celor cu acces), înregistrare „Respiră-te acasă” publicată (celor înscrise în ziua
  aceea), întâlnire care începe în 15 minute (celor înscrise). În loturi, prin cron, fără să blocheze cererea.
- `/.well-known/apple-app-site-association` și `/.well-known/assetlinks.json` din opțiuni (Team ID, SHA-256).
- `/app/intra`: pagina pentru cine nu are aplicația (linkurile spre magazine).

## Pagina offline (app/, locală)

Se deschide când nu e internet. Arată: faza de azi din `Preferences.ah_ciclu` (același calcul), meditațiile
descărcate cu player (Media Session), butonul „Încearcă din nou”. Fără cont, fără rețea.

## Ce a rămas din v1

Păstrăm: proiectul Capacitor și folderele native, construirea automată (`.github/`, `fastlane/`), documentele pentru
magazine (de actualizat), ghidul `docs/release.md`. Renunțăm: ecranele React și `server/ah-app-api.php` (logarea
proprie, `/meditatii`, `/cursuri`, `/ciclu` etc.); din el se refolosesc trimiterea FCM, `.well-known` și testele utile.
