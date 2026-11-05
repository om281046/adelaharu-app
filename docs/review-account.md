# Cerință pentru server: contul de recenzie (App Review / Google Play)

Pentru: colegul de la `server/ah-app-api.php`. De la: build & magazine. Context: `docs/store/review-notes.md`.

Recenzorii Apple și Google trebuie să poată intra fără acces la o căsuță de email. Intrarea e cu cod de 6 cifre
(`/auth/cod` → `/auth/verifica`), deci avem nevoie de **o singură adresă de recenzie cu un cod fix**, setată din admin.

## Ce trebuie să facă serverul

1. **Opțiune nouă** `ah_app_recenzie` = `{"email":"recenzie@adelaharu.ro","cod":"<6 cifre>"}`, editabilă în pagina
   „Aplicația mobilă” (doar `manage_options`). Gol sau dezactivat = funcția nu există. Codul: exact 6 cifre, iar
   `000000`, `123456` și cifre identice se refuză la salvare.
2. **`POST /auth/cod`** pentru exact acea adresă (comparată normalizat, fără majuscule/spații): răspunde `{"ok":true}`
   ca de obicei; poate trimite sau nu emailul (recomand: nu trimite, scrie în jurnal „cod de recenzie cerut”).
   Nu consumă limita de 5/oră/email (recenzorii repetă), dar limita pe IP (20/oră) rămâne.
3. **`POST /auth/verifica`** pentru acea adresă acceptă codul fix, comparat cu `hash_equals`, oricând (nu expiră,
   nu e de unică folosință). Codurile normale trimise pe email continuă să meargă. 5 greșeli la rând ⇒ blocat 15 minute
   (nu anulare definitivă, ca recenzia să nu se blocheze singură).
4. **Limita de telefoane**: pentru adresa de recenzie, la depășire se înlocuiește cel mai vechi dispozitiv (nu
   `403 dispozitive`). Apple testează pe mai multe dispozitive, uneori în paralel, la fiecare versiune.
5. **Accesul vine din comenzi reale**, fără excepții în cod: proprietara face o comandă manuală de 0 lei cu Inner Temple
   (anual) și una cu un curs pentru această adresă, deci `ah_med_acces()` și `ah_cm_cursuri_pentru()` merg normal.
   Nimic special la `/eu`, `/meditatii`, `/cursuri`.
6. **`DELETE /cont`** pe adresa de recenzie se comportă normal (Apple chiar îl încearcă); comenzile rămân, deci
   recenzorul poate intra din nou.
7. **Jurnal** (`ah_app_jurnal`): fiecare intrare cu codul fix, cu IP-ul trunchiat, ca să se vadă dacă cineva îl abuzează.
8. **Test** în `server/tests/run.php`: codul fix merge doar pentru adresa setată; nu merge pentru altă adresă;
   opțiunea goală ⇒ comportament neschimbat; `hash_equals`; limita de telefoane înlocuiește în loc să refuze.

## De ce așa (riscuri)

- E o „ușă din spate” controlată: o singură adresă, fără date reale, doar conținut, și se poate opri din admin.
  Codul fix nu se pune în repo; îl alege proprietara și îl trece doar în App Store Connect și Play Console.
- Nu folosim un cont real de clientă și nici nu ocolim verificarea accesului (punctul 5).
