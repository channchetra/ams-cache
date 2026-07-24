#!/usr/bin/env bash
#
# Build a WordPress-ready AMS Cache release zip with version management.
# Bash port of bin/build-release.ps1 for Ubuntu/Linux/macOS/Git-Bash.
#
# Examples:
#   bash bin/build-release.sh
#   bash bin/build-release.sh --version 3.0.0 --keep-stage
#   bash bin/build-release.sh --bump patch
#   bash bin/build-release.sh --bump minor --set-version

set -euo pipefail

PLUGIN_SLUG='ams-cache'
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
MAIN_FILE="$ROOT/cache-master.php"

VERSION=''
BUMP=''
SET_VERSION=0
OUT_DIR='dist'
INCLUDE_TESTS=0
KEEP_STAGE=0
SKIP_BUN_BUILD=0

usage() {
    sed -n '3,12p' "${BASH_SOURCE[0]}"
    exit 0
}

while [[ $# -gt 0 ]]; do
    case "$1" in
        --version)        VERSION="$2"; shift 2 ;;
        --bump)           BUMP="$2"; shift 2 ;;
        --set-version)    SET_VERSION=1; shift ;;
        --out-dir)        OUT_DIR="$2"; shift 2 ;;
        --include-tests)  INCLUDE_TESTS=1; shift ;;
        --keep-stage)     KEEP_STAGE=1; shift ;;
        --skip-bun-build) SKIP_BUN_BUILD=1; shift ;;
        -h|--help)        usage ;;
        *) echo "Unknown option: $1" >&2; exit 1 ;;
    esac
done

fail() {
    echo "Error: $*" >&2
    exit 1
}

[[ -f "$MAIN_FILE" ]] || fail "Cannot find cache-master.php at $MAIN_FILE"

read_plugin_version() {
    grep -Po '^\s*\*\s*Version:\s*\K[^\r\n]+' "$MAIN_FILE" | head -n 1 | tr -d '[:space:]'
}

bump_version() {
    local v="$1" component="$2"
    local major minor patch
    IFS='.' read -r major minor patch _ <<< "$v"

    [[ -n "${patch:-}" ]] || fail "Version '$v' does not follow MAJOR.MINOR.PATCH format for bumping. Use --version to specify manually."

    case "$component" in
        major) major=$((major + 1)); minor=0; patch=0 ;;
        minor) minor=$((minor + 1)); patch=0 ;;
        patch) patch=$((patch + 1)) ;;
        *) fail "Invalid bump component '$component'. Use major, minor, or patch." ;;
    esac

    echo "$major.$minor.$patch"
}

if [[ -z "$VERSION" ]]; then
    CURRENT_VERSION="$(read_plugin_version)"
    [[ -n "$CURRENT_VERSION" ]] || fail 'Cannot read plugin Version from cache-master.php'

    if [[ -n "$BUMP" ]]; then
        VERSION="$(bump_version "$CURRENT_VERSION" "$BUMP")"
        echo "Bumped $BUMP : $CURRENT_VERSION -> $VERSION"
    else
        VERSION="$CURRENT_VERSION"
    fi
fi

[[ "$VERSION" =~ ^[0-9A-Za-z._-]+$ ]] || fail "Invalid version '$VERSION'. Use numbers, letters, dot, underscore, or dash."

if [[ $SET_VERSION -eq 1 ]]; then
    perl -0777 -pi -e "s/^(\s*\*\s*Version:\s*)[^\r\n]+/\${1}$VERSION/m" "$MAIN_FILE"
    echo "Updated cache-master.php header version to $VERSION"

    if [[ -f "$ROOT/README.txt" ]]; then
        perl -0777 -pi -e "my \$done = 0; s/^(\s*Stable tag:\s*)[^\r\n]+/\$done++ ? \$& : \$1 . '$VERSION'/me" "$ROOT/README.txt"
        echo "Updated README.txt Stable tag to $VERSION"
    fi
fi

if [[ $SKIP_BUN_BUILD -eq 0 && -f "$ROOT/package.json" ]]; then
    (
        cd "$ROOT"

        if command -v bun >/dev/null 2>&1; then
            bun install --frozen-lockfile
            bun run build
        elif command -v pnpm >/dev/null 2>&1; then
            pnpm install --frozen-lockfile
            pnpm run build
        elif command -v npm >/dev/null 2>&1; then
            npm ci || npm install
            npm run build
        else
            fail 'No JS package manager found (bun, pnpm, or npm). Install one or pass --skip-bun-build with prebuilt assets.'
        fi
    )
fi

MANIFEST_PATH="$ROOT/inc/assets/build/.vite/manifest.json"

[[ -f "$MANIFEST_PATH" ]] || fail 'Release missing Vite manifest. Run the JS build or pass --skip-bun-build only when built assets already exist.'

read_admin_entry() {
    if command -v node >/dev/null 2>&1; then
        node -e '
            const manifest = JSON.parse(require("fs").readFileSync(process.argv[1], "utf8"));
            for (const name of ["assets/src/admin.jsx", "assets/src/admin.js", "admin.js"]) {
                if (manifest[name] && manifest[name].file) {
                    process.stdout.write(manifest[name].file);
                    process.exit(0);
                }
            }
            process.exit(1);
        ' "$MANIFEST_PATH" 2>/dev/null && return 0
    fi

    if command -v php >/dev/null 2>&1; then
        php -r '
            $manifest = json_decode(file_get_contents($argv[1]), true);
            foreach (array("assets/src/admin.jsx", "assets/src/admin.js", "admin.js") as $name) {
                if (isset($manifest[$name]["file"])) {
                    echo $manifest[$name]["file"];
                    exit(0);
                }
            }
            exit(1);
        ' "$MANIFEST_PATH" 2>/dev/null && return 0
    fi

    if command -v python3 >/dev/null 2>&1; then
        python3 -c '
import json, sys
manifest = json.load(open(sys.argv[1]))
for name in ("assets/src/admin.jsx", "assets/src/admin.js", "admin.js"):
    entry = manifest.get(name) or {}
    if entry.get("file"):
        sys.stdout.write(entry["file"])
        sys.exit(0)
sys.exit(1)
' "$MANIFEST_PATH" 2>/dev/null && return 0
    fi

    return 1
}

ADMIN_FILE="$(read_admin_entry || true)"

[[ -n "$ADMIN_FILE" ]] || fail 'Release manifest does not contain the React admin entry.'
[[ -f "$ROOT/inc/assets/build/$ADMIN_FILE" ]] || fail "Release missing built admin script $ADMIN_FILE."

DIST_DIR="$ROOT/$OUT_DIR"
STAGE_ROOT="$DIST_DIR/_stage"
STAGE_PLUGIN="$STAGE_ROOT/$PLUGIN_SLUG"
ZIP_PATH="$DIST_DIR/$PLUGIN_SLUG-$VERSION.zip"
NOTES_PATH="$DIST_DIR/$PLUGIN_SLUG-$VERSION-changelog.md"

rm -rf "$STAGE_ROOT"
mkdir -p "$STAGE_PLUGIN" "$DIST_DIR"

is_excluded() {
    local path="$1"

    local excluded_prefixes=(
        '.git/'
        '.github/'
        '.vscode/'
        '.agents/'
        '.code-review-graph/'
        '.opencode/'
        '.understand-anything/'
        '.ua/'
        "$OUT_DIR/"
        'assets/'
        'bin/'
        'graphify-out/'
        'node_modules/'
        'vendor/bin/'
        'vendor/composer/tmp-'
        'vendor/phpunit/'
        'vendor/yoast/'
        'vendor/sebastian/'
        'vendor/phar-io/'
        'vendor/theseer/'
        'vendor/myclabs/'
        'vendor/nikic/'
        'vendor/doctrine/'
        'vendor/shieldon/simple-cache/.github/'
        'vendor/shieldon/simple-cache/tests/'
        'vendor/psr/simple-cache/.github/'
        'vendor/psr/simple-cache/tests/'
    )

    if [[ $INCLUDE_TESTS -eq 0 ]]; then
        excluded_prefixes+=('tests/')
    fi

    local shopt_state
    shopt_state="$(shopt -p nocasematch || true)"
    shopt -s nocasematch

    local prefix
    for prefix in "${excluded_prefixes[@]}"; do
        if [[ "$path" == "$prefix"* ]]; then
            eval "$shopt_state" 2>/dev/null || true
            return 0
        fi
    done

    eval "$shopt_state" 2>/dev/null || true

    local excluded_names=(
        '.gitignore'
        '.gitattributes'
        '.travis.yml'
        '.scrutinizer.yml'
        'install-tests.sh'
        'phpunit.xml'
        'phpcs.xml'
        'sample.txt'
        'composer.lock'
        'bun.lock'
        'bun.lockb'
        'yarn.lock'
        'pnpm-lock.yaml'
        '.phpunit.result.cache'
        'vendor/shieldon/simple-cache/.gitignore'
        'vendor/shieldon/simple-cache/.travis.yml'
        'vendor/shieldon/simple-cache/.scrutinizer.yml'
        'vendor/shieldon/simple-cache/phpunit.xml'
        'vendor/psr/simple-cache/.editorconfig'
        'graphify-out'
        'AGENTS.md'
        'SESSION_MEMORY.md'
        'DESIGN.md'
        'skills-lock.json'
        'vite.config.js'
        'economy.ams.com.kh-20260521T162807.json'
    )

    local name
    for name in "${excluded_names[@]}"; do
        if [[ "$path" == "$name" ]]; then
            return 0
        fi
    done

    if [[ "$path" =~ \.(zip|tar|gz|tgz|7z)$ ]]; then
        return 0
    fi

    return 1
}

COPIED=0

while IFS= read -r -d '' file; do
    relative="${file#"$ROOT"/}"

    if is_excluded "$relative"; then
        continue
    fi

    target="$STAGE_PLUGIN/$relative"
    mkdir -p "$(dirname "$target")"
    cp -p "$file" "$target"
    COPIED=$((COPIED + 1))
done < <(find "$ROOT" -type f -print0)

[[ -f "$STAGE_PLUGIN/vendor/autoload.php" ]] || fail 'Release missing vendor/autoload.php. Runtime vendor files must be present before packaging.'

rm -f "$ZIP_PATH"

get_latest_changelog_section() {
    local changelog="$ROOT/CHANGELOG.md"

    if [[ -f "$changelog" ]]; then
        awk -v ver="$VERSION" '
            BEGIN { found = 0 }
            /^##[[:space:]]+\[/ {
                if (found) exit
                if (index($0, "[" ver "]") > 0) { found = 1 }
            }
            found { print }
        ' "$changelog"
    fi
}

RELEASE_NOTES="$(get_latest_changelog_section)"

if [[ -z "$RELEASE_NOTES" ]]; then
    RELEASE_NOTES="## [$VERSION]

See README.txt changelog."
fi

printf '%s\n' "$RELEASE_NOTES" > "$NOTES_PATH"

if command -v zip >/dev/null 2>&1; then
    (cd "$STAGE_ROOT" && zip -r -q -9 "$ZIP_PATH" "$PLUGIN_SLUG")
elif command -v python3 >/dev/null 2>&1; then
    python3 - "$STAGE_ROOT" "$ZIP_PATH" "$PLUGIN_SLUG" <<'PYEOF'
import os
import sys
import zipfile

stage_root, zip_path, slug = sys.argv[1], sys.argv[2], sys.argv[3]

with zipfile.ZipFile(zip_path, 'w', zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
    base = os.path.join(stage_root, slug)
    for dirpath, _dirnames, filenames in os.walk(base):
        for filename in sorted(filenames):
            full = os.path.join(dirpath, filename)
            entry = os.path.relpath(full, stage_root).replace(os.sep, '/')
            archive.write(full, entry)
PYEOF
else
    fail 'Neither zip nor python3 found. Install one to create the release archive.'
fi

if [[ $KEEP_STAGE -eq 0 ]]; then
    rm -rf "$STAGE_ROOT"
fi

SIZE_MB="$(awk -v bytes="$(stat -c%s "$ZIP_PATH" 2>/dev/null || stat -f%z "$ZIP_PATH")" 'BEGIN { printf "%.2f", bytes / 1048576 }')"

echo "Release zip: $ZIP_PATH"
echo "Changelog:   $NOTES_PATH"
echo "Version:     $VERSION"
echo "Files:       $COPIED"
echo "Size:        $SIZE_MB MB"
