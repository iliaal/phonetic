# Parity oracle

Apache Commons Codec **1.17.1** (Apache-2.0) is the parity oracle for this
extension's golden test vectors. It ships the same BMPM / Daitch-Mokotoff rule
data we vendor under `vendor/commons-codec-bm/`, with zero GPL contact — the
GPL BMPM reference and the abydos port are never consulted.

The jar is fetched, not committed (see `.gitignore` here).

## Usage

```sh
./fetch-codec.sh    # downloads the jar from Maven Central, verifies pinned SHA-256

# one input per line on stdin, TSV "input<TAB>output" on stdout
echo Smith | java -cp commons-codec-1.17.1.jar Oracle.java dm            # primary|alternate
echo Smith | java -cp commons-codec-1.17.1.jar Oracle.java dm 0          # maxlen 0
echo Smith | java -cp commons-codec-1.17.1.jar Oracle.java bmpm gen approx
echo Smith | java -cp commons-codec-1.17.1.jar Oracle.java bmpm ash exact russian
echo Smith | java -cp commons-codec-1.17.1.jar Oracle.java dmsoundex     # '|'-joined branches
echo Smith | java -cp commons-codec-1.17.1.jar Oracle.java nysiis strict
echo Smith | java -cp commons-codec-1.17.1.jar Oracle.java mra
```

Per-line encoder errors are emitted as `input<TAB>!ERROR:<message>` instead of
aborting the run.

## Regenerating golden vectors

1. `./fetch-codec.sh`
2. Pipe the word list of the `.phpt` under revision through the matching mode.
3. Paste the TSV output into the test's `--EXPECT--` section (adapting the
   formatting the test uses).

Requires Java 11+ (single-file source launch).
