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

/* Shared UTF-8 and case-folding helpers for the phonetic engines. All
 * functions are static inline: each engine keeps its own copy in its
 * translation unit, so -fvisibility and per-TU inlining are unaffected. */

#ifndef PHONETIC_UTF8_H
#define PHONETIC_UTF8_H

#include <stdint.h>
#include <string.h>

#include "php.h"

/* Decode the first code point of s (len >= 1 bytes remaining). Stores the
 * sequence's byte length in *clen. Malformed sequences -- truncated, bare
 * continuation, overlong (C0/C1 and overlong 3/4-byte forms), UTF-16 surrogate
 * range, or > U+10FFFF -- decode as a single raw byte: the scan always advances,
 * and bytes 0x80-0xFF fall back to their Latin-1 meaning instead of swallowing
 * the following characters or admitting a non-scalar code point. */
static zend_always_inline uint32_t ph_u8_next(const char *s, size_t len, int *clen)
{
	const unsigned char *p = (const unsigned char *) s;
	unsigned char c = p[0];
	uint32_t cp;

	if (c < 0x80) {
		*clen = 1;
		return c;
	}
	if ((c >> 5) == 0x6 && len >= 2 && (p[1] & 0xc0) == 0x80) {
		cp = ((c & 0x1fu) << 6) | (p[1] & 0x3fu);
		if (cp >= 0x80) {                               /* not C0/C1 overlong */
			*clen = 2;
			return cp;
		}
	} else if ((c >> 4) == 0xe && len >= 3 && (p[1] & 0xc0) == 0x80 && (p[2] & 0xc0) == 0x80) {
		cp = ((c & 0x0fu) << 12) | ((p[1] & 0x3fu) << 6) | (p[2] & 0x3fu);
		if (cp >= 0x800 && (cp < 0xd800 || cp > 0xdfff)) {   /* not overlong, not surrogate */
			*clen = 3;
			return cp;
		}
	} else if ((c >> 3) == 0x1e && len >= 4 && (p[1] & 0xc0) == 0x80 && (p[2] & 0xc0) == 0x80 &&
	    (p[3] & 0xc0) == 0x80) {
		cp = ((c & 0x07u) << 18) | ((p[1] & 0x3fu) << 12) | ((p[2] & 0x3fu) << 6) | (p[3] & 0x3fu);
		if (cp >= 0x10000 && cp <= 0x10ffff) {          /* not overlong, in range */
			*clen = 4;
			return cp;
		}
	}

	*clen = 1;
	return c;
}

/* Decode a whole UTF-8 string into a freshly emalloc'd code-point array;
 * the caller owns it. *n receives the code-point count. */
static inline uint32_t *ph_u8_decode(const char *s, size_t len, int *n)
{
	uint32_t *out = safe_emalloc(len + 1, sizeof(uint32_t), 0);
	int k = 0;
	size_t i = 0;

	while (i < len) {
		int clen;
		out[k++] = ph_u8_next(s + i, len - i, &clen);
		i += (size_t) clen;
	}
	*n = k;
	return out;
}

/* Decode a NUL-terminated UTF-8 string into buf, up to cap code points.
 * Returns the number of code points written, or -1 when cap is too small. */
static inline int ph_u8_decode_buf(const char *s, uint32_t *buf, int cap)
{
	int k = 0;
	size_t i = 0, len = strlen(s);

	while (i < len && k < cap) {
		int clen;
		buf[k++] = ph_u8_next(s + i, len - i, &clen);
		i += (size_t) clen;
	}
	if (i < len) {
		return -1;
	}
	return k;
}

/* Encode one code point into buf (>= 4 bytes); returns the byte length. */
static inline int ph_u8_encode_cp(uint32_t cp, char *buf)
{
	if (cp < 0x80) {
		buf[0] = (char) cp;
		return 1;
	}
	if (cp < 0x800) {
		buf[0] = (char) (0xc0 | (cp >> 6));
		buf[1] = (char) (0x80 | (cp & 0x3f));
		return 2;
	}
	if (cp < 0x10000) {
		buf[0] = (char) (0xe0 | (cp >> 12));
		buf[1] = (char) (0x80 | ((cp >> 6) & 0x3f));
		buf[2] = (char) (0x80 | (cp & 0x3f));
		return 3;
	}
	buf[0] = (char) (0xf0 | (cp >> 18));
	buf[1] = (char) (0x80 | ((cp >> 12) & 0x3f));
	buf[2] = (char) (0x80 | ((cp >> 6) & 0x3f));
	buf[3] = (char) (0x80 | (cp & 0x3f));
	return 4;
}

/* Lower-case one code point over the Latin ranges the rule data and realistic
 * names use (ASCII, Latin-1 Supplement, Latin Extended-A/B pairs), mirroring
 * Java's lowercase mappings.
 *
 * U+0130 (I with dot above) is special-cased to plain 'i': the even/odd
 * Extended-A pairing would map it to U+0131 (dotless i), but I-with-dot and
 * dotless-i are not a case pair, and U+0131 matches nothing in any rule set,
 * so the letter would silently vanish. Character.toLowerCase(U+0130) is 'i';
 * String.toLowerCase would give "i" + U+0307, whose combining mark also
 * matches nothing — dropping it keeps "Istanbul" and "İstanbul" equivalent. */
static inline uint32_t ph_lc_latin(uint32_t c)
{
	if (c >= 'A' && c <= 'Z') return c + 32;
	if (c >= 0xC0 && c <= 0xD6) return c + 0x20;   /* A-grave .. O-diaeresis */
	if (c >= 0xD8 && c <= 0xDE) return c + 0x20;   /* O-slash .. Thorn */
	if (c == 0x0130) return 'i';                    /* I with dot above (Turkish) */
	if (c == 0x1E9E) return 0x00DF;                 /* capital sharp S -> small sharp s (Java lowercase) */
	if (c >= 0x0100 && c <= 0x0137) return (c & 1u) ? c : c + 1;
	if (c >= 0x0139 && c <= 0x0148) return (c & 1u) ? c + 1 : c;
	if (c >= 0x014A && c <= 0x0177) return (c & 1u) ? c : c + 1;
	if (c == 0x0178) return 0x00FF;                 /* Y-diaeresis */
	if (c >= 0x0179 && c <= 0x017E) return (c & 1u) ? c + 1 : c;
	if (c == 0x021A) return 0x021B;                 /* T-comma (Romanian) */
	return c;
}

#endif /* PHONETIC_UTF8_H */
