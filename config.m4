dnl config.m4 for extension phonetic

PHP_ARG_ENABLE(phonetic, whether to enable phonetic support,
[  --enable-phonetic       Enable phonetic (Double Metaphone, Beider-Morse, Daitch-Mokotoff) support])

PHP_ARG_ENABLE(phonetic-dev, whether to enable developer build flags,
[  --enable-phonetic-dev   Upgrade wrapper warnings to -Werror plus strict checks], no, no)

if test "$PHP_PHONETIC" != "no"; then

  PHP_VERSION_ID=$($PHP_CONFIG --vernum)
  if test "$PHP_VERSION_ID" -lt "80100"; then
    AC_MSG_ERROR([phonetic requires PHP 8.1.0 or later (found $PHP_VERSION_ID)])
  fi

  dnl All phonetic data (Beider-Morse + Daitch-Mokotoff rule tables, vendored
  dnl from Apache Commons Codec under Apache-2.0) is compiled in via the
  dnl generated src/bmpm_data.h, so there is no external library dependency.
  PHONETIC_SOURCES="phonetic.c src/double_metaphone.c src/bmpm.c src/dm_soundex.c src/nysiis.c src/match_rating.c"

  dnl -Wall -Wextra are on by default so regressions get caught in every local
  dnl build; --enable-phonetic-dev upgrades warnings to -Werror.
  dnl -Wno-unused-parameter silences the framework-supplied MINIT/MINFO macro
  dnl params (type, module_number, zend_module) that handlers don't reference.
  dnl -fvisibility=hidden keeps internal symbols out of the .so's dynamic
  dnl symbol table (only get_module needs exporting).
  PHONETIC_CFLAGS="-fvisibility=hidden -Wall -Wextra -Wno-unused-parameter"

  if test "$PHP_PHONETIC_DEV" = "yes"; then
    PHONETIC_CFLAGS="$PHONETIC_CFLAGS -Werror -Wstrict-prototypes"
  fi

  PHP_NEW_EXTENSION(phonetic,
    $PHONETIC_SOURCES,
    $ext_shared,,
    $PHONETIC_CFLAGS)

  PHP_ADD_BUILD_DIR([$ext_builddir/src], 1)
fi
