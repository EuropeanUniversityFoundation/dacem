#!/usr/bin/env bash
set -euo pipefail

# Run inside DDEV: ddev exec bash scripts/tests_csv.sh [PHPUnit options]
# Resolve paths relative to this script, regardless of the caller's directory.
script_dir="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
cd -- "$script_dir/../web"

export SIMPLETEST_DB="${SIMPLETEST_DB:-sqlite://localhost/:memory:}"

exec ../vendor/bin/phpunit \
  -c core/phpunit.xml.dist \
  --bootstrap modules/custom/dacem_csv_import_export/tests/bootstrap.php \
  --exclude-group csv_browser \
  modules/custom/dacem_csv_import_export/tests/src \
  "$@"
