# Implementation status — 21 November 2026

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
* Full calendar date picker (current account expiry accepts validated Solar Hijri text), national-ID encryption/checksum/fingerprint and profile identity workflow, avatars/reencoding, full profile identity/avatar UI, complete localized error contract and full production/browser acceptance.
* Complete historical/as-of traceability and requirement/configuration matrices; custom-field schema validation/versioning and entity-value workflows. Reviewed pinned shared steps, typed templates/datasets, dynamic views, preview fingerprints and variant planning are now implemented below.
* Generic record storage/forms exist for these domains but **are not completed workflows** and must not be represented as such.
* Execution drafts/pause/resume/timing, comments/mentions/follows/notifications; complete transition matrix and richer actionable/trend/coverage dashboards. Current-baseline release gates are implemented below.
* Validated CSV/XLSX preview/import/export. Run/release quality reports now have dedicated all-row print/PDF; overview printing and other report types remain incomplete.
* Exposed services, client credentials, delegation and usage reports are deferred by the owner for this phase and are not release blockers. Their UI navigation is removed.
* Comprehensive security/integration/accessibility/load acceptance and measured100k-registered-account performance. Native deployment templates and readiness probes exist; organization installation, TLS, load balancer and operations checks belong to the owner.

## Verification

Real PostgreSQL17 feature suite:69 tests,817 assertions passed (includes two framework smoke tests). Coverage includes project/section isolation, independent/stale review, frozen snapshots, idempotency, inactive/expired accounts, group/category deny precedence, privilege restoration bounds, optimistic membership/assignment/rule edits, substitute execution, independent evidence-read denial, encrypted20MiB upload/stream roundtrip, immutable evidence, UTC metadata, password-challenge cancellation and refresh replay revocation. Thirteen frontend tests pass, including single-flight renewal and prevention of stale refresh/write replay after an identity change. This is focused regression coverage, not a comprehensive security audit.

React TypeScript/Vite build passed. Docker PHP8.4 build passed using temporary BuildKit CA secret for the environment; the CA was not baked into the image. Docker CLI server is development-only. Browser checks/screenshots use local actual application and synthetic training data; detailed results are in validation documents. No organization server was accessed. No production credentials or keys are included.

Complete the listed gaps against the full specification and pass acceptance tests before production deployment. A successful core test suite is not evidence that unimplemented modules exist.

## Verified browser continuation

Synthetic training flows exercised group/custom-role editing, membership role changes, independent review and primary/substitute assignment. Assigned-tester evidence upload → recorded result → history download completed with matching SHA256 bytes and no page errors or failed API requests. Persian mobile and English access editors passed viewport/footer checks. Dates in server metadata are explicit UTC; browser checks used Asia/Tehran. Main data, capabilities and evidence reject stale responses when their workspace/context changes. Account administration, service consumption, advanced planning and professional reports remain unfinished as listed above.

## Organization account continuation

System administrators now open Organization accounts from the header popup, even before creating a project. Search/pagination, regular account creation, active state, system administrator status, inclusive Tehran expiry, optimistic account edits and24-hour forced password reset are available in API/UI. Creation never grants project membership. Audit snapshots exclude password material. Security updates/reset revoke sessions; self-lockout and loss of the last permanent administrator are blocked. Password changes serialize against administrator resets. National-ID/profile identity fields and avatars remain incomplete.

Signing-key generation now bundles a minimal OpenSSL configuration, checks generation/export/write failures, serializes concurrent generation and refuses to overwrite either existing key. RSA sign/verify and refusal tests pass on the Linux development host; native Windows execution has not been run here. Set an explicit readable configuration with `php artisan afra:keys --config=...` if a custom OPENSSL_CONF is invalid.

User guides were intentionally not updated in this iteration.

Real-browser account flow passed: ordinary account creation, Persian-digit expiry roundtrip, disable/reenable, reset, mobile width/footer and forced temporary-password change. No page errors or failed API requests. Repeat only on synthetic data with `FrontEnd/tests/system_accounts.py`.

## Requirement and session continuation

Dedicated requirement API/UI now replaces generic requirement forms: acceptance criteria, separate product/content versions, immutable revisions, optimistic edits, archival, per-case-revision links, scoped change impact and independent compatibility confirmation. Review submission pins requirement scope; an intervening change prevents approval. New runs freeze compatible exact requirement revisions. A historic Pass cannot verify a newer requirement after compatibility confirmation. Current coverage is per case/exact requirement revision; full plan/configuration baseline aggregation and historical matrices remain unfinished. Hidden-section cases/results are filtered from impact.

Browser sessions now recover through origin-checked CSRF bootstrap and opaque refresh rotation, coordinated with Web Locks across modern same-origin browser tabs. Session/subject binding prevents a previous user's retry from crossing into another login. BroadcastChannel clears other tabs on explicit login/logout. Cookie-authenticated logout revokes a family even with an invalid/expired access token; network failure does not falsely clear the interface as a successful logout. Production refresh cookie uses the __Secure- prefix. Configured previous public keys support explicit kid rotation. API responses use no-store/security headers/request IDs; bounded proxy trust is configurable. Actual browser checks passed reload recovery, two-tab recovery and propagated logout.

`php artisan afra:check --production` checks infrastructure/configuration; `/api/ready` checks dependencies/current migrations and omits diagnostic paths publicly. Both explicitly retain incomplete release acceptance. These checks do not replace the outstanding functional, security, load and deployment acceptance. User guides were not changed.

Native deployment templates and topology/permissions notes are in deploy/README.en.md and deploy/README.fa.md. Nginx/FPM and Supervisor examples are unexecuted templates; IIS XML is well formed but IIS/ARR runtime acceptance was not performed. The explicit release blocker register is docs/release-acceptance.en.md.

Refresh now requires both expected_session_id and expected_user_id; legacy clients without binding are rejected rather than silently switching subjects. Coordinate frontend/backend deployment. Project selection now supports scoped server search beyond the first page; malformed email arrays produce validation errors instead of server errors.


## Typed planning and shared-step continuation — 21 November 2026

Implemented actual API/UI workflows for templates (expected result per checklist item), strictly typed datasets (including false/zero/date), nested personal/team saved views, environment/build references, configuration matrices, plans and release policies. Lightweight record selectors/listing omit large definitions; immutable record history is paginated. Archived records cannot be newly consumed. Internal exposed services are excluded from this phase.

Plan preview expands approved cases × configuration rows × data rows, with bounded materialized snapshots and exact source versions. Confirmation is actor/project bound, expires after five minutes, rejects changed dependencies/ACL, retains all required variants and is idempotent for identical confirmation. Execution freezes steps, variable values, configuration, requirements and selected rows. Data dates display Solar Hijri while preserving ISO source values.

Shared steps have submit/independent approve/reject, exact revision pins, scoped consumer lists and explicit case-draft upgrades. Detachment preserves combined order, including multiple blocks at one boundary. Editing a shared block does not change a consumer or past execution. Current case input separates local from resolved steps; unapproved, cross-project or forged sources are rejected. An existing archived pin may remain in a new case revision, but new consumers cannot attach it.

Run/release reports aggregate required/optional variants, use database observation/baseline ordering and only explicitly linked retests. Release readiness checks source freshness, complete visibility, required execution completion, pass threshold, blocked items and critical/forced-closed defects. Force close never records a synthetic Pass. Printing opens an escaped dedicated document containing all report rows; a 60-row demonstration produced five landscape A4 pages. Historical as-of matrices and a comprehensive dashboard remain unfinished.

User guides are unchanged. These additions do not clear the remaining release blockers in release-acceptance.en.md.
