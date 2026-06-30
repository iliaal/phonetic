# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.1.0] - 2026-06-30

### Added

- `double_metaphone(string $string, int $max_length = 4): array`. Clean-room Double Metaphone (Lawrence Philips), returning `['primary' => …, 'alternate' => …]`.
- `bmpm(string $string, int $name_type = BMPM_GENERIC, int $accuracy = BMPM_APPROX, string $language = ""): string`. Beider-Morse Phonetic Matching, with parity to Apache Commons Codec's default `BeiderMorseEncoder`. Constants: `BMPM_GENERIC`, `BMPM_ASHKENAZI`, `BMPM_SEPHARDIC`, `BMPM_APPROX`, `BMPM_EXACT`.
- `dm_soundex(string $string): array`. Daitch-Mokotoff Soundex (branching mode), returning the distinct 6-digit codes, with parity to Apache Commons Codec.
- Beider-Morse and Daitch-Mokotoff rule data vendored from Apache Commons Codec (Apache-2.0). Requires PHP 8.1+.

### Fixed

- `double_metaphone()`: skip non-letter bytes instead of duplicating the previous
  phoneme (matching Commons Codec), so `"Smith-Jones"` codes like `"Smith Jones"`.
- `double_metaphone()`: locale-independent ASCII uppercasing (no longer affected
  by `LC_CTYPE`, e.g. a Turkish locale).
- `bmpm()`: bounded the GENERIC `d'`/name-prefix recursion (`BMPM_MAX_PREFIX_DEPTH`)
  to prevent a native stack-overflow crash on crafted deeply-nested input.
- `bmpm()`: size the word buffers to the input instead of a fixed 64, so names
  with more than 64 words no longer silently drop the excess.
- `bmpm()`: lowercase Cyrillic-script input so raw Cyrillic names (e.g. `Иванов`)
  match Commons Codec. Greek script remains a known limitation (final-sigma).
- `bmpm()`: validate UTF-8 continuation bytes when decoding, so malformed
  multi-byte input decodes as single raw bytes, consistent with the other encoders.
- `bmpm()` and `double_metaphone()`: reject input longer than `INT_MAX` bytes,
  closing an integer-narrowing heap overflow reachable only with a multi-GB `memory_limit`.

### Performance

- `dm_soundex()`: first-byte rule dispatch index and load-time pattern lengths,
  about 5.6x faster encoding.
- `bmpm()`: pre-decode rule contexts at load, skip language-guess rules whose
  required literals are absent, and small-string-optimize phoneme text (~24% faster).

[Unreleased]: https://github.com/iliaal/phonetic/compare/0.1.0...HEAD
[0.1.0]: https://github.com/iliaal/phonetic/releases/tag/0.1.0
