#!/usr/bin/env bash
set -euo pipefail

# Run inside DDEV: ddev exec bash scripts/tests_csv_browser.sh [PHPUnit options]
# Resolve paths relative to this script, regardless of the caller's directory.
script_dir="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
cd -- "$script_dir/../web"

export SIMPLETEST_DB="${SIMPLETEST_DB:-sqlite://localhost//tmp/dacem-csv-browser.sqlite}"
export SIMPLETEST_BASE_URL="${SIMPLETEST_BASE_URL:-http://localhost}"
export BROWSERTEST_OUTPUT_DIRECTORY="${BROWSERTEST_OUTPUT_DIRECTORY:-/tmp/dacem-csv-browser-output}"
mkdir -p -- "$BROWSERTEST_OUTPUT_DIRECTORY"

exec ../vendor/bin/phpunit \
  -c core/phpunit.xml.dist \
  --bootstrap modules/custom/dacem_csv_import_export/tests/bootstrap.php \
  --group csv_browser \
  modules/custom/dacem_csv_import_export/tests/src/Functional \
  "$@"
