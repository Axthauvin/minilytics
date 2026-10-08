#!/usr/bin/env bash
set -euo pipefail

# -----------------------------------------------------------------------------
# Minilytics Release Packaging Script
# -----------------------------------------------------------------------------
# Packages the deployable application into a .tar.gz archive.
# Excludes repository/dev/doc/test files and handles potential landing page
# additions in the repository.
# -----------------------------------------------------------------------------

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"

# Output configuration
DIST_DIR="${1:-${PROJECT_ROOT}/dist}"
ARCHIVE_NAME="${2:-minilytics.tar.gz}"
OUTPUT_ARCHIVE="${DIST_DIR}/${ARCHIVE_NAME}"

echo "==> Packaging Minilytics from: ${PROJECT_ROOT}"
echo "==> Target archive: ${OUTPUT_ARCHIVE}"

mkdir -p "${DIST_DIR}"

# The archive must ship production dependencies so installs without shell
# access (or Composer) work out of the box: `composer install --no-dev`.
if [ ! -f "${PROJECT_ROOT}/vendor/autoload.php" ]; then
    echo "ERROR: vendor/autoload.php is missing. Run 'composer install --no-dev --optimize-autoloader' first." >&2
    exit 1
fi
if [ -d "${PROJECT_ROOT}/vendor/friendsofphp" ]; then
    echo "ERROR: vendor/ contains dev dependencies. Run 'composer install --no-dev --optimize-autoloader' first." >&2
    exit 1
fi

# Create a clean temporary staging area
STAGING_DIR="$(mktemp -d)"
trap 'rm -rf "${STAGING_DIR}"' EXIT

# List of files/directories to exclude from deployment
EXCLUDES=(
    ".git"
    ".github"
    ".gitignore"
    ".gitattributes"
    ".git-blame-ignore-revs"
    ".editorconfig"
    ".php-cs-fixer.dist.php"
    ".php-cs-fixer.cache"
    "README.md"
    "docs"
    "tests"
    "scripts"
    "dist"
    "landing"
    "website"
    "umami-import"
    "node_modules"
    "*.tar.gz"
    "*.zip"
    "*.log"
    ".DS_Store"
    ".idea"
    ".vscode"
)

# Build rsync / copy exclusion arguments
EXCLUDE_ARGS=()
for item in "${EXCLUDES[@]}"; do
    EXCLUDE_ARGS+=(--exclude "${item}")
done

# Exclude runtime database and secret files in data/, but keep data/ and data/.htaccess
EXCLUDE_ARGS+=(
    --exclude "data/*.db"
    --exclude "data/*.db-wal"
    --exclude "data/*.db-shm"
    --exclude "data/sites.json"
    --exclude "data/.tracking-secret"
)

# Synchronize project files to staging directory
if command -v rsync >/dev/null 2>&1; then
    rsync -a "${EXCLUDE_ARGS[@]}" "${PROJECT_ROOT}/" "${STAGING_DIR}/"
else
    # Fallback if rsync is not installed
    cp -R "${PROJECT_ROOT}/." "${STAGING_DIR}/"
    for item in "${EXCLUDES[@]}"; do
        rm -rf "${STAGING_DIR:?}/${item}" 2>/dev/null || true
    done
    rm -f "${STAGING_DIR}/data"/*.db "${STAGING_DIR}/data"/*.db-wal "${STAGING_DIR}/data"/*.db-shm "${STAGING_DIR}/data/sites.json" "${STAGING_DIR}/data/.tracking-secret" 2>/dev/null || true
fi

# Ensure data/ directory exists and contains data/.htaccess
mkdir -p "${STAGING_DIR}/data"
if [ ! -f "${STAGING_DIR}/data/.htaccess" ] && [ -f "${PROJECT_ROOT}/data/.htaccess" ]; then
    cp "${PROJECT_ROOT}/data/.htaccess" "${STAGING_DIR}/data/.htaccess"
fi

# Ensure .htaccess at root exists
if [ ! -f "${STAGING_DIR}/.htaccess" ] && [ -f "${PROJECT_ROOT}/.htaccess" ]; then
    cp "${PROJECT_ROOT}/.htaccess" "${STAGING_DIR}/.htaccess"
fi

# Safety check: ensure no db files leaked into staging
LEAKED_DB=$(find "${STAGING_DIR}/data" -name "*.db" 2>/dev/null || true)
if [ -n "${LEAKED_DB}" ]; then
    echo "ERROR: Database file detected in staging data/ folder: ${LEAKED_DB}" >&2
    exit 1
fi

# Create tar.gz archive with clean relative paths
echo "==> Creating tarball..."
tar -czf "${OUTPUT_ARCHIVE}" -C "${STAGING_DIR}" .

# Generate SHA256 checksum
CHECKSUM_FILE="${OUTPUT_ARCHIVE}.sha256"
if command -v sha256sum >/dev/null 2>&1; then
    (cd "${DIST_DIR}" && sha256sum "$(basename "${OUTPUT_ARCHIVE}")" > "$(basename "${CHECKSUM_FILE}")")
elif command -v shasum >/dev/null 2>&1; then
    (cd "${DIST_DIR}" && shasum -a 256 "$(basename "${OUTPUT_ARCHIVE}")" > "$(basename "${CHECKSUM_FILE}")")
fi

echo "==> Archive successfully created:"
ls -lh "${OUTPUT_ARCHIVE}"
if [ -f "${CHECKSUM_FILE}" ]; then
    cat "${CHECKSUM_FILE}"
fi
