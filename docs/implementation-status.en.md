# Implementation status — 20 November 2026

**Not a production release. The full requested implementation is not finished.** The normative product specification intentionally describes more than this first real application delivers. Do not deploy it as a complete secure system. Historical HTML v4 is a functional offline design reference; it does not provide backend security.

## Delivered in real source

* Laravel13/PHP8.4 application, PostgreSQL migrations, baseline permission-catalog seed with no default user, hidden-prompt administrator provisioning and RSA-key generation.
* Separate React19/TypeScript/Ant Design application using actual REST endpoints, orange branding, Persian/English labels, RTL/LTR, light/dark/system preferences, project selector, seven navigation groups, mobile navigation and drawer layout.
* JWT RS256 login, distinct five-minute password-change challenge, fifteen-minute user access, hashed opaque refresh rotation and spent-token revocation, account/session checks, password change and basic administrator user/reset APIs.
* Projects, section creation/tree, cases/checklist expected results and required flags, immutable content revisions, independent review, optimistic case edits, approved-case run creation, frozen items and append-only/idempotent results.
* Defect creation from failed items, linked exact-item retest API/UI, resolution, conditional normal closure and reasoned forced closure without a synthetic Pass.
* Canonical77-action catalog; project roles/custom roles, editable groups and direct grants, versioned section rules, effective capabilities and constrained access-management APIs/UI. Profile name/mobile/bio, searchable member selection, project activity filters/API and basic dashboard counts.
* Private encrypted database chunks with20MiB limit; real upload/selection UI, transactional immutable result-evidence binding, attempt history and authenticated download. Primary/substitute assignment has an independent optimistic version and does not invalidate approved content or change existing runs.
* General/backend/frontend standalone Persian/English specifications; bilingual setup/runbooks and QA/user guides; actual React screenshots; native Word TOC and embedded Vazir fonts with Cambria declared for Latin.

## Explicitly incomplete

* Comprehensive authorization/security acceptance across all future domain workflows and reports. Current project/section filtering and authority-bounded role/group/direct-grant updates have focused regression coverage; this does not constitute a full security audit.
* Full calendar date picker (current account expiry accepts validated Solar Hijri text), national-ID encryption/checksum/fingerprint and profile identity workflow, avatars/reencoding, full profile identity/avatar UI, CSRF bootstrap/cross-tab refresh, signing-key rotation and complete localized error contract.
* Requirement revisions/compatibility/impact/traceability; independently reviewed pinned shared steps and consumer updates; rich templates/datasets/custom-field schema validation/versioning; dynamic saved-view planning, preview fingerprints, configuration/data expansion, required-variant aggregation.
* Generic record storage/forms exist for these domains but **are not completed workflows** and must not be represented as such.
* Execution drafts/pause/resume/timing, comments/mentions/follows/notifications; complete transition matrix; release gates and richer actionable/trend/coverage dashboards.
* CSV/XLSX import/export; professional all-filtered-data PDF/print (current print is basic browser printing).
* Exposed service handlers/client credentials/external audience/delegation/idempotency/usage telemetry and reports are **not implemented**. No fake insecure external endpoint has been supplied.
* Production PHP-FPM/reverse-proxy/worker/scheduler packaging, readiness, comprehensive security/integration/accessibility/print/load tests and measured100k-account performance.

## Verification

Real PostgreSQL17 feature suite:38 tests,324 assertions passed (includes two framework smoke tests). Coverage includes project/section isolation, independent/stale review, frozen snapshots, idempotency, inactive/expired accounts, group/category deny precedence, privilege restoration bounds, optimistic membership/assignment/rule edits, substitute execution, independent evidence-read denial, encrypted20MiB upload/stream roundtrip, immutable evidence, UTC metadata, password-challenge cancellation and refresh replay revocation. Seven frontend tests pass, including single-flight renewal and prevention of stale refresh/write replay after an identity change. This is focused regression coverage, not a comprehensive security audit.

React TypeScript/Vite build passed. Docker PHP8.4 build passed using temporary BuildKit CA secret for the environment; the CA was not baked into the image. Docker CLI server is development-only. Browser checks/screenshots use local actual application and synthetic training data; detailed results are in validation documents. No organization server was accessed. No production credentials or keys are included.

Complete the listed gaps against the full specification and pass acceptance tests before production deployment. A successful core test suite is not evidence that unimplemented modules exist.

## Verified browser continuation

Synthetic training flows exercised group/custom-role editing, membership role changes, independent review and primary/substitute assignment. Assigned-tester evidence upload → recorded result → history download completed with matching SHA256 bytes and no page errors or failed API requests. Persian mobile and English access editors passed viewport/footer checks. Dates in server metadata are explicit UTC; browser checks used Asia/Tehran. Main data, capabilities and evidence reject stale responses when their workspace/context changes. Account administration, service consumption, advanced planning and professional reports remain unfinished as listed above.

## Organization account continuation

System administrators now open Organization accounts from the header popup, even before creating a project. Search/pagination, regular account creation, active state, system administrator status, inclusive Tehran expiry, optimistic account edits and24-hour forced password reset are available in API/UI. Creation never grants project membership. Audit snapshots exclude password material. Security updates/reset revoke sessions; self-lockout and loss of the last permanent administrator are blocked. Password changes serialize against administrator resets. National-ID/profile identity fields and avatars remain incomplete.

Signing-key generation now bundles a minimal OpenSSL configuration, checks generation/export/write failures, serializes concurrent generation and refuses to overwrite either existing key. RSA sign/verify and refusal tests pass on the Linux development host; native Windows execution has not been run here. Set an explicit readable configuration with `php artisan afra:keys --config=...` if a custom OPENSSL_CONF is invalid.

User guides were intentionally not updated in this iteration.

Real-browser account flow passed: ordinary account creation, Persian-digit expiry roundtrip, disable/reenable, reset, mobile width/footer and forced temporary-password change. No page errors or failed API requests. Repeat only on synthetic data with `FrontEnd/tests/system_accounts.py`.
