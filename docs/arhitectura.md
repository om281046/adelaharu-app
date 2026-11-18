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
  AndroidManifest: App Links `autoVerify` pentru `/spatiul-meu` (cu `?cont=` din emailuri) și `/app/intra`
  (exact + subcăile cu „/”; aceleași căi în AASA: `/spatiul-meu`, `/spatiul-meu/*`, `/app/intra`, `/app/intra/*`),
  `POST_NOTIFICATIONS`, `FOREGROUND_SERVICE_MEDIA_PLAYBACK` dacă e cazul.

## Puntea (în fragment, JavaScript tipărit doar în aplicație pe /spatiul-meu/, /sala-de-curs/, /cartile-mele/, /meditatiile-mele/)

`window.AHApp` (namespace unic), cu:

1. **Linkuri:** `App.addListener('appUrlOpen')` → dacă e `https://www.adelaharu.ro/...` permis, `location.href = url`
   (așa linkul personal `?cont=` din orice email deschide aplicația deja logată).
2. **Notificări push:** după intrarea în cont, cere permisiunea O SINGURĂ dată, la un moment potrivit (nu la prima
   deschidere), cu un ecran de explicație; tokenul FCM → `POST /wp-json/ah-spa/v1/push` (legat de emailul din
   cookie-ul `ah_sp`). La ieșire din cont → `DELETE`. Atingerea unei notificări deschide `data.url`.
   Doar dacă `AhApp.info().push` e `true` (build cu Firebase; pe iOS și cu `aps-environment`); altfel puntea nu
   întreabă și nu arată comutatorul „Notificări”, iar notele de recenzie și fișa magazinului nu promit push.
3. **Ciclul:** citește `localStorage.ahsp_ciclu` (`{starturi:[YYYY-MM-DD…], lung, men, sit}`), calculează fazele cu
   EXACT aceeași regulă ca Spațiul meu (`CI.faze` / `CI.lung` din 235: `oS=max(L-16,m+2)`, `oE=min(oS+4,L-2)`), și:
   - programează notificări LOCALE la începutul fiecărei faze, pentru următoarele 3 cicluri (anulează și reprogramează
     la fiecare schimbare; nimic dacă `sit` e `hormonal` sau `sarcina`; respectă comutatorul din „Mai mult”);
   - scrie în `Preferences` cheia `ah_ciclu` = `{starturi, lung, men, sit, actualizat}` pentru pagina offline;
   - **refacere:** dacă `localStorage.ahsp_ciclu` și `ah_ciclu_v1` lipsesc (iOS poate goli stocarea paginii), dar
     `Preferences.ah_ciclu` există și contul e același (`S.cont`), îl scrie înapoi în `localStorage.ahsp_ciclu` și
     păstrează mementourile. `ah_ciclu` și mementourile se șterg DOAR la acțiuni explicite: „Șterge datele de pe
     acest telefon” din Ciclul meu (`removeItem('ahsp_ciclu')` când cheia exista), ieșirea din cont, „Șterge contul”.
     Cu test.
4. **Meditații offline:** buton „Descarcă” lângă fiecare meditație Inner Temple pe care o poate asculta →
   `GET /wp-json/ah-spa/v1/descarca?id=<id>` → `{url, expira, titlu, durata, imagine}` (URL semnat, 15 min) →
   `FileTransfer.downloadFile` în `Directory.LibraryNoCloud` pe iOS (`LIBRARY_NO_CLOUD`: fără copie în iCloud;
   `Directory.Library` simplu AR intra în backup) și `Directory.Data` pe Android (`DATA`), calea
   `meditatii/<id>.<ext>` + poza în `meditatii/<id>.jpg`; indexul în `Preferences` `ah_descarcate` =
   `[{id,titlu,durata,fisier,poza,dir,descarcat}]`, cu folderul în `dir` pe fiecare rând (pagina offline și
   ștergerea îl citesc de acolo). De confirmat pe un iPhone: fișierele din `LIBRARY_NO_CLOUD` au
   `NSURLIsExcludedFromBackupKey` (IONFilesystemLib `.notSyncedLibrary`). „Șterge descărcarea” scoate fișierul și rândul. La ieșirea din cont
   se șterg toate.
5. **Ecran blocat:** pe redarea audio din Inner Temple setează `navigator.mediaSession` (titlu, „Adela Haru”, poza,
   play/pause/înainte 15 s/înapoi 15 s). Pe Android (WebView-ul nu leagă Media Session de sistem și nu are serviciu de
   prim-plan) oglindește în plus starea în pluginul `AhMedia` și ascultă comenzile lui (`actiune`); `opreste()` la ieșire.
6. **Ștergerea contului** (cerință Apple 5.1.1(v) și Google): în „Mai mult” › Aplicația, „Șterge contul” cu confirmare
   → `POST /wp-json/ah-spa/v1/sterge-cont` → șterge datele ciclului (tabelul `ah_sp_ciclu`, ca `ciclu-sterge`),
   abonarea la emailurile fazelor (tabelul `ah_ciclu` din 41), intrarea din `ah_sp_conturi`, tokenurile push,
   dispozitivele, cookie-urile; comenzile și facturile rămân (obligație legală, spus clar în text); trimite echipei un
   email. Apoi aplicația șterge descărcările și `Preferences`. Pentru că accesul vine din comenzi, o nouă intrare cu
   același email arată din nou conținutul cumpărat, fără datele șterse (spus în notele de recenzie).

## Ce ascunde fragmentul în aplicație

- Server: în aplicație, orice pagină în afara listei permise (`/spatiul-meu/`, `/sala-de-curs/`, `/cartile-mele/`,
  `/meditatiile-mele/`, `/app/*`, politica de confidențialitate, termenii, politica de cookie-uri, `/wp-json/`,
  fișierele media) → redirect 302 la `/spatiul-meu/`.
- **Fără analiză și urmărire în aplicație (cerință fermă; de ea depind etichetele de confidențialitate și notele de
  recenzie):** în aplicație nu se încarcă Google Analytics (`gtag` / Site Kit / GTM), pixelul Meta
  (`facebook-for-woocommerce`), atribuirea comenzilor WooCommerce (`sourcebuster`, `wc-order-attribution`) și bara de
  cookie-uri Complianz (în aplicație se folosesc doar cookie-uri funcționale: `ah_sp`, `ah_app`, `ah_med`, ale sălii,
  deci bara nu e necesară). Se scot la sursă (dequeue pe `wp_enqueue_scripts` / `wp_print_scripts`, filtrele
  Complianz, ieșirea pixelului), doar când `ah_spa_e_aplicatie()`, cu test; fără `gtag`, evenimentele `ev()` (235) și
  `masoara()` (266) nu pleacă nicăieri. Verificare în aplicație: nicio cerere spre `google-analytics.com`,
  `googletagmanager.com`, `connect.facebook.net`.
- **Vimeo fără urmărire:** orice iframe `player.vimeo.com` din aplicație are `dnt=1` (embed-urile din 235, 44, 51, 265
  îl au deja; puntea îl adaugă celor care nu-l au). Embed-ul Apple Podcasts: de verificat; dacă pune cookie-uri de
  analiză, în aplicație se înlocuiește cu linkul spre aplicația Podcasts.
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
- **Contul de recenzie** (Apple / Google): cod fix pentru o singură adresă, setat în Setări › Aplicația mobilă, prin
  `rest_pre_dispatch` pe `POST /ah-sp/v1/cod` și `/ah-sp/v1/intra` (235 neschimbat). Cerința completă și testele:
  `docs/review-account.md`. Fără el nu se trimite la recenzie.

## Pagina offline (app/, locală)

Se deschide când nu e internet. Arată: faza de azi din `Preferences.ah_ciclu` (același calcul), meditațiile
descărcate cu player (Media Session), butonul „Încearcă din nou”. Fără cont, fără rețea.

## Ce a rămas din v1

Păstrăm: proiectul Capacitor și folderele native, construirea automată (`.github/`, `fastlane/`), documentele pentru
magazine (de actualizat), ghidul `docs/release.md`. Renunțăm: ecranele React și `server/ah-app-api.php` (logarea
proprie, `/meditatii`, `/cursuri`, `/ciclu` etc.); din el se refolosesc trimiterea FCM, `.well-known` și testele utile.

## Contul pentru el (partenerul ei) — decizie Mircea, 18 nov. 2026

Două feluri de cont în aceeași aplicație (și în browser):
- **Ea**: Spațiul meu complet (mai sus).
- **El**: un cont simplu, doar pentru ciclul ei: „Azi” (unde e ea, ce trăiește, cum o poate sprijini, ce urmează),
  „Învață” (pagini scurte despre menstruație, cele 4 faze, sindromul premenstrual, cum vorbește cu ea) și
  notificări la începutul fiecărei faze. Nimic de cumpărat, nimic din contul ei în afară de ciclu.

Fragment nou, separat: `server/ah-sp-partener.php` („Spațiul meu – Pentru el”), aditiv, nu modifică 235.
Merge și în browser, și în aplicație; în aplicație ascunde linkurile spre cărți/produse (ca modul aplicație).

**Invitația (din „Ciclul meu” al ei):** prenumele lui, emailul lui și o bifă separată, obligatorie:
„Sunt de acord ca [prenume] să vadă faza mea de azi și datele estimate ale fazelor următoare. Pot opri oricând.”
Dacă ciclul ei nu e încă salvat în cont (`ah_sp_ciclu`), invitația îi cere întâi acordul pentru salvare (ca în 235).
El primește un email (cadrul comun al emailurilor) cu un link personal permanent: `/pentru-el/?t=<token>`
(32 de octeți aleatori; pe server doar sha256). Linkul deschide aplicația (universal link) sau pagina din browser și
îl ține conectat (cookie semnat `ah_spp`, 1 an). Link pierdut: „Trimite-mi din nou linkul” cu emailul lui → token nou,
cel vechi se anulează. Ea vede în „Ciclul meu”: cine e invitat, dacă a intrat, ultima vizită; „Oprește accesul” (pe loc)
și „Trimite din nou invitația”. Cel mult 2 parteneri activi.

**Ce primește el (sincronizat, minim):** `GET /wp-json/ah-spp/v1/azi` (cookie `ah_spp` sau `?t=`) calculează pe server,
din datele ei criptate (`ah_sp_ciclu`: `ciclu.starturi`, `lung`, `men`, `sit`), cu EXACT regula `CI.faze`/`CI.lung` din 235:
`{nume_ea, stare:'ok'|'pauza'|'oprit', faza, zi, lungime, pana_la, urmatoarea:{faza, de_la}, inceputuri:[{faza,data}… 6 luni],
texte:{ce, sprijin[], citate[]}}`. NICIODATĂ notițele ei, simptomele, situația (`sit`) sau zilele exacte notate.
Dacă `sit` nu e `natural`/`alaptare`/`perimenopauza` sau ea a șters ciclul → `stare:'pauza'` cu un text neutru
(„Pentru moment, ea a oprit estimările.”). Accesul oprit → `stare:'oprit'`.

**Notificări pentru el:** în aplicație, push (FCM) din cron-ul zilnic, la începutul fiecărei faze, doar dacă s-a schimbat faza
(la fel și când ea notează o menstruație nouă care mută faza). Fără aplicație: email la începutul fiecărei faze, doar dacă
bifează asta pe pagina lui (oprire din orice email). Tokenurile push ale lui sunt separate de ale ei.

**În aplicație:** pe ecranul de intrare apare „Sunt partenerul ei” → „Deschide linkul primit pe email” sau „Trimite-mi din nou
linkul”. Apple: același cont simplu (fără parolă, fără cumpărături).

**Datele lui:** prenume, email (criptat; hash pentru căutare), token (hash), tokenuri push, ultima vizită. El își poate șterge
accesul din pagina lui; când ea își șterge contul sau ciclul, rândurile lui se șterg. Paragraf nou în Politica de confidențialitate.

**Învață:** 6–8 pagini scurte, CIORNĂ pentru Adela, din textele ei existente (Harta ciclului 41, citatele din „Magia Ciclului
Feminin” din 235, episoadele de podcast), fără afirmații medicale, cu nota „estimare, nu metodă de contracepție”.
Linkul vechi „Pentru el” (`?ciclul-ei` cu datele după #) continuă să meargă; în „Ciclul meu” generatorul vechi e înlocuit
vizual de invitația nouă.
