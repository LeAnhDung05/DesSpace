# Git & GitHub Workflow

## Branches

- `main`: stable, demo-ready release branch.
- `develop`: shared integration branch.
- Feature branches: one focused branch per Issue.

Naming:
```text
feature/<issue-number>-<short-description>
fix/<issue-number>-<short-description>
docs/<issue-number>-<short-description>
```

## Daily workflow

```bash
git clone https://github.com/LeAnhDung05/DesSpace.git
cd DesSpace
git switch develop
git pull origin develop
git switch -c feature/<issue-number>-<short-description>
```

After implementation:
```bash
git add <specific-files>
git commit -m "feat(<module>): <short description>"
git push -u origin feature/<issue-number>-<short-description>
```

Then open a Pull Request:
- Base: `develop`
- Compare: your feature branch
- Link the Issue using `Closes #<issue-number>` only when the task is fully completed.
- Request at least one reviewer.
- Include test steps, screenshots when UI changes, risks and migration notes.

## Merge rules

- Never push feature work directly to `main`.
- Do not merge a PR with unresolved review comments or failing required checks.
- Prefer Squash and merge for focused feature branches.
- Rebase / update feature branch from `develop` before requesting final review when conflicts exist.
- Release manager merges verified `develop` into `main`.

## Code ownership boundaries

- M1: `includes/Product-3D/`
- M2: `themes/despace-child/`, `includes/Catalog/`
- M3: `includes/Checkout-Orders/`
- M4: `includes/Inventory/`, inventory migration files
- M5: `despace-core.php`, `includes/Reports/`, shared API bootstrap and docs

Do not let all five members edit the same bootstrap file. Request M5 review for changes to shared interfaces or plugin loading.

## Never commit

- `wp-config.php`, .env files, database dumps or credentials.
- WordPress core or installed third-party plugin source.
- Local uploads / caches / generated files.
- Real customer, payment or personal data.
- Large GLB assets unless the team explicitly selects and configures Git LFS. Prefer shared staging/media storage.

## Suggested commit prefixes

- `feat`: new behavior
- `fix`: bug fix
- `docs`: documentation
- `test`: tests
- `refactor`: internal structure without behavior change
- `chore`: maintenance / tooling
