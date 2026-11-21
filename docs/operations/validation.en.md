# Validation — 20 November 2026

This is focused development evidence, not production acceptance or a security audit. All data and accounts were synthetic; no organization server was accessed.

- Laravel suite against real PostgreSQL17:31 tests,273 assertions passed. Run `php artisan test --compact` with a dedicated disposable `afra_test` database. `RefreshDatabase` destroys/rebuilds test tables; never point it at operational data.
- Frontend:4 Vitest API tests, strict TypeScript/Vite build and Prettier source check passed. Commands in `FrontEnd`: `npm ci`, `npm run format:check`, `npm test`, `npm run build`. The build currently warns about a large Ant Design bundle; further route splitting remains desirable.
- Actual browser: group/custom-role/member editing, case assignment/substitute, independent review, assigned-tester evidence upload/save/history/download. File bytes matched SHA256 `0304d2668aed66916ed58fbf5e489844afacb1ba5791a2a11cc3e942c6383fe6`. This browser proof uses a small text file; the20MiB boundary was separately exercised through Laravel upload and private streamed download tests, not an HTTP20MiB deployment test.
- Actual Persian mobile390×844 and English desktop1440×1050 access editors: no horizontal document overflow; save footer inside viewport; no page errors or failed API requests. Browser timezone Asia/Tehran. Screenshots are under `docs/screenshots`.
- Word guides: native TOC field, embedded Vazir regular/bold, declared Cambria for Latin, reviewed cover and stable cached heading/page maps. Local PDF rendering uses fallback for Cambria because that proprietary font is unavailable here. Persian22pages, English18pages.

## Repeat browser checks

Install Python Playwright and supply Chromium at `/usr/bin/chromium`. Start local API/Vite with synthetic training fixtures. Set `AFRA_TEST_URL`, `AFRA_TEST_EMAIL`, `AFRA_TEST_PASSWORD_FILE` (path outside repository), and optionally `AFRA_SCREENSHOT_DIR` (defaults under `/tmp`). Do not commit credentials.

`python FrontEnd/tests/access_responsive.py` is read-only and needs a training administrator with at least one existing project access group. `python FrontEnd/tests/evidence_roundtrip.py` **writes** a training result and attachment: use only a disposable synthetic project, an assigned tester, and an existing run. Never execute it against production. These scripts do not provision accounts or grant permissions.

`PHP_BINARY=php python scripts/build_openapi.py` regenerates the current-route OpenAPI draft from Laravel routes. The draft does not define unimplemented external services; secondary user/record schemas still require completion.

See the implementation-status documents for mandatory remaining work. No100k-account capacity or complete deployment/readiness claim has been made.

## Planning, release quality and shared steps — 21 November 2026

Focused PostgreSQL regressions cover matrix expansion (2 cases ×2 configurations ×2 data rows), false/zero preservation, Solar date rendering with ISO source retention, required/optional selection, actor-bound expiring previews, changed-source rejection and idempotent confirmation. Nested saved views reject unsupported fields, preserve literal wildcard search and isolate private owners. Shared steps reject self approval, stale reviews, cross-project/forged sources and unapproved pins; upgrades create new drafts, old revisions stay unchanged, archived existing pins remain usable, and record history is paginated.

Actual browser acceptance passed both `planning_workflow.py` and `shared_steps_workflow.py` with no page errors or failed API requests. Planning preview showed8 items/4 required; deselecting one optional item created7. A reviewed shared block plus one local step saved two resolved steps and one local step with the approved revision pinned. Mobile390×844 document-width checks passed after responsive layout settled. Training data only; no organization deployment.

Run/release quality reports filter sections, aggregate required variants, use latest explicitly linked observations and database baseline ordering, reject global readiness under partial visibility/stale sources and retain forced closure as unverified. Export permission is independently checked. Printable output escapes user text and includes all rows. A generated60-row print demonstration rendered to five A4 landscape pages, with repeated table headers and the final row verified in PDF text. This demonstration is not a user-guide update or complete print/accessibility audit.

OpenAPI now documents55 paths/71 unique operations, including planning, typed definitions, shared review and quality-report routes. Remaining functionality/security/capacity limitations are recorded in `docs/release-acceptance.en.md`. Exposed services are deferred by the owner; organization deployment belongs to the owner.

[Planning preview](../screenshots/planning-matrix-preview.png) · [Quality report](../screenshots/planning-quality-report.png) · [Shared steps on mobile](../screenshots/shared-steps-mobile.png)
