#!/usr/bin/env bash
# Regenerate phonetic_arginfo.h from phonetic.stub.php using the vendored
# gen_stub.php (same tool /php-stub-regen wraps). Run from the repo root or any path.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PHP_BIN="${PHP_BIN:-php}"
GEN="$ROOT/build/gen_stub.php"
if [[ ! -f "$GEN" ]]; then
	echo "missing $GEN (vendored php-src gen_stub.php)" >&2
	exit 1
fi
"$PHP_BIN" "$GEN" "$ROOT/phonetic.stub.php"
"$PHP_BIN" "$ROOT/scripts/check_arginfo.php"
echo "regenerated phonetic_arginfo.h"
