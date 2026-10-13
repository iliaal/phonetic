#!/usr/bin/env bash
set -euo pipefail

# PIE install smoke test for iliaal/phonetic. Runs in a clean php:<ver>-cli
# container with this repo mounted at /phonetic. After a release, exercises
# the real-user `pie install iliaal/phonetic` path against Packagist. A PIE
# install requires a zero installer exit and a successful extension-load probe.
# Either failure is fatal by default (that is the path under test); set
# ALLOW_MANUAL_FALLBACK=1 to fall back to phpize+make+install for local
# source-build iteration only.
#
#   docker run --rm -v "$PWD":/phonetic:ro php:8.4-cli bash /phonetic/scripts/pie-smoke.sh

# Inspect output only after the producer has exited: head/grep -q can close
# a pipe early under pipefail, turning successful commands into SIGPIPE failures.
log_dir=$(mktemp -d)
trap 'rm -rf "$log_dir"' EXIT
show_version() {
    local lines=$1 status=0
    shift
    "$@" > "$log_dir/version.out" 2>&1 || status=$?
    head -n "$lines" "$log_dir/version.out"
    return "$status"
}

echo "======================================================================"
echo " PIE install smoke test for iliaal/phonetic"
echo "======================================================================"
echo
echo "PHP:"
show_version 1 php --version
echo "phpize:"
show_version 2 phpize --version
echo

echo "---- 1. System build tools ----"
apt-get update -qq >/dev/null
# php:<ver>-cli Debian images lack these: git (PIE clones via git), bison +
# libtoolize (PIE's build-tools check insists on both), ca-certificates (HTTPS
# clone), unzip (composer shells out to /usr/bin/unzip for the prebuilt zip).
apt-get install -y -qq git ca-certificates bison libtool-bin unzip >/dev/null
git --version
show_version 1 bison --version
show_version 1 libtoolize --version || echo "libtoolize not found"
echo

echo "---- 2. Fresh clone from mounted source (avoids host build artifacts) ----"
git config --global --add safe.directory /phonetic
git config --global --add safe.directory /phonetic/.git
git clone -q file:///phonetic /tmp/src
cd /tmp/src
echo "HEAD: $(git log --oneline -1)"
echo "tag:  $(git describe --tags --always)"
ls composer.json config.m4 php_phonetic.h
echo

echo "---- 3. Install Composer ----"
curl -sS https://getcomposer.org/installer | php -- --quiet
mv composer.phar /usr/local/bin/composer
show_version 1 composer --version
echo

echo "---- 4. Download PIE ----"
curl -sSL https://github.com/php/pie/releases/latest/download/pie.phar -o /usr/local/bin/pie
chmod +x /usr/local/bin/pie
show_version 3 pie --version
echo

echo "---- 5. pie install (against Packagist, real-user path) ----"
PIE_OK=0
PIE_STATUS=0
echo "   pie install iliaal/phonetic"
pie install \
    --with-php-config=/usr/local/bin/php-config \
    --auto-install-build-tools \
    iliaal/phonetic > "$log_dir/pie.out" 2>&1 || PIE_STATUS=$?
tail -25 "$log_dir/pie.out"

if [ "$PIE_STATUS" -eq 0 ] && php -r 'exit(extension_loaded("phonetic") ? 0 : 1);'; then
    PIE_OK=1
    echo "   PIE install: success"
fi
echo "   overall PIE result: PIE_OK=$PIE_OK, installer status=$PIE_STATUS"
echo

echo "---- 6. Verify extension loads ----"
if [ "$PIE_OK" = "0" ]; then
    # Manual fallback is opt-in: it cannot validate a failed PIE install.
    if [ "${ALLOW_MANUAL_FALLBACK:-0}" != "1" ]; then
        echo "   *** pie install iliaal/phonetic FAILED (PIE_OK=0). ***" >&2
        echo "   *** This smoke test exercises the PIE/Packagist path; not masking it with a manual build. ***" >&2
        echo "   *** Set ALLOW_MANUAL_FALLBACK=1 to fall back to phpize for local iteration only. ***" >&2
        exit 1
    fi
    echo "   *** PIE did not install the extension; ALLOW_MANUAL_FALLBACK=1 set, falling back to manual phpize+make+install ***"
    cd /tmp/src
    phpize >/dev/null
    ./configure --enable-phonetic >/dev/null
    make -j"$(nproc)" 2>&1 | tail -3
    make install 2>&1 | tail -3
    docker-php-ext-enable phonetic
    echo "   [fallback] manual install SUCCEEDED (NOTE: PIE path was NOT validated)"
fi
php -m | grep -ix phonetic
php -r 'echo "phonetic version: ", phpversion("phonetic"), PHP_EOL;'
echo

echo "---- 7. Functional smoke test ----"
php -r '
if (!extension_loaded("phonetic")) { echo "load FAIL\n"; exit(1); }
echo "load OK\n";

$dm = double_metaphone("Schwarzenegger");
if ($dm !== ["primary" => "XRSN", "alternate" => "XFRT"]) {
    echo "double_metaphone FAIL: ", var_export($dm, true), "\n"; exit(1);
}
echo "double_metaphone OK\n";

$b = bmpm("Garcia", BMPM_SEPHARDIC, BMPM_EXACT);
if ($b !== "garsia|gartSa") { echo "bmpm FAIL: ", var_export($b, true), "\n"; exit(1); }
echo "bmpm OK\n";

$s = dm_soundex("Auerbach");
if ($s !== ["097400", "097500"]) { echo "dm_soundex FAIL: ", var_export($s, true), "\n"; exit(1); }
echo "dm_soundex OK\n";
'
echo
echo "======================================================================"
echo " PIE install smoke test: PASSED"
echo "======================================================================"
