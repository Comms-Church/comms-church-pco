#!/usr/bin/env bash
#
# Deploy a new plugin version from a downloaded zip.
#
# Usage:
#   ./deploy.sh                    # uses newest comms-church-pco*.zip in ~/Downloads
#   ./deploy.sh path/to/plugin.zip # uses a specific zip
#
# What it does:
#   1. Extracts the zip and syncs its files into this repo
#   2. Reads the version from the plugin header
#   3. Commits, tags v<version>, and pushes
#   4. GitHub Actions builds the release zip and publishes the release
set -euo pipefail

REPO_DIR="$(cd "$(dirname "$0")" && pwd)"
PLUGIN_FILE="comms-church-pco.php"

# --- 1. Locate the zip -------------------------------------------------------
if [ $# -ge 1 ]; then
    ZIP="$1"
else
    ZIP="$(ls -t "$HOME/Downloads"/comms-church-pco*.zip 2>/dev/null | head -1 || true)"
fi

if [ -z "${ZIP:-}" ] || [ ! -f "$ZIP" ]; then
    echo "Error: no zip found. Pass a path or put comms-church-pco*.zip in ~/Downloads." >&2
    exit 1
fi
echo "==> Using zip: $ZIP"

# --- 2. Extract --------------------------------------------------------------
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT
unzip -q "$ZIP" -d "$TMP"

# Find the folder containing the main plugin file (zip may or may not have a root folder)
SRC="$(dirname "$(find "$TMP" -name "$PLUGIN_FILE" -maxdepth 3 | head -1)")"
if [ ! -f "$SRC/$PLUGIN_FILE" ]; then
    echo "Error: $PLUGIN_FILE not found inside the zip." >&2
    exit 1
fi

# --- 3. Version checks -------------------------------------------------------
VERSION="$(grep -m1 "Version:" "$SRC/$PLUGIN_FILE" | sed -E 's/.*Version:[[:space:]]*//' | tr -d '[:space:]')"
CONST_VERSION="$(grep -m1 "CCPCO_VERSION" "$SRC/$PLUGIN_FILE" | sed -E "s/.*'([0-9.]+)'.*/\1/")"

if [ -z "$VERSION" ]; then
    echo "Error: could not read Version from plugin header." >&2
    exit 1
fi
if [ "$VERSION" != "$CONST_VERSION" ]; then
    echo "Error: header Version ($VERSION) != CCPCO_VERSION constant ($CONST_VERSION). Fix the zip first." >&2
    exit 1
fi

cd "$REPO_DIR"
git fetch --tags --quiet origin
if git rev-parse "v$VERSION" >/dev/null 2>&1; then
    echo "Error: tag v$VERSION already exists. Bump the version in the plugin before deploying." >&2
    exit 1
fi
if [ -n "$(git status --porcelain)" ]; then
    echo "Error: repo has uncommitted changes. Commit or discard them first." >&2
    git status --short
    exit 1
fi
echo "==> New version: $VERSION"

# --- 4. Sync files into the repo --------------------------------------------
# Repo-managed files (.git, workflows, this script) are protected from the zip.
rsync -a --delete \
    --exclude='.git/' \
    --exclude='.github/' \
    --exclude='.gitattributes' \
    --exclude='deploy.sh' \
    --exclude='.DS_Store' \
    "$SRC/" "$REPO_DIR/"

if [ -z "$(git status --porcelain)" ]; then
    echo "Nothing changed — repo already matches the zip." >&2
    exit 1
fi

echo "==> Changes:"
git status --short

# --- 5. Commit, tag, push ----------------------------------------------------
git add -A
git commit -m "v$VERSION"
git tag "v$VERSION"
git push origin main "v$VERSION"

echo ""
echo "==> Pushed v$VERSION. GitHub Actions is building the release:"
echo "    https://github.com/Comms-Church/comms-church-pco/actions"
echo "    Release will appear at: https://github.com/Comms-Church/comms-church-pco/releases/tag/v$VERSION"
