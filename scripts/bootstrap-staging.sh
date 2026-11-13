#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

if [[ ! -f .env ]]; then
  cp .env.example .env
  echo "Created .env from .env.example."
fi

set -a
# shellcheck disable=SC1091
source .env
set +a

if [[ "${WP_ADMIN_PASSWORD:-}" == "Change-this-local-password-123!" || -z "${WP_ADMIN_PASSWORD:-}" ]]; then
  echo "Set a unique WP_ADMIN_PASSWORD in .env before continuing." >&2
  exit 1
fi

wp() {
  docker compose --profile tools run --rm wpcli "$@"
}

echo "[1/6] Starting MariaDB and WordPress"
docker compose up -d db wordpress

echo "[2/6] Installing WordPress if this is a fresh database"
if ! wp core is-installed >/dev/null 2>&1; then
  wp core install \
    --url="${WP_URL:-http://localhost:8080}" \
    --title="${WP_TITLE:-DeSpace Staging MVP}" \
    --admin_user="${WP_ADMIN_USER:-despaceadmin}" \
    --admin_password="$WP_ADMIN_PASSWORD" \
    --admin_email="${WP_ADMIN_EMAIL:-admin@example.test}" \
    --skip-email
else
  echo "WordPress is already installed; keeping the current database and admin account."
fi

echo "[3/6] Installing WooCommerce 11.1.2 if missing"
if ! wp plugin is-installed woocommerce >/dev/null 2>&1; then
  wp plugin install woocommerce --version=11.1.2 --activate
else
  wp plugin activate woocommerce >/dev/null
fi

echo "[4/6] Activating DeSpace Core and staging storefront"
wp plugin activate despace-core >/dev/null || wp plugin is-active despace-core
wp theme activate despace-child

echo "[5/6] Creating demo pages, sample products and test checkout settings"
wp eval-file wp-content/themes/despace-child/seed-demo.php

echo "[6/6] Running staging smoke checks"
bash scripts/verify-staging.sh

echo
echo "DeSpace Staging MVP is ready:"
echo "  Storefront: ${WP_URL:-http://localhost:8080}/"
echo "  Products:   ${WP_URL:-http://localhost:8080}/shop/"
echo "  Cart:       ${WP_URL:-http://localhost:8080}/cart/"
echo "  Checkout:   ${WP_URL:-http://localhost:8080}/checkout/"
echo "  Login:      ${WP_URL:-http://localhost:8080}/my-account/"
echo "  Admin:      ${WP_URL:-http://localhost:8080}/wp-admin/"
echo
echo "Use test data only. Do not send real payments."
