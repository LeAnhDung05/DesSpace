# DeSpace — Staging MVP (Integration Branch)

This branch is a temporary, isolated integration target. It does not merge into `develop` and does not rewrite any member branch. It brings together the current M3 checkout backend, M4 inventory module, M5 plugin bootstrap/RBAC, and a clearly-labelled temporary storefront theme so the team can run the first browser-based MVP.

## What is included

- WordPress + WooCommerce local runtime using PHP 8.3 and MariaDB 10.11.
- M5 `despace-core` plugin and root PSR-4 loader / bootstrap.
- M3 cart, coupon, checkout, COD/BACS, account, guest tracking and transactional email hooks.
- M4 inventory event observer, audit table, admin UI, REST endpoint and reconciliation.
- Temporary responsive storefront theme: homepage, product archive/product detail (WooCommerce native rendering), cart, checkout and account pages.
- Idempotent sample data script with eight sample furniture products, VND pricing, COD/BACS and a Vietnam flat-rate shipping zone.

The temporary theme is not a substitute for the final approved M2 Figma implementation. M1 3D/AR and M2 premium catalog integration are not included yet because their feature branches currently have no source changes relative to `develop`.

## Requirements

- Docker Desktop / Docker Engine with the Compose plugin.
- Ports 8080 and local Docker resources available.
- Internet access for initial WordPress/WooCommerce image and plugin downloads.

## Quick start

Requirements: Docker Desktop / Docker Engine with the Compose plugin, internet access for initial images/plugin download, and local port 8080 available.

From the repository root:

```bash
cp .env.example .env
```

Edit `.env` and replace `WP_ADMIN_PASSWORD` with a unique local password. Then run the one-command setup:

```bash
bash scripts/bootstrap-staging.sh
```

The bootstrap script starts MariaDB and WordPress, installs WordPress if the database is fresh, installs WooCommerce 11.1.2, activates DeSpace Core and the temporary storefront, creates pages/products/payment/shipping demo settings, and runs the smoke-verification script. It preserves an existing local database and admin account.

Open:
- Storefront: ${WP_URL:-http://localhost:8080}/
- Products: ${WP_URL:-http://localhost:8080}/shop/
- Cart: ${WP_URL:-http://localhost:8080}/cart/
- Checkout: ${WP_URL:-http://localhost:8080}/checkout/
- Account / login / registration: ${WP_URL:-http://localhost:8080}/my-account/
- Guest order tracking: ${WP_URL:-http://localhost:8080}/order-tracking/
- WordPress admin: ${WP_URL:-http://localhost:8080}/wp-admin/

## First end-to-end test

1. Sign in to `/my-account/` or create a customer account.
2. Open `/shop/`, open a demo product, choose quantity and click Add to cart.
3. Open `/cart/`, change quantity or remove an item and verify totals.
4. Open `/checkout/`, enter test customer details and choose COD or bank transfer.
5. Place a test order. For BACS, the order should remain on-hold until an administrator verifies receipt; do not send real money.
6. Verify the order in WooCommerce > Orders and the stock event in WooCommerce > Inventory Logs.
7. For guest tracking, use the order number and the same email/phone used at checkout on `/order-tracking/`.

## Important staging safety

- This is a local preview, not a public production deployment.
- Do not enter real customer personal data, real bank details or real payments.
- BACS is manual transfer; no bank webhook or automatic settlement is implemented.
- Do not expose this Compose stack to the public internet with default credentials.
- Product images and M1 GLB assets are intentionally placeholders until approved catalog and 3D assets are integrated.
- The final M2 child theme must replace this temporary standalone staging theme after review.

## Troubleshooting

- If the site shows a database connection error, wait until the `db` health check is healthy, then restart `wordpress`.
- If the Core plugin does not appear, check that `wordpress/wp-content/plugins/despace-core/despace-core.php` exists on this branch and restart the `wordpress` service.
- If a module is not initialized, inspect `wp-content/debug.log` and the WooCommerce dependency status.
- To stop: `docker compose down`.
- To remove all local demo DB/uploads permanently: `docker compose down -v` (destructive).
