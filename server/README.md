# server/ — „Spațiul meu – modul aplicație” (v2)

Fragmentul Code Snippets **`ah-sp-aplicatie.php`** (modul aplicație) și, alături, **`ah-sp-partener.php`** („Pentru el”,
contul partenerului: `docs/partener.md`). Arhitectura: `docs/arhitectura.md`. Legăturile dintre ele: secțiunea
„Contul pentru el în aplicație” mai jos.
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
   și `ah_spp`, **Do Not Cache URIs** += `/pentru-el/` (pagina lui, `docs/partener.md`)
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
| Linkuri universale | `/.well-known/apple-app-site-association` (`/spatiul-meu`, `/spatiul-meu/*`, `/app/intra`, `/app/intra/*`, `/pentru-el`, `/pentru-el/*`; aceleași căi ca App Links pe Android, verificat în `tests/integrare/run.php`), `/.well-known/assetlinks.json`. |
| `/app/intra` | Fără aplicație: pagina cu magazinele + „deschide Spațiul meu” (păstrează `?cont=`). În aplicație: 302 la `/spatiul-meu/?cont=…`. |
| Contul de recenzie | Secțiunea 9 (cerința: `docs/review-account.md`). Setări › Aplicația mobilă › „Contul de recenzie”: adresa, codul fix (6 cifre; refuzate 123456, 654321, 000000, șirurile și cifra repetată; **se păstrează doar `wp_hash_password`**, câmpul se afișează gol cu „Cod setat: da/nu”), „Doar în aplicație” (bifat implicit). Opțiunea `ah_spa_recenzie` (non-autoload). `rest_pre_dispatch` (prioritatea 4) prinde **doar** `POST /ah-sp/v1/cod` și `/ah-sp/v1/intra` cu exact adresa de recenzie (normalizată ca în 235): `/cod` → `200 {"ok":true}` fără email și fără contoarele 235 (limită proprie 30/oră/IP); `/intra` → `wp_check_password`, la succes `ah_sp_seteaza_cookie()` din 235 + golirea dispozitivelor meditațiilor (51) și ale sălii (44) **doar pentru această adresă**; 5 coduri greșite la rând ⇒ 429 timp de 15 minute (`ah_spa_rec_n`). Orice altă adresă, metodă sau rută: neatinsă. Fără 235: nu face nimic. Jurnal fără cod și fără adresă, IP trunchiat (/24, /48). `sterge-cont` pe adresa de recenzie: subiectul emailului are „(contul de recenzie)”. |
| Fără analiză și urmărire | Secțiunea 10, doar în aplicație: (a) filtrele pluginurilor (`facebook_for_woocommerce_integration_pixel_enabled`, `wc_order_attribution_allow_tracking`, `cmplz_site_needs_cookiewarning`, Site Kit `*_tag_blocked`) și scoaterea din coadă (`wp_enqueue_scripts`, `wp_print_scripts/styles`, `wp_print_footer_scripts`) a scripturilor/stilurilor de urmărire (după handle și adresă: `ah_spa_e_urmarire`); (b) ce e tipărit direct în pagină (fragmentul GA al Complianz, Clarity, pixelul Meta, `<noscript>`-ul pixelului / GTM, bara și „Administrează consimțămintele”) se scoate din ieșirea paginilor aplicației (`ah_spa_fara_urmarire`, un singur `ob_start` la `template_redirect` 9999, doar pe Spațiul meu, sală, cărți, politici; nu pe `/?ah_med_api` & co.); (c) Conversions API (`graph.facebook.com/…/events`) cerut de server în timpul unei cereri din aplicație: oprit la `pre_http_request`. Scripturile 235/266/ale noastre nu se ating niciodată (chiar dacă întreabă de `gtag`); fără `gtag`, `ev()` (235) și `masoara()` (266) nu trimit nimic. |
| Vimeo, Apple Podcasts | Orice `<iframe>` `player.vimeo.com` din paginile aplicației primește `dnt=1` (în HTML, din server; cele desenate mai târziu, din punte; și cele ținute de Complianz în `data-src-cmplz`, care altfel n-ar mai porni fără scriptul lui). Playerul Apple Podcasts nu se încarcă: `episoade[].embed = 'about:blank'` la sursă, iar puntea pune în locul lui „Ascultă în Apple Podcasts” (linkul episodului, câmpul `apple`), pe care telefonul îl deschide în aplicația Podcasts. |

### De ce nu mai trebuie bara de cookie-uri în aplicație

Bara Complianz cere acord pentru cookie-urile „statistice” (Google Analytics, Microsoft Clarity) și „marketing” (pixelul
Meta). În aplicație toate acestea lipsesc (verificat în teste: niciun script, nicio cerere spre `googletagmanager.com`,
`google-analytics.com`, `connect.facebook.net`, `facebook.com/tr`, `clarity.ms`, nici atribuirea WooCommerce). Rămân doar
cookie-urile și stocarea strict necesare serviciului cerut de ea (`ah_sp` contul, `ah_app` recunoașterea aplicației,
`ah_med` sunetul meditațiilor, cookie-urile sălii, `localStorage`-ul Ciclului meu), scutite de acord (art. 5 alin. 3 din
Directiva 2002/58, art. 4 alin. 6 din Legea 506/2004), iar Vimeo merge cu `dnt=1` (fără cookie-uri și statistici).
Nimic nu mai cere acord, deci bara n-ar avea ce întreba: în aplicație se scoate. Politica de confidențialitate și cea
de cookie-uri rămân accesibile (zona „politici”). **Condiția**: dacă pe site apare un serviciu nou de analiză sau
reclame (alt plugin, alt domeniu), el trebuie adăugat în `ah_spa_urmarire_tipare` / `ah_spa_e_urmarire` înainte de
versiunea următoare a aplicației; altfel ar rula în aplicație fără bară. În browser bara și scripturile rămân exact ca acum.

## Contul pentru el în aplicație (integrarea cu `ah-sp-partener.php`)

| | |
|---|---|
| Lista permisă | `/pentru-el/` intră prin filtrul `ah_spa_permise` (din fragmentul lui) ⇒ redirecționarea din aplicație o lasă în pace, iar puntea o are în `S.permise` (`permis()` pentru `appUrlOpen`, `getLaunchUrl`, atingerea notificărilor). Fără fragmentul lui: `/pentru-el/` → `/spatiul-meu/`, ca orice altă cale. |
| Linkuri universale | `/pentru-el`, `/pentru-el/*` în AASA (`ah_spa_aasa()`) și în App Links (`AndroidManifest.xml`); pe iOS ajunge `applinks:www.adelaharu.ro`. Pornirea la rece din linkul lui: aplicația deschide `/spatiul-meu/`, puntea citește `getLaunchUrl()` și merge la `/pentru-el/?t=…` (cookie `ah_spp`, apoi adresa curată). |
| Contul activ pe telefon | Aplicația pornește mereu din `/spatiul-meu/`: cu ea intrată (`ah_sp`) e contul ei; fără ea, cu `ah_spp` valid, fragmentul lui face 302 la `/pentru-el/`. Puntea `AHApp` se tipărește doar pe paginile ei (zonele `sp`, `sala`, `carti`, `politici`), nu pe pagina lui; `AH_SPA` primește doar `el` (da/nu: e intrat el pe telefon), `api_el`, `pagina_el`. |
| Push, un telefon = un cont | Pe server: `POST /ah-spa/v1/push` scoate tokenul din `wp_ah_spp_push`, `POST /ah-spp/v1/push` îl scoate din `wp_ah_spa_push`; cu ea intrată pe telefon, `POST /ah-spp/v1/push` → 409 `cont_ea`. Pe telefon: `Preferences.ah_push.cont`. Pagina lui cere notificări doar cu `AhApp.info()` `push:true` + `token:'fcm'` (ca puntea; fără Firebase, `register()` oprește aplicația pe Android) și fără ea intrată (`cont_ea`); tokenul APNs brut nu pleacă. |
| Ieșirea | El: „Ieși” / „Șterge accesul meu” → `DELETE /ah-spp/v1/push` cu tokenul lui, `Preferences.ah_push` șters, `unregister()` doar dacă tokenul era al lui. Ea: vezi „Al cui e tokenul” mai sus. |
| Ștergerea contului ei | `ah_spa_sterge_cont()` șterge pe loc partenerii ei (`ah_spp_sterge_ale_ei`, rândurile + tokenurile lor push) și pune numărul în emailul echipei (`pentru_el`); fragmentul lui ascultă și de `ah_spa_cont_sters` (aceeași ștergere, fără efect a doua oară). |
| Pagina lui în aplicație | HTML propriu (fără `wp_head`): fără analiză, pixel sau bara de cookie-uri, fără Google Fonts, fără cărți (`spp-carte`, scoase și din date). Linkul spre politica de confidențialitate deschide zona „politici”, unde bara de sus spune „Înapoi la ciclul ei” (spre `/pentru-el/`) când e intrat el și nu ea. |

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
| `POST /push` | `{token, platforma: ios\|android, versiune, disp}` → leagă tokenul FCM de adresă (un rând per token, același `disp` = token vechi șters, cel mult 10 pe adresă, 30/oră). Tokenul APNs brut (64 hex) e refuzat cu `motiv: apns`. Dacă tokenul era al partenerului (`wp_ah_spp_push`), pleacă de acolo: un telefon = un cont. |
| `DELETE /push` | `{token}` → scos. Nu cere contul (merge și după ieșire): cine are tokenul e telefonul acela. |
| `GET /descarca?id=` | acces verificat cu `ah_med_acces()` + `ah_med_poate()` (51) → `{url, expira, titlu, durata, imagine, ext}`; `url` = `/fisier`, semnat HMAC, 15 minute. 60/oră. |
| `GET /fisier?id&exp&c&s` | fără cookie (îl cere FileTransfer); semnătura `HMAC(id\|exp\|cine)`, `cine` = amprenta adresei (adresa nu apare în link). Fișierul din `ah_med_cale()` cu `Range` (206/416), sau 302 spre URL-ul extern al sunetului. |
| `POST /sterge-cont` | `{confirmare: true}` → șterge: ciclul din cont (`ah_sp_ciclu`, ca `ciclu-sterge`), emailurile fazelor (`ah_ciclu`, 41), intrarea din `ah_sp_conturi` (281), vizitele (`ah_sp_vizite`), tokenurile push, telefoanele/minutele/ascultările Inner Temple (`ah_med_d_/min_/asc_`), cookie-urile `ah_sp`, `ah_med`, `ah_ct_*`, `ah_sc*`; pe comenzile ei (`wc_get_orders` după `billing_email`) mementourile sălii `_ah_sc_ritm` și `_ah_sc_repeta` → `activ=false`, `sms=false`, `telefon=''`, cu `ah_sc_ritm_index`/`ah_sc_repeta_index` și o notă pe comandă; înscrierea ei din `ah_respira_inscrieri` (220) ștearsă; partenerii invitați de ea (`ah-sp-partener.php`: rândurile și tokenurile lor push, `pentru_el` în email). Comenzile și facturile rămân. Email echipei (cu ce s-a șters, inclusiv `sala_ritm`/`sala_repeta`/`respira_inscriere`, și ce e de verificat de mână: EmailOctopus). Cârlig: `ah_spa_cont_sters`. |
| `GET /config` | `{versiune_minima:{ios,android}, mesaj, magazine:{ios,android}, pagina}` (public, 5 minute cache). |

## Notificările push

- **Meditație nouă**: `transition_post_status` pe `ah_meditatie` → după 2 minute, lucrare către cei care o pot asculta (`ah_med_poate`). O singură dată (`_ah_spa_anuntat`).
- **Înregistrare „Respiră-te acasă”**: `update_option_ah_respira_inregistrari` + cronul la 5 minute (`ah_spa_tick`) → către înscrisele în ziua aceea (`ah_respira_inreg_destinatare`). Nu și dacă mai e mai puțin de o oră din cele 24, nici pentru înregistrări publicate înainte de pornirea fragmentului.
- **Întâlnire în 15 minute**: `ah_respira_start()` (220) → către înscrisele în ziua aceea. Alte module: filtrul `ah_spa_intalniri`.
- `data.url` deschide în aplicație secțiunea potrivită. Jurnal în Setări (fără adrese de email).

## Puntea `window.AHApp` (JavaScript, doar în aplicație)

- **Linkuri**: `App.appUrlOpen` și `App.getLaunchUrl()` → dacă e o adresă permisă de pe site, se deschide (linkul personal `?cont=` intră direct în cont). Atingerea unei notificări (push sau locală) deschide `url`.
- **Push**, tipul tokenului: puntea cere notificări doar dacă `AhApp.info()` are `push: true` și `token: 'fcm'`; la `registration`, un token APNs brut (64 hexa) nu se trimite serverului. Platforma trimisă e cea din Capacitor (`getPlatform()`).
- **Push**: întrebarea apare **o singură dată**, din a doua deschidere a aplicației cu contul intrat, pe Acasă, cu un ecran de explicație („Vrei să-ți dăm de veste?”). „Nu acum” nu mai întreabă; se poate porni din Mai mult › Aplicația › Notificări. Tokenul se retrimite la schimbarea contului sau o dată pe săptămână.
- **Ciclul**: citește `localStorage.ahsp_ciclu` + `ah_ciclu_v1` cu **exact** `CI.d`, `CI.lung`, `CI.faze` din 235 (copiate; testul le compară cu originalul); notificări locale la 9:00 la începutul fiecărei faze, pe următoarele 3 cicluri, id-uri 41000–41099, reprogramate la orice schimbare (prinsă din `localStorage.setItem`). Nimic pentru `hormonal`/`sarcina`, înainte de prima zi sau când menstruația întârzie. Comutator în Mai mult. Scrie `Preferences.ah_ciclu = {starturi, lung, men, sit, cine, actualizat}` (`cine` = amprenta contului).
- **Refacerea ciclului** (iOS poate goli `localStorage`): pe Spațiul meu, cu contul intrat, dacă `ahsp_ciclu`/`ah_ciclu_v1` n-au date dar `Preferences.ah_ciclu` e al aceluiași cont (`cine`), îl pune înapoi în `localStorage.ahsp_ciclu`; `#ahsp` stă ascuns (`html.ahspa-refac`, cel mult 1,5 s) până răspunde Preferences, iar dacă 235 desenase deja, redesenează (`hashchange`). Copia și mementourile **nu** se șterg când `localStorage` e gol; se șterg doar la: „Șterge datele de pe acest telefon” (`removeItem('ahsp_ciclu')` pe o cheie existentă: pe loc, nu după 900 ms), ieșirea din cont, „Șterge contul”. Copia altui cont: nu se reface, se șterge.
- **Descărcări**: „Descarcă” / „Se descarcă… %” / „Descărcat” + „Șterge descărcarea” lângă fiecare meditație Inner Temple pe care o poate asculta. Fișierele: `meditatii/<id>.<ext>` și `meditatii/<id>.jpg` în `LIBRARY_NO_CLOUD` (iOS: Library fără copie iCloud, cerința Apple pentru conținut redescărcabil) sau `DATA` (Android). Index: `Preferences.ah_descarcate = [{id,titlu,durata,fisier,poza,dir,descarcat}]` — `dir` e directorul de folosit la citire.
- **Ecranul blocat**: Media Session (titlu, „Adela Haru”, poza, play/pauză, ±15 s, poziție) pe orice sunet pornit (playerul Inner Temple, sunetele din sală).
- **Mai mult › Aplicația**: Notificări, Mementourile ciclului, Meditații descărcate (șterge tot), **Șterge contul** (bifă de confirmare; apoi șterge descărcările, Preferences, mementourile și datele `ahsp_*` de pe telefon), versiunea. Se alătură secțiunii „Aplicația” a lui 266, dacă există.
- **Ieșirea din cont**: înainte de „Ieși din cont” scoate tokenul push, descărcările și mementourile. O pagină fără cont (cookie expirat) face aceeași curățenie.
- **Al cui e tokenul** (`PUSH.al`): `Preferences.ah_push = {token, cont:'ea'|'el', cine, la}`. Puntea scrie `cont:'ea'`; pagina lui `/pentru-el/` scrie `cont:'el'`. `PUSH.opreste()` scoate doar un token al ei (+ `unregister()`); un token al lui rămâne cât e intrat el (`AH_SPA.el`), altfel pleacă prin `DELETE /ah-spp/v1/push` (`AH_SPA.api_el`). Când ea e intrată, tokenul telefonului trece la ea (contul ei e cel activ pe telefon).
- **Versiunea minimă**: sub ea, o bandă cu mesajul și „Actualizează” (linkul magazinului).

## Teste

```
bash server/tests/ruleaza.sh
```

Rulează tot: verifică întâi că `server/tests/date/v2/` (bucățile din 235/41 și pagina publică, pentru CI, unde
`reference/` lipsește; `php server/tests/extrage-referinte.php` le reface) e la zi cu `reference/v2/`, apoi testele de mai jos, apoi `server/tests/partener/ruleaza.sh` (contul pentru el: `docs/partener.md`) și
`php server/tests/integrare/run.php`. Harnașul vizual de aici șterge la pornire doar pozele lui (`browser-*`, `aplicatie-*`),
nu și `partener-*` sau `offline-*`.

- `php server/tests/run.php` — 526 de verificări (în plus față de 322: contul de recenzie, punctul 9 din `docs/review-account.md`; urmărirea pe pagina publică salvată `reference/v2/spatiul-meu-public.html` + un pixel Meta clasic; coada de scripturi; filtrele pluginurilor; Conversions API; `ob_start` doar în aplicație; Vimeo `dnt=1`; podcastul), WordPress imitat (`tests/stubs.php`, `$wpdb` = SQLite în memorie): recunoașterea, lista permisă (36 de adrese, în aplicație și în browser), zona politici, portalurile REST, curățarea datelor (pe `tests/date/spatiul-meu.json`) și a ieșirii shortcode-ului, „nimic în browser”, semnăturile și `Range`, `descarca`/`fisier`, validarea și tabelul push, ștergerea contului (ce pleacă, mementourile sălii, Respiră-te acasă, ce rămâne, datele altor cliente), loturile push cu cheie (oprire la mijloc, bugetul de timp, cel mult o dublură), AASA/assetlinks, setările, declanșatorii, JWT-ul FCM (RS256 verificat).
- `node server/tests/ciclu.test.cjs` — ~50.000 de comparații între calculul ciclului din punte și `CI` citit direct din `reference/v2/sn235.php`: fazele pentru ciclu 21–45 × menstruație 2–10, `CI.d` și `CI.lung` pe 3.000 de istorii aleatoare (cu date stricate și limite depășite), mementourile pe 3 cicluri.
- `node server/tests/vizual/poze.cjs` — harnașul vizual: Spațiul meu (CSS/JS din 235 și 266) , o sală de curs de probă (cu „Mai departe”, 214 și calendarul .ics) și Termeni și condiții (antetul și subsolul temei din pagina publică), în Chromium 390×844, browser vs aplicație (cu `window.Capacitor` imitat), totul servit local ca `https://www.adelaharu.ro` (nimic nu pleacă pe internet). 163 de verificări (scenariul 7: contul pentru el pe același telefon: tokenul lui trece la ea când ea e intrată, ieșirea ei nu-l atinge, o pagină fără cont îl scoate de la el, bara „Înapoi la ciclul ei” pe politici; paginile au și urmărirea de pe site, din pagina publică salvată: în browser se cer GA/pixel/Complianz/atribuirea, în aplicație nimic; Vimeo cu `dnt=1` din server și pentru iframe-urile adăugate târziu; podcastul fără playerul Apple; tokenul APNs nu ajunge la server; refacerea ciclului după golirea `localStorage`, ștergerea cerută care nu revine, copia altui cont; niciun text/link/element de cumpărare vizibil în aplicație, pe fiecare secțiune; martorii din browser; mementourile = 235; descărcarea; Media Session; push; linkurile; ieșirea; ștergerea contului). Pozele: `docs/screenshots-v2/` (`browser-*` și `aplicatie-*`). În pozele „full page”, bara de jos (fixă) apare la mijlocul paginii: e doar efectul capturii.
- `bash server/tests/partener/ruleaza.sh` — contul pentru el (`docs/partener.md`): `run.php` 265 (1.1.0: întârzierea = pauză neutră, „Trimite din nou” deconectează sesiunile, „Trimite-mi linkul” fără anularea linkului vechi și cu timp egal), fazele față de 235 (73.273 de comparații), harnașul vizual 88 de verificări (plus linkul lipit la „Sunt partenerul ei”) (în plus: tokenul lui la `ah-spp/v1`, `Preferences.ah_push.cont`, „Ieși” scoate doar tokenul lui, `unregister()` doar pe tokenul lui, atingerea notificării lui; fără notificări pentru el fără Firebase, cu token APNs sau cu ea intrată pe telefon).
- `php server/tests/integrare/run.php` — 51 de verificări cu **ambele fragmente încărcate** (stubs-ul partenerului, cu `do_action`/`apply_filters` care rulează cârligele): `/pentru-el/` pe lista permisă și în `S.permise`, cărțile tot blocate, AASA = căile din `AndroidManifest.xml` (același șir), Associated Domains, `allowNavigation`; 302 `/spatiul-meu/` → `/pentru-el/` doar fără ea și doar în aplicație; `AH_SPA.el`; tokenul push mutat ea ↔ el, 409 `cont_ea`, APNs refuzat, „Ieși” de fiecare parte; `sterge-cont` cu `pentru_el` și tokenurile lor, altă femeie neatinsă; pagina lui în aplicație fără cărți / Google / urmărire / puntea ei.

## Ce rămâne de făcut de oameni

- **Adela**: textele marcate CIORNĂ (explicația notificărilor, mementourile fazelor, ștergerea contului, `/app/intra`, notificările push, banda de actualizare).
- **Mircea**: OK pentru urcare (inactiv, cu copie); LiteSpeed (pasul 5); cheia Firebase; Team ID și amprentele după crearea aplicațiilor în magazine.
- **Proprietara**: contul de recenzie (adresa, comenzile manuale, codul în Setări și în magazine), `docs/review-account.md`.
- **De verificat pe telefon, contul pentru el**: linkul `/pentru-el/?t=` din email deschide aplicația (iOS și Android, după AASA / `assetlinks.json` cu `/pentru-el`), și la pornirea rece; notificarea lui ajunge și se deschide pe Azi; pagina lui cu `viewport-fit=cover` și `contentInset: 'automatic'` (bara de jos și capul, fără spațiu dublu sau tăiat pe iPhone cu notch).
- **De verificat pe telefon** (Web Inspector / `chrome://inspect`, cu fragmentul activ): nicio cerere spre `googletagmanager.com`, `google-analytics.com`, `connect.facebook.net`, `facebook.com/tr`, `clarity.ms`; nicio bară de cookie-uri; iframe-urile Vimeo cu `dnt=1`; intrarea cu codul fix.
- Juridic (Mircea): confirmarea că bara poate lipsi în aplicație (raționamentul de mai sus). Pe site nu se schimbă nimic.
- Rămân în sală, ca stare, fraze ca „Abonamentul îl oprești oricând” (224, fără link). De văzut cu Adela dacă se rescriu.
