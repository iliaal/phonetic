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

/* Match Rating Approach (Western Airlines, 1977). Reimplementation of the
 * published algorithm; validated against Apache Commons Codec's
 * MatchRatingApproachEncoder as parity oracle. match_rating() returns the
 * codex; match_rating_compare() applies the MRA homophone comparison
 * (isEncodeEquals). Operates on ASCII; the Latin-1/Latin-Extended accent set
 * the reference folds is reproduced below as a plain character correspondence. */

#ifdef HAVE_CONFIG_H
#include "config.h"
#endif

#include <stdint.h>
#include <string.h>

#include "php.h"
#include "php_phonetic.h"
#include "phonetic_utf8.h"

static zend_always_inline int mra_is_vowel(char c)
{
	return c == 'A' || c == 'E' || c == 'I' || c == 'O' || c == 'U';
}

/* Accented code point -> ASCII letter (uppercase result applied by caller).
 * Mirrors Commons Codec's PLAIN_ASCII/UNICODE correspondence table. */
static char mra_deaccent(uint32_t cp)
{
	switch (cp) {
	/* grave */
	case 0x00C0: case 0x00E0: return 'A';
	case 0x00C8: case 0x00E8: return 'E';
	case 0x00CC: case 0x00EC: return 'I';
	case 0x00D2: case 0x00F2: return 'O';
	case 0x00D9: case 0x00F9: return 'U';
	/* acute */
	case 0x00C1: case 0x00E1: return 'A';
	case 0x00C9: case 0x00E9: return 'E';
	case 0x00CD: case 0x00ED: return 'I';
	case 0x00D3: case 0x00F3: return 'O';
	case 0x00DA: case 0x00FA: return 'U';
	case 0x00DD: case 0x00FD: return 'Y';
	/* circumflex */
	case 0x00C2: case 0x00E2: return 'A';
	case 0x00CA: case 0x00EA: return 'E';
	case 0x00CE: case 0x00EE: return 'I';
	case 0x00D4: case 0x00F4: return 'O';
	case 0x00DB: case 0x00FB: return 'U';
	case 0x0176: case 0x0177: return 'Y';
	/* tilde */
	case 0x00C3: case 0x00E3: return 'A';
	case 0x00D5: case 0x00F5: return 'O';
	case 0x00D1: case 0x00F1: return 'N';
	/* umlaut */
	case 0x00C4: case 0x00E4: return 'A';
	case 0x00CB: case 0x00EB: return 'E';
	case 0x00CF: case 0x00EF: return 'I';
	case 0x00D6: case 0x00F6: return 'O';
	case 0x00DC: case 0x00FC: return 'U';
	case 0x0178: case 0x00FF: return 'Y';
	/* ring */
	case 0x00C5: case 0x00E5: return 'A';
	/* cedilla */
	case 0x00C7: case 0x00E7: return 'C';
	/* double acute */
	case 0x0150: case 0x0151: return 'O';
	case 0x0170: case 0x0171: return 'U';
	default:
		return 0;
	}
}

static int mra_is_space(unsigned char c)
{
	return c == ' ' || c == '\t' || c == '\n' || c == '\r' || c == '\f' || c == '\v';
}

/* True when the string holds at most one code point (empty or a single
 * character). The reference's trivial-input guard is `name.length() == 1`,
 * i.e. character count, not byte count — so a single multi-byte letter must
 * still count as one character, not encode as if it were several. */
static int mra_trivial(const char *s, size_t len)
{
	size_t i = 0, cps = 0;

	while (i < len) {
		int clen;
		(void) ph_u8_next(s + i, len - i, &clen);
		i += (size_t) clen;
		if (++cps > 1) {
			return 0;
		}
	}
	return 1;
}

/* Upper-case, strip the reference's punctuation set (- & ' . ,) and whitespace,
 * fold accents. Returns an emalloc'd buffer; *outlen gets its length. */
static char *mra_clean(const char *src, size_t len, size_t *outlen)
{
	char *out = safe_emalloc(len, 2, 1);
	size_t i = 0, n = 0;

	while (i < len) {
		int clen;
		uint32_t cp = ph_u8_next(src + i, len - i, &clen);
		i += (size_t) clen;

		if (cp < 0x80) {
			unsigned char c = (unsigned char) cp;
			if (c == '-' || c == '&' || c == '\'' || c == '.' || c == ',' || mra_is_space(c)) {
				continue;
			}
			out[n++] = (c >= 'a' && c <= 'z') ? (char) (c - ('a' - 'A')) : (char) c;
		} else if (cp == 0x00DF) {
			/* The reference upper-cases (ENGLISH) before folding accents, and
			 * ß upper-cases to "SS"; reproduce that one expansion here. Other
			 * case-expanding code points are out of scope (ASCII/Latin only). */
			out[n++] = 'S';
			out[n++] = 'S';
		} else {
			char a = mra_deaccent(cp);
			if (a != 0) {
				out[n++] = a;        /* table already yields uppercase */
			}
			/* unmapped non-ASCII is dropped */
		}
	}

	out[n] = '\0';
	*outlen = n;
	return out;
}

/* Delete all vowels, then restore a leading vowel if the word began with one. */
static size_t mra_remove_vowels(char *buf, size_t len)
{
	char first;
	size_t i, n = 0;

	if (len == 0) {
		return 0;
	}
	first = buf[0];
	for (i = 0; i < len; i++) {
		if (!mra_is_vowel(buf[i])) {
			buf[n++] = buf[i];
		}
	}
	if (mra_is_vowel(first)) {
		memmove(buf + 1, buf, n);
		buf[0] = first;
		n++;
	}
	return n;
}

/* Collapse each doubled consonant to a single letter. The reference's
 * DOUBLE_CONSONANT list is exactly the 21 non-vowel letters, so a single
 * left-to-right pass that drops the second of any equal consonant pair matches
 * its per-letter String.replace sweep. */
static size_t mra_remove_doubles(char *buf, size_t len)
{
	size_t i = 0, n = 0;

	while (i < len) {
		char c = buf[i];
		buf[n++] = c;
		if (i + 1 < len && buf[i + 1] == c
				&& c >= 'A' && c <= 'Z' && !mra_is_vowel(c)) {
			i += 2;
		} else {
			i += 1;
		}
	}
	return n;
}

/* If longer than 6, keep first 3 + last 3. */
static size_t mra_first3last3(char *buf, size_t len)
{
	if (len > 6) {
		buf[3] = buf[len - 3];
		buf[4] = buf[len - 2];
		buf[5] = buf[len - 1];
		return 6;
	}
	return len;
}

static int mra_min_rating(int sum_length)
{
	if (sum_length <= 4) return 5;
	if (sum_length <= 7) return 4;
	if (sum_length <= 11) return 3;
	if (sum_length == 12) return 2;
	return 1;
}

/* Full MRA pipeline. Returns an emalloc'd buffer (possibly empty). */
static char *mra_encode(const char *src, size_t len, size_t *outlen)
{
	char *buf;
	size_t n;

	buf = mra_clean(src, len, &n);
	if (n == 0) { *outlen = 0; return buf; }
	n = mra_remove_vowels(buf, n);
	if (n == 0) { *outlen = 0; return buf; }
	n = mra_remove_doubles(buf, n);
	n = mra_first3last3(buf, n);
	*outlen = n;
	return buf;
}

/* ASCII case-insensitive equality of two raw strings. */
static int mra_iequals(const char *a, size_t al, const char *b, size_t bl)
{
	size_t i;
	if (al != bl) {
		return 0;
	}
	for (i = 0; i < al; i++) {
		unsigned char x = (unsigned char) a[i];
		unsigned char y = (unsigned char) b[i];
		if (x >= 'a' && x <= 'z') x -= ('a' - 'A');
		if (y >= 'a' && y <= 'z') y -= ('a' - 'A');
		if (x != y) {
			return 0;
		}
	}
	return 1;
}

/* Process left-to-right then right-to-left, blanking identical characters at
 * mirrored positions; return 6 minus the longer remaining length. */
static int mra_ltr_rtl(const char *a, size_t na, const char *b, size_t nb)
{
	/* Both inputs have already passed getFirst3Last3, so na, nb <= 6; a stack
	 * scratch buffer avoids two heap alloc/free pairs per comparison. */
	char ca[8], cb[8];
	int n1 = (int) na - 1;
	int n2 = (int) nb - 1;
	int i, la = 0, lb = 0, longer, r;

	if (na > 6 || nb > 6) {
		return 0;
	}
	ZEND_ASSERT(na <= sizeof(ca) && nb <= sizeof(cb));
	memcpy(ca, a, na);
	memcpy(cb, b, nb);

	for (i = 0; i < (int) na; i++) {
		if (i > n2) {
			break;
		}
		if (a[i] == b[i]) {
			ca[i] = ' ';
			cb[i] = ' ';
		}
		if (a[n1 - i] == b[n2 - i]) {
			ca[n1 - i] = ' ';
			cb[n2 - i] = ' ';
		}
	}

	for (i = 0; i < (int) na; i++) if (ca[i] != ' ') la++;
	for (i = 0; i < (int) nb; i++) if (cb[i] != ' ') lb++;
	longer = la > lb ? la : lb;

	r = 6 - longer;
	return r < 0 ? -r : r;
}

PHP_FUNCTION(match_rating)
{
	zend_string *input;
	char *res;
	size_t reslen = 0;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_STR(input)
	ZEND_PARSE_PARAMETERS_END();

	/* Trivial input (empty / single character) has no code. */
	if (mra_trivial(ZSTR_VAL(input), ZSTR_LEN(input))) {
		RETURN_EMPTY_STRING();
	}

	res = mra_encode(ZSTR_VAL(input), ZSTR_LEN(input), &reslen);
	RETVAL_STRINGL(res, reslen);
	efree(res);
}

PHP_FUNCTION(match_rating_compare)
{
	zend_string *a, *b;
	char *na, *nb;
	size_t nal, nbl;
	int diff, sum, min_rating, count;

	ZEND_PARSE_PARAMETERS_START(2, 2)
		Z_PARAM_STR(a)
		Z_PARAM_STR(b)
	ZEND_PARSE_PARAMETERS_END();

	/* Trivial input never compares. */
	if (mra_trivial(ZSTR_VAL(a), ZSTR_LEN(a)) || mra_trivial(ZSTR_VAL(b), ZSTR_LEN(b))) {
		RETURN_FALSE;
	}
	/* Identical (case-insensitive) raw inputs are homophones by definition. */
	if (mra_iequals(ZSTR_VAL(a), ZSTR_LEN(a), ZSTR_VAL(b), ZSTR_LEN(b))) {
		RETURN_TRUE;
	}

	na = mra_clean(ZSTR_VAL(a), ZSTR_LEN(a), &nal);
	nb = mra_clean(ZSTR_VAL(b), ZSTR_LEN(b), &nbl);
	nal = mra_remove_vowels(na, nal);
	nbl = mra_remove_vowels(nb, nbl);

	/* The reference dereferences the first character unconditionally here; an
	 * input that cleans away to nothing has no comparable code. */
	if (nal == 0 || nbl == 0) {
		efree(na);
		efree(nb);
		RETURN_FALSE;
	}

	nal = mra_remove_doubles(na, nal);
	nbl = mra_remove_doubles(nb, nbl);
	nal = mra_first3last3(na, nal);
	nbl = mra_first3last3(nb, nbl);

	diff = (int) nal - (int) nbl;
	if (diff < 0) {
		diff = -diff;
	}
	if (diff >= 3) {
		efree(na);
		efree(nb);
		RETURN_FALSE;
	}

	sum = (int) nal + (int) nbl;
	min_rating = mra_min_rating(sum);
	count = mra_ltr_rtl(na, nal, nb, nbl);

	efree(na);
	efree(nb);
	RETURN_BOOL(count >= min_rating);
}
