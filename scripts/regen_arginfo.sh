#!/usr/bin/env bash
# Regenerate phonetic_arginfo.h from phonetic.stub.php using the vendored
# gen_stub.php (same tool /php-stub-regen wraps). Run from the repo root or any path.
#
# Requires a PHP CLI that can run build/gen_stub.php (php-src stub generator
# needs the tokenizer extension and the vendored PHP-Parser under build/).
# Prefer the same PHP you use for phpize/configure, e.g.:
#   PHP_BIN=$HOME/php-install-PHP-8.1/bin/php ./scripts/regen_arginfo.sh
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"

if [[ -z "${PHP_BIN:-}" ]]; then
	# Prefer a known project install if present, else PATH `php`.
	if [[ -x "${HOME}/php-install-PHP-8.1/bin/php" ]]; then
		PHP_BIN="${HOME}/php-install-PHP-8.1/bin/php"
	else
		PHP_BIN="php"
	fi
fi

GEN="$ROOT/build/gen_stub.php"
if [[ ! -f "$GEN" ]]; then
	echo "missing $GEN (vendored php-src gen_stub.php)" >&2
	exit 1
fi
if ! command -v "$PHP_BIN" >/dev/null 2>&1 && [[ ! -x "$PHP_BIN" ]]; then
	echo "PHP_BIN not executable: $PHP_BIN" >&2
	exit 1
fi

"$PHP_BIN" "$GEN" "$ROOT/phonetic.stub.php"
"$PHP_BIN" "$ROOT/scripts/check_arginfo.php"
echo "regenerated phonetic_arginfo.h (PHP_BIN=$PHP_BIN)"
