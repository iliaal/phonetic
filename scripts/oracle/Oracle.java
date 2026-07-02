import java.io.BufferedReader;
import java.io.InputStreamReader;
import java.nio.charset.StandardCharsets;
import java.util.Collections;
import java.util.Locale;

import org.apache.commons.codec.EncoderException;
import org.apache.commons.codec.language.DaitchMokotoffSoundex;
import org.apache.commons.codec.language.DoubleMetaphone;
import org.apache.commons.codec.language.MatchRatingApproachEncoder;
import org.apache.commons.codec.language.Nysiis;
import org.apache.commons.codec.language.bm.Languages;
import org.apache.commons.codec.language.bm.NameType;
import org.apache.commons.codec.language.bm.PhoneticEngine;
import org.apache.commons.codec.language.bm.RuleType;

/**
 * Parity oracle driver over Apache Commons Codec.
 *
 * Usage: java -cp commons-codec-1.17.1.jar Oracle.java <mode> [options]
 * Reads one input per line from stdin, writes "input<TAB>output" TSV to stdout.
 *
 * Modes:
 *   dm [maxlen]                          Double Metaphone, output "primary|alternate"
 *   bmpm <gen|ash|sep> <approx|exact> [language]
 *                                        Beider-Morse (concat=true), optional forced language
 *   dmsoundex                            Daitch-Mokotoff Soundex (branching, '|'-joined)
 *   nysiis <strict|full>                 NYSIIS
 *   mra                                  Match Rating Approach codex
 */
public final class Oracle {

    private interface Encoder {
        String encode(String input) throws EncoderException;
    }

    public static void main(String[] args) throws Exception {
        if (args.length < 1) {
            usage();
        }
        final Encoder enc = buildEncoder(args);

        final BufferedReader in =
            new BufferedReader(new InputStreamReader(System.in, StandardCharsets.UTF_8));
        String line;
        while ((line = in.readLine()) != null) {
            String out;
            try {
                out = enc.encode(line);
            } catch (EncoderException | IllegalArgumentException e) {
                out = "!ERROR:" + e.getMessage();
            }
            System.out.println(line + "\t" + out);
        }
    }

    private static Encoder buildEncoder(String[] args) {
        final String mode = args[0].toLowerCase(Locale.ROOT);
        switch (mode) {
            case "dm": {
                final DoubleMetaphone dm = new DoubleMetaphone();
                if (args.length > 1) {
                    dm.setMaxCodeLen(Integer.parseInt(args[1]));
                }
                return s -> dm.doubleMetaphone(s, false) + "|" + dm.doubleMetaphone(s, true);
            }
            case "bmpm": {
                if (args.length < 3) {
                    usage();
                }
                final NameType nameType = nameType(args[1]);
                final RuleType ruleType = ruleType(args[2]);
                final PhoneticEngine engine = new PhoneticEngine(nameType, ruleType, true);
                if (args.length > 3) {
                    final Languages.LanguageSet langs =
                        Languages.LanguageSet.from(Collections.singleton(args[3]));
                    return s -> engine.encode(s, langs);
                }
                return engine::encode;
            }
            case "dmsoundex": {
                final DaitchMokotoffSoundex dms = new DaitchMokotoffSoundex();
                return dms::soundex;
            }
            case "nysiis": {
                if (args.length < 2) {
                    usage();
                }
                final boolean strict;
                if ("strict".equals(args[1])) {
                    strict = true;
                } else if ("full".equals(args[1])) {
                    strict = false;
                } else {
                    usage();
                    throw new AssertionError();
                }
                final Nysiis nysiis = new Nysiis(strict);
                return nysiis::encode;
            }
            case "mra": {
                final MatchRatingApproachEncoder mra = new MatchRatingApproachEncoder();
                return mra::encode;
            }
            default:
                usage();
                throw new AssertionError();
        }
    }

    private static NameType nameType(String s) {
        switch (s.toLowerCase(Locale.ROOT)) {
            case "gen": return NameType.GENERIC;
            case "ash": return NameType.ASHKENAZI;
            case "sep": return NameType.SEPHARDIC;
            default:
                usage();
                throw new AssertionError();
        }
    }

    private static RuleType ruleType(String s) {
        switch (s.toLowerCase(Locale.ROOT)) {
            case "approx": return RuleType.APPROX;
            case "exact":  return RuleType.EXACT;
            default:
                usage();
                throw new AssertionError();
        }
    }

    private static void usage() {
        System.err.println("usage: java -cp commons-codec-1.17.1.jar Oracle.java <mode> [options]");
        System.err.println("  dm [maxlen]");
        System.err.println("  bmpm <gen|ash|sep> <approx|exact> [language]");
        System.err.println("  dmsoundex");
        System.err.println("  nysiis <strict|full>");
        System.err.println("  mra");
        System.exit(2);
    }
}
