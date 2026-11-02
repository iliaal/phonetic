#!/usr/bin/env python3
"""Run the downloader offline with real hashes/files and a controlled curl."""

import hashlib
import os
from pathlib import Path
import re
import subprocess
import sys
import tempfile
import time
import unittest

SOURCE = Path(__file__).resolve().parents[1] / "oracle/fetch-codec.sh"
JAR = "commons-codec-1.17.1.jar"
GOOD = b"valid fixture jar\n"


class DownloadTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name)
        self.script = self.root / "fetch-codec.sh"
        # Replace only the pinned artifact hash with our offline fixture hash.
        self.script.write_text(re.sub(r'SHA256="[0-9a-f]{64}"',
            f'SHA256="{hashlib.sha256(GOOD).hexdigest()}"', SOURCE.read_text()))
        curl = self.root / "curl"
        curl.write_text(f"#!{sys.executable}\n" + '''import os, pathlib, sys, time
target = pathlib.Path(sys.argv[sys.argv.index('-o') + 1])
mode = os.environ.get('DOWNLOAD_MODE', 'good')
# Keep curl's output descriptor open while the competing download publishes.
stream = target.open('wb')
if mode == 'wait-bad':
    pathlib.Path(os.environ['READY']).touch()
    deadline = time.monotonic() + 10
    while not pathlib.Path(os.environ['RELEASE']).exists():
        if time.monotonic() > deadline:
            sys.exit(99)
        time.sleep(.01)
stream.write(b'valid fixture jar\\n' if mode == 'good' else b'bad')
stream.close()
sys.exit(22 if mode == 'fail' else 0)
''')
        curl.chmod(0o755)
        self.env = dict(os.environ, PATH=str(self.root) + os.pathsep + os.environ['PATH'])

    def run_download(self, mode="good"):
        return subprocess.run(['bash', str(self.script)],
            env=dict(self.env, DOWNLOAD_MODE=mode), capture_output=True, timeout=15)

    def assert_clean(self):
        self.assertEqual(list(self.root.glob(JAR + '.tmp.*')), [])

    def test_preserves_unrelated_fixed_temporary(self):
        unrelated = self.root / (JAR + '.tmp')
        unrelated.write_bytes(b'unrelated')
        self.assertEqual(self.run_download().returncode, 0)
        self.assertEqual(unrelated.read_bytes(), b'unrelated')
        self.assertEqual((self.root / JAR).read_bytes(), GOOD)
        self.assert_clean()

    def test_curl_failure_cleans_partial_download(self):
        self.assertNotEqual(self.run_download('fail').returncode, 0)
        self.assertFalse((self.root / JAR).exists())
        self.assertEqual(list(self.root.glob(JAR + '.tmp*')), [])

    def test_checksum_failure_preserves_existing_cache(self):
        cache = self.root / JAR
        cache.write_bytes(b'old cache')
        self.assertNotEqual(self.run_download('bad').returncode, 0)
        self.assertEqual(cache.read_bytes(), b'old cache')
        self.assert_clean()

    def test_download_respects_umask(self):
        result = subprocess.run(['bash', '-c', 'umask 027; bash "$1"',
            'test', str(self.script)], env=self.env, capture_output=True, timeout=15)
        self.assertEqual(result.returncode, 0)
        self.assertEqual((self.root / JAR).stat().st_mode & 0o777, 0o640)

    def test_valid_cache_skips_curl(self):
        (self.root / JAR).write_bytes(GOOD)
        self.assertEqual(self.run_download('fail').returncode, 0)
        self.assertEqual((self.root / JAR).read_bytes(), GOOD)
        self.assert_clean()

    def test_failed_concurrent_download_preserves_success(self):
        ready, release = self.root / 'ready', self.root / 'release'
        first = subprocess.Popen(['bash', str(self.script)], stdout=subprocess.PIPE,
            stderr=subprocess.PIPE, env=dict(self.env, DOWNLOAD_MODE='wait-bad',
                READY=str(ready), RELEASE=str(release)))
        try:
            deadline = time.monotonic() + 10
            while not ready.exists():
                self.assertIsNone(first.poll(), 'first download exited before barrier')
                self.assertLess(time.monotonic(), deadline, 'download barrier timed out')
                time.sleep(.01)
            self.assertEqual(self.run_download().returncode, 0)
            release.touch()
            first.communicate(timeout=15)
            self.assertNotEqual(first.returncode, 0)
            self.assertEqual((self.root / JAR).read_bytes(), GOOD)
            self.assert_clean()
        finally:
            if first.poll() is None:
                first.kill()
                first.communicate()


if __name__ == '__main__':
    unittest.main()
