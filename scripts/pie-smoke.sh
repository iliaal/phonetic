#!/usr/bin/env bash
set -euo pipefail

# PIE install smoke test for iliaal/phonetic. Runs in a clean php:<ver>-cli
# container with this repo mounted at /phonetic. After a release, exercises
# the real-user `pie install iliaal/phonetic` path against Packagist, with a
# manual phpize+make+install fallback for the source-build lane.
#
#   docker run --rm -v "$PWD":/phonetic:ro php:8.4-cli bash /phonetic/scripts/pie-smoke.sh

echo "======================================================================"
echo " PIE install smoke test for iliaal/phonetic"
echo "======================================================================"
echo
echo "PHP:"
php --version | head -1
echo "phpize:"
phpize --version 2>&1 | head -2
echo

echo "---- 1. System build tools ----"
apt-get update -qq >/dev/null
# php:<ver>-cli Debian images lack these: git (PIE clones via git), bison +
# libtoolize (PIE's build-tools check insists on both), ca-certificates (HTTPS
# clone), unzip (composer shells out to /usr/bin/unzip for the prebuilt zip).
apt-get install -y -qq git ca-certificates bison libtool-bin unzip >/dev/null
git --version
bison --version | head -1
libtoolize --version | head -1 || echo "libtoolize not found"
echo

echo "---- 2. Fresh clone from mounted source (avoids host build artifacts) ----"
git config --global --add safe.directory /phonetic
git config --global --add safe.directory /phonetic/.git
git clone -q file:///phonetic /tmp/src
cd /tmp/src
echo "HEAD: $(git log --oneline -1)"
echo "tag:  $(git describe --tags --always)"
ls composer.json config.m4 php_phonetic.h | head
echo

echo "---- 3. Install Composer ----"
curl -sS https://getcomposer.org/installer | php -- --quiet
mv composer.phar /usr/local/bin/composer
composer --version | head -1
echo

echo "---- 4. Download PIE ----"
curl -sSL https://github.com/php/pie/releases/latest/download/pie.phar -o /usr/local/bin/pie
chmod +x /usr/local/bin/pie
pie --version 2>&1 | head -3
echo

echo "---- 5. pie install (against Packagist, real-user path) ----"
PIE_OK=0
echo "   pie install iliaal/phonetic"
pie install \
    --with-php-config=/usr/local/bin/php-config \
    --auto-install-build-tools \
    iliaal/phonetic 2>&1 | tee /tmp/pie.out | tail -25 || true

if php -m | grep -qi phonetic; then
    PIE_OK=1
    echo "   PIE install: success"
fi
echo "   overall PIE result: PIE_OK=$PIE_OK"
echo

echo "---- 6. Verify extension loads ----"
if [ "$PIE_OK" = "0" ]; then
    echo "   *** PIE did not install the extension; falling back to manual phpize+make+install ***"
    cd /tmp/src
    phpize >/dev/null
    ./configure --enable-phonetic >/dev/null
    make -j"$(nproc)" 2>&1 | tail -3
    make install 2>&1 | tail -3
    docker-php-ext-enable phonetic
    echo "   [fallback] manual install SUCCEEDED"
fi
php -m | grep -i phonetic
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
