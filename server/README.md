# server/ — „Spațiul meu – modul aplicație” (v2)

Un singur fragment Code Snippets: **`ah-sp-aplicatie.php`**. Arhitectura: `docs/arhitectura.md`.
Înlocuiește v1 (`ah-app-api.php`, API-ul și logarea proprie: șterse; rămân în istoricul git).

Fragmentul lucrează **doar în aplicație**. Fiecare cârlig de pagină întreabă întâi `ah_spa_e_aplicatie()`;
în browser site-ul rămâne exact ca acum (verificat în teste: același `<head>`, aceeași ieșire a shortcode-ului,
aceleași clase, nicio redirecționare). Fragmentele existente (235, 266, 281, 295, 220/265, 51, 44, 231, 233, 279, 178)
**nu se modifică**.

## Cum se urcă (doar cu OK-ul lui Mircea)

1. Copie de siguranță a site-ului.
2. Code Snippets › Add New › PHP, titlul „Spațiul meu – modul aplicație”. Lipești tot ce e **sub** `<?php`.
   „Run everywhere”. Se salvează **inactiv**; `php -l` a trecut (vezi Teste).
3. Se activează când există o versiune a aplicației de probat (fără aplicație nu face nimic vizibil, în afară de
   `/.well-known/…`, `/app/intra` și un tabel nou `wp_ah_spa_push`, creat la primul `init`).
4. Setări › Aplicația mobilă: Team ID, amprentele SHA-256, cheia Firebase (JSON), linkurile magazinelor,
   versiunea minimă și mesajul, ce notificări pleacă, emailul echipei. Butonul „Probă” trimite o notificare.
5. LiteSpeed Cache › Excludes: **Do Not Cache User Agents** += `AdelaHaruApp`, **Do Not Cache Cookies** += `ah_app`
   (paginile din aplicație și redirecționările ei nu trebuie să ajungă în cache-ul public; fragmentul trimite și el
   `litespeed_control_set_nocache`, dar o pagină deja în cache e servită înainte de PHP).

Oprire: dezactivezi fragmentul. Aplicația arată atunci Spațiul meu ca în browser (inclusiv ce se vinde: de evitat
cât timp aplicația e în magazine).

## Ce face

| | |
|---|---|
| Recunoaștere | UA conține `AdelaHaruApp/<ver> (ios\|android)` **sau** cookie `ah_app=ios\|android` (pus la prima cerere cu UA, 1 an, Secure, HttpOnly, Lax). `ah_spa_e_aplicatie()`, `ah_spa_platforma()`, `ah_spa_versiune()`. |
| Lista permisă | `/spatiul-meu/`, `/sala-de-curs/`, `/cartile-mele/`, `/meditatiile-mele/`, `/app/`, `/politica-de-confidentialitate/`, `/termeni-si-conditii/`, `/politica-de-cookie-uri/`, `/wp-json/`, `/wp-content/`, `/wp-includes/` și, pe prima pagină `/`, doar cu cheile `ciclul-ei`, `ah_med_api`, `ah_sc_ics`, `ah_sc_ics_r`, `ahp`, `ah_sc_ritm_stop`, `ah_sc_repeta_stop` (partenerul, sunetul meditațiilor, calendarul și mementourile sălii 44; filtru `ah_spa_chei_radacina`). Orice altceva → 302 la `/spatiul-meu/` (fără cache). Filtru: `ah_spa_permise`. |
| Blocate și pe pagini permise | `?add-to-cart=`, `?ah_bf=` → `/spatiul-meu/`; `?mergi=abonament` (portalul Stripe) → `/spatiul-meu/#abonamente`; `/meditatiile-mele/` rămâne întâi la 235 (intrarea automată), apoi → `#meditatii`; REST `/ah-hb/v1/portal`, `/ah-it/v1/portal`, `…/abonare` → 302 la `#abonamente` (`rest_pre_dispatch`). |
| Date curățate la sursă | `do_shortcode_tag` pe `[ah_spatiul_meu]`: JSON-ul `window.AH_SP` trece prin `ah_spa_curata_date()` (tabelul de mai jos). Aceeași curățenie o face și scriptul, ca rezervă, legat de `window.AH_SP` după 266. |
| Puntea | `<head>` (prioritatea 61, după 266) pe Spațiul meu, sala de curs și Cărțile mele: stilul `#ahspa-css` + `window.AH_SPA` (configurație fără email, doar o amprentă `cine`) + `window.AHApp`. |
| REST `ah-spa/v1` | vezi mai jos; răspunsuri `Cache-Control: no-store, private` + LiteSpeed nocache. |
| Push | FCM HTTP v1 prin cron (`ah_spa_lot`): grupuri de 100 de rânduri, câte o cerere pe telefon (3 s), bugetul de 20 s verificat înaintea fiecărui telefon, cursorul (opțiunea `ah_spa_lucrari`) scris după fiecare notificare plecată: o oprire la mijloc repetă cel mult un telefon. Lacăt 120 s. Fără cheie: doar jurnal. |
| Politici | `/termeni-si-conditii/`, `/politica-de-confidentialitate/`, `/politica-de-cookie-uri/` = zona `politici` (filtru `ah_spa_politici`): stilul și curățenia rulează și aici; antetul și subsolul temei (Shop, Cursuri online, Cart, abonarea la scrisori), `#ahbq-*`, Black Friday, popup-urile Elementor ascunse; linkurile blocate din textul legal devin text (paragraful rămâne întreg); sus, bara „Înapoi în Spațiul meu” (CIORNĂ; înapoi în istoric dacă a venit din aplicație). |
| Linkuri universale | `/.well-known/apple-app-site-association` (`/spatiul-meu`, `/spatiul-meu/*`, `/app/intra`, `/app/intra/*`; aceleași căi ca App Links pe Android), `/.well-known/assetlinks.json`. |
| `/app/intra` | Fără aplicație: pagina cu magazinele + „deschide Spațiul meu” (păstrează `?cont=`). În aplicație: 302 la `/spatiul-meu/?cont=…`. |

## Tot ce se ascunde în aplicație și cum

**La sursă** (`ah_spa_curata_date`, înainte să ajungă în pagină):

| Ce | Cum |
|---|---|
| Fila și ecranul **Comenzi**, rândul Acasă „Ai o plată de făcut”, „Plătește”, tile-ul Comenzi, rândul „Comenzile mele” din Mai mult | `comenzi = []` (235 nu mai desenează nimic din ele) |
| Cursurile **încă nedeschise** (lacăt, „Descoperă cursul” → pagina produsului) | `inchise = []` |
| Cursurile **încheiate** (sala le oferă din nou spre cumpărare) | scoase din `cursuri` |
| Meditațiile **închise** din Inner Temple („Încă închise”, prețul „49 lei”, „Deschide”, „În curând”) | `med_toate` doar cu `acces`, `pret` și `cumpara` goale |
| **Abonamente**: „Gestionează” (portalul Stripe) | `link = ''`; „Acces plătit până pe …” → „Activ până pe …” |
| **Ciclul meu**: produsele fiecărei faze („Ce te susține acum”, cu lacăt) | `ciclu.produse = {}` |
| **Podcast**: „Mai departe: <curs>” sub episod | `episoade[].mai = null` |
| Dă mai departe / Women Empower Women, Card cadou, „Vezi cursurile”, „Practicile pentru fiecare fază” (Yin Nourish de cumpărat) | `linkuri.recomanda/cadou/cursuri/yin = ''` (apoi scoase din pagină) |
| Înapoi pe site | `linkuri.site` RĂMÂNE (din el se face linkul pentru partener din Ciclul meu: `CI.paLink` = `site + '?ciclul-ei#d=…'`); rândul e ascuns din stil (`#ahsp a[href="<prima pagină>"]`) și scos de `curata()` |

**În pagină** (stil `#ahspa-css` + scriptul care urmărește desenările, ca 266, funcția `curata()`):

| Ce | Cum |
|---|---|
| Orice link golit la sursă (`href=""`) | stil + rândul lui scos (`.rand`, `.tile`, `.cov`, `li`; în meniuri doar linkul; altfel paragraful) |
| Orice link spre o pagină din afara listei permise sau spre tipare de cumpărare (`stripe.com`, `/checkout`, `/cart/`, `/cos/`, `/product/`, `/produs/`, `/black-friday`, `/card-cadou`, `/recomanda`, `/cursuri-online`, `add-to-cart=`, `ah_bf=`, `mergi=abonament`, `/wp-json/ah-(hb\|it)/v1/portal`, `…/abonare`), plus ancora `#comenzi` | același `curata()`; tiparele: filtrul `ah_spa_tipare_blocate` |
| `.cov.inchis`, `.curs.inchis`, `.cov-pret`, `.med-desch` | stil + scoase |
| Paragraful „Oprești abonamentul oricând…” (Abonamente) | scos |
| „Fișierele pentru Kindle sunt în emailul de la cumpărare” | înlocuit cu „…le găsești în emailul cu cartea” (CIORNĂ) |
| 266: „Pune pe ecranul telefonului” (rândul și indiciul) | stil `#spx-inst-r`, `.spx-inst` |
| Sala (233): butoanele Black Friday, Card cadou, Women Empower Women | `#ahsb` ascuns și scos |
| Sala (279): sugestiile de sub înregistrare („Vezi detalii →”) | `#rsg` ascuns și scos |
| Sala (224): „Abonamentul meu” (portal) din rândul HER BREATH; „Revino · … lei / lună” | linkul scos (rămâne „Abonament activ până pe …”); `.ahhb-lacat .ahs-buton` |
| Sala (44): „te poți abona din nou de pe pagina programului” (acces încheiat) | paragraful scos |
| Sala (44): „Mai departe” de la finalul cursului (alt program, preț, „Vezi programul →”) | `#ahs-urmator` ascuns |
| Sala (214): „Dacă ți-a făcut bine, te invit…”, „Vreau să continui practica lunară →”, rândul cu prețul | `#ah-dar7-curs` ascuns |
| Sala (44): „Adaugă în calendarul meu” (`/?ah_sc_ics=…`, `/?ah_sc_ics_r=…`) | RĂMÂNE; în aplicație se deschide cu `Browser.open` (WebView-ul nu deschide atașamentul .ics) |
| Black Friday (178): banda de sus, bara de jos, pagina | `#ah-bf-banda`, `.ah-bf-banda`, `#ah-bf-bara`, `#ahbf`, `#ahbq-*` |
| Cadrul 235 în sală / cărți: Dă mai departe, Card cadou, Înapoi pe site, fila Comenzi | stil + scoase |

Rămâne: starea abonamentelor, cursurile active, cărțile, meditațiile pe care le poate asculta, darul Adelei,
podcastul (Spotify / Apple Podcasts se deschid în afara aplicației), Ciclul meu, întâlnirile, WhatsApp.

## REST `/wp-json/ah-spa/v1/`

Autentificare = cookie-ul `ah_sp` existent (`ah_sp_email()`). Cererile care schimbă ceva cer antetul `X-AH-App: 1`.

| Ruta | |
|---|---|
| `POST /push` | `{token, platforma: ios\|android, versiune, disp}` → leagă tokenul FCM de adresă (un rând per token, același `disp` = token vechi șters, cel mult 10 pe adresă, 30/oră). Tokenul APNs brut (64 hex) e refuzat cu `motiv: apns`. |
| `DELETE /push` | `{token}` → scos. Nu cere contul (merge și după ieșire): cine are tokenul e telefonul acela. |
| `GET /descarca?id=` | acces verificat cu `ah_med_acces()` + `ah_med_poate()` (51) → `{url, expira, titlu, durata, imagine, ext}`; `url` = `/fisier`, semnat HMAC, 15 minute. 60/oră. |
| `GET /fisier?id&exp&c&s` | fără cookie (îl cere FileTransfer); semnătura `HMAC(id\|exp\|cine)`, `cine` = amprenta adresei (adresa nu apare în link). Fișierul din `ah_med_cale()` cu `Range` (206/416), sau 302 spre URL-ul extern al sunetului. |
| `POST /sterge-cont` | `{confirmare: true}` → șterge: ciclul din cont (`ah_sp_ciclu`, ca `ciclu-sterge`), emailurile fazelor (`ah_ciclu`, 41), intrarea din `ah_sp_conturi` (281), vizitele (`ah_sp_vizite`), tokenurile push, telefoanele/minutele/ascultările Inner Temple (`ah_med_d_/min_/asc_`), cookie-urile `ah_sp`, `ah_med`, `ah_ct_*`, `ah_sc*`; pe comenzile ei (`wc_get_orders` după `billing_email`) mementourile sălii `_ah_sc_ritm` și `_ah_sc_repeta` → `activ=false`, `sms=false`, `telefon=''`, cu `ah_sc_ritm_index`/`ah_sc_repeta_index` și o notă pe comandă; înscrierea ei din `ah_respira_inscrieri` (220) ștearsă. Comenzile și facturile rămân. Email echipei (cu ce s-a șters, inclusiv `sala_ritm`/`sala_repeta`/`respira_inscriere`, și ce e de verificat de mână: EmailOctopus). Cârlig: `ah_spa_cont_sters`. |
| `GET /config` | `{versiune_minima:{ios,android}, mesaj, magazine:{ios,android}, pagina}` (public, 5 minute cache). |

## Notificările push

- **Meditație nouă**: `transition_post_status` pe `ah_meditatie` → după 2 minute, lucrare către cei care o pot asculta (`ah_med_poate`). O singură dată (`_ah_spa_anuntat`).
- **Înregistrare „Respiră-te acasă”**: `update_option_ah_respira_inregistrari` + cronul la 5 minute (`ah_spa_tick`) → către înscrisele în ziua aceea (`ah_respira_inreg_destinatare`). Nu și dacă mai e mai puțin de o oră din cele 24, nici pentru înregistrări publicate înainte de pornirea fragmentului.
- **Întâlnire în 15 minute**: `ah_respira_start()` (220) → către înscrisele în ziua aceea. Alte module: filtrul `ah_spa_intalniri`.
- `data.url` deschide în aplicație secțiunea potrivită. Jurnal în Setări (fără adrese de email).

## Puntea `window.AHApp` (JavaScript, doar în aplicație)

- **Linkuri**: `App.appUrlOpen` și `App.getLaunchUrl()` → dacă e o adresă permisă de pe site, se deschide (linkul personal `?cont=` intră direct în cont). Atingerea unei notificări (push sau locală) deschide `url`.
- **Push**: întrebarea apare **o singură dată**, din a doua deschidere a aplicației cu contul intrat, pe Acasă, cu un ecran de explicație („Vrei să-ți dăm de veste?”). „Nu acum” nu mai întreabă; se poate porni din Mai mult › Aplicația › Notificări. Tokenul se retrimite la schimbarea contului sau o dată pe săptămână.
- **Ciclul**: citește `localStorage.ahsp_ciclu` + `ah_ciclu_v1` cu **exact** `CI.d`, `CI.lung`, `CI.faze` din 235 (copiate; testul le compară cu originalul); notificări locale la 9:00 la începutul fiecărei faze, pe următoarele 3 cicluri, id-uri 41000–41099, reprogramate la orice schimbare (prinsă din `localStorage.setItem`). Nimic pentru `hormonal`/`sarcina`, înainte de prima zi sau când menstruația întârzie. Comutator în Mai mult. Scrie `Preferences.ah_ciclu = {starturi, lung, men, sit, actualizat}`.
- **Descărcări**: „Descarcă” / „Se descarcă… %” / „Descărcat” + „Șterge descărcarea” lângă fiecare meditație Inner Temple pe care o poate asculta. Fișierele: `meditatii/<id>.<ext>` și `meditatii/<id>.jpg` în `LIBRARY_NO_CLOUD` (iOS: Library fără copie iCloud, cerința Apple pentru conținut redescărcabil) sau `DATA` (Android). Index: `Preferences.ah_descarcate = [{id,titlu,durata,fisier,poza,dir,descarcat}]` — `dir` e directorul de folosit la citire.
- **Ecranul blocat**: Media Session (titlu, „Adela Haru”, poza, play/pauză, ±15 s, poziție) pe orice sunet pornit (playerul Inner Temple, sunetele din sală).
- **Mai mult › Aplicația**: Notificări, Mementourile ciclului, Meditații descărcate (șterge tot), **Șterge contul** (bifă de confirmare; apoi șterge descărcările, Preferences, mementourile și datele `ahsp_*` de pe telefon), versiunea. Se alătură secțiunii „Aplicația” a lui 266, dacă există.
- **Ieșirea din cont**: înainte de „Ieși din cont” scoate tokenul push, descărcările și mementourile. O pagină fără cont (cookie expirat) face aceeași curățenie.
- **Versiunea minimă**: sub ea, o bandă cu mesajul și „Actualizează” (linkul magazinului).

## Teste

```
bash server/tests/ruleaza.sh
```

- `php server/tests/run.php` — 322 de verificări, WordPress imitat (`tests/stubs.php`, `$wpdb` = SQLite în memorie): recunoașterea, lista permisă (36 de adrese, în aplicație și în browser), zona politici, portalurile REST, curățarea datelor (pe `tests/date/spatiul-meu.json`) și a ieșirii shortcode-ului, „nimic în browser”, semnăturile și `Range`, `descarca`/`fisier`, validarea și tabelul push, ștergerea contului (ce pleacă, mementourile sălii, Respiră-te acasă, ce rămâne, datele altor cliente), loturile push cu cheie (oprire la mijloc, bugetul de timp, cel mult o dublură), AASA/assetlinks, setările, declanșatorii, JWT-ul FCM (RS256 verificat).
- `node server/tests/ciclu.test.cjs` — ~50.000 de comparații între calculul ciclului din punte și `CI` citit direct din `reference/v2/sn235.php`: fazele pentru ciclu 21–45 × menstruație 2–10, `CI.d` și `CI.lung` pe 3.000 de istorii aleatoare (cu date stricate și limite depășite), mementourile pe 3 cicluri.
- `node server/tests/vizual/poze.cjs` — harnașul vizual: Spațiul meu (CSS/JS din 235 și 266) , o sală de curs de probă (cu „Mai departe”, 214 și calendarul .ics) și Termeni și condiții (antetul și subsolul temei din pagina publică), în Chromium 390×844, browser vs aplicație (cu `window.Capacitor` imitat), totul servit local ca `https://www.adelaharu.ro` (nimic nu pleacă pe internet). 115 verificări (niciun text/link/element de cumpărare vizibil în aplicație, pe fiecare secțiune; martorii din browser; mementourile = 235; descărcarea; Media Session; push; linkurile; ieșirea; ștergerea contului). Pozele: `docs/screenshots-v2/` (`browser-*` și `aplicatie-*`). În pozele „full page”, bara de jos (fixă) apare la mijlocul paginii: e doar efectul capturii.

## Ce rămâne de făcut de oameni

- **Adela**: textele marcate CIORNĂ (explicația notificărilor, mementourile fazelor, ștergerea contului, `/app/intra`, notificările push, banda de actualizare).
- **Mircea**: OK pentru urcare (inactiv, cu copie); LiteSpeed (pasul 5); cheia Firebase; Team ID și amprentele după crearea aplicațiilor în magazine.
- **Aplicația (app/)**: pe iOS, `@capacitor/push-notifications` dă tokenul **APNs**; FCM HTTP v1 cere tokenul **FCM** (Firebase Messaging în AppDelegate, care trimite tokenul FCM la `capacitorDidRegisterForRemoteNotifications`, sau `@capacitor-firebase/messaging`). Serverul refuză clar tokenul APNs (`motiv: apns`). UA-ul `AdelaHaruApp/<ver> (ios|android)` prin `appendUserAgent`. Pagina offline citește `ah_ciclu` și `ah_descarcate` (cu `dir`).
- **Bara de cookie-uri** (Complianz) rămâne: pagina încarcă Google Analytics și Meta, deci nu sunt doar cookie-uri funcționale. Dacă se oprește analiza în aplicație, se poate ascunde (decizie juridică).
- Rămân în sală, ca stare, fraze ca „Abonamentul îl oprești oricând” (224, fără link). De văzut cu Adela dacă se rescriu.
