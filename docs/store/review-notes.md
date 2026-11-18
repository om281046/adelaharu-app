# Note pentru recenzie (Apple App Review și Google Play „Acces la aplicație”), v2

Recenzorii Apple citesc notele în engleză, deci textul de lipit e în engleză (nu apare nicăieri în aplicație).
Mai jos, explicația în română pentru proprietară. Stare: 18 nov. 2026, după `docs/arhitectura.md` (v2).

**Cele două riscuri mari la Apple, pe scurt:**
1. **4.2 Minimum functionality / 4.2.2 („site într-o ramă”).** Aplicația v2 deschide Spațiul meu de pe site.
   Ne apărăm cu funcții care există doar în aplicație (lista de mai jos) și cu faptul că aplicația e un loc închis,
   doar pentru contul clientei (fără meniul site-ului, fără magazin). Toate trebuie să meargă în build-ul trimis.
2. **3.1.1 / 3.1.3(a) („reader app”).** Nimic de cumpărat, niciun link sau îndemn spre cumpărare, nicăieri.
   O singură scăpare (un preț, „Reînnoiește”, un link spre produs, banda de Black Friday) aduce respingerea.

## Contul de recenzie (demo)

Intrarea e cu un cod de 6 cifre trimis pe email; recenzorul nu are acces la căsuța noastră. Fragmentul
`ah-sp-aplicatie.php` acceptă **un cod fix pentru o singură adresă de recenzie**, setată din admin. Cerința exactă
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
3. Background audio and lock-screen controls: start a meditation and lock the phone. Title, artwork, play/pause and 15-second skip appear on the lock screen and in Control Center; playback continues in the background.
4. Local notifications for the cycle: in "Ciclul", after entering the first day of the last period, the app schedules on-device reminders at the start of each phase for the next three cycles (no server involved). They can be turned off in "Mai mult" > "Aplicația".
5. Push notifications (opt-in, asked once with an explanation screen, never at first launch): a new meditation, a new recording of a live meeting the user attended, a live meeting starting in 15 minutes. Tapping a notification opens the exact item.
6. Universal links: personal links in our emails (www.adelaharu.ro/spatiul-meu/...) open directly in the app, already signed in.
7. System apps: Zoom, WhatsApp and podcast links open the corresponding apps; the app itself stays inside the member area.

NO PURCHASES IN THE APP (Guidelines 3.1.1 / 3.1.3(a), reader app)
The app does not sell anything and has no in-app purchases. There are no prices, no purchase, renewal, upgrade or subscription buttons, no links to product or checkout pages, no subscription-management portal, no gift cards, referral offers or promotional campaigns, and no text telling users where or how to buy. All courses, memberships and e-books are purchased by customers on our website only, independently of the app; the app lets existing customers access what they already own. Content a user does not own simply does not appear, or appears without any price or link. Membership status is shown as plain information ("active until ..."), without any action.

ACCOUNT DELETION (5.1.1(v))
"Mai mult" (More) > "Aplicația" > "Șterge contul" (Delete account) > check "Am înțeles..." > "Șterge contul". This deletes the user's account data on our server (cycle data and notes, member-area registration, connected devices, push tokens) and, on the phone, downloads, cycle data and reminders. Invoices and orders are kept because Romanian law requires it; the user is told this before confirming.

HEALTH DATA (5.1.1, 5.1.3)
The cycle map calculates phases on the device. Cycle dates and daily notes are stored in the user's account on our server only if she turns this on and gives explicit consent; they are encrypted at rest (AES-256-GCM) and can be deleted at any time. An optional "link for him" lets her share her phase calendar with a partner: the data travels only inside the link's URL fragment (after "#"), which browsers never send to a server. No HealthKit, no advertising, no analytics or tracking SDKs, no data sold or shared. The app states that phase estimates are not a contraception method and not medical advice.

Contact: <NUME>, <TELEFON>, contact@adelaharu.ro
```

*De înlocuit:* `<REVIEW_EMAIL>`, `<REVIEW_CODE>`, `<NUME>`, `<TELEFON>`.
*De verificat pe build-ul din TestFlight înainte de „Submit for Review”* (altfel se scoate rândul din text, Apple 2.3.1):
- denumirile: „Trimite-mi codul”, „Intră” (235, verificate 18 nov.), „Descarcă”, „Mai mult” › „Aplicația” ›
  „Șterge contul”, caseta „Am înțeles…” (fragmentul, verificate 18 nov.); bara de jos arată cel mult două dintre
  Cursuri / Inner Temple / Cărți, restul sunt în meniul „Mai mult” sau pe Acasă;
- fiecare funcție nativă 1–7 merge pe un iPhone real (mai ales 1–4, la care se uită recenzorul la 4.2);
- „no analytics or tracking SDKs” e adevărat **doar dacă** fragmentul nu încarcă în aplicație Google Analytics,
  pixelul Meta (facebook-for-woocommerce) și bara Complianz. Vezi „Riscuri”, punctul 3.

## Textul pentru Google Play (Acces la aplicație, câmpul „Alte informații”)

```
Sign-in uses a one-time email code. For this review account, enter the email above, tap "Trimite-mi codul" and type the fixed code given as the password, then tap "Intră"; no email access is needed. The account has a course, the Inner Temple meditation membership and an e-book. The app sells nothing: content is purchased on www.adelaharu.ro only, and the app shows no prices or purchase links.
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
   Cerință pentru fragment: în aplicație nu se încarcă GA, pixelul Meta și nici bara de cookie-uri (rămân doar
   cookie-urile funcționale). Etichetele din `privacy-answers.md` presupun asta.
4. **Vimeo** (lecții, explicații video): se redă în cadrul paginii (`player.vimeo.com` e permis în aplicație).
   Videourile cu restricție de domeniu cer ca pagina să fie pe `www.adelaharu.ro` (este). De verificat redarea pe
   iPhone și Android în build-ul de recenzie; un video care nu pornește arată a aplicație „ruptă” (2.1).
5. **Sesiunea pe iPhone.** Cookie-ul `ah_sp` e pus de server (HttpOnly, un an), pe domeniul primar al WebView-ului,
   deci limita ITP de 7 zile (care lovește cookie-urile scrise din JavaScript și `localStorage`) nu ar trebui să-l
   atingă. Dacă recenzorul șterge aplicația și o reinstalează, intră din nou cu codul. De încercat: intrare,
   închiderea aplicației, deschidere după o zi. (Riscul pentru `localStorage`, unde stă ciclul: `strategie-v2.md`.)
6. **Linkurile din aplicație spre site** (termeni, confidențialitate, cookie-uri) sunt permise; niciuna nu trebuie
   să ducă pe o pagină cu prețuri sau coș. Restul site-ului se întoarce în cont (302).
7. **Notificările:** permisiunea se cere o singură dată, cu ecran de explicație, nu la prima deschidere (Apple 4.5.4:
   push-ul nu e obligatoriu și nu se folosește pentru reclame).
8. **Datele de sănătate** (5.1.3): nu în iCloud, nu pentru reclame; „linkul pentru el” e o partajare făcută de ea,
   cu datele doar în fragmentul URL-ului. Nu e un „terț” care primește date de la noi.
9. Apple nu cere „Sign in with Apple” (4.8): intrarea e cu sistemul propriu (email + cod), fără Google/Facebook.
10. **iPad:** proiectul are acum `TARGETED_DEVICE_FAMILY = 1,2`. Dacă rămâne așa, Apple testează și pe iPad și cere
    capturi de iPad; recomand doar iPhone (`1`) pentru prima versiune (decizia colegei de la aplicație).
