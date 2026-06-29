# Vendored rule data: Apache Commons Codec (Beider-Morse / Daitch-Mokotoff)

The `.txt` rule files under this directory are vendored **verbatim** from Apache
Commons Codec and drive the Beider-Morse Phonetic Matching (BMPM) and
Daitch-Mokotoff Soundex engines. Each file retains its original Apache-2.0
license header.

| | |
|---|---|
| Upstream repository | https://github.com/apache/commons-codec |
| Cloned commit | `8f2a08ebc73397c56bfd55131709d1fb151b1726` |
| Vendored on | 2026-06-29 |
| License | Apache License 2.0 |

These files are the data layer only. The phonetic engines that consume them are
original BSD-3-Clause code in this project; see the top-level `LICENSE`.

## Why Commons Codec specifically

The rule data must come from Apache Commons Codec (Apache-2.0). The original
Beider-Morse PHP reference implementation and the `abydos` project are both
GPL-3.0; vendoring their data would force this entire extension to GPL. Commons
Codec ships the same algorithmic rule tables under Apache-2.0, which is
compatible with the BSD-3-Clause base of this extension.

## Upstream paths

| Vendored path | Upstream path |
|---|---|
| `bm/*.txt` | `src/main/resources/org/apache/commons/codec/language/bm/*.txt` |
| `dmrules.txt` | `src/main/resources/org/apache/commons/codec/language/dmrules.txt` |

## Vendored files

- `dmrules.txt` — Daitch-Mokotoff rule + accent-folding table.
- `bm/` — 127 Beider-Morse rule files. For each name type (`gen`, `ash`, `sep`):
  - `<nt>_languages.txt` — ordered language list for that name type.
  - `<nt>_lang.txt` — language-guessing rules.
  - `<nt>_rules_<lang>.txt`, `<nt>_approx_<lang>.txt`, `<nt>_exact_<lang>.txt`
    — per-language transliteration / approximation / exact rule sets.
  - `<nt>_approx_common.txt`, `<nt>_exact_common.txt` — shared rule sets.
  - `<nt>_exact_approx_common.txt`, `<nt>_hebrew_common.txt` — helper files
    spliced into the above via `#include`.
  - `lang.txt` — license-header-only placeholder retained from upstream.

The full file list is the directory contents; nothing was added, removed, or
modified relative to the upstream commit above.

## Regeneration

`scripts/gen_bmpm_data.php` parses these files into `src/bmpm_data.h`. After
updating the vendored data, rerun:

```
php scripts/gen_bmpm_data.php
```
