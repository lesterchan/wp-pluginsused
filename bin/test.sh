#!/usr/bin/env bash
#
# Run the PHPUnit suite against a real WordPress install.
#
# Docker is the only prerequisite: wp-env brings up WordPress, MySQL and the
# WordPress test library. Dev dependencies are installed INSIDE the container,
# so vendor/ never appears in the repo.
#
#   bash bin/test.sh                 # whole suite
#   bash bin/test.sh --filter Escaping
#
# Override the stack with WP_ENV_PHP_VERSION / WP_ENV_CORE, exactly as CI does.

set -euo pipefail

SLUG=wp-pluginsused
CWD=wp-content/plugins/$SLUG

cd "$(dirname "$0")/.."

echo "==> Starting wp-env (PHP ${WP_ENV_PHP_VERSION:-default}, core ${WP_ENV_CORE:-default})"
npx --yes @wordpress/env start

echo "==> Installing dev dependencies inside the tests container"
npx --yes @wordpress/env run tests-cli --env-cwd="$CWD" \
	composer install --no-interaction --no-progress

echo "==> Running PHPUnit"
npx --yes @wordpress/env run tests-cli --env-cwd="$CWD" \
	vendor/bin/phpunit "$@"
