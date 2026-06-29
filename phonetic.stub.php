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
