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

/* Double Metaphone (Lawrence Philips, C/C++ Users Journal, June 2000).
 * Clean-room implementation of the published algorithm; no third-party data.
 * A single forward cursor walks the folded, upper-cased input and appends to a
 * primary and an alternate code buffer that diverge at the documented branch
 * points (silent leading GN/KN/PN/WR/PS, CH, SCH, Slavic/Germanic J and G,
 * Greek/Italian clusters, ...). */

#ifdef HAVE_CONFIG_H
#include "config.h"
#endif

#include <limits.h>
#include <string.h>

#include "php.h"
#include "zend_smart_str.h"
#include "php_phonetic.h"
#include "phonetic_utf8.h"

/* Padding so lookbehind/lookahead can run past both ends without bounds tests;
 * the sentinel '-' never matches a real cluster because no code or target
 * literal contains it. PREPAD covers the deepest lookbehind (position-4),
 * POSTPAD the deepest lookahead (a 6-char window read from position+1). */
#define DMET_PREPAD  2
#define DMET_POSTPAD 8
#define DMET_SENTINEL '-'
#define DMET_MAX_FOLDED_INPUT (INT_MAX - (DMET_PREPAD + DMET_POSTPAD + 1))

static zend_always_inline int dmet_is_vowel(char c)
{
	return c == 'A' || c == 'E' || c == 'I' || c == 'O' || c == 'U' || c == 'Y';
}

/* Single character at an absolute buffer index; sentinel when out of range. */
static zend_always_inline char dmet_at(const char *buf, int total, int i)
{
	if (i < 0 || i >= total) {
		return DMET_SENTINEL;
	}
	return buf[i];
}

/* True when the literal `s` (length `n`) matches the buffer window starting at
 * index `i`. A window that runs past either end of the padded buffer cannot
 * equal a real literal; the published algorithm's word-end space comparisons
 * are handled explicitly at their call sites instead of via padding. The
 * length is passed by the SAT macro as sizeof(literal)-1 so no strlen runs at
 * match time. */
static zend_always_inline int dmet_string_at(const char *buf, int total, int i, const char *s, size_t n)
{
	if (i < 0 || (size_t)i + n > (size_t)total) {
		return 0;
	}
	return memcmp(buf + i, s, n) == 0;
}

/* Map an accented Latin code point to its base ASCII spelling (upper case).
 * C-cedilla maps to S rather than C: in the canonical algorithm the cedilla
 * carries the /s/ sound (façade), not a hard /k/. Combining marks and code
 * points outside Latin-1 fall through to NULL and are dropped. */
static const char *dmet_fold_cp(unsigned cp)
{
	switch (cp) {
		case 0xC0: case 0xC1: case 0xC2: case 0xC3: case 0xC4: case 0xC5:
			return "A";
		case 0xC6: return "AE";
		case 0xC7: return "S";
		case 0xC8: case 0xC9: case 0xCA: case 0xCB: return "E";
		case 0xCC: case 0xCD: case 0xCE: case 0xCF: return "I";
		case 0xD0: return "D";
		case 0xD1: return "N";
		case 0xD2: case 0xD3: case 0xD4: case 0xD5: case 0xD6: case 0xD8:
			return "O";
		case 0xD9: case 0xDA: case 0xDB: case 0xDC: return "U";
		case 0xDD: return "Y";
		case 0xDE: return "TH";
		case 0xDF: return "SS";
		case 0xE0: case 0xE1: case 0xE2: case 0xE3: case 0xE4: case 0xE5:
			return "A";
		case 0xE6: return "AE";
		case 0xE7: return "S";
		case 0xE8: case 0xE9: case 0xEA: case 0xEB: return "E";
		case 0xEC: case 0xED: case 0xEE: case 0xEF: return "I";
		case 0xF0: return "D";
		case 0xF1: return "N";
		case 0xF2: case 0xF3: case 0xF4: case 0xF5: case 0xF6: case 0xF8:
			return "O";
		case 0xF9: case 0xFA: case 0xFB: case 0xFC: return "U";
		case 0xFD: case 0xFF: return "Y";
		case 0xFE: return "TH";
		default: return NULL;
	}
}

/* Decode UTF-8, ASCII-fold accented Latin, upper-case; spaces are preserved
 * so multi-word input keeps its word boundaries. Malformed sequences decode
 * as single raw bytes (ph_u8_next), so a stray lead byte folds as its Latin-1
 * character instead of swallowing the letters after it. */
static void dmet_fold(const char *src, size_t len, smart_str *out)
{
	size_t i = 0;

	while (i < len) {
		int clen;
		uint32_t cp = ph_u8_next(src + i, len - i, &clen);

		i += (size_t) clen;
		if (cp < 0x80) {
			/* ASCII-only uppercasing: libc toupper() honours LC_CTYPE, and a
			 * userland setlocale() (e.g. tr_TR) would mis-fold the buffer the
			 * encoder walks. The algorithm is defined over ASCII A-Z. */
			char u = (cp >= 'a' && cp <= 'z') ? (char) (cp - 'a' + 'A') : (char) cp;
			smart_str_appendc(out, u);
		} else {
			const char *mapped = dmet_fold_cp((unsigned) cp);

			if (mapped) {
				smart_str_appends(out, mapped);
			}
		}
	}
}

/* Core encoder. `folded` is upper-cased ASCII; emits the full (untruncated)
 * primary and alternate codes into the supplied buffers. */
static void dmet_encode(const char *folded, size_t len, smart_str *primary, smart_str *secondary)
{
	int total, start, end, pos, slavo;
	char *buf;
	const char *pc, *sc;
	int adv;

	if (len == 0) {
		return;
	}

	/* The encoder walks the padded buffer with signed int cursors; a folded
	 * length that would overflow them must be refused before the narrowing cast
	 * below undersizes the allocation. Unreachable under any sane memory_limit,
	 * but folded length can exceed input length for Latin expansions like ß. */
	if (len > (size_t) DMET_MAX_FOLDED_INPUT) {
		return;
	}

	total = DMET_PREPAD + (int) len + DMET_POSTPAD;
	buf = emalloc((size_t) total + 1);
	memset(buf, DMET_SENTINEL, (size_t) total);
	memcpy(buf + DMET_PREPAD, folded, len);
	buf[total] = '\0';

	start = DMET_PREPAD;
	end = DMET_PREPAD + (int) len - 1;
	/* The reference cleanInput() runs Java String.trim(), which strips every
	 * leading/trailing byte <= 0x20 (space, tab, newline, CR, ...), not only
	 * ASCII space. The folded buffer is pure ASCII so the unsigned compare
	 * never matches a folded high byte, and the pad sentinel ('-') is > 0x20. */
	while (start <= end && (unsigned char) buf[start] <= ' ') {
		start++;
	}
	while (end >= start && (unsigned char) buf[end] <= ' ') {
		end--;
	}
	if (start > end) {
		efree(buf);
		return;
	}

	slavo = (memchr(folded, 'W', len) != NULL) || (memchr(folded, 'K', len) != NULL);
	if (!slavo) {
		size_t i;
		for (i = 1; i < len; i++) {
			if (folded[i - 1] == 'C' && folded[i] == 'Z') {
				slavo = 1;
				break;
			}
		}
	}

#define B(i)       dmet_at(buf, total, (i))
#define SAT(i, s)  dmet_string_at(buf, total, (i), "" s, sizeof(s) - 1)

	/* check_word_start: skip a silent leading cluster, and map an initial X to S. */
	pos = start;
	if (SAT(pos, "GN") || SAT(pos, "KN") || SAT(pos, "PN")
			|| SAT(pos, "WR") || SAT(pos, "PS")) {
		pos++;
	}
	if (B(start) == 'X') {
		smart_str_appendc(primary, 'S');
		smart_str_appendc(secondary, 'S');
		pos++;
	}

	/* Every branch assigns the (pc, sc, adv) action for the current position.
	 * Non-letter bytes are handled by the switch default, which skips them
	 * (emit nothing, advance one). */
	pc = NULL;
	sc = NULL;
	adv = 1;

	while (pos <= end) {
		char c = B(pos);

		if (c == ' ') {
			pos++;
			continue;
		}

		if (dmet_is_vowel(c)) {
			pc = (pos == start) ? "A" : NULL;
			sc = pc;
			adv = 1;
		} else {
			switch (c) {
			case 'B':
				pc = sc = "P";
				adv = (B(pos + 1) == 'B') ? 2 : 1;
				break;

			case 'C':
				if (pos > start + 1 && !dmet_is_vowel(B(pos - 2)) && SAT(pos - 1, "ACH")
						&& B(pos + 2) != 'I'
						&& (B(pos + 2) != 'E' || SAT(pos - 2, "BACHER") || SAT(pos - 2, "MACHER"))) {
					pc = sc = "K";
					adv = 2;
				} else if (pos == start && SAT(start, "CAESAR")) {
					pc = sc = "S";
					adv = 2;
				} else if (SAT(pos, "CHIA")) {
					pc = sc = "K";
					adv = 2;
				} else if (SAT(pos, "CH")) {
					if (pos > start && SAT(pos, "CHAE")) {
						pc = "K";
						sc = "X";
						adv = 2;
					} else if (pos == start
							&& (SAT(pos + 1, "HARAC") || SAT(pos + 1, "HARIS")
								|| SAT(pos + 1, "HOR") || SAT(pos + 1, "HYM")
								|| SAT(pos + 1, "HIA") || SAT(pos + 1, "HEM"))
							&& !SAT(start, "CHORE")) {
						pc = sc = "K";
						adv = 2;
					} else if (SAT(start, "VAN ") || SAT(start, "VON ") || SAT(start, "SCH")
							|| SAT(pos - 2, "ORCHES") || SAT(pos - 2, "ARCHIT") || SAT(pos - 2, "ORCHID")
							|| B(pos + 2) == 'T' || B(pos + 2) == 'S'
							|| ((B(pos - 1) == 'A' || B(pos - 1) == 'O' || B(pos - 1) == 'U' || B(pos - 1) == 'E'
									|| pos == start)
								&& (B(pos + 2) == 'L' || B(pos + 2) == 'R' || B(pos + 2) == 'N'
									|| B(pos + 2) == 'M' || B(pos + 2) == 'B' || B(pos + 2) == 'H'
									|| B(pos + 2) == 'F' || B(pos + 2) == 'V' || B(pos + 2) == 'W'
									/* germanic CH at word end ('mooch') or before a space */
									|| B(pos + 2) == ' ' || pos + 2 > end))) {
						pc = sc = "K";
						adv = 2;
					} else {
						if (pos > start) {
							if (SAT(start, "MC")) {
								pc = sc = "K";
							} else {
								pc = "X";
								sc = "K";
							}
						} else {
							pc = sc = "X";
						}
						adv = 2;
					}
				} else if (SAT(pos, "CZ") && !SAT(pos - 2, "WICZ")) {
					pc = "S";
					sc = "X";
					adv = 2;
				} else if (SAT(pos + 1, "CIA")) {
					pc = sc = "X";
					adv = 3;
				} else if (SAT(pos, "CC") && !(pos == start + 1 && B(start) == 'M')) {
					if ((B(pos + 2) == 'I' || B(pos + 2) == 'E' || B(pos + 2) == 'H')
							&& !SAT(pos + 2, "HU")) {
						if ((pos == start + 1 && B(start) == 'A')
								|| SAT(pos - 1, "UCCEE") || SAT(pos - 1, "UCCES")) {
							pc = sc = "KS";
						} else {
							pc = sc = "X";
						}
						adv = 3;
					} else {
						pc = sc = "K";
						adv = 2;
					}
				} else if (SAT(pos, "CK") || SAT(pos, "CG") || SAT(pos, "CQ")) {
					pc = sc = "K";
					adv = 2;
				} else if (SAT(pos, "CI") || SAT(pos, "CE") || SAT(pos, "CY")) {
					if (SAT(pos, "CIO") || SAT(pos, "CIE") || SAT(pos, "CIA")) {
						pc = "S";
						sc = "X";
					} else {
						pc = sc = "S";
					}
					adv = 2;
				} else {
					if (SAT(pos + 1, " C") || SAT(pos + 1, " Q") || SAT(pos + 1, " G")) {
						pc = sc = "K";
						adv = 3;
					} else if ((B(pos + 1) == 'C' || B(pos + 1) == 'K' || B(pos + 1) == 'Q')
							&& !(SAT(pos + 1, "CE") || SAT(pos + 1, "CI"))) {
						pc = sc = "K";
						adv = 2;
					} else {
						pc = sc = "K";
						adv = 1;
					}
				}
				break;

			case 'D':
				if (SAT(pos, "DG")) {
					if (B(pos + 2) == 'I' || B(pos + 2) == 'E' || B(pos + 2) == 'Y') {
						pc = sc = "J";
						adv = 3;
					} else {
						pc = sc = "TK";
						adv = 2;
					}
				} else if (SAT(pos, "DT") || SAT(pos, "DD")) {
					pc = sc = "T";
					adv = 2;
				} else {
					pc = sc = "T";
					adv = 1;
				}
				break;

			case 'F':
				pc = sc = "F";
				adv = (B(pos + 1) == 'F') ? 2 : 1;
				break;

			case 'G':
				if (B(pos + 1) == 'H') {
					if (pos > start && !dmet_is_vowel(B(pos - 1))) {
						pc = sc = "K";
						adv = 2;
					} else if (pos == start) {
						if (B(pos + 2) == 'I') {
							pc = sc = "J";
						} else {
							pc = sc = "K";
						}
						adv = 2;
					} else if ((pos > start + 1 && (B(pos - 2) == 'B' || B(pos - 2) == 'H' || B(pos - 2) == 'D'))
							|| (pos > start + 2 && (B(pos - 3) == 'B' || B(pos - 3) == 'H' || B(pos - 3) == 'D'))
							|| (pos > start + 3 && (B(pos - 4) == 'B' || B(pos - 4) == 'H'))) {
						/* Parker's rule: silent GH after B/H/D within four letters
						 * ('hugh', 'bought') */
						pc = sc = NULL;
						adv = 2;
					} else {
						if (pos > start + 2 && B(pos - 1) == 'U'
								&& (B(pos - 3) == 'C' || B(pos - 3) == 'G' || B(pos - 3) == 'L'
									|| B(pos - 3) == 'R' || B(pos - 3) == 'T')) {
							pc = sc = "F";
						} else if (pos > start && B(pos - 1) != 'I') {
							pc = sc = "K";
						} else {
							pc = sc = NULL;   /* GH after initial I ('sigh') */
						}
						adv = 2;
					}
				} else if (B(pos + 1) == 'N') {
					if (pos == start + 1 && dmet_is_vowel(B(start)) && !slavo) {
						pc = "KN";
						sc = "N";
						adv = 2;
					} else if (!SAT(pos + 2, "EY") && B(pos + 1) != 'Y' && !slavo) {
						pc = "N";
						sc = "KN";
						adv = 2;
					} else {
						pc = sc = "KN";
						adv = 2;
					}
				} else if (SAT(pos + 1, "LI") && !slavo) {
					pc = "KL";
					sc = "L";
					adv = 2;
				} else if (pos == start
						&& (B(pos + 1) == 'Y'
							|| SAT(pos + 1, "ES") || SAT(pos + 1, "EP") || SAT(pos + 1, "EB")
							|| SAT(pos + 1, "EL") || SAT(pos + 1, "EY") || SAT(pos + 1, "IB")
							|| SAT(pos + 1, "IL") || SAT(pos + 1, "IN") || SAT(pos + 1, "IE")
							|| SAT(pos + 1, "EI") || SAT(pos + 1, "ER"))) {
					pc = "K";
					sc = "J";
					adv = 2;
				} else if ((SAT(pos + 1, "ER") || B(pos + 1) == 'Y')
						&& !(SAT(start, "DANGER") || SAT(start, "RANGER") || SAT(start, "MANGER"))
						&& B(pos - 1) != 'E' && B(pos - 1) != 'I'
						&& !(SAT(pos - 1, "RGY") || SAT(pos - 1, "OGY"))) {
					pc = "K";
					sc = "J";
					adv = 2;
				} else if (B(pos + 1) == 'E' || B(pos + 1) == 'I' || B(pos + 1) == 'Y'
						|| SAT(pos - 1, "AGGI") || SAT(pos - 1, "OGGI")) {
					if (SAT(start, "VON ") || SAT(start, "VAN ") || SAT(start, "SCH")
							|| SAT(pos + 1, "ET")) {
						pc = sc = "K";
						adv = 2;
					} else if (SAT(pos + 1, "IER")) {
						pc = sc = "J";
						adv = 2;
					} else {
						pc = "J";
						sc = "K";
						adv = 2;
					}
				} else if (B(pos + 1) == 'G') {
					pc = sc = "K";
					adv = 2;
				} else {
					pc = sc = "K";
					adv = 1;
				}
				break;

			case 'H':
				if ((pos == start || dmet_is_vowel(B(pos - 1))) && dmet_is_vowel(B(pos + 1))) {
					pc = sc = "H";
					adv = 2;
				} else {
					pc = sc = NULL;
					adv = 1;
				}
				break;

			case 'J':
				if (SAT(pos, "JOSE") || SAT(start, "SAN ")) {
					/* pos + 4 > end: the word is exactly "JOSE" (bare, no tail) */
					if ((pos == start && (B(pos + 4) == ' ' || pos + 4 > end))
							|| SAT(start, "SAN ")) {
						pc = sc = "H";
					} else {
						pc = "J";
						sc = "H";
					}
				} else if (pos == start && !SAT(pos, "JOSE")) {
					pc = "J";
					sc = "A";
				} else if (dmet_is_vowel(B(pos - 1)) && !slavo
						&& (B(pos + 1) == 'A' || B(pos + 1) == 'O')) {
					pc = "J";
					sc = "H";
				} else if (pos == end) {
					pc = "J";
					sc = " ";
				} else if (!(B(pos + 1) == 'L' || B(pos + 1) == 'T' || B(pos + 1) == 'K'
							|| B(pos + 1) == 'S' || B(pos + 1) == 'N' || B(pos + 1) == 'M'
							|| B(pos + 1) == 'B' || B(pos + 1) == 'Z')
						&& !(B(pos - 1) == 'S' || B(pos - 1) == 'K' || B(pos - 1) == 'L')) {
					pc = sc = "J";
				} else {
					pc = sc = NULL;
				}
				adv = (B(pos + 1) == 'J') ? 2 : 1;
				break;

			case 'K':
				pc = sc = "K";
				adv = (B(pos + 1) == 'K') ? 2 : 1;
				break;

			case 'L':
				if (B(pos + 1) == 'L') {
					if ((pos == end - 2
							&& (SAT(pos - 1, "ILLO") || SAT(pos - 1, "ILLA") || SAT(pos - 1, "ALLE")))
							|| ((SAT(end - 1, "AS") || SAT(end - 1, "OS")
									|| B(end) == 'A' || B(end) == 'O')
								&& SAT(pos - 1, "ALLE"))) {
						pc = "L";
						sc = NULL;
					} else {
						pc = sc = "L";
					}
					adv = 2;
				} else {
					pc = sc = "L";
					adv = 1;
				}
				break;

			case 'M':
				/* silent B in -UMB / -UMBER: U before, B after ('dumb', 'thumb') */
				if ((SAT(pos - 1, "UMB") && (pos + 1 == end || SAT(pos + 2, "ER")))
						|| B(pos + 1) == 'M') {
					pc = sc = "M";
					adv = 2;
				} else {
					pc = sc = "M";
					adv = 1;
				}
				break;

			case 'N':
				pc = sc = "N";
				adv = (B(pos + 1) == 'N') ? 2 : 1;
				break;

			case 'P':
				if (B(pos + 1) == 'H') {
					pc = sc = "F";
					adv = 2;
				} else if (B(pos + 1) == 'P' || B(pos + 1) == 'B') {
					pc = sc = "P";
					adv = 2;
				} else {
					pc = sc = "P";
					adv = 1;
				}
				break;

			case 'Q':
				pc = sc = "K";
				adv = (B(pos + 1) == 'Q') ? 2 : 1;
				break;

			case 'R':
				if (pos == end && !slavo && SAT(pos - 2, "IE")
						&& !(SAT(pos - 4, "ME") || SAT(pos - 4, "MA"))) {
					pc = NULL;
					sc = "R";
				} else {
					pc = sc = "R";
				}
				adv = (B(pos + 1) == 'R') ? 2 : 1;
				break;

			case 'S':
				if (SAT(pos - 1, "ISL") || SAT(pos - 1, "YSL")) {
					pc = sc = NULL;
					adv = 1;
				} else if (pos == start && SAT(start, "SUGAR")) {
					pc = "X";
					sc = "S";
					adv = 1;
				} else if (SAT(pos, "SH")) {
					if (SAT(pos + 1, "HEIM") || SAT(pos + 1, "HOEK")
							|| SAT(pos + 1, "HOLM") || SAT(pos + 1, "HOLZ")) {
						pc = sc = "S";
					} else {
						pc = sc = "X";
					}
					adv = 2;
				} else if (SAT(pos, "SIO") || SAT(pos, "SIA") || SAT(pos, "SIAN")) {
					if (!slavo) {
						pc = "S";
						sc = "X";
					} else {
						pc = sc = "S";
					}
					adv = 3;
				} else if ((pos == start
							&& (B(pos + 1) == 'M' || B(pos + 1) == 'N'
								|| B(pos + 1) == 'L' || B(pos + 1) == 'W'))
						|| B(pos + 1) == 'Z') {
					pc = "S";
					sc = "X";
					adv = (B(pos + 1) == 'Z') ? 2 : 1;
				} else if (SAT(pos, "SC")) {
					if (B(pos + 2) == 'H') {
						if (SAT(pos + 3, "OO") || SAT(pos + 3, "ER") || SAT(pos + 3, "EN")
								|| SAT(pos + 3, "UY") || SAT(pos + 3, "ED") || SAT(pos + 3, "EM")) {
							if (SAT(pos + 3, "ER") || SAT(pos + 3, "EN")) {
								pc = "X";
								sc = "SK";
							} else {
								pc = sc = "SK";
							}
						} else if (pos == start && !dmet_is_vowel(B(start + 3)) && B(start + 3) != 'W') {
							pc = "X";
							sc = "S";
						} else {
							pc = sc = "X";
						}
					} else if (B(pos + 2) == 'I' || B(pos + 2) == 'E' || B(pos + 2) == 'Y') {
						pc = sc = "S";
					} else {
						pc = sc = "SK";
					}
					adv = 3;
				} else if (pos == end && (SAT(pos - 2, "AI") || SAT(pos - 2, "OI"))) {
					pc = NULL;
					sc = "S";
					adv = 1;
				} else {
					pc = sc = "S";
					adv = (B(pos + 1) == 'S' || B(pos + 1) == 'Z') ? 2 : 1;
				}
				break;

			case 'T':
				if (SAT(pos, "TION")) {
					pc = sc = "X";
					adv = 3;
				} else if (SAT(pos, "TIA") || SAT(pos, "TCH")) {
					pc = sc = "X";
					adv = 3;
				} else if (SAT(pos, "TH") || SAT(pos, "TTH")) {
					if (SAT(pos + 2, "OM") || SAT(pos + 2, "AM")
							|| SAT(start, "VON ") || SAT(start, "VAN ") || SAT(start, "SCH")) {
						pc = sc = "T";
					} else {
						pc = "0";
						sc = "T";
					}
					adv = 2;
				} else if (B(pos + 1) == 'T' || B(pos + 1) == 'D') {
					pc = sc = "T";
					adv = 2;
				} else {
					pc = sc = "T";
					adv = 1;
				}
				break;

			case 'V':
				pc = sc = "F";
				adv = (B(pos + 1) == 'V') ? 2 : 1;
				break;

			case 'W':
				if (SAT(pos, "WR")) {
					pc = sc = "R";
					adv = 2;
				} else if (pos == start && (dmet_is_vowel(B(pos + 1)) || SAT(pos, "WH"))) {
					if (dmet_is_vowel(B(pos + 1))) {
						pc = "A";
						sc = "F";
					} else {
						pc = sc = "A";
					}
					adv = 1;
				} else if ((pos == end && dmet_is_vowel(B(pos - 1)))
						|| SAT(pos - 1, "EWSKI") || SAT(pos - 1, "EWSKY")
						|| SAT(pos - 1, "OWSKI") || SAT(pos - 1, "OWSKY")
						|| SAT(start, "SCH")) {
					pc = NULL;
					sc = "F";
					adv = 1;
				} else if (SAT(pos, "WICZ") || SAT(pos, "WITZ")) {
					pc = "TS";
					sc = "FX";
					adv = 4;
				} else {
					pc = sc = NULL;
					adv = 1;
				}
				break;

			case 'X':
				if (!(pos == end
						&& (SAT(pos - 3, "IAU") || SAT(pos - 3, "EAU")
							|| SAT(pos - 2, "AU") || SAT(pos - 2, "OU")))) {
					pc = sc = "KS";
				} else {
					pc = sc = NULL;
				}
				adv = (B(pos + 1) == 'C' || B(pos + 1) == 'X') ? 2 : 1;
				break;

			case 'Z':
				if (B(pos + 1) == 'H') {
					pc = sc = "J";
				} else if (SAT(pos + 1, "ZO") || SAT(pos + 1, "ZI") || SAT(pos + 1, "ZA")
						|| (slavo && pos > start && B(pos - 1) != 'T')) {
					pc = "S";
					sc = "TS";
				} else {
					pc = sc = "S";
				}
				adv = (B(pos + 1) == 'Z' || B(pos + 1) == 'H') ? 2 : 1;
				break;

			default:
				/* Non-letter byte that survived folding (digit, punctuation,
				 * hyphen): emit nothing and advance one, like a space. Commons
				 * Codec's DoubleMetaphone does `default: index++`. The old
				 * "carry the previous action" duplicated the prior phoneme
				 * ("a1b2" -> AAPP not AP; "Smith-Jones" != "Smith Jones"). */
				pc = sc = NULL;
				adv = 1;
				break;
			}
		}

		if (pc) {
			smart_str_appends(primary, pc);
		}
		if (sc) {
			smart_str_appends(secondary, sc);
		}
		pos += adv;
	}

#undef B
#undef SAT

	efree(buf);
}

PHP_FUNCTION(double_metaphone)
{
	zend_string *input;
	zend_long max_length = 4;
	smart_str folded = {0};
	smart_str primary = {0};
	smart_str secondary = {0};
	const char *pval = "";
	const char *sval = "";
	size_t plen = 0;
	size_t slen = 0;

	ZEND_PARSE_PARAMETERS_START(1, 2)
		Z_PARAM_STR(input)
		Z_PARAM_OPTIONAL
		Z_PARAM_LONG(max_length)
	ZEND_PARSE_PARAMETERS_END();

	dmet_fold(ZSTR_VAL(input), ZSTR_LEN(input), &folded);
	if (folded.s != NULL && ZSTR_LEN(folded.s) > (size_t) DMET_MAX_FOLDED_INPUT) {
		smart_str_free(&folded);
		zend_argument_value_error(1, "must not exceed %d bytes after folding", DMET_MAX_FOLDED_INPUT);
		RETURN_THROWS();
	}

	if (folded.s != NULL && ZSTR_LEN(folded.s) > 0) {
		dmet_encode(ZSTR_VAL(folded.s), ZSTR_LEN(folded.s), &primary, &secondary);
	}

	if (primary.s != NULL) {
		smart_str_0(&primary);
		pval = ZSTR_VAL(primary.s);
		plen = ZSTR_LEN(primary.s);
	}
	if (secondary.s != NULL) {
		smart_str_0(&secondary);
		sval = ZSTR_VAL(secondary.s);
		slen = ZSTR_LEN(secondary.s);
	}

	/* max_length <= 0 means "no limit": emit the full untruncated codes. */
	if (max_length > 0) {
		if (plen > (size_t) max_length) {
			plen = (size_t) max_length;
		}
		if (slen > (size_t) max_length) {
			slen = (size_t) max_length;
		}
	}

	array_init_size(return_value, 2);
	add_assoc_stringl(return_value, "primary", (char *) pval, plen);
	add_assoc_stringl(return_value, "alternate", (char *) sval, slen);

	smart_str_free(&folded);
	smart_str_free(&primary);
	smart_str_free(&secondary);
}

/* Fold + encode one string into its untruncated primary/alternate codes. */
static int dmet_codes(const char *in, size_t len, uint32_t arg_num, smart_str *primary, smart_str *secondary)
{
	smart_str folded = {0};

	dmet_fold(in, len, &folded);
	if (folded.s != NULL && ZSTR_LEN(folded.s) > (size_t) DMET_MAX_FOLDED_INPUT) {
		smart_str_free(&folded);
		zend_argument_value_error(arg_num, "must not exceed %d bytes after folding", DMET_MAX_FOLDED_INPUT);
		return FAILURE;
	}
	if (folded.s != NULL && ZSTR_LEN(folded.s) > 0) {
		dmet_encode(ZSTR_VAL(folded.s), ZSTR_LEN(folded.s), primary, secondary);
	}
	if (primary->s != NULL) {
		smart_str_0(primary);
	}
	if (secondary->s != NULL) {
		smart_str_0(secondary);
	}
	smart_str_free(&folded);
	return SUCCESS;
}

/* Two non-empty codes of equal (capped) length comparing identical. */
static zend_always_inline int dmet_code_eq(const smart_str *x, const smart_str *y, size_t cap)
{
	size_t xl = x->s ? ZSTR_LEN(x->s) : 0;
	size_t yl = y->s ? ZSTR_LEN(y->s) : 0;

	if (xl > cap) xl = cap;
	if (yl > cap) yl = cap;
	if (xl == 0 || xl != yl) {
		return 0;
	}
	return memcmp(ZSTR_VAL(x->s), ZSTR_VAL(y->s), xl) == 0;
}

PHP_FUNCTION(double_metaphone_match)
{
	zend_string *a, *b;
	zend_long max_length = 4;
	smart_str pa = {0}, sa = {0}, pb = {0}, sb = {0};
	size_t cap;
	int result = 0;

	ZEND_PARSE_PARAMETERS_START(2, 3)
		Z_PARAM_STR(a)
		Z_PARAM_STR(b)
		Z_PARAM_OPTIONAL
		Z_PARAM_LONG(max_length)
	ZEND_PARSE_PARAMETERS_END();

	if (dmet_codes(ZSTR_VAL(a), ZSTR_LEN(a), 1, &pa, &sa) == FAILURE
			|| dmet_codes(ZSTR_VAL(b), ZSTR_LEN(b), 2, &pb, &sb) == FAILURE) {
		smart_str_free(&pa);
		smart_str_free(&sa);
		smart_str_free(&pb);
		smart_str_free(&sb);
		RETURN_THROWS();
	}

	/* max_length <= 0 means "no limit": compare the full codes. */
	cap = (max_length > 0) ? (size_t) max_length : (size_t) -1;

	if (dmet_code_eq(&pa, &pb, cap)) {
		result = 2;                                       /* primaries agree */
	} else if (dmet_code_eq(&pa, &sb, cap) || dmet_code_eq(&sa, &pb, cap)
			|| dmet_code_eq(&sa, &sb, cap)) {
		result = 1;                                       /* an alternate crosses */
	}

	smart_str_free(&pa);
	smart_str_free(&sa);
	smart_str_free(&pb);
	smart_str_free(&sb);

	RETURN_LONG(result);
}
