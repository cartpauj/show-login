#!/usr/bin/env bash
#
# Installs WordPress core + the WordPress PHPUnit test library into ./tmp
# and creates the test database.
#
# Usage: composer tests-install [-- <wp-version>]
#
# Database settings come from environment variables (defaults shown):
#   DB_NAME=show_login_tests DB_USER=root DB_PASS= DB_HOST=localhost
#
# WARNING: DB_NAME is dropped and recreated on every test run. Never point it
# at a real database.

set -e

ROOT="$(cd "$(dirname "$0")/.." && pwd)"

export WP_TESTS_DIR="$ROOT/tmp/wordpress-tests-lib"
export WP_CORE_DIR="$ROOT/tmp/wordpress"
export TMPDIR="$ROOT/tmp"

mkdir -p "$TMPDIR"

WP_VERSION="${1:-${WP_VERSION:-latest}}"

# Start fresh so switching WP versions works.
rm -rf "$WP_TESTS_DIR" "$WP_CORE_DIR"

# The last argument ("true") skips the interactive "drop existing database?" prompt;
# we drop and recreate it ourselves instead.
if command -v mariadb > /dev/null 2>&1; then DB_CLIENT=mariadb; else DB_CLIENT=mysql; fi
HOST_ARGS=()
if [[ "${DB_HOST:-localhost}" == *:* ]]; then
	HOST_ARGS=(--host="${DB_HOST%%:*}" --port="${DB_HOST##*:}" --protocol=tcp)
elif [[ "${DB_HOST:-localhost}" != "localhost" ]]; then
	HOST_ARGS=(--host="$DB_HOST" --protocol=tcp)
fi
"$DB_CLIENT" --user="${DB_USER:-root}" --password="${DB_PASS:-}" "${HOST_ARGS[@]}" \
	--execute="DROP DATABASE IF EXISTS \`${DB_NAME:-show_login_tests}\`; CREATE DATABASE \`${DB_NAME:-show_login_tests}\`;"

bash "$ROOT/bin/install-wp-tests.sh" \
	"${DB_NAME:-show_login_tests}" \
	"${DB_USER:-root}" \
	"${DB_PASS:-}" \
	"${DB_HOST:-localhost}" \
	"$WP_VERSION" \
	true
