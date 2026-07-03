/*
  +----------------------------------------------------------------------+
  | Copyright (c) 2026, Ilia Alshanetsky                                 |
  | Copyright (c) 2026, Advanced Internet Designs Inc.                   |
  +----------------------------------------------------------------------+
  | This source file is subject to the BSD 3-Clause license that is      |
  | bundled with this package in the file LICENSE.                       |
  +----------------------------------------------------------------------+
  | Author: Ilia Alshanetsky <ilia@ilia.ws>                              |
  +----------------------------------------------------------------------+
*/

/* NYSIIS (New York State Identification and Intelligence System) encoder.
 * Reimplementation of the published algorithm; no third-party data. Validated
 * against Apache Commons Codec's Nysiis (strict mode) as parity oracle.
 * Operates on ASCII letters: non-letters and non-ASCII bytes are dropped
 * during cleaning, matching the Soundex-style clean the reference uses. */

#ifdef HAVE_CONFIG_H
#include "config.h"
#endif

#include <string.h>

#include "php.h"
#include "php_phonetic.h"

static zend_always_inline int ny_is_vowel(char c)
{
	return c == 'A' || c == 'E' || c == 'I' || c == 'O' || c == 'U';
}

/* Keep ASCII letters only, upper-cased. Returns the kept count. */
static size_t ny_clean(const char *src, size_t len, char *out)
{
	size_t i, n = 0;
	for (i = 0; i < len; i++) {
		unsigned char c = (unsigned char) src[i];
		if (c >= 'a' && c <= 'z') {
			out[n++] = (char) (c - ('a' - 'A'));
		} else if (c >= 'A' && c <= 'Z') {
			out[n++] = (char) c;
		}
	}
	return n;
}

/* transcodeRemaining: sliding window [prev, curr, next, aNext]. Writes 1-3
 * replacement chars into dst and returns the count. */
static int ny_transcode(char prev, char curr, char next, char aNext, char *dst)
{
	/* EV -> AF */
	if (curr == 'E' && next == 'V') {
		dst[0] = 'A'; dst[1] = 'F';
		return 2;
	}
	/* A E I O U -> A */
	if (ny_is_vowel(curr)) {
		dst[0] = 'A';
		return 1;
	}
	switch (curr) {
	case 'Q': dst[0] = 'G'; return 1;
	case 'Z': dst[0] = 'S'; return 1;
	case 'M': dst[0] = 'N'; return 1;
	case 'K':
		if (next == 'N') { dst[0] = 'N'; dst[1] = 'N'; return 2; }
		dst[0] = 'C';
		return 1;
	default:
		break;
	}
	/* SCH -> SSS */
	if (curr == 'S' && next == 'C' && aNext == 'H') {
		dst[0] = 'S'; dst[1] = 'S'; dst[2] = 'S';
		return 3;
	}
	/* PH -> FF */
	if (curr == 'P' && next == 'H') {
		dst[0] = 'F'; dst[1] = 'F';
		return 2;
	}
	/* H -> if previous or next is a non-vowel, previous */
	if (curr == 'H' && (!ny_is_vowel(prev) || !ny_is_vowel(next))) {
		dst[0] = prev;
		return 1;
	}
	/* W -> if previous is a vowel, previous */
	if (curr == 'W' && ny_is_vowel(prev)) {
		dst[0] = prev;
		return 1;
	}
	dst[0] = curr;
	return 1;
}

static zend_string *ny_encode(const char *src, size_t srclen, zend_long max_length)
{
	char *s;
	char *key;
	zend_string *ret;
	size_t n, klen, i;

	s = emalloc(srclen + 1);
	n = ny_clean(src, srclen, s);
	if (n == 0) {
		efree(s);
		return ZSTR_EMPTY_ALLOC();
	}

	/* First-character rules. Each is anchored at the start and the set is
	 * mutually exclusive after the first match fires, so the sequential
	 * replaceFirst chain of the reference reduces to this if/else. All are
	 * length-preserving, so they edit s in place. */
	if (n >= 3 && s[0] == 'M' && s[1] == 'A' && s[2] == 'C') {
		s[1] = 'C';                               /* MAC -> MCC */
	} else if (n >= 2 && s[0] == 'K' && s[1] == 'N') {
		s[0] = 'N';                               /* KN -> NN */
	} else if (s[0] == 'K') {
		s[0] = 'C';                               /* K -> C */
	} else if (n >= 2 && s[0] == 'P' && (s[1] == 'H' || s[1] == 'F')) {
		s[0] = 'F'; s[1] = 'F';                   /* PH|PF -> FF */
	} else if (n >= 3 && s[0] == 'S' && s[1] == 'C' && s[2] == 'H') {
		s[1] = 'S'; s[2] = 'S';                   /* SCH -> SSS */
	}

	/* Last-character rules (EE|IE -> Y, then DT|RT|RD|NT|ND -> D). */
	if (n >= 2 && ((s[n - 2] == 'E' && s[n - 1] == 'E')
			|| (s[n - 2] == 'I' && s[n - 1] == 'E'))) {
		s[n - 2] = 'Y';
		n--;
	}
	if (n >= 2) {
		char a = s[n - 2], b = s[n - 1];
		if ((a == 'D' && b == 'T') || (a == 'R' && b == 'T') || (a == 'R' && b == 'D')
				|| (a == 'N' && b == 'T') || (a == 'N' && b == 'D')) {
			s[n - 2] = 'D';
			n--;
		}
	}

	/* First character of key = first character of name. */
	key = emalloc(n + 1);
	klen = 0;
	key[klen++] = s[0];

	for (i = 1; i < n; i++) {
		char prev = s[i - 1];
		char curr = s[i];
		char next = (i < n - 1) ? s[i + 1] : ' ';
		char aNext = (i + 2 < n) ? s[i + 2] : ' ';
		char tc[3];
		int tlen = ny_transcode(prev, curr, next, aNext, tc);
		int k;
		/* The replacement overwrites s[i..] in place so later windows see it;
		 * multi-char writes only fire when the lookahead chars exist. */
		for (k = 0; k < tlen; k++) {
			s[i + (size_t) k] = tc[k];
		}
		if (s[i] != s[i - 1]) {
			key[klen++] = s[i];
		}
	}

	if (klen > 1) {
		char last = key[klen - 1];
		if (last == 'S') {                        /* trailing S removed */
			klen--;
			last = key[klen - 1];
		}
		if (klen > 2 && key[klen - 2] == 'A' && last == 'Y') {
			key[klen - 2] = key[klen - 1];        /* AY -> Y */
			klen--;
		}
		if (last == 'A') {                        /* trailing A removed */
			klen--;
		}
	}

	if (max_length > 0 && klen > (size_t) max_length) {
		klen = (size_t) max_length;
	}
	ret = klen > 0 ? zend_string_init(key, klen, 0) : ZSTR_EMPTY_ALLOC();

	efree(key);
	efree(s);
	return ret;
}

PHP_FUNCTION(nysiis)
{
	zend_string *input;
	zend_long max_length = 6;
	zend_string *out;

	ZEND_PARSE_PARAMETERS_START(1, 2)
		Z_PARAM_STR(input)
		Z_PARAM_OPTIONAL
		Z_PARAM_LONG(max_length)
	ZEND_PARSE_PARAMETERS_END();

	out = ny_encode(ZSTR_VAL(input), ZSTR_LEN(input), max_length);
	RETURN_STR(out);
}

PHP_FUNCTION(nysiis_match)
{
	zend_string *a, *b;
	zend_long max_length = 6;
	zend_string *ka, *kb;
	zend_bool matched = 0;

	ZEND_PARSE_PARAMETERS_START(2, 3)
		Z_PARAM_STR(a)
		Z_PARAM_STR(b)
		Z_PARAM_OPTIONAL
		Z_PARAM_LONG(max_length)
	ZEND_PARSE_PARAMETERS_END();

	ka = ny_encode(ZSTR_VAL(a), ZSTR_LEN(a), max_length);
	kb = ny_encode(ZSTR_VAL(b), ZSTR_LEN(b), max_length);

	/* NYSIIS yields a single key; two names match when the keys are equal.
	 * A name that produces no key never matches (consistent with the other
	 * *_match helpers, where an empty/unencodable input is never a homophone). */
	if (ZSTR_LEN(ka) > 0 && ZSTR_LEN(kb) > 0) {
		matched = zend_string_equals(ka, kb);
	}

	zend_string_release(ka);
	zend_string_release(kb);

	RETURN_BOOL(matched);
}
