# Serverul aplicației mobile „Adela Haru”

Un singur snippet nou, `ah-app-api.php`, pentru Code Snippets. Doar adaugă: un namespace REST nou
(`ah-app/v1`), trei tabele noi, opțiuni noi `ah_app_*`. Nu modifică niciun snippet existent; le
folosește funcțiile (fiecare apel e păzit cu `function_exists`, deci fără ele merge cu mai puțin, nu cade).

Contractul: [`../docs/api-contract.md`](../docs/api-contract.md).

## Cum se instalează

1. **Code Snippets › Snippets › Add New › PHP.** Lipește tot ce este **sub** linia `<?php`,
   alege „Run everywhere”, „Save Changes and Activate”.
2. Deschide o dată orice pagină a site-ului: la primul `init` se creează tabelele
   `wp_ah_app_tokenuri`, `wp_ah_app_push` și `wp_ah_app_coduri` (dbDelta; versiunea în opțiunea
   `ah_app_tabel`, acum `2`). Tot atunci se programează cronurile `ah_app_orar` (din oră în oră) și
   `ah_app_zilnic` (o dată pe zi: curățenia ciclului și a codurilor vechi).
3. **Meditațiile mele › Aplicația mobilă** (dacă snippetul 51 nu e activ: WooCommerce › Aplicația mobilă).
   Doar administratorii (`manage_options`). Completează:

   | Câmp | Opțiunea | Unde se folosește |
   |---|---|---|
   | iOS Team ID | `ah_app_ios_team` | `/.well-known/apple-app-site-association` (`TEAMID.ro.adelaharu.app`) |
   | Android SHA-256 (una sau două amprente) | `ah_app_android_sha256` | `/.well-known/assetlinks.json` (pune și cheia de semnare Google Play, și cheia de încărcare) |
   | Linkul din App Store / Google Play | `ah_app_link_ios`, `ah_app_link_android` | pagina `/app/intra`, pentru cine nu are aplicația |
   | Versiunea minimă | `ah_app_versiune_minima` | `GET /config` |
   | Anunț (titlu + text) | `ah_app_mesaj` | `GET /config` → `mesaj` (gol = `null`) |
   | Cheia Firebase (JSON-ul contului de serviciu) | `ah_app_fcm` | notificările push. **Fără ea nu pleacă nimic**, doar se scrie în jurnal |

   Tot acolo: cauți o adresă (câte telefoane are, câte sunt abonate la notificări), „Golește telefoanele”,
   „Trimite o notificare de probă”, și jurnalul (`ah_app_jurnal`, ultimele 200 de rânduri).

4. **Serverul (o singură dată, de verificat pe găzduire):**
   - **Authorization.** Unele servere (LiteSpeed/Apache cu CGI) taie headerul `Authorization`. Dacă
     `GET /eu` dă mereu 401 cu un token bun, pune în `.htaccess`, deasupra blocului WordPress:
     `SetEnvIf Authorization "(.*)" HTTP_AUTHORIZATION=$1`.
     Ca rezervă, snippetul primește tokenul și în headerul `X-Ah-Token`.
   - **LiteSpeed Cache.** Snippetul trimite `Cache-Control: no-store`, `X-LiteSpeed-Cache-Control: no-cache`
     și `do_action('litespeed_control_set_nocache')`. Pentru siguranță, la LiteSpeed Cache › Excludes ›
     „Do Not Cache URIs” adaugă `/wp-json/ah-app/`, `/app/` și la „Do Not Cache Query Strings” `ah_app_media`.
   - **`.well-known`.** Dacă există pe server un folder real `/.well-known/` (de la certificatul SSL),
     cererile spre `apple-app-site-association` și `assetlinks.json` trebuie să ajungă totuși la WordPress
     (fișierele nu există, deci regula WordPress le trimite la `index.php`). De verificat cu
     `curl -I https://www.adelaharu.ro/.well-known/assetlinks.json` (trebuie `200` și `application/json`).
     Apple cere fără redirect: `https://www.adelaharu.ro/...` direct, iar aplicația trebuie să declare
     domeniul `applinks:www.adelaharu.ro`.
   - Pluginuri care blochează REST API-ul pentru vizitatori nelogați trebuie să lase liber `ah-app/v1`.

## Testele

```
php -l server/ah-app-api.php
php server/tests/run.php      # 236 de verificări, ies cu 1 dacă pică ceva (cere pdo_sqlite)
```

`tests/stubs.php` înlocuiește funcțiile WordPress folosite (inclusiv un `$wpdb` în memorie; tabelul codurilor
merge într-un SQLite adevărat, ca interogarea atomică a încercărilor să ruleze ca SQL). Testele acoperă:
tokenurile (32 de octeți, doar sha256 pe server, Bearer), limita de 5 telefoane și reînnoirea pe același id,
codul de 6 cifre (5 greșeli ⇒ anulat, o singură folosire, 15 minute, 10 greșeli / adresă / 24 h peste coduri
noi), limitele (5/oră/email, 20/oră/IP), duratele lecțiilor (fiecare durată scrisă în `reference/sn44.php` dă
secunde: „29 de minute”, „cam 2 ore și 40 de minute”, „cam o oră și 45 de minute”), curățenia ciclului după
365 de zile, notificările în loturi (cursorul, reîncercarea, accesul o dată pe adresă),
semnarea și verificarea linkurilor (media, lecții, player; respinse la orice câmp schimbat, expirate sau
prea departe în viitor), validarea ciclului, **limitele fazelor comparate cu `ah_ciclu_limite()` din
`reference/sn41.php` pentru toate ciclurile 21–45 × 2–10**, cheile lecțiilor comparate cu funcțiile adevărate
din `reference/sn44.php`, „pe rând”, modulele, `Range`, AASA/assetlinks, forma erorilor, JWT-ul FCM
(semnătura RS256 verificată cu cheia publică) și fluxul `/auth/cod` → `/auth/verifica` → `/auth/iesire`.
Nu e testat pe un WordPress adevărat (nu am voie să scriu pe site): vezi „De verificat pe staging”.

## Unde citește și unde scrie fiecare rută

| Ruta | Citește | Scrie |
|---|---|---|
| `POST /auth/cod` | `ah_med_acces` (doar prenumele), `wp_ah_app_coduri` (blocată 24 h?) | `wp_ah_app_coduri` (hmac al codului, `n`=0), limite `ah_app_l_*`; emailul prin `ah_med_trimite_email` (rama din 51) |
| `POST /auth/verifica` | `wp_ah_app_coduri` | întâi `UPDATE … SET n=n+1 … WHERE n<5 AND (zi_n<10 …)` (atomic), apoi compară; `wp_ah_app_tokenuri` (sha256 al tokenului) |
| `POST /auth/iesire` | | șterge tokenul și tokenurile push legate de el |
| `DELETE /cont` | | șterge: tokenurile, push, codul (`wp_ah_app_coduri`), rândul din `wp_ah_ciclu` (= oprirea emailurilor fazelor), `ah_app_ciclu_*`, pozițiile `ah_app_poz_*`. Comenzile și facturile rămân |
| `GET /eu` | `ah_med_acces`, comenzile cu `_ah_it_plan`/`_ah_it_abonament` (154), Stripe `GET subscriptions/{id}` prin `ah_it_api` (ținut minte 6 ore), cursurile ca `ah_cm_cursuri_pentru` (163) | |
| `GET /meditatii` | `ah_med_lista`, `ah_med_poate`, `ah_med_m`, `ah_med_durata`, `ah_med_vimeo` | |
| `GET /meditatii/{id}/redare` | `ah_med_poate` | |
| `POST /meditatii/{id}/pozitie` | | opțiunea `ah_app_poz_<md5>`; la `terminat:true` `ah_med_adauga_minute` (durata rotunjită, ca în 51) |
| `GET /cursuri`, `/cursuri/{slug}` | `ah_sc_din_comanda`, `ah_sc_platita`, `ah_sc_expirat`, `ah_sc_sfarsit`, `ah_sc_bife_citeste`, `ah_sc_cheie_lectie`, `ah_sc_revine`, `ah_sc_echipa`, `ah_live_setari[456]` (programul Wild Love) | |
| `POST /cursuri/{slug}/progres` | `ah_sc_harta_lectii`, `ah_sc_din_cheie` | `ah_sc_bife_scrie` (= `_ah_sc_bife` + `_ah_sc_progres` pe comandă, exact ca „Am terminat” din sală); la final `_ah_sc_terminat` + notă în comandă + `ah_sc_cadou` |
| `GET /ciclu/faze` | `ah_ciclu_faze`, `ah_ciclu_produse`, `ah_ciclu_texte_lungi`, `ah_ciclu_limite` | |
| `GET/PUT/DELETE /ciclu` | `wp_ah_ciclu` (41) | `wp_ah_ciclu` (același rând ca abonarea din pagină; la rând nou `ah_ciclu_trimite('bun_venit')`, ca în 41) |
| `POST/DELETE /push` | | `wp_ah_app_push` |
| `?ah_app_media=m<id>` | `ah_med_cale` | `ah_med_streameaza` (Range) |
| `?ah_app_media=l<fișier>` | `ah_sc_cale_media` (copia protejată din 44) | streaming 51/44; jurnalul ascultărilor (`AH_ASC_META`, regula de 30 de minute din 146) |
| `/app/player` | Vimeo din `_ah_med_video` sau din lecție (`ah_sc_vimeo`) | jurnalul ascultărilor (146) |

## Ce am făcut altfel decât contractul / ce am adăugat

Nu am schimbat `docs/api-contract.md`. Toate diferențele sunt **câmpuri în plus** sau **detalii nespecificate**;
niciun câmp din contract nu lipsește. De hotărât de lead dacă le trece în contract (recomand: `lectie.deschisa`).

- **Lecțiile au `deschisa` (bool).** Sala are „pe rând” (`pe_rand`): o lecție se deschide după ce o termini
  pe cea dinainte (ca `liber()` din JavaScript-ul sălii; echipa și cursantele care revin văd tot). La o lecție
  închisă, `player`, `fisier`, `pdf` sunt `null`. `modul.deschis` = are măcar o lecție deschisă.
  `POST /progres` cu `terminata:true` pe o lecție închisă ⇒ `403 fara_acces`.
- **Câmpuri în plus la lecție:** `nr` („Modulul 1 · Practică”), `durata_text` (sala are durata ca text: „cam 2 ore
  și jumătate”; `durata` în secunde e citită din el, sau `null`), `audio_titlu`, `pdf` (caietul, când `fisier`
  e deja audio/video), `atasamente` (`[{titlu,url}]`, din `pdfuri`).
  `fisier` = audio dacă există, altfel mp4, altfel PDF. `text` = textul de citit din curs (`citeste`/`texte`) +
  nota + întrebările de jurnal, HTML simplu (`h4`, `p`, `ul/li`), escapat.
- **La curs:** `cum` (lista „cum parcurgi”), `pana_la`, `progres`, `pe_rand`, `mai_vin` (textul sălii când
  urmează module). `descriere` = `bun_venit` din curs. `imagine` = poza produsului WooCommerce.
- **Module în sală = `grup`.** În 44 fiecare element din `module` e o lecție; le adun după `grup`, ca sala
  (`grup` 0 sau lipsă = lecție singură). `id` = `m<grup>` sau `l<număr>`, titlul = prima lecție a grupului.
- **`se_deschide_la`** există doar pentru Wild Love (singurul curs cu program: `ah_live_setari[456]['sesiuni']`,
  +2 ore după întâlnirea live, fără ele la modulele „înregistrate”). Modulele viitoare apar închise, cu titlurile
  lecțiilor, fără fișiere. Celelalte cursuri nu au eliberare pe săptămâni pe server.
- **`POST /progres` răspunde** `{"ok":true,"progres":0.4,"module_terminate":2,"terminate":["<chei>"]}`.
- **Linkurile semnate** au parametri în plus: `t` (id-ul rândului de token, nu tokenul), iar la lecții `c`
  (comanda), `s` (slug), `k` (mp3/mp4/pdf); la player `m` (meditația) sau `c`,`s`,`l` (cheia lecției).
  Semnătura HMAC-SHA256 (cheia din `wp_salt('auth')`) acoperă tot, inclusiv emailul. Linkul se oprește
  și când tokenul e scos (ieșire, ștergerea contului, „Golește telefoanele”).
- **Fișierele externe** (de ex. un mp3 de pe alt domeniu, cum face sala) vin direct, nesemnate, ca în sală.
- **`GET /meditatii`:** în plus `are_audio` (unele meditații sunt doar video). `categorie` = slugul
  categoriei text din 51 („Respirație” ⇒ `respiratie`), iar `categorii[].nume` e textul original.
  `poza` e `null` dacă lipsește. Cine nu are nimic cumpărat vede doar gratuitele.
- **`POST /pozitie`:** în 51 poziția e doar în browser (localStorage), deci nu se poate sincroniza cu aplicația
  web. O țin într-o opțiune pe adresă (`ah_app_poz_<md5>`, cel mult 400 de meditații). Minutele se adaugă
  ca în 51: la final, durata rotunjită (nu din secunde parțiale). Răspuns `{"ok":true}` (+ `minute` dacă s-au adăugat).
- **Ciclul fără emailuri.** În 41 un rând din `wp_ah_ciclu` ÎNSEAMNĂ abonarea la emailuri (nu există rând fără
  emailuri). Deci: `emailuri_faze:true` ⇒ rândul din 41 (aceeași abonare, același email de bun venit);
  `emailuri_faze:false` ⇒ rândul din 41 se șterge (ca linkul de oprire) și datele stau într-o opțiune
  `ah_app_ciclu_<md5>`, doar pentru sincronizare. Niciodată în ambele. `PUT` răspunde cu obiectul salvat.
  **Aceeași păstrare ca în 41:** opțiunea are `actualizat`, iar cronul zilnic `ah_app_zilnic` șterge orice
  `ah_app_ciclu_<md5>` neactualizat de 365 de zile (cum face `ah_ciclu_trimite_zilnic` cu rândurile din
  `wp_ah_ciclu`). Opțiunile scrise înainte de câmpul `actualizat` folosesc data acordului (rescrisă la fiecare salvare).
- **Prima zi mai veche de 120 de zile** (limita din 41) nu e respinsă: o mut înainte cu cicluri întregi
  (faza de azi iese aceeași). Datele din viitor, ciclul în afara 21–45 sau sângerarea în afara 2–10 ⇒ `400 invalid`.
- **`/ciclu/faze`:** `slug` = anotimpul (`iarna`, `primavara`, `vara`, `toamna`), ca în exemplul din contract; în
  plus `cheie` (cheia din 41: `menstruala`…), `nume`, `luna`, `potrivit_lista`, `practica`, `text_lung` (textul din
  „Spre feminitate”), `zile_la_28_5`. `meditatie_id` = meditația legată de produsul din `ah_ciclu_produse()`,
  altfel `curs_slug` dacă produsul e un curs din sală, altfel ambele `null`. Fără produse, fără prețuri.
  `regula` descrie formula; `docs/ciclu-algoritm.md` nu e scris de mine (nu e în directorul meu).
- **`/auth/cod` cu o adresă greșită** ⇒ `400 invalid` (contractul spune „mereu ok”, dar asta e despre existența
  adresei, nu despre formatul ei). Am pus și o limită de 40 de verificări / oră / IP la `/auth/verifica`.
- **Push:** `subiecte` lipsă ⇒ ambele pornite. Același token FCM trimis de alt cont se mută pe contul nou.
  „Modul nou deschis” se află din oră în oră (cron `ah_app_orar`): compară grupurile vizibile din
  `ah_sc_cursuri()` cu ce știa (`ah_app_grupuri_vazute`); prima rulare doar ține minte. Merge pentru orice curs
  în care apar lecții noi (programul Wild Love, lecțiile puse din Vimeo de snippetul 106).
  Meditația nouă: la trecerea în „Publicat”, după 2 minute, o singură dată (`_ah_app_push` pe meditație),
  doar celor care au acces la ea.
  **Trimiterea e în loturi** (`ah_app_push_lot`): fiecare trimitere stă într-o opțiune `ah_app_pj_*` cu un
  cursor (ultimul id push trimis); fiecare lot e un eveniment cron separat cu 100 de telefoane, accesul se
  calculează o dată pe adresă în lot, cursorul se scrie după fiecare telefon. La începutul lotului se programează
  o reîncercare peste 15 minute, scoasă când lotul ajunge la capăt; dacă lotul cade (timeout, memorie), reîncercarea
  îl reia de la cursor (după 3 căderi la rând, trimiterea se oprește, cu jurnal). Nu mai există tăierea la 1000.
  Marcajele `_ah_app_push` și `ah_app_grupuri_vazute` se scriu după ce trimiterea e pusă la coadă.
- **Încercările la cod** se numără atomic în `wp_ah_app_coduri` (un `UPDATE` care rezervă încercarea înainte de
  comparare), deci cererile trimise în paralel nu primesc mai mult de 5 încercări pe cod. În plus, cel mult 10
  încercări greșite pe adresă în 24 de ore, chiar dacă cere coduri noi; apoi `429 prea_multe` („mai încearcă mâine”)
  atât la `/auth/verifica`, cât și la `/auth/cod` (nu mai trimitem un cod care nu ar putea fi folosit).
- **`/app/player` nu e link universal:** în AASA `paths` începe cu `NOT /app/player*`, iar `components` cu
  `{"/": "/app/player*", "exclude": true}`, înaintea lui `/app/*`. Așa, când aplicația deschide playerul în
  browser, iOS nu îl trimite înapoi în aplicație.
- **`/app/*`** în afară de `/app/player`: aceeași pagină ca `/app/intra` (link universal deschis fără aplicație).
- **CORS** doar pentru `capacitor://localhost`, `https://localhost`, `http://localhost`, fără credentials.
  `/ciclu/faze` are `Cache-Control: public, max-age=86400`; restul `no-store`.
- **Cursul terminat din aplicație** face ce face sala: `_ah_sc_terminat`, nota în comandă și cuponul-cadou
  `ah_sc_cadou` (ca să-l găsească în sala de pe site). **Aplicația nu primește și nu arată cuponul.**

## Ce nu am putut lega de datele existente

- **Poziția în meditații** nu se sincronizează cu aplicația web `/meditatiile-mele/` (acolo e doar în browser).
- **Poziția în lecțiile sălii** (`_ah_sc_poz_k`) nu are rută în contract; nu e expusă (se poate adăuga ușor).
- **Meditațiile gratuite fără cont:** contractul cere token pentru `/meditatii`, deci fără cont nu se văd.
- **`se_reinnoieste`** cere o citire la Stripe (snippetul 154 nu ține starea abonamentului pe site). Fără 154
  sau fără Stripe ⇒ `false`. După un webhook Stripe pentru abonament, ce știam se uită.
- **`tip` Inner Temple** vine din `_ah_it_plan` (comenzile din 154); abonamentele vechi după zilele produsului
  (≥ 300 ⇒ `anual`).

## De verificat pe staging (nu am putut aici)

1. Tabelele se creează; `/auth/cod` trimite emailul în rama din 51; `/auth/verifica` dă tokenul; `GET /eu`
   merge cu Bearer (vezi nota despre `Authorization`).
2. `?ah_app_media=…` cu `Range` (`curl -r 0-99 -I …` ⇒ `206`) pentru o meditație din folderul protejat și pentru
   un fișier dintr-o lecție; redarea în fundal și descărcarea offline din aplicație.
3. **Vimeo în `/app/player`** pe telefon. Videourile au restricție de domeniu `adelaharu.ro`: pagina trimite
   `Referrer-Policy: strict-origin-when-cross-origin`, deci Vimeo vede `https://www.adelaharu.ro/`. Recomand ca
   aplicația să deschidă `/app/player` ca pagină întreagă (WebView / browser în aplicație), nu într-un iframe
   din `capacitor://localhost`. Playerul trimite `postMessage({ahApp:"vimeo",ev:"play|pause|ended",sec})`.
4. AASA și assetlinks pe domeniul real; verificarea Apple și `adb shell pm verify-app-links`. Pe iPhone:
   un link `/app/player?…` deschis din aplicație în Safari / SFSafariViewController trebuie să rămână în browser.
5. `wp_ah_app_coduri` se creează (versiunea tabelelor `2`). 10 cereri `/auth/verifica` trimise deodată cu un cod
   greșit: cel mult 4 `cod_gresit` + una `cod_expirat` consumă încercări, restul sunt `cod_expirat` fără să conteze.
6. FCM: cheia pusă, „Trimite o notificare de probă” pe un iPhone și un Android.
7. Timpul de răspuns la `/eu` și `/cursuri` pentru o cursantă cu multe comenzi (fiecare cerere citește până la
   100 de comenzi și lista de cursuri din 44, ca sala).

## De hotărât de oameni

- **Textele** (emailul cu codul, `/app/intra`, notificările, mesajele de eroare) sunt CIORNĂ, de citit de Adela.
- **Linkurile universale includ `/sala-de-curs/*`, `/meditatiile-mele/*`, `/cursurile-mele/*`** (cum cere contractul).
  Pe un telefon cu aplicația instalată, linkurile din emailurile vechi ale sălii se vor deschide în aplicație;
  aplicația trebuie să le înțeleagă (sau să le deschidă în browser), altfel cursantele rămân blocate.
- Datele de pus în admin: Team ID Apple, amprentele Android, JSON-ul Firebase, linkurile din magazine.
- Paragraful din Politica de confidențialitate despre aplicație (tokenurile telefoanelor, tokenurile push,
  ciclul păstrat pe site cu acord explicit, ștergerea din aplicație). **Păstrarea ciclului e aceeași cu sau
  fără emailuri: după 12 luni fără nicio actualizare, datele se șterg singure** (de spus la fel și în
  paragraful de confidențialitate din aplicație).
- **Echipa aplicației (Android):** `AndroidManifest.xml` are `android:pathPrefix="/app/"`, care prinde și
  `/app/player`. De schimbat în `android:pathPrefix="/app/intra"` (assetlinks nu poate exclude căi; filtrul
  de intenții decide), altfel un Custom Tab cu `/app/player` poate fi trimis înapoi în aplicație.
