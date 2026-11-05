# Note pentru recenzie (Apple App Review și Google Play „Acces la aplicație”)

Recenzorii Apple citesc notele în engleză, deci textul de lipit e în engleză (nu apare nicăieri în aplicație).
Mai jos, explicația în română pentru proprietară.

> Textul de pe meditațiile blocate (`TEXT_LACAT`) e neutru pe toate platformele: „Această meditație nu e inclusă în accesul tău.”, fără link, fără preț și fără trimitere spre site. Verificați-l pe build-ul din TestFlight înainte de „Submit for Review”.

## Contul de recenzie (demo)

Apple și Google cer un cont cu care recenzorul vede tot conținutul. Aplicația nu are parole: intrarea e cu un cod
de 6 cifre trimis pe email, iar recenzorul nu are acces la căsuța noastră. De aceea **serverul trebuie să
accepte un cod fix pentru o singură adresă de recenzie**, setată din admin. Cerința pentru colegul de la server:
[`../review-account.md`](../review-account.md).

Ce pregătește proprietara înainte de prima trimitere (o dată):
1. O adresă dedicată, de ex. `recenzie@adelaharu.ro` (o căsuță reală, ca să poată primi și emailul).
2. În WooCommerce, pe această adresă: o comandă manuală de 0 lei, „Finalizată”, cu produsul Inner Temple (anual)
   și una cu un curs (ex. „Wild Love”), ca recenzorul să vadă meditații deblocate și sala de curs.
   **De reînnoit înainte să expire** (abonamentul anual), altfel recenzia următoare vede totul blocat.
3. În admin › Meditațiile mele › Aplicația mobilă: adresa de recenzie + codul fix (6 cifre, ales de voi,
   nu `123456`). Acolo se poate și opri.

În **App Store Connect › versiunea › App Review Information**: bifat „Sign-in required”, User name = adresa,
Password = codul fix, plus notele de mai jos. Contact: numele, telefonul și emailul cuiva care răspunde repede.

În **Play Console › Conținutul aplicației › Acces la aplicație**: „Toate sau unele funcții sunt restricționate”
› adaugă instrucțiuni: numele „Cont de recenzie”, utilizator = adresa, parolă = codul, și textul scurt de mai jos.

## Textul pentru Apple (de lipit în „Notes”, în engleză)

```
Thank you for reviewing Adela Haru.

WHAT THE APP IS
Adela Haru is the companion app for www.adelaharu.ro (HARU TRAINING SRL, Romania). Women who already have access on the website (the "Inner Temple" meditation membership, individual meditations, or online courses) use the app to listen to their meditations (audio, some with video explanations), follow their courses and use a menstrual-cycle map. The UI is in Romanian.

HOW TO SIGN IN (no password)
1. On the first screen ("Bine ai venit"), enter the email <REVIEW_EMAIL> in "Adresa de email" and tap "Trimite-mi codul".
2. For this review account no email access is needed: type the fixed code <REVIEW_CODE> in "Codul din email" and tap "Intră".
The review account has an active Inner Temple membership and one course, so all screens are unlocked.
The cycle map ("Ciclul meu" tab) works without signing in and fully offline.

NO PURCHASES IN THE APP (Guidelines 3.1.1 / 3.1.3(a), reader app)
The app does not sell anything. There are no prices, no purchase or subscription buttons, no links or calls to action to buy on the website, and no in-app purchases. Memberships and courses are purchased by customers on our website only, independently of the app; the app only lets existing customers access content they already own. Content a user does not have access to is shown as locked, without any price, link or purchase instructions. Subscription management links are not shown on iOS.

ACCOUNT DELETION (5.1.1(v))
Profile tab ("Profil") > "Șterge contul din aplicație" > "Da, șterge" deletes the app account data on our server (devices, push tokens, cycle data, listening positions). Legally required invoices are kept.

HEALTH DATA
Cycle phases are calculated on the device. Cycle data is sent to our server only if the user turns on sync and gives explicit consent. No HealthKit, no advertising, no tracking, no data sold. The app states that phase estimates are not a contraception method and not medical advice.

NATIVE FEATURES
Background audio playback with lock-screen controls, offline downloads of meditations, local notifications at the start of each cycle phase, push notifications for new meditations and course modules, universal links from our emails (www.adelaharu.ro/app/...).

Contact: <NUME>, <TELEFON>, contact@adelaharu.ro
```

*De înlocuit:* `<REVIEW_EMAIL>`, `<REVIEW_CODE>`, `<NUME>`, `<TELEFON>`. De verificat cu colega de la aplicație că
denumirile butoanelor (verificate pe 3 nov. 2026 în `app/src`: „Trimite-mi codul”, „Intră”, „Profil”, „Șterge contul din aplicație”, „Ciclul meu”) au rămas aceleași,
și că funcțiile native enumerate există în build-ul trimis (altfel se scot din text).

## Textul pentru Google Play (Acces la aplicație, câmpul „Alte informații”)

```
Sign-in uses a one-time email code. For this review account enter the email above, tap "Trimite-mi codul" and type the fixed code given as password; no email access is needed. The cycle map works without signing in. The app sells nothing: content is purchased on www.adelaharu.ro only.
```

## Riscuri cunoscute la recenzie (de știut dinainte)

1. **Textul de pe meditațiile blocate** (`TEXT_LACAT`): rezolvat, e neutru („Această meditație nu e inclusă în accesul tău.”), fără trimitere spre site.
2. **4.2 Minimum functionality** (aplicație „doar site într-o ramă”): ne apărăm prin funcțiile native de mai sus;
   e important ca ele să meargă în build-ul de recenzie, mai ales audio în fundal și descărcarea.
3. **Portalul Stripe / „se reînnoiește”**: pe iOS nu apare niciun link de gestionare a plății (contractul spune deja asta).
   Un text informativ de tipul „Abonamentul tău e activ până la …” este în regulă.
4. **Linkurile din aplicație spre site** (termeni, confidențialitate, contact) sunt permise; niciuna nu trebuie să ducă
   pe o pagină cu prețuri sau coș. Dacă playerul Vimeo sau o pagină deschisă în aplicație are în meniu link spre
   magazin, recenzorul îl poate considera link de cumpărare. De verificat paginile `/app/*` (fără meniul site-ului).
5. Apple nu cere „Sign in with Apple”: intrarea e cu sistemul propriu (email + cod), fără Google/Facebook (4.8).
