#!/usr/bin/env bash
set -euo pipefail
test "${GITHUB_ACTIONS:-}" = true
test "${SAFE_BROWSER_CI:-}" = 1
test -z "$(ip route show default)"
test "$(ip -o link show | wc -l)" -eq 1
test -f "$BROWSER_FIXTURE_DIR/database.sqlite"
test ! -s "$BROWSER_FIXTURE_DIR/database.sqlite"
# Must reject an unguarded invocation before any migration/DB access.
if env -u SAFE_BROWSER_CI php browser-tests/fixture.php reset >/dev/null 2>&1; then
  echo 'FAIL fixture guard'; exit 1
fi
php artisan package:discover --ansi
php browser-tests/fixture.php reset >/dev/null
cd browser-tests
node node_modules/@playwright/test/cli.js test
