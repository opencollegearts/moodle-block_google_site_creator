#!/usr/bin/env bash
# Build a Marketplace-safe plugin ZIP (no .git, no __MACOSX, no nested zips).
# Usage:
#   ./scripts/build-release-zip.sh [output.zip]
#
# Prefer: commit your changes, then run this so the ZIP matches a git revision.
# If the working tree is dirty, the current tree is archived instead of HEAD.

set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

if ! git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  echo "Error: not a git repository. Initialise git or use a clean checkout." >&2
  exit 1
fi

# Parse version.php without bootstrapping Moodle.
RELEASE="$(sed -n "s/.*\\\$plugin->release[[:space:]]*=[[:space:]]*'\\([^']*\\)'.*/\\1/p" version.php | head -1)"
VERSION="$(sed -n "s/.*\\\$plugin->version[[:space:]]*=[[:space:]]*\\([0-9][0-9]*\\).*/\\1/p" version.php | head -1)"
if [[ -z "${RELEASE}" ]]; then
  RELEASE="dev"
fi
if [[ -z "${VERSION}" ]]; then
  VERSION="$(date +%Y%m%d)"
fi

# Default beside the repo (outside the plugin tree). Fall back to /tmp if needed.
DEFAULT_OUT="$(dirname "$ROOT")/block_google_site_creator-${RELEASE}-${VERSION}.zip"
OUT="${1:-$DEFAULT_OUT}"

# Never write the ZIP inside the plugin directory (avoids nested packaging).
OUT_DIR="$(cd "$(dirname "$OUT")" 2>/dev/null && pwd || true)"
if [[ -n "$OUT_DIR" && "$OUT_DIR" == "$ROOT" ]]; then
  echo "Error: output ZIP must not be written inside the plugin directory." >&2
  echo "Suggested path: $DEFAULT_OUT" >&2
  exit 1
fi
if [[ -n "$OUT_DIR" && "$OUT_DIR"/ == "$ROOT"/* ]]; then
  echo "Error: output ZIP must not be written inside the plugin directory." >&2
  echo "Suggested path: $DEFAULT_OUT" >&2
  exit 1
fi

mkdir -p "$(dirname "$OUT")"
rm -f "$OUT"

if git diff --quiet && git diff --cached --quiet; then
  echo "Archiving HEAD (clean working tree)..."
  git archive --format=zip --prefix=google_site_creator/ -o "$OUT" HEAD
else
  echo "Warning: working tree has uncommitted changes; archiving current tree." >&2
  TMPINDEX="$(mktemp)"
  cleanup() { rm -f "$TMPINDEX"; }
  trap cleanup EXIT
  export GIT_INDEX_FILE="$TMPINDEX"
  git read-tree HEAD
  git add -A
  TREE="$(git write-tree)"
  git archive --format=zip --prefix=google_site_creator/ -o "$OUT" "$TREE"
fi

echo "Created: $OUT"
echo "Entry count: $(zipinfo -1 "$OUT" | wc -l | tr -d ' ')"
echo "Top-level paths:"
zipinfo -1 "$OUT" | awk -F/ '{print $1}' | sort -u
echo
echo "Sanity checks:"
if zipinfo -1 "$OUT" | grep -E '(^|/)(__MACOSX/|\.git/|\.DS_Store$|\.zip$)' >/dev/null; then
  echo "FAIL: ZIP contains .git, __MACOSX, .DS_Store, or nested .zip entries." >&2
  exit 1
fi
echo "OK: no .git, __MACOSX, .DS_Store, or nested .zip entries."
