# Commons Codec parity fixture

The checked-in fixture freezes Apache Commons Codec 1.17.1 output for every
configured mode and every name in `words.txt`. CI rejects missing, duplicate,
and unexpected mode/name pairs before comparing encoder output.

The matrix covers:

- Double Metaphone at the public default length
- NYSIIS strict and full modes
- Match Rating Approach
- Daitch-Mokotoff Soundex
- BMPM generic, Ashkenazi, and Sephardic name types in approximate and exact modes

`dmsoundex` and all BMPM results are normalized to sorted `|`-joined sets, so
the fixture ignores alternative ordering. The word list stays ASCII and avoids
recognized BMPM prefixes because forced-language prefix behavior deliberately
diverges from Commons Codec.

## Run locally

```sh
php -d extension=modules/phonetic.so scripts/oracle/parity/check.php
```

The checker needs no Java. It reads the frozen `golden.tsv` and requires the
complete 11-mode by 50-word matrix.

## Regenerate from the pinned oracle

```sh
scripts/oracle/fetch-codec.sh
php scripts/oracle/parity/generate.php
php -d extension=modules/phonetic.so scripts/oracle/parity/check.php
```

`generate.php` verifies the jar SHA-256 before invoking `Oracle.java`, checks
the returned word order and row count for each mode, then atomically replaces
`golden.tsv`. Regenerate only after an intended and understood parity change.
