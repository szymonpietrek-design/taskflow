#!/usr/bin/env bash
# Manually capture the existing PHPStan level 5 issues once, on the same PHP
# version used by GitHub Actions. New issues will continue to fail CI.
set -euo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")/.."

if [[ ! -f phpstan.neon ]]; then
    echo "phpstan.neon not found; run this script from a TaskFlow checkout." >&2
    exit 1
fi

if [[ ! -f vendor/bin/phpstan ]]; then
    echo "PHP dependencies missing: running composer install." >&2
    composer install --no-interaction --no-scripts
fi

tmp_config=".phpstan-baseline-generation-$$.neon"
trap 'rm -f "$tmp_config"' EXIT

# Analyse WITHOUT the existing baseline. Otherwise generating a refreshed
# baseline might omit errors that were already suppressed.
awk '!/^[[:space:]]*-[[:space:]]*phpstan-baseline\.neon[[:space:]]*$/' phpstan.neon > "$tmp_config"

echo "Generating PHPStan baseline at level 5..."
./vendor/bin/phpstan analyse \
    --configuration="$tmp_config" \
    --generate-baseline=phpstan-baseline.neon \
    --no-progress \
    --memory-limit=1G

echo "Verifying the regular PHPStan configuration..."
./vendor/bin/phpstan analyse --no-progress --memory-limit=1G

echo "PHPStan level 5 passes with the generated baseline."
