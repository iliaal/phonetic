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
    printf '%s  %s\n' "$SHA256" "$1" | sha256sum -c --status
}

if [[ -f "$JAR" ]] && verify "$JAR"; then
    echo "$JAR already present, SHA-256 OK"
    exit 0
fi

echo "Downloading $URL"
TMP=$(mktemp "${JAR}.tmp.XXXXXX")
trap 'rm -f -- "$TMP"' EXIT
# Match curl's usual new-file permissions while respecting the caller's umask.
chmod =rw "$TMP"
curl -fsSL -o "$TMP" "$URL"

if ! verify "$TMP"; then
    echo "SHA-256 mismatch for $JAR" >&2
    echo "expected: $SHA256" >&2
    echo "actual:   $(sha256sum "$TMP" | cut -d' ' -f1)" >&2
    exit 1
fi

mv -- "$TMP" "$JAR"
trap - EXIT
echo "$JAR downloaded, SHA-256 OK"
