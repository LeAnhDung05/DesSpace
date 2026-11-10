# DeSpace — Premium Furniture E-commerce

**Project:** DESPACE-2026  
**Internship period:** 19/10/2026 – 29/11/2026  
**Team:** 5 members  
**Design:** [DeSpace Figma file](https://www.figma.com/design/2O1yGyEkoUrDRcGX6upmNw)

## Final technology stack

| Layer | Technology |
|---|---|
| Frontend | HTML5 + CSS3 + JavaScript ES6+ |
| Design | Figma — Premium UI/UX |
| CMS | WordPress |
| E-commerce | WooCommerce |
| Backend | PHP |
| Database | MySQL Community Edition |
| API | WordPress REST API + AJAX / Fetch API |
| 3D / AR | Google model-viewer + GLB |
| Custom module | DeSpace Core Plugin (`despace-core`) |
| Presentation | DeSpace Child Theme (`despace-child`) |
| Version control | Git + GitHub |

## Product architecture

- One shared website, one repository and one integrated WordPress/WooCommerce application.
- `despace-child` owns presentation, templates and theme assets.
- `despace-core` owns DeSpace-specific business logic and extension modules.
- WordPress/WooCommerce remain the source of truth for users, products, variations, coupons, orders and stock.
- Do not create duplicate `users`, `products`, `orders` or `coupons` tables.
- Access orders through WooCommerce CRUD APIs for HPOS compatibility.
- The custom `despace_inventory_logs` table is an audit log only. It must never become a second stock engine or deduct/restore stock twice.

## Five workstreams

| Owner | Workstream | Primary responsibility |
|---|---|---|
| M1 | 3D / AR Showroom | GLB metadata, model-viewer, AR support and fallbacks |
| M2 | Catalog & Premium UI | Child Theme, homepage, catalog, search and AJAX filters |
| M3 | Checkout & Orders | Cart, coupon, checkout, payment instructions and order tracking |
| M4 | Inventory Traceability | Idempotent inventory event log and stock audit UI |
| M5 | Dashboard & Integration | Admin dashboard, reports, API contracts, integration and release coordination |

Detailed task breakdown is tracked in GitHub Issues. Add the created Issues to the **Thực Tập Chuyên Ngành** GitHub Project and assign them to the matching team member.

## Repository layout

```text
wordpress/
  wp-content/
    themes/despace-child/
    plugins/despace-core/
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
docs/
.github/
```

See:
- [Team task map](docs/TEAM_TASKS.md)
- [Git workflow](docs/GIT_WORKFLOW.md)
- [Architecture rules](docs/ARCHITECTURE.md)
- [API contract](docs/API_CONTRACT.md)
- [Definition of done](docs/DEFINITION_OF_DONE.md)

## Local development

Each member runs a separate local WordPress + WooCommerce + MySQL environment using the same agreed versions and seed data. Integrate through the shared `develop` branch and test end-to-end on one shared staging environment.

Never commit `wp-config.php`, credentials, local databases, caches, WordPress core, or unapproved customer data. Use environment-specific configuration and the provided `.gitignore`.

## Current delivery focus

The project is in the final implementation period (10/11/2026). Prioritize a working MVP for all five workstreams, cross-module integration, security checks, responsive tests and a demonstrable end-to-end ordering flow by 29/11/2026.
