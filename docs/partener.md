# „Contul pentru el” — `server/ah-sp-partener.php`

Fragment Code Snippets nou, „Spațiul meu – Pentru el”. Specificația: `docs/arhitectura.md`, ultima secțiune.
Aditiv: nu modifică 235, 266, 41 sau `ah-sp-aplicatie.php`. Nu face nimic dacă lipsesc funcțiile lui 235
(`ah_sp_email`, `ah_sp_ciclu_cheie`, `ah_sp_ciclu_rand`, `ah_sp_ciclu_decripteaza`): fiecare cârlig întreabă `ah_spp_gata()`.
Merge în browser și în aplicație. **Fără texte noi** (Mircea, 18 nov.): tot ce citește el vine de pe site — Harta ciclului (41:
`pe_scurt`, `simti`, `ajuta`, `practica`, arhetip, luna; primul paragraf al „Notei” din *Spre feminitate*), 235 (`ah_sp_partener_texte()`:
`sprijin`, citatele din *Magia Ciclului Feminin*; `ah_sp_podcast()`; frazele paginii vechi `?ciclul-ei`) — vezi lista de la sfârșit
pentru puținele rânduri funcționale care nu au echivalent pe site.

## Cum se urcă (doar cu OK-ul lui Mircea)

1. Copie de siguranță. Code Snippets › Add New › PHP, titlul „Spațiul meu – Pentru el”, tot ce e sub `<?php`, „Run everywhere”, **inactiv**.
2. **Cheia (1.3.0)**: de preferat, în `wp-config.php`: `define( 'AH_SPP_CHEIE', '…64 de caractere aleatorii…' );` (o dată, înainte de
   activare; păstrată și în copia de siguranță a lui `wp-config.php`, nu în baza de date). Fără ea, cheia se derivă din sărurile
   WordPress (`AUTH_KEY`/`SECURE_AUTH_KEY` + `_SALT`). **Schimbarea constantei sau a sărurilor** face emailurile partenerilor de
   necitit și îi deconectează pe toți (ei trebuie să-i retrimită invitația). Pe un site unde a rulat 1.2.0 (cheia în opțiunea
   `ah_spp_secret`), trecerea se face în doi pași (1.4.0): primul `init` începe o **tranziție de 30 de zile** (opțiunea
   `ah_spp_tranzitie`: subcheile cookie/stop + data de sfârșit) în care nimic nu se rescrie — rândurile vechi se citesc cu cheia veche
   (emailul, amprenta, semnul fazei), ce e nou se scrie cu cheia nouă, cookie-urile și linkurile „Oprește emailurile” vechi merg;
   la capăt, rândurile vechi se recriptează și `ah_spp_secret` + `ah_spp_tranzitie` se șterg.
   **Întoarcerea sub 1.3 (rollback) e sigură doar în cele 30 de zile de după urcarea lui 1.3/1.4**: 1.2 găsește atunci aceeași cheie
   și aceleași rânduri (doar invitațiile făcute între timp trebuie retrimise, iar un el al cărui cookie a fost semnat din nou își
   deschide încă o dată linkul din email). După 30 de zile cheia veche nu mai există: **ștergeți copia veche a fragmentului**
   (Code Snippets), ca să nu fie activată din greșeală (și-ar face altă cheie, iar emailurile lor n-ar mai putea fi citite).
   (În 1.3.0 opțiunea se ștergea pe loc, deci un rollback ar fi pierdut toate emailurile.)
3. La activare, primul `init` creează tabelele (`dbDelta`, versiunea în opțiunea `ah_spp_tabel`, acum `5`: `2` a adus `token_nou_h`, `3` `acord_v`, `4` `pauza_de_la`, `5` tokenul push unic pe (partener, token), nu pe token: indexul unic vechi `token_h` se scoate întâi, de mână, pentru că dbDelta nu șterge indexuri). Din 1.4.0 versiunea se notează doar dacă `SHOW COLUMNS` / `SHOW INDEX` arată că tabelele chiar au coloanele `token_nou_h`, `acord_v`, `pauza_de_la` și indexul `partener_token`; altfel jurnal și o nouă încercare peste o oră și cronul zilnic `ah_spp_zilnic` (8:10, ora site-ului: un lanț de evenimente unice, fiecare rulare o programează pe următoarea; un eveniment „daily” rămas de la 1.2.0 e înlocuit).
4. LiteSpeed Cache › Excludes › Do Not Cache URIs += `/pentru-el/`; Do Not Cache Cookies += `ah_spp` (lista completă, cu cele ale
   modulului aplicație: `server/README.md`, pasul 5)
   (fragmentul trimite și `litespeed_control_set_nocache` + `nocache_headers`, dar o pagină deja în cache e servită înainte de PHP).
5. Politica de confidențialitate: paragraful de mai jos.
6. Pagina `/pentru-el/` NU trebuie creată în WordPress: fragmentul o servește din `template_redirect` (și pe 404).

## Ce face

| | |
|---|---|
| Ea, în Ciclul meu | Secțiunea „Pentru el” înaintea „Setările ciclului” (script care urmărește desenările lui 235, ca 266). Dacă ciclul nu e în cont (`GET /parteneri` → `ciclu:false`), cere întâi acordul pentru asta apăsând butonul lui 235 `[data-ci-sync]` (fluxul `ciclu-date` al lui 235, `acord:1`); rezervă: același `POST ciclu-date`. Apoi formularul: prenumele lui, emailul lui, numele ei (cum o vede el, din `AH_SP.nume`) și bifa separată, obligatorie: „Sunt de acord ca [prenume] să vadă faza mea de azi și datele estimate ale fazelor următoare. Pot opri oricând.” Lista: „Invitat pe … · încă nu a intrat” / „A intrat · ultima vizită: …”, „Trimite din nou invitația”, „Oprește accesul” (confirmare într-o foaie în pagină, `role=dialog`, cu „Renunț”; nu `window.confirm`, care în aplicație arată adresa site-ului). Un singur rând de mesaj (`#spp-msg`, `role=status`), sub formular/listă, care nu se redesenează; la o greșeală câmpul primește `aria-invalid` și focusul. Toate cererile ei trimit antetul `X-AH-SPP: 1`. Cel mult 2 activi. Cu `sit` hormonal/sarcină și fără parteneri, cardul nu apare (ca în 235). |
| Generatorul vechi | Blocul `?ciclul-ei` din setări e ascuns (CSS `:has` + JS). Dacă ea are un link vechi, cardul spune „Ai și un link vechi… Oprește linkul vechi” (apasă `#ci-pa-stop` al lui 235). Linkurile vechi merg mai departe (235 neschimbat). |
| Intrarea în Spațiul meu | Sub formularul de email: „Sunt partenerul ei” → „Deschide linkul primit pe email” (explicație + un câmp „Sau lipește aici linkul copiat”, care deschide `/pentru-el/?t=` în aceeași pagină, deci în aplicație; folosit de recenzia Apple, unde linkul tastat în Safari nu deschide aplicația) + „Trimite-mi din nou linkul”. Același câmp e și pe pagina lui fără cont. |
| Pagina lui `/pentru-el/` | HTML propriu (stilul paginii `?ciclul-ei`: Gilda Display, Cormorant, Figtree, piersică, culorile fazelor din 235). Fila **Azi**: „Azi, {nume} e în” + numele fazei + arhetip · luna + „până în jur de {data}” (în toamnă: „până la următoarea menstruație”, fără dată; fără inel, fără „Ziua N din L”), o întrebare despre anunțuri, „Ce trăiește acum”, „Cum o poți sprijini”, „Ce le spune Adela femeilor, în Magia Ciclului Feminin”, apoi **„Ce urmează”**: faza următoare mare („{Faza} · {arhetip}, din jurul datei de {data}.”; pentru iarnă o fereastră: „Iarna ei interioară · {arhetip}, între {d1} și {d2}.” + primul rând din `sprijin`) și începuturile fazelor până la iarna următoare, ea inclusă ca fereastră („6–10 decembrie”), nimic după ea (doar nume + dată); la final nota („Fazele sunt o estimare: nu sunt o metodă de contracepție.” din 41 + „Corpul ei poate avea alt ritm… Ea poate opri oricând acest link.” din `?ciclul-ei`). Fila **Învață** (6 pagini: „Cele 4 faze ale ciclului feminin”, cele patru faze cu numele lor — „Iarna ei interioară”… — și „Din podcastul Intim Feminin”, episoadele 2–6 cu titlul și descrierea de pe site; dedesubt nota și, doar în browser, rândul despre cărți din `?ciclul-ei`). **Setări** (rotița: Notificări în aplicație, Emailurile fazelor, „Ieși din cont pe acest telefon” în aplicație / „Ieși din cont” în browser, „Șterge accesul meu” cu foaie de confirmare, Politica de confidențialitate). Stări: pauză (cu un card spre Învață sub mesaj), oprit, fără cont, link expirat (fără al doilea titlu „Ciclul ei”; „Deschide linkul” e butonul principal, „Trimite-mi linkul” cel secundar, ca pe ecranul ei). În browser, „Da, anunță-mă” pornește emailurile fazelor, cu rândul „Primești un email la începutul fiecărei faze.” deasupra. **Seara**: culorile închise ale „Aspectului de seară” (266) după telefon (`prefers-color-scheme`), iar alegerea ei din Spațiul meu (`localStorage.ahspx_seara`, pe același telefon) are întâietate; culorile fazelor au o variantă de seară (`c2`), contrast AA (testat). **Zone sigure**: `viewport-fit=cover`; sus/jos și stânga/dreapta (telefonul culcat) o singură dată, în `#spp`, `.bara` și foaie. Bara: sus doar la `min-width:700px` și `min-height:600px` (telefonul culcat o păstrează jos). Ținte de cel puțin 44 px pentru legături. Android: butonul Înapoi închide foaia, apoi Învață/{pagină} → Învață → Azi, Setări → Azi, din Azi aplicația trece în fundal. |
| Linkul | `/pentru-el/?t=<43 caractere base64url>` (32 de octeți aleatori; în tabel doar sha256) → cookie `ah_spp` = `id.expirare.HMAC` (1 an, HttpOnly, Secure, Lax) → 302 la `/pentru-el/`. Un token necunoscut: pagina cu „Linkul acesta nu mai e valabil” + formularul. |
| Aplicația | `/pentru-el/` e adăugată la lista permisă prin filtrul `ah_spa_permise` (deci și în `S.permise` al punții). Pe `/spatiul-meu/` fără `ah_sp`, dar cu `ah_spp` valid (și fără `?cont=`/`?intra=`) → 302 la `/pentru-el/` (aplicația pornește mereu din Spațiul meu). Pagina lui are propriul script mic: `PushNotifications` (înregistrare la cerere, atingerea notificării deschide `data.url`), `App.appUrlOpen` pentru linkurile `/pentru-el/?t=`. În aplicație: fără linkuri spre cărți (nici în date, nici în pagină; clasa `spp-carte`) și fără Google Fonts: Cormorant nu e găzduit pe site (doar Figtree și Gilda Display, prin Elementor), deci în aplicație citatele, arhetipul și „Adela Haru” din cap sunt în Figtree cursiv subțire (altfel Cormorant ar cădea pe Georgia). |

## Date (GDPR art. 9: minimizare, acord separat, ștergere)

Tabelul `wp_ah_spp_parteneri`: `ea` = `ah_sp_ciclu_cheie(emailul ei)` (fără emailul ei), `nume`, `nume_ea`, `email_c`
(emailul lui, AES-256-GCM cu cheia proprie, alta decât a ciclului; din 1.3.0 cheia nu mai stă în baza de date, vezi „Cum se urcă”, pasul 2), `email_h` (HMAC, pentru căutare),
`token_h` (sha256), `token_nou_h` (sha256 al linkului cerut de el, valabil alături de cel vechi până la prima folosire), `ses` (intră în semnătura cookie-ului: o valoare nouă deconectează toate sesiunile lui), `acord` (momentul bifei ei), `acord_v` (versiunea textului acordului pe care l-a bifat, `AH_SPP_ACORD_V` = `2026-11-18`; dovada acordului, GDPR art. 7 alin. 1; nu ajunge la el și nici în lista ei — se schimbă constanta când se schimbă textul bifei), `creat`, `invitat`, `intrat`, `vizita`, `oprit`, `email_faze`,
`faza_trimisa` (faza + o amprentă HMAC a zilei de început, ca să observăm schimbarea fără să păstrăm date), `pauza_de_la` (ziua din care el vede pauza după ce ea a trecut la hormonal/sarcină; golită când revine).

**Cheia ciclului din 235 stă încă în baza de date** (opțiunea `ah_sp_ciclu_secret`, lângă tabelul `ah_sp_ciclu`): o copie a bazei
de date ajunge pentru a decripta ciclurile lor. Nu e schimbată de acest fragment (235 rămâne neatins). Recomandare pentru Mircea:
aceeași mutare în 235 (constantă în `wp-config.php` sau derivare din săruri, cu migrarea rândurilor), cu OK-ul lui și o copie de siguranță.
Tabelul `wp_ah_spp_push`: tokenurile FCM ale lui (separate de `wp_ah_spa_push` al ei), cel mult 5 pe invitație. Din 1.4.0, dacă
același bărbat (aceeași adresă, `email_h`) e invitat de două femei, tokenul telefonului stă lângă fiecare invitație activă a lui
(unic pe `partener` + `token_h`): amândouă îl notifică, iar pagina lui spune mereu a cui e pagina deschisă (`nume_ea`). `POST /push`
îl pune pe toate (și îl scoate de la alt bărbat de pe același telefon); la prima intrare pe o invitație nouă, telefoanele lui de pe
celelalte ajung și acolo; „Oprește accesul” (ea) îl scoate doar de lângă ea; „Ieși” (`POST /push/sterge`), „Șterge accesul meu” și un
token pe care Firebase nu-l mai cunoaște îl scot de pe toate.

`GET /azi` calculează pe server din `ah_sp_ciclu` (decriptat), cu portul exact al `CI.d` / `CI.lung` / `CI.faze` / `CI.azi`
(inclusiv regulile `Number()`/`||`/`Math.round` din JS). Întoarce doar
`{nume_ea, nume, stare, faza, pana_la, urmatoarea:{faza,de_la,pana_la,sprijin,arhetip}, inceputuri[{faza,data,pana}], texte:{ce,sprijin,citate,arhetip,luna}, setari}`
— doar ce arată pagina. Din 1.2.0 au plecat `zi`, `lungime` și `harta` (le folosea doar inelul) și `azi` (nefolosit);
`inceputuri` acoperă cel mult următoarele 35 de zile (`AH_SPP_URM_ZILE`) și se oprește la iarna următoare (1.3.0).

**Ziua estimată a menstruației nu se vede (1.3.0).** Iarna următoare nu are o zi: `urmatoarea.de_la`/`pana_la` (și în `inceputuri`,
`data`/`pana`) = ziua estimată ± 2 (`AH_SPP_FEREASTRA`), arătată până la capătul ferestrei. Toamna are `pana_la: null` („până la
următoarea menstruație”). Ce vine după iarna următoare nu se arată (depinde de ziua în care începe). Așa, răspunsul e **identic** în
ziua dinainte, în ziua estimată și în zilele de după, până la capătul ferestrei (test: octet cu octet). După capătul ferestrei,
dacă ea nu a notat o menstruație nouă, el vede tot toamna, fără nicio dată a iernii, identic pentru 3…10 zile de întârziere
(`AH_SPP_INTARZIERE`); abia de la a 11-a zi după ziua estimată, pauza.
Niciodată: notițele, simptomele, `sit`, starturile notate, linkul vechi.

- `stare:'pauza'` (text neutru care nu spune de ce: „Pentru moment, aici nu e nicio estimare.”, fără al doilea rând): fără rând în
  `ah_sp_ciclu`, date nevalide, prima zi în viitor, menstruația întârzie **peste 10 zile** după ziua estimată (până atunci: toamna,
  fără date, vezi mai sus) sau `sit` hormonal/sarcină. **Situația nu ajunge la el pe loc** (altfel ar afla momentul schimbării):
  la prima verificare după schimbare (cronul de 2 minute după salvarea ei, cel zilnic sau vizita lui), serverul alege pentru
  fiecare partener ziua pauzei — granița următoare de fază (nu cea spre iarnă, care ar cădea pe ziua estimată) sau peste 1–3
  zile (HMAC al partenerului și al zilei: aceeași la fiecare cerere), care vine întâi — și o ține în `pauza_de_la`; până atunci
  vede faza, ca înainte. Revenirea ei la natural: pe loc. Răspunsul de pauză e identic pentru orice motiv (test).
  Rândul din cardul ei care o anunța despre asta a plecat odată cu textele noastre (vezi „De decis”).
  `sit` lipsă sau necunoscut = „natural”, exact ca `CI.sit()` pe ecranul ei.
- `stare:'oprit'`: accesul oprit de ea (el păstrează cookie-ul și vede, cu cuvintele paginii `?ciclul-ei`, „Linkul a fost oprit” /
  „Linkul acesta nu mai e activ. Dacă vrei să-l primești din nou, vorbește cu ea.” până la curățenie).

Ștergerea: el — „Șterge accesul meu” (pe loc, rând + tokenuri). Ea — „Oprește accesul” (pe loc; rândul pleacă după 30 de zile);
`POST /ah-sp/v1/ciclu-sterge` (prins prin `rest_request_after_callbacks`) și `ah_spa_cont_sters` șterg pe loc tot ce ține de ea;
cronul zilnic șterge și rândurile al căror rând `ah_sp_ciclu` lipsește (orice alt drum de ștergere), plus tokenurile push nevăzute de 270 de zile.

## REST `ah-spp/v1` (toate `Cache-Control: no-store, private`)

Rutele care schimbă ceva (`invita`, `opreste`, `retrimite`, `email`, `push` POST, `iesi`, `sterge`) cer antetul `X-AH-SPP: 1`,
altfel 403 `{motiv:'antet'}` (1.3.0; apărare CSRF în plus față de cookie-urile SameSite=Lax: un alt site nu-l poate trimite fără o
cerere CORS preliminară, pe care WordPress nu o aprobă cât timp antetul nu e pus în `rest_allowed_cors_headers`). Nu îl cer:
`GET /azi`, `GET /parteneri`, `POST /trimite-link` (fără cont; același răspuns oricui) și `POST /push/sterge` / `DELETE /push`
(șterg doar tokenul dat, pe care un alt site nu-l știe; le cheamă și puntea ei din `ah-sp-aplicatie.php`). Pagina lui folosește
`POST /push/sterge` (1.4.0): unele firewalluri ale gazdei (WAF) blochează DELETE; `DELETE /push` rămâne pentru compatibilitate.

| Ruta | Cine | |
|---|---|---|
| `GET /parteneri` | ea (`ah_sp`) | `{ciclu, max, parteneri:[{id,nume,email,stare:invitat\|a_intrat,vizita,creat}]}` |
| `POST /invita` | ea | `{nume, email, nume_ea, acord:true}`. Refuză: fără acord, fără ciclu în cont (409 `ciclu`), adresa ei, prenume cu linkuri/`@`, același el activ, peste 2 activi, peste 6 invitații pe zi sau 3 pe zi spre aceeași adresă. **Adresa, strict (1.4.0)**: doar litere mici și spațiile de la capete tăiate, apoi trebuie să fie `is_email()` și neschimbată de `sanitize_email()`; orice caracter în afara ASCII-ului tipăribil (diacritice, spațiu, spațiu fără întrerupere, caractere invizibile, litere chirilice) → 400 „Adresa conține caractere nepermise (diacritice sau spații).” (înainte, `sanitize_email` le scotea pe tăcute: `andrei.ștefan@gmail.com` ajungea la `andrei.tefan@gmail.com`, adresa altcuiva). Verificarea limitei de 2 și inserarea se fac sub `GET_LOCK('ah_spp_'+cheia ei, 3)` (503 `ocupat` dacă nu se ia în 3 s). |
| `POST /opreste` | ea | `{id}` — doar ale ei. |
| `POST /retrimite` | ea | `{id}` — token nou și `ses` nou (linkul vechi și toate cookie-urile lui, pe orice telefon sau browser, nu mai merg), invitația din nou; 3 pe zi. Din 1.4.0 emailul pleacă întâi, cu tokenul nou, iar rândul se schimbă doar dacă a plecat (altfel `email:false` și linkul vechi rămâne). |
| `GET /azi` | el (`ah_spp` sau `?t=`) | vezi mai sus; 401 `{stare:'iesit'}` fără cont. |
| `POST /trimite-link` | oricine | `{email}` (aceeași verificare strictă a adresei ca la `invita`) → același răspuns pentru orice adresă, în același timp (emailul pleacă după răspuns, prin `litespeed_finish_request` / `fastcgi_finish_request`; altfel orice răspuns durează cel puțin `AH_SPP_LINK_MS` = 1,5 s). Dacă e invitat activ: un link nou în `token_nou_h`; linkul pe care îl are rămâne valabil până deschide linkul nou (deci altcineva care îi știe adresa nu i-l poate strica); sesiunile deschise nu se ating. 5/oră de pe IP (429), 3/zi pe adresă (tăcut), capcană `site`. |
| `POST /push` | el | `{token, platforma}`; validarea lui `ah_spa_valideaza_push` dacă există (APNs brut refuzat); tokenul pe toate invitațiile active ale lui. |
| `POST /push/sterge`, `DELETE /push` | telefonul | `{token}` — scoate tokenul de pe toate invitațiile lui. |
| `POST /email` | el | `{on}` — emailurile fazelor. Oprire și din orice email: `/pentru-el/?fara-emailuri=<id>.<HMAC>`. |
| `POST /iesi` | el | scoate cookie-ul (pagina scoate și tokenul push). |
| `POST /sterge` | el | `{confirmare:true}`. |

## Notificări

Cron zilnic `ah_spp_zilnic` (8:10, lacăt 120 s, buget 20 s + cursor) și `ah_spp_verifica` la 2 minute după un
`POST /ah-sp/v1/ciclu-date` reușit al ei. Pentru fiecare partener activ care a intrat: dacă semnul fazei s-a schimbat și
faza nouă a început azi sau ieri → push (dacă are tokenuri) și/sau email (dacă a cerut). Prima verificare, o fază
„schimbată” din urmă (corectarea datelor) sau revenirea din pauză doar notează, fără mesaj. Semnul fazei noi se ia printr-un
UPDATE condiționat de cel vechi (două rulări simultane nu trimit de două ori); dacă nimic n-a plecat (FCM fără jeton de acces,
eroare de rețea, emailul n-a plecat), semnul vechi se pune la loc și rularea următoare reîncearcă (`reincearca`), cât timp faza a
început azi sau ieri (1.3.0; înainte notificarea se pierdea). Cronul: 8:10 ora site-ului în fiecare zi, și după schimbarea orei.
Push: refolosește `ah_spa_fcm_cont`, `ah_spa_fcm_acces`, `ah_spa_fcm_mesaj` din modulul aplicație (prin `function_exists`);
**nu** `ah_spa_trimite_push`, care șterge tokenuri invalide după `id` din tabelul ei (ar șterge rânduri greșite). Fără modulul
aplicație sau fără cheia Firebase: doar jurnal (`ah_spp_jurnal`, fără adrese). **Textul notificării e neutru (1.4.0)**, pentru că
se vede pe ecranul blocat: titlul „Ciclul ei”, corpul „Începe o fază nouă. Vezi cum o poți sprijini.” (`ah_spp_push_faza()`); faza
și sprijinul se văd doar în pagină. Pe Android mesajul are în plus `android.notification.visibility = PRIVATE` (pus peste mesajul lui
`ah_spa_fcm_mesaj`, în `ah_spp_mesaj_push()`): pe ecranul blocat apare doar că a venit o notificare. Subiectul emailului fazei e
și el neutru: „Ciclul ei: o fază nouă” (`ah_spp_subiect_faza()`); corpul emailului păstrează numele fazei („Începe Iarna ei
interioară”) și lista „Cum o poți sprijini”. Fiecare rulare a cronului zilnic notează `ah_spp_ultima_rulare` (unix time): modulul
aplicație arată un aviz în admin când e veche.

Emailuri (cadrul comun `ah_mail_trimite` / `ah_mail_p`; rezervă `wp_mail`): invitația (rândul ei = mesajul de WhatsApp din 235:
„Ți-am pregătit ceva, cu textele Adelei Haru: aici vezi în ce anotimp al ciclului sunt și cum mă poți sprijini.”), linkul nou
(după emailul cu linkurile cursurilor, 163), începutul fazei (numele, arhetipul, ce trăiește, cum o poți sprijini, nota; „Oprește
emailurile”). Toate trei numesc operatorul și politica, în `motiv` (GDPR art. 14): „Datele tale sunt prelucrate de HARU TRAINING SRL,
operatorul site-ului adelaharu.ro.” + linkul „Politica de confidențialitate” (`ah_spp_operator()`).
Anunțul către ea „a intrat” nu e făcut (ar cere păstrarea emailului ei); ea vede starea în listă.

## Integrarea cu modulul aplicație (făcută 18 nov., `server/README.md`, „Contul pentru el în aplicație”)

1. **AASA**: `'/pentru-el'`, `'/pentru-el/*'` în `ah_spa_aasa()`. **Făcut.**
2. **Android App Links**: `path="/pentru-el"`, `pathPrefix="/pentru-el/"` în `AndroidManifest.xml`. iOS: nimic în plus. **Făcut**;
   `server/tests/integrare/run.php` verifică că AASA și manifestul au aceleași căi.
3. **Lista permisă**: prin filtrul `ah_spa_permise` (verificat împreună cu modulul aplicație în testul de integrare).
4. **Ștergerea contului ei**: `ah_spa_sterge_cont()` cheamă direct `ah_spp_sterge_ale_ei()` (numărul ajunge în emailul echipei,
   `pentru_el`); cârligul `ah_spa_cont_sters` rămâne și el.
5. **Push pentru el, un telefon = un cont**: pagina lui cere notificări doar cu `AhApp.info()` `push:true` + `token:'fcm'` (ca puntea;
   fără Firebase, `register()` oprește aplicația pe Android) și doar fără ea intrată pe telefon (`cont_ea`; altfel
   `POST /push` → 409 `cont_ea`). `Preferences.ah_push = {token, cont:'el'}` (cheia punții ei, cu stăpânul notat); serverul mută
   tokenul dintr-un tabel în celălalt la fiecare `POST /push`. „Ieși” / „Șterge accesul meu” scot doar tokenul lui, `unregister()`
   doar dacă era al lui. Atingerea notificării lui: Azi, fără reîncărcarea paginii. Tokenul se retrimite o dată pe săptămână.
6. **Ieșirea ei**: puntea nu atinge tokenul lui cât e intrat el (`AH_SPA.el`); pe o pagină fără niciun cont, un token rămas al lui
   pleacă prin `POST /ah-spp/v1/push/sterge` (din 1.4.0; `DELETE /push` rămâne).
6b. **Interfețele din 1.4.0**: `do_action('ah_spa_cont_mutat', $vechi, $nou)` (ea și-a schimbat adresa contului) → rândurile ei trec de
   la `ah_sp_ciclu_cheie($vechi)` la `ah_sp_ciclu_cheie($nou)`; `apply_filters('ah_spa_are_acces_el', …)` → cu un cookie `ah_spp`
   valid, decide acesta (true doar pentru un acces activ, nu oprit), fără cookie rămâne valoarea primită; aplicația ei arată atunci
   rândul „Ciclul ei” spre `/pentru-el/`; opțiunea `ah_spp_ultima_rulare` (vezi „Notificări”).
7. **Harnașele**: `server/tests/vizual/poze.cjs` șterge doar `browser-*`/`aplicatie-*`; `server/tests/ruleaza.sh` rulează și
   `partener/ruleaza.sh` și `integrare/run.php`.
8. Notele de recenzie, etichetele și adaosul la politică: `docs/store/` (actualizate).

## Paragraf pentru Politica de confidențialitate (CIORNĂ)

> **Pagina „Pentru el”.** Dacă păstrezi ciclul în contul tău, poți invita cel mult două persoane să vadă faza ta de azi și datele
> estimate ale fazelor următoare, cu acordul tău separat. Pentru fiecare păstrăm prenumele, adresa de email (criptată), momentul
> acordului tău, data invitației, prima și ultima vizită, alegerea emailurilor și, dacă folosește aplicația, identificatorul pentru
> notificări. Persoana invitată nu vede notițele tale, cum te simți, situația ta sau zilele notate; când menstruația
> întârzie sau datele nu sunt la zi, vede doar un mesaj neutru, fără motiv. Poți opri accesul oricând din
> Ciclul meu; datele lui se șterg la 30 de zile după oprire, pe loc dacă ștergi ciclul din cont sau contul, și pe loc dacă își șterge
> el accesul din pagina lui.

## Teste

`bash server/tests/partener/ruleaza.sh`

- `php server/tests/partener/run.php` — 442 de verificări (stubs: copie a `tests/stubs.php` + funcțiile lui 235 și `ah_ciclu_faze` din 41
  citite direct din `reference/v2`): încărcare dublă, rute, criptare/GCM/cheie separată, token (format, doar sha256, rotație la
  retrimitere cu sesiune nouă; „trimite-mi linkul” nu anulează linkul vechi până la folosirea celui nou; timpul răspunsului și
  emailul trimis după răspuns), acordul (lipsă/implicit/ciclul nu e în cont), limitele (2 activi, 6/zi, 3 retrimiteri,
  5/oră IP, 3/zi adresă tăcut, capcana), cookie (semnătură, id, expirare, `\n`), minimizarea (chei exacte; fără notițe, `sit`,
  starturi, emailuri), pauza (toate situațiile, viitor, orice întârziere, identică pentru 1 și 8 zile și pentru `hormonal`, ciclu șters), oprit, push (APNs, dubluri, 5 maxim, separat
  de ea), notificările (o dată, email cu oprire, corectare din urmă, pauză, FCM 404 → token șters, programarea după `ciclu-date`),
  curățenia (30 de zile, `ciclu-sterge`, rând lipsă, `ah_spa_cont_sters`, alte femei neatinse), pagina (browser vs aplicație), redirecționarea în aplicație;
  din 1.2.0: `acord_v` păstrat la invitație (și nu în lista ei), operatorul + politica în cele trei emailuri, textele notificării,
  „oprit” cu cuvintele lui 235, Învață = 6 pagini în care fiecare paragraf, rând, citat și episod se găsește literal în 41 sau 235.
  Din 1.3.0: fereastra iernii și răspunsul identic (octet cu octet) în ziua dinainte / estimată / de după / +2, toamna fără date
  identică pentru 3–10 zile de întârziere, pauza de la a 11-a; amânarea pauzei la hormonal/sarcină (1–3 zile, deterministă, granița
  fazei, nu cea spre iarnă, golită la revenire); cheia în afara bazei de date și migrarea (rânduri recriptate, amprentă și semnul
  fazei refăcute, rând stricat lăsat, o singură dată, cookie și link de oprire vechi primite 30 de zile, apoi refuzate); antetul
  X-AH-SPP pe cele 7 rute; cronul la 8:10 peste schimbările orei (25 oct. 2026, 28 mar. 2027), fără programare dublă, „daily” înlocuit;
  notificarea reîncercată după FCM fără jeton / eroare de rețea, fără dubluri, și la două rulări simultane.
  Din 1.4.0: adresa strictă (andrei.ștefan@, spațiu în mijloc, spațiu fără întrerupere, „о” chirilic, spațiu de lățime zero → 400 cu
  mesajul clar și niciun email; spațiile de la capete și majusculele, acceptate; la fel în `/trimite-link`); tranziția cheii (cheia
  veche rămâne 30 de zile, rândurile vechi nerescrise și lizibile cu cheia veche = rollback sigur, citite de 1.4 prin cheia veche:
  lista ei, „L-ai invitat deja”, „Trimite-mi linkul”, fără notificare falsă; ce e nou cu cheia nouă; la capăt recriptare, cheia și
  subcheile șterse, cookie/link vechi refuzate; o altă cheie veche după capăt nu atinge rândurile mutate); notificarea neutră
  (titlu, corp, nimic despre fază în mesajul FCM, `visibility: PRIVATE`; subiectul emailului); `/retrimite` cu emailul căzut nu
  schimbă tokenul/sesiunea; linkul unei invitații oprite nu înlocuiește un cookie activ; invitat de două femei: tokenul pe ambele,
  push de la amândouă, oprirea uneia, `POST /push/sterge` și „Șterge accesul meu” de pe toate, alt bărbat pe același telefon; lacătul
  `/invita` (ținut → 503, eliberat după reușită și după refuz) și **o rafală reală în paralel** (6 procese `pcntl_fork`, SQLite pe
  disc, lacătul = `flock`, citirea limitei încetinită 150 ms): fără lacăt trec 6 (controlul), cu lacăt exact 2 + 4 „max”; versiunea
  tabelelor nenotată când lipsește indexul sau o coloană, reîncercare peste o oră, apoi 5; `ah_spa_cont_mutat`, `ah_spa_are_acces_el`,
  `ah_spp_ultima_rulare`.
- `node server/tests/partener/faze.test.cjs` — 12.750 de cazuri (grila 21–40 × 2–9 × fiecare zi + 6.000 de istorii aleatoare cu
  date stricate, numere ca text, zecimale, ani 0–99, luni/zile peste limită, schimbarea orei): `ah_spp_*` = `CI` din `sn235.php`, 73.273 de comparații.
- `node server/tests/partener/poze.cjs` — 197 de verificări (din 1.4.0 și: **axe-core** — `server/tests/partener/package.json`,
  instalat de `ruleaza.sh` cu `npm ci` — 0 încălcări serious/critical pe Azi, Învață, Toamna, podcast, Setări, foaia, seara, aplicație
  (Azi, Setări), pauza, oprit, fără cont, link expirat și pe cardul ei (ciclul nu e în cont, formularul, lista, doi parteneri, foaia,
  panoul „Sunt partenerul ei”); focus pe h1 (`tabindex=-1`, fără derulare) și `document.title` după fiecare schimbare de ecran;
  `<main>`, „Ce urmează” ca `ul/li`, h2 în paginile din Învață, `aria-label` la podcast și „Înapoi la Învață”, h1 „Intră în pagina
  ei” și `role=alert` la linkul expirat; zoom 200% (195 px CSS): fără derulare orizontală pe Azi, Învață, pagini, podcast, Setări,
  foaie, fără cont; foile și Setările cu butoanele unul sub altul, fiecare pe un rând; „Trimite-i din nou linkul”; adresa cu
  diacritice oprită în cele trei formulare; după o cerere căzută butonul revine activ, cu focus, `aria-busy` cât așteaptă; „Nu am putut
  salva…” după 8 s; adresa curățată de `?t=`; linkul oprit cu sesiune activă; din 1.3.0 și: foile de confirmare, mesajul persistent `role=status`, `aria-invalid` + focus, tastatura câmpurilor, ținte de 44 px, seara după telefon și după alegerea ei, cu contrast AA măsurat, toamna cu fereastra, pauza cu Învață, bara la telefonul culcat / ecran mare, butonul Înapoi pe Android, Figtree cursiv în aplicație, antetul X-AH-SPP; înainte 92 (Azi fără inel și fără „Ziua N”, „Ce urmează” cu începuturile; Învață: 6 pagini, toamna doar din textele site-ului, podcastul; plus linkul lipit, pe ecranul de intrare și pe pagina lui) în Chromium 390×844 și pozele `docs/screenshots-v2/partener-*.png` (în plus, de la integrare: push-ul lui și al cui e tokenul, fără push fără Firebase / cu APNs / cu ea intrată pe telefon).

## De decis / de verificat

- Întârzierea (1.3.0, Mircea): toamna fără date până la 10 zile după ziua estimată, apoi pauza; iarna ca fereastră ±2 zile. De confirmat cu Adela. Inelul „Ziua N din L” a plecat (Mircea, 18 nov.).
- Rândul din cardul ei pentru hormonal/sarcină („…el vede doar că estimările sunt în pauză”) nu spune că pauza vine după 1–3 zile; nu l-am schimbat (fără texte noi). Dacă trebuie, un rând ales cu Adela.
- Cheia ciclului din 235 (`ah_sp_ciclu_secret`) e încă în baza de date: recomandarea de mai sus, la „Date”.
- De verificat pe un telefon adevărat: zonele sigure (notch, bara de jos, telefonul culcat), butonul Înapoi pe Android, Figtree cursiv în aplicație, seara.
- Cardul ei nu mai spune că, la o întârziere, el vede doar un mesaj neutru (era un rând al nostru). Dacă e nevoie de el pentru
  informarea ei, e de ales un rând cu Adela.
- Linkul din email rămâne permanent (nu e de unică folosință): recenzia Apple și telefonul + laptopul lui îl deschid de mai multe ori.
  Ea îl poate anula oricând cu „Trimite din nou invitația” (deconectează și sesiunile).
- Fonturile: Figtree și Gilda din `/wp-content/uploads/elementor/google-fonts/css/*.css` (pe site există; Cormorant nu e găzduit pe site); Cormorant de la Google doar în browser, în aplicație Figtree cursiv.
- `wp_date()` pentru „azi” pe server = ora site-ului (Europe/Bucharest); telefonul ei folosește ora telefonului.

## Rânduri funcționale fără echivalent pe site (de citit de Adela)

**Noi în 1.4.0** (puține, funcționale): „Adresa conține caractere nepermise (diacritice sau spații).” (cardul ei, „Trimite-mi
linkul” la el și pe ecranul de intrare); notificarea lui „Ciclul ei” / „Începe o fază nouă. Vezi cum o poți sprijini.”; subiectul
emailului fazei „Ciclul ei: o fază nouă”; h1 „Intră în pagina ei” (el, fără cont / link expirat); „Trimite-i din nou linkul” (ea,
pentru cine a intrat deja); `aria-label` „Ascultă „{titlu}” în Apple Podcasts” și „Înapoi la Învață”; titlurile documentului
„{Azi | Învață | {pagina} | Setări} · Ciclul ei · Adela Haru”. Refolosite: „Nu am putut salva. Verifică internetul și mai încearcă.”
(din 235), „Linkul acesta nu mai e activ.” (din `?ciclul-ei`), „N-a mers. Mai încearcă o dată.”.


Tot restul (fazele, sprijinul, citatele, podcastul, nota, „oprit”, „Fă un link nou”, „Oprește linkul”, „Ieși din cont pe acest
telefon”, „Emailurile fazelor”, „Primești emailurile fazelor.”, „Păstrează-le în contul meu”, „Linkul nu pare întreg…”, „Scrie,
te rog, adresa de email întreagă.”, „N-a mers. Mai încearcă o dată.”) vine de pe site. Fără sursă au rămas doar:

- **El, pagina**: „Pentru moment, aici nu e nicio estimare.” (pauza); „Vrei să afli când începe o fază nouă?” / „Da, anunță-mă” /
  „Nu acum” / „Primești notificările fazelor.” (după „Primești emailurile fazelor.” din 41); „Linkul copiat din email”; „Pe acest
  telefon e deschis contul ei, așa că notificările ajung la ea.”; „Ștergi accesul tău la ciclul ei?” / „Accesul tău a fost șters”;
  (1.3.0) „până la următoarea menstruație”, „între {d1} și {d2}”, „Primești un email la începutul fiecărei faze.”, „Renunț” (foaia
  de confirmare), „Ieși din cont” (în browser), „Deschide linkul” (butonul, după „Deschide linkul primit pe email”);
  eticheta citatelor din Harta ciclului „Ce le spune Adela femeilor, în Spre feminitate” (după eticheta lui 235, cu altă carte).
- **Emailurile lui**: subiectele („{Ea} te invită să-i cunoști ciclul”, „Linkul tău nou: ciclul ei”); „Ai cerut pe adelaharu.ro un
  link nou spre pagina lui {ea}.” (după 163); rândurile „Primești acest mesaj pentru că…” (după 163); butoanele „Deschide pagina ei” /
  „Vezi pagina ei”; rândul despre operator, cerut de GDPR art. 14.
- **Ea, cardul**: „Ca el să vadă faza ta, ciclul tău trebuie păstrat în contul tău.”; textul bifei de acord (decizia din 18 nov.);
  confirmările și refuzurile listei (de ex. „Gata. {El} primește acum emailul cu linkul lui.”, „Am trimis din nou invitația, cu un link
  nou…”, „Ai invitat 2 persoane, cât se poate…”, rândul despre situația hormonal/sarcină); foaia „Oprești accesul lui {El}?” / „Nu mai vede nimic din ciclul tău, de acum.” (fostul `confirm`) / „Oprește accesul” / „Renunț”.
- **Intrarea**: „Sunt partenerul ei”, „Trimite-mi din nou linkul” și răspunsul lui `/trimite-link` (cu „Uită-te și în Promoții sau Spam.” din 235).
