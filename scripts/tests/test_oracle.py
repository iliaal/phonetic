#!/usr/bin/env python3
"""Check the UTF-8 oracle protocol; run oracle/fetch-codec.sh first."""

import hashlib
import os
from pathlib import Path
import subprocess
import unittest

ORACLE = Path(__file__).resolve().parents[1] / "oracle/Oracle.java"
JAR = Path(os.environ.get("CODEC_JAR", ORACLE.with_name("commons-codec-1.17.1.jar")))


class OracleTests(unittest.TestCase):
    def setUp(self):
        self.assertTrue(JAR.is_file(), "run scripts/oracle/fetch-codec.sh first")
        self.assertEqual(hashlib.sha256(JAR.read_bytes()).hexdigest(),
                         "f9f6cb103f2ddc3c99a9d80ada2ae7bf0685111fd6bffccb72033d1da4e6ff23")

    def run_oracle(self, args):
        return subprocess.run(
            ["java", "-cp", str(JAR), str(ORACLE), *args],
            input=b"Smith\n", capture_output=True, timeout=30)

    def test_documented_argument_counts(self):
        cases = [
            (["dm"], "SM0|XMT"),
            (["dm", "2"], "SM|XM"),
            (["bmpm", "gen", "approx"], "zmit"),
            (["bmpm", "gen", "approx", "english"], "zmit"),
            (["dmsoundex"], "463000"),
            (["nysiis", "strict"], "SNAT"),
            (["nysiis", "full"], "SNAT"),
            (["mra"], "SMTH"),
        ]
        for args, expected in cases:
            with self.subTest(args=args):
                result = self.run_oracle(args)
                self.assertEqual(result.returncode, 0, result.stderr)
                self.assertEqual(result.stderr, b"")
                self.assertEqual(result.stdout,
                                 ("Smith\t" + expected + os.linesep).encode("utf-8"))

    def test_extra_arguments_fail_before_emitting_vectors(self):
        for args in (["dm", "4"], ["bmpm", "gen", "approx", "english"],
                     ["dmsoundex"], ["nysiis", "strict"], ["mra"]):
            for extras in (["unexpected"], ["unexpected", "another"]):
                with self.subTest(args=args, extras=extras):
                    result = self.run_oracle(args + extras)
                    self.assertEqual(result.returncode, 2, result.stderr)
                    self.assertEqual(result.stdout, b"")
                    self.assertTrue(result.stderr.startswith(b"usage: java "), result.stderr)

    def test_missing_required_arguments_still_fail(self):
        for args in ([], ["bmpm"], ["bmpm", "gen"], ["nysiis"]):
            with self.subTest(args=args):
                result = self.run_oracle(args)
                self.assertEqual(result.returncode, 2, result.stderr)
                self.assertEqual(result.stdout, b"")
                self.assertTrue(result.stderr.startswith(b"usage: java "), result.stderr)

    def test_utf8_output_independent_of_default_charset(self):
        cases = [
            (["dm"], "KRK|KRK"),
            (["bmpm", "gen", "approx"], "garkia|garkio|gorkia|gorkio"),
            (["dmsoundex"], "594000|595000"),
            (["nysiis", "strict"], "GARCÍ"),
            (["mra"], "GRC"),
        ]
        for encoding in ("US-ASCII", "UTF-8"):
            for args, expected in cases:
                with self.subTest(encoding=encoding, mode=args[0]):
                    result = subprocess.run(
                        ["java", "-Dfile.encoding=" + encoding, "-cp", str(JAR),
                         str(ORACLE), *args], input="García\n".encode("utf-8"),
                        env=dict(os.environ, LC_ALL="C"), capture_output=True, timeout=30)
                    self.assertEqual(result.returncode, 0, result.stderr)
                    self.assertEqual(result.stdout, ("García\t" + expected + os.linesep).encode("utf-8"))


if __name__ == "__main__":
    unittest.main()
