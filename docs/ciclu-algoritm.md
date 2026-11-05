# Calculul fazelor ciclului (identic cu `sn41`)

Sursa: `reference/sn41.php` („Harta ciclului tău”), funcțiile PHP `ah_ciclu_limite`, `ah_ciclu_faza_zilei`, `ah_ciclu_unde`, `ah_ciclu_data_faza`, `ah_ciclu_medie`, `ah_ciclu_data_valida`, `ah_ciclu_ics` și scriptul JS al paginii (`lim`, `fazaZilei`, `unde`, `medie`, `startNou`, trimiterea formularului, „următoarele faze”, `luna`).

Implementarea din aplicație: `app/src/ciclu/algoritm.ts` (funcții pure, fără stocare și fără ceas).
Teste: `app/src/ciclu/algoritm.test.ts` (cazuri scrise de mână) și `app/src/ciclu/algoritm.diferential.test.ts`, care rulează **codul JS original copiat verbatim** din `sn41` (`app/src/ciclu/fixtures/sn41-original.js`) pe toate combinațiile `c` 0–60 × `m` 0–15 și pe 3 000 de stări aleatoare și cere rezultate identice.

## Datele de intrare

| Câmp (aplicație / sn41 JS) | PHP / API | Ce e | Limite |
|---|---|---|---|
| `s` | `start` / `prima_zi` | prima zi a ultimei menstruații (`Y-m-d`) | nu în viitor, cel mult 120 de zile în urmă (la introducere) |
| `m` | `durata` / `zile_sangerare` | câte zile sângerează | 2–10 (implicit 5) |
| `c` | `ciclu` / `zile_ciclu` | câte zile are ciclul | 21–45 (implicit 28) |
| `starturi` | — (doar în pagină, `localStorage`) | ultimele starturi cunoscute, sortate | cel mult 6 |

Datele se lucrează ca zile calendaristice întregi: `utc("Y-m-d") = Date.UTC(an, lună-1, zi)`, diferența `dif(a,b) = round((utc(b)-utc(a)) / 86 400 000)`. Așa, schimbarea orei de vară nu mută nimic. „Azi” este data locală a telefonului (în PHP: `today` în fusul site-ului, Europe/Bucharest).

## 1. Limitele fazelor — `lim(c, m)`

```
c = clamp(trunc(c), 21, 45);  m = clamp(trunc(m), 2, 10)
ovS = max(c − 16, m + 2)      // ovulația, cam cu 14 zile înaintea menstruației următoare
ovE = min(ovS + 4, c − 2)
menstruala    = [1,      m]
foliculara    = [m + 1,  ovS − 1]
ovulatorie    = [ovS,    ovE]
premenstruala = [ovE + 1, c]
```

Exemple: 28/5 ⇒ 1–5, 6–11, 12–16, 17–28. 21/10 ⇒ 1–10, 11–11, 12–16, 17–21. 45/2 ⇒ 1–2, 3–28, 29–33, 34–45.
Pentru orice `c`∈[21,45], `m`∈[2,10] fazele acoperă zilele 1…c fără goluri, ovulatorie are mereu 5 zile, foliculara cel puțin 1 zi (verificat în teste).

`fazaZilei(zi, c, m)`: prima fază (în ordinea menstruala, foliculara, ovulatorie, premenstruala) al cărei interval conține `zi`; dacă niciunul (zi 0 sau > c), întoarce `premenstruala`.

## 2. Ziua de azi — `unde(zi, S)`

1. Startul aplicat: ultimul element din `starturi` (sortate) care e ≤ `zi`; dacă nu există, `S.s`.
2. Dacă nu există start sau `zi` < start ⇒ `null` (startul e în viitor).
3. `d = dif(start, zi)`, `nr = floor(d / c)`, `ziua = d mod c + 1`, `faza = fazaZilei(ziua, c, m)`, `inceput = start + nr·c` zile.

Ciclul se proiectează înainte la nesfârșit cu lungimea `c`. PHP-ul (`ah_ciclu_unde`, folosit la emailuri) e același calcul cu un singur start (`starturi = [start]`) și cu `c` limitat la 21–45; aplicația păstrează mereu `c` deja limitat, deci rezultatele coincid.

## 3. Formularul „În ce fază ești azi?” — `calculeaza`

- validare, în ordine: dată lipsă/greșită ⇒ „Alege prima zi a ultimei menstruații.”; în viitor ⇒ „Data aleasă e în viitor…”; mai veche de 120 de zile ⇒ „Data e de acum mai bine de patru luni…”;
- `c = +c || 28`, `m = +m || 5`, apoi limitate la 21–45 și 2–10;
- `starturi` = starturile vechi **mai mici** decât `s` și la cel puțin 15 zile de el, plus `s`; sortate; ultimele 6.

## 4. „Mi-a venit menstruația” — `startNou(S, d)`

- acceptat doar dacă `d` ≤ azi și cel mult 120 de zile în urmă;
- dacă ultimul start e la mai puțin de 15 zile (în orice direcție) de `d`, e înlocuit (o corectură, nu un ciclu nou);
- se adaugă `d`, se scot dublurile, se sortează, se păstrează ultimele 6;
- `s` = ultimul start; `c = medie(starturi, c)`; `m` rămâne.

`medie(starturi, implicit)`: pauzele dintre starturi consecutive (sortate), păstrate doar cele între 21 și 45 de zile; din ele ultimele 3; media rotunjită (`Math.round`, 29,5 ⇒ 30, identic cu `round` din PHP); fără pauze valide ⇒ `implicit`.

## 5. Următoarele faze și notificările

- **Următoarele faze** (lista din pagină): pentru fiecare fază, `inceput + (început_fază − 1)`; dacă data e ≤ azi, `+ c`; sortate după dată.
- **Calendarul .ics din sn41**: 6 cicluri × 4 faze începând cu ciclul curent, alarmă la `PT9H` (9:00) în ziua de început. Aplicația folosește exact aceeași listă (`inceputuriFaze`) pentru **notificările locale**, la 9:00 ora telefonului, doar cele încă în viitor; titlu „Începe faza menstruală · Alchimista (estimare)”, ca `DESCRIPTION`-ul alarmei din .ics (+ „(estimare)” din `SUMMARY` pentru menstruație), textul = `pe_scurt`. Se reprogramează la fiecare schimbare și la fiecare pornire a aplicației (ID-uri 4100–4199).

## 6. Luna pe cer — `luna(data)`

`P = 29,530588853`; `zile = (utc(data) + 12 h − 2000-01-06 18:14 UTC) / 1 zi`; `v = zile mod P` (pozitiv); `f = v / P`.
`v < 1,85` sau `> 27,7` ⇒ Luna nouă; `< 13,8` ⇒ Luna în creștere; `< 15,8` ⇒ Luna plină; altfel Luna în descreștere.

## Ce NU face aplicația (diferențe intenționate față de pagină)

- Jurnalul zilnic (energie, stări, notițe) și „tiparele” din pagină nu sunt în v1.
- Produsul fazei din pagină (preț, „Cumpără”) **nu** există în aplicație (fără vânzare). În locul lui, aplicația arată meditația `meditatie_id` din `GET /ciclu/faze`, doar dacă e în lista ei de meditații; dacă nu are acces, apare cu lacăt și textul fără link.
- Sincronizarea cu site-ul (`PUT /ciclu`) trimite doar `prima_zi = s`, `zile_ciclu = c`, `zile_sangerare = m` (serverul nu ține `starturi`) și numai cu bifa separată de acord pentru datele de sănătate. La intrarea în cont, dacă telefonul nu are date și site-ul are (salvate acolo cu acord), aplicația le preia cu `starturi = [prima_zi]`.
