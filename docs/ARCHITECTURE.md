# DeSpace — Architecture & Data Ownership

## Final stack

Frontend: HTML5, CSS3, JavaScript ES6+  
Design: Figma — Premium UI/UX  
CMS: WordPress  
E-commerce: WooCommerce  
Backend: PHP  
Database: MySQL Community Edition  
API: WordPress REST API + AJAX / Fetch API  
3D / AR: Google model-viewer + GLB  
Custom module: DeSpace Core Plugin (`despace-core`)  
Theme: DeSpace Child Theme (`despace-child`)  
Version control: Git + GitHub

## Layer responsibilities

1. Presentation: Child Theme, templates, CSS, JavaScript, responsive screens.
2. Application: WordPress Core, WooCommerce and DeSpace Core Plugin.
3. Data: MySQL Community Edition with WordPress / WooCommerce-managed tables and the narrowly scoped DeSpace inventory log table.
4. Media: WordPress Media Library attachment IDs for product images, posters and GLB models.
5. External services: bank / payment provider, SMTP or CDN only when configured and credentials are supplied.

## Data source of truth

| Domain | System of record | DeSpace responsibility |
|---|---|---|
| Users / customers | WordPress users / WooCommerce customer APIs | Capabilities and DeSpace-specific profile extensions only |
| Products / variations / SKU / price | WooCommerce product objects | Additional metadata and presentation |
| Categories / attributes | WordPress / WooCommerce taxonomy APIs | Furniture-specific filtering and room presentation |
| Coupons | WooCommerce coupon objects | UI integration only |
| Cart / checkout / orders / order items | WooCommerce | Custom checkout presentation and extensions through APIs |
| Stock quantity | WooCommerce | Read / display and event audit only |
| 3D / AR metadata | WordPress attachment + product meta | GLB attachment ID, poster ID, AR enable flag and validation |
| Inventory event history | `{$wpdb->prefix}despace_inventory_logs` | Append-only, idempotent audit log |

Never create duplicate `users`, `products`, `orders`, `order_items` or `coupons` tables.

Use WooCommerce CRUD APIs such as `wc_get_order()`, `WC_Order`, `WC_Order_Item`, `WC_Product` and `WC_Coupon`. Do not access order storage directly; preserve HPOS compatibility.

## Plugin module boundaries

```text
despace-core/
  despace-core.php
  includes/
    Products/
    Product-3D/
    Catalog/
    Checkout-Orders/
    Inventory/
    Reports/
    REST-API/
    Admin/
    Security/
    Database/
```

Module rules:
- Prefix functions, hooks, options, metadata and database tables with a DeSpace namespace / prefix.
- Keep business logic out of `functions.php`.
- Register REST routes with permission callbacks.
- Use nonce for CSRF protection where appropriate; nonce is not authentication.
- Check capabilities on every privileged action.
- Sanitize input, escape output and use `$wpdb->prepare()` for custom SQL.
- Do not bypass WooCommerce validation for price, coupon, tax, shipping or stock.

## Inventory event log

The event log is not a stock engine. Use a unique `event_key` to prevent duplicate processing. Log only confirmed WooCommerce stock movement events. Record source, product / variation, order, actor, quantity delta, stock-after and time. Do not restore stock for an order that did not reduce it.

## Payment boundary

MVP supports COD and manual bank transfer. A transfer without reconciliation must not be labelled paid. Automated gateways are conditional on provider, sandbox credentials and API documentation.
