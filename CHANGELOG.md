# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Initial scaffold of the `phonetic` extension (builds and loads on PHP 8.1–8.5).
- `double_metaphone(string $string, int $max_length = 4): array` — clean-room
  Double Metaphone (Lawrence Philips) returning `['primary' => …, 'alternate' => …]`.
- `bmpm(string $string, int $name_type = BMPM_GENERIC, int $accuracy = BMPM_APPROX, string $language = ""): string`
  — Beider-Morse Phonetic Matching, reproducing Apache Commons Codec's default
  `BeiderMorseEncoder` semantics (100% exact parity over a 2234-comparison corpus).
  Constants: `BMPM_GENERIC`, `BMPM_ASHKENAZI`, `BMPM_SEPHARDIC`, `BMPM_APPROX`, `BMPM_EXACT`.
- `dm_soundex(string $string): array` — Daitch-Mokotoff Soundex, returning the
  list of distinct 6-digit codes, reproducing Apache Commons Codec's
  `DaitchMokotoffSoundex` (branching mode) at 100% exact set-parity.
- Vendored Beider-Morse and Daitch-Mokotoff rule data from Apache Commons Codec
  (Apache-2.0) under `vendor/commons-codec-bm/`, with `scripts/gen_bmpm_data.php`
  generating `src/bmpm_data.h`. Licensing reflected in `LICENSE` (Section 2) and
  the `composer.json` SPDX array.

All three encoders build warning-clean and pass their golden-vector suites on
PHP 8.1 through 8.5.

[Unreleased]: https://github.com/iliaal/phonetic/commits/master
