# Cerință pentru fragment: contul de recenzie (App Review / Google Play)

Pentru: colegul de la `server/ah-sp-aplicatie.php`. De la: magazine și build. Context: `docs/store/review-notes.md`.
Versiunea: v2 (18 nov. 2026). Înlocuiește cerința v1 (`/auth/cod`, `/auth/verifica` din `ah-app-api.php`, scos).

> **Stare: IMPLEMENTAT în fragment** (`server/ah-sp-aplicatie.php` 2.1.0, secțiunea 9, 18 nov. 2026): setarea în
> Setări › Aplicația mobilă › „Contul de recenzie” (opțiunea `ah_spa_recenzie`, doar hash-ul codului), filtrul
> `ah_spa_recenzie_filtru` pe `rest_pre_dispatch` (prioritatea 4), golirea dispozitivelor, subiectul „(contul de
> recenzie)” la `sterge-cont`; testele din punctul 9 în `server/tests/run.php` (secțiunea 12) trec. **Rămâne** (blochează
> trimiterea până atunci): ce pregătește proprietara (mai jos) și o intrare reușită cu codul fix în TestFlight / testarea internă.

## Problema

În v2 aplicația deschide Spațiul meu, iar intrarea e cea din fragmentul 235: emailul → `POST /wp-json/ah-sp/v1/cod`
(trimite un cod de 6 cifre pe email) → `POST /wp-json/ah-sp/v1/intra` (`{email, cod}`; la succes pune cookie-ul
semnat `ah_sp` pe un an, prin `ah_sp_seteaza_cookie()`). Recenzorii Apple și Google nu au acces la nicio căsuță de
email, deci au nevoie de **o singură adresă de recenzie care primește un cod fix**. Fragmentul 235 nu se modifică.

## Ce am găsit în sn235 (ce se poate și ce nu)

| Cârlig | Ce permite | Ajunge? |
|---|---|---|
| filtrul `ah_sp_poate_intra` (folosit de 220 și 281) | decide doar *dacă* se trimite un cod unei adrese fără comenzi | Nu: codul e tot aleator, trimis pe email |
| transientul `ah_sp_cod_<md5(email)>` = `{h: wp_hash_password(cod), n}` | s-ar putea pre-scrie cu hash-ul codului fix | Merge, dar depinde de un detaliu intern al lui 235 și îi resetează contorul de încercări; doar rezervă |
| **filtrul WordPress `rest_pre_dispatch`** | rulează înaintea oricărei rute REST; un răspuns non-null oprește ruta din 235 | **Da. Varianta aleasă** (fragmentul îl folosește deja pentru portalurile Stripe) |
| funcția publică `ah_sp_seteaza_cookie( $email )` din 235 | pune exact cookie-ul `ah_sp` pe care îl pune `/intra` | Da: o refolosim, nu copiem semnătura |

Interceptarea e sigură pentru restul site-ului: acționează **doar** pe `POST /ah-sp/v1/cod` și `POST /ah-sp/v1/intra`
și **doar** când `email` (normalizat ca în 235: `strtolower( trim() )`) e exact adresa de recenzie configurată.
Orice altă adresă trece neatinsă (filtrul întoarce `$rez` primit). Corpul vine ca JSON (`post()` din 235 trimite
`Content-Type: application/json`); `$req->get_param('email')` și `get_param('cod')` îl citesc și în `rest_pre_dispatch`.

## Ce trebuie să facă fragmentul

1. **Setarea** (Setări › Aplicația mobilă, secțiune nouă „Contul de recenzie”, doar `manage_options`), în
   `ah_spa_setari` sau într-o opțiune separată `ah_spa_recenzie` (non-autoload):
   - `recenzie_email` (gol = funcția nu există; validat cu `is_email`, salvat normalizat);
   - `recenzie_cod`: exact 6 cifre. La salvare se refuză `000000`, `123456`, `654321` și cele cu aceeași cifră
     de 6 ori. **Se păstrează doar hash-ul** (`wp_hash_password`), iar câmpul se afișează gol („Cod setat:
     da/nu”), ca parola; codul îl știe doar cine îl trece în App Store Connect / Play Console;
   - `recenzie_doar_app` (bifat implicit): codul fix merge doar dacă `ah_spa_e_aplicatie()`. Nu e o barieră de
     securitate (User-Agent-ul se poate imita), doar reduce expunerea; se debifează dacă un recenzor vrea să
     verifice și pe web.
2. **`POST /ah-sp/v1/cod`** cu adresa de recenzie → răspuns `200 {"ok":true}` (exact forma din 235), **fără email**,
   fără să atingă contoarele `ah_sp_ip_*` / `ah_sp_em_*` ale lui 235 (recenzorii repetă des și ar da de limita
   de 3 coduri / 15 minute). Limită proprie: 30 de cereri / oră / IP (`ah_spa_l_*`, ca restul fragmentului),
   peste ea `429` cu mesajul din 235 („Ai cerut deja câteva coduri. Încearcă din nou peste 15 minute.”).
3. **`POST /ah-sp/v1/intra`** cu adresa de recenzie:
   - întâi limita: după 5 coduri greșite la rând pentru adresa de recenzie ⇒ `429` 15 minute
     („Prea multe încercări. Cere un cod nou.”, textul din 235), apoi se poate din nou (nu blocare definitivă,
     ca recenzia să nu se blocheze singură). Contorul în transient `ah_spa_rec_n`, rezervat **înainte** de comparare;
   - codul: `preg_replace('/\D/', '', cod)`, 6 cifre, `wp_check_password( $cod, $hash )` (timp constant);
   - greșit ⇒ `403 {"ok":false,"mesaj":"Codul nu se potrivește. Verifică cifrele."}` (textul din 235);
   - bun ⇒ `ah_sp_seteaza_cookie( $e )` (păzit cu `function_exists`; dacă lipsește 235, filtrul nu face nimic),
     contorul la zero, `ah_spa_fara_cache()`, jurnal, curățenia dispozitivelor (punctul 5), răspuns `200 {"ok":true}`.
     Pagina se reîncarcă singură (`location.reload()` din 235), ca la o intrare obișnuită.
   - Codul fix nu expiră și nu e de unică folosință. **Codurile adevărate trimise pe email nu sunt afectate**
     pentru alte adrese; pentru adresa de recenzie ruta `/cod` nu mai trimite email, deci nu există alt cod.
4. **Accesul vine din comenzi reale, fără excepții în cod.** Nimic special la `ah_sp_date`, `ah_med_acces`,
   `ah_sc_*`, cărți: proprietara face comenzi manuale pe adresa de recenzie (vezi „Ce pregătește proprietara”).
5. **Limitele de dispozitive** (Apple testează pe mai multe telefoane, uneori în paralel, la fiecare versiune):
   la fiecare intrare reușită cu codul fix, fragmentul golește **doar pentru adresa de recenzie**:
   - dispozitivele meditațiilor (51): `delete_option( ah_med_cheie_disp( $e ) )`, dacă `function_exists`;
   - dispozitivele sălii (44) din comenzile adresei: `$c->delete_meta_data('_ah_sc_disp'); $c->save();` (ce face
     butonul „Golește dispozitivele sălii de curs” din comandă), dacă `function_exists('ah_sc_dispozitive')`.
   Pentru orice altă adresă, nimic.
6. **`POST /ah-spa/v1/sterge-cont`** pe adresa de recenzie se comportă normal (Apple îl încearcă): se șterg ciclul,
   push-ul, dispozitivele, cookie-urile. Comenzile rămân, deci recenzorul poate intra din nou. Emailul către echipă
   pleacă și el, cu mențiunea „(contul de recenzie)” în subiect, ca să nu sperie pe nimeni.
7. **Push pe adresa de recenzie:** primește notificările ca oricine (Apple vrea să le vadă). Fără excepții.
8. **Jurnal** (`ah_spa_jurnal`): „recenzie: cod cerut”, „recenzie: intrare”, „recenzie: cod greșit”, cu IP-ul
   trunchiat (/24 pentru IPv4, /48 pentru IPv6). Nu se scrie niciodată codul.
9. **Teste** (`server/tests/`), cu stuburi pentru `WP_REST_Request` / `rest_pre_dispatch`:
   - opțiunea goală ⇒ filtrul întoarce `null` pentru orice cerere (235 rulează neschimbat);
   - altă adresă (ex. `recenzie+1@adelaharu.ro`, `recenzie@adelaharu.com`) ⇒ `null`;
   - adresa de recenzie cu majuscule și spații ⇒ recunoscută;
   - `/cod` ⇒ `200 ok`, niciun apel la `ah_mail_trimite`, contoarele 235 neatinse;
   - `/intra` cu codul bun ⇒ `ah_sp_seteaza_cookie` chemat o dată cu adresa normalizată; cu cod greșit ⇒ 403;
     a 6-a încercare ⇒ 429; după 15 minute ⇒ din nou acceptă;
   - `recenzie_doar_app` bifat și UA de browser ⇒ `null` (merge pe ruta normală din 235);
   - metodă GET sau altă rută (`/ah-sp/v1/iesi`, `/ah-sp/v1/ciclu-date`) ⇒ `null`;
   - validarea codului la salvare (refuzate `123456`, `000000`, `5 cifre`, litere); hash-ul, nu codul, în opțiune;
   - golirea dispozitivelor atinge doar adresa de recenzie.
   - `sterge-cont` pe adresa de recenzie ⇒ emailul echipei are „(contul de recenzie)” în subiect; pe altă adresă, nu;
   - jurnalul nu conține niciodată codul (nici cel bun, nici cel greșit).

### Schiță (orientativă; numele exacte le alege colegul)

```php
/* Contul de recenzie (Apple / Google): cod fix pentru O SINGURĂ adresă, setată în Setări › Aplicația mobilă. */
add_filter( 'rest_pre_dispatch', function ( $rez, $server, $req ) {
	if ( null !== $rez || ! is_object( $req ) || 'POST' !== $req->get_method() ) { return $rez; }
	$ruta = (string) $req->get_route();
	if ( '/ah-sp/v1/cod' !== $ruta && '/ah-sp/v1/intra' !== $ruta ) { return $rez; }
	$r = ah_spa_recenzie(); // ['email' => '', 'hash' => '', 'doar_app' => 1]
	$e = strtolower( trim( (string) $req->get_param( 'email' ) ) );
	if ( '' === $r['email'] || '' === $r['hash'] || $e !== $r['email'] ) { return $rez; }
	if ( $r['doar_app'] && ! ah_spa_e_aplicatie() ) { return $rez; }
	return '/ah-sp/v1/cod' === $ruta ? ah_spa_recenzie_cod() : ah_spa_recenzie_intra( $e, (string) $req->get_param( 'cod' ) );
}, 4, 3 );
```

Prioritatea 4 (înaintea filtrului de portaluri, 5) nu contează pentru corectitudine (rutele sunt diferite), dar
ține ordinea limpede.

## Ce pregătește proprietara (o dată, înainte de prima trimitere)

1. **O adresă dedicată**, de ex. `recenzie@adelaharu.ro`, o căsuță reală (nu primește coduri, dar poate primi alte
   emailuri ale site-ului). Fără date personale reale pe ea.
2. **Comenzi manuale în WooCommerce, pe această adresă** (prenume „Recenzie”, nume „App”):
   - Inner Temple (anual) și un curs (ex. „Wild Love”) cu starea **„Acces gratuit”** (`wc-ah-acces`, „Extern
     Invitation”): o recunosc 235, 51 (de la 17 nov.) și sala (44 / 224), fără factură;
   - **o carte electronică**: Cărțile mele (231) citește doar comenzile „În procesare” / „Finalizată”, deci aceasta
     trebuie să fie **„Finalizată”, 0 lei**. *De verificat* că programul de facturare nu emite automat o factură
     de 0 lei (sau anulați-o) și că emailul „Comanda finalizată” nu deranjează.
   - opțional: înscrierea adresei la o întâlnire „Respiră-te acasă” care are înregistrare, ca recenzorul să vadă și
     secțiunea întâlnirilor.
   **De reînnoit înainte să expire** accesul anual la Inner Temple (`pana` = data comenzii + zilele produsului);
   altfel recenzia următoare vede meditațiile închise.
3. În **Setări › Aplicația mobilă › Contul de recenzie**: adresa și codul fix (6 cifre alese de voi, nu ușor de
   ghicit; nu `123456`). Acolo se poate și opri (golind adresa).
4. Același cod se trece în App Store Connect (App Review Information › Password) și în Play Console (Acces la
   aplicație). **Codul nu se pune în repo, în chat sau în emailuri.**

## Partenerul de recenzie (contul „Pentru el”, când există `ah-sp-partener.php`)

Contul lui nu are cod: intră cu un link personal permanent (`/pentru-el/?t=<token>`, `arhitectura.md`), care pe
telefon deschide aplicația **când e atins**. Recenzorul nu are emailul, deci îi dăm linkul direct în note. **Nimic special în cod.**

Cum ajunge recenzorul în aplicație cu linkul (ales 18 nov., seara): iOS nu deschide aplicația pentru un link tastat
sau lipit în bara de adrese din Safari (acolo se deschide site-ul). Notele îi cer deci (a) să lipească linkul în
Notițe sau Mesaje pe iPhone și să-l atingă de acolo, sau (b) să-l lipească în aplicație: pe ecranul de intrare
(fără cont), „Sunt partenerul ei” › câmpul „Sau lipește aici linkul copiat” › „Deschide linkul”, care deschide
`/pentru-el/?t=` în aceeași pagină, deci în aplicație (`ah-sp-partener.php` 1.1.0; același câmp e și pe pagina lui
fără cont). Pe Android, la fel: linkul atins dintr-o notiță sau din Gmail deschide aplicația.
1. Cu contul de recenzie (ea), în Ciclul meu: ciclul salvat în cont (cu acordul), apoi invitația pentru
   „Andrei”, `recenzie-el@adelaharu.ro` (a doua căsuță reală, la care are acces echipa), cu bifa de acord.
2. Din emailul primit de `recenzie-el@`, linkul personal se copiază în App Review Information › Notes
   (`<REVIEW_PARTNER_LINK>` din `store/review-notes.md`) și în Play Console › Acces la aplicație (a doua
   instrucțiune, „Cont partener”).
3. Linkul nu mai merge dacă recenzorul își șterge accesul de partener, dacă apasă „Oprește accesul” sau „Trimite din
   nou invitația” în contul ei, **sau dacă șterge contul de recenzie** („Șterge contul” șterge, prin
   `ah_spa_sterge_cont` › `ah_spp_sterge_ale_ei`, toți partenerii ei și ciclul păstrat în cont). De aceea notele pun
   blocul PARTNER ACCESS înaintea ștergerii contului și îi cer recenzorului să încerce întâi partenerul. **Înainte de
   fiecare trimitere** (și după orice rundă de recenzie): intrați cu contul de recenzie, verificați că ciclul e în cont
   (altfel îl refaceți: prima zi, lungimile, „Păstrează în cont” cu acordul) și că „Andrei” apare în „Pentru el”; dacă
   lipsește, invitați-l din nou (punctele 1–2) și înlocuiți linkul în App Store Connect și în Play Console. Deschideți
   linkul nou o dată (atins dintr-o notiță) ca să vedeți că merge. Același memento ca la reînnoirea Inner Temple.
4. Pe telefonul pe care e intrat și contul de recenzie (ea), pagina lui se deschide, dar notificările telefonului rămân
   ale ei (un telefon = un cont): pagina lui oferă atunci emailurile fazelor și „Deschide Spațiul meu”. Notele spun asta.
Cerință pentru colegul de la `ah-sp-partener.php`: limita de 2 parteneri activi și „Trimite din nou linkul” merg
normal pentru aceste adrese; tokenul nu expiră singur (ca pentru oricine).

## De ce așa (riscuri)

- E o „ușă din spate” controlată: o singură adresă, fără date reale, doar conținut deja oferit gratuit, oprită
  dintr-un câmp. Comenzile reale dau accesul, deci nimic din verificarea accesului nu e ocolit.
- Codul stă doar ca hash; o scurgere a bazei de date nu îl arată.
- Dacă 235 își schimbă vreodată rutele (`/cod`, `/intra`) sau numele `ah_sp_seteaza_cookie`, filtrul nu mai prinde
  nimic și intrarea de recenzie cade în fluxul normal (cod pe email): **nu strică site-ul**, doar recenzia. De aceea,
  la orice versiune nouă a lui 235, testul de mai sus se rulează din nou cu copia din `reference/v2/`.
