#!/usr/bin/env bash
# DeSpace Database Synchronization Script for macOS / Linux
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

echo "=============================================================================="
echo "               DESPACE - DATABASE AUTO-SYNC SCRIPT"
echo "=============================================================================="
echo ""

if ! command -v wp &> /dev/null; then
    echo "⚠️  WP-CLI not found in PATH."
    echo "Please run this inside LocalWP 'Open Site Shell' or install wp-cli."
    exit 1
fi

read -r -p "Enter your LocalWP port (Default 10016, or press Enter): " PORT
PORT="${PORT:-10016}"

if ! [[ "${PORT}" =~ ^[0-9]+$ ]] || (( PORT < 1 || PORT > 65535 )); then
    echo "❌ Error: Invalid port '${PORT}'. Must be an integer between 1 and 65535."
    exit 1
fi

echo ""
echo "[1/3] Importing seed data into MySQL..."
if [ -f "${SCRIPT_DIR}/despace_seed_data.sql.gz" ] && command -v gzip &> /dev/null; then
    gzip -dc "${SCRIPT_DIR}/despace_seed_data.sql.gz" | wp db import -
elif [ -f "${SCRIPT_DIR}/despace_seed_data.sql" ]; then
    wp db import "${SCRIPT_DIR}/despace_seed_data.sql"
else
    echo "❌ Error: Seed SQL not found in ${SCRIPT_DIR}!"
    exit 1
fi

echo ""
echo "[2/3] Replacing URLs to http://localhost:${PORT}..."
wp search-replace "http://localhost:8090" "http://localhost:${PORT}" --all-tables

echo ""
echo "[3/3] Disabling Coming Soon mode and flushing rewrites..."
wp option update woocommerce_coming_soon "no"
wp rewrite flush

echo ""
echo "=============================================================================="
echo "🎉 SYNC COMPLETE 100%! Open: http://localhost:${PORT}/shop/"
echo "=============================================================================="
