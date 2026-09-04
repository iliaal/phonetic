<?php

/**
 * @generate-class-entries
 *
 * Function signatures and the BMPM_* constants are declared here; run
 * scripts/regen_arginfo.sh (or /php-stub-regen) after editing to refresh
 * phonetic_arginfo.h.
 *
 * BMPM encode work/guess-cap overruns fail with E_ERROR and cannot be caught;
 * dm_soundex() instead keeps the first 128 distinct codes on branch saturation
 * (no error). @throws below covers only ValueError paths.
 */

/** @var int */
const BMPM_GENERIC = 0;
/** @var int */
const BMPM_ASHKENAZI = 1;
/** @var int */
const BMPM_SEPHARDIC = 2;
/* Accuracy values are deliberately disjoint from the name-type values above so
 * a misplaced constant (e.g. bmpm($s, BMPM_APPROX)) is rejected by argument
 * validation instead of silently running as a name type. Keep in sync with
 * PH_BMPM_APPROX / PH_BMPM_EXACT in src/bmpm.c. */
/** @var int */
const BMPM_APPROX = 10;
/** @var int */
const BMPM_EXACT = 20;

function double_metaphone(string $string, int $max_length = 4): array {}

/**
 * @throws ValueError on invalid $name_type/$accuracy, input over 4096 bytes,
 * or unknown forced $language ($language "any" included).
 */
function bmpm(string $string, int $name_type = BMPM_GENERIC, int $accuracy = BMPM_APPROX, string $language = ""): string {}

/**
 * @throws ValueError on input over 4096 bytes.
 */
function dm_soundex(string $string): array {}

function nysiis(string $string, int $max_length = 6): string {}

function match_rating(string $string): string {}

function nysiis_match(string $a, string $b, int $max_length = 6): bool {}

function match_rating_compare(string $a, string $b): bool {}

/**
 * Returns the Double Metaphone match strength of two strings:
 * 2 = primary codes agree, 1 = an alternate code crosses, 0 = no match.
 */
function double_metaphone_match(string $a, string $b, int $max_length = 4): int {}

/**
 * @throws ValueError on invalid $name_type/$accuracy, either input over
 * 4096 bytes, or unknown forced $language ($language "any" included).
 */
function bmpm_match(string $a, string $b, int $name_type = BMPM_GENERIC, int $accuracy = BMPM_APPROX, string $language = ""): bool {}

/**
 * @throws ValueError on either input over 4096 bytes.
 */
function dm_soundex_match(string $a, string $b): bool {}
