# Security policy

phonetic is a C PHP extension that encodes names into phonetic keys
(Double Metaphone, Beider-Morse, Daitch-Mokotoff Soundex, NYSIIS, Match
Rating) and compares them. Every entry point passes arbitrary
user-controlled strings to hand-written C string code, so the main risks
are memory-safety bugs in the encoders and CPU-exhaustion DoS from
pathological input.

## Supported versions

| Version | Supported          |
|---------|--------------------|
| 0.4.x   | :white_check_mark: |
| 0.3.x   | :x:                |

Pre-1.0: the current minor gets security fixes, and the API may still
move between minors until 1.0.

## Reporting a vulnerability

**Do not file a public GitHub issue for security vulnerabilities.**

Use GitHub's private security advisory feature at
<https://github.com/iliaal/phonetic/security/advisories/new>
or email Ilia Alshanetsky <ilia@ilia.ws> directly.

Please include:

- Affected phonetic version (`php -r 'echo phpversion("phonetic");'`)
- PHP version (`php -v`)
- A minimal reproducing case (the PHP call and the exact input string
  that triggers it, small enough to inline in the report)
- Impact: crash / memory corruption / info disclosure / DoS / etc.
- Whether you've coordinated disclosure with anyone else

Acknowledgement within 7 days, fix or status update within 30. Once a
fix is released the advisory becomes public.

## Scope

In scope:

- Crashes, memory corruption, or out-of-bounds reads in the encoder
  code (`src/double_metaphone.c`, `src/bmpm.c`, `src/dm_soundex.c`,
  `src/nysiis.c`, `src/match_rating.c`) reachable from any of the ten
  public functions: `double_metaphone()`, `bmpm()`, `dm_soundex()`,
  `nysiis()`, `match_rating()`, and their `*_match` / `*_compare`
  counterparts.
- Integer or buffer overflows driven by input length, `max_length`, or
  the BMPM `name_type` / `accuracy` / `language` arguments.
- Uncontrolled CPU or memory consumption from crafted input
  disproportionate to its size, such as super-linear BMPM expansion or
  runaway recursion. These limits are security boundaries, and bypasses
  are in scope:
  - the 4096-byte input cap on `bmpm()`, `bmpm_match()`, `dm_soundex()`,
    and `dm_soundex_match()`
  - the BMPM prefix recursion depth of 6, after which encoding continues
    without further prefix branching
  - the BMPM budgets per encoded operand, shared across its prefix recursion:
    2048 cumulative distinct phonemes added to final-pass sets, 4 MiB of
    cumulative text added with those phonemes, 32 MiB of phoneme-text copying
    while expanding rule alternatives, and 4,194,304 cumulative language-guess
    scan units (code-point count times rule count per guess)
  - the BMPM serialized-output cap of 256 times the original operand's byte
    length, counting each leaf's serialized bytes and five punctuation bytes
    per prefix pair once across the combined prefix tree
  - the Daitch-Mokotoff cap of 128 live branches with distinct accumulated
    codes; final duplicate codes are removed
  - the Daitch-Mokotoff work budget of 32,768 executed branch/replacement
    expansions per encoded operand; reused transitions do not consume this
    budget, and match operands have independent budgets when encoded

  BMPM resource-budget overruns throw catchable `ValueError` exceptions without
  returning partial output. A short input can trigger an exception, including
  the 14-byte `"de akeaxhaeasz"`. These counters do not impose a wall-clock CPU
  limit or a total-memory limit. Daitch-Mokotoff saturation retains the first
  128 branches with distinct accumulated codes without an exception; exceeding
  its work budget throws catchable `ValueError` without returning partial codes.

  Crafted input that drives disproportionate CPU or memory up to or past
  these budgets is also in scope.
- Arginfo / ZPP mismatches that cause undefined behavior reachable from
  PHP.

Out of scope:

- Phonetically "wrong" output. The encoders are heuristics; report a key
  you disagree with as a regular bug.
- Passing untrusted input to uncapped encoders without your own length
  limits. Bound input size at the application layer as you would for
  any string-processing routine.
