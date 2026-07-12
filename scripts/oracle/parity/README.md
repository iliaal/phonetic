# Commons Codec parity fixture

A frozen snapshot of Apache Commons Codec **1.17.1** (the parity oracle) output
for a curated word list, checked in CI so that a rule-data regeneration, a
`gen_bmpm_data.php` change, or an encoder edit that silently drifts from Commons
fails the build instead of shipping.

- `words.txt` — the input names (ASCII, no recognised BMPM prefixes, so no
  documented divergences are exercised).
- `golden.tsv` — `mode<TAB>word<TAB>expected`, one line per agreeing
  `(word, mode)` pair. Modes: `dm`, `nysiis_strict`, `mra`, `dmsoundex`,
  `bmpm_gen_approx`. `dmsoundex` and `bmpm_gen_approx` values are normalised to a
  sorted `|`-joined set (order-independent).
- `check.php` — runs the extension over `golden.tsv` and exits non-zero on any
  mismatch. This is what CI runs; it needs **no Java** (the golden is frozen).

The golden is intentionally a *matching* set: every line is a case where the
extension and Commons agree today, so any CI mismatch is a genuine regression.

## Run locally

```sh
php -d extension=modules/phonetic.so scripts/oracle/parity/check.php
```

## Regenerate after a deliberate change

Only regenerate when a divergence is intended and understood (the golden is a
frozen oracle, not extension output).

```sh
cd scripts/oracle
./fetch-codec.sh                                  # SHA-256-pinned jar
for m in "dm" "nysiis strict" "mra" "dmsoundex" "bmpm gen approx"; do
    java -cp commons-codec-1.17.1.jar Oracle.java $m < parity/words.txt
done
```

Normalise `dmsoundex` / `bmpm gen approx` to a sorted `|`-set (see `check.php`)
and rebuild `golden.tsv` as `mode<TAB>word<TAB>expected`. Requires Java 11+.
