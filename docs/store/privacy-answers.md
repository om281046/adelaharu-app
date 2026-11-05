# Confidențialitatea: răspunsurile pentru Apple și Google

Bazate pe ce face aplicația v1 conform `docs/api-contract.md` și codului din `app/`. **Dacă se adaugă un SDK
(analiză, rapoarte de erori, Facebook, Google Analytics etc.), răspunsurile se refac înainte de versiunea nouă.**

## Ce date pleacă de pe telefon (inventarul)

| Dată | Când | Unde ajunge | De ce |
|---|---|---|---|
| Adresa de email | la intrare (cod pe email) | serverul adelaharu.ro | contul, accesul la meditațiile și cursurile cumpărate pe site |
| ID-ul telefonului generat de aplicație (UUID), numele modelului („iPhone 15”), platforma | la intrare | serverul adelaharu.ro | limita de 5 telefoane, ieșirea de pe un telefon |
| Tokenul de notificări (FCM) și subiectele alese | doar dacă permite notificările | serverul adelaharu.ro; Google Firebase (trimite notificarea) | notificări despre meditații noi și module noi |
| Poziția în meditații, lecțiile bifate, minutele ascultate | când ascultă / bifează | serverul adelaharu.ro | „continuă de unde ai rămas”, progresul la curs |
| **Datele ciclului** (prima zi, zile de sângerare, lungimea ciclului) | **doar dacă alege sincronizarea și bifează acordul** | serverul adelaharu.ro (același loc ca harta de pe site) | sincronizare și emailurile fazelor |

Nu pleacă: numele (prenumele vine de la server, din comenzile de pe site), locația, contactele, pozele,
identificatorul de reclame (IDFA/AAID), date de plată. Fără reclame, fără urmărire, fără vânzare de date.
Ciclul calculat local și notificările de fază (locale) **nu** pleacă de pe telefon.

Firebase Cloud Messaging (Google) e singurul SDK terț care primește ceva (tokenul / ID-ul instalării Firebase).
Google îl tratează ca „furnizor de servicii”, deci la Google Play nu se declară „partajare”.

---

## Apple: App Privacy („etichetele nutriționale”), App Store Connect › App Privacy

**Do you or your third-party partners collect data from this app?** → **Yes**

| Tip de date (Apple) | Colectat | Scopuri | Legat de identitate | Folosit pentru urmărire |
|---|---|---|---|---|
| Contact Info › **Email Address** | Da | App Functionality | Da | Nu |
| Health & Fitness › **Health** (ciclul menstrual, doar cu sincronizarea pornită) | Da | App Functionality | Da | Nu |
| Usage Data › **Product Interaction** (poziții, lecții terminate, minute) | Da | App Functionality | Da | Nu |
| Identifiers › **Device ID** (UUID-ul aplicației, tokenul de notificări) | Da | App Functionality | Da | Nu |

Toate celelalte categorii: **Nu** (Name, Phone, Physical Address, Location, Contacts, User Content, Browsing/Search
History, Purchases, Financial Info, Sensitive Info, Diagnostics, Advertising Data, Other).

Explicații și puncte nesigure:
- **Health:** Apple cere declararea și dacă e opțională. Răspundem „Da”.
- **Emailurile fazelor:** dacă vor conține și promovări (cursuri, produse), la Email Address și la Health se adaugă
  scopul „Developer’s Advertising or Marketing”. Dacă sunt strict conținutul cerut (textul fazei), rămâne doar
  App Functionality. **De hotărât de Adela.**
- **Name:** nu e colectat de aplicație (nu se introduce în aplicație). Dacă vreți varianta cea mai prudentă,
  bifați Contact Info › Name, App Functionality, legat de identitate.
- **Purchases:** aplicația doar citește ce s-a cumpărat pe site; nu colectează istoricul. „Nu”.
- **Tracking:** Nu. Aplicația nu cere permisiunea ATT (App Tracking Transparency).
- Fișierul **PrivacyInfo.xcprivacy** (manifestul de confidențialitate, obligatoriu) trebuie să existe în proiectul
  iOS cu aceleași tipuri de date și cu motivele pentru API-urile „required reason” (ex. `UserDefaults` → `CA92.1`,
  timpul fișierelor → `C617.1`, spațiul liber pe disc → `E174.1` dacă se verifică înainte de descărcare).
  **Acum nu există în `app/ios`**, e sarcina colegei de la aplicație.

---

## Google Play: Data safety (Play Console › Conținutul aplicației › Siguranța datelor)

**Colectează sau partajează aplicația vreun tip de date al utilizatorilor?** → Da
**Sunt toate datele criptate în timpul transferului?** → Da (doar HTTPS)
**Oferiți o modalitate prin care utilizatorii pot solicita ștergerea datelor?** → Da
(în aplicație: Profil › „Șterge contul din aplicație”, `DELETE /cont`; pe web: URL-ul de mai jos)
**Conturi:** aplicația permite crearea contului (prin email + cod) → se cere **URL de ștergere a contului**.

| Categoria › tipul | Colectat | Partajat | Prelucrat efemer | Obligatoriu / opțional | Scopuri |
|---|---|---|---|---|---|
| Informații personale › **Adresă de email** | Da | Nu | Nu | Obligatoriu (pentru cont) | Funcționalitatea aplicației; Gestionarea contului |
| Sănătate și fitness › **Informații despre sănătate** | Da | Nu | Nu | **Opțional** | Funcționalitatea aplicației |
| Activitatea în aplicație › **Interacțiuni cu aplicația** | Da | Nu | Nu | Obligatoriu | Funcționalitatea aplicației |
| ID-uri ale dispozitivului sau alte ID-uri | Da | Nu | Nu | Obligatoriu | Funcționalitatea aplicației |

Restul: nu. (Firebase = furnizor de servicii, nu „partajare”, conform definiției Google.)
Același punct ca la Apple: dacă emailurile fazelor conțin marketing, se adaugă scopul „Comunicări ale dezvoltatorului”.

**URL pentru ștergerea contului** (obligatoriu, public, fără login): **de creat** pe site, de ex.
`https://www.adelaharu.ro/stergere-cont-aplicatie/`, cu textul:
> Ca să îți ștergi contul din aplicația Adela Haru, deschide aplicația › Profil › Șterge contul din aplicație. Dacă nu mai ai
> aplicația, scrie-ne de pe adresa contului la contact@adelaharu.ro, cu subiectul „Ștergere cont aplicație”, și o
> facem în cel mult 30 de zile. Se șterg: telefoanele conectate, tokenurile de notificări, datele ciclului (și
> abonarea la emailurile fazelor) și pozițiile de ascultare. Comenzile și facturile se păstrează cât cere legea
> (obligații contabile și fiscale).

---

## Google Play: declarația „Aplicații de sănătate” (Conținutul aplicației › Health apps)

Obligatorie pentru aplicațiile cu funcții de sănătate (și pentru categoria Sănătate și fitness).
Bifăm (denumirile exacte din formular se pot schimba; alegeți cea mai apropiată):
- **Urmărirea ciclului menstrual / sănătatea reproducerii** (period tracking): Da, harta ciclului.
- **Gestionarea stresului, relaxare, meditație** (stress management / relaxation / mindfulness): Da, meditațiile.
- Health Connect: **Nu** folosim. Dispozitiv medical / diagnostic / tratament: **Nu**.
- Cercetare medicală (human subject research): **Nu**.

Ce mai cere politica Google pentru aceste aplicații, și cum stăm:
- politică de confidențialitate publică, care descrie datele de sănătate: **trebuie adăugat paragraful din
  `privacy-policy-addendum.md`**;
- avertisment că nu e sfat medical: există („estimare, nu o metodă de contracepție”); recomandat și în descriere (este);
- datele de sănătate nu se folosesc pentru reclame și nu se vând: adevărat.

## Google Play: alte secțiuni din „Conținutul aplicației”

| Secțiune | Răspuns |
|---|---|
| Reclame | Nu conține reclame |
| Acces la aplicație | Unele funcții sunt restricționate → instrucțiunile din `review-notes.md` (emailul de recenzie + codul fix) |
| Public țintă | **18 ani și peste** (doar). Nu e destinată copiilor; asta evită programul „Families”. |
| Clasificarea conținutului | chestionarul IARC, vezi `content-rating.md` |
| Aplicație guvernamentală / funcții financiare / știri | Nu / Nu / Nu |
| Permisiunea de alarme exacte | aplicația declară `SCHEDULE_EXACT_ALARM` doar până la Android 12L (`maxSdkVersion 32`); de verificat în manifestul final (merged) că pluginul nu o readaugă pentru versiuni noi. Dacă apare, Play cere declarație. |
| Serviciu în prim-plan (foreground service) | v1 nu declară. Dacă redarea în fundal pe Android ajunge să folosească `FOREGROUND_SERVICE_MEDIA_PLAYBACK`, se completează declarația „Foreground service permissions” cu un video scurt. |
