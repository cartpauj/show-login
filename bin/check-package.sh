#!/usr/bin/env bash
#
# Builds the same archive Packagist/Composer and the GitHub release will serve
# (git archive honours .gitattributes export-ignore) and checks what is in it.
#
# Usage: bin/check-package.sh [ref]   (default: HEAD)

set -euo pipefail

cd "$(dirname "$0")/.."
REF="${1:-HEAD}"

files=$(git archive "$REF" | tar -t | grep -v '/$' | sort)

echo "Files in the package:"
echo "$files" | sed 's/^/  /'

# Must ship.
missing=0
for required in show-login.php readme.txt LICENSE composer.json includes/class-show-login.php templates/popup.php assets/js/show-login.js assets/css/show-login.css loco.xml languages/show-login.pot; do
	if ! grep -qx "$required" <<< "$files"; then
		echo "MISSING: $required" >&2
		missing=1
	fi
done

# Must not ship.
leaked=$(grep -E '^(tests/|bin/|\.github/|node_modules/|vendor/|tmp/|\.gitattributes|\.gitignore|phpunit\.xml|phpcs\.xml|package(-lock)?\.json|RELEASES\.md)' <<< "$files" || true)
if [[ -n "$leaked" ]]; then
	echo "DEV FILES IN PACKAGE (add them to .gitattributes with export-ignore):" >&2
	echo "$leaked" | sed 's/^/  /' >&2
fi

if [[ $missing -ne 0 || -n "$leaked" ]]; then
	exit 1
fi
echo "Package OK."
