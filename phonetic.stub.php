<?php

/**
 * @generate-class-entries
 *
 * Function signatures and the BMPM_* constants are declared here; run
 * /php-stub-regen after editing to refresh phonetic_arginfo.h.
 */

/** @var int */
const BMPM_GENERIC = 0;
/** @var int */
const BMPM_ASHKENAZI = 1;
/** @var int */
const BMPM_SEPHARDIC = 2;
/** @var int */
const BMPM_APPROX = 1;
/** @var int */
const BMPM_EXACT = 2;

function double_metaphone(string $string, int $max_length = 4): array {}

function bmpm(string $string, int $name_type = BMPM_GENERIC, int $accuracy = BMPM_APPROX, string $language = ""): string {}

function dm_soundex(string $string): array {}

function nysiis(string $string, int $max_length = 6): string {}

function match_rating(string $string): string {}

function nysiis_match(string $string1, string $string2, int $max_length = 6): bool {}

function match_rating_compare(string $string1, string $string2): bool {}

/**
 * Returns the Double Metaphone match strength of two strings:
 * 2 = primary codes agree, 1 = an alternate code crosses, 0 = no match.
 */
function double_metaphone_match(string $string1, string $string2, int $max_length = 4): int {}

function bmpm_match(string $string1, string $string2, int $name_type = BMPM_GENERIC, int $accuracy = BMPM_APPROX, string $language = ""): bool {}

function dm_soundex_match(string $string1, string $string2): bool {}
