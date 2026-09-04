/*
  +----------------------------------------------------------------------+
  | Copyright (c) 2026, Ilia Alshanetsky                                 |
  | Copyright (c) 2026, Advanced Internet Designs Inc.                   |
  +----------------------------------------------------------------------+
  | The generator and the C scaffolding in this file are subject to the  |
  | BSD 3-Clause license bundled with this package in the file LICENSE.  |
  | The embedded rule tables are mechanically transformed from Apache    |
  | Commons Codec resource files and remain under the Apache License 2.0 |
  | (see LICENSE Section 2 and vendor/commons-codec-bm/).                |
  +----------------------------------------------------------------------+
  | Author: Ilia Alshanetsky <ilia@ilia.ws>                              |
  +----------------------------------------------------------------------+
*/

/*
 * GENERATED FILE -- DO NOT EDIT BY HAND.
 *
 * Regenerate with:  php scripts/gen_bmpm_data.php
 *
 * Source data: Daitch-Mokotoff rule file vendored from
 * Apache Commons Codec (Apache License 2.0) under vendor/commons-codec-bm/.
 * Pattern / code columns are stored RAW (uninterpreted);
 * the Daitch-Mokotoff engine compiles them at runtime.
 * (Companion to src/bmpm_data.h, emitted by the same run.)
 *
 * Schema
 * ------
 *   dm_rule          : one Daitch-Mokotoff rule: pattern + three code columns
 *                      (at_start / before_vowel / default). A code column may
 *                      hold "|"-separated branch alternatives, kept raw.
 *   dm_folding       : one accent/ligature folding (from -> to), raw UTF-8.
 *
 * Stack/buffer caps below are the single source of truth shared with src/dm_soundex.c.
 */

#ifndef PHP_DM_DATA_H
#define PHP_DM_DATA_H

#include <stddef.h>

/* DM Soundex replacement-field caps (dms_encode alts[][]). */
#define DMS_CAP_CODE_ALTS     8
#define DMS_CAP_CODE_LEN      3
/* DM Soundex folding comparison window (dms_cleanup tmp[]). */
#define DMS_CAP_FOLD_FROM     4

typedef struct {
    const char *pattern;
    const char *at_start;
    const char *before_vowel;
    const char *default_code;
} dm_rule;

typedef struct {
    const char *from;
    int         from_len;   /* strlen(from), precomputed */
    const char *to;
} dm_folding;

/* ---- Daitch-Mokotoff rules (dmrules.txt) ---- */
static const dm_rule dm_rules[] = {
    { "a", "0", "", "" },
    { "e", "0", "", "" },
    { "i", "0", "", "" },
    { "o", "0", "", "" },
    { "u", "0", "", "" },
    { "b", "7", "7", "7" },
    { "d", "3", "3", "3" },
    { "f", "7", "7", "7" },
    { "g", "5", "5", "5" },
    { "h", "5", "5", "" },
    { "k", "5", "5", "5" },
    { "l", "8", "8", "8" },
    { "m", "6", "6", "6" },
    { "n", "6", "6", "6" },
    { "p", "7", "7", "7" },
    { "q", "5", "5", "5" },
    { "r", "9", "9", "9" },
    { "s", "4", "4", "4" },
    { "t", "3", "3", "3" },
    { "v", "7", "7", "7" },
    { "w", "7", "7", "7" },
    { "x", "5", "54", "54" },
    { "y", "1", "", "" },
    { "z", "4", "4", "4" },
    { "\305\243", "3|4", "3|4", "3|4" },
    { "\310\233", "3|4", "3|4", "3|4" },
    { "\304\231", "", "", "|6" },
    { "\304\205", "", "", "|6" },
    { "schtsch", "2", "4", "4" },
    { "schtsh", "2", "4", "4" },
    { "schtch", "2", "4", "4" },
    { "shtch", "2", "4", "4" },
    { "shtsh", "2", "4", "4" },
    { "stsch", "2", "4", "4" },
    { "ttsch", "4", "4", "4" },
    { "zhdzh", "2", "4", "4" },
    { "shch", "2", "4", "4" },
    { "scht", "2", "43", "43" },
    { "schd", "2", "43", "43" },
    { "stch", "2", "4", "4" },
    { "strz", "2", "4", "4" },
    { "strs", "2", "4", "4" },
    { "stsh", "2", "4", "4" },
    { "szcz", "2", "4", "4" },
    { "szcs", "2", "4", "4" },
    { "ttch", "4", "4", "4" },
    { "tsch", "4", "4", "4" },
    { "ttsz", "4", "4", "4" },
    { "zdzh", "2", "4", "4" },
    { "zsch", "4", "4", "4" },
    { "chs", "5", "54", "54" },
    { "csz", "4", "4", "4" },
    { "czs", "4", "4", "4" },
    { "drz", "4", "4", "4" },
    { "drs", "4", "4", "4" },
    { "dsh", "4", "4", "4" },
    { "dsz", "4", "4", "4" },
    { "dzh", "4", "4", "4" },
    { "dzs", "4", "4", "4" },
    { "sch", "4", "4", "4" },
    { "sht", "2", "43", "43" },
    { "szt", "2", "43", "43" },
    { "shd", "2", "43", "43" },
    { "szd", "2", "43", "43" },
    { "tch", "4", "4", "4" },
    { "trz", "4", "4", "4" },
    { "trs", "4", "4", "4" },
    { "tsh", "4", "4", "4" },
    { "tts", "4", "4", "4" },
    { "ttz", "4", "4", "4" },
    { "tzs", "4", "4", "4" },
    { "tsz", "4", "4", "4" },
    { "zdz", "2", "4", "4" },
    { "zhd", "2", "43", "43" },
    { "zsh", "4", "4", "4" },
    { "ai", "0", "1", "" },
    { "aj", "0", "1", "" },
    { "ay", "0", "1", "" },
    { "au", "0", "7", "" },
    { "cz", "4", "4", "4" },
    { "cs", "4", "4", "4" },
    { "ds", "4", "4", "4" },
    { "dz", "4", "4", "4" },
    { "dt", "3", "3", "3" },
    { "ei", "0", "1", "" },
    { "ej", "0", "1", "" },
    { "ey", "0", "1", "" },
    { "eu", "1", "1", "" },
    { "fb", "7", "7", "7" },
    { "ia", "1", "", "" },
    { "ie", "1", "", "" },
    { "io", "1", "", "" },
    { "iu", "1", "", "" },
    { "ks", "5", "54", "54" },
    { "kh", "5", "5", "5" },
    { "mn", "66", "66", "66" },
    { "nm", "66", "66", "66" },
    { "oi", "0", "1", "" },
    { "oj", "0", "1", "" },
    { "oy", "0", "1", "" },
    { "pf", "7", "7", "7" },
    { "ph", "7", "7", "7" },
    { "sh", "4", "4", "4" },
    { "sc", "2", "4", "4" },
    { "st", "2", "43", "43" },
    { "sd", "2", "43", "43" },
    { "sz", "4", "4", "4" },
    { "th", "3", "3", "3" },
    { "ts", "4", "4", "4" },
    { "tc", "4", "4", "4" },
    { "tz", "4", "4", "4" },
    { "ui", "0", "1", "" },
    { "uj", "0", "1", "" },
    { "uy", "0", "1", "" },
    { "ue", "0", "1", "" },
    { "zd", "2", "43", "43" },
    { "zh", "4", "4", "4" },
    { "zs", "4", "4", "4" },
    { "c", "4|5", "4|5", "4|5" },
    { "ch", "4|5", "4|5", "4|5" },
    { "ck", "5|45", "5|45", "5|45" },
    { "rs", "4|94", "4|94", "4|94" },
    { "rz", "4|94", "4|94", "4|94" },
    { "j", "1|4", "|4", "|4" },
};
static const size_t dm_rules_count = 124;

/* ---- Daitch-Mokotoff accent / ligature foldings ---- */
static const dm_folding dm_foldings[] = {
    { "\303\237", 2, "s" },
    { "\303\240", 2, "a" },
    { "\303\241", 2, "a" },
    { "\303\242", 2, "a" },
    { "\303\243", 2, "a" },
    { "\303\244", 2, "a" },
    { "\303\245", 2, "a" },
    { "\303\246", 2, "a" },
    { "\303\247", 2, "c" },
    { "\303\250", 2, "e" },
    { "\303\251", 2, "e" },
    { "\303\252", 2, "e" },
    { "\303\253", 2, "e" },
    { "\303\254", 2, "i" },
    { "\303\255", 2, "i" },
    { "\303\256", 2, "i" },
    { "\303\257", 2, "i" },
    { "\303\260", 2, "d" },
    { "\303\261", 2, "n" },
    { "\303\262", 2, "o" },
    { "\303\263", 2, "o" },
    { "\303\264", 2, "o" },
    { "\303\265", 2, "o" },
    { "\303\266", 2, "o" },
    { "\303\270", 2, "o" },
    { "\303\271", 2, "u" },
    { "\303\272", 2, "u" },
    { "\303\273", 2, "u" },
    { "\303\275", 2, "y" },
    { "\303\275", 2, "y" },
    { "\303\276", 2, "b" },
    { "\303\277", 2, "y" },
    { "\304\207", 2, "c" },
    { "\305\202", 2, "l" },
    { "\305\233", 2, "s" },
    { "\305\274", 2, "z" },
    { "\305\272", 2, "z" },
};
static const size_t dm_foldings_count = 37;

#endif /* PHP_DM_DATA_H */
