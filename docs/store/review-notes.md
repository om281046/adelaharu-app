# Note pentru recenzie (Apple App Review și Google Play „Acces la aplicație”), v2

Recenzorii Apple citesc notele în engleză, deci textul de lipit e în engleză (nu apare nicăieri în aplicație).
Mai jos, explicația în română pentru proprietară. Stare: 18 nov. 2026, după `docs/arhitectura.md` (v2).

> **NU SE TRIMITE LA RECENZIE până nu sunt bifate toate trei.** Stare 18 nov. 2026, seara: primele două sunt în
> fragment, cu teste (`server/README.md`); se bifează după verificarea pe build-ul din TestFlight / testul intern Play.
> - [ ] **Contul de recenzie** (cod fix pentru o singură adresă) e în fragment, după `../review-account.md`, cu
>   testele trecute. Fără el, recenzorii nu pot intra (codul pleacă pe un email pe care nu-l văd) ⇒ respingere 2.1.
> - [ ] **Fără Google Analytics, pixelul Meta, atribuirea comenzilor WooCommerce și bara Complianz în aplicație**
>   (cerință fermă în `arhitectura.md`, „Ce ascunde fragmentul”), cu test. Până atunci, propozițiile „no analytics
>   or tracking” de mai jos și etichetele din `privacy-answers.md` ar fi false (Apple 5.1.2 / 2.3).
> - [ ] **Push pe iPhone:** build-ul iOS are Firebase Messaging, fișierul `GoogleService-Info.plist` (valid, din
>   secretul `GOOGLE_SERVICE_INFO_PLIST`), faza care îl copiază și `aps-environment` (workflow-ul iOS oprește încărcarea
>   în TestFlight dacă lipsește oricare, cât timp `push_ios` e bifat; corectat 18 nov., seara: înainte, verificarea
>   căuta doar numele în `project.pbxproj`, unde apare mereu, și trecea și fără fișier).
>   Dacă versiunea pleacă fără push pe iPhone: din notele Apple se scoate funcția 5, iar din fișa App Store rândul
>   despre notificări (`listing.md`).

**Cele două riscuri mari la Apple, pe scurt:**
1. **4.2 Minimum functionality / 4.2.2 („site într-o ramă”).** Aplicația v2 deschide Spațiul meu de pe site.
   Ne apărăm cu funcții care există doar în aplicație (lista de mai jos) și cu faptul că aplicația e un loc închis,
   doar pentru contul clientei (fără meniul site-ului, fără magazin). Toate trebuie să meargă în build-ul trimis.
2. **3.1.1 / 3.1.3(a) („reader app”).** Nimic de cumpărat, niciun link sau îndemn spre cumpărare, nicăieri.
   O singură scăpare (un preț, „Reînnoiește”, un link spre produs, banda de Black Friday) aduce respingerea.

## Contul de recenzie (demo)

Intrarea e cu un cod de 6 cifre trimis pe email; recenzorul nu are acces la căsuța noastră. Fragmentul
`ah-sp-aplicatie.php` **trebuie să accepte** un cod fix pentru o singură adresă de recenzie, setată din admin
(18 nov., seara: în fragment, secțiunea 9, cu teste; de verificat în TestFlight). Cerința exactă
pentru coleg și pașii proprietarei (comenzile manuale „Acces gratuit”, cartea „Finalizată”, reînnoirea anuală):
[`../review-account.md`](../review-account.md).

În **App Store Connect › versiunea › App Review Information**: bifat „Sign-in required”, User name = adresa de
recenzie, Password = codul fix, plus notele de mai jos. Contact: numele, telefonul și emailul cuiva care răspunde
repede (în engleză). Opțional, dar util la 4.2: un **video scurt** (1–2 minute, înregistrat pe iPhone) care arată
descărcarea unei meditații, ascultarea cu ecranul blocat, modul avion cu pagina offline și o notificare; se
încarcă la „Attachment” (de verificat dacă formularul îl mai oferă) sau ca link nelistat în note.

În **Play Console › Conținutul aplicației › Acces la aplicație**: „Toate sau unele funcții sunt restricționate” ›
adaugă instrucțiuni: numele „Cont de recenzie”, utilizator = adresa, parolă = codul, și textul scurt de mai jos.

## Textul pentru Apple (de lipit în „Notes”, în engleză)

```
Thank you for reviewing Adela Haru.

WHAT THE APP IS
Adela Haru is the members' app of www.adelaharu.ro (HARU TRAINING SRL, Romania), for women who already have access to our content: online courses, the "Inner Temple" meditation membership, e-books, live meetings and their recordings, a podcast, and a menstrual-cycle map. The interface is in Romanian. The app opens only the member area of each user's account ("Spațiul meu"); the public website, shop and blog are not reachable from the app.

HOW TO SIGN IN (no password)
1. On the first screen ("Spațiul meu"), enter <REVIEW_EMAIL> and tap "Trimite-mi codul" (Send me the code).
2. No email access is needed for this review account: type the fixed code <REVIEW_CODE> and tap "Intră" (Sign in).
The review account has the Inner Temple membership, one course and one e-book, so all sections are unlocked.
Sections (bottom bar and "Mai mult" = More): Acasă (Home), Ciclul (cycle map), Cursuri (courses), Inner Temple (meditations), Cărți (e-books), Abonamente (membership status only), Podcast, Mai mult (More: settings, notifications, downloads, account deletion).

NATIVE FEATURES (Guideline 4.2) - things the website cannot do
1. Offline meditations: in Inner Temple, tap "Descarcă" (Download) next to a meditation. The audio file is stored in the app's private storage (excluded from backup) and plays without internet.
2. Offline screen: turn on Airplane Mode and reopen the app. A local, bundled screen shows today's cycle phase (calculated on the device) and the downloaded meditations with a player, plus a "try again" button.
3. Background audio and lock-screen controls: start a meditation in Inner Temple (online or downloaded) and lock the phone. Title, artwork, play/pause and 15-second skip appear on the lock screen and in Control Center; playback continues in the background.
4. Local notifications for the cycle: open "Mai mult" (More) > "Aplicația" (App), turn on "Mementourile ciclului" (cycle reminders) and allow notifications when asked. Then, in "Ciclul", enter the first day of the last period: the app schedules on-device reminders at the start of each phase for the next three cycles (calculated on the phone, no server involved). The same switch turns them off.
5. Push notifications (opt-in): the explanation screen ("Vrei să-ți dăm de veste?") appears automatically from the second launch of the app, never at the first; it can also be turned on at any time in "Mai mult" > "Aplicația" > "Notificări". Notifications: a new meditation, a new recording of a live meeting the user attended, a live meeting starting in 15 minutes. Tapping a notification opens the exact item. Accepting push also allows the cycle reminders from feature 4.
6. Universal links: personal links in our emails (www.adelaharu.ro/spatiul-meu/...) open directly in the app, already signed in.
7. System apps: Zoom, WhatsApp and podcast links open the corresponding apps; the app itself stays inside the member area.

NO PURCHASES IN THE APP (Guidelines 3.1.1 / 3.1.3(a), reader app)
The app does not sell anything and has no in-app purchases. There are no prices, no purchase, renewal, upgrade or subscription buttons, no links to product or checkout pages, no subscription-management portal, no gift cards, referral offers or promotional campaigns, and no text telling users where or how to buy. All courses, memberships and e-books are purchased by customers on our website only, independently of the app; the app lets existing customers access what they already own. Content a user does not own simply does not appear, or appears without any price or link. Membership status is shown as plain information ("active until ..."), without any action.

HEALTH DATA (5.1.1, 5.1.3)
The cycle map calculates phases on the device. Cycle dates and daily notes are stored in the user's account on our server only if she turns this on and gives explicit consent; this in-account storage is encrypted at rest (AES-256-GCM) and can be deleted at any time. Separately, she can subscribe to cycle-phase emails (opt-in, with consent): for that we store her email, first name, the start date of her last period and her cycle and period lengths, used only to send those emails; she can unsubscribe from any email, and account deletion removes it. No HealthKit, no advertising, no analytics or tracking scripts inside the app (the website's analytics and advertising pixel are not loaded in the app), no data sold. Embedded Vimeo players use Vimeo's "do not track" mode. The app states that phase estimates are not a contraception method and not medical advice.

PARTNER ACCESS (optional, initiated and controlled by her)
In "Ciclul" she can invite her partner by first name and email, with a separate, mandatory consent checkbox ("I agree that [name] sees my phase today and the estimated dates of the next phases. I can stop this at any time."). He receives a personal link that opens a simple partner space in the same app: today's phase, how to support her, short educational pages and a reminder at the start of each phase. He never sees her notes, symptoms, her situation or the exact days she logged; the server only computes the phase. She sees who has access and can stop it instantly ("Oprește accesul"); he can delete his access from his own page in the app (gear icon > "Șterge accesul meu" (Delete my access)). When she deletes her account or her cycle data, all partner access linked to her is deleted too. The partner account has no password and no purchases.
Please try the partner space BEFORE deleting the review account: deleting her account removes all partner access by design, and the partner link then shows "this link is no longer valid".
To see the partner space, use this personal link: <REVIEW_PARTNER_LINK>
iOS opens links in the app only when they are tapped, not when they are typed or pasted into the Safari address bar. Please either (a) paste the link into Notes or Messages on the test iPhone and tap it there (it opens the app), or (b) in the app, while signed out, tap "Sunt partenerul ei" (I am her partner) on the sign-in screen, paste the link into the field "Sau lipește aici linkul copiat" (Or paste the copied link here) and tap "Deschide linkul" (Open the link).
The partner space has two tabs, "Azi" (Today) and "Învață" (Learn), and settings under the gear icon. If you are still signed in to the review account on the same phone, the phone's push notifications stay with her account, so the partner page offers phase emails instead of notifications; "Deschide Spațiul meu" in the partner settings (or reopening the app) returns to her account. On a phone where she is not signed in, the partner page offers notifications at the start of each phase. "Ieși" (Sign out) in the partner settings signs the partner out of this phone; the link signs him in again.
(On the sign-in screen, "Sunt partenerul ei" (I am her partner) explains how partners sign in and accepts a pasted link.)

ACCOUNT DELETION (5.1.1(v))
"Mai mult" (More) > "Aplicația" > "Șterge contul" (Delete account) > check "Am înțeles..." > "Șterge contul". This deletes the user's personal data on our server (cycle data and daily notes, the cycle-phase email subscription, member-area registration, connected devices, push tokens, course reminders, live-meeting registration and any partner access she granted, including the partners' data and push tokens) and, on the phone, downloads, cycle data and reminders. The user is signed out.
Orders and invoices are kept because Romanian accounting law requires it; the user is told this before confirming. Access to previously purchased content is tied to those purchase records, so if you sign in again with the same email after deleting the account, the purchased courses, meditations and e-books are visible again (as they would be on our website), but none of the deleted personal data comes back: no cycle data, notes, devices or push tokens.

Contact: <NUME>, <TELEFON>, contact@adelaharu.ro
```

*De înlocuit:* `<REVIEW_EMAIL>`, `<REVIEW_CODE>`, `<REVIEW_PARTNER_LINK>` (vezi `review-account.md`, „Partenerul de recenzie”), `<NUME>`, `<TELEFON>`. Blocul PARTNER ACCESS (cu tot ce spune despre partener, inclusiv ștergerea accesului lui) și mențiunea „partner access” din ACCOUNT DELETION se trimit doar dacă fragmentul `ah-sp-partener.php` („Pentru el”, `docs/partener.md`) e activ pe site când se trimite la recenzie; altfel se scot. Ștergerea contului ei îi șterge partenerii (18 nov., seara: în `ah_spa_sterge_cont`, cu test). De verificat pe build: linkul partenerului, atins în Notițe / Mesaje, deschide aplicația (AASA / `assetlinks.json` cu `/pentru-el`); lipit în câmpul de la „Sunt partenerul ei” (fără cont în aplicație) deschide pagina lui tot în aplicație; Azi / Învață / rotița, „Deschide Spațiul meu” pe telefonul ei, „Șterge accesul meu”. Blocul PARTNER ACCESS stă **înaintea** lui ACCOUNT DELETION, cu rugămintea de a încerca partenerul înainte de ștergere: recenzorii testează des întâi ștergerea, iar ștergerea contului ei anulează linkul partenerului (corect, dar ar arăta ca o funcție stricată, 2.1). După orice ștergere a contului de recenzie, partenerul se reface după `review-account.md`, „Partenerul de recenzie”, punctul 3.
*De verificat pe build-ul din TestFlight înainte de „Submit for Review”* (altfel se scoate rândul din text, Apple 2.3.1):
- denumirile: „Trimite-mi codul”, „Intră” (235, verificate 18 nov.), „Descarcă”, „Mai mult” › „Aplicația” ›
  „Șterge contul”, caseta „Am înțeles…” (fragmentul, verificate 18 nov.); bara de jos arată cel mult două dintre
  Cursuri / Inner Temple / Cărți, restul sunt în meniul „Mai mult” sau pe Acasă;
- fiecare funcție nativă 1–7 merge pe un iPhone real (mai ales 1–4, la care se uită recenzorul la 4.2); pe Android,
  funcția 3 și pentru o meditație pornită **online** (puntea trimite starea în pluginul `AhMedia`: notificarea
  „Adela Haru” cu butoane apare, sunetul continuă cu ecranul blocat);
- după „Șterge contul” pe contul de recenzie, intrarea din nou cu codul fix merge și arată conținutul cumpărat,
  fără ciclu și notițe (exact ce spune blocul ACCOUNT DELETION); apoi refaceți ciclul și partenerul de recenzie
  (`review-account.md`, „Partenerul de recenzie”, punctul 3) înainte de trimitere;
- „no analytics or tracking SDKs” e adevărat **doar dacă** fragmentul nu încarcă în aplicație Google Analytics,
  pixelul Meta (facebook-for-woocommerce) și bara Complianz. Vezi „Riscuri”, punctul 3.

## Textul pentru Google Play (Acces la aplicație, câmpul „Alte informații”)

```
Sign-in uses a one-time email code. For this review account, enter the email above, tap "Trimite-mi codul" and type the fixed code given as the password, then tap "Intră"; no email access is needed. The account has a course, the Inner Temple meditation membership and an e-book. The app sells nothing: content is purchased on www.adelaharu.ro only, and the app shows no prices or purchase links.
```

A doua instrucțiune (doar cu `ah-sp-partener.php` activ), numele „Cont partener”, utilizator „—”, parola „—”, cu textul:

```
Partner mode: a woman can invite her partner (Ciclul > "Pentru el", with a separate consent checkbox) to see only her current cycle phase and estimated next phases. The partner has no password: he signs in with a personal link from his invitation email. To see the partner space (Today, Learn, settings with sign-out and "delete my access"), tap this link on the device, for example after pasting it into a note or message (a link typed into the browser address bar opens the website, not the app): <REVIEW_PARTNER_LINK>. Or, in the app while signed out, tap "Sunt partenerul ei" on the sign-in screen and paste the link into the field there. Please try this before deleting the review account: deleting her account removes partner access by design. If the review account is signed in on the same device, notifications stay with her account and the partner page offers phase emails instead.
```

## De ce nu e „un site împachetat” (pentru discuția cu Apple, dacă vine o respingere 4.2)

Răspunsul în Resolution Center, în engleză, pe scurt, cu video:
- aplicația nu arată site-ul public: doar zona de membru a fiecărei cliente, cu navigația ei proprie (bara de jos),
  fără meniul site-ului, fără magazin și fără blog; orice pagină în afara zonei se întoarce în cont;
- funcțiile 1–7 de mai sus nu există pe web: descărcare offline în stocarea privată, pagină offline locală, audio
  în fundal cu comenzi pe ecranul blocat, notificări locale calculate pe telefon, push, linkuri universale;
- conținutul e personal și se schimbă zilnic (meditații noi, înregistrări ale întâlnirilor, module de curs), de aceea
  vine de pe server: e modelul oricărei aplicații de abonament (exemplu de formulare: „like other membership
  apps, content is served from our backend so new meditations appear without an app update”).
Dacă Apple insistă, planul B (în ordinea costului): un ecran nativ de start cu meditațiile descărcate și faza zilei
(pagina offline, folosită și online ca „Acasă” a aplicației), apoi un player nativ pentru Inner Temple.

## Riscuri cunoscute la recenzie (de știut dinainte)

1. **Orice urmă de cumpărare în pagină** (3.1.1). Fragmentul ascunde la sursă și cu un script care urmărește
   desenările (`arhitectura.md`, „Ce ascunde fragmentul”). Înainte de fiecare trimitere: trecere prin TOATE
   secțiunile cu contul de recenzie, pe iPhone și Android, căutând: preț, „lei”, „€”, „cumpără”, „comandă”,
   „abonează”, „reînnoiește”, „Abonamentul meu” (portal Stripe), „card cadou”, „Recomandă”, „Black Friday”,
   „Women Empower Women”, linkuri spre `/produs/`, `/cos/`, `/finalizare-comanda/`, sugestiile de sub înregistrări
   (279), butoanele din sala de curs (233). Și ecranul de intrare: textul din 235 „Scrie adresa de email cu care ai
   cumpărat” nu e un îndemn, dar recomand ca fragmentul să-l înlocuiască în aplicație cu „Scrie adresa de email a
   contului tău de pe adelaharu.ro.” (CIORNĂ, pentru Adela).
2. **Emailurile site-ului.** Apple nu le vede, dar o notificare push nu are voie să ducă spre cumpărare: push-urile
   (meditație nouă, înregistrare, întâlnire) deschid doar conținut deja accesibil.
3. **Analiză și urmărire în WebView.** Pagina publică încarcă Google Analytics (blocat până la acord, prin
   Complianz) și scriptul `facebook-for-woocommerce` (pixelul Meta). Dacă în aplicație se acceptă „Marketing”,
   pixelul Meta = **urmărire** în sensul Apple ⇒ ar trebui ATT (fereastra „Permite urmărirea”) și alte etichete.
   Cerință fermă pentru fragment (`arhitectura.md`): în aplicație nu se încarcă GA, pixelul Meta, atribuirea
   comenzilor WooCommerce și nici bara de cookie-uri (rămân doar cookie-urile funcționale); evenimentele `ev()`
   din 235 și `masoara()` din 266 nu mai au unde pleca fără `gtag`. **18 nov., seara: implementat în fragment
   (`ah-sp-aplicatie.php` 2.1.0, secțiunea 10), cu teste; de verificat pe build-ul din TestFlight / testul intern cu
   Web Inspector sau `chrome://inspect`** (nicio cerere spre `googletagmanager.com`, `google-analytics.com`,
   `connect.facebook.net`, `facebook.com/tr`; nicio bară Complianz). Etichetele din `privacy-answers.md` presupun asta.
4. **Vimeo** (lecții, explicații video): se redă în cadrul paginii (`player.vimeo.com` e permis în aplicație).
   Videourile cu restricție de domeniu cer ca pagina să fie pe `www.adelaharu.ro` (este). Embed-urile site-ului au
   deja `dnt=1` (235, 44, 51, 265: fără cookie-uri și statistici Vimeo); fragmentul îl adaugă în aplicație oricărui
   iframe Vimeo care nu-l are (cerință în `arhitectura.md`). De verificat redarea pe
   iPhone și Android în build-ul de recenzie; un video care nu pornește arată a aplicație „ruptă” (2.1).
5. **Sesiunea pe iPhone.** Cookie-ul `ah_sp` e pus de server (HttpOnly, un an), pe domeniul primar al WebView-ului,
   deci limita ITP de 7 zile (care lovește cookie-urile scrise din JavaScript și `localStorage`) nu ar trebui să-l
   atingă. Dacă recenzorul șterge aplicația și o reinstalează, intră din nou cu codul. De încercat: intrare,
   închiderea aplicației, deschidere după o zi. (Riscul pentru `localStorage`, unde stă ciclul: `strategie-v2.md`.)
6. **Linkurile din aplicație spre site** (termeni, confidențialitate, cookie-uri) sunt permise; niciuna nu trebuie
   să ducă pe o pagină cu prețuri sau coș. Restul site-ului se întoarce în cont (302).
7. **Notificările:** permisiunea se cere o singură dată, cu ecran de explicație, de la a doua deschidere (Apple 4.5.4:
   push-ul nu e obligatoriu și nu se folosește pentru reclame). Pe iPhone, ecranul apare doar dacă build-ul are
   Firebase (`AhApp.info().push`); altfel nici întrebare, nici funcția 5 în note.
8. **Datele de sănătate și partenerul** (5.1.3, 5.1.1): nu în iCloud, nu pentru reclame. Contul „Pentru el” arată
   unei alte persoane o informație derivată din datele ei de sănătate (faza de azi și datele estimate): e în regulă
   doar cu acordul ei explicit, separat (bifa din invitație), minim (fără notițe, simptome, situație) și revocabil pe
   loc. Recenzorul poate întreba; textul de mai sus răspunde. Și contul lui are ștergere în aplicație (5.1.1(v)).
   Linkul vechi „Pentru el” (`?ciclul-ei#d=…`) are datele doar în fragmentul URL-ului, pe care browserul nu îl
   trimite la server.
9. Apple nu cere „Sign in with Apple” (4.8): intrarea e cu sistemul propriu (email + cod), fără Google/Facebook.
10. **iPad:** proiectul are acum `TARGETED_DEVICE_FAMILY = 1,2`. Dacă rămâne așa, Apple testează și pe iPad și cere
    capturi de iPad; recomand doar iPhone (`1`) pentru prima versiune (decizia colegei de la aplicație).
11. **Ștergerea contului care „revine”** (5.1.1(v)). Recenzorii șterg des contul și apoi intră din nou. Pentru că
    accesul vine din comenzi (păstrate legal), după reintrare conținutul cumpărat apare din nou; nota ACCOUNT
    DELETION explică asta dinainte, ca să nu pară o simplă „resetare”. Rămân pe site și un eventual utilizator
    WordPress/WooCommerce creat la cumpărare și numele de pe comenzi (date de facturare). Dacă Mircea vrea și
    ștergerea/anonimizarea utilizatorului WordPress de client (doar rolul `customer`, fără alte roluri), e o
    schimbare în `ah_spa_sterge_cont`, de cerut colegului de la fragment; atunci nota se completează.
