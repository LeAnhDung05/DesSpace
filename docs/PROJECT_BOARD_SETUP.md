# GitHub Projects — Thực Tập Chuyên Ngành

## Project and repository

- Project: **Thực Tập Chuyên Ngành**
- Repository: `LeAnhDung05/DesSpace`
- Repository URL: https://github.com/LeAnhDung05/DesSpace
- All five workstreams belong to one website and one repository.

## Add the Issues to the Project

The repository Issues have been created, but the GitHub Projects v2 board still needs the repository Issues added as Project items:

1. Open the account Project **Thực Tập Chuyên Ngành**.
2. In the Backlog/Table view, select **Add item**.
3. Search for `repo:LeAnhDung05/DesSpace` or paste the Issue URLs below.
4. Add Issues **#1–#28**. Keep all work in this one Project.
5. Use the Module field to group the parent and task Issues by M1–M5.

The Project board and repository Issues are separate GitHub objects. Adding an Issue to the repository does not guarantee it is already visible as an item on this Project.

## Recommended custom fields

| Field | Type | Values / purpose |
|---|---|---|
| Module | Single select | M1, M2, M3, M4, M5 |
| Priority | Single select | P0, P1, P2, P3 |
| Sprint | Iteration | Sprint 4, Sprint 5, Sprint 6 |
| Estimate | Number | 1, 2, 3, 5, 8 points |
| Status | Built-in Status | Backlog, Ready, In Progress, In Review, Testing, Done, Blocked |

Recommended Status meanings:

- **Backlog** — not yet refined or scheduled.
- **Ready** — scope, dependencies and acceptance criteria are clear.
- **In Progress** — actively being implemented.
- **In Review** — Pull Request opened and awaiting review.
- **Testing** — merged to develop or deployed to staging for verification.
- **Done** — acceptance criteria passed and task is complete.
- **Blocked** — waiting for access, dependency, decision or external input.

## Recommended views

- **Backlog** — table; group by Module, sort by Priority.
- **Board** — board; group by Status.
- **Current iteration** — filter to the active Sprint.
- **Roadmap** — timeline/roadmap view by Sprint.
- **My items** — filter to the current user's assignee.

## Issue map

| Module | Parent Issue | Task Issues |
|---|---:|---|
| M1 — 3D / AR | #1 | #6–#9 |
| M2 — Catalog & UI | #2 | #10–#14 |
| M3 — Checkout & Orders | #3 | #15–#19 |
| M4 — Inventory | #4 | #20–#23 |
| M5 — Dashboard & Integration | #5 | #24–#28 |

Parent Issues contain links to their task Issues. Use the Module field for grouping and the built-in Sub-issues progress field only if actual GitHub sub-issue relationships are created in the UI.

## Suggested iterations

Project period: **19/10/2026 – 29/11/2026**. The plan below reflects the remaining work as of **10/11/2026**.

| Iteration | Dates | Focus |
|---|---|---|
| Sprint 4 | 09/11/2026 – 15/11/2026 | Shared repository, contracts, child theme/plugin foundation and first working module slices |
| Sprint 5 | 16/11/2026 – 22/11/2026 | Complete five workstreams and integrate cross-module flows |
| Sprint 6 | 23/11/2026 – 29/11/2026 | Staging regression, fixes, demo, documentation and handoff |

## Team assignment

Map M1–M5 to the actual GitHub usernames only after all members accept repository invitations.

- M1 — 3D / AR Showroom
- M2 — Catalog & Premium UI
- M3 — Cart, Checkout & Order Management
- M4 — Inventory Traceability
- M5 — Dashboard, Reports & Integration (integration/release lead)

## Important working rule

Keep one application, one repository and one shared Project. Members work on separate local WordPress/MySQL environments and their own feature branches; integration happens through Pull Requests into `develop`, with a shared staging environment for end-to-end testing.