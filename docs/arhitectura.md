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
  (deschisă când site-ul nu răspunde). `allowNavigation`: doar `www.adelaharu.ro`, `adelaharu.ro` (2.2.0; iframe-urile
  Vimeo se încarcă oricum; pe iOS puntea primește mesaje doar din cadrul principal de pe `https://www.adelaharu.ro` și
  din pagina offline, `AhPuntePazita`).
  Orice altă adresă se deschide în browserul sistemului (Spotify, Apple Podcasts, Zoom, WhatsApp, email).
- Pluginuri (accesibile din pagina site-ului prin `window.Capacitor.Plugins.*`): `App`, `PushNotifications`,
  `LocalNotifications`, `Preferences`, `Filesystem`, `FileTransfer` (`@capacitor/file-transfer`), `Browser`,
  `Device`, `SplashScreen`, `StatusBar`, `Haptics` (opțional). Plus un plugin nativ mic, `AhMedia`, DOAR dacă e
  nevoie pentru comenzile de pe ecranul blocat (altfel Media Session din WebView + `UIBackgroundModes audio`).
- Info.plist: `UIBackgroundModes` = `audio` (+ `remote-notification` doar dacă trimitem notificări silențioase:
  NU în v1), Associated Domains `applinks:www.adelaharu.ro`, `PrivacyInfo.xcprivacy`.
  AndroidManifest: App Links `autoVerify` pentru `/spatiul-meu` (cu `?cont=` din emailuri) și `/app/intra`
  (exact + subcăile cu „/”; aceleași căi în AASA: `/spatiul-meu`, `/spatiul-meu/*`, `/app/intra`, `/app/intra/*`)
  și `/pentru-el` (pagina partenerului, linkul `/pentru-el/?t=` din emailul lui: `/pentru-el`, `/pentru-el/*`),
  `POST_NOTIFICATIONS`, `FOREGROUND_SERVICE_MEDIA_PLAYBACK` dacă e cazul.

## Puntea (în fragment, JavaScript tipărit doar în aplicație pe /spatiul-meu/, /sala-de-curs/, /cartile-mele/, /meditatiile-mele/)

`window.AHApp` (namespace unic), cu:

1. **Linkuri:** `App.addListener('appUrlOpen')` → dacă e `https://www.adelaharu.ro/...` permis, `location.href = url`
   (așa linkul personal `?cont=` din orice email deschide aplicația deja logată).
2. **Notificări push:** după intrarea în cont, cere permisiunea O SINGURĂ dată, la un moment potrivit (nu la prima
   deschidere), cu un ecran de explicație; tokenul FCM → `POST /wp-json/ah-spa/v1/push` (legat de emailul din
   cookie-ul `ah_sp`). La ieșire din cont → `POST /wp-json/ah-spa/v1/push/sterge` (2.3.0; `DELETE /push` rămâne pentru
   aplicațiile vechi). Atingerea unei notificări deschide `data.url`.
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
   - **contul de pe telefon** (2.2.0): `localStorage.ahspa_cine` ține amprenta contului care a scris datele paginii; alt
     cont pe același telefon ⇒ înainte ca 235 să citească ceva, pleacă `ahsp_*`, `ahspx_*`, `ahct_*`, `ah_ciclu_v1`, apoi
     `Preferences` ale ei (`ah_ciclu`, `ah_ciclu_notif`, `ah_push_on`, `ah_push_intrebat`, `ah_deschideri`), mementourile
     și descărcările. Tot ce citește/anulează/programează mementourile trece printr-o singură coadă.
     **Amprenta** (2.3.0): `S.cine` = HMAC al adresei cu un secret creat o dată în baza de date (`ah_spa_cine_secret`), nu
     din sărurile WordPress (rotirea lor ar fi șters ciclul tuturor de pe telefoane). `S.cine_vechi` = amprentele aceluiași
     cont (cea din săruri, 60 de zile; aliasurile din „Mută contul”, un an): pentru ele nu se șterge nimic, doar se rescrie
     `ahspa_cine`, iar refacerea primește și o copie `ah_ciclu` cu amprenta veche.
   - **o pagină fără cont** (cookie expirat) nu șterge nimic de pe telefon (2.3.0); șterg doar ieșirea cerută, alt cont,
     „Șterge contul”. Un „Șterge contul” întrerupt (aplicația închisă după răspuns) se termină la pornirea următoare
     (`Preferences.ah_sterge`, scris înaintea cererii).
   - mementourile se reprogramează și la schimbarea fusului orar (în semnătură) și la `App.resume`.
   - mementourile pornesc doar dacă ea a spus da (`ah_ciclu_notif` = 1; pe Android ≤ 12 permisiunea e dată fără
     întrebare, deci nu ajunge); textul de pe ecranul blocat e neutru („Ciclul meu · Începe o fază nouă.”).
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

7. **Setările aplicației** (2.4.0, Mircea, 19 nov.): în „Mai mult”, grupuri cu aspectul rândurilor din 235 — Notificări
   (push pe categorii, mementourile ciclului, mementoul zilnic de meditație, sunet, ore de liniște, ecranul blocat, insignă),
   Aspect (ca telefonul / după apus și răsărit la București, NOAA, fără locație / mereu zi / mereu seară, peste `ahspx_seara`
   din 266), Ecranul principal (widgetul), Meditații (doar pe Wi-Fi, spațiul folosit; temporizatorul în player), Confidențialitate
   (Ciclul meu cu Face ID / amprenta, Statistici anonime, Ciclul ei, Șterge contul), Despre. Alegerile de pe telefon:
   `Preferences.ah_setari`; cele pentru push: pe rândul tokenului ei (`POST /ah-spa/v1/preferinte`), aplicate de server în lot.
   **Interfața punte ↔ nativ** (fixă; puntea verifică și tace dacă lipsește): `AhApp.widget({mod:'meditatie'|'faza'|'nimic',
   titlu, sub, culoare, imagine, url})` → `{ok}`; `AhApp.biometrie()` → `{disponibil, tip:'faceid'|'touchid'|'amprenta'|'fata'|'cod'}`;
   `AhApp.autentifica({motiv})` → `{ok}` / `{ok:false, eroare}`; `AhApp.insigna({n})` → `{ok}` (răspunsul lui spune și că
   aplicația are partea nativă 2.4); `AhApp.tema({seara})` → `{ok}` (bara de stare). Toate se rezolvă, nu resping. Plugin
   `@capacitor/network`. Sunete: `res/raw/clopotel.wav` (Android, canalul implicit FCM din manifest `ah_clopotel`),
   `clopotel.caf` (iOS). Canalele Android: `ah_clopotel`, `ah_implicit`, `ah_liniste` și `ah_ciclu_*` (privat), create de punte.
   Id-urile notificărilor locale: ciclul 41000–41099, mementoul zilnic 41100.
8. **Statistici proprii** (2.4.0): fără Google Analytics, Firebase Analytics sau Meta în aplicație (ar cere ATT și bara de
   cookie-uri). Puntea trimite `POST /ah-spa/v1/stat {ev, platforma, versiune, obiect?}` doar cu „Statistici anonime” pornit
   (implicit da, interes legitim, opoziție din aplicație: `docs/juridic.md`, 5a). Serverul: contoare pe zi (13 luni) și o amprentă
   HMAC lunară pe zi pentru utilizatoarele active (≤ 35 de zile, secretul vechi șters); fără adrese, IP-uri, id-uri de telefon.
   Pagina lui: `do_action( 'ah_spa_stat', 'el_deschidere', … )` / `ah_spa_stat_inc()`. Setări › Aplicația mobilă › Statistici, CSV.

## Ce ascunde fragmentul în aplicație

- Server: în aplicație, orice pagină în afara listei permise (`/spatiul-meu/`, `/sala-de-curs/`, `/cartile-mele/`,
  `/meditatiile-mele/`, `/app/*`, politica de confidențialitate, termenii, politica de cookie-uri, `/wp-json/`,
  fișierele media) → redirect 302 la `/spatiul-meu/`.
- **Fără analiză și urmărire a terților în aplicație (cerință fermă; statisticile noastre, de mai sus, nu pleacă la nimeni; de ea depind etichetele de confidențialitate și notele de
  recenzie):** în aplicație nu se încarcă Google Analytics (`gtag` / Site Kit / GTM), pixelul Meta
  (`facebook-for-woocommerce`), atribuirea comenzilor WooCommerce (`sourcebuster`, `wc-order-attribution`) și bara de
  cookie-uri Complianz (în aplicație se folosesc doar cookie-uri funcționale: `ah_sp`, `ah_app`, `ah_med`, ale sălii,
  deci bara nu e necesară). Se scot la sursă (dequeue pe `wp_enqueue_scripts` / `wp_print_scripts`, filtrele
  Complianz, ieșirea pixelului), doar când `ah_spa_e_aplicatie()`, cu test; fără `gtag`, evenimentele `ev()` (235) și
  `masoara()` (266) nu pleacă nicăieri. Verificare în aplicație: nicio cerere spre `google-analytics.com`,
  `googletagmanager.com`, `connect.facebook.net`.
- **Vimeo fără urmărire:** orice iframe `player.vimeo.com` din aplicație are `dnt=1` (și, din 2.4.0, `allow="autoplay;
  fullscreen; picture-in-picture"` + `allowfullscreen`) (embed-urile din 235, 44, 51, 265
  îl au deja; puntea îl adaugă celor care nu-l au). Embed-ul Apple Podcasts: de verificat; dacă pune cookie-uri de
  analiză, în aplicație se înlocuiește cu linkul spre aplicația Podcasts.
- Pagini: CSS + JS care urmăresc desenările (ca 266): fila Comenzi și rândurile ei, butoanele/linkurile de plată și
  „Abonamentul meu” (portal Stripe), „Reînnoiește”, prețuri, linkurile `mai` din podcast, produsele din Ciclul meu
  (`D.ciclu.produse`), cursurile închise/expirate care trimit la cumpărare, card cadou, Recomandă, Black Friday,
  Women Empower Women, sugestiile de sub înregistrări (279), butoanele din sala de curs (233), cumpărarea de cărți.
  Ce rămâne: starea abonamentului („Activ până pe …”), fără nicio acțiune.
- Unde e mai sigur la sursă (filtre PHP existente, ex. datele `D` din `ah_sp_date`), se curăță la sursă.

## Pe server, fragmentul nou mai face

- `ah-spa/v1`: `POST /push`, `POST /push/sterge` (+ `DELETE /push`, pentru aplicațiile vechi), `GET /descarca`,
  `POST /sterge-cont`, `GET /config` (versiunea minimă, mesaj; îl întreabă și pagina offline); din 2.4.0 `POST /preferinte`,
  `POST /insigna`, `POST /stat`.
  Autentificare = cookie-ul `ah_sp` existent (`ah_sp_email()`), nimic nou. `Cache-Control: no-store`, LiteSpeed nocache —
  din 2.3.0 pe toate rutele `ah-sp`, `ah-spx`, `ah-spp`, `ah-spa` (`rest_post_dispatch`), în afară de `punte` și `config`.
- **„Mută contul”** (2.3.0, Setări › Aplicația mobilă, doar administratori): adresa veche → nouă pentru ciclul din cont
  (`ah_sp_ciclu.cheie`), emailurile fazelor, push, telefoanele Inner Temple; alias pentru amprenta de pe telefon;
  `do_action('ah_spa_cont_mutat', $vechi, $nou)`. Comenzile se mută în WooCommerce.
- **Cronul verificat** (2.3.0): ultimele rulări (`ah_spa_tick`, `ah_spa_lot`, `ah_spp_ultima_rulare`) în setări; anunț în
  admin după 26 h fără `ah_spa_tick` (sau fără cronul lui, cu fragmentul lui activ).
- Trimiterea push: FCM HTTP v1 (cont de serviciu în opțiunea `ah_spa_fcm`; până atunci doar jurnal). Declanșatori:
  meditație nouă publicată (celor cu acces), înregistrare „Respiră-te acasă” publicată (celor înscrise în ziua
  aceea), întâlnire care începe în 15 minute (celor înscrise). În loturi, prin cron, fără să blocheze cererea.
- `/.well-known/apple-app-site-association` și `/.well-known/assetlinks.json` din opțiuni (Team ID, SHA-256).
- `/app/intra`: pagina pentru cine nu are aplicația (linkurile spre magazine).
- **Contul de recenzie** (Apple / Google): cod fix pentru o singură adresă, setat în Setări › Aplicația mobilă, prin
  `rest_pre_dispatch` pe `POST /ah-sp/v1/cod` și `/ah-sp/v1/intra` (235 neschimbat). Cerința completă și testele:
  `docs/review-account.md`. Fără el nu se trimite la recenzie.
- **Codul de intrare din 235, și în browser** (2.2.0): un contor atomic pe adresă (tabelul `wp_ah_spa_incercari`), mărit
  înaintea lui 235 prin `rest_pre_dispatch` pe `POST /ah-sp/v1/intra`: cel mult 5 încercări pe codul trimis, 15 greșeli
  pe zi, apoi `429` cu textul lui 235 (235 neschimbat; contorul lui nu e atomic).

## Pagina offline (app/, locală)

Se deschide când nu e internet sau când site-ul răspunde cu o eroare. Arată: motivul („Nu avem internet acum” /
„Site-ul e în lucru, revino în câteva minute” / pe Android cu WebView sub 90, „Telefonul are nevoie de o actualizare”),
faza de azi din `Preferences.ah_ciclu` (același calcul), meditațiile descărcate cu player (Media Session), butonul
„Încearcă din nou”. Fără cont. Singura cerere: `GET /wp-json/ah-spa/v1/config` (CORS, `r.ok`), ca să deosebească cele
două motive; încercările automate au pauze tot mai lungi după fiecare întoarcere rapidă (fără buclă site ↔ offline).
iOS: `AhNavigare` deschide pagina și pentru un 5xx al site-ului (`offline.html?cod=`); Android: Capacitor o deschide la
orice ≥ 400, iar codul îl ține `AhApp.info().eroare`. Android: un proces WebView oprit reface ecranul (nu închide aplicația).

## Ce a rămas din v1

Păstrăm: proiectul Capacitor și folderele native, construirea automată (`.github/`, `fastlane/`), documentele pentru
magazine (de actualizat), ghidul `docs/release.md`. Renunțăm: ecranele React și `server/ah-app-api.php` (logarea
proprie, `/meditatii`, `/cursuri`, `/ciclu` etc.); din el se refolosesc trimiterea FCM, `.well-known` și testele utile.

## Contul pentru el (partenerul ei) — decizie Mircea, 18 nov. 2026

Două feluri de cont în aceeași aplicație (și în browser):
- **Ea**: Spațiul meu complet (mai sus).
- **El**: un cont simplu, doar pentru ciclul ei: „Azi” (unde e ea, ce trăiește, cum o poate sprijini, ce urmează),
  „Învață” (cele 4 faze, fiecare fază, podcastul; doar textele de pe site) și
  notificări la începutul fiecărei faze. Nimic de cumpărat, nimic din contul ei în afară de ciclu.

Fragment nou, separat: `server/ah-sp-partener.php` („Spațiul meu – Pentru el”), aditiv, nu modifică 235.
Merge și în browser, și în aplicație; în aplicație ascunde linkurile spre cărți/produse (ca modul aplicație).

**Invitația (din „Ciclul meu” al ei):** prenumele lui, emailul lui și o bifă separată, obligatorie:
„Sunt de acord ca [prenume] să vadă faza mea de azi și datele estimate ale fazelor următoare. Pot opri oricând.”
Dacă ciclul ei nu e încă salvat în cont (`ah_sp_ciclu`), invitația îi cere întâi acordul pentru salvare (ca în 235).
El primește un email (cadrul comun al emailurilor) cu un link personal permanent: `/pentru-el/?t=<token>`
(32 de octeți aleatori; pe server doar sha256). Linkul deschide aplicația (universal link) sau pagina din browser și
îl ține conectat (cookie semnat `ah_spp`, 1 an). Link pierdut: „Trimite-mi din nou linkul” cu emailul lui → token nou,
cel vechi se anulează când îl folosește pe cel nou (review 18 nov.). Ea vede în „Ciclul meu”: cine e invitat, dacă a intrat, ultima vizită; „Oprește accesul” (pe loc)
și „Trimite din nou invitația” („Trimite-i din nou linkul” dacă a intrat deja; token și sesiune noi: deconectează orice telefon sau
browser al lui, dar doar dacă emailul a plecat, 1.4.0). Cel mult 2 parteneri activi (verificat sub `GET_LOCK`, ca două cereri
deodată să nu treacă amândouă). Adresa lui se verifică strict (1.4.0): diacriticele, spațiile sau orice caracter non-ASCII sunt
refuzate cu un mesaj clar, nu „curățate” de `sanitize_email` spre adresa altcuiva.

**Ce primește el (sincronizat, minim):** `GET /wp-json/ah-spp/v1/azi` (cookie `ah_spp` sau `?t=`) calculează pe server,
din datele ei criptate (`ah_sp_ciclu`: `ciclu.starturi`, `lung`, `men`, `sit`), cu EXACT regula `CI.faze`/`CI.lung` din 235:
`{nume_ea, nume, stare:'ok'|'pauza'|'oprit', faza, pana_la, urmatoarea:{faza, de_la, pana_la, sprijin, arhetip}, inceputuri:[{faza,data,pana}… până la iarna următoare, cel mult 35 de zile],
texte:{ce, sprijin[], citate[], arhetip, luna}, setari}` — doar ce arată pagina (fără `zi`, `lungime`, `harta`: pagina lui nu are
inel și nici „Ziua N din L”, decizia lui Mircea, 18 nov.). NICIODATĂ notițele ei, simptomele, situația (`sit`) sau zilele exacte notate.
Ea a șters ciclul, date nevalide, prima zi în viitor → `stare:'pauza'` cu un text neutru („Pentru moment, aici nu e nicio
estimare.”, fără motiv). **Ziua estimată a menstruației nu se vede (1.3.0, Mircea):** iarna următoare apare ca fereastră (±2 zile),
toamna ține „până la următoarea menstruație” fără dată, iar răspunsul e identic în ziua dinainte, în ziua estimată și după ea;
după capătul ferestrei, întârzierea arată ca toamna fără date, la fel până la 10 zile după ziua estimată, apoi pauza. `sit` care nu e
`natural`/`alaptare`/`perimenopauza` → pauza, dar nu pe loc: la următoarea graniță de fază sau după 1–3 zile (alese pe server,
pentru fiecare partener, păstrate în `pauza_de_la`). `sit` lipsă = `natural`, ca pe ecranul ei. Accesul oprit → `stare:'oprit'`.

**Notificări pentru el:** în aplicație, push (FCM) din cron-ul zilnic (8:10 ora site-ului, lanț de evenimente unice, deci și
după schimbarea orei), la începutul fiecărei faze, doar dacă s-a schimbat faza (la fel și când ea notează o menstruație nouă care
mută faza). Faza e notată ca trimisă doar după o trimitere reușită (altfel se reîncearcă). Fără aplicație: email la începutul fiecărei faze, doar dacă
bifează asta pe pagina lui (oprire din orice email). Tokenurile push ale lui sunt separate de ale ei. **Notificarea nu spune faza
(1.4.0)**, pentru că se vede pe ecranul blocat: „Ciclul ei” / „Începe o fază nouă. Vezi cum o poți sprijini.”, iar pe Android
vizibilitatea e `PRIVATE`; subiectul emailului e „Ciclul ei: o fază nouă” (corpul păstrează faza și sprijinul). Invitat de două
femei, telefonul lui stă lângă amândouă invitațiile (unic pe partener + token) și primește de la amândouă. Fiecare rulare a
cronului notează `ah_spp_ultima_rulare` (avizul din admin al modulului aplicație).

**Un telefon = un cont, pentru push** (integrare, 18 nov.): tokenul FCM e al telefonului, nu al contului, deci stă într-un
singur tabel o dată. Serverul îl mută: `POST /ah-spa/v1/push` (ea) îl scoate din `wp_ah_spp_push`, `POST /ah-spp/v1/push`
(el) îl scoate din `wp_ah_spa_push`. Pe telefon, `Preferences.ah_push = {token, cont:'ea'|'el', …}` spune al cui e
(rândurile fără `cont`, de dinainte, sunt ale ei). **Contul activ** = pagina deschisă: aplicația pornește din
`/spatiul-meu/`; cu ea intrată (`ah_sp`) e contul ei, altfel cu cookie-ul lui (`ah_spp`) serverul o duce la `/pentru-el/`.
Pe un telefon cu ea intrată, telefonul e al ei: pagina lui nu oferă notificări (`cont_ea`) și `POST /ah-spp/v1/push`
întoarce 409 `cont_ea`. Puntea ei (`AHApp`) se tipărește doar pe paginile ei, deci nu poate lega telefonul lui de contul
ei; pagina lui are scriptul ei, care scrie doar la `ah-spp/v1`. „Ieși”/„Șterge accesul meu” la el scot doar tokenul lui
(`unregister()` doar dacă `ah_push.cont` e `el`); ieșirea ei scoate doar tokenul ei și nu atinge tokenul lui cât e intrat
el (`AH_SPA.el`); pe o pagină fără niciun cont, un token rămas al lui pleacă prin `POST /ah-spp/v1/push/sterge` (1.4.0, pentru
firewallurile care blochează DELETE; `DELETE /push` rămâne pentru compatibilitate).

**Interfețele cu modulul aplicație (1.4.0):** `ah_spa_cont_mutat($vechi, $nou)` — ea și-a schimbat adresa: rândurile ei trec pe
`ah_sp_ciclu_cheie($nou)`; `ah_spa_are_acces_el` — adevărat doar cu un cookie `ah_spp` valid al unui acces activ (aplicația ei arată
atunci rândul „Ciclul ei” spre `/pentru-el/`); `ah_spp_ultima_rulare` (opțiune, unix time).

**În aplicație:** `/pentru-el/` e pe lista permisă (filtrul `ah_spa_permise`), deschisă în aplicație (`allowNavigation`
`www.adelaharu.ro`, App Links / AASA); fără analiză, fără Google Fonts, fără cărți. Pe ecranul de intrare apare „Sunt partenerul ei” → „Deschide linkul primit pe email” (cu un câmp pentru
linkul copiat, care îl deschide în aplicație) sau „Trimite-mi din nou linkul”. Apple: același cont simplu (fără parolă, fără cumpărături).

**Datele lui:** prenume, email (criptat; hash pentru căutare), token (hash), tokenuri push, ultima vizită. Cheia modulului nu
stă în baza de date (constanta `AH_SPP_CHEIE` din `wp-config.php` sau derivată din sărurile WordPress). Cheia veche din opțiuni
(până la 1.2.0) rămâne 30 de zile după urcare (1.4.0): rândurile vechi se citesc cu ea și se recriptează abia la capăt, așa că o
întoarcere sub 1.3 e sigură doar în aceste 30 de zile (apoi copia veche a fragmentului se șterge). Cheia ciclului din 235 (`ah_sp_ciclu_secret`) e încă în opțiuni: recomandare în
`docs/partener.md`. Rutele care schimbă ceva cer antetul `X-AH-SPP: 1` (apărare CSRF în plus față de SameSite=Lax). El își poate șterge
accesul din pagina lui; când ea își șterge contul (`sterge-cont`, cu numărul în emailul echipei: `pentru_el`) sau ciclul,
rândurile lui și tokenurile lui push se șterg pe loc. Paragraf nou în Politica de confidențialitate.

**Textele (Mircea, 18 nov.): niciun text nou.** Azi și Învață (6 pagini) se fac doar din ce e deja pe site: Harta ciclului 41
(`pe_scurt`, `simti`, `ajuta`, `practica`, arhetip, luna, „Nota” din *Spre feminitate*), 235 (`sprijin`, citatele din „Magia Ciclului
Feminin”, episoadele 2–6 ale podcastului, frazele paginii `?ciclul-ei`), cu nota „Fazele sunt o estimare: nu sunt o metodă de
contracepție.” (41). Notificarea fazei: neutră (vezi mai sus); emailul fazei: „Începe {Iarna ei interioară…}” + sprijinul. Emailurile lui numesc operatorul
(HARU TRAINING SRL) și politica de confidențialitate; versiunea textului acordului ei (`acord_v`) stă lângă `acord`. Puținele rânduri
funcționale fără sursă pe site: lista din `docs/partener.md`.
Linkul vechi „Pentru el” (`?ciclul-ei` cu datele după #) continuă să meargă; în „Ciclul meu” generatorul vechi e înlocuit
vizual de invitația nouă.
