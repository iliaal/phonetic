#!/usr/bin/env bash
# Regenerate phonetic_arginfo.h from phonetic.stub.php.
#
# gen_stub.php is *not* vendored in-repo: it appears under build/ after phpize
# (php-src stub generator + PHP-Parser). From a clean clone:
#   phpize && ./configure --enable-phonetic
# then re-run this script. Same tool /php-stub-regen wraps.
#
# Requires a PHP CLI with the tokenizer extension. Prefer the same PHP you use
# for phpize/configure, e.g.:
#   PHP_BIN=$HOME/php-install-PHP-8.1/bin/php ./scripts/regen_arginfo.sh
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"

if [[ -z "${PHP_BIN:-}" ]]; then
	if [[ -x "${HOME}/php-install-PHP-8.1/bin/php" ]]; then
		PHP_BIN="${HOME}/php-install-PHP-8.1/bin/php"
	else
		PHP_BIN="php"
	fi
fi

GEN="$ROOT/build/gen_stub.php"
if [[ ! -f "$GEN" ]]; then
	echo "missing $GEN" >&2
	echo "run phpize (and optionally ./configure --enable-phonetic) first so build/gen_stub.php exists" >&2
	exit 1
fi
if ! command -v "$PHP_BIN" >/dev/null 2>&1 && [[ ! -x "$PHP_BIN" ]]; then
	echo "PHP_BIN not executable: $PHP_BIN" >&2
	exit 1
fi

"$PHP_BIN" "$GEN" "$ROOT/phonetic.stub.php"
"$PHP_BIN" "$ROOT/scripts/check_arginfo.php"
echo "regenerated phonetic_arginfo.h (PHP_BIN=$PHP_BIN)"
