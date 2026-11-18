# „Contul pentru el” — `server/ah-sp-partener.php`

Fragment Code Snippets nou, „Spațiul meu – Pentru el”. Specificația: `docs/arhitectura.md`, ultima secțiune.
Aditiv: nu modifică 235, 266, 41 sau `ah-sp-aplicatie.php`. Nu face nimic dacă lipsesc funcțiile lui 235
(`ah_sp_email`, `ah_sp_ciclu_cheie`, `ah_sp_ciclu_rand`, `ah_sp_ciclu_decripteaza`): fiecare cârlig întreabă `ah_spp_gata()`.
Merge în browser și în aplicație. Textele noi sunt **CIORNĂ** pentru Adela.

## Cum se urcă (doar cu OK-ul lui Mircea)

1. Copie de siguranță. Code Snippets › Add New › PHP, titlul „Spațiul meu – Pentru el”, tot ce e sub `<?php`, „Run everywhere”, **inactiv**.
2. La activare, primul `init` creează tabelele (`dbDelta`, versiunea în opțiunea `ah_spp_tabel`, acum `2`: coloana `token_nou_h`) și cronul zilnic `ah_spp_zilnic` (8:10).
3. LiteSpeed Cache › Excludes › Do Not Cache URIs += `/pentru-el/`; Do Not Cache Cookies += `ah_spp` (lista completă, cu cele ale
   modulului aplicație: `server/README.md`, pasul 5)
   (fragmentul trimite și `litespeed_control_set_nocache` + `nocache_headers`, dar o pagină deja în cache e servită înainte de PHP).
4. Politica de confidențialitate: paragraful de mai jos.
5. Pagina `/pentru-el/` NU trebuie creată în WordPress: fragmentul o servește din `template_redirect` (și pe 404).

## Ce face

| | |
|---|---|
| Ea, în Ciclul meu | Secțiunea „Pentru el” înaintea „Setările ciclului” (script care urmărește desenările lui 235, ca 266). Dacă ciclul nu e în cont (`GET /parteneri` → `ciclu:false`), cere întâi acordul pentru asta apăsând butonul lui 235 `[data-ci-sync]` (fluxul `ciclu-date` al lui 235, `acord:1`); rezervă: același `POST ciclu-date`. Apoi formularul: prenumele lui, emailul lui, numele ei (cum o vede el, din `AH_SP.nume`) și bifa separată, obligatorie: „Sunt de acord ca [prenume] să vadă faza mea de azi și datele estimate ale fazelor următoare. Pot opri oricând.” Lista: „Invitat pe … · încă nu a intrat” / „A intrat · ultima vizită: …”, „Trimite din nou invitația”, „Oprește accesul” (cu confirmare). Cel mult 2 activi. Cu `sit` hormonal/sarcină și fără parteneri, cardul nu apare (ca în 235). |
| Generatorul vechi | Blocul `?ciclul-ei` din setări e ascuns (CSS `:has` + JS). Dacă ea are un link vechi, cardul spune „Ai și un link vechi… Oprește linkul vechi” (apasă `#ci-pa-stop` al lui 235). Linkurile vechi merg mai departe (235 neschimbat). |
| Intrarea în Spațiul meu | Sub formularul de email: „Sunt partenerul ei” → „Deschide linkul primit pe email” (explicație + un câmp „Sau lipește aici linkul copiat”, care deschide `/pentru-el/?t=` în aceeași pagină, deci în aplicație; folosit de recenzia Apple, unde linkul tastat în Safari nu deschide aplicația) + „Trimite-mi din nou linkul”. Același câmp e și pe pagina lui fără cont. |
| Pagina lui `/pentru-el/` | HTML propriu (stilul paginii `?ciclul-ei`: Gilda Display, Cormorant, Figtree, piersică, culorile fazelor din 235). Fila **Azi** (faza, inelul, ce trăiește, cum o poate sprijini, citatele, ce urmează, începuturile pe 6 luni, nota „estimare, nu contracepție”, o întrebare despre anunțuri), fila **Învață** (8 pagini), **Setări** (rotița: notificări în aplicație, email la începutul fazelor, Ieși, Șterge accesul meu). Stări: pauză, oprit, fără cont, link expirat. |
| Linkul | `/pentru-el/?t=<43 caractere base64url>` (32 de octeți aleatori; în tabel doar sha256) → cookie `ah_spp` = `id.expirare.HMAC` (1 an, HttpOnly, Secure, Lax) → 302 la `/pentru-el/`. Un token necunoscut: pagina cu „Linkul acesta nu mai e valabil” + formularul. |
| Aplicația | `/pentru-el/` e adăugată la lista permisă prin filtrul `ah_spa_permise` (deci și în `S.permise` al punții). Pe `/spatiul-meu/` fără `ah_sp`, dar cu `ah_spp` valid (și fără `?cont=`/`?intra=`) → 302 la `/pentru-el/` (aplicația pornește mereu din Spațiul meu). Pagina lui are propriul script mic: `PushNotifications` (înregistrare la cerere, atingerea notificării deschide `data.url`), `App.appUrlOpen` pentru linkurile `/pentru-el/?t=`. În aplicație: fără linkuri spre cărți (nici în date, nici în pagină; clasa `spp-carte`) și fără Google Fonts. |

## Date (GDPR art. 9: minimizare, acord separat, ștergere)

Tabelul `wp_ah_spp_parteneri`: `ea` = `ah_sp_ciclu_cheie(emailul ei)` (fără emailul ei), `nume`, `nume_ea`, `email_c`
(emailul lui, AES-256-GCM cu cheia proprie `ah_spp_secret`, alta decât a ciclului), `email_h` (HMAC, pentru căutare),
`token_h` (sha256), `token_nou_h` (sha256 al linkului cerut de el, valabil alături de cel vechi până la prima folosire), `ses` (intră în semnătura cookie-ului: o valoare nouă deconectează toate sesiunile lui), `acord` (momentul bifei ei), `creat`, `invitat`, `intrat`, `vizita`, `oprit`, `email_faze`,
`faza_trimisa` (faza + o amprentă HMAC a zilei de început, ca să observăm schimbarea fără să păstrăm date).
Tabelul `wp_ah_spp_push`: tokenurile FCM ale lui (separate de `wp_ah_spa_push` al ei), cel mult 5.

`GET /azi` calculează pe server din `ah_sp_ciclu` (decriptat), cu portul exact al `CI.d` / `CI.lung` / `CI.faze` / `CI.azi`
(inclusiv regulile `Number()`/`||`/`Math.round` din JS). Întoarce doar
`{nume_ea, nume, stare, faza, zi, lungime, pana_la, urmatoarea:{faza,de_la,sprijin,arhetip}, inceputuri[], harta[{faza,a,b}], texte:{ce,sprijin,citate,arhetip,luna}, azi, setari}`.
Niciodată: notițele, simptomele, `sit`, starturile notate, linkul vechi.

- `stare:'pauza'` (text neutru care nu spune de ce: „Pentru moment, aici nu e nicio estimare.”, CIORNĂ): fără rând în
  `ah_sp_ciclu`, date nevalide, `sit` hormonal/sarcină, prima zi în viitor, sau **menstruația întârzie, oricât de puțin**
  (review 18 nov.: o întârziere, deci posibil o sarcină, e mai sensibilă decât faza și datele estimate la care și-a dat
  acordul; înainte, 1–7 zile de întârziere îi arătau lui „Ziua 31 din 28”). Răspunsul e identic pentru orice motiv al pauzei
  și orice număr de zile (test). Ea e anunțată în cardul ei: „Când menstruația întârzie sau datele nu sunt la zi, el vede doar
  un mesaj neutru, fără motiv.” `sit` lipsă sau necunoscut = „natural”, exact ca `CI.sit()` pe ecranul ei.
- `zi` (ziua din ciclu, pentru inel) ajunge la el doar între 1 și lungimea ciclului; ea se poate deduce oricum din datele estimate
  ale fazelor (începutul următoarei ierni − lungimea). De văzut cu Adela dacă inelul cu „Ziua N din L” rămâne.
- `stare:'oprit'`: accesul oprit de ea (el păstrează cookie-ul și vede „oprit” până la curățenie).

Ștergerea: el — „Șterge accesul meu” (pe loc, rând + tokenuri). Ea — „Oprește accesul” (pe loc; rândul pleacă după 30 de zile);
`POST /ah-sp/v1/ciclu-sterge` (prins prin `rest_request_after_callbacks`) și `ah_spa_cont_sters` șterg pe loc tot ce ține de ea;
cronul zilnic șterge și rândurile al căror rând `ah_sp_ciclu` lipsește (orice alt drum de ștergere), plus tokenurile push nevăzute de 270 de zile.

## REST `ah-spp/v1` (toate `Cache-Control: no-store, private`)

| Ruta | Cine | |
|---|---|---|
| `GET /parteneri` | ea (`ah_sp`) | `{ciclu, max, parteneri:[{id,nume,email,stare:invitat\|a_intrat,vizita,creat}]}` |
| `POST /invita` | ea | `{nume, email, nume_ea, acord:true}`. Refuză: fără acord, fără ciclu în cont (409 `ciclu`), adresa ei, prenume cu linkuri/`@`, același el activ, peste 2 activi, peste 6 invitații pe zi sau 3 pe zi spre aceeași adresă. |
| `POST /opreste` | ea | `{id}` — doar ale ei. |
| `POST /retrimite` | ea | `{id}` — token nou și `ses` nou (linkul vechi și toate cookie-urile lui, pe orice telefon sau browser, nu mai merg), invitația din nou; 3 pe zi. |
| `GET /azi` | el (`ah_spp` sau `?t=`) | vezi mai sus; 401 `{stare:'iesit'}` fără cont. |
| `POST /trimite-link` | oricine | `{email}` → același răspuns pentru orice adresă, în același timp (emailul pleacă după răspuns, prin `litespeed_finish_request` / `fastcgi_finish_request`; altfel orice răspuns durează cel puțin `AH_SPP_LINK_MS` = 1,5 s). Dacă e invitat activ: un link nou în `token_nou_h`; linkul pe care îl are rămâne valabil până deschide linkul nou (deci altcineva care îi știe adresa nu i-l poate strica); sesiunile deschise nu se ating. 5/oră de pe IP (429), 3/zi pe adresă (tăcut), capcană `site`. |
| `POST /push`, `DELETE /push` | el / telefonul | `{token, platforma}`; validarea lui `ah_spa_valideaza_push` dacă există (APNs brut refuzat). |
| `POST /email` | el | `{on}` — emailurile fazelor. Oprire și din orice email: `/pentru-el/?fara-emailuri=<id>.<HMAC>`. |
| `POST /iesi` | el | scoate cookie-ul (pagina scoate și tokenul push). |
| `POST /sterge` | el | `{confirmare:true}`. |

## Notificări

Cron zilnic `ah_spp_zilnic` (8:10, lacăt 120 s, buget 20 s + cursor) și `ah_spp_verifica` la 2 minute după un
`POST /ah-sp/v1/ciclu-date` reușit al ei. Pentru fiecare partener activ care a intrat: dacă semnul fazei s-a schimbat și
faza nouă a început azi sau ieri → push (dacă are tokenuri) și/sau email (dacă a cerut). Prima verificare, o fază
„schimbată” din urmă (corectarea datelor) sau revenirea din pauză doar notează, fără mesaj.
Push: refolosește `ah_spa_fcm_cont`, `ah_spa_fcm_acces`, `ah_spa_fcm_mesaj` din modulul aplicație (prin `function_exists`);
**nu** `ah_spa_trimite_push`, care șterge tokenuri invalide după `id` din tabelul ei (ar șterge rânduri greșite). Fără modulul
aplicație sau fără cheia Firebase: doar jurnal (`ah_spp_jurnal`, fără adrese). Textele notificărilor: `ah_spp_mesaj_faza()` (CIORNĂ).

Emailuri (cadrul comun `ah_mail_trimite` / `ah_mail_p`; rezervă `wp_mail`): invitația, linkul nou, începutul fazei (cu link de oprire).
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
   pleacă prin `DELETE /ah-spp/v1/push`.
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

- `php server/tests/partener/run.php` — 265 de verificări (stubs: copie a `tests/stubs.php` + funcțiile lui 235 și `ah_ciclu_faze` din 41
  citite direct din `reference/v2`): încărcare dublă, rute, criptare/GCM/cheie separată, token (format, doar sha256, rotație la
  retrimitere cu sesiune nouă; „trimite-mi linkul” nu anulează linkul vechi până la folosirea celui nou; timpul răspunsului și
  emailul trimis după răspuns), acordul (lipsă/implicit/ciclul nu e în cont), limitele (2 activi, 6/zi, 3 retrimiteri,
  5/oră IP, 3/zi adresă tăcut, capcana), cookie (semnătură, id, expirare, `\n`), minimizarea (chei exacte; fără notițe, `sit`,
  starturi, emailuri), pauza (toate situațiile, viitor, orice întârziere, identică pentru 1 și 8 zile și pentru `hormonal`, ciclu șters), oprit, push (APNs, dubluri, 5 maxim, separat
  de ea), notificările (o dată, email cu oprire, corectare din urmă, pauză, FCM 404 → token șters, programarea după `ciclu-date`),
  curățenia (30 de zile, `ciclu-sterge`, rând lipsă, `ah_spa_cont_sters`, alte femei neatinse), pagina (browser vs aplicație), redirecționarea în aplicație.
- `node server/tests/partener/faze.test.cjs` — 12.750 de cazuri (grila 21–40 × 2–9 × fiecare zi + 6.000 de istorii aleatoare cu
  date stricate, numere ca text, zecimale, ani 0–99, luni/zile peste limită, schimbarea orei): `ah_spp_*` = `CI` din `sn235.php`, 73.273 de comparații.
- `node server/tests/partener/poze.cjs` — 88 de verificări (plus linkul lipit, pe ecranul de intrare și pe pagina lui) în Chromium 390×844 și pozele `docs/screenshots-v2/partener-*.png` (în plus, de la integrare: push-ul lui și al cui e tokenul, fără push fără Firebase / cu APNs / cu ea intrată pe telefon).

## De decis / de verificat

- Adela: toate textele CIORNĂ (cardul ei, pagina lui, Învață, emailuri, notificări).
- Pauza la orice întârziere, cu text neutru (review 18 nov.); de confirmat cu Adela, împreună cu inelul „Ziua N din L”.
- Linkul din email rămâne permanent (nu e de unică folosință): recenzia Apple și telefonul + laptopul lui îl deschid de mai multe ori.
  Ea îl poate anula oricând cu „Trimite din nou invitația” (deconectează și sesiunile).
- Fonturile: Figtree și Gilda din `/wp-content/uploads/elementor/google-fonts/css/*.css` (pe site există); Cormorant de la Google doar în browser.
- `wp_date()` pentru „azi” pe server = ora site-ului (Europe/Bucharest); telefonul ei folosește ora telefonului.
