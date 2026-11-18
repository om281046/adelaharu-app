#!/usr/bin/env bash
# Toate testele fragmentului ah-sp-aplicatie.php. Din rădăcina proiectului: bash server/tests/ruleaza.sh
set -euo pipefail
cd "$(dirname "$0")/../.."
php -l server/ah-sp-aplicatie.php
php server/tests/run.php
node server/tests/ciclu.test.cjs
node server/tests/vizual/poze.cjs
