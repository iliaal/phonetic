# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Initial scaffold of the `phonetic` extension (builds and loads on PHP 8.1–8.5).
- `double_metaphone(string $string, int $max_length = 4): array` — clean-room
  Double Metaphone (Lawrence Philips) returning `['primary' => …, 'alternate' => …]`.
- Vendored Beider-Morse and Daitch-Mokotoff rule data from Apache Commons Codec
  (Apache-2.0) under `vendor/commons-codec-bm/`, with `scripts/gen_bmpm_data.php`
  generating `src/bmpm_data.h`. Licensing reflected in `LICENSE` (Section 2) and
  the `composer.json` SPDX array.
- Still to land for 0.1.0: `bmpm()` and `dm_soundex()` engines over the vendored data.

[Unreleased]: https://github.com/iliaal/phonetic/commits/master
