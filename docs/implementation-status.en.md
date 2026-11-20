# Implementation status — 20 November 2026

**Not a production release. The full requested implementation is not finished.** The normative product specification intentionally describes more than this first real application delivers. Do not deploy it as a complete secure system. Historical HTML v4 is a functional offline design reference; it does not provide backend security.

## Delivered in real source

* Laravel13/PHP8.4 application, PostgreSQL migrations, baseline permission-catalog seed with no default user, hidden-prompt administrator provisioning and RSA-key generation.
* Separate React19/TypeScript/Ant Design application using actual REST endpoints, orange branding, Persian/English labels, RTL/LTR, light/dark/system preferences, project selector, seven navigation groups, mobile navigation and drawer layout.
* JWT RS256 login, distinct five-minute password-change challenge, fifteen-minute user access, hashed opaque refresh rotation and spent-token revocation, account/session checks, password change and basic administrator user/reset APIs.
* Projects, section creation/tree, cases/checklist expected results and required flags, immutable content revisions, independent review, optimistic case edits, approved-case run creation, frozen items and append-only/idempotent results.
* Defect creation from failed items, linked exact-item retest API/UI, resolution, conditional normal closure and reasoned forced closure without a synthetic Pass.
* Basic project roles, direct grants, group-deny evaluation/group-create API, profile name/mobile/bio, member listing/assignment, project activity filters/API, dashboard counts.
* Private encrypted database-chunk upload/download API with20MiB limit. No complete evidence UI yet.
* General/backend/frontend standalone Persian/English specifications; bilingual setup/runbooks and QA/user guides; actual React screenshots; native Word TOC and embedded Vazir fonts with Cambria declared for Latin.

## Explicitly incomplete

* Full76-action catalog alignment, custom roles, editable groups/section access, complete capability endpoints and consistent section authorization/filtering across every list/report/nested action; complete delegated access-manager constraints. **Security acceptance blocked.**
* Account expiry as an inclusive Tehran date picker, national-ID encryption/checksum/fingerprint and profile identity workflow, avatars/reencoding, complete user UI,24-hour temporary reset expiry, login account/IP rate limiter, CSRF bootstrap/cross-tab refresh, signing-key rotation and complete localized error contract.
* Requirement revisions/compatibility/impact/traceability; independently reviewed pinned shared steps and consumer updates; rich templates/datasets/custom-field schema validation/versioning; dynamic saved-view planning, preview fingerprints, configuration/data expansion, required-variant aggregation.
* Generic record storage/forms exist for these domains but **are not completed workflows** and must not be represented as such.
* Execution drafts/pause/resume/timing/substitutes, immutable evidence bindings/UI, comments/mentions/follows/notifications; complete transition matrix; release gates and richer actionable/trend/coverage dashboards.
* CSV/XLSX import/export; professional all-filtered-data PDF/print (current print is basic browser printing).
* Exposed service handlers/client credentials/external audience/delegation/idempotency/usage telemetry and reports are **not implemented**. No fake insecure external endpoint has been supplied.
* Production PHP-FPM/reverse-proxy/worker/scheduler packaging, readiness, comprehensive security/integration/accessibility/print/load tests and measured100k-account performance.

## Verification

Real PostgreSQL17 feature suite:15 tests,145 assertions passed during development (includes two framework smoke tests). Covered project isolation, independent review, immutable snapshot, result idempotency/conflict, immediate inactive/expired account rejection, group deny precedence, forced closure without Pass, separate password challenge, and committed refresh-replay family revocation. This is focused core coverage, **not** a comprehensive security audit.

React TypeScript/Vite build passed. Docker PHP8.4 build passed using temporary BuildKit CA secret for the environment; the CA was not baked into the image. Docker CLI server is development-only. Browser checks/screenshots use local actual application and synthetic training data; detailed results are in validation documents. No organization server was accessed. No production credentials or keys are included.

Complete the listed gaps against the full specification and pass acceptance tests before production deployment. A successful core test suite is not evidence that unimplemented modules exist.

## Verified continuation update

Section restrictions now filter case lists, execution items, defects, audit and dashboard counts; execution items retain their section context. Hidden-section evidence is denied. Result evidence is selected from an item upload and bound transactionally to one immutable result; idempotent replay cannot change/rebind it. Upload/selection and result-history downloads have been added to React; final real-browser verification of this new evidence UI is still pending. Named login/account/IP and API limiters use separate namespaces. Remaining catalog alignment, access-management UI and comprehensive security acceptance are still open.
