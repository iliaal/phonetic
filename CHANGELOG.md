# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed

- CI: PIE smoke pins a false `bmpm_match` and Double Metaphone match strengths;
  Windows jobs assert `php_phonetic.dll` was produced after the builder step.
- Tests: cleaned-empty identity match pins; multiword concat non-match;
  SEPHARDIC non-identity true pair; forced-language case-sensitivity.
- `scripts/check_arginfo.php` locks `PH_BMPM_*` accuracy ints to the stub;
  smoke no longer hardcodes the version string; regen_arginfo docs fixed.
- `bmpm()` MINIT: language lists exceeding `BMPM_CAP_LANGUAGES` hard-fail;
  oversize phoneme `|` alternative lists hard-fail with `E_CORE_ERROR` (same
  policy as language brackets; generator already enforces).
- `match_rating_compare()`: skip second encode when the first cleans to empty.
- `config.w32`: require PHP 8.1+ (parity with `config.m4`).
- Tests: match-helper oracle pins; forced-language `bmpm_match` flip;
  identical unencodable DM match; final-J space match; `John Smith` multiword.
- Docs: forced-language API note; Double Metaphone unmapped non-ASCII fold;
  LICENSE Section 2 names generated `bmpm_data.h` embedding.
- Docs: DM index example queries every non-`000000` code (not only `[0]`).
- `scripts/regen_arginfo.sh`: prefers `$HOME/php-install-PHP-8.1/bin/php` when
  `PHP_BIN` is unset; documents tokenizer/gen_stub requirements.
- `bmpm()` / `bmpm_match()`: forced `$language = "any"` is rejected with
  `ValueError` (it is the default ruleset label, not a force option). Pass an
  empty string for auto-detect.
- `double_metaphone()`: stop encoding once both primary and alternate codes
  reach `max_length` (still post-truncates multi-char overshoot). Same results
  for default length 4; less work on long inputs.
- Shared `PHONETIC_MAX_RULED_INPUT` (4096) for BMPM and DM Soundex caps;
  generated `DMS_CAP_*` macros size DM replacement alt buffers.
- Match helpers short-circuit empty first encode and identical operands
  (behavior-preserving).
- `bmpm()`: MINIT hard-fails if a language bracket exceeds `BMPM_CAP_LANG_BRACKET`
  (was silent clip). Generator already enforces the cap.
- `bmpm()`: lowered `BMPM_MAX_PREFIX_DEPTH` from 16 to 6 (still above realistic
  name nesting; bounds exponential prefix dual-encode fan-out under the 4096-byte
  input cap).
- Internal BMPM rule-type enum tokens renamed to `BMPM_RT_APPROX` /
  `BMPM_RT_EXACT` so they cannot collide with public accuracy constants.
  Generated stack caps (`BMPM_CAP_*`) are shared between the generator and
  `src/bmpm.c`.

### Fixed

- Docs: Cyrillic lowercasing attributed to `bmpm()` only; DM `"000000"` dual
  meaning and index recipe; MRA identity short-circuit; forced-language `"any"`;
  NFC vs NFD BMPM note.
- Tests: multi-code `dm_soundex_match`, dual-sentinel, MRA first3+last3 content,
  SEPHARDIC/`any` match errors, oracle `parity_golden.phpt`, ü fold comment.
- `double_metaphone()`: JOSE/SAN `J` branch no longer applies the double-J
  advance (Commons Codec `handleJ` advances by 1 on that arm only).
- `dm_soundex()`: treat U+0085 (NEXT LINE) as whitespace, matching Java
  `Character.isWhitespace`.
- `bmpm()` MINIT: zero-initialize ruleset/language indexes so a mid-build abort
  cannot free garbage pointers on unload.
- `bmpm()`: overflow-safe allocation for phoneme-set growth and prefix pair
  buffers; final-rule merge uses a hash map + one sort instead of O(R²)
  insert-sort (same comparator order).
- Docs/tests: empty/unencodable match contracts, BMPM validation and accuracy
  values, `bmpm_match` accuracy forwarding, and word-final `J` alternate space.

## [0.4.0] - 2026-07-25

### Changed

- **BREAKING:** `BMPM_APPROX` is now `10` and `BMPM_EXACT` is now `20` (were
  `1` and `2`). The accuracy values are now disjoint from the name-type values
  (`BMPM_GENERIC`/`BMPM_ASHKENAZI`/`BMPM_SEPHARDIC` = `0`/`1`/`2`), so a
  misplaced constant such as `bmpm($name, BMPM_APPROX)` is rejected by argument
  validation instead of silently running as a name type. Code that uses the
  constant names is unaffected; only code hard-coding the numeric values `1`/`2`
  for the `$accuracy` argument needs updating.
- Version string is `0.4.0`.

### Fixed

- `nysiis()`: corrected a source comment that claimed parity with Commons Codec's
  `SoundexUtils.clean`; the ASCII-only cleaning is a deliberate, documented
  restriction.
- `scripts/gen_bmpm_data.php`: `src/bmpm_data.h` is now written to a temp file and
  renamed into place, so an interrupted regeneration can't leave a truncated
  header.

## [0.3.0] - 2026-07-09

### Fixed

- `double_metaphone()`: four rules now follow the published Philips algorithm
  (`thumb` `0M|TM`, `high`/`hugh` `H`, `jose` `HS|HS`, `mooch` `MK`).
- `double_metaphone()`: leading/trailing whitespace (any byte `<= U+0020`) is
  trimmed, so `"\tOtto"` and `"\nKNIGHT"` keep their word-start anchors.
- `double_metaphone()`: `-gier` uses the French soft-G branch anywhere, not only
  word-final, so `brigier` codes `PRJ|PRJR` and `angiera` codes `ANJR|ANJR`.
- `double_metaphone()`: malformed UTF-8 folds byte-wise instead of swallowing
  the letters after a stray lead byte.
- UTF-8 decoding (all encoders): overlong, UTF-16 surrogate, and above-U+10FFFF
  sequences fold the lead byte to Latin-1 rather than decoding as a code point.
- `bmpm()` and `dm_soundex()`: U+1E9E (`ẞ`) folds to `ß`, so `STRAẞE` encodes
  like `Straße`.
- `bmpm_match()`: token intersection handles the `(remainder)-(combined)` prefix
  group syntax, so `bmpm_match("van Smith", "Smith")` is true.
- `bmpm()` / `bmpm_match()`: a forced `$language` is honoured inside the
  prefix/`d'` split (deliberate divergence from Commons Codec).
- `bmpm()` / `bmpm_match()`: the invalid-`$language` exception preview is
  binary-safe (non-printables as `?`) and bounded to 32 bytes.
- `bmpm()`: leading/trailing control characters (`<= U+0020`) are trimmed.
- `dm_soundex_match()`: two inputs that match no rule no longer compare equal via
  the padded `"000000"` sentinel.
- U+0130 (`İ`) lower-cases to `i` in `bmpm()` and `dm_soundex()`, so `İstanbul`
  matches `istanbul`.

### Security

- `match_rating()` and `match_rating_compare()` no longer write past the clean
  buffer on a bare Latin-1 `0xDF` that expands to `SS`.
- `bmpm()` and `bmpm_match()` reject input over 4096 bytes with a `ValueError`,
  matching the Daitch-Mokotoff cap.

### Changed

- The five comparison helpers' parameters are named `$a`/`$b` (affects
  named-argument calls only).
- `composer.json` license is the SPDX expression `BSD-3-Clause AND Apache-2.0`
  (was a disjunctive array).
- The README BMPM token-splitting recipe splits on `(`, `)`, `|`, `-` with
  `PREG_SPLIT_NO_EMPTY`, matching `bmpm_match()`'s tokenizer.
- Performance: `bmpm()` decodes input once and pre-parses rules at init;
  Double Metaphone, Daitch-Mokotoff, and NYSIIS drop per-call allocations.

### For contributors

- macOS CI builds with `--enable-phonetic-dev`, enforcing the same warning-clean
  build as Linux.
- Generated BMPM table decoding fails module init if a checked-in table exceeds
  the C buffer caps.
- CI preflights `extension_loaded("phonetic")` and fails on any
  skipped/borked/leaked test, so a silent load failure can't pass green.
- `scripts/pie-smoke.sh` treats a `pie install` failure as fatal (opt out with
  `ALLOW_MANUAL_FALLBACK=1`).

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

[Unreleased]: https://github.com/iliaal/phonetic/compare/0.4.0...HEAD
[0.4.0]: https://github.com/iliaal/phonetic/releases/tag/0.4.0
[0.3.0]: https://github.com/iliaal/phonetic/releases/tag/0.3.0
[0.2.0]: https://github.com/iliaal/phonetic/releases/tag/0.2.0
[0.1.0]: https://github.com/iliaal/phonetic/releases/tag/0.1.0
