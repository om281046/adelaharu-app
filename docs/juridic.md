# Partea juridică: aplicația și contul „Pentru el”

Scris pe 18 nov. 2026 pentru HARU TRAINING SRL (operatorul adelaharu.ro). Acoperă ce trebuie hotărât, scris și
păstrat ca dovadă înainte ca aplicația și contul pentru partener să ajungă pe site și în magazine. E scris după
codul din acest repo (`server/ah-sp-aplicatie.php`, `server/ah-sp-partener.php`, fragmentul 235 de pe site).
Textul pentru public e în `docs/store/privacy-policy-addendum.md`. Nu înlocuiește o consultație juridică: dacă
firma lucrează deja cu un consultant GDPR, e bine să citească măcar secțiunile 1, 2 și 7.

---

## 1. Temeiurile legale (art. 6 și art. 9 GDPR)

| Ce prelucrăm | Al cui | Temei | De ce acesta |
|---|---|---|---|
| Contul, intrarea cu cod, cursurile, meditațiile, dispozitivele | ea (clienta) | art. 6(1)(b), contract | sunt serviciile cumpărate de ea |
| Datele ciclului în cont (zile, lungime, situație, notițe) | ea | art. 9(2)(a), consimțământ explicit | date privind sănătatea; acord separat, cerut înainte de prima salvare |
| Arătarea fazei ei către partener | ea | art. 9(2)(a), consimțământ explicit, **separat** | dezvăluire a datelor de sănătate către o persoană aleasă de ea; bifă proprie, cu numele lui în text, ora acordului păstrată |
| Emailul de invitație către el | el | art. 6(1)(f), interes legitim | ea cere trimiterea; e un singur email, el nu trebuie să facă nimic, fără link nu vede nimic și nu primește alte mesaje |
| Pagina lui (link, cookie, ultima vizită, retrimiterea linkului) | el | art. 6(1)(b), contract | după ce deschide linkul, el folosește un serviciu gratuit pe care l-a cerut |
| Notificările și emailurile lui la fiecare fază | el | art. 6(1)(a), consimțământ | sunt opționale, le pornește el, le oprește oricând (link de dezabonare, buton în pagină) |
| Tokenurile de notificări (ea și el) | amândoi | art. 6(1)(b) | trimise doar după permisiunea dată în telefon |
| Facturi, comenzi | ea | art. 6(1)(c), obligație legală | Legea contabilității 82/1991, 10 ani |

**Hotărârea pentru „temeiul datelor lui”:** am ales (f) pentru invitație și (b) pentru pagina lui, în locul unui
singur (f) pentru tot. (b) e mai solid din momentul în care el folosește activ pagina, iar (f) acoperă singurul pas
în care încă nu a ales nimic: primirea invitației. Testul de echilibru pentru (f), pe scurt:
- interesul: să-i transmitem invitația pe care ea a cerut-o;
- necesitatea: un singur email, cu prenumele lui și numele ei, fără nicio dată de sănătate în el;
- așteptările lui: un email de la cineva apropiat, cu explicație clară și fără alte mesaje dacă îl ignoră;
- garanții: adresa lui e criptată, se șterge la 30 de zile după oprire sau pe loc la cererea lui, ea poate invita cel
  mult 2 persoane, iar retrimiterea e limitată.

**Ce primește el nu devine „al lui” în sensul GDPR.** Ce vede el despre ea e prelucrat de el în scop personal
(excepția casnică, art. 2(2)(c)). Răspunderea noastră e să-i arătăm doar ce a acceptat ea și să oprim pe loc când
își retrage acordul (art. 7(3)). Codul face asta: oprirea șterge tokenurile, iar pagina, REST-ul și cronul citesc
starea pe loc.

## 2. Consimțământul ei: ce trebuie păstrat ca dovadă (art. 7(1))

Pentru fiecare invitație, codul păstrează deja momentul bifei (`acord`). De adăugat pe lângă:
- **versiunea textului de acord.** O constantă, de exemplu `AH_SPP_ACORD_V = '2026-11-18'`, salvată lângă `acord`.
  Dacă textul se schimbă, versiunea nouă se cere din nou la următoarea invitație;
- textul exact, păstrat în acest fișier:
  „Sunt de acord ca [prenume] să vadă faza mea de azi și datele estimate ale fazelor următoare. Pot opri oricând.”

Acordul e valabil fiindcă e:
- **specific**: numele lui e în text;
- **separat**: bifa nu e legată de acordul pentru salvarea ciclului în cont, și niciun buton nu merge fără ea;
- **informat**: textul spune ce vede el și ce nu vede;
- **retras la fel de ușor cum a fost dat**: un buton, efect imediat.

## 3. Informarea lui (art. 14)

El nu e client, deci trebuie informat la primul contact. Emailul de invitație, cel de link nou și cele de fază
spun:
- cine e operatorul (HARU TRAINING SRL);
- de ce a primit mesajul (ea i-a scris adresa);
- ce se întâmplă dacă îl ignoră;
- unde e Politica de confidențialitate (link).

Pagina lui are și secțiunea „Ce vezi aici”, cu ce păstrăm despre el. Politica publică (adaosul) are un paragraf
dedicat lui.

## 4. Minimizare și protecție din proiectare (art. 5(1)(c), art. 25)

Ce face deja codul și trebuie păstrat la orice schimbare:
- **Ce primește el:** doar faza de azi, până când ține și datele estimate ale fazelor următoare. Nu primește
  notițe, simptome, situația ei (anticoncepționale, sarcină), zilele notate de ea și nici „ziua N din L”, din care
  s-ar deduce data menstruației.
- **Întârzierea menstruației nu i se arată.** El nu vede o zi a menstruației următoare, ci o fereastră de ±2 zile
  în jurul zilei estimate. În ziua dinainte, în ziua estimată și după ea, pagina lui arată exact la fel (testat).
  Dacă menstruația întârzie, el vede mai departe faza de toamnă, „până la următoarea menstruație”, fără nicio dată,
  la fel pentru oricâte zile, până la 10 zile după ziua estimată; apoi vede mesajul neutru al pauzei, fără motiv.
  O întârziere poate indica o sarcină, iar ea nu și-a dat acordul pentru asta.
- **Schimbarea situației ei nu i se arată pe loc.** Când ea alege contracepție hormonală sau sarcină, el vede pauza
  abia la următoarea schimbare de fază sau după 1–3 zile, alese aleatoriu și păstrate pe server. Așa nu poate lega
  pauza de un moment anume. Același mesaj neutru apare pentru orice motiv al pauzei.
- **Datele lui:** emailul e criptat (AES-256-GCM, cheie separată de cea a ciclului). Din versiunea 1.3.0 cheia nu
  mai stă în baza de date: e în `wp-config.php` (constanta `AH_SPP_CHEIE`) sau derivată din sărurile WordPress de
  acolo. Linkul e păstrat doar ca sha256. Ea e identificată printr-o cheie HMAC, nu prin email.
- **Cererile care schimbă ceva** (invitația, oprirea, ștergerea, notificările) vin doar din paginile site-ului:
  pe lângă cookie-urile SameSite, cer un antet pe care alt site nu-l poate trimite.
- **Retrimiterea linkului:** răspunde la fel, în același timp, pentru orice adresă. Așa nu se poate afla cine a
  fost invitat.

## 5. Cookie-uri și bannerul de cookie-uri în aplicație

**Concluzie:** în aplicație nu e nevoie de banner, cât timp acolo nu rulează nimic din ce cere consimțământ.

**Temei:** art. 5(3) din Directiva 2002/58 și art. 4(5) din Legea 506/2004 scutesc de consimțământ stocarea
„strict necesară” pentru un serviciu cerut explicit de utilizator. În aplicație rămân doar:
- `ah_sp`: sesiunea ei;
- `ah_spp`: sesiunea lui;
- `ah_app`: semnalul „suntem în aplicație”;
- cookie-urile de sesiune WordPress/WooCommerce;
- tokenul de notificări, creat doar după permisiunea din telefon.

Tot ce cere consimțământ e oprit în aplicație de `ah-sp-aplicatie.php` 2.1: Google Analytics, pixelul Meta, Clarity
și atribuirea WooCommerce. Vimeo rulează cu `dnt=1`, iar Firebase Analytics e dezactivat.

**Condiția care trebuie respectată:** orice instrument nou de analiză sau reclamă pus pe site trebuie adăugat în
lista de trackere a fragmentului **înainte** de următoarea versiune a aplicației. Altfel ar rula în aplicație fără
banner. Regula e scrisă și în `server/README.md`.

În browser, site-ul rămâne cu bannerul Complianz, ca acum.

## 6. Nu este dispozitiv medical (Regulamentul (UE) 2017/745)

Un software destinat contracepției sau diagnosticului e dispozitiv medical și are nevoie de marcaj CE (cum au
aplicațiile de contracepție certificate). Destinația declarată a aplicației e educativă și de bună-stare:
- fazele sunt prezentate ca anotimpuri;
- peste tot scrie că e o estimare și că nu e metodă de contracepție;
- nu se calculează „zile sigure” și nu se pune niciun diagnostic.

Ca să rămână așa:
- în magazine, pe site și în aplicație nu se scrie că aplicația „previne sarcina”, „arată zilele fertile ca să
  eviți sarcina” sau „detectează probleme”;
- nota din site rămâne lângă orice estimare: „Este o estimare […] nu o folosi ca metodă de contracepție.”;
- descrierea din magazine (`docs/store/listing.md`) se verifică după regula aceasta înainte de trimitere.

## 7. Registrul activităților de prelucrare (art. 30): rânduri noi

De adăugat în registrul firmei (dacă nu există, acesta e începutul lui):

| Activitate | Persoane | Date | Temei | Destinatari / împuterniciți | Transfer în afara SEE | Păstrare |
|---|---|---|---|---|---|---|
| Aplicația mobilă (cont, cursuri, meditații) | cliente | email, prenume, dispozitive, progres | 6(1)(b) | găzduirea site-ului; Vimeo (video) | Vimeo: SUA, Cadrul UE-SUA / CCS | cât există contul |
| Notificări push | cliente, parteneri | token, platformă, versiune | 6(1)(b), 6(1)(a) pentru el | Google Firebase Cloud Messaging | SUA, Cadrul UE-SUA / CCS | până la dezinstalare, ieșire din cont sau token invalid |
| Ciclul în cont | cliente | zile, lungime, situație, notițe (criptate) | 9(2)(a) | găzduirea site-ului | nu | până le șterge ea; propunere mai jos |
| Emailurile fazelor (ea) | cliente | email, prenume, prima zi, lungimi | 9(2)(a) | găzduirea, serviciul de email | după serviciul de email | până la dezabonare |
| Accesul partenerului | cliente, parteneri | vezi secțiunea 1 | 9(2)(a) / 6(1)(f) / 6(1)(b) / 6(1)(a) | găzduirea, serviciul de email, Firebase | ca mai sus | 30 de zile după oprire; pe loc la ștergere |

**Contracte de împuternicire (art. 28):** acceptate de firmă, de păstrat ca PDF:
- Google Firebase (Data Processing and Security Terms, acceptate în consola Firebase);
- găzduirea site-ului (contractul cu furnizorul);
- serviciul prin care pleacă emailurile;
- Vimeo.

## 8. Evaluarea de impact (art. 35)

**Este obligatorie?** Probabil nu: prelucrarea nu e „pe scară largă” în sensul ghidurilor (o comunitate de cliente a
unui singur autor, nu o platformă națională). Cu toate acestea, sunt date de sănătate, iar contul partenerului e o
dezvăluire către terți. De aceea recomandăm o evaluare scurtă, păstrată la dosar. Secțiunile 1–5 de mai sus
conțin deja materialul ei.

| Risc | Probabilitate / gravitate | Măsură |
|---|---|---|
| El află ceva ce ea nu a vrut să știe (întârziere, sarcină) | mică / mare | fereastră în loc de ziua menstruației; nimic nu se schimbă în ziua estimată; întârzierea de până la 10 zile arată ca toamna, apoi pauză neutră; situația nouă ajunge la el cu 1–3 zile întârziere; fără „ziua N” |
| Ea e presată să-i dea acces sau nu-l poate opri | mică / mare | oprire dintr-un buton, pe loc, fără să-l anunțe; ea vede cine a intrat |
| Un link trimis mai departe ajunge la altcineva | medie / medie | link personal; retrimiterea deconectează toate sesiunile vechi; ea vede ultima vizită |
| Cineva află cine e invitat | mică / medie | aceeași răspuns și același timp pentru orice adresă; limite pe IP și pe adresă |
| Scurgere din baza de date | mică / mare | emailul lui criptat cu o cheie ținută în afara bazei de date (`wp-config.php`); linkuri doar ca sha256. **Ciclul ei e criptat, dar cheia lui (opțiunea `ah_sp_ciclu_secret` a fragmentului 235) stă în aceeași bază de date**, deci o copie a bazei de date ajunge pentru a-l citi. Recomandare: mutarea acestei chei în `wp-config.php`, ca la contul lui (de hotărât de Mircea; 235 nu a fost schimbat). Backup criptat la găzduire (de verificat) |
| Tracker pus mai târziu pe site rulează în aplicație | medie / medie | listă de trackere în fragment; verificare la fiecare versiune (secțiunea 5) |

**Rezultat:** riscul rămas e acceptabil cu măsurile de mai sus.

## 9. Ce mai trebuie scris în Termeni și condiții

Un paragraf nou, sub „Spațiul meu”:

> **Accesul pentru partener.** Din Ciclul meu poți invita cel mult două persoane să vadă faza ta de azi și datele
> estimate ale fazelor următoare. Invită doar persoane care au cel puțin 16 ani și care se așteaptă să primească un
> email de la tine. Accesul e personal: persoana invitată nu îl poate transmite altcuiva. Poți opri accesul oricând,
> iar HARU TRAINING SRL îl poate opri dacă e folosit abuziv. Fazele sunt o estimare și nu sunt o metodă de
> contracepție.

**Vârsta.** Pentru salvarea ciclului în cont și pentru invitații, consimțământul trebuie dat de cineva cu cel puțin
16 ani (art. 8 GDPR; România nu a coborât pragul). De pus în Termeni: „Spațiul meu e pentru persoane de cel puțin
16 ani.” În magazine, clasificarea calculată poate ieși mai mică (Apple 13+/16+, Google PEGI 3–12, vezi `docs/store/content-rating.md`); de aceea recomand „Age Rating Override” la 16+ în App Store Connect, ca să se potrivească cu Termenii.

## 10. Hotărâri rămase pentru Mircea

1. **Păstrarea ciclului pe server.** Azi, datele stau până le șterge ea. Propunerea mea: dacă nu intră în cont
   24 de luni, primește un email; după încă 30 de zile fără intrare, datele ciclului, notițele și accesurile
   partenerilor se șterg. Comenzile rămân. Odată aprobată, regula intră în cod și în politică.
2. **Ștergerea contului.** Ștergerea șterge acum și utilizatorul WordPress de client, sau doar datele din Spațiul
   meu? Politica spune azi „înscrierea în Spațiul meu”, deci varianta mai mică. Apple cere doar ca acel cont să nu
   mai poată fi folosit.
3. **Încălcări de securitate (art. 33–34).** E nevoie de o persoană care primește alerta. Notificarea la ANSPDCP
   se face în 72 de ore, iar clientele se anunță dacă e implicat ciclul, pentru că sunt date de sănătate.

## 11. Ce se publică și când

| Când | Ce |
|---|---|
| Înainte de prima trimitere în magazine | adaosul din `docs/store/privacy-policy-addendum.md`, pe pagina publică a politicii (fără login) |
| La activarea `ah-sp-partener.php` | paragraful „Partenerul tău” din adaos și paragraful din Termeni (secțiunea 9) |
| La dosar, nepublicate | secțiunile 1, 2, 7 și 8 din acest fișier și PDF-urile contractelor de împuternicire |
