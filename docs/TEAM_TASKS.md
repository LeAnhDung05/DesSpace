# DeSpace — Team Workstream & Ownership

## Team ownership

### M1 — 3D / AR Showroom
Owns:
- Product GLB / poster attachment metadata and AR enable flag.
- GLB attachment validation and safe upload handling.
- Product detail model-viewer integration.
- Rotate / zoom, loading, error and fallback states.
- AR capability detection and device testing.

Acceptance:
- Valid GLB is displayed and interactive.
- AR entry is offered only when supported.
- Failed or unsupported AR does not break 2D product browsing.
- Product metadata stores attachment IDs, not hard-coded media URLs.

### M2 — Catalog & Premium UI
Owns:
- `despace-child`, shared header/footer and reusable storefront presentation.
- Homepage, catalog, product cards and responsive behavior.
- Search by product name / SKU.
- Multi-filter AJAX using Fetch API.
- Empty state, pagination, filter reset and URL state where applicable.

Acceptance:
- Catalog data and price come from WooCommerce.
- Combined filters return correct results.
- Desktop / tablet / mobile views work.
- UI follows the shared Figma file and DeSpace Premium design tokens.

### M3 — Checkout & Order Management
Owns:
- Cart quantity changes, remove item and cart validation.
- WooCommerce coupon handling.
- Checkout for Customer and Guest when enabled.
- COD and manual bank transfer instructions / QR when configured.
- Order confirmation and customer order tracking.

Acceptance:
- WooCommerce validates price, coupon and stock.
- Unconfirmed bank transfer is never marked paid.
- Order status and payment status are represented accurately.
- Customer can only view their own orders; Guest lookup requires a suitable secure verification mechanism.

### M4 — Inventory Traceability
Owns:
- `despace_inventory_logs` schema and dbDelta migration.
- Event key and idempotent logging.
- Stock movement event capture for import / sale / restore / adjust.
- Inventory audit screen and filters.
- Tests for cancellation, restore and repeated hooks.

Acceptance:
- WooCommerce is the only stock source of truth.
- One real event produces at most one inventory log.
- Cancelling an order that never reduced stock does not increase stock.
- Logs record event origin, product / variation, order, quantity delta, stock-after and timestamp.

### M5 — Dashboard, Reports & Integration
Owns:
- Core plugin bootstrap and module integration boundaries.
- API Contract, shared architecture and integration coordination.
- Admin dashboard KPIs.
- Revenue, order-status and top-product reports.
- Staff / Admin capability checks and release coordination.

Acceptance:
- Dashboard uses real WooCommerce data and documented date filters.
- M1–M4 modules integrate without duplicated business logic.
- REST endpoints enforce authentication / capabilities and validate input.
- Pull Requests are reviewed and staging smoke tests pass.

## Shared responsibilities

Every module owner must deliver:
1. Editable Figma implementation reference or screen mapping.
2. PHP / JavaScript source in the agreed module folder.
3. Input validation, authorization and error handling.
4. Unit or functional tests for the owned logic.
5. At least one integration test with its upstream / downstream module.
6. Documentation for configuration and known limitations.
7. Pull Request with screenshots / test evidence.

## Integration dependencies

- M2 provides product card, product detail layout and catalog context; M1 plugs the model-viewer into product detail.
- M1 provides / documents product 3D metadata keys; M2 consumes the metadata to show 3D availability badges and filters.
- M3 consumes WooCommerce products, variations, coupons and stock; it must not create an independent cart/order engine.
- M4 listens to WooCommerce stock events; it must not control stock quantity independently.
- M5 owns shared bootstrap, API contract and final integration; each module owner owns its own implementation and tests.

## Team member identities

Replace M1–M5 with actual GitHub usernames in the Project's Module field / assignees after all five accounts have accepted repository access.
