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

#include <limits.h>
#include <stdint.h>
#include <string.h>

#ifdef _MSC_VER
#include <intrin.h>
#endif

#include "php.h"
#include "zend_smart_str.h"
#include "php_phonetic.h"
#include "phonetic_utf8.h"
#include "bmpm_data.h"

#define BMPM_MAX_PHONEMES 20

/* The GENERIC d'/name-prefix handling recurses (bm_encode_core -> bm_encode_sub
 * -> bm_encode_core), peeling one code point per level. Real names nest at most
 * one or two prefixes; crafted input ("d" followed by thousands of apostrophes)
 * would otherwise drive O(input-length) native recursion and smash the C stack.
 * Past this depth, stop treating a leading prefix specially and encode inline. */
#define BMPM_MAX_PREFIX_DEPTH 16

typedef uint32_t langset_t;
#define LS_NONE 0u
#define LS_ANY  0xFFFFFFFFu

/* ---------------------------------------------------------------------- */
/* Case folding (UTF-8 <-> code points lives in phonetic_utf8.h)          */
/* ---------------------------------------------------------------------- */

/* Lower-case a single code point, mirroring Java's
 * String.toLowerCase(Locale.ENGLISH) for the ranges the rule data and
 * realistic names use: the shared Latin map plus Cyrillic. Greek is
 * intentionally not handled: its context-sensitive final-sigma needs
 * positional logic this point-wise map can't express, so raw Greek-script
 * input remains a known limitation. */
static uint32_t bm_lc_cp(uint32_t c)
{
	if (c >= 0x0410 && c <= 0x042F) return c + 0x20;   /* \u0410-\u042F -> \u0430-\u044F */
	if (c >= 0x0400 && c <= 0x040F) return c + 0x50;   /* incl. YO (U+0401) */
	if (c >= 0x0460 && c <= 0x0481) return (c & 1u) ? c : c + 1;
	if (c >= 0x048A && c <= 0x04BF) return (c & 1u) ? c : c + 1;
	if (c == 0x04C0) return 0x04CF;
	if (c >= 0x04C1 && c <= 0x04CE) return (c & 1u) ? c + 1 : c;
	if (c >= 0x04D0 && c <= 0x052F) return (c & 1u) ? c : c + 1;
	return ph_lc_latin(c);
}

/* ---------------------------------------------------------------------- */
/* Language sets                                                          */
/* ---------------------------------------------------------------------- */

static const bmpm_language_list *bm_nt_langs(int nt)
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
	const bmpm_language_list *l = bm_nt_langs(nt);
	return (langset_t) ((1u << l->count) - 1u);
}

static int bm_lang_index(int nt, const char *name, size_t len)
{
	const bmpm_language_list *l = bm_nt_langs(nt);
	size_t i;
	for (i = 0; i < l->count; i++) {
		if (strlen(l->languages[i]) == len && memcmp(l->languages[i], name, len) == 0) {
			return (int) i;
		}
	}
	return -1;
}

/* Parse a '+'-separated language list into a bitmask. */
static langset_t bm_parse_lang_list(int nt, const char *s)
{
	langset_t mask = 0;
	const char *p = s;
	while (*p) {
		const char *start = p;
		while (*p && *p != '+') p++;
		int idx = bm_lang_index(nt, start, (size_t) (p - start));
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
	*name = bm_nt_langs(nt)->languages[idx];
	return 1;
}

/* ---------------------------------------------------------------------- */
/* Restricted-regex matcher (mirrors Rule.pattern() in Commons Codec)     */
/* ---------------------------------------------------------------------- */

static int bm_classmember(const uint32_t *cls, int cn, uint32_t c)
{
	int i;
	for (i = 0; i < cn; i++) {
		if (cls[i] == c) return 1;
	}
	return 0;
}

static int bm_seqeq(const uint32_t *a, int an, const uint32_t *b, int bn)
{
	int i;
	if (an != bn) return 0;
	for (i = 0; i < an; i++) if (a[i] != b[i]) return 0;
	return 1;
}

static int bm_prefixeq(const uint32_t *seg, int sn, const uint32_t *p, int pn)
{
	int i;
	if (pn > sn) return 0;
	for (i = 0; i < pn; i++) if (seg[i] != p[i]) return 0;
	return 1;
}

static int bm_suffixeq(const uint32_t *seg, int sn, const uint32_t *p, int pn)
{
	int i;
	if (pn > sn) return 0;
	for (i = 0; i < pn; i++) if (seg[sn - pn + i] != p[i]) return 0;
	return 1;
}

static int bm_substr_find(const uint32_t *seg, int sn, const uint32_t *p, int pn)
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

static int bm_atom_match(const atom_t *a, uint32_t c)
{
	if (a->is_class) {
		int m = bm_classmember(a->cls, a->cn, c);
		return a->negate ? !m : m;
	}
	return c == a->lit;
}

static int bm_atoms_match_at(const uint32_t *seg, int sn, const atom_t *atoms, int na, int p)
{
	int k;
	for (k = 0; k < na; k++) {
		if (p + k >= sn) return 0;
		if (!bm_atom_match(&atoms[k], seg[p + k])) return 0;
	}
	return 1;
}

/* Parse the restricted regex grammar (leading "^", trailing "$", literal and
 * character-class atoms) into an atom array. Class atoms' cls pointers index
 * into R, so R must outlive the parsed atoms. Returns the atom count. */
static int bm_parse_regex(const uint32_t *R, int Rn, atom_t *atoms, int cap, int *as_out, int *ae_out)
{
	int as = 0, ae = 0, pos = 0, end = Rn;
	int na = 0;

	if (Rn > 0 && R[0] == '^') { as = 1; pos = 1; }
	if (end > pos && R[end - 1] == '$') { ae = 1; end--; }

	while (pos < end && na < cap) {
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

	*as_out = as;
	*ae_out = ae;
	return na;
}

/* Match a parsed atom sequence against seg, honouring the start/end anchors. */
static int bm_atoms_find(int as, int ae, const atom_t *atoms, int na, const uint32_t *seg, int sn)
{
	if (as && ae) {
		if (sn != na) return 0;
		return bm_atoms_match_at(seg, sn, atoms, na, 0);
	}
	if (as) {
		if (sn < na) return 0;
		return bm_atoms_match_at(seg, sn, atoms, na, 0);
	}
	if (ae) {
		if (sn < na) return 0;
		return bm_atoms_match_at(seg, sn, atoms, na, sn - na);
	}
	{
		int p;
		if (na == 0) return 1;
		for (p = 0; p + na <= sn; p++) {
			if (bm_atoms_match_at(seg, sn, atoms, na, p)) return 1;
		}
		return 0;
	}
}

/* General "find" matcher over the restricted regex grammar (anchors plus
 * literal / character-class atoms). Drives the rare rule-context fall-through
 * cases; the language-guessing patterns use the pre-parsed form. */
static int bm_regex_atom_match(const uint32_t *R, int Rn, const uint32_t *seg, int sn)
{
	atom_t atoms[64];
	int as, ae;
	int na = bm_parse_regex(R, Rn, atoms, 64, &as, &ae);
	return bm_atoms_find(as, ae, atoms, na, seg, sn);
}

/* Build the anchored, decoded context regex for a rule context at MINIT.
 * side 0 = left (raw + "$"), side 1 = right ("^" + raw). The run-time matcher
 * bm_ctx_match_pre consumes the result, so the per-match u8_decode + R-assembly is
 * paid once at module load instead of on every candidate rule. */
static uint32_t *bm_build_ctx_R(const char *raw, int side, int *Rn_out)
{
	uint32_t rb[128];
	int rn = ph_u8_decode_buf(raw, rb, 128);
	int Rn = 0, i;
	uint32_t *R = pemalloc((size_t) (rn + 1) * sizeof(uint32_t), 1);

	if (side == 1) {
		R[Rn++] = '^';
		for (i = 0; i < rn; i++) R[Rn++] = rb[i];
	} else {
		for (i = 0; i < rn; i++) R[Rn++] = rb[i];
		R[Rn++] = '$';
	}
	*Rn_out = Rn;
	return R;
}

/* Match a pre-decoded rule context (from bm_build_ctx_R) against seg. Reproduces
 * Commons Codec's pattern() dispatch byte for byte, including its literal
 * treatment of "." and the fall-through to the general matcher for compound
 * contexts. */
static int bm_ctx_match_pre(const uint32_t *R, int Rn, const uint32_t *seg, int sn)
{
	int i;
	int sw = Rn > 0 && R[0] == '^';
	int ew = Rn > 0 && R[Rn - 1] == '$';
	const uint32_t *content = R + (sw ? 1 : 0);
	int cn = Rn - (sw ? 1 : 0) - (ew ? 1 : 0);
	int boxes = 0;
	for (i = 0; i < cn; i++) if (content[i] == '[') { boxes = 1; break; }

	if (!boxes) {
		if (sw && ew) {
			if (cn == 0) return sn == 0;
			return bm_seqeq(seg, sn, content, cn);
		}
		if ((sw || ew) && cn == 0) return 1;
		if (sw) return bm_prefixeq(seg, sn, content, cn);
		if (ew) return bm_suffixeq(seg, sn, content, cn);
		return bm_substr_find(seg, sn, content, cn);
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
				if (sw && ew) return sn == 1 && (bm_classmember(cls, clsn, seg[0]) == should);
				if (sw) return sn > 0 && (bm_classmember(cls, clsn, seg[0]) == should);
				if (ew) return sn > 0 && (bm_classmember(cls, clsn, seg[sn - 1]) == should);
			}
		}
		return bm_regex_atom_match(R, Rn, seg, sn);
	}
}

/* ---------------------------------------------------------------------- */
/* Phoneme builder                                                        */
/* ---------------------------------------------------------------------- */

/* Phoneme text is small-string optimized: when tn <= PHON_INL the bytes live
 * in inl[] and t is NULL, so most phonemes avoid a heap allocation. t is never
 * a self-pointer, so realloc/memmove of a phon_t array stay valid; read text
 * through PHON_T(). Invariant: t != NULL  <=>  tn > PHON_INL. */
#define PHON_INL 23
typedef struct {
	char     *t;
	size_t    tn;
	langset_t langs;
	char      inl[PHON_INL + 1];
} phon_t;
#define PHON_T(p) ((p)->t ? (p)->t : (p)->inl)

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
	phon_t *p;
	size_t tot = ln + rn;
	char *dst;

	pb_reserve(pb, pb->n + 1);
	p = &pb->a[pb->n];
	if (tot <= PHON_INL) {
		p->t = NULL;
		dst = p->inl;
	} else {
		p->t = emalloc(tot + 1);
		dst = p->t;
	}
	if (ln) memcpy(dst, lt, ln);
	if (rn) memcpy(dst + ln, rt, rn);
	dst[tot] = '\0';
	p->tn = tot;
	p->langs = langs;
	pb->n++;
}

static void pb_free(pbuilder *pb)
{
	size_t i;
	for (i = 0; i < pb->n; i++) if (pb->a[i].t) efree(pb->a[i].t);
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
		size_t tot = p->tn + sn;
		if (tot <= PHON_INL) {
			/* src is inline here (heap text already exceeds PHON_INL) */
			memcpy(p->inl + p->tn, s, sn);
			p->inl[tot] = '\0';
		} else if (p->t) {
			/* grow in place; the allocator's size classes amortize repeated
			 * single-byte appends without an explicit capacity field */
			p->t = erealloc(p->t, tot + 1);
			memcpy(p->t + p->tn, s, sn);
			p->t[tot] = '\0';
		} else {
			char *nt = emalloc(tot + 1);
			if (p->tn) memcpy(nt, p->inl, p->tn);
			memcpy(nt + p->tn, s, sn);
			nt[tot] = '\0';
			p->t = nt;
		}
		p->tn = tot;
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
static int bm_parse_phoneme_expr(const char *raw, int nt, alt_t *alts, int cap)
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
				alts[na].langs = bm_parse_lang_list(nt, lb);
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
					alts[na].langs = bm_parse_lang_list(nt, lb);
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
			alts[na].langs = bm_parse_lang_list(nt, lb);
		} else {
			alts[na].t = raw; alts[na].tn = l; alts[na].langs = LS_ANY;
		}
		na++;
	}
	return na;
}

/* Cartesian product of the current phonemes with a rule's pre-parsed
 * alternatives (from bm_build_ruleset_index), pruning language-incompatible
 * combinations and capping at maxPhonemes with a mid-product break, exactly
 * as PhonemeBuilder.apply does. */
static void pb_apply(pbuilder *pb, const alt_t *alts, int na, int max)
{
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
				pb_push(&out, PHON_T(&pb->a[li]), pb->a[li].tn, alts[ai].t, (size_t) alts[ai].tn, ls);
				if ((int) out.n >= max) { done = 1; break; }
			}
		}
	}
	pb_free(pb);
	*pb = out;
}

/* ---------------------------------------------------------------------- */
/* Rule lookup + MINIT-built first-code-point dispatch index               */
/* ---------------------------------------------------------------------- */

/* Per-rule pre-decoded pattern (code points + length), pointing into the
 * ruleset's flat arena. Built once at MINIT, read-only thereafter. */
typedef struct {
	const uint32_t *pat;
	int             plen;
	uint32_t       *lctx;    /* decoded lcontext + '$' (side 0), built at MINIT */
	int             lctx_n;
	uint32_t       *rctx;    /* decoded '^' + rcontext (side 1) */
	int             rctx_n;
	alt_t          *alts;    /* pre-parsed phoneme alternatives (4th column) */
	int             nalts;
} rule_decoded;

/* Per-ruleset dispatch index. Rules are bucketed by their pattern's first code
 * point; within a bucket the original rule order is preserved so first-match-
 * wins and longest-match semantics are unchanged. order[] is the flat list of
 * rule indices grouped by bucket; bk_cp[] is the sorted distinct first code
 * points with parallel bk_off[]/bk_cnt[] slices into order[]. */
typedef struct {
	rule_decoded *decoded;   /* [count], parallel to rs->rules */
	uint32_t     *arena;     /* flat decoded-pattern storage */
	int          *order;     /* [count], rule indices grouped by bucket */
	uint32_t     *bk_cp;     /* [nbk], sorted distinct first code points */
	int          *bk_off;    /* [nbk] */
	int          *bk_cnt;    /* [nbk] */
	int           nbk;
	size_t        count;
} ruleset_index;

static ruleset_index *g_rs_index;   /* [bmpm_rulesets_count] */

static int bm_find_ruleset_idx(int nt, int rt, const char *lang)
{
	size_t i;
	for (i = 0; i < bmpm_rulesets_count; i++) {
		const bmpm_ruleset *r = &bmpm_rulesets[i];
		if (r->name_type == nt && r->rule_type == rt && strcmp(r->language, lang) == 0) {
			return (int) i;
		}
	}
	return -1;
}

/* Locate the bucket for code point c; returns its [off,cnt) slice into order[]. */
static int rs_dispatch(const ruleset_index *ix, uint32_t c, int *off, int *cnt)
{
	int lo = 0, hi = ix->nbk - 1;
	while (lo <= hi) {
		int mid = (lo + hi) >> 1;
		uint32_t v = ix->bk_cp[mid];
		if (v == c) { *off = ix->bk_off[mid]; *cnt = ix->bk_cnt[mid]; return 1; }
		if (v < c) lo = mid + 1;
		else hi = mid - 1;
	}
	return 0;
}

/* Walk the transliteration rules once across cp[0..n), mutating the builder.
 * Unmatched positions are dropped (no literal append), matching the main pass
 * in PhoneticEngine.encode. */
static void bm_run_main(pbuilder *pb, const bmpm_ruleset *rs, const ruleset_index *ix,
                     const uint32_t *cp, int n, int max)
{
	int i = 0;
	if (rs == NULL || rs->rules == NULL) return;
	while (i < n) {
		int adv = 1, off = 0, cnt = 0;
		if (rs_dispatch(ix, cp[i], &off, &cnt)) {
			int t;
			for (t = 0; t < cnt; t++) {
				int r = ix->order[off + t];
				const uint32_t *pat = ix->decoded[r].pat;
				int plen = ix->decoded[r].plen;
				if (plen <= 0) continue;   /* defensive: an empty pattern would match with adv=0 and loop forever; the generator forbids it */
				if (i + plen > n) continue;
				if (!bm_seqeq(cp + i, plen, pat, plen)) continue;
				if (!bm_ctx_match_pre(ix->decoded[r].rctx, ix->decoded[r].rctx_n, cp + i + plen, n - i - plen)) continue;
				if (!bm_ctx_match_pre(ix->decoded[r].lctx, ix->decoded[r].lctx_n, cp, i)) continue;
				pb_apply(pb, ix->decoded[r].alts, ix->decoded[r].nalts, max);
				adv = plen;
				break;
			}
		}
		i += adv;
	}
}

/* Phoneme comparator from Rule.Phoneme.COMPARATOR: char-by-char, shorter wins
 * ties when it is a prefix of the longer. Phoneme text is ASCII. */
static int bm_phon_cmp(const char *a, size_t an, const char *b, size_t bn)
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
static void bm_apply_final(pbuilder *pb, const bmpm_ruleset *rs, const ruleset_index *ix,
                        int max)
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
		uint32_t stk[512];
		uint32_t *tcp;
		size_t si;

		pb_init(&sub);
		pb_seed(&sub, P->langs);

		/* Phoneme text is pure ASCII (rule phonemes are ASCII, and the
		 * unmatched-character fallback below only re-appends such bytes), so
		 * byte == code point: widen in place instead of UTF-8-decoding, on the
		 * stack for every realistic length. */
		tcp = P->tn <= 512 ? stk : safe_emalloc(P->tn, sizeof(uint32_t), 0);
		tn = (int) P->tn;
		{
			const char *pt = PHON_T(P);
			for (i = 0; i < tn; i++) tcp[i] = (unsigned char) pt[i];
		}

		i = 0;
		while (i < tn) {
			int adv = 1, found = 0, off = 0, cnt = 0;
			if (rs_dispatch(ix, tcp[i], &off, &cnt)) {
				int t;
				for (t = 0; t < cnt; t++) {
					int r = ix->order[off + t];
					const uint32_t *pat = ix->decoded[r].pat;
					int plen = ix->decoded[r].plen;
					if (plen <= 0) continue;   /* defensive: an empty pattern would match with adv=0 and loop forever; the generator forbids it */
					if (i + plen > tn) continue;
					if (!bm_seqeq(tcp + i, plen, pat, plen)) continue;
					if (!bm_ctx_match_pre(ix->decoded[r].rctx, ix->decoded[r].rctx_n, tcp + i + plen, tn - i - plen)) continue;
					if (!bm_ctx_match_pre(ix->decoded[r].lctx, ix->decoded[r].lctx_n, tcp, i)) continue;
					pb_apply(&sub, ix->decoded[r].alts, ix->decoded[r].nalts, max);
					found = 1;
					adv = plen;
					break;
				}
			}
			if (!found) {
				char ch = (char) tcp[i];
				pb_append_all(&sub, &ch, 1);
			}
			i += adv;
		}
		if (tcp != stk) efree(tcp);

		for (si = 0; si < sub.n; si++) {
			phon_t *np = &sub.a[si];
			size_t k;
			int merged = 0;
			for (k = 0; k < result.n; k++) {
				if (result.a[k].tn == np->tn && memcmp(PHON_T(&result.a[k]), PHON_T(np), np->tn) == 0) {
					result.a[k].langs = ls_merge(result.a[k].langs, np->langs);
					merged = 1;
					break;
				}
			}
			if (!merged) {
				size_t pos = 0;
				pb_reserve(&result, result.n + 1);
				while (pos < result.n &&
				       bm_phon_cmp(PHON_T(&result.a[pos]), result.a[pos].tn, PHON_T(np), np->tn) < 0) {
					pos++;
				}
				memmove(&result.a[pos + 1], &result.a[pos],
				        (result.n - pos) * sizeof(phon_t));
				/* Move the whole phoneme (heap pointer or inline bytes) into the
				 * result, then null the source's heap pointer so pb_free skips
				 * it; inline entries carry no pointer and need no cleanup. */
				result.a[pos] = *np;
				np->t = NULL;
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
		smart_str_appendl(&s, PHON_T(&pb->a[i]), pb->a[i].tn);
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
/* Language guessing + MINIT-built pre-parsed guess rules                  */
/* ---------------------------------------------------------------------- */

/* One language-guessing rule, decoded and regex-parsed once at MINIT. The
 * atoms' class pointers index into R, and the accept mask is pre-resolved
 * against the name type's language list. Read-only after MINIT. */
typedef struct {
	uint32_t *R;
	int       Rn;
	int       as, ae;
	atom_t   *atoms;
	int       na;
	langset_t mask;
	int       accept;
	uint32_t *req_lit;   /* literal atoms the rule needs present to match */
	int       req_n;
} lang_parsed;

typedef struct {
	lang_parsed *rules;
	size_t       count;
} langset_index;

static langset_index *g_lang_index;   /* [bmpm_lang_sets_count] */

static int bm_find_lang_slot(int nt)
{
	size_t i;
	for (i = 0; i < bmpm_lang_sets_count; i++) {
		if (bmpm_lang_sets[i].name_type == nt) return (int) i;
	}
	return 0;
}

/* Guess the language set from already-lowercased code points. The caller
 * decodes and lowercases once (bm_encode_string), so the default path pays a
 * single UTF-8 decode instead of one per phase. */
static langset_t bm_guess_languages(int nt, const uint32_t *cp, int n)
{
	int i;
	const langset_index *li = &g_lang_index[bm_find_lang_slot(nt)];
	langset_t mask = ls_full(nt);
	uint64_t present[2] = { 0, 0 };   /* which ASCII code points the input contains */
	size_t r;

	for (i = 0; i < n; i++) {
		uint32_t c = cp[i];
		if (c < 128) present[c >> 6] |= (uint64_t) 1 << (c & 63);
	}

	for (r = 0; r < li->count; r++) {
		const lang_parsed *lp = &li->rules[r];
		int q, skip = 0;
		for (q = 0; q < lp->req_n; q++) {
			uint32_t L = lp->req_lit[q];
			if (L < 128 && !(present[L >> 6] & ((uint64_t) 1 << (L & 63)))) { skip = 1; break; }
		}
		if (skip) continue;
		if (bm_atoms_find(lp->as, lp->ae, lp->atoms, lp->na, cp, n)) {
			if (lp->accept) mask &= lp->mask;
			else mask &= ~lp->mask;
		}
	}
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

static int bm_cp_eq_ascii(const uint32_t *w, int wl, const char *s)
{
	int i;
	if ((int) strlen(s) != wl) return 0;
	for (i = 0; i < wl; i++) if (w[i] != (uint32_t) (unsigned char) s[i]) return 0;
	return 1;
}

static int bm_is_prefix_word(int nt, const uint32_t *w, int wl)
{
	const char *const *list;
	int count, i;
	if (nt == BMPM_ASH) { list = ash_prefixes; count = ash_prefix_count; }
	else if (nt == BMPM_SEP) { list = sep_prefixes; count = sep_prefix_count; }
	else { list = gen_prefix_order; count = gen_prefix_count; }
	for (i = 0; i < count; i++) {
		if (bm_cp_eq_ascii(w, wl, list[i])) return 1;
	}
	return 0;
}

static int bm_is_ws(uint32_t c)
{
	return c == 0x20 || c == 0x09 || c == 0x0A || c == 0x0B || c == 0x0C || c == 0x0D;
}

/* ---------------------------------------------------------------------- */
/* Encoding                                                               */
/* ---------------------------------------------------------------------- */

/* Encode already-lowercased, '-'-mapped code points. `forced` is the langset
 * a caller pinned via the $language argument (LS_NONE when guessing); it is
 * threaded through the prefix recursion so a forced language applies to the
 * split variants too. Commons Codec re-guesses inside its prefix branch even
 * under a forced LanguageSet — a documented, deliberate divergence: the PHP
 * API validates and advertises the language as forced, so it stays forced. */
static char *bm_encode_sub(int nt, int rt, langset_t forced, const uint32_t *cp, int n, size_t *outlen, int depth);

static char *bm_encode_core(int nt, int rt, langset_t ls, langset_t forced,
                         const uint32_t *cp, int n, size_t *outlen, int depth)
{
	const char *lang = "any";
	const char *single;
	const bmpm_ruleset *rules_main, *final1, *final2;
	const ruleset_index *ix_main, *ix_f1, *ix_f2;
	int i_main, i_f1, i_f2;
	int tn;                  /* trimmed length */
	const uint32_t *tcp;     /* trimmed code points */

	if (ls_singleton_lang(nt, ls, &single)) lang = single;

	i_main = bm_find_ruleset_idx(nt, BMPM_RULES, lang);
	i_f1 = bm_find_ruleset_idx(nt, rt, "common");
	i_f2 = bm_find_ruleset_idx(nt, rt, lang);
	rules_main = i_main < 0 ? NULL : &bmpm_rulesets[i_main];
	final1 = i_f1 < 0 ? NULL : &bmpm_rulesets[i_f1];
	final2 = i_f2 < 0 ? NULL : &bmpm_rulesets[i_f2];
	ix_main = i_main < 0 ? NULL : &g_rs_index[i_main];
	ix_f1 = i_f1 < 0 ? NULL : &g_rs_index[i_f1];
	ix_f2 = i_f2 < 0 ? NULL : &g_rs_index[i_f2];

	/* trim: the reference's String.trim() strips every code point <= U+0020
	 * (controls included), not just the \s whitespace used for word splits */
	{
		int a = 0, b = n;
		while (a < b && cp[a] <= 0x20) a++;
		while (b > a && cp[b - 1] <= 0x20) b--;
		tcp = cp + a;
		tn = b - a;
	}

	/* GENERIC d' and name-prefix recursion (depth-bounded; see BMPM_MAX_PREFIX_DEPTH) */
	if (nt == BMPM_GEN && depth < BMPM_MAX_PREFIX_DEPTH) {
		if (tn >= 2 && tcp[0] == 'd' && tcp[1] == 0x27) {
			size_t rr, cr;
			uint32_t *cbuf = safe_emalloc(tn, sizeof(uint32_t), 0);
			char *renc, *cenc;
			smart_str s = {0};
			cbuf[0] = 'd';
			memcpy(cbuf + 1, tcp + 2, (size_t) (tn - 2) * sizeof(uint32_t));
			renc = bm_encode_sub(nt, rt, forced, tcp + 2, tn - 2, &rr, depth + 1);
			cenc = bm_encode_sub(nt, rt, forced, cbuf, tn - 1, &cr, depth + 1);
			efree(cbuf);
			smart_str_appendc(&s, '(');
			smart_str_appendl(&s, renc, rr);
			smart_str_appendl(&s, ")-(", 3);
			smart_str_appendl(&s, cenc, cr);
			smart_str_appendc(&s, ')');
			smart_str_0(&s);
			efree(renc); efree(cenc);
			*outlen = ZSTR_LEN(s.s);
			char *out = estrndup(ZSTR_VAL(s.s), ZSTR_LEN(s.s));
			smart_str_free(&s);
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
					size_t rr, cr;
					int remn = tn - (ll + 1);
					uint32_t *cbuf = safe_emalloc((size_t) (ll + remn), sizeof(uint32_t), 0);
					char *renc, *cenc;
					smart_str s = {0};
					for (k = 0; k < ll; k++) cbuf[k] = (uint32_t) (unsigned char) l[k];
					memcpy(cbuf + ll, tcp + ll + 1, (size_t) remn * sizeof(uint32_t));
					renc = bm_encode_sub(nt, rt, forced, tcp + ll + 1, remn, &rr, depth + 1);
					cenc = bm_encode_sub(nt, rt, forced, cbuf, ll + remn, &cr, depth + 1);
					efree(cbuf);
					smart_str_appendc(&s, '(');
					smart_str_appendl(&s, renc, rr);
					smart_str_appendl(&s, ")-(", 3);
					smart_str_appendl(&s, cenc, cr);
					smart_str_appendc(&s, ')');
					smart_str_0(&s);
					efree(renc); efree(cenc);
					*outlen = ZSTR_LEN(s.s);
					char *out = estrndup(ZSTR_VAL(s.s), ZSTR_LEN(s.s));
					smart_str_free(&s);
					return out;
				}
			}
		}
	}

	/* split tidied input into words, build words2 per name type. Word count is
	 * bounded by tn (each word is at least one code point), so tn+1 entries
	 * cover the worst case plus the empty-input "".split -> [""] sentinel.
	 * Commons Codec imposes no word cap, so these grow with the input. */
	{
		size_t wcap = (size_t) tn + 1;
		struct { const uint32_t *p; int l; } *words2 =
			safe_emalloc(wcap, sizeof(*words2), 0);
		int nw2 = 0;
		struct { int s, l; } *words =
			safe_emalloc(wcap, sizeof(*words), 0);
		int nw = 0;

		if (tn == 0) {
			words[nw].s = 0; words[nw].l = 0; nw++;   /* "".split -> [""] */
		} else {
			int a = 0;
			while (a < tn) {
				int b = a;
				while (b < tn && !bm_is_ws(tcp[b])) b++;
				if (b > a) { words[nw].s = a; words[nw].l = b - a; nw++; }
				a = b;
				while (a < tn && bm_is_ws(tcp[a])) a++;
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
				if (!bm_is_prefix_word(nt, segp, segl)) {
					words2[nw2].p = segp; words2[nw2].l = segl; nw2++;
				}
			}
		} else if (nt == BMPM_ASH) {
			int wi;
			for (wi = 0; wi < nw; wi++) {
				const uint32_t *wp = tcp + words[wi].s;
				int wl = words[wi].l;
				if (!bm_is_prefix_word(nt, wp, wl)) {
					words2[nw2].p = wp; words2[nw2].l = wl; nw2++;
				}
			}
		} else {
			int wi;
			for (wi = 0; wi < nw; wi++) {
				words2[nw2].p = tcp + words[wi].s;
				words2[nw2].l = words[wi].l;
				nw2++;
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
				bm_run_main(&pb, rules_main, ix_main, jn, jl, BMPM_MAX_PHONEMES);
				bm_apply_final(&pb, final1, ix_f1, BMPM_MAX_PHONEMES);
				bm_apply_final(&pb, final2, ix_f2, BMPM_MAX_PHONEMES);
				out = pb_makestring(&pb, outlen);
				pb_free(&pb);
				efree(jn);
				efree(words);
				efree(words2);
				return out;
			}
		}
	}
}

/* Recursion entry: code points are already lowercased and '-'-mapped. A
 * forced language stays forced; otherwise each split variant is re-guessed
 * (the sub-name's letters may imply a different language than the whole). */
static char *bm_encode_sub(int nt, int rt, langset_t forced, const uint32_t *cp, int n, size_t *outlen, int depth)
{
	langset_t ls = forced != LS_NONE ? forced : bm_guess_languages(nt, cp, n);
	return bm_encode_core(nt, rt, ls, forced, cp, n, outlen, depth);
}

/* String entry: decode and lowercase exactly once. Language guessing sees the
 * lowercased but untidied text (hyphens intact, untrimmed), like the
 * reference's Lang.guessLanguages; the '-' -> ' ' tidy happens after. */
static char *bm_encode_string(int nt, int rt, langset_t forced, const char *input, size_t len, size_t *outlen)
{
	int n, i;
	uint32_t *cp = ph_u8_decode(input, len, &n);
	langset_t ls;
	char *out;

	for (i = 0; i < n; i++) cp[i] = bm_lc_cp(cp[i]);
	ls = forced != LS_NONE ? forced : bm_guess_languages(nt, cp, n);
	for (i = 0; i < n; i++) if (cp[i] == '-') cp[i] = ' ';
	out = bm_encode_core(nt, rt, ls, forced, cp, n, outlen, 0);
	efree(cp);
	return out;
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

	/* The code-point decoders index with signed int; refuse an input whose byte
	 * length would overflow them before u8_decode's counter wraps. Unreachable
	 * under any sane memory_limit (needs a ~2GB string). */
	if (ZSTR_LEN(input) > (size_t) INT_MAX) {
		zend_argument_value_error(1, "is too long");
		RETURN_THROWS();
	}

	if (language != NULL && ZSTR_LEN(language) > 0) {
		int idx = bm_lang_index((int) name_type, ZSTR_VAL(language), ZSTR_LEN(language));
		if (idx < 0) {
			zend_argument_value_error(4, "\"%s\" is not a known language for the given name type",
			                          ZSTR_VAL(language));
			RETURN_THROWS();
		}
		result = bm_encode_string((int) name_type, (int) accuracy, (langset_t) (1u << idx),
		                       ZSTR_VAL(input), ZSTR_LEN(input), &result_len);
	} else {
		result = bm_encode_string((int) name_type, (int) accuracy, LS_NONE,
		                       ZSTR_VAL(input), ZSTR_LEN(input), &result_len);
	}

	RETVAL_STRINGL(result, result_len);
	efree(result);
}

/* Token separators in an encoded set: '|' between alternatives, and the
 * '('  ')'  '-' group syntax the prefix branch emits ("(A)-(B)"). No rule
 * phoneme contains any of these, so splitting on all four decomposes a
 * grouped result into its constituent tokens without false merges. */
static zend_always_inline int bmpm_tok_sep(char c)
{
	return c == '|' || c == '(' || c == ')' || c == '-';
}

/* Do the two phoneme sets share a token? Empty sets never match, so two
 * unencodable inputs are not treated as homophones. */
static zend_bool bmpm_tokens_intersect(const char *a, size_t al, const char *b, size_t bl)
{
	size_t i = 0;

	if (al == 0 || bl == 0) {
		return 0;
	}
	while (i < al) {
		size_t j = i;
		size_t tlen;
		while (j < al && !bmpm_tok_sep(a[j])) {
			j++;
		}
		tlen = j - i;
		if (tlen > 0) {
			size_t p = 0;
			while (p < bl) {
				size_t q = p;
				while (q < bl && !bmpm_tok_sep(b[q])) {
					q++;
				}
				if (q - p == tlen && memcmp(a + i, b + p, tlen) == 0) {
					return 1;
				}
				p = q + 1;
			}
		}
		i = j + 1;
	}
	return 0;
}

PHP_FUNCTION(bmpm_match)
{
	zend_string *a, *b;
	zend_long name_type = BMPM_GEN;
	zend_long accuracy = BMPM_APPROX;
	zend_string *language = NULL;
	char *ra, *rb;
	size_t ral = 0, rbl = 0;
	int lang_idx = -1;
	zend_bool matched;

	ZEND_PARSE_PARAMETERS_START(2, 5)
		Z_PARAM_STR(a)
		Z_PARAM_STR(b)
		Z_PARAM_OPTIONAL
		Z_PARAM_LONG(name_type)
		Z_PARAM_LONG(accuracy)
		Z_PARAM_STR(language)
	ZEND_PARSE_PARAMETERS_END();

	if (name_type != BMPM_GEN && name_type != BMPM_ASH && name_type != BMPM_SEP) {
		zend_argument_value_error(3, "must be one of BMPM_GENERIC, BMPM_ASHKENAZI, or BMPM_SEPHARDIC");
		RETURN_THROWS();
	}
	if (accuracy != BMPM_APPROX && accuracy != BMPM_EXACT) {
		zend_argument_value_error(4, "must be either BMPM_APPROX or BMPM_EXACT");
		RETURN_THROWS();
	}
	if (ZSTR_LEN(a) > (size_t) INT_MAX) {
		zend_argument_value_error(1, "is too long");
		RETURN_THROWS();
	}
	if (ZSTR_LEN(b) > (size_t) INT_MAX) {
		zend_argument_value_error(2, "is too long");
		RETURN_THROWS();
	}
	if (language != NULL && ZSTR_LEN(language) > 0) {
		lang_idx = bm_lang_index((int) name_type, ZSTR_VAL(language), ZSTR_LEN(language));
		if (lang_idx < 0) {
			zend_argument_value_error(5, "\"%s\" is not a known language for the given name type",
			                          ZSTR_VAL(language));
			RETURN_THROWS();
		}
	}

	{
		langset_t forced = lang_idx >= 0 ? (langset_t) (1u << lang_idx) : LS_NONE;
		ra = bm_encode_string((int) name_type, (int) accuracy, forced, ZSTR_VAL(a), ZSTR_LEN(a), &ral);
		rb = bm_encode_string((int) name_type, (int) accuracy, forced, ZSTR_VAL(b), ZSTR_LEN(b), &rbl);
	}

	matched = bmpm_tokens_intersect(ra, ral, rb, rbl);

	efree(ra);
	efree(rb);

	RETURN_BOOL(matched);
}

/* ---------------------------------------------------------------------- */
/* MINIT / MSHUTDOWN: build and free the read-only dispatch indices        */
/* ---------------------------------------------------------------------- */

static void bm_build_ruleset_index(const bmpm_ruleset *rs, ruleset_index *ix)
{
	size_t i;
	size_t total = 0, pos = 0;
	uint32_t *firsts;
	int nf = 0, k, op = 0;

	ix->decoded = NULL; ix->arena = NULL; ix->order = NULL;
	ix->bk_cp = NULL; ix->bk_off = NULL; ix->bk_cnt = NULL;
	ix->nbk = 0; ix->count = rs->count;

	if (rs->rules == NULL || rs->count == 0) return;

	ix->decoded = pemalloc(rs->count * sizeof(rule_decoded), 1);
	for (i = 0; i < rs->count; i++) {
		uint32_t b[64];
		ix->decoded[i].plen = ph_u8_decode_buf(rs->rules[i].pattern, b, 64);
		total += (size_t) ix->decoded[i].plen;
	}
	ix->arena = pemalloc((total ? total : 1) * sizeof(uint32_t), 1);
	for (i = 0; i < rs->count; i++) {
		int pl = ix->decoded[i].plen;
		alt_t tmp_alts[64];
		int na;
		ph_u8_decode_buf(rs->rules[i].pattern, ix->arena + pos, pl);
		ix->decoded[i].pat = ix->arena + pos;
		pos += (size_t) pl;
		ix->decoded[i].lctx = bm_build_ctx_R(rs->rules[i].lcontext, 0, &ix->decoded[i].lctx_n);
		ix->decoded[i].rctx = bm_build_ctx_R(rs->rules[i].rcontext, 1, &ix->decoded[i].rctx_n);
		/* pre-parse the phoneme column (alt texts point into the static rule
		 * data), so pb_apply never re-parses it at match time */
		na = bm_parse_phoneme_expr(rs->rules[i].phoneme, rs->name_type, tmp_alts, 64);
		ix->decoded[i].alts = pemalloc((na ? (size_t) na : 1) * sizeof(alt_t), 1);
		if (na) memcpy(ix->decoded[i].alts, tmp_alts, (size_t) na * sizeof(alt_t));
		ix->decoded[i].nalts = na;
	}

	/* distinct first code points, sorted ascending; empty patterns can never
	 * match (bm_run_main/bm_apply_final skip them), so keep them out of the buckets
	 * rather than dereference their zero-length arena slice */
	firsts = pemalloc(rs->count * sizeof(uint32_t), 1);
	for (i = 0; i < rs->count; i++) {
		uint32_t c;
		int seen = 0, j;
		if (ix->decoded[i].plen <= 0) continue;
		c = ix->decoded[i].pat[0];
		for (j = 0; j < nf; j++) if (firsts[j] == c) { seen = 1; break; }
		if (!seen) firsts[nf++] = c;
	}
	for (k = 1; k < nf; k++) {            /* insertion sort */
		uint32_t key = firsts[k];
		int j = k - 1;
		while (j >= 0 && firsts[j] > key) { firsts[j + 1] = firsts[j]; j--; }
		firsts[j + 1] = key;
	}

	ix->nbk = nf;
	ix->order = pemalloc(rs->count * sizeof(int), 1);
	ix->bk_cp = pemalloc((nf ? (size_t) nf : 1) * sizeof(uint32_t), 1);
	ix->bk_off = pemalloc((nf ? (size_t) nf : 1) * sizeof(int), 1);
	ix->bk_cnt = pemalloc((nf ? (size_t) nf : 1) * sizeof(int), 1);
	for (k = 0; k < nf; k++) {
		uint32_t c = firsts[k];
		int cnt = 0;
		ix->bk_cp[k] = c;
		ix->bk_off[k] = op;
		for (i = 0; i < rs->count; i++) {    /* original order within bucket */
			if (ix->decoded[i].plen > 0 && ix->decoded[i].pat[0] == c) { ix->order[op++] = (int) i; cnt++; }
		}
		ix->bk_cnt[k] = cnt;
	}
	pefree(firsts, 1);
}

static void bm_build_lang_index(const bmpm_lang_set *S, int nt, langset_index *li)
{
	size_t r;
	li->count = S->count;
	li->rules = pemalloc((S->count ? S->count : 1) * sizeof(lang_parsed), 1);
	for (r = 0; r < S->count; r++) {
		lang_parsed *lp = &li->rules[r];
		uint32_t buf[512];
		atom_t tmp[128];
		int dn = ph_u8_decode_buf(S->rules[r].pattern, buf, 512);
		int as, ae, na;
		lp->R = pemalloc((dn ? (size_t) dn : 1) * sizeof(uint32_t), 1);
		if (dn) memcpy(lp->R, buf, (size_t) dn * sizeof(uint32_t));
		lp->Rn = dn;
		na = bm_parse_regex(lp->R, dn, tmp, 128, &as, &ae);
		lp->as = as; lp->ae = ae; lp->na = na;
		lp->atoms = pemalloc((na ? (size_t) na : 1) * sizeof(atom_t), 1);
		if (na) memcpy(lp->atoms, tmp, (size_t) na * sizeof(atom_t));
		lp->mask = bm_parse_lang_list(nt, S->rules[r].languages);
		lp->accept = S->rules[r].accept;

		/* Literal atoms must all appear in the input for any match, so
		 * bm_guess_languages can skip the substring scan when one is absent. */
		{
			int q, rc = 0;
			for (q = 0; q < na; q++) if (!tmp[q].is_class) rc++;
			lp->req_lit = pemalloc((rc ? (size_t) rc : 1) * sizeof(uint32_t), 1);
			lp->req_n = rc;
			rc = 0;
			for (q = 0; q < na; q++) if (!tmp[q].is_class) lp->req_lit[rc++] = tmp[q].lit;
		}
	}
}

void bmpm_minit(void)
{
	size_t i;
	g_rs_index = pemalloc(bmpm_rulesets_count * sizeof(ruleset_index), 1);
	for (i = 0; i < bmpm_rulesets_count; i++) {
		bm_build_ruleset_index(&bmpm_rulesets[i], &g_rs_index[i]);
	}
	g_lang_index = pemalloc(bmpm_lang_sets_count * sizeof(langset_index), 1);
	for (i = 0; i < bmpm_lang_sets_count; i++) {
		bm_build_lang_index(&bmpm_lang_sets[i], bmpm_lang_sets[i].name_type, &g_lang_index[i]);
	}
}

void bmpm_mshutdown(void)
{
	size_t i, r;
	if (g_rs_index) {
		for (i = 0; i < bmpm_rulesets_count; i++) {
			ruleset_index *ix = &g_rs_index[i];
			if (ix->decoded) {
				size_t d;
				for (d = 0; d < ix->count; d++) {
					if (ix->decoded[d].lctx) pefree(ix->decoded[d].lctx, 1);
					if (ix->decoded[d].rctx) pefree(ix->decoded[d].rctx, 1);
					if (ix->decoded[d].alts) pefree(ix->decoded[d].alts, 1);
				}
				pefree(ix->decoded, 1);
			}
			if (ix->arena) pefree(ix->arena, 1);
			if (ix->order) pefree(ix->order, 1);
			if (ix->bk_cp) pefree(ix->bk_cp, 1);
			if (ix->bk_off) pefree(ix->bk_off, 1);
			if (ix->bk_cnt) pefree(ix->bk_cnt, 1);
		}
		pefree(g_rs_index, 1);
		g_rs_index = NULL;
	}
	if (g_lang_index) {
		for (i = 0; i < bmpm_lang_sets_count; i++) {
			langset_index *li = &g_lang_index[i];
			for (r = 0; r < li->count; r++) {
				if (li->rules[r].R) pefree(li->rules[r].R, 1);
				if (li->rules[r].atoms) pefree(li->rules[r].atoms, 1);
				if (li->rules[r].req_lit) pefree(li->rules[r].req_lit, 1);
			}
			if (li->rules) pefree(li->rules, 1);
		}
		pefree(g_lang_index, 1);
		g_lang_index = NULL;
	}
}
