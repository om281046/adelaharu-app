#!/usr/bin/env bash
# Toate testele fragmentului ah-sp-partener.php. Din rădăcina proiectului: bash server/tests/partener/ruleaza.sh
set -euo pipefail
cd "$(dirname "$0")/../../.."
php -l server/ah-sp-partener.php
php server/tests/partener/run.php
node server/tests/partener/faze.test.cjs
node server/tests/partener/poze.cjs
