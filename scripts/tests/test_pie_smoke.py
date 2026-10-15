#!/usr/bin/env python3
"""Run the actual standalone smoke script with isolated, controlled commands.

No package installation, network access, system configuration, or builds occur.
The production shell expressions are retained, including set -euo pipefail.
"""

import argparse
import os
from pathlib import Path
import subprocess
import tempfile

ROOT = Path(__file__).resolve().parents[2]

# Shell functions intercept every external side effect in the smoke script.
# Only local log inspection and temporary-directory cleanup use real commands.
PRELUDE = r'''
version() {
    echo "version $1"
    if [ "$LARGE_OUTPUT" = 1 ]; then
        local i
        for ((i=0; i<256; i++)); do printf '%2048s\n' ''; done
    fi
    if [ "$FAIL_VERSION" = "$1" ]; then return 7; fi
}
php() {
    case "$1" in
        --version) version php ;;
        -m) echo "$MODULES" ;;
        -r)
            if [[ "$2" == *'exit(extension_loaded("phonetic") ? 0 : 1);'* ]]; then
                return "$LOAD_STATUS"
            fi
            if [[ "$2" == *'load FAIL'* ]]; then
                [ "$LOAD_STATUS" = 0 ] || return "$LOAD_STATUS"
                echo FUNCTIONAL
            fi
            ;;
        --) cat >/dev/null ;;
        *) echo 'unexpected php invocation' >&2; return 90 ;;
    esac
}
phpize() { if [ "${1:-}" = --version ]; then version phpize; else echo FALLBACK >> "$EVENTS"; fi; }
bison() { version bison; }
libtoolize() { version libtoolize; }
composer() { version composer; }
pie() {
    if [ "$1" = --version ]; then version pie; else
        echo 'installer diagnostics'
        echo 'installer end' >&2
        return "$INSTALL_STATUS"
    fi
}
apt-get() { :; }
git() { :; }
ls() { :; }
curl() { :; }
mv() { :; }
chmod() { :; }
make() { :; }
nproc() { echo 1; }
docker-php-ext-enable() { LOAD_STATUS=0; MODULES=phonetic; }
cd() { :; }
'''


def run(script, directory, *, install=0, load=0, modules="phonetic", fallback=0,
        large=0, fail_version="", accepted=True, manual=False):
    events = directory / "events"
    events.write_text("")
    env = dict(os.environ, INSTALL_STATUS=str(install), LOAD_STATUS=str(load),
               MODULES=modules, ALLOW_MANUAL_FALLBACK=str(fallback),
               LARGE_OUTPUT=str(large), FAIL_VERSION=fail_version, EVENTS=str(events))
    # The old script's fixed log is redirected into the test directory too.
    body = script.replace("/tmp/pie.out", str(directory / "pie.out"))
    result = subprocess.run(["bash", "-c", PRELUDE + body], cwd=directory,
                            env=env, capture_output=True, text=True, timeout=10)
    assert (result.returncode == 0) == accepted, (
        f"install={install}, load={load}, fallback={fallback}, large={large}, "
        f"version={fail_version!r}: status {result.returncode}\n"
        f"{result.stdout}{result.stderr}"
    )
    assert ("FALLBACK" in events.read_text()) == manual, "wrong fallback path"
    assert ("FUNCTIONAL" in result.stdout) == accepted, "functional smoke was bypassed"
    assert ("libtoolize not found" in result.stdout) == (fail_version == "libtoolize"), (
        "successful libtoolize was misreported as unavailable"
    )
    if not fail_version:
        assert "installer end" in result.stdout, "installer diagnostics lost"
    if fail_version and fail_version != "libtoolize":
        assert result.returncode == 7, "version command failure was masked"


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--repeat", type=int, default=1)
    args = parser.parse_args()
    if args.repeat < 1:
        parser.error("--repeat must be positive")
    script = (ROOT / "scripts/pie-smoke.sh").read_text()
    cases = [dict(), dict(large=1),
             dict(load=7, accepted=False),
             dict(load=7, fallback=1, manual=True), dict(load=1, modules="", accepted=False),
             dict(load=1, modules="other_phonetic", accepted=False),
             dict(load=1, modules="other_phonetic", fallback=1, manual=True),
             dict(load=1, modules="", fallback=1, manual=True)]
    for status in (1, 7, 139):
        cases += [dict(install=status, accepted=False),
                  dict(install=status, fallback=1, manual=True),
                  dict(install=status, load=1, modules="", accepted=False)]
    for command in ("php", "phpize", "bison", "composer", "pie", "libtoolize"):
        cases.append(dict(fail_version=command, accepted=command == "libtoolize"))
    with tempfile.TemporaryDirectory(prefix="phonetic-pie-smoke-") as temp:
        directory = Path(temp)
        configure = directory / "configure"
        configure.write_text("#!/bin/sh\nexit 0\n")
        configure.chmod(0o755)
        for _ in range(args.repeat):
            for case in cases:
                run(script, directory, **case)
    print(f"PIE smoke: {len(cases)} cases x {args.repeat} passed")


if __name__ == "__main__":
    main()
