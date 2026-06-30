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

/* Daitch-Mokotoff Soundex engine. Rule data (dmrules.txt) is vendored from
 * Apache Commons Codec (Apache-2.0) and compiled in via the generated
 * bmpm_data.h. This engine replicates Commons Codec 1.17.1
 * DaitchMokotoffSoundex.soundex() (branching enabled). */

#ifdef HAVE_CONFIG_H
#include "config.h"
#endif

#include "php.h"
#include "php_phonetic.h"
#include "zend_smart_str.h"
#include "bmpm_data.h"

#include <string.h>

#define DMS_MAX 6

/* ---------------------------------------------------------------------- */
/* UTF-8 helpers                                                          */
/* ---------------------------------------------------------------------- */

/* Decode the first code point of s (length len). Stores its byte length in
 * *clen. Malformed/truncated sequences decode as a single raw byte. */
static uint32_t dms_u8_decode(const char *s, size_t len, int *clen)
{
	const unsigned char *p = (const unsigned char *) s;
	unsigned char c = p[0];

	if (c < 0x80) {
		*clen = 1;
		return c;
	}
	if ((c >> 5) == 0x6 && len >= 2 && (p[1] & 0xc0) == 0x80) {
		*clen = 2;
		return ((c & 0x1fu) << 6) | (p[1] & 0x3fu);
	}
	if ((c >> 4) == 0xe && len >= 3 && (p[1] & 0xc0) == 0x80 && (p[2] & 0xc0) == 0x80) {
		*clen = 3;
		return ((c & 0x0fu) << 12) | ((p[1] & 0x3fu) << 6) | (p[2] & 0x3fu);
	}
	if ((c >> 3) == 0x1e && len >= 4 && (p[1] & 0xc0) == 0x80 && (p[2] & 0xc0) == 0x80 &&
	    (p[3] & 0xc0) == 0x80) {
		*clen = 4;
		return ((c & 0x07u) << 18) | ((p[1] & 0x3fu) << 12) | ((p[2] & 0x3fu) << 6) | (p[3] & 0x3fu);
	}

	*clen = 1;
	return c;
}

/* Encode a code point to UTF-8 in buf (>= 4 bytes); returns the byte length. */
static int dms_u8_encode(uint32_t cp, char *buf)
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

/* Number of code points in a UTF-8 byte run. */
static int dms_u8_cplen(const char *s, size_t len)
{
	size_t i;
	int n = 0;
	for (i = 0; i < len; i++) {
		if (((unsigned char) s[i] & 0xc0) != 0x80) {
			n++;
		}
	}
	return n;
}

/* Lower-case a single code point, mirroring Java's Character.toLowerCase for
 * the Latin ranges the rule data and realistic names use. */
static uint32_t dms_lc_cp(uint32_t c)
{
	if (c >= 'A' && c <= 'Z') return c + 32;
	if (c >= 0xC0 && c <= 0xD6) return c + 0x20;   /* A-grave .. O-diaeresis */
	if (c >= 0xD8 && c <= 0xDE) return c + 0x20;   /* O-slash .. Thorn */
	if (c >= 0x0100 && c <= 0x0137) return (c & 1u) ? c : c + 1;
	if (c >= 0x0139 && c <= 0x0148) return (c & 1u) ? c + 1 : c;
	if (c >= 0x014A && c <= 0x0177) return (c & 1u) ? c : c + 1;
	if (c == 0x0178) return 0x00FF;                 /* Y-diaeresis */
	if (c >= 0x0179 && c <= 0x017E) return (c & 1u) ? c + 1 : c;
	if (c == 0x021A) return 0x021B;                 /* T-comma (Romanian) */
	return c;
}

/* Java Character.isWhitespace: ASCII control whitespace plus the Unicode space
 * separators, excluding the no-break spaces. */
static int dms_is_ws(uint32_t cp)
{
	if (cp >= 0x09 && cp <= 0x0D) return 1;
	if (cp >= 0x1C && cp <= 0x1F) return 1;
	if (cp == 0x20) return 1;
	if (cp == 0x1680) return 1;
	if (cp >= 0x2000 && cp <= 0x2006) return 1;     /* 0x2007 (figure space) excluded */
	if (cp >= 0x2008 && cp <= 0x200A) return 1;
	if (cp == 0x2028 || cp == 0x2029) return 1;
	if (cp == 0x205F) return 1;
	if (cp == 0x3000) return 1;
	return 0;
}

/* ---------------------------------------------------------------------- */
/* Branch set                                                             */
/* ---------------------------------------------------------------------- */

typedef struct {
	char code[DMS_MAX + 1];   /* accumulated digits, NUL-terminated */
	int  len;
	char lastrep[4];          /* last replacement applied (<= 2 chars) */
	int  last_null;           /* 1 while no replacement has been applied yet */
} dms_branch;

typedef struct {
	dms_branch *b;
	int         n;
	int         cap;
} dms_set;

static void dms_set_init(dms_set *s)
{
	s->b = NULL;
	s->n = 0;
	s->cap = 0;
}

static void dms_set_free(dms_set *s)
{
	if (s->b) {
		efree(s->b);
	}
	s->b = NULL;
	s->n = 0;
	s->cap = 0;
}

static void dms_set_push(dms_set *s, const dms_branch *br)
{
	if (s->n == s->cap) {
		s->cap = s->cap ? s->cap * 2 : 8;
		s->b = erealloc(s->b, (size_t) s->cap * sizeof(dms_branch));
	}
	s->b[s->n++] = *br;
}

/* Index of a branch whose accumulated code equals `code`, or -1. */
static int dms_set_find(const dms_set *s, const char *code)
{
	int i;
	for (i = 0; i < s->n; i++) {
		if (strcmp(s->b[i].code, code) == 0) {
			return i;
		}
	}
	return -1;
}

/* Insert preserving first occurrence (mirrors LinkedHashSet<Branch>, where
 * Branch identity is its accumulated string). */
static void dms_set_push_dedup(dms_set *s, const dms_branch *br)
{
	if (dms_set_find(s, br->code) < 0) {
		dms_set_push(s, br);
	}
}

/* Commons Codec Branch.processNextReplacement. A replacement is skipped when
 * the previous replacement already ends with it, unless forced (mn/nm). */
static void dms_process(dms_branch *br, const char *rep, int force)
{
	size_t replen = strlen(rep);
	int append;

	if (br->last_null || force) {
		append = 1;
	} else {
		size_t ll = strlen(br->lastrep);
		if (replen > ll) {
			append = 1;
		} else {
			append = memcmp(br->lastrep + ll - replen, rep, replen) != 0;
		}
	}

	if (append && br->len < DMS_MAX) {
		size_t i;
		for (i = 0; i < replen && br->len < DMS_MAX; i++) {
			br->code[br->len++] = rep[i];
		}
		br->code[br->len] = '\0';
	}

	/* lastReplacement is always updated, even when not appended. */
	memcpy(br->lastrep, rep, replen + 1 <= sizeof(br->lastrep) ? replen + 1 : sizeof(br->lastrep));
	br->lastrep[sizeof(br->lastrep) - 1] = '\0';
	br->last_null = 0;
}

/* ---------------------------------------------------------------------- */
/* MINIT-built first-byte dispatch index over dm_rules                     */
/* ---------------------------------------------------------------------- */

/* Precomputed pattern byte-length and code-point length per rule, plus rule
 * indices bucketed by the pattern's first byte. dms_encode then scans only the
 * rules that can match at the current position instead of all of dm_rules, and
 * never recomputes strlen()/cplen() in the hot loop. Read-only after MINIT. */
static int *g_dms_plen;        /* [dm_rules_count] strlen(pattern) */
static int *g_dms_pcplen;      /* [dm_rules_count] code-point length */
static int *g_dms_order;       /* [dm_rules_count] rule indices grouped by first byte */
static int  g_dms_off[256];
static int  g_dms_cnt[256];

void dms_minit(void)
{
	size_t r;
	int b, pos;
	int fillpos[256];

	g_dms_plen   = pemalloc(dm_rules_count * sizeof(int), 1);
	g_dms_pcplen = pemalloc(dm_rules_count * sizeof(int), 1);
	g_dms_order  = pemalloc(dm_rules_count * sizeof(int), 1);

	memset(g_dms_cnt, 0, sizeof g_dms_cnt);
	for (r = 0; r < dm_rules_count; r++) {
		const char *pat = dm_rules[r].pattern;
		int pb = (int) strlen(pat);
		g_dms_plen[r]   = pb;
		g_dms_pcplen[r] = dms_u8_cplen(pat, (size_t) pb);
		if (pb > 0) {
			g_dms_cnt[(unsigned char) pat[0]]++;
		}
	}

	pos = 0;
	for (b = 0; b < 256; b++) {
		g_dms_off[b] = pos;
		pos += g_dms_cnt[b];
	}

	memcpy(fillpos, g_dms_off, sizeof fillpos);
	for (r = 0; r < dm_rules_count; r++) {
		if (g_dms_plen[r] > 0) {
			unsigned char fb = (unsigned char) dm_rules[r].pattern[0];
			g_dms_order[fillpos[fb]++] = (int) r;
		}
	}
}

void dms_mshutdown(void)
{
	if (g_dms_plen)   { pefree(g_dms_plen, 1);   g_dms_plen = NULL; }
	if (g_dms_pcplen) { pefree(g_dms_pcplen, 1); g_dms_pcplen = NULL; }
	if (g_dms_order)  { pefree(g_dms_order, 1);  g_dms_order = NULL; }
}

/* ---------------------------------------------------------------------- */
/* Encoder                                                                */
/* ---------------------------------------------------------------------- */

/* Clean the input: drop whitespace, lower-case, then apply accent/ligature
 * folding. Output is a UTF-8 byte run. */
static void dms_cleanup(const char *s, size_t len, smart_str *out)
{
	size_t i = 0;

	while (i < len) {
		int cl;
		uint32_t cp = dms_u8_decode(s + i, len - i, &cl);
		i += cl;

		if (dms_is_ws(cp)) {
			continue;
		}

		cp = dms_lc_cp(cp);

		if (cp < 0x80) {
			smart_str_appendc(out, (char) cp);
			continue;
		}

		{
			char tmp[4];
			int tl = dms_u8_encode(cp, tmp);
			int folded = 0;
			size_t f;
			for (f = 0; f < dm_foldings_count; f++) {
				const char *from = dm_foldings[f].from;
				if ((int) strlen(from) == tl && memcmp(from, tmp, (size_t) tl) == 0) {
					smart_str_appends(out, dm_foldings[f].to);
					folded = 1;
					break;
				}
			}
			if (!folded) {
				smart_str_appendl(out, tmp, (size_t) tl);
			}
		}
	}
}

static int dms_is_vowel(char c)
{
	return c == 'a' || c == 'e' || c == 'i' || c == 'o' || c == 'u';
}

/* Run the DM soundex over the cleaned buffer, collecting distinct 6-digit
 * codes into `out` in branch insertion order. */
static void dms_encode(const char *buf, size_t buflen, dms_set *out)
{
	dms_set cur;
	dms_branch init;
	uint32_t last_char = 0;
	size_t index = 0;
	int i;

	dms_set_init(&cur);
	memset(&init, 0, sizeof init);
	init.last_null = 1;
	dms_set_push(&cur, &init);

	while (index < buflen) {
		int cplen;
		uint32_t cp = dms_u8_decode(buf + index, buflen - index, &cplen);
		const dm_rule *best = NULL;
		int best_bytes = 0;
		int best_cplen = 0;
		const char *field;
		int at_start;
		char alts[8][4];
		int nalts;
		int force;
		dms_set next;

		/* Longest pattern matching at this position. Only rules whose pattern
		 * starts with the current byte can match, so scan that bucket alone and
		 * read the pre-stored byte/code-point lengths instead of recomputing. */
		{
			unsigned char fb = (unsigned char) buf[index];
			int off = g_dms_off[fb], cnt = g_dms_cnt[fb], t;
			for (t = 0; t < cnt; t++) {
				int rr = g_dms_order[off + t];
				int pb = g_dms_plen[rr];
				if ((size_t) index + (size_t) pb > buflen) {
					continue;
				}
				if (memcmp(buf + index, dm_rules[rr].pattern, (size_t) pb) != 0) {
					continue;
				}
				if (best == NULL || g_dms_pcplen[rr] > best_cplen) {
					best = &dm_rules[rr];
					best_cplen = g_dms_pcplen[rr];
					best_bytes = pb;
				}
			}
		}

		if (best == NULL) {
			index += cplen;
			continue;
		}

		at_start = (last_char == 0);
		if (at_start) {
			field = best->at_start;
		} else {
			size_t na = index + (size_t) best_bytes;
			char nx = (na < buflen) ? buf[na] : '\0';
			field = dms_is_vowel(nx) ? best->before_vowel : best->default_code;
		}

		/* Split the replacement field on '|' into branch alternatives. */
		nalts = 0;
		{
			const char *p = field;
			int wl = 0;
			for (;;) {
				if (*p == '|' || *p == '\0') {
					alts[nalts][wl] = '\0';
					nalts++;
					if (*p == '\0' || nalts >= 8) {
						break;
					}
					wl = 0;
					p++;
				} else {
					if (wl < 3) {
						alts[nalts][wl++] = *p;
					}
					p++;
				}
			}
		}

		force = (last_char == 'm' && cp == 'n') || (last_char == 'n' && cp == 'm');

		dms_set_init(&next);
		for (i = 0; i < cur.n; i++) {
			int ai;
			for (ai = 0; ai < nalts; ai++) {
				dms_branch nb = cur.b[i];
				dms_process(&nb, alts[ai], force);
				dms_set_push_dedup(&next, &nb);
			}
		}
		dms_set_free(&cur);
		cur = next;

		last_char = cp;
		index += (size_t) best_bytes;
	}

	for (i = 0; i < cur.n; i++) {
		dms_branch *br = &cur.b[i];
		while (br->len < DMS_MAX) {
			br->code[br->len++] = '0';
		}
		br->code[DMS_MAX] = '\0';
		if (dms_set_find(out, br->code) < 0) {
			dms_set_push(out, br);
		}
	}
	dms_set_free(&cur);
}

/* ---------------------------------------------------------------------- */
/* PHP function                                                           */
/* ---------------------------------------------------------------------- */

PHP_FUNCTION(dm_soundex)
{
	zend_string *input;
	smart_str cleaned = {0};
	dms_set out;
	const char *buf;
	size_t buflen;
	int i;

	ZEND_PARSE_PARAMETERS_START(1, 1)
		Z_PARAM_STR(input)
	ZEND_PARSE_PARAMETERS_END();

	array_init(return_value);

	/* Empty input maps to an empty list (API contract); any non-empty input
	 * runs the engine, matching the oracle even when nothing codes. */
	if (ZSTR_LEN(input) == 0) {
		return;
	}

	dms_cleanup(ZSTR_VAL(input), ZSTR_LEN(input), &cleaned);
	smart_str_0(&cleaned);
	buf = cleaned.s ? ZSTR_VAL(cleaned.s) : "";
	buflen = cleaned.s ? ZSTR_LEN(cleaned.s) : 0;

	dms_set_init(&out);
	dms_encode(buf, buflen, &out);
	for (i = 0; i < out.n; i++) {
		add_next_index_stringl(return_value, out.b[i].code, DMS_MAX);
	}

	dms_set_free(&out);
	smart_str_free(&cleaned);
}

/* Encode `input` into its Daitch-Mokotoff code set. */
static void dms_codes(zend_string *input, dms_set *out)
{
	smart_str cleaned = {0};

	dms_set_init(out);
	if (ZSTR_LEN(input) == 0) {
		return;
	}
	dms_cleanup(ZSTR_VAL(input), ZSTR_LEN(input), &cleaned);
	smart_str_0(&cleaned);
	dms_encode(cleaned.s ? ZSTR_VAL(cleaned.s) : "",
	           cleaned.s ? ZSTR_LEN(cleaned.s) : 0, out);
	smart_str_free(&cleaned);
}

PHP_FUNCTION(dm_soundex_match)
{
	zend_string *a, *b;
	dms_set sa, sb;
	int i;
	zend_bool matched = 0;

	ZEND_PARSE_PARAMETERS_START(2, 2)
		Z_PARAM_STR(a)
		Z_PARAM_STR(b)
	ZEND_PARSE_PARAMETERS_END();

	dms_codes(a, &sa);
	dms_codes(b, &sb);

	for (i = 0; i < sa.n; i++) {
		if (dms_set_find(&sb, sa.b[i].code) >= 0) {
			matched = 1;
			break;
		}
	}

	dms_set_free(&sa);
	dms_set_free(&sb);

	RETURN_BOOL(matched);
}
