# Contractul API: aplicația mobilă ↔ adelaharu.ro

Versiunea 1 (3 nov. 2026). Sursa de adevăr pentru echipa server și echipa aplicație.
Orice schimbare se face întâi aici.

## Context

- Site: WordPress 7.1 + WooCommerce, cod custom în Code Snippets (copii în `reference/`):
  - `sn51.php`: „Meditațiile mele” (aplicația web cu cod pe email). Accesul: `ah_med_acces($email)`, `ah_med_poate($id,$acc)`, lista `ah_med_lista()`, `ah_med_date()`, streaming `ah_med_streameaza()`, limită dispozitive `ah_med_disp()`. Autentificare azi: cookie `ah_med`.
  - `sn154.php`: **Inner Temple** = abonamentul la meditații prin Stripe (lunar 80 lei / anual 600 lei). Fiecare plată creează o comandă WooCommerce cu produsul de abonament, deci accesul trece prin `ah_med_acces()`. Portal Stripe: `ah_it_portal`.
  - `sn163.php`: „Cursurile mele”, `ah_cm_cursuri_pentru($email)` (slug ⇒ nume, link, până la).
  - `sn44.php`: „Sala de curs” (`ah_sc_*`): cursuri, module, lecții Vimeo/PDF, progres, `ah_sc_din_comanda()`, `ah_sc_platita()`.
  - `sn146.php`: ascultări în sală (jurnal pe persoană).
  - `sn41.php`: „Harta ciclului” (`ah_ciclu_*`): fazele, texte pe faze, abonarea la emailuri (date de sănătate, art. 9 GDPR).
- Aplicația NU vinde nimic: fără prețuri, fără butoane de cumpărare, fără linkuri spre cumpărare.

## Generalități

- Bază: `https://www.adelaharu.ro/wp-json/ah-app/v1`
- Autentificare: `Authorization: Bearer <token>` (exceptând `/auth/*`, `/config`, `/ciclu/faze`).
- Răspuns de eroare: HTTP 4xx/5xx + `{"eroare":{"cod":"<slug>","mesaj":"<text în română pentru utilizatoare>"}}`.
  Coduri: `neautentificat` (401), `fara_acces` (403), `negasit` (404), `prea_multe` (429), `cod_gresit` (400), `cod_expirat` (400), `dispozitive` (403, limita de dispozitive), `invalid` (400), `server` (500).
- Datele: ISO 8601 (`2026-11-03`, `2026-11-03T10:00:00+02:00`). Durate în secunde.
- CORS: serverul răspunde pentru originile `capacitor://localhost`, `https://localhost`, `http://localhost` (headere `Authorization, Content-Type`; metode `GET, POST, PUT, DELETE, OPTIONS`). Aplicația folosește oricum `CapacitorHttp` (HTTP nativ), CORS e doar plasă de siguranță.
- Fără cache: toate răspunsurile `Cache-Control: no-store` (LiteSpeed nu are voie să le cacheze).

## Autentificare (cod de 6 cifre pe email, ca la „Meditațiile mele”)

### `POST /auth/cod`
Corp: `{"email":"ana@ex.ro"}`
Răspuns: `{"ok":true}` **mereu** (nu spunem dacă adresa există).
- Trimite un email cu un cod de 6 cifre (valabil 15 minute) și cu un link
  `https://www.adelaharu.ro/app/intra?e=<email>&c=<cod>`, care pe telefon deschide aplicația (universal link / app link) și completează codul singur.
- Limite: 5 coduri / oră / email, 20 / oră / IP ⇒ `429 prea_multe`.
- Dacă adresa nu are nimic cumpărat, emailul tot pleacă (contul se deschide, vede meditațiile gratuite și ciclul).

### `POST /auth/verifica`
Corp: `{"email":"…","cod":"123456","dispozitiv":{"id":"<uuid generat de aplicație>","nume":"iPhone 15","platforma":"ios|android"}}`
Răspuns: `{"token":"<64 hex>","utilizatoare":{"email":"…","prenume":"Ana"}}`
- 5 încercări greșite pe cod ⇒ codul se anulează (`cod_expirat`).
- Tokenul: 32 de octeți aleatori; pe server se păstrează **doar sha256**. Nu expiră singur. Ultima folosire se actualizează (cel mult o dată pe oră).
- Limita: cel mult 5 dispozitive ale aplicației pe email (separat de cele 5 ale aplicației web) ⇒ `403 dispozitive`, cu mesaj să scrie la contact@adelaharu.ro. Același `dispozitiv.id` reînnoiește tokenul, nu ocupă loc nou.

### `POST /auth/iesire`
Revocă tokenul curent. `{"ok":true}`

### `DELETE /cont`
Șterge tot ce ține de aplicație pentru această adresă: tokenuri, tokenuri push, datele ciclului (și abonarea la emailurile fazelor), pozițiile de ascultare. Nu atinge comenzile și facturile. `{"ok":true}`. (Cerință Apple/Google: ștergerea contului din aplicație.)

## Configurație publică

### `GET /config`
`{"versiune_minima":"1.0.0","contact":{"email":"contact@adelaharu.ro","whatsapp":"+40742709306"},"linkuri":{"site":"https://www.adelaharu.ro/","confidentialitate":"https://www.adelaharu.ro/politica-de-confidentialitate/","termeni":"https://www.adelaharu.ro/termeni-si-conditii/"},"mesaj":null}`
(`mesaj` = anunț opțional `{"titlu","text"}` setat din admin.)

## Profil și acces

### `GET /eu`
```json
{
  "email": "ana@ex.ro",
  "prenume": "Ana",
  "inner_temple": {"activ": true, "pana_la": "2027-01-15", "tip": "lunar|anual|null", "se_reinnoieste": true},
  "meditatii_cumparate": 3,
  "cursuri": [{"slug":"wild-love","nume":"Wild Love","imagine":"https://…","pana_la":null,"progres":0.4}],
  "ciclu": {"salvat_pe_site": true, "emailuri_faze": true}
}
```
`se_reinnoieste`: abonament Stripe activ și neanulat. Aplicația NU afișează link spre portalul de plată pe iOS. Pe Android, doar text informativ.

## Inner Temple / meditații

### `GET /meditatii`
```json
{
  "categorii": [{"slug":"respiratie","nume":"Respirație"}],
  "meditatii": [{
    "id": 123, "titlu": "…", "text": "…", "poza": "https://…",
    "durata": 600, "categorie": "respiratie",
    "acces": "abonament|cumparata|gratuita",
    "poate_asculta": true,
    "video": {"tip":"vimeo","embed":"https://www.adelaharu.ro/app/player?…"} | null,
    "pozitie": 120,
    "noua": true
  }]
}
```
- Meditațiile fără acces apar (cu `poate_asculta:false`) DOAR dacă `acces=="gratuita"` sau dacă femeia are deja ceva; aplicația le arată cu lacăt și textul „Disponibilă în Inner Temple. Detalii pe adelaharu.ro.” FĂRĂ link și fără preț (regula Apple 3.1.1/3.1.3(a)).
- `noua`: publicată în ultimele 14 zile.

### `GET /meditatii/{id}/redare`
`{"url":"https://www.adelaharu.ro/?ah_app_media=<id>&exp=<unix>&sig=<hmac>","expira":"…"}`
- URL semnat (HMAC-SHA256 cu o cheie din `wp_salt`, valabil 6 ore), legat de email și id; suportă `Range` (pentru derulare și redare în fundal). Refolosește streamingul din `sn51` (`ah_med_streameaza`).
- Aceeași adresă e folosită pentru descărcarea offline (aplicația o salvează local).
- `403 fara_acces` dacă nu are voie.

### `POST /meditatii/{id}/pozitie`
Corp `{"secunde":120,"terminat":false}`. Se sincronizează cu poziția din aplicația web (aceeași memorie ca în `sn51`, dacă există; altfel user meta). Adaugă minutele ascultate ca `ah_med_adauga_minute`.

## Cursuri (sala de curs)

### `GET /cursuri`
Lista din `/eu.cursuri`, cu `module_total` și `module_terminate`.

### `GET /cursuri/{slug}`
```json
{
  "slug":"wild-love","nume":"…","imagine":"…","descriere":"…",
  "module":[{
    "id":"m1","titlu":"…","deschis":true,"se_deschide_la":null,
    "lectii":[{
      "cheie":"…","titlu":"…","tip":"video|audio|pdf|text",
      "durata":900,
      "player":"https://www.adelaharu.ro/app/player?…",
      "fisier":"https://www.adelaharu.ro/?ah_app_media=…",
      "text":"<html simplu>",
      "terminata":false
    }]
  }]
}
```
- `deschis/se_deschide_la`: respectă eliberarea pe săptămâni din sală, dacă există.
- **Vimeo**: videourile au restricție de domeniu. De aceea aplicația NU încarcă direct `player.vimeo.com`; încarcă `player` = o pagină minimală găzduită pe adelaharu.ro (`/app/player?l=<cheie>&exp=…&sig=…`), care conține iframe-ul Vimeo (referrerul e adelaharu.ro). Semnat, valabil 6 ore, legat de email.
- PDF/audio: `fisier` semnat ca la meditații.

### `POST /cursuri/{slug}/progres`
Corp `{"cheie":"…","terminata":true}` ⇒ același progres ca „Am terminat modulul” din sală (merge pe orice dispozitiv).

## Ciclul

Aplicația calculează fazele **local, pe telefon** (merge fără cont și fără internet). Serverul e folosit doar dacă femeia vrea sincronizare sau emailurile fazelor.

### `GET /ciclu/faze` (public, se poate cache-ui 1 zi)
Textele celor 4 faze, extrase din `sn41`:
```json
{"faze":[{"slug":"iarna","arhetip":"Alchimista","anotimp":"Iarna","culoare":"#…","ce_se_intampla":"…","ce_e_potrivit":"…","meditatie_id":123,"curs_slug":null}],
 "regula":"<cum se calculează fazele, identic cu sn41>",
 "avertisment":"Fazele sunt o estimare, nu o metodă de contracepție."}
```
Algoritmul de calcul al fazelor trebuie să fie IDENTIC cu cel din `sn41` (se documentează în `docs/ciclu-algoritm.md`).

### `GET /ciclu`
`{"prima_zi":"2026-10-20","zile_sangerare":5,"zile_ciclu":28,"emailuri_faze":true}` sau `null`.

### `PUT /ciclu`
Corp `{"prima_zi":"…","zile_sangerare":5,"zile_ciclu":28,"emailuri_faze":true,"acord_date_sanatate":true}`
- Fără `acord_date_sanatate:true` ⇒ `400 invalid`. Scrie în același loc ca `sn41` (aceeași abonare la emailurile fazelor), ca să nu fie două surse.

### `DELETE /ciclu`
Șterge rândul ei (ca linkul de oprire din emailuri).

## Notificări push

### `POST /push`
Corp `{"token":"<FCM token>","platforma":"ios|android","subiecte":{"meditatii_noi":true,"cursuri":true}}`. Legat de tokenul aplicației.

### `DELETE /push`
Corp `{"token":"…"}`.

Trimiterea: FCM HTTP v1 (și pentru iOS, prin APNs în Firebase). Cheia de cont de serviciu se pune în admin (opțiunea `ah_app_fcm`); până atunci trimiterea e oprită și se scrie doar în jurnal. Declanșatori v1:
- meditație nouă publicată ⇒ celor cu acces (`meditatii_noi`);
- modul nou deschis într-un curs ⇒ cursantelor (`cursuri`).
Notificările de fază (ciclu) sunt **locale**, programate de aplicație, nu de server.

## Linkuri universale

Serverul servește:
- `https://www.adelaharu.ro/.well-known/apple-app-site-association` (JSON, fără extensie; `applinks` pentru `/app/*`, `/meditatiile-mele/*`, `/sala-de-curs/*`, `/cursurile-mele/*`); Team ID din opțiunea `ah_app_ios_team`.
- `https://www.adelaharu.ro/.well-known/assetlinks.json` (pachet `ro.adelaharu.app`, amprenta SHA-256 din opțiunea `ah_app_android_sha256`).
- `/app/intra`: dacă aplicația nu e instalată, pagina arată codul și linkurile spre magazine.

Identificatori: bundle/package `ro.adelaharu.app`, numele aplicației „Adela Haru”.
