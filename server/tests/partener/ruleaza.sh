#!/usr/bin/env bash
# Toate testele fragmentului ah-sp-partener.php. Din rădăcina proiectului: bash server/tests/partener/ruleaza.sh
set -euo pipefail
cd "$(dirname "$0")/../../.."
php -l server/ah-sp-partener.php
php server/tests/partener/run.php
node server/tests/partener/faze.test.cjs
# axe-core pentru harnașul vizual (server/tests/partener/package.json), o singură dată
[ -f server/tests/partener/node_modules/axe-core/axe.min.js ] || npm ci --prefix server/tests/partener --no-audit --no-fund
node server/tests/partener/poze.cjs
