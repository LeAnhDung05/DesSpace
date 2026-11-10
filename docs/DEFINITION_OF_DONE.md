# DeSpace — Definition of Done

A task is done only when all applicable checks pass:

- [ ] Scope matches its GitHub Issue and no unrelated module is changed.
- [ ] Code follows the final technology stack and agreed folder ownership.
- [ ] Permission checks, validation, sanitization and output escaping are implemented.
- [ ] No secrets or local database / uploads are committed.
- [ ] Unit / functional test evidence is included.
- [ ] Integration behavior with dependent modules is tested.
- [ ] UI matches approved Figma screen and responsive behavior is checked.
- [ ] Pull Request links its Issue and receives at least one review.
- [ ] No unresolved review comments or known blocker remains.
- [ ] Staging smoke test passes.
- [ ] Documentation and known limitations are updated.

## End-to-end MVP acceptance

1. Admin creates / edits a WooCommerce product with SKU, price, category, variations, stock and image.
2. For a product with a valid GLB, the product detail screen shows a working model-viewer; AR appears only on supported devices.
3. Customer searches and combines catalog filters; result set and pagination are correct.
4. Customer adds products to cart, applies an eligible coupon and completes checkout using COD or manual transfer.
5. WooCommerce creates the order and remains responsible for stock movement.
6. DeSpace inventory audit records each stock event once, including the order / product reference.
7. Staff / Admin access is capability-controlled.
8. Dashboard reports values from WooCommerce within the selected date range.
9. Desktop, tablet and mobile smoke tests pass.
10. Backup, setup steps, test evidence and known limitations are documented.
