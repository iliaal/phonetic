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

/* Match Rating Approach (Western Airlines, 1977), reimplemented from the
 * published algorithm; Commons Codec is the parity oracle. */

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

/* Commons Codec's PLAIN_ASCII/UNICODE correspondence; returns uppercase ASCII. */
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

/* Count code points so a multibyte letter remains trivial input. */
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

/* Feed one cleaned character through vowel removal and non-overlapping
 * consonant-pair removal, retaining only the first three and last three bytes. */
static void mra_append(char c, char out[6], size_t *n, char *pending)
{
	if (*n != 0 && mra_is_vowel(c)) {
		return;
	}
	if (*pending != 0 && *pending == c) {
		*pending = 0;
		return;
	}
	*pending = (c >= 'A' && c <= 'Z' && !mra_is_vowel(c)) ? c : 0;
	if (*n < 6) {
		out[(*n)++] = c;
	} else {
		out[3] = out[4];
		out[4] = out[5];
		out[5] = c;
	}
}

/* Full MRA pipeline in constant temporary space. The tail must still be read:
 * the codex includes its last three characters after cleaning and pair removal. */
static size_t mra_encode(const char *src, size_t len, char out[6])
{
	size_t i = 0, n = 0;
	char pending = 0;

	while (i < len) {
		int clen;
		uint32_t cp = ph_u8_next(src + i, len - i, &clen);
		i += (size_t) clen;

		if (cp < 0x80) {
			unsigned char c = (unsigned char) cp;
			if (c == '-' || c == '&' || c == '\'' || c == '.' || c == ',' || mra_is_space(c)) {
				continue;
			}
			mra_append((c >= 'a' && c <= 'z') ? (char) (c - ('a' - 'A')) : (char) c,
				out, &n, &pending);
		} else if (cp == 0x00DF) {
			/* Uppercasing expands both UTF-8 ß and its Latin-1 fallback to SS. */
			mra_append('S', out, &n, &pending);
			mra_append('S', out, &n, &pending);
		} else {
			char a = mra_deaccent(cp);
			if (a != 0) {
				mra_append(a, out, &n, &pending);
			}
			/* Drop unmapped non-ASCII; Commons Codec retains it. */
		}
	}
	return n;
}

static int mra_min_rating(int sum_length)
{
	if (sum_length <= 4) return 5;
	if (sum_length <= 7) return 4;
	if (sum_length <= 11) return 3;
	if (sum_length == 12) return 2;
	return 1;
}

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
	/* mra_encode bounds each input to six bytes. */
	char ca[8], cb[8];
	int n1 = (int) na - 1;
	int n2 = (int) nb - 1;
	int i, la = 0, lb = 0, longer;

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
	return 6 - longer;
}

PHP_FUNCTION(match_rating)
{
	zend_string *input;
	char res[6];
	size_t reslen = 0;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_STR(input)
	ZEND_PARSE_PARAMETERS_END();

	if (mra_trivial(ZSTR_VAL(input), ZSTR_LEN(input))) {
		RETURN_EMPTY_STRING();
	}

	reslen = mra_encode(ZSTR_VAL(input), ZSTR_LEN(input), res);
	RETVAL_STRINGL(res, reslen);
}

PHP_FUNCTION(match_rating_compare)
{
	zend_string *a, *b;
	char na[6], nb[6];
	size_t nal, nbl;
	int diff, sum, min_rating, count;

	ZEND_PARSE_PARAMETERS_START(2, 2)
		Z_PARAM_STR(a)
		Z_PARAM_STR(b)
	ZEND_PARSE_PARAMETERS_END();

	if (mra_trivial(ZSTR_VAL(a), ZSTR_LEN(a)) || mra_trivial(ZSTR_VAL(b), ZSTR_LEN(b))) {
		RETURN_FALSE;
	}
	/* Compare raw inputs before cleaning, including punctuation-only strings. */
	if (mra_iequals(ZSTR_VAL(a), ZSTR_LEN(a), ZSTR_VAL(b), ZSTR_LEN(b))) {
		RETURN_TRUE;
	}

	nal = mra_encode(ZSTR_VAL(a), ZSTR_LEN(a), na);
	if (nal == 0) {
		RETURN_FALSE;
	}
	nbl = mra_encode(ZSTR_VAL(b), ZSTR_LEN(b), nb);

	if (nbl == 0) {
		RETURN_FALSE;
	}

	diff = (int) nal - (int) nbl;
	if (diff < 0) {
		diff = -diff;
	}
	if (diff >= 3) {
		RETURN_FALSE;
	}

	sum = (int) nal + (int) nbl;
	min_rating = mra_min_rating(sum);
	count = mra_ltr_rtl(na, nal, nb, nbl);

	RETURN_BOOL(count >= min_rating);
}
