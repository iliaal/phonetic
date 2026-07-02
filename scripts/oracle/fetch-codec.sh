#!/usr/bin/env bash
# Fetch the Apache Commons Codec jar used as the parity oracle.
# Idempotent: skips the download when the jar is present and its hash matches.
set -euo pipefail

VERSION="1.17.1"
JAR="commons-codec-${VERSION}.jar"
URL="https://repo1.maven.org/maven2/commons-codec/commons-codec/${VERSION}/${JAR}"
SHA256="f9f6cb103f2ddc3c99a9d80ada2ae7bf0685111fd6bffccb72033d1da4e6ff23"

cd "$(dirname "${BASH_SOURCE[0]}")"

verify() {
    printf '%s  %s\n' "$SHA256" "$JAR" | sha256sum -c --status
}

if [[ -f "$JAR" ]] && verify; then
    echo "$JAR already present, SHA-256 OK"
    exit 0
fi

echo "Downloading $URL"
curl -fsSL -o "${JAR}.tmp" "$URL"
mv "${JAR}.tmp" "$JAR"

if ! verify; then
    echo "SHA-256 mismatch for $JAR" >&2
    echo "expected: $SHA256" >&2
    echo "actual:   $(sha256sum "$JAR" | cut -d' ' -f1)" >&2
    rm -f "$JAR"
    exit 1
fi

echo "$JAR downloaded, SHA-256 OK"
