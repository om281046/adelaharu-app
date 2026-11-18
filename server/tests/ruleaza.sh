#!/usr/bin/env bash
# Toate testele de pe server: modul aplicație (ah-sp-aplicatie.php), contul pentru el (ah-sp-partener.php) și
# legăturile dintre ele. Din rădăcina proiectului: bash server/tests/ruleaza.sh
set -euo pipefail
cd "$(dirname "$0")/../.."
# copiile de referință pentru CI (server/tests/date/v2) la zi cu reference/v2; fără reference/v2 nu face nimic
php server/tests/extrage-referinte.php --verifica
php -l server/ah-sp-aplicatie.php
php server/tests/run.php
node server/tests/ciclu.test.cjs
node server/tests/vizual/poze.cjs
# contul pentru el: php -l, run.php, fazele față de 235, harnașul vizual (pozele partener-*.png; cel de sus nu le mai șterge)
bash server/tests/partener/ruleaza.sh
# integrarea: ambele fragmente încărcate împreună (lista permisă, AASA = App Links, push un telefon = un cont, ștergerea)
php server/tests/integrare/run.php
