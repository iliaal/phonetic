# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.2.0] - 2026-06-30

### Added

- `nysiis(string $string, int $max_length = 6): string`. NYSIIS encoder (clean-room), with parity to Apache Commons Codec's `Nysiis`. `$max_length <= 0` returns the full, non-truncated key.
- `match_rating(string $string): string`. Match Rating Approach codex (clean-room), with parity to Apache Commons Codec.
- `double_metaphone_match(string $a, string $b, int $max_length = 4): int`. Double Metaphone match strength: `2` when the primary keys agree, `1` when an alternate key crosses, `0` for no match.
- `bmpm_match(string $a, string $b, int $name_type = BMPM_GENERIC, int $accuracy = BMPM_APPROX, string $language = ""): bool`. True when two names share a Beider-Morse phoneme token.
- `dm_soundex_match(string $a, string $b): bool`. True when two names share a Daitch-Mokotoff code.
- `nysiis_match(string $a, string $b, int $max_length = 6): bool`. True when two names produce the same NYSIIS key.
- `match_rating_compare(string $a, string $b): bool`. Match Rating Approach homophone test (`isEncodeEquals`), applying the length and minimum-rating rules.
- Prebuilt binaries attached to releases: Linux (glibc x86_64 and arm64) and macOS (arm64) for PHP 8.4 and 8.5, alongside the existing Windows DLLs.

### Fixed

- `match_rating()`: count UTF-8 code points, not bytes, in the trivial-input
  guard, so a lone multi-byte letter has no code (`match_rating("é")` is now `""`).
- `match_rating()`: expand `ß` to `SS` before folding accents, so
  `match_rating("Straße")` returns `"STRS"`.

### Security

- `dm_soundex()` and `dm_soundex_match()`: reject input over 4096 bytes with a
  `ValueError`, so untrusted long input cannot become a multi-second CPU sink.
- `bmpm()`: skip and reject zero-length rule patterns, closing a latent
  infinite-loop path against a corrupted rule set.

### Performance

- `dm_soundex()`: O(1) hashed branch-set dedup for branchy input (threshold-gated
  so short names keep the linear path), making long input scale linearly.
- `match_rating_compare()`: stack scratch buffer in the comparison pass instead of
  two heap allocations per call.

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

[Unreleased]: https://github.com/iliaal/phonetic/compare/0.2.0...HEAD
[0.2.0]: https://github.com/iliaal/phonetic/releases/tag/0.2.0
[0.1.0]: https://github.com/iliaal/phonetic/releases/tag/0.1.0
