#!/bin/bash
# DeSpace Database Synchronization Script for macOS / Linux

echo "=============================================================================="
echo "               DESPACE - DATABASE AUTO-SYNC SCRIPT"
echo "=============================================================================="
echo ""

if ! command -v wp &> /dev/null; then
    echo "⚠️  WP-CLI not found in PATH."
    echo "Please run this inside LocalWP 'Open Site Shell' or install wp-cli."
    exit 1
fi

read -p "Enter your LocalWP port (Default 10016, or press Enter): " PORT
PORT=${PORT:-10016}

echo ""
echo "[1/3] Importing despace_seed_data.sql into MySQL..."
if [ -f "database/despace_seed_data.sql" ]; then
    wp db import database/despace_seed_data.sql
elif [ -f "../database/despace_seed_data.sql" ]; then
    wp db import ../database/despace_seed_data.sql
else
    echo "❌ Error: database/despace_seed_data.sql not found!"
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
