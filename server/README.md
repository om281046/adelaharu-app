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
5. LiteSpeed Cache › Excludes: **Do Not Cache User Agents** += `AdelaHaruApp`, **Do Not Cache Cookies** += `ah_app`,
   `ah_spp` și `ah_sp` (2.3.0: cookie-ul contului ei, orice pagină cu el e personală), **Do Not Cache URIs** += `/pentru-el/`
   (pagina lui, `docs/partener.md`) și `/wp-json/ah-` (2.3.0: toate rutele Spațiului meu, ale extensiilor, ale lui și ale
   noastre; `/wp-json/ah-spa/v1/punte` și `/config` sunt oricum mici și se țin în cache-ul WebView-ului, nu în LiteSpeed)
   (paginile din aplicație și redirecționările ei nu trebuie să ajungă în cache-ul public; fragmentul trimite și el
   `litespeed_control_set_nocache`, dar o pagină deja în cache e servită înainte de PHP).
   Din 2.3.0, fragmentul trimite și pe **toate** rutele `/wp-json/ah-sp/v1`, `ah-spx/v1`, `ah-spp/v1`, `ah-spa/v1` (în afară
   de `punte` și `config`) `Cache-Control: no-store, private` + `X-LiteSpeed-Cache-Control: no-cache` (`rest_post_dispatch`,
   prioritatea 99), oricine le-ar fi scris (235 și 266 nu le trimit pe toate).

Oprire: dezactivezi fragmentul. Aplicația arată atunci Spațiul meu ca în browser (inclusiv ce se vinde: de evitat
cât timp aplicația e în magazine).

### Oprire completă (dezinstalare)

Code Snippets nu anunță un fragment că a fost dezactivat (nu există cârlig de dezactivare pentru un fragment), deci
fragmentul nu-și poate scoate singur cronurile. Evenimentele rămase rulează fără nimic legat de ele (nu fac nimic, dar
apar în listă). Pentru o curățenie completă, după dezactivare, cu WP-CLI (sau WP Crontrol, „Delete”):

```
wp cron event delete ah_spa_tick ah_spa_lot ah_spa_meditatie_noua
wp cron event delete ah_spp_zilnic            # doar dacă și „Pentru el” se oprește de tot
```

Ce lasă în baza de date (se pot șterge **doar** dacă aplicația nu mai e folosită deloc; ștergerea tokenurilor oprește
notificările, a secretului `ah_spa_cine_secret` face din fiecare telefon „alt cont” la o reactivare):
- tabelele `wp_ah_spa_push` (tokenurile push), `wp_ah_spa_incercari` (contoarele codului, doar amprente);
- opțiunile `ah_spa_setari`, `ah_spa_recenzie`, `ah_spa_fcm` (cheia Firebase), `ah_spa_tabel`, `ah_spa_pornit`,
  `ah_spa_jurnal`, `ah_spa_lucrari`, `ah_spa_trimis`, `ah_spa_cine_secret`, `ah_spa_cine_din`, `ah_spa_alias`,
  `ah_spa_cron_tick`, `ah_spa_cron_lot`; tranzitoriile `ah_spa_*`; meta `_ah_spa_anuntat` pe meditații.
- „Pentru el” are lista lui în `docs/partener.md`.

### Cronul (2.3.0)

`ah_spa_tick` (la 5 minute) își notează rularea în `ah_spa_cron_tick`, `ah_spa_lot` în `ah_spa_cron_lot`; cronul zilnic
al contului pentru el, în `ah_spp_ultima_rulare` (scris de `ah-sp-partener.php`). Dacă `ah_spa_tick` (sau, cu fragmentul
lui activ, `ah_spp_zilnic`) n-a rulat de **26 de ore**, administratorii văd un anunț în admin; ultimele rulări apar și în
Setări › Aplicația mobilă. Cauze obișnuite: `DISABLE_WP_CRON` fără un cron al serverului, un site fără vizite, un plugin de
securitate care blochează `wp-cron.php`. `ah_spa_tick` curăță și contoarele expirate de peste o zi (în loturi de 5000).

### „Mută contul” (2.3.0)

Setări › Aplicația mobilă › „Mută contul pe o adresă nouă” (doar administratori, cu nonce): adresa veche → adresa nouă.
Mută cheia rândului ei din `ah_sp_ciclu` (HMAC prin `ah_sp_ciclu_cheie`, datele criptate nu depind de adresă), emailurile
fazelor (41), tokenurile push, `ah_med_d_/min_/asc_` (51), vizitele (235); ce există deja pe adresa nouă nu se suprascrie
(„…_ocupat” în mesaj). Lasă un alias (`ah_spa_alias`, amprentele vechi → cea nouă, un an, fără adrese), ca telefonul ei
să nu fie luat drept „alt cont”, apoi `do_action( 'ah_spa_cont_mutat', $vechi, $nou )` (contul pentru el își mută acolo
rândurile). **Comenzile nu se mută aici** (accesul vine din ele): adresa de facturare se schimbă în WooCommerce.

## Ce face

| | |
|---|---|
| Recunoaștere | UA conține `AdelaHaruApp/<ver> (ios\|android)` **sau** cookie `ah_app=ios\|android` (pus la prima cerere cu UA, 1 an, Secure, HttpOnly, Lax). `ah_spa_e_aplicatie()`, `ah_spa_platforma()`, `ah_spa_versiune()`. |
| Lista permisă | `/spatiul-meu/`, `/sala-de-curs/`, `/cartile-mele/`, `/meditatiile-mele/`, `/app/`, `/politica-de-confidentialitate/`, `/termeni-si-conditii/`, `/politica-de-cookie-uri/`, `/wp-json/`, `/wp-content/`, `/wp-includes/` și, pe prima pagină `/`, doar cu cheile `ciclul-ei`, `ah_med_api`, `ah_sc_ics`, `ah_sc_ics_r`, `ahp`, `ah_sc_ritm_stop`, `ah_sc_repeta_stop` (partenerul, sunetul meditațiilor, calendarul și mementourile sălii 44; filtru `ah_spa_chei_radacina`). Orice altceva → 302 la `/spatiul-meu/` (fără cache). Filtru: `ah_spa_permise`. |
| Blocate și pe pagini permise | `?add-to-cart=`, `?ah_bf=` → `/spatiul-meu/`; `?mergi=abonament` (portalul Stripe) → `/spatiul-meu/#abonamente`; `/meditatiile-mele/` rămâne întâi la 235 (intrarea automată), apoi → `#meditatii`; REST `/ah-hb/v1/portal`, `/ah-it/v1/portal`, `…/abonare` → 302 la `#abonamente` (`rest_pre_dispatch`). |
| Date curățate la sursă | `do_shortcode_tag` pe `[ah_spatiul_meu]`: JSON-ul `window.AH_SP` trece prin `ah_spa_curata_date()` (tabelul de mai jos). Aceeași curățenie o face și scriptul, ca rezervă, legat de `window.AH_SP` după 266. |
| Puntea | `<head>` (prioritatea 61, după 266) pe Spațiul meu, sala de curs, Cărțile mele și politici: `<link id="ahspa-css">` și `<script id="ahspa-js" defer>` din `GET /wp-json/ah-spa/v1/punte?f=css\|js&v=<AH_SPA>-<amprenta conținutului>` (același fișier pentru toate, `public, max-age=31536000, immutable`); în pagină rămân doar `window.AH_SPA` (configurație fără email, doar o amprentă `cine`) și partea „devreme” (`ah_spa_js_devreme()`: clasa zonei, contul de pe telefon, ascunderea cât se reface ciclul), care rulează înaintea lui 235. Plus `preload` pentru Figtree 400 și Gilda Display (latin + latin-ext, filtrul `ah_spa_fonturi`). |
| Contul de pe telefon (2.2.0) | `localStorage.ahspa_cine` = amprenta contului care a scris datele paginii. Alt cont pe același telefon ⇒ partea devreme șterge `ahsp_*`, `ahspx_*`, `ahct_*`, `ah_ciclu_v1` înainte ca 235 să le citească (sau să le urce în contul nou), apoi puntea scoate `Preferences` `ah_ciclu`, `ah_ciclu_notif`, `ah_push_on`, `ah_push_intrebat`, `ah_deschideri`, anulează mementourile și șterge descărcările. Fără amprentă (prima pornire după actualizare): doar se notează. „Ieși din cont” și „Șterge contul” curăță la fel. |
| Pagina în aplicație (2.2.0) | `viewport-fit=cover` (ieșirea paginii, `ah_spa_viewport_cover`; aplicația are `ios.contentInset: 'never'`), lateralele cu `env(safe-area-inset-*)`. Pe Spațiul meu: fără marcajul antetului / subsolului / popup-urilor Elementor (`ah_spa_fara_elementor`) și fără stilurile temei (`ah_spa_scoate_tema`: dequeue + deregister Astra, Elementor frontend + kitul 18, WooCommerce, blocurile, stilurile globale, doar dacă niciun stil rămas nu depinde de ele; în sală și Cărțile mele doar WooCommerce / blocuri / globale). Harnașul compară Spațiul meu cu și fără `reference/site.css`: diferență ≤ 0,004% din pixeli (singurul lucru folosit din Astra era `body{margin:0}`, pus acum de punte). |
| REST `ah-spa/v1` | vezi mai jos; răspunsuri `Cache-Control: no-store, private` + LiteSpeed nocache. |
| Push | FCM HTTP v1 prin cron (`ah_spa_lot`): grupuri de 100 de rânduri, câte o cerere pe telefon (3 s), bugetul de 20 s verificat înaintea fiecărui telefon, cursorul (opțiunea `ah_spa_lucrari`) scris după fiecare notificare plecată: o oprire la mijloc repetă cel mult un telefon. Lacăt 120 s. Fără cheie: doar jurnal. |
| Politici | `/termeni-si-conditii/`, `/politica-de-confidentialitate/`, `/politica-de-cookie-uri/` = zona `politici` (filtru `ah_spa_politici`): stilul și curățenia rulează și aici; antetul și subsolul temei (Shop, Cursuri online, Cart, abonarea la scrisori), `#ahbq-*`, Black Friday, popup-urile Elementor ascunse; linkurile blocate din textul legal devin text (paragraful rămâne întreg); sus, bara „Înapoi în Spațiul meu” (CIORNĂ; înapoi în istoric dacă a venit din aplicație). |
| Linkuri universale | `/.well-known/apple-app-site-association` (`/spatiul-meu`, `/spatiul-meu/*`, `/app/intra`, `/app/intra/*`, `/pentru-el`, `/pentru-el/*`; aceleași căi ca App Links pe Android, verificat în `tests/integrare/run.php`), `/.well-known/assetlinks.json`. |
| `/app/intra` | Fără aplicație: pagina cu magazinele + „deschide Spațiul meu” (păstrează `?cont=`). În aplicație: 302 la `/spatiul-meu/?cont=…`. |
| Codul de intrare (235), și în browser (2.2.0) | Secțiunea 11. `rest_pre_dispatch` (prioritatea 6) pe `POST /ah-sp/v1/intra`: contor **atomic** pe adresă (normalizată ca `ah_sp_email_norm`), în tabelul `wp_ah_spa_incercari` (`INSERT IGNORE` + un `UPDATE n = n + 1`, cheia = amprentă sha1, fără adresă), mărit **înainte** ca 235 să compare: peste 5 încercări pe codul trimis (fereastra se golește la `/cod` 200, prin `rest_post_dispatch`, și la o intrare reușită) sau peste 15 greșeli pe zi ⇒ `429 {"ok":false,"mesaj":"Prea multe încercări. Cere un cod nou."}` (ca 235) și codul de acum din 235 (`ah_sp_cod_*`) se șterge. Contul de recenzie nu trece pe aici (are contoarele lui). Fără tabel: același contor prin transient. |
| Contul de recenzie | Secțiunea 9 (cerința: `docs/review-account.md`). Setări › Aplicația mobilă › „Contul de recenzie”: adresa, codul fix (6 cifre; refuzate 123456, 654321, 000000, șirurile și cifra repetată; **se păstrează doar `wp_hash_password`**, câmpul se afișează gol cu „Cod setat: da/nu”), „Doar în aplicație” (bifat implicit). Opțiunea `ah_spa_recenzie` (non-autoload). `rest_pre_dispatch` (prioritatea 4) prinde **doar** `POST /ah-sp/v1/cod` și `/ah-sp/v1/intra` cu exact adresa de recenzie (normalizată ca în 235): `/cod` → `200 {"ok":true}` fără email și fără contoarele 235 (limită proprie 30/oră/IP); `/intra` → `wp_check_password`, la succes `ah_sp_seteaza_cookie()` din 235 + golirea dispozitivelor meditațiilor (51) și ale sălii (44) **doar pentru această adresă**; contoare atomice (secțiunea 11): 5 încercări / 15 minute / IP (IPv6 pe /64), iar după 20 de coduri greșite în total contul de recenzie se **oprește** (`ah_spa_recenzie['oprit']`), echipa primește un email și adresa trece pe drumul obișnuit din 235; se pornește din nou din setări (bifă sau cod nou). Orice altă adresă, metodă sau rută: neatinsă. Fără 235: nu face nimic. Jurnal fără cod și fără adresă, IP trunchiat (/24, /48). `sterge-cont` pe adresa de recenzie: subiectul emailului are „(contul de recenzie)”. |
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
| Sala (224): „Abonamentul meu” (portal) din rândul HER BREATH; „Revino · … lei / lună” | linkul scos (rămâne „Abonament activ până pe …”); din 2.2.0 toată cutia `.ahhb-lacat` („…practicile te așteaptă”, „Abonamentul îl oprești oricând”) |
| Sala: cutiile `.ahs-cutie` rămase doar cu titlul (ex. „Accesul s-a încheiat”, după scoaterea paragrafului); „Tot în comanda ta:” | scoase; „Tot de la tine:” (CIORNĂ) |
| Sala și Cărțile mele: bara de jos `#ahspc-bara` | aceleași 5 file ca în Spațiul meu (se adaugă Inner Temple); sus, în sală, bara „Înapoi în Spațiul meu”, ca la politici |
| „Mai mult” › Datele mele: „Toate comenzile făcute cu această adresă apar aici.” | „Cursurile, cărțile și meditațiile de pe această adresă apar aici.” (CIORNĂ); iconița Abonamentelor: calendar, nu card |
| Podcast | pe copertele episoadelor: căști în loc de ▶; în foaia episodului: iPhone = „Ascultă în Apple Podcasts” (episodul) + „Ascultă pe Spotify” (emisiunea), Android = doar Spotify (și fără butonul Apple Podcasts de sus) |
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
| `GET /punte?f=js\|css&v=` | puntea și stilul ei (vezi „Puntea”), fără cont, public, cache un an cu versiunea potrivită (altfel 5 minute). |
| `POST /push` | `{token, platforma: ios\|android, versiune, disp}` → leagă tokenul FCM de adresă (un rând per token, același `disp` **pe aceeași adresă** = token vechi șters, cel mult 10 pe adresă, 30/oră). Tokenul APNs brut (64 hex) e refuzat cu `motiv: apns`. Dacă tokenul era al partenerului (`wp_ah_spp_push`), pleacă de acolo: un telefon = un cont. |
| `POST /push/sterge` | `{token}` → scos (2.3.0; puntea folosește doar asta). Nu cere contul (merge și după ieșire): cine are tokenul e telefonul acela. |
| `DELETE /push` | la fel, păstrat pentru aplicațiile de dinainte de 2.3.0 (puntea veche). |
| `GET /descarca?id=` | acces verificat cu `ah_med_acces()` + `ah_med_poate()` (51) → `{url, expira, titlu, durata, imagine, ext}`; `url` = `/fisier`, semnat HMAC, 15 minute. 60/oră. |
| `GET /fisier?id&exp&c&s` | fără cookie (îl cere FileTransfer); semnătura `HMAC(id\|exp\|cine)`, `cine` = amprenta adresei (adresa nu apare în link). Fișierul din `ah_med_cale()` cu `Range` (206/416), sau 302 spre URL-ul extern al sunetului. |
| `POST /sterge-cont` | `{confirmare: true}` → șterge: ciclul din cont (`ah_sp_ciclu`, ca `ciclu-sterge`), emailurile fazelor (`ah_ciclu`, 41), intrarea din `ah_sp_conturi` (281), vizitele (`ah_sp_vizite`), tokenurile push, telefoanele/minutele/ascultările Inner Temple (`ah_med_d_/min_/asc_`), cookie-urile `ah_sp`, `ah_med`, `ah_ct_*`, `ah_sc*`; pe comenzile ei (`wc_get_orders` după `billing_email`) mementourile sălii `_ah_sc_ritm` și `_ah_sc_repeta` → `activ=false`, `sms=false`, `telefon=''`, cu `ah_sc_ritm_index`/`ah_sc_repeta_index` și o notă pe comandă; înscrierea ei din `ah_respira_inscrieri` (220) ștearsă; partenerii invitați de ea (`ah-sp-partener.php`: rândurile și tokenurile lor push, `pentru_el` în email). Comenzile și facturile rămân. Email echipei (cu ce s-a șters, inclusiv `sala_ritm`/`sala_repeta`/`respira_inscriere`, și ce e de verificat de mână: EmailOctopus). Cârlig: `ah_spa_cont_sters`. |
| `GET /config` | `{versiune_minima:{ios,android}, mesaj, magazine:{ios,android}, pagina}` (public, 5 minute cache). Din 2.3.0 îl întreabă și pagina offline a aplicației (CORS, `r.ok`), ca să deosebească „fără internet” de „site-ul e în lucru”: trebuie să rămână public și neblocat de pluginurile de securitate. |

Cu fragmentul „Pentru el” **oprit**, `sterge-cont` șterge totuși invitații ei direct din tabelele lui
(`wp_ah_spp_parteneri` după `ea = ah_sp_ciclu_cheie(adresa)` și `wp_ah_spp_push` după `partener`), cu SQL pregătit.

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
- **Ieșirea din cont**: înainte de „Ieși din cont” scoate tokenul push, descărcările și mementourile. **Din 2.3.0, o pagină fără cont (cookie expirat) nu mai șterge nimic** de pe telefon (descărcările, copia ciclului, mementourile și tokenul ei rămân); pleacă doar un token rămas al lui. Curăță doar: ieșirea cerută, alt cont pe telefon, „Șterge contul”.
- **Amprenta contului** (`AH_SPA.cine`, 2.3.0): HMAC al adresei cu un secret creat o dată în baza de date (`ah_spa_cine_secret`, nu din sărurile WordPress: rotirea lor nu mai e „alt cont”). `AH_SPA.cine_vechi` = amprentele acceptate ca același cont: cea din săruri (60 de zile de la prima rulare 2.3.0) și aliasurile din „Mută contul” (un an). Partea „devreme” doar rescrie `ahspa_cine` pentru ele; refacerea primește și o copie `ah_ciclu` cu o amprentă veche.
- **„Șterge contul” întrerupt** (2.3.0): înainte de cerere se scrie `Preferences.ah_sterge`; după curățenie se scoate. Dacă aplicația se închide între răspuns și curățenie, la pornirea următoare puntea curăță tot (înaintea refacerii ciclului).
- **Mementourile și fusul orar** (2.3.0): semnătura programării are și `getTimezoneOffset()`, iar `App.resume` reverifică (prin coadă): după o călătorie, notificările de la 9:00 sunt pe ora locului.
- **Accesibilitate** (2.3.0, 235 neschimbat, totul din punte): foile `.ahspa-foaie` cu focus prins (Tab/Shift+Tab), Escape = „Renunț”/„Închide”, fundalul `inert`, focusul înapoi pe butonul care a deschis-o; butoanele foii „Șterge contul” lipite jos; „Mai mult” cu `<button>` (comutatoarele `role=switch`); titlul secțiunii `<h1 id="ahsp-tit" tabindex="-1">`, cu focus și `document.title` = „Secțiunea · Spațiul meu” la schimbare; eticheta mică ce repetă titlul ascunsă pe telefon; zoom 200% (bara pe 5 coloane, fără derulare laterală la 195 px, etichetele barei ascunse sub 260 px, cu `aria-label`); loc sub listă cât se vede playerul; playerul `role=region` „Player”, poziția `aria-valuetext` „0:07 din 0:30”; descărcările anunțate (`#ahspa-sr`, `role=status`), butoanele cu numele meditației; ecranul de intrare: codul fără `maxlength`, „Intră” doar cu 6 cifre („Codul are 6 cifre.”), greșeala cu `aria-invalid` și focus, 5xx = „Nu am putut verifica codul acum. Mai încearcă puțin.”, „Trimite-mi un cod nou” separat de „Altă adresă” (focus pe el după 429 / cod expirat), emailul gol cu `aria-invalid` și focus. Modul de seară urmează telefonul cât timp ea n-a ales nimic în „Aspect de seară”.
- **Rândul „Ciclul ei”** în Mai mult: `AH_SPA.el = apply_filters( 'ah_spa_are_acces_el', <cookie-ul lui pe telefon>, $email )` (filtrul îl pune `ah-sp-partener.php`); când e adevărat, un rând spre `/pentru-el/`.
- **Al cui e tokenul** (`PUSH.al`): `Preferences.ah_push = {token, cont:'ea'|'el', cine, la}`. Puntea scrie `cont:'ea'`; pagina lui `/pentru-el/` scrie `cont:'el'`. `PUSH.opreste()` scoate doar un token al ei (+ `unregister()`); un token al lui rămâne cât e intrat el (`AH_SPA.el`), altfel pleacă prin `POST /ah-spp/v1/push/sterge` cu `X-AH-SPP: 1` (2.3.0; un fragment al lui mai vechi, 404/405, primește `DELETE /ah-spp/v1/push`) (`AH_SPA.api_el`). Tokenul ei: `POST /ah-spa/v1/push/sterge`. Când ea e intrată, tokenul telefonului trece la ea (contul ei e cel activ pe telefon).
- **Versiunea minimă**: sub ea, o bandă cu mesajul și „Actualizează” (linkul magazinului).

## Teste

```
bash server/tests/ruleaza.sh
```

Rulează tot: verifică întâi că `server/tests/date/v2/` (bucățile din 235/41 și pagina publică, pentru CI, unde
`reference/` lipsește; `php server/tests/extrage-referinte.php` le reface) e la zi cu `reference/v2/`, apoi testele de mai jos, apoi `server/tests/partener/ruleaza.sh` (contul pentru el: `docs/partener.md`) și
`php server/tests/integrare/run.php`. Harnașul vizual de aici șterge la pornire doar pozele lui (`browser-*`, `aplicatie-*`),
nu și `partener-*` sau `offline-*`.

- `php server/tests/run.php` — 675 de verificări (2.3.0: amprenta din baza de date și rotirea sărurilor, rutele private fără cache, `POST /push/sterge`, contoarele fără „adrese” nevalide și curățenia lor, versiunea tabelelor doar cu coloanele, ștergerea „Pentru el” cu fragmentul lui oprit, „Mută contul”, cronul oprit; în plus față de 322: contul de recenzie, punctul 9 din `docs/review-account.md`; urmărirea pe pagina publică salvată `reference/v2/spatiul-meu-public.html` + un pixel Meta clasic; coada de scripturi; filtrele pluginurilor; Conversions API; `ob_start` doar în aplicație; Vimeo `dnt=1`; podcastul), WordPress imitat (`tests/stubs.php`, `$wpdb` = SQLite în memorie): recunoașterea, lista permisă (36 de adrese, în aplicație și în browser), zona politici, portalurile REST, curățarea datelor (pe `tests/date/spatiul-meu.json`) și a ieșirii shortcode-ului, „nimic în browser”, semnăturile și `Range`, `descarca`/`fisier`, validarea și tabelul push, ștergerea contului (ce pleacă, mementourile sălii, Respiră-te acasă, ce rămâne, datele altor cliente), loturile push cu cheie (oprire la mijloc, bugetul de timp, cel mult o dublură), AASA/assetlinks, setările, declanșatorii, JWT-ul FCM (RS256 verificat).
- `node server/tests/ciclu.test.cjs` — ~50.000 de comparații între calculul ciclului din punte și `CI` citit direct din `reference/v2/sn235.php`: fazele pentru ciclu 21–45 × menstruație 2–10, `CI.d` și `CI.lung` pe 3.000 de istorii aleatoare (cu date stricate și limite depășite), mementourile pe 3 cicluri.
- `node server/tests/vizual/poze.cjs` — harnașul vizual: Spațiul meu (CSS/JS din 235 și 266) , o sală de curs de probă (cu „Mai departe”, 214 și calendarul .ics) și Termeni și condiții (antetul și subsolul temei din pagina publică), în Chromium 390×844, browser vs aplicație (cu `window.Capacitor` imitat), totul servit local ca `https://www.adelaharu.ro` (nimic nu pleacă pe internet). 269 de verificări (2.3.0, scenariile 11–16: sărurile rotite păstrează datele ei, o pagină fără cont nu șterge nimic, „Șterge contul” întrerupt, ecranul de intrare și codul, axe-core pe toate secțiunile, foaia „Șterge contul”, ecranul de intrare și playerul fără probleme serioase/critice, focusul prins în foaie, Escape, `inert`, „Mai mult” cu butoane, zoom 200% la 195 px fără derulare laterală, modul întunecat al telefonului; axe-core vine din `app/node_modules`, deci `cd app && npm ci` înainte; scenariul 7: contul pentru el pe același telefon: tokenul lui trece la ea când ea e intrată, ieșirea ei nu-l atinge, o pagină fără cont îl scoate de la el, bara „Înapoi la ciclul ei” pe politici; paginile au și urmărirea de pe site, din pagina publică salvată: în browser se cer GA/pixel/Complianz/atribuirea, în aplicație nimic; Vimeo cu `dnt=1` din server și pentru iframe-urile adăugate târziu; podcastul fără playerul Apple; tokenul APNs nu ajunge la server; refacerea ciclului după golirea `localStorage`, ștergerea cerută care nu revine, copia altui cont; niciun text/link/element de cumpărare vizibil în aplicație, pe fiecare secțiune; martorii din browser; mementourile = 235; descărcarea; Media Session; push; linkurile; ieșirea; ștergerea contului). Pozele: `docs/screenshots-v2/` (`browser-*` și `aplicatie-*`). În pozele „full page”, bara de jos (fixă) apare la mijlocul paginii: e doar efectul capturii.
- `bash server/tests/partener/ruleaza.sh` — contul pentru el (`docs/partener.md`): `run.php` 265 (1.1.0: întârzierea = pauză neutră, „Trimite din nou” deconectează sesiunile, „Trimite-mi linkul” fără anularea linkului vechi și cu timp egal), fazele față de 235 (73.273 de comparații), harnașul vizual 88 de verificări (plus linkul lipit la „Sunt partenerul ei”) (în plus: tokenul lui la `ah-spp/v1`, `Preferences.ah_push.cont`, „Ieși” scoate doar tokenul lui, `unregister()` doar pe tokenul lui, atingerea notificării lui; fără notificări pentru el fără Firebase, cu token APNs sau cu ea intrată pe telefon).
- `php server/tests/integrare/run.php` — 51 de verificări cu **ambele fragmente încărcate** (stubs-ul partenerului, cu `do_action`/`apply_filters` care rulează cârligele): `/pentru-el/` pe lista permisă și în `S.permise`, cărțile tot blocate, AASA = căile din `AndroidManifest.xml` (același șir), Associated Domains, `allowNavigation`; 302 `/spatiul-meu/` → `/pentru-el/` doar fără ea și doar în aplicație; `AH_SPA.el`; tokenul push mutat ea ↔ el, 409 `cont_ea`, APNs refuzat, „Ieși” de fiecare parte; `sterge-cont` cu `pentru_el` și tokenurile lor, altă femeie neatinsă; pagina lui în aplicație fără cărți / Google / urmărire / puntea ei.

## Ce rămâne de făcut de oameni

- **Adela**: textele marcate CIORNĂ (explicația notificărilor, mementourile fazelor, ștergerea contului, `/app/intra`, notificările push, banda de actualizare).
- **Mircea**: OK pentru urcare (inactiv, cu copie); LiteSpeed (pasul 5, cu `ah_sp` și `/wp-json/ah-` din 2.3.0); cheia Firebase; Team ID și amprentele după crearea aplicațiilor în magazine.
- **La urcarea 2.3.0**: amprenta contului se schimbă o dată (secretul nou din baza de date); 60 de zile, telefoanele o recunosc pe cea veche. Aplicațiile deschise în acest timp își rescriu amprenta; după 60 de zile, o aplicație nedeschisă deloc în acest timp ar vedea „alt cont” (doar dacă iOS a golit și `localStorage`; altfel `ahspa_cine` e rescris la prima deschidere).
- **Proprietara**: contul de recenzie (adresa, comenzile manuale, codul în Setări și în magazine), `docs/review-account.md`.
- **De verificat pe telefon, contul pentru el**: linkul `/pentru-el/?t=` din email deschide aplicația (iOS și Android, după AASA / `assetlinks.json` cu `/pentru-el`), și la pornirea rece; notificarea lui ajunge și se deschide pe Azi; pagina lui cu `viewport-fit=cover` și `contentInset: 'automatic'` (bara de jos și capul, fără spațiu dublu sau tăiat pe iPhone cu notch).
- **De verificat pe telefon** (Web Inspector / `chrome://inspect`, cu fragmentul activ): nicio cerere spre `googletagmanager.com`, `google-analytics.com`, `connect.facebook.net`, `facebook.com/tr`, `clarity.ms`; nicio bară de cookie-uri; iframe-urile Vimeo cu `dnt=1`; intrarea cu codul fix.
- Juridic (Mircea): confirmarea că bara poate lipsi în aplicație (raționamentul de mai sus). Pe site nu se schimbă nimic.
- Rămân în sală, ca stare, fraze ca „Abonamentul îl oprești oricând” (224, fără link). De văzut cu Adela dacă se rescriu.
