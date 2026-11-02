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
    def test_utf8_output_independent_of_default_charset(self):
        self.assertTrue(JAR.is_file(), "run scripts/oracle/fetch-codec.sh first")
        self.assertEqual(hashlib.sha256(JAR.read_bytes()).hexdigest(),
                         "f9f6cb103f2ddc3c99a9d80ada2ae7bf0685111fd6bffccb72033d1da4e6ff23")
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
