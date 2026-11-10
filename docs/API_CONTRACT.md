# DeSpace — API Contract (Initial)

This document is the shared contract between module owners. Agree on endpoint names, schemas and permissions here before implementation. Do not create parallel endpoints that duplicate WooCommerce behavior.

## Conventions

- Namespace: `despace/v1`
- Base path: `/wp-json/despace/v1/`
- Methods: GET for read; POST for state-changing actions.
- Response: JSON with stable keys and clear HTTP status codes.
- Every privileged route must define a permission callback and capability check.
- Validate and sanitize every parameter.
- Public catalog filters may be read-only; product administration and reports require suitable capabilities.
- Use WordPress REST API for structured operations; use AJAX / Fetch API only where theme interaction requires it.

## Initial endpoints

| Method | Endpoint | Owner | Purpose |
|---|---|---|---|
| GET | `/catalog` | M2 | Search, filter and paginate published WooCommerce products |
| GET | `/products/{id}/3d` | M1 | Read validated GLB / poster metadata |
| POST | `/products/{id}/3d` | M1 | Save 3D attachment metadata; authorized staff only |
| GET | `/inventory/logs` | M4 | Filter stock audit events; authorized staff only |
| GET | `/reports/overview` | M5 | Dashboard KPIs by date range; authorized staff only |
| GET | `/reports/top-products` | M5 | Top products for selected date range; authorized staff only |

Cart, coupon, checkout and order lifecycle should use WooCommerce APIs and supported WooCommerce endpoints / hooks. Do not duplicate these as a parallel DeSpace order service.

## Catalog query contract

Suggested query parameters:
- `q`: product name / SKU search
- `category`: product category / room taxonomy term
- `min_price`, `max_price`
- `material`, `color`, `size`
- `has_3d`: boolean
- `page`, `per_page`, `orderby`, `order`

Response should include:
- `items`: product ID, name, permalink, image, price display, SKU, stock status, categories, 3D availability.
- `pagination`: page, per_page, total_items, total_pages.
- `applied_filters`: normalized filters.

The server must validate bounds and allowed sort fields. Price and availability displayed by the UI must not be used as the final checkout authority; WooCommerce revalidates at checkout.

## Error shape

```json
{
  "code": "despace_invalid_filter",
  "message": "The selected filter is invalid.",
  "data": { "status": 400 }
}
```

## Coordination rule

When a module needs a contract change:
1. Open an Issue describing the change and affected modules.
2. Update this document in a PR.
3. Obtain approval from affected module owners.
4. Merge the contract change before dependent implementation is merged.
