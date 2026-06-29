<?php

/**
 * @generate-class-entries
 *
 * Function signatures land here as each algorithm is implemented:
 *   double_metaphone(string $string, int $max_length = 4): array
 *   bmpm(string $string, int $name_type = BMPM_GENERIC, int $accuracy = BMPM_APPROX, string $language = ""): string
 *   dm_soundex(string $string): array
 *
 * Run /php-stub-regen after editing to refresh phonetic_arginfo.h.
 */

function double_metaphone(string $string, int $max_length = 4): array {}

function bmpm(string $string, int $name_type = BMPM_GENERIC, int $accuracy = BMPM_APPROX, string $language = ""): string {}

function dm_soundex(string $string): array {}
