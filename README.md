# phonetic

Native phonetic matching for PHP: **Double Metaphone**, **Beider-Morse Phonetic Matching (BMPM)**, and **Daitch-Mokotoff Soundex** — phonetic name-matching encoders that PHP core does not ship.

PHP core has `soundex()` and `metaphone()`, but not these three, which are the standard tools for fuzzy name matching, record linkage, and genealogy search across spelling and transliteration variants.

## Status

Early scaffold. The extension builds and loads; the functions below are being implemented for the 0.1.0 release.

## Planned API

```php
// Double Metaphone — primary + alternate phonetic keys.
double_metaphone(string $string, int $max_length = 4): array
// => ['primary' => 'KMP', 'alternate' => 'KMP']

// Beider-Morse Phonetic Matching — language-aware token set.
bmpm(string $string, int $name_type = BMPM_GENERIC, int $accuracy = BMPM_APPROX, string $language = ""): string

// Daitch-Mokotoff Soundex — set of 6-digit codes.
dm_soundex(string $string): array
```

BMPM option constants: `BMPM_GENERIC`, `BMPM_ASHKENAZI`, `BMPM_SEPHARDIC`, `BMPM_APPROX`, `BMPM_EXACT`.

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
