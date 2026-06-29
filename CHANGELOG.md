# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- `double_metaphone(string $string, int $max_length = 4): array`. Clean-room Double Metaphone (Lawrence Philips), returning `['primary' => …, 'alternate' => …]`.
- `bmpm(string $string, int $name_type = BMPM_GENERIC, int $accuracy = BMPM_APPROX, string $language = ""): string`. Beider-Morse Phonetic Matching, with parity to Apache Commons Codec's default `BeiderMorseEncoder`. Constants: `BMPM_GENERIC`, `BMPM_ASHKENAZI`, `BMPM_SEPHARDIC`, `BMPM_APPROX`, `BMPM_EXACT`.
- `dm_soundex(string $string): array`. Daitch-Mokotoff Soundex (branching mode), returning the distinct 6-digit codes, with parity to Apache Commons Codec.
- Beider-Morse and Daitch-Mokotoff rule data vendored from Apache Commons Codec (Apache-2.0). Requires PHP 8.1+.

### Fixed

- `double_metaphone()`: skip non-letter bytes (digits, punctuation, hyphens)
  instead of duplicating the previous phoneme, matching Commons Codec's
  `default: index++`; `"Smith-Jones"` now codes identically to `"Smith Jones"`.
- `double_metaphone()`: locale-independent ASCII uppercasing (no longer affected
  by `LC_CTYPE`, e.g. a Turkish locale).
- `bmpm()`: bounded the GENERIC `d'`/name-prefix recursion (`BMPM_MAX_PREFIX_DEPTH`)
  to prevent a native stack-overflow crash on crafted deeply-nested input.

[Unreleased]: https://github.com/iliaal/phonetic/commits/master
