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

/* Beider-Morse Phonetic Matching engine. Rule data is vendored from Apache
 * Commons Codec (Apache-2.0) and compiled in via the generated bmpm_data.h.
 *
 * The engine reproduces the semantics of Commons Codec's PhoneticEngine with
 * its default BeiderMorseEncoder settings (concat = true, maxPhonemes = 20).
 * All transliteration matching runs over Unicode code points so multi-byte
 * patterns and character classes line up with the reference (which matches on
 * Java chars); generated phoneme output is pure ASCII. */

#ifdef HAVE_CONFIG_H
#include "config.h"
#endif

#include <stdint.h>
#include <string.h>

#ifdef _MSC_VER
#include <intrin.h>
#endif

#include "php.h"
#include "zend_smart_str.h"
#include "php_phonetic.h"
#include "bmpm_data.h"

#define BMPM_MAX_PHONEMES 20

/* The GENERIC d'/name-prefix handling recurses (encode_core -> encode_guess ->
 * encode_core), peeling one code point per level. Real names nest at most one
 * or two prefixes; crafted input ("d" followed by thousands of apostrophes)
 * would otherwise drive O(input-length) native recursion and smash the C stack.
 * Past this depth, stop treating a leading prefix specially and encode inline. */
#define BMPM_MAX_PREFIX_DEPTH 16

typedef uint32_t langset_t;
#define LS_NONE 0u
#define LS_ANY  0xFFFFFFFFu

/* ---------------------------------------------------------------------- */
/* UTF-8 <-> code points                                                  */
/* ---------------------------------------------------------------------- */

static uint32_t *u8_decode(const char *s, size_t len, int *n)
{
	uint32_t *out = safe_emalloc(len + 1, sizeof(uint32_t), 0);
	int k = 0;
	size_t i = 0;
	while (i < len) {
		unsigned char c = (unsigned char) s[i];
		uint32_t cp;
		int adv;
		if (c < 0x80) {
			cp = c; adv = 1;
		} else if ((c >> 5) == 0x6 && i + 1 < len) {
			cp = ((c & 0x1fu) << 6) | ((unsigned char) s[i + 1] & 0x3fu); adv = 2;
		} else if ((c >> 4) == 0xe && i + 2 < len) {
			cp = ((c & 0x0fu) << 12) | (((unsigned char) s[i + 1] & 0x3fu) << 6) |
			     ((unsigned char) s[i + 2] & 0x3fu); adv = 3;
		} else if ((c >> 3) == 0x1e && i + 3 < len) {
			cp = ((c & 0x07u) << 18) | (((unsigned char) s[i + 1] & 0x3fu) << 12) |
			     (((unsigned char) s[i + 2] & 0x3fu) << 6) | ((unsigned char) s[i + 3] & 0x3fu); adv = 4;
		} else {
			cp = c; adv = 1;
		}
		out[k++] = cp;
		i += (size_t) adv;
	}
	*n = k;
	return out;
}

static int u8_decode_buf(const char *s, uint32_t *buf, int cap)
{
	int k = 0;
	size_t i = 0, len = strlen(s);
	while (i < len && k < cap) {
		unsigned char c = (unsigned char) s[i];
		uint32_t cp;
		int adv;
		if (c < 0x80) {
			cp = c; adv = 1;
		} else if ((c >> 5) == 0x6 && i + 1 < len) {
			cp = ((c & 0x1fu) << 6) | ((unsigned char) s[i + 1] & 0x3fu); adv = 2;
		} else if ((c >> 4) == 0xe && i + 2 < len) {
			cp = ((c & 0x0fu) << 12) | (((unsigned char) s[i + 1] & 0x3fu) << 6) |
			     ((unsigned char) s[i + 2] & 0x3fu); adv = 3;
		} else if ((c >> 3) == 0x1e && i + 3 < len) {
			cp = ((c & 0x07u) << 18) | (((unsigned char) s[i + 1] & 0x3fu) << 12) |
			     (((unsigned char) s[i + 2] & 0x3fu) << 6) | ((unsigned char) s[i + 3] & 0x3fu); adv = 4;
		} else {
			cp = c; adv = 1;
		}
		buf[k++] = cp;
		i += (size_t) adv;
	}
	return k;
}

static uint32_t u8_first(const char *s)
{
	const unsigned char *p = (const unsigned char *) s;
	unsigned char c = p[0];
	if (c < 0x80) return c;
	if ((c >> 5) == 0x6) return ((c & 0x1fu) << 6) | (p[1] & 0x3fu);
	if ((c >> 4) == 0xe) return ((c & 0x0fu) << 12) | ((p[1] & 0x3fu) << 6) | (p[2] & 0x3fu);
	if ((c >> 3) == 0x1e) return ((c & 0x07u) << 18) | ((p[1] & 0x3fu) << 12) | ((p[2] & 0x3fu) << 6) | (p[3] & 0x3fu);
	return c;
}

/* Append one code point to a smart_str as UTF-8. */
static void u8_put(smart_str *out, uint32_t cp)
{
	if (cp < 0x80) {
		smart_str_appendc(out, (char) cp);
	} else if (cp < 0x800) {
		smart_str_appendc(out, (char) (0xc0 | (cp >> 6)));
		smart_str_appendc(out, (char) (0x80 | (cp & 0x3f)));
	} else if (cp < 0x10000) {
		smart_str_appendc(out, (char) (0xe0 | (cp >> 12)));
		smart_str_appendc(out, (char) (0x80 | ((cp >> 6) & 0x3f)));
		smart_str_appendc(out, (char) (0x80 | (cp & 0x3f)));
	} else {
		smart_str_appendc(out, (char) (0xf0 | (cp >> 18)));
		smart_str_appendc(out, (char) (0x80 | ((cp >> 12) & 0x3f)));
		smart_str_appendc(out, (char) (0x80 | ((cp >> 6) & 0x3f)));
		smart_str_appendc(out, (char) (0x80 | (cp & 0x3f)));
	}
}

/* Encode a code-point range to a freshly allocated, NUL-terminated UTF-8
 * string; the caller owns it. */
static char *u8_encode(const uint32_t *cp, int n, size_t *outlen)
{
	smart_str s = {0};
	int i;
	for (i = 0; i < n; i++) {
		u8_put(&s, cp[i]);
	}
	smart_str_0(&s);
	if (s.s == NULL) {
		char *e = emalloc(1);
		e[0] = '\0';
		*outlen = 0;
		return e;
	}
	*outlen = ZSTR_LEN(s.s);
	char *r = estrndup(ZSTR_VAL(s.s), ZSTR_LEN(s.s));
	smart_str_free(&s);
	return r;
}

/* Lower-case a single code point, mirroring Java's
 * String.toLowerCase(Locale.ENGLISH) for the Latin ranges the rule data and
 * realistic names use. */
static uint32_t lc_cp(uint32_t c)
{
	if (c >= 'A' && c <= 'Z') return c + 32;
	if (c >= 0xC0 && c <= 0xD6) return c + 0x20;   /* A-grave .. O-diaeresis */
	if (c >= 0xD8 && c <= 0xDE) return c + 0x20;   /* O-slash .. Thorn */
	if (c >= 0x0100 && c <= 0x0137) return (c & 1u) ? c : c + 1;
	if (c >= 0x0139 && c <= 0x0148) return (c & 1u) ? c + 1 : c;
	if (c >= 0x014A && c <= 0x0177) return (c & 1u) ? c : c + 1;
	if (c == 0x0178) return 0x00FF;                 /* Y-diaeresis */
	if (c >= 0x0179 && c <= 0x017E) return (c & 1u) ? c + 1 : c;
	return c;
}

/* ---------------------------------------------------------------------- */
/* Language sets                                                          */
/* ---------------------------------------------------------------------- */

static const bmpm_language_list *nt_langs(int nt)
{
	size_t i;
	for (i = 0; i < bmpm_language_lists_count; i++) {
		if (bmpm_language_lists[i].name_type == nt) {
			return &bmpm_language_lists[i];
		}
	}
	return NULL;
}

static langset_t ls_full(int nt)
{
	const bmpm_language_list *l = nt_langs(nt);
	return (langset_t) ((1u << l->count) - 1u);
}

static int lang_index(int nt, const char *name, size_t len)
{
	const bmpm_language_list *l = nt_langs(nt);
	size_t i;
	for (i = 0; i < l->count; i++) {
		if (strlen(l->languages[i]) == len && memcmp(l->languages[i], name, len) == 0) {
			return (int) i;
		}
	}
	return -1;
}

/* Parse a '+'-separated language list into a bitmask. */
static langset_t parse_lang_list(int nt, const char *s)
{
	langset_t mask = 0;
	const char *p = s;
	while (*p) {
		const char *start = p;
		while (*p && *p != '+') p++;
		int idx = lang_index(nt, start, (size_t) (p - start));
		if (idx >= 0) mask |= (1u << idx);
		if (*p == '+') p++;
	}
	return mask;
}

static langset_t ls_restrict(langset_t a, langset_t b)
{
	if (a == LS_ANY) return b;
	if (b == LS_ANY) return a;
	return a & b;
}

static langset_t ls_merge(langset_t a, langset_t b)
{
	if (a == LS_NONE) return b;
	if (a == LS_ANY) return b;
	if (b == LS_NONE) return a;
	if (b == LS_ANY) return LS_ANY;
	return a | b;
}

/* Index of the lowest set bit; ls is guaranteed non-zero by the caller. */
static int ls_ctz(uint32_t ls)
{
#if defined(__GNUC__) || defined(__clang__)
	return __builtin_ctz(ls);
#elif defined(_MSC_VER)
	unsigned long idx;
	_BitScanForward(&idx, ls);
	return (int) idx;
#else
	int n = 0;
	while (!(ls & 1u)) { ls >>= 1; n++; }
	return n;
#endif
}

static int ls_singleton_lang(int nt, langset_t ls, const char **name)
{
	if (ls == LS_ANY || ls == LS_NONE) return 0;
	if ((ls & (ls - 1)) != 0) return 0;       /* more than one bit */
	int idx = ls_ctz(ls);
	*name = nt_langs(nt)->languages[idx];
	return 1;
}

/* ---------------------------------------------------------------------- */
/* Restricted-regex matcher (mirrors Rule.pattern() in Commons Codec)     */
/* ---------------------------------------------------------------------- */

static int classmember(const uint32_t *cls, int cn, uint32_t c)
{
	int i;
	for (i = 0; i < cn; i++) {
		if (cls[i] == c) return 1;
	}
	return 0;
}

static int seqeq(const uint32_t *a, int an, const uint32_t *b, int bn)
{
	int i;
	if (an != bn) return 0;
	for (i = 0; i < an; i++) if (a[i] != b[i]) return 0;
	return 1;
}

static int prefixeq(const uint32_t *seg, int sn, const uint32_t *p, int pn)
{
	int i;
	if (pn > sn) return 0;
	for (i = 0; i < pn; i++) if (seg[i] != p[i]) return 0;
	return 1;
}

static int suffixeq(const uint32_t *seg, int sn, const uint32_t *p, int pn)
{
	int i;
	if (pn > sn) return 0;
	for (i = 0; i < pn; i++) if (seg[sn - pn + i] != p[i]) return 0;
	return 1;
}

static int substr_find(const uint32_t *seg, int sn, const uint32_t *p, int pn)
{
	int s;
	if (pn == 0) return 1;
	for (s = 0; s + pn <= sn; s++) {
		int k, ok = 1;
		for (k = 0; k < pn; k++) if (seg[s + k] != p[k]) { ok = 0; break; }
		if (ok) return 1;
	}
	return 0;
}

typedef struct {
	int             is_class;
	int             negate;
	uint32_t        lit;
	const uint32_t *cls;
	int             cn;
} atom_t;

static int atom_match(const atom_t *a, uint32_t c)
{
	if (a->is_class) {
		int m = classmember(a->cls, a->cn, c);
		return a->negate ? !m : m;
	}
	return c == a->lit;
}

static int atoms_match_at(const uint32_t *seg, int sn, const atom_t *atoms, int na, int p)
{
	int k;
	for (k = 0; k < na; k++) {
		if (p + k >= sn) return 0;
		if (!atom_match(&atoms[k], seg[p + k])) return 0;
	}
	return 1;
}

/* General "find" matcher over the restricted regex grammar (anchors plus
 * literal / character-class atoms). Drives both the rare rule-context
 * fall-through cases and the language-guessing patterns. */
static int regex_atom_match(const uint32_t *R, int Rn, const uint32_t *seg, int sn)
{
	int as = 0, ae = 0, pos = 0, end = Rn;
	atom_t atoms[64];
	int na = 0;

	if (Rn > 0 && R[0] == '^') { as = 1; pos = 1; }
	if (end > pos && R[end - 1] == '$') { ae = 1; end--; }

	while (pos < end && na < 64) {
		if (R[pos] == '[') {
			int j = pos + 1, neg = 0, cs;
			if (j < end && R[j] == '^') { neg = 1; j++; }
			cs = j;
			while (j < end && R[j] != ']') j++;
			atoms[na].is_class = 1;
			atoms[na].negate = neg;
			atoms[na].cls = R + cs;
			atoms[na].cn = j - cs;
			na++;
			pos = (j < end) ? j + 1 : end;
		} else {
			atoms[na].is_class = 0;
			atoms[na].negate = 0;
			atoms[na].lit = R[pos];
			na++;
			pos++;
		}
	}

	if (as && ae) {
		if (sn != na) return 0;
		return atoms_match_at(seg, sn, atoms, na, 0);
	}
	if (as) {
		if (sn < na) return 0;
		return atoms_match_at(seg, sn, atoms, na, 0);
	}
	if (ae) {
		if (sn < na) return 0;
		return atoms_match_at(seg, sn, atoms, na, sn - na);
	}
	{
		int p;
		if (na == 0) return 1;
		for (p = 0; p + na <= sn; p++) {
			if (atoms_match_at(seg, sn, atoms, na, p)) return 1;
		}
		return 0;
	}
}

/* Match a rule's raw left/right context. side 0 = left  (regex = raw + "$"),
 * side 1 = right (regex = "^" + raw). Reproduces Commons Codec's pattern()
 * dispatch byte for byte, including its literal treatment of "." and the
 * fall-through to the general matcher for compound contexts. */
static int ctx_match(const char *raw, int side, const uint32_t *seg, int sn)
{
	uint32_t rb[128];
	int rn = u8_decode_buf(raw, rb, 128);
	uint32_t R[130];
	int Rn = 0, i;

	if (side == 1) {
		R[Rn++] = '^';
		for (i = 0; i < rn; i++) R[Rn++] = rb[i];
	} else {
		for (i = 0; i < rn; i++) R[Rn++] = rb[i];
		R[Rn++] = '$';
	}

	int sw = Rn > 0 && R[0] == '^';
	int ew = Rn > 0 && R[Rn - 1] == '$';
	const uint32_t *content = R + (sw ? 1 : 0);
	int cn = Rn - (sw ? 1 : 0) - (ew ? 1 : 0);
	int boxes = 0;
	for (i = 0; i < cn; i++) if (content[i] == '[') { boxes = 1; break; }

	if (!boxes) {
		if (sw && ew) {
			if (cn == 0) return sn == 0;
			return seqeq(seg, sn, content, cn);
		}
		if ((sw || ew) && cn == 0) return 1;
		if (sw) return prefixeq(seg, sn, content, cn);
		if (ew) return suffixeq(seg, sn, content, cn);
		return substr_find(seg, sn, content, cn);
	} else {
		int swB = cn > 0 && content[0] == '[';
		int ewB = cn > 0 && content[cn - 1] == ']';
		if (swB && ewB) {
			const uint32_t *inner = content + 1;
			int in_n = cn - 2;
			int hasInner = 0;
			for (i = 0; i < in_n; i++) if (inner[i] == '[') { hasInner = 1; break; }
			if (!hasInner) {
				int negate = in_n > 0 && inner[0] == '^';
				const uint32_t *cls = inner + (negate ? 1 : 0);
				int clsn = in_n - (negate ? 1 : 0);
				int should = !negate;
				if (sw && ew) return sn == 1 && (classmember(cls, clsn, seg[0]) == should);
				if (sw) return sn > 0 && (classmember(cls, clsn, seg[0]) == should);
				if (ew) return sn > 0 && (classmember(cls, clsn, seg[sn - 1]) == should);
			}
		}
		return regex_atom_match(R, Rn, seg, sn);
	}
}

/* ---------------------------------------------------------------------- */
/* Phoneme builder                                                        */
/* ---------------------------------------------------------------------- */

typedef struct {
	char     *t;
	size_t    tn;
	langset_t langs;
} phon_t;

typedef struct {
	phon_t *a;
	size_t  n;
	size_t  cap;
} pbuilder;

static void pb_init(pbuilder *pb)
{
	pb->a = NULL;
	pb->n = 0;
	pb->cap = 0;
}

static void pb_reserve(pbuilder *pb, size_t need)
{
	if (need > pb->cap) {
		size_t nc = pb->cap ? pb->cap * 2 : 8;
		if (nc < need) nc = need;
		pb->a = erealloc(pb->a, nc * sizeof(phon_t));
		pb->cap = nc;
	}
}

static void pb_push(pbuilder *pb, const char *lt, size_t ln, const char *rt, size_t rn, langset_t langs)
{
	pb_reserve(pb, pb->n + 1);
	char *t = emalloc(ln + rn + 1);
	if (ln) memcpy(t, lt, ln);
	if (rn) memcpy(t + ln, rt, rn);
	t[ln + rn] = '\0';
	pb->a[pb->n].t = t;
	pb->a[pb->n].tn = ln + rn;
	pb->a[pb->n].langs = langs;
	pb->n++;
}

static void pb_free(pbuilder *pb)
{
	size_t i;
	for (i = 0; i < pb->n; i++) efree(pb->a[i].t);
	if (pb->a) efree(pb->a);
	pb->a = NULL;
	pb->n = pb->cap = 0;
}

static void pb_seed(pbuilder *pb, langset_t langs)
{
	pb_push(pb, "", 0, "", 0, langs);
}

/* Append a literal run to every phoneme in place. */
static void pb_append_all(pbuilder *pb, const char *s, size_t sn)
{
	size_t i;
	for (i = 0; i < pb->n; i++) {
		phon_t *p = &pb->a[i];
		char *nt = emalloc(p->tn + sn + 1);
		if (p->tn) memcpy(nt, p->t, p->tn);
		memcpy(nt + p->tn, s, sn);
		nt[p->tn + sn] = '\0';
		efree(p->t);
		p->t = nt;
		p->tn += sn;
	}
}

typedef struct {
	const char *t;
	int         tn;
	langset_t   langs;
} alt_t;

/* Parse one phoneme expression (the rule's 4th column) into alternatives.
 * Mirrors Rule.parsePhonemeExpr / parsePhoneme, including Java's
 * trailing-empty split behaviour and the explicit silent alternative when the
 * body has a leading or trailing '|'. */
static int parse_phoneme_expr(const char *raw, int nt, alt_t *alts, int cap)
{
	size_t len = strlen(raw);
	int na = 0;

	if (len >= 2 && raw[0] == '(' && raw[len - 1] == ')') {
		const char *body = raw + 1;
		int blen = (int) len - 2;
		int has_bar = 0, i;
		for (i = 0; i < blen; i++) if (body[i] == '|') { has_bar = 1; break; }

		if (!has_bar) {
			/* single segment, even if empty */
			int start = 0, l = blen;
			const char *seg = body + start;
			int o = -1, j;
			for (j = 0; j < l; j++) if (seg[j] == '[') { o = j; break; }
			if (o >= 0 && l > 0 && seg[l - 1] == ']') {
				char lb[64];
				int cln = l - 1 - (o + 1);
				if (cln < 0) cln = 0;
				if (cln > 63) cln = 63;
				memcpy(lb, seg + o + 1, cln); lb[cln] = '\0';
				alts[na].t = seg; alts[na].tn = o;
				alts[na].langs = parse_lang_list(nt, lb);
			} else {
				alts[na].t = seg; alts[na].tn = l; alts[na].langs = LS_ANY;
			}
			na++;
		} else {
			/* split on '|', then drop trailing empty segments (Java) */
			struct { int s, l; } segs[64];
			int ns = 0, start = 0;
			for (i = 0; i <= blen; i++) {
				if (i == blen || body[i] == '|') {
					if (ns < 64) { segs[ns].s = start; segs[ns].l = i - start; ns++; }
					start = i + 1;
				}
			}
			while (ns > 0 && segs[ns - 1].l == 0) ns--;
			for (i = 0; i < ns && na < cap; i++) {
				const char *seg = body + segs[i].s;
				int l = segs[i].l;
				int o = -1, j;
				for (j = 0; j < l; j++) if (seg[j] == '[') { o = j; break; }
				if (o >= 0 && l > 0 && seg[l - 1] == ']') {
					char lb[64];
					int cln = l - 1 - (o + 1);
					if (cln < 0) cln = 0;
					if (cln > 63) cln = 63;
					memcpy(lb, seg + o + 1, cln); lb[cln] = '\0';
					alts[na].t = seg; alts[na].tn = o;
					alts[na].langs = parse_lang_list(nt, lb);
				} else {
					alts[na].t = seg; alts[na].tn = l; alts[na].langs = LS_ANY;
				}
				na++;
			}
		}

		if (na < cap && (body[0] == '|' || (blen > 0 && body[blen - 1] == '|'))) {
			alts[na].t = ""; alts[na].tn = 0; alts[na].langs = LS_ANY;
			na++;
		}
	} else {
		int l = (int) len, o = -1, j;
		for (j = 0; j < l; j++) if (raw[j] == '[') { o = j; break; }
		if (o >= 0 && l > 0 && raw[l - 1] == ']') {
			char lb[64];
			int cln = l - 1 - (o + 1);
			if (cln < 0) cln = 0;
			if (cln > 63) cln = 63;
			memcpy(lb, raw + o + 1, cln); lb[cln] = '\0';
			alts[na].t = raw; alts[na].tn = o;
			alts[na].langs = parse_lang_list(nt, lb);
		} else {
			alts[na].t = raw; alts[na].tn = l; alts[na].langs = LS_ANY;
		}
		na++;
	}
	return na;
}

/* Cartesian product of the current phonemes with a rule's alternatives,
 * pruning language-incompatible combinations and capping at maxPhonemes with a
 * mid-product break, exactly as PhonemeBuilder.apply does. */
static void pb_apply(pbuilder *pb, const char *raw_phoneme, int nt, int max)
{
	alt_t alts[64];
	int na = parse_phoneme_expr(raw_phoneme, nt, alts, 64);
	pbuilder out;
	size_t li;
	int ai;
	int done = 0;

	pb_init(&out);
	for (li = 0; li < pb->n && !done; li++) {
		for (ai = 0; ai < na; ai++) {
			langset_t ls = ls_restrict(pb->a[li].langs, alts[ai].langs);
			if (ls == LS_NONE) continue;
			if ((int) out.n < max) {
				pb_push(&out, pb->a[li].t, pb->a[li].tn, alts[ai].t, (size_t) alts[ai].tn, ls);
				if ((int) out.n >= max) { done = 1; break; }
			}
		}
	}
	pb_free(pb);
	*pb = out;
}

/* ---------------------------------------------------------------------- */
/* Rule lookup                                                            */
/* ---------------------------------------------------------------------- */

static const bmpm_ruleset *find_ruleset(int nt, int rt, const char *lang)
{
	size_t i;
	for (i = 0; i < bmpm_rulesets_count; i++) {
		const bmpm_ruleset *r = &bmpm_rulesets[i];
		if (r->name_type == nt && r->rule_type == rt && strcmp(r->language, lang) == 0) {
			return r;
		}
	}
	return NULL;
}

/* Walk the transliteration rules once across cp[0..n), mutating the builder.
 * Unmatched positions are dropped (no literal append), matching the main pass
 * in PhoneticEngine.encode. */
static void run_main(pbuilder *pb, const bmpm_ruleset *rs, const uint32_t *cp, int n, int nt, int max)
{
	int i = 0;
	if (rs == NULL || rs->rules == NULL) return;
	while (i < n) {
		int adv = 1;
		size_t r;
		for (r = 0; r < rs->count; r++) {
			const bmpm_rule *rule = &rs->rules[r];
			uint32_t pat[64];
			int plen;
			if (u8_first(rule->pattern) != cp[i]) continue;
			plen = u8_decode_buf(rule->pattern, pat, 64);
			if (i + plen > n) continue;
			if (!seqeq(cp + i, plen, pat, plen)) continue;
			if (!ctx_match(rule->rcontext, 1, cp + i + plen, n - i - plen)) continue;
			if (!ctx_match(rule->lcontext, 0, cp, i)) continue;
			pb_apply(pb, rule->phoneme, nt, max);
			adv = plen;
			break;
		}
		i += adv;
	}
}

/* Phoneme comparator from Rule.Phoneme.COMPARATOR: char-by-char, shorter wins
 * ties when it is a prefix of the longer. Phoneme text is ASCII. */
static int phon_cmp(const char *a, size_t an, const char *b, size_t bn)
{
	size_t i;
	for (i = 0; i < an; i++) {
		if (i >= bn) return 1;
		int c = (int) (unsigned char) a[i] - (int) (unsigned char) b[i];
		if (c != 0) return c;
	}
	if (an < bn) return -1;
	return 0;
}

/* Apply a final ruleset (common, then language-specific). Re-transcribes each
 * phoneme's text, then collapses duplicates by text while unioning language
 * sets, leaving the result ordered by the phoneme comparator. */
static void apply_final(pbuilder *pb, const bmpm_ruleset *rs, int nt, int max)
{
	pbuilder result;
	size_t pi;

	if (rs == NULL || rs->rules == NULL || rs->count == 0) {
		return; /* finalRules.isEmpty() -> unchanged */
	}

	pb_init(&result);

	for (pi = 0; pi < pb->n; pi++) {
		phon_t *P = &pb->a[pi];
		pbuilder sub;
		int tn, i;
		uint32_t *tcp;
		size_t si;

		pb_init(&sub);
		pb_seed(&sub, P->langs);
		tcp = u8_decode(P->t, P->tn, &tn);

		i = 0;
		while (i < tn) {
			int adv = 1, found = 0;
			size_t r;
			for (r = 0; r < rs->count; r++) {
				const bmpm_rule *rule = &rs->rules[r];
				uint32_t pat[64];
				int plen;
				if (u8_first(rule->pattern) != tcp[i]) continue;
				plen = u8_decode_buf(rule->pattern, pat, 64);
				if (i + plen > tn) continue;
				if (!seqeq(tcp + i, plen, pat, plen)) continue;
				if (!ctx_match(rule->rcontext, 1, tcp + i + plen, tn - i - plen)) continue;
				if (!ctx_match(rule->lcontext, 0, tcp, i)) continue;
				pb_apply(&sub, rule->phoneme, nt, max);
				found = 1;
				adv = plen;
				break;
			}
			if (!found) {
				char ch = (char) tcp[i];
				pb_append_all(&sub, &ch, 1);
			}
			i += adv;
		}
		efree(tcp);

		for (si = 0; si < sub.n; si++) {
			phon_t *np = &sub.a[si];
			size_t k;
			int merged = 0;
			for (k = 0; k < result.n; k++) {
				if (result.a[k].tn == np->tn && memcmp(result.a[k].t, np->t, np->tn) == 0) {
					result.a[k].langs = ls_merge(result.a[k].langs, np->langs);
					merged = 1;
					break;
				}
			}
			if (!merged) {
				size_t pos = 0;
				pb_reserve(&result, result.n + 1);
				while (pos < result.n &&
				       phon_cmp(result.a[pos].t, result.a[pos].tn, np->t, np->tn) < 0) {
					pos++;
				}
				memmove(&result.a[pos + 1], &result.a[pos],
				        (result.n - pos) * sizeof(phon_t));
				result.a[pos].t = estrndup(np->t, np->tn);
				result.a[pos].tn = np->tn;
				result.a[pos].langs = np->langs;
				result.n++;
			}
		}
		pb_free(&sub);
	}

	pb_free(pb);
	*pb = result;
}

static char *pb_makestring(pbuilder *pb, size_t *outlen)
{
	smart_str s = {0};
	size_t i;
	for (i = 0; i < pb->n; i++) {
		if (i) smart_str_appendc(&s, '|');
		smart_str_appendl(&s, pb->a[i].t, pb->a[i].tn);
	}
	smart_str_0(&s);
	if (s.s == NULL) {
		char *e = emalloc(1);
		e[0] = '\0';
		*outlen = 0;
		return e;
	}
	*outlen = ZSTR_LEN(s.s);
	char *r = estrndup(ZSTR_VAL(s.s), ZSTR_LEN(s.s));
	smart_str_free(&s);
	return r;
}

/* ---------------------------------------------------------------------- */
/* Language guessing                                                      */
/* ---------------------------------------------------------------------- */

static const bmpm_lang_set *find_lang_set(int nt)
{
	size_t i;
	for (i = 0; i < bmpm_lang_sets_count; i++) {
		if (bmpm_lang_sets[i].name_type == nt) return &bmpm_lang_sets[i];
	}
	return NULL;
}

static langset_t guess_languages(int nt, const char *input, size_t len)
{
	int n, i;
	uint32_t *cp = u8_decode(input, len, &n);
	const bmpm_lang_set *S = find_lang_set(nt);
	langset_t mask = ls_full(nt);
	size_t r;

	for (i = 0; i < n; i++) cp[i] = lc_cp(cp[i]);

	for (r = 0; r < S->count; r++) {
		uint32_t rb[256];
		int rn = u8_decode_buf(S->rules[r].pattern, rb, 256);
		if (regex_atom_match(rb, rn, cp, n)) {
			langset_t rm = parse_lang_list(nt, S->rules[r].languages);
			if (S->rules[r].accept) mask &= rm;
			else mask &= ~rm;
		}
	}
	efree(cp);
	return mask == 0 ? LS_ANY : mask;
}

/* ---------------------------------------------------------------------- */
/* Name-type prefix word lists                                            */
/* ---------------------------------------------------------------------- */

/* GENERIC prefixes in the HashSet iteration order observed from the reference
 * (OpenJDK); the prefix-recursion loop returns on the first startsWith match,
 * so order is observable for nested prefixes such as "de la". */
static const char *const gen_prefix_order[] = {
	"de", "van", "di", "dos", "del", "do", "dal", "della",
	"du", "des", "von", "dela", "de la", "da"
};
static const int gen_prefix_count = 14;

static const char *const ash_prefixes[] = { "bar", "ben", "da", "de", "van", "von" };
static const int ash_prefix_count = 6;

static const char *const sep_prefixes[] = {
	"al", "el", "da", "dal", "de", "del", "dela", "de la",
	"della", "des", "di", "do", "dos", "du", "van", "von"
};
static const int sep_prefix_count = 16;

static int cp_eq_ascii(const uint32_t *w, int wl, const char *s)
{
	int i;
	if ((int) strlen(s) != wl) return 0;
	for (i = 0; i < wl; i++) if (w[i] != (uint32_t) (unsigned char) s[i]) return 0;
	return 1;
}

static int is_prefix_word(int nt, const uint32_t *w, int wl)
{
	const char *const *list;
	int count, i;
	if (nt == BMPM_ASH) { list = ash_prefixes; count = ash_prefix_count; }
	else if (nt == BMPM_SEP) { list = sep_prefixes; count = sep_prefix_count; }
	else { list = gen_prefix_order; count = gen_prefix_count; }
	for (i = 0; i < count; i++) {
		if (cp_eq_ascii(w, wl, list[i])) return 1;
	}
	return 0;
}

static int is_ws(uint32_t c)
{
	return c == 0x20 || c == 0x09 || c == 0x0A || c == 0x0B || c == 0x0C || c == 0x0D;
}

/* ---------------------------------------------------------------------- */
/* Encoding                                                               */
/* ---------------------------------------------------------------------- */

static char *encode_guess(int nt, int rt, const char *input, size_t len, size_t *outlen, int depth);

static char *encode_core(int nt, int rt, langset_t ls, const char *input, size_t len, size_t *outlen, int depth)
{
	const char *lang = "any";
	const char *single;
	const bmpm_ruleset *rules_main, *final1, *final2;
	int n, i;
	uint32_t *cp;
	int tn;            /* tidied length */
	uint32_t *tcp;     /* tidied code points */

	if (ls_singleton_lang(nt, ls, &single)) lang = single;

	rules_main = find_ruleset(nt, BMPM_RULES, lang);
	final1 = find_ruleset(nt, rt, "common");
	final2 = find_ruleset(nt, rt, lang);

	/* tidy: lowercase, '-' -> space, trim (over code points) */
	cp = u8_decode(input, len, &n);
	for (i = 0; i < n; i++) {
		uint32_t c = lc_cp(cp[i]);
		if (c == '-') c = ' ';
		cp[i] = c;
	}
	{
		int a = 0, b = n;
		while (a < b && is_ws(cp[a])) a++;
		while (b > a && is_ws(cp[b - 1])) b--;
		tcp = cp + a;
		tn = b - a;
	}

	/* GENERIC d' and name-prefix recursion (depth-bounded; see BMPM_MAX_PREFIX_DEPTH) */
	if (nt == BMPM_GEN && depth < BMPM_MAX_PREFIX_DEPTH) {
		if (tn >= 2 && tcp[0] == 'd' && tcp[1] == 0x27) {
			size_t rl, cl, rr, cr;
			char *rem = u8_encode(tcp + 2, tn - 2, &rl);
			uint32_t *cbuf = safe_emalloc(tn, sizeof(uint32_t), 0);
			char *comb;
			char *renc, *cenc;
			smart_str s = {0};
			cbuf[0] = 'd';
			memcpy(cbuf + 1, tcp + 2, (size_t) (tn - 2) * sizeof(uint32_t));
			comb = u8_encode(cbuf, tn - 1, &cl);
			efree(cbuf);
			renc = encode_guess(nt, rt, rem, rl, &rr, depth + 1);
			cenc = encode_guess(nt, rt, comb, cl, &cr, depth + 1);
			smart_str_appendc(&s, '(');
			smart_str_appendl(&s, renc, rr);
			smart_str_appendl(&s, ")-(", 3);
			smart_str_appendl(&s, cenc, cr);
			smart_str_appendc(&s, ')');
			smart_str_0(&s);
			efree(rem); efree(comb); efree(renc); efree(cenc);
			*outlen = ZSTR_LEN(s.s);
			char *out = estrndup(ZSTR_VAL(s.s), ZSTR_LEN(s.s));
			smart_str_free(&s);
			efree(cp);
			return out;
		}
		{
			int pi;
			for (pi = 0; pi < gen_prefix_count; pi++) {
				const char *l = gen_prefix_order[pi];
				int ll = (int) strlen(l);
				int k, ok = 1;
				if (tn < ll + 1) continue;
				for (k = 0; k < ll; k++) {
					if (tcp[k] != (uint32_t) (unsigned char) l[k]) { ok = 0; break; }
				}
				if (!ok || tcp[ll] != ' ') continue;
				{
					size_t rl, cl, rr, cr;
					int remn = tn - (ll + 1);
					char *rem = u8_encode(tcp + ll + 1, remn, &rl);
					uint32_t *cbuf = safe_emalloc((size_t) (ll + remn), sizeof(uint32_t), 0);
					char *comb, *renc, *cenc;
					smart_str s = {0};
					for (k = 0; k < ll; k++) cbuf[k] = (uint32_t) (unsigned char) l[k];
					memcpy(cbuf + ll, tcp + ll + 1, (size_t) remn * sizeof(uint32_t));
					comb = u8_encode(cbuf, ll + remn, &cl);
					efree(cbuf);
					renc = encode_guess(nt, rt, rem, rl, &rr, depth + 1);
					cenc = encode_guess(nt, rt, comb, cl, &cr, depth + 1);
					smart_str_appendc(&s, '(');
					smart_str_appendl(&s, renc, rr);
					smart_str_appendl(&s, ")-(", 3);
					smart_str_appendl(&s, cenc, cr);
					smart_str_appendc(&s, ')');
					smart_str_0(&s);
					efree(rem); efree(comb); efree(renc); efree(cenc);
					*outlen = ZSTR_LEN(s.s);
					char *out = estrndup(ZSTR_VAL(s.s), ZSTR_LEN(s.s));
					smart_str_free(&s);
					efree(cp);
					return out;
				}
			}
		}
	}

	/* split tidied input into words, build words2 per name type */
	{
		struct { const uint32_t *p; int l; } words2[64];
		int nw2 = 0;
		struct { int s, l; } words[64];
		int nw = 0;

		if (tn == 0) {
			words[nw].s = 0; words[nw].l = 0; nw++;   /* "".split -> [""] */
		} else {
			int a = 0;
			while (a < tn) {
				int b = a;
				while (b < tn && !is_ws(tcp[b])) b++;
				if (b > a && nw < 64) { words[nw].s = a; words[nw].l = b - a; nw++; }
				a = b;
				while (a < tn && is_ws(tcp[a])) a++;
			}
			if (nw == 0) { words[nw].s = 0; words[nw].l = 0; nw++; }
		}

		if (nt == BMPM_SEP) {
			int wi;
			for (wi = 0; wi < nw; wi++) {
				const uint32_t *wp = tcp + words[wi].s;
				int wl = words[wi].l;
				int last = -1, j;
				for (j = 0; j < wl; j++) if (wp[j] == 0x27) last = j;
				const uint32_t *segp = wp + (last + 1);
				int segl = wl - (last + 1);
				if (!is_prefix_word(nt, segp, segl) && nw2 < 64) {
					words2[nw2].p = segp; words2[nw2].l = segl; nw2++;
				}
			}
		} else if (nt == BMPM_ASH) {
			int wi;
			for (wi = 0; wi < nw; wi++) {
				const uint32_t *wp = tcp + words[wi].s;
				int wl = words[wi].l;
				if (!is_prefix_word(nt, wp, wl) && nw2 < 64) {
					words2[nw2].p = wp; words2[nw2].l = wl; nw2++;
				}
			}
		} else {
			int wi;
			for (wi = 0; wi < nw; wi++) {
				if (nw2 < 64) {
					words2[nw2].p = tcp + words[wi].s;
					words2[nw2].l = words[wi].l;
					nw2++;
				}
			}
		}

		/* concat = true: join words2 with single spaces */
		{
			uint32_t *jn = safe_emalloc((size_t) tn + 64, sizeof(uint32_t), 0);
			int jl = 0, wi;
			for (wi = 0; wi < nw2; wi++) {
				int k;
				if (wi) jn[jl++] = ' ';
				for (k = 0; k < words2[wi].l; k++) jn[jl++] = words2[wi].p[k];
			}

			/* main + two final passes */
			{
				pbuilder pb;
				char *out;
				pb_init(&pb);
				pb_seed(&pb, ls);
				run_main(&pb, rules_main, jn, jl, nt, BMPM_MAX_PHONEMES);
				apply_final(&pb, final1, nt, BMPM_MAX_PHONEMES);
				apply_final(&pb, final2, nt, BMPM_MAX_PHONEMES);
				out = pb_makestring(&pb, outlen);
				pb_free(&pb);
				efree(jn);
				efree(cp);
				return out;
			}
		}
	}
}

static char *encode_guess(int nt, int rt, const char *input, size_t len, size_t *outlen, int depth)
{
	langset_t ls = guess_languages(nt, input, len);
	return encode_core(nt, rt, ls, input, len, outlen, depth);
}

/* ---------------------------------------------------------------------- */
/* PHP function                                                           */
/* ---------------------------------------------------------------------- */

PHP_FUNCTION(bmpm)
{
	zend_string *input;
	zend_long name_type = BMPM_GEN;
	zend_long accuracy = BMPM_APPROX;
	zend_string *language = NULL;
	char *result;
	size_t result_len = 0;

	ZEND_PARSE_PARAMETERS_START(1, 4)
		Z_PARAM_STR(input)
		Z_PARAM_OPTIONAL
		Z_PARAM_LONG(name_type)
		Z_PARAM_LONG(accuracy)
		Z_PARAM_STR(language)
	ZEND_PARSE_PARAMETERS_END();

	if (name_type != BMPM_GEN && name_type != BMPM_ASH && name_type != BMPM_SEP) {
		zend_argument_value_error(2, "must be one of BMPM_GENERIC, BMPM_ASHKENAZI, or BMPM_SEPHARDIC");
		RETURN_THROWS();
	}
	if (accuracy != BMPM_APPROX && accuracy != BMPM_EXACT) {
		zend_argument_value_error(3, "must be either BMPM_APPROX or BMPM_EXACT");
		RETURN_THROWS();
	}

	if (language != NULL && ZSTR_LEN(language) > 0) {
		int idx = lang_index((int) name_type, ZSTR_VAL(language), ZSTR_LEN(language));
		if (idx < 0) {
			zend_argument_value_error(4, "\"%s\" is not a known language for the given name type",
			                          ZSTR_VAL(language));
			RETURN_THROWS();
		}
		result = encode_core((int) name_type, (int) accuracy, (langset_t) (1u << idx),
		                     ZSTR_VAL(input), ZSTR_LEN(input), &result_len, 0);
	} else {
		result = encode_guess((int) name_type, (int) accuracy,
		                      ZSTR_VAL(input), ZSTR_LEN(input), &result_len, 0);
	}

	RETVAL_STRINGL(result, result_len);
	efree(result);
}
