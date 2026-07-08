# Security policy

phonetic is a pure-C PHP extension that encodes names into phonetic
keys (Double Metaphone, Beider-Morse, Daitch-Mokotoff Soundex, NYSIIS,
Match Rating) and compares them. Every entry point takes arbitrary
user-controlled strings straight into hand-written C string-processing
code, so the realistic threat surface is memory-safety bugs in the
encoders and CPU-exhaustion DoS from pathological input.

## Supported versions

| Version | Supported          |
|---------|--------------------|
| 0.2.x   | :white_check_mark: |

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
  runaway recursion. `bmpm()`, `bmpm_match()`, `dm_soundex()`, and
  `dm_soundex_match()` reject inputs over 4096 bytes; those caps and the
  BMPM recursion bound are security boundaries, and bypasses of them are
  in scope.
- Arginfo / ZPP mismatches that cause undefined behavior reachable from
  PHP.

Out of scope:

- Phonetically "wrong" output. The encoders are heuristics; a key you
  disagree with is a correctness question, not a vulnerability.
- Passing untrusted input to uncapped encoders without your own length
  limits. Bound input size at the application layer as you would for
  any string-processing routine.
