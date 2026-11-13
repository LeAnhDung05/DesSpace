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

Run from the repository root:

```bash
cp .env.example .env
docker compose up -d db wordpress
```

Install WordPress (replace the local-only password):

```bash
docker compose --profile tools run --rm wpcli core install \
  --url=http://localhost:8080 \
  --title="DeSpace Staging MVP" \
  --admin_user=despaceadmin \
  --admin_password='Change-this-local-password-123!' \
  --admin_email=admin@example.test
```

Install WooCommerce and activate DeSpace Core:

```bash
docker compose --profile tools run --rm wpcli plugin install woocommerce --activate
docker compose --profile tools run --rm wpcli plugin activate despace-core
docker compose --profile tools run --rm wpcli theme activate despace-child
```

Create pages, demo products, VND currency, COD/BACS and sample shipping:

```bash
docker compose --profile tools run --rm wpcli eval-file wp-content/themes/despace-child/seed-demo.php
```

Open:

- Storefront: http://localhost:8080/
- Products: http://localhost:8080/shop/
- Cart: http://localhost:8080/cart/
- Checkout: http://localhost:8080/checkout/
- Account / login / registration: http://localhost:8080/my-account/
- Guest order tracking: http://localhost:8080/order-tracking/
- WordPress admin: http://localhost:8080/wp-admin/

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
