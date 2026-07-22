#!/usr/bin/env bash
# Fetch the PHPUnit phar used by this project's test suite.
# The phar is gitignored (dev-only, never deployed to the web root); run this once
# after cloning, then: php tools/phpunit.phar
set -euo pipefail
VERSION="11"
DEST="$(cd "$(dirname "$0")" && pwd)/phpunit.phar"
URL="https://phar.phpunit.de/phpunit-${VERSION}.phar"
echo "Downloading PHPUnit ${VERSION} -> ${DEST}"
curl -fSL -o "$DEST" "$URL"
chmod +x "$DEST"
php "$DEST" --version
