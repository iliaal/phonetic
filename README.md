# phonetic

Native phonetic matching for PHP: **Double Metaphone**, **Beider-Morse Phonetic Matching (BMPM)**, and **Daitch-Mokotoff Soundex**, the phonetic name-matching encoders that PHP core does not ship.

PHP core has `soundex()` and `metaphone()`, but not these three, which are the standard tools for fuzzy name matching, record linkage, and genealogy search across spelling and transliteration variants.

## Choosing an algorithm

| | Double Metaphone | BMPM | Daitch-Mokotoff Soundex |
|---|---|---|---|
| Output | primary + alternate key | language-aware token set | distinct 6-digit codes |
| Two names match when | keys are equal | token sets intersect | code sets intersect |
| Strongest for | English and general Latin-script names | cross-language and transliteration variants (Slavic, Germanic, Hebrew, Romance) | Eastern-European and Ashkenazi surnames, genealogy |
| Spelling-variant recall | good | highest | high, within its language model |
| Ambiguity handling | up to 2 keys | many tokens | multiple codes |
| Relative speed | fastest (1.0x) | slowest (~13x) | middle (~5x) |
| Data source | clean-room published algorithm | Apache Commons Codec rule data | Apache Commons Codec rule data |

Rule of thumb: reach for Double Metaphone as a fast general-purpose default, BMPM when names cross languages or scripts, and Daitch-Mokotoff for Eastern-European and Jewish genealogy where it is the field standard.

## API

### Double Metaphone

Primary + alternate phonetic keys (Lawrence Philips). Clean-room implementation.

```php
double_metaphone(string $string, int $max_length = 4): array

double_metaphone("Schwarzenegger");        // ['primary' => 'XRSN', 'alternate' => 'XFRT']
double_metaphone("Smith");                 // ['primary' => 'SM0',  'alternate' => 'XMT']
double_metaphone("Catherine", 3);          // ['primary' => 'K0R',  'alternate' => 'KTR']
```

`alternate` equals `primary` when the algorithm produced no alternate branch. `max_length` caps each key (default 4; `0` or negative = unlimited).

### Beider-Morse Phonetic Matching

Language-aware token set, joined by `|` (alternatives) and `-` (words). Matches Apache Commons Codec's default `BeiderMorseEncoder`.

```php
bmpm(string $string, int $name_type = BMPM_GENERIC, int $accuracy = BMPM_APPROX, string $language = ""): string

bmpm("Jackson");                           // "iakson|iaksun|...|zokson"
bmpm("Garcia", BMPM_SEPHARDIC, BMPM_EXACT);// "garsia|gartSa"
```

Empty `$language` auto-detects; pass a language name (e.g. `"russian"`) to force it. Constants: `BMPM_GENERIC`, `BMPM_ASHKENAZI`, `BMPM_SEPHARDIC`, `BMPM_APPROX`, `BMPM_EXACT`.

### Daitch-Mokotoff Soundex

List of distinct 6-digit codes (the algorithm branches on ambiguous letters). Matches Apache Commons Codec's `DaitchMokotoffSoundex` in branching mode.

```php
dm_soundex(string $string): array

dm_soundex("Auerbach");                    // ['097400', '097500']
dm_soundex("Peters");                      // ['734000', '739400']
```

## Usage

Encode each name, then compare. Double Metaphone matches on key equality; BMPM and Daitch-Mokotoff produce sets, so two names match when their sets intersect.

```php
// Double Metaphone: equal primary, or a primary that crosses the other's alternate
function names_sound_alike(string $a, string $b): bool {
    $x = double_metaphone($a);
    $y = double_metaphone($b);
    return $x['primary']   === $y['primary']
        || $x['primary']   === $y['alternate']
        || $x['alternate'] === $y['primary'];
}

names_sound_alike("Catherine", "Kathryn");   // true  (both K0RN / KTRN)

// Daitch-Mokotoff: codes match when the sets overlap
(bool) array_intersect(
    dm_soundex("Moskowitz"),                 // ['645740']
    dm_soundex("Moskovitz")                  // ['645740']  -> true
);

// BMPM: split a single-word token string on '|' and intersect
$a = explode('|', bmpm("Peterson"));         // pitirzon, pitirzun
$b = explode('|', bmpm("Petersen"));         // ..., pitirzon, ...
(bool) array_intersect($a, $b);              // true
```

For indexed lookup, store the encoded key(s) with each record and query by encoded value instead of re-encoding at search time. Multi-word BMPM output also separates words with `-`, so split on both `|` and `-` for those.

## Performance

Single-name encode, warm, PHP 8.1 on one core, over a representative mix of 18 short names. Absolute time scales with input length; the relative ordering is the stable part.

| function | per call | throughput | relative |
|---|---|---|---|
| `double_metaphone()` | ~2.7 µs | ~370k/sec | 1.0x |
| `dm_soundex()` | ~13 µs | ~77k/sec | ~5x slower |
| `bmpm()` | ~34 µs | ~29k/sec | ~13x slower |

Double Metaphone is a single linear pass over the input. Daitch-Mokotoff branches on ambiguous letters and dedups the resulting codes. BMPM is the heaviest: language detection, a main transliteration pass, and two final rule passes, expanding a Cartesian product of phoneme alternatives capped at 20 per word. Passing an explicit `$language` skips detection, but the rule passes dominate, so it is not materially faster. Choose BMPM for recall, not throughput.

## Notes & limitations

- Input is UTF-8. `bmpm()` and `dm_soundex()` fold accented Latin and lowercase both
  Latin and Cyrillic script before rule matching, so raw `Иванов` encodes correctly.
- **Greek-script input is a known limitation:** Greek capitals are not lowercased
  (the algorithm's context-sensitive final-sigma cannot be expressed by a point-wise
  case map), so pass Greek names already lowercased or romanized.
- `double_metaphone()` targets ASCII/Latin; non-letter bytes are skipped, matching
  Apache Commons Codec.

## Install

Via [PIE](https://github.com/php/pie):

```sh
pie install iliaal/phonetic
```

Requires PHP 8.1 or later.

## License

BSD 3-Clause (see [LICENSE](LICENSE)).

The Beider-Morse and Daitch-Mokotoff rule data is vendored from
[Apache Commons Codec](https://commons.apache.org/proper/commons-codec/) under
the Apache License 2.0; its notice is included in Section 2 of the LICENSE file.
Double Metaphone is a clean-room implementation of the published algorithm.
