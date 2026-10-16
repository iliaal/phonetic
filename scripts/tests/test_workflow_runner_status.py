#!/usr/bin/env python3
"""Execute actual workflow result guards with controlled runner output/status.

Only the run-tests invocation and subsequent result guard are executed: no
build, dependency installation, release, or upload steps. All workflow files
are scanned so the release mirrors remain covered without running a release.
"""

import argparse
import os
from pathlib import Path
import re
import subprocess
import tempfile

ROOT = Path(__file__).resolve().parents[2]


def guards():
    found = []
    for path in sorted((ROOT / ".github/workflows").glob("*.yml")):
        text = path.read_text()
        # These workflows use ten-space-indented literal shell run blocks.
        # Stop at the first nonempty dedented line, including a new step/job.
        blocks = re.findall(r"^        run: \|\n((?:          .*\n|\n)+)", text, re.M)
        expected = text.count("PASSED=$(num passed)")
        selected = [block for block in blocks if "PASSED=$(num passed)" in block]
        assert len(selected) == expected, f"unrecognized result guard in {path.name}"
        for index, block in enumerate(selected):
            lines = [line[10:] for line in block.splitlines()]
            start = next(i for i, line in enumerate(lines)
                         if line.startswith("RESULT=")
                         or line.startswith("/tmp/php-asan/bin/php run-tests.php"))
            # Include status initialization when present; this is production
            # code too, and dropping it would hide uninitialized-state bugs.
            if start and lines[start - 1] == "TEST_STATUS=0":
                start -= 1
            # Require diagnostics in every Tests lane, independent of the
            # implementation. Release guards retain their existing assertions.
            found.append((f"{path.name} guard {index + 1}", "\n".join(lines[start:]),
                          path.name == "tests.yml"))
    assert found, "no workflow result guards found"
    return found


def summary(passed=90, failed=0, skipped=0, borked=0, leaked=0):
    return "".join(f"Tests {key} {value}\n" for key, value in locals().items())


def check(label, body, output, status, accepted, directory, diagnostics=False, stderr=""):
    # This sole path substitution isolates the ASAN log for concurrent tests;
    # the runner command, status expression, parser, and guard stay unchanged.
    body = body.replace("/tmp/test-output.txt", str(Path(directory) / "output.txt"))
    script = '''set -euo pipefail
function php() {
    printf '%s' "$RUNNER_OUTPUT"
    printf '%s' "$RUNNER_STDERR" >&2
    return "$RUNNER_STATUS"
}
function /tmp/php-asan/bin/php() { php; }
phpbin=php
runnerwin=run-tests.php
''' + body
    env = dict(os.environ, RUNNER_OUTPUT=output, RUNNER_STDERR=stderr,
               RUNNER_STATUS=str(status))
    result = subprocess.run(["bash", "-c", script], env=env,
                            capture_output=True, text=True, timeout=10)
    assert (result.returncode == 0) == accepted, (
        f"{label}: runner status {status}, expected accepted={accepted}, "
        f"guard status {result.returncode}\n{result.stdout}{result.stderr}"
    )
    if "TEST_STATUS=0" in body and status:
        assert result.returncode == status, f"{label}: runner status not preserved"
        assert output in result.stdout, f"{label}: runner diagnostics lost"
    if diagnostics:
        if status:
            assert result.returncode == status, f"{label}: runner status not preserved"
        # Command substitution removes terminal newlines; check the captured
        # content appears once without requiring their original count.
        for stream, content in (("stdout", output), ("stderr", stderr)):
            content = content.rstrip("\n")
            if content:
                assert result.stdout.count(content) == 1, (
                    f"{label}: runner {stream} lost or printed more than once"
                )


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--repeat", type=int, default=1)
    args = parser.parse_args()
    if args.repeat < 1:
        parser.error("--repeat must be positive")
    cases = [(summary(), code, code == 0) for code in (0, 1, 7, 139)]
    cases += [(summary(**{field: 1}), 0, False)
              for field in ("failed", "skipped", "borked", "leaked")]
    cases += [(summary(passed=0), 0, False), ("", 0, False), ("", 7, False)]
    cases += [("\n" + summary() + "\n", 0, True),
              (summary().replace("\n", "\n\n"), 0, True)]
    # Keep the original fixtures, including truly empty output, unchanged.
    cases = [(*case, "") for case in cases]
    cases += [(summary() + "runner stdout diagnostic\n", code, code == 0,
               "runner stderr diagnostic\n") for code in (0, 1, 7, 139)]
    selected = guards()
    with tempfile.TemporaryDirectory(prefix="phonetic-runner-guard-") as directory:
        for label, body, diagnostics in selected:
            for _ in range(args.repeat):
                for output, status, accepted, stderr in cases:
                    check(label, body, output, status, accepted, directory, diagnostics, stderr)
            print(f"{label}: {len(cases)} cases x {args.repeat} passed", flush=True)
    print(f"{len(selected)} workflow guards passed")


if __name__ == "__main__":
    main()
