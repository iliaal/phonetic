# phonetic

Native phonetic matching for PHP: **Double Metaphone**, **Beider-Morse Phonetic Matching (BMPM)**, and **Daitch-Mokotoff Soundex** — phonetic name-matching encoders that PHP core does not ship.

PHP core has `soundex()` and `metaphone()`, but not these three, which are the standard tools for fuzzy name matching, record linkage, and genealogy search across spelling and transliteration variants.

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
