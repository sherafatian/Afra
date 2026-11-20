# Afra — standalone backend implementation specification

The full product specification is included below so this file can be handed independently to another team or AI. Implement every domain invariant, not only resource CRUD. Current code maturity is separately recorded in docs/implementation-status.en.md.

## Backend architecture

Use Laravel13 and PHP8.4 with PostgreSQL16+ and shared Redis. Normal Laravel source, composer.lock, migrations, constraints, indexes, factories/seeders, secure provisioning commands, explicit resource schemas, OpenAPI and actual PostgreSQL tests. Use controllers and validated requests at the boundary, application services for transaction workflows and policies for project/section/action authorization. Split large controllers into domain modules before declaring the architecture complete. Do not introduce microservices for this single-organization product.

Normalized core entities: users; sessions/refresh families/spent tokens/password challenges; projects/members; sections; permission catalog/roles/groups/grants/section rules; cases/revisions/checklist/review; requirements/revisions/compatibility; shared blocks/versions; templates/datasets/views/plans/config profiles/environments/builds/releases; runs/items/results/drafts/retest links; defects/history; private attachment metadata/chunks; comments/follows/notifications; audit; service catalog/clients/credentials/delegation/usage/idempotency. Use JSON for versioned snapshots and bounded typed configuration, not as a substitute for workflow constraints.

Use UUID/string identifiers at API boundaries, UTC timestamps, optimistic versions/If-Match and append-only history. Enforce foreign/project relationships and uniqueness. Lock rows for concurrent review, scope confirmation, result idempotency, refresh reuse, last-admin protection and closure. Never trust hidden frontend buttons as authorization.

JWT RS256 through a maintained library, fixed audiences/purpose and server key map; private key outside repository. User access15min, opaque rotating HttpOnly refresh7day absolute family, challenge5min, external5min. Live account/session/ACL checks. Commit replay revocation before errors. Exact Origin/CORS/CSRF and shared rate limits. Argon2id, redacted errors and request IDs. Distinct resource serializers for public members/self/admin. Do not include national IDs/birth dates in generic user lists.

Database attachments are encrypted bounded chunks with streaming reads,20MiB validation, private policy and checksums. Preserve all required encryption keys across nodes/restores. No server filesystem dependency for authoritative uploads. Provision schema/seeds without default production secrets. Load keys from secret mounts; shared DB/Redis; queue worker and scheduler documented. TLS termination/trusted proxies must be explicit. Docker development is not proof of production readiness.

Tests must cover product invariants, IDOR, all deny sources, revocation/reuse, cross-audience, concurrent writes, immutability, exact variant retest, forced closure and streamed evidence. Benchmark100k registered account indexing separately from concurrency assumptions. No credentials or personal data in logs/fixtures published as real data.

---

# Afra — product specification, edition 5

19 November 2026. This specifies the required finished product; it does **not** assert that every feature is implemented. See [implementation status](../implementation-status.en.md). Historical HTML is an offline design reference, not an authenticated application.

## Delivery and deployment assumptions

Afra (maple) follows the tree naming convention and uses vivid orange branding. Build conventional editable Laravel 13 REST source in `BackEnd` and separate React/TypeScript/Ant Design source in `FrontEnd`. Include migrations, constraints/indexes, baseline seeds, secure administrator provisioning, tests, OpenAPI, Persian/English documents, user guides and Docker/ordinary setup. Do not substitute an iframe or browser-local store for the real application.

One organization has many projects and teams. Expect **10,000–100,000 registered accounts**, not simultaneous users. Establish concurrency and throughput by measurement. Several frontend/backend nodes may sit behind organization load balancers. Use shared PostgreSQL, Redis, keys, session revocation, cache, queue, rate limits and locks; no node-local authoritative state. Operations owns backups; document database consistency, encryption/signing key preservation and restoration. No organization-server connection is needed now. Attachment allowance is 20 MiB per file, preferably encrypted chunks in the database; history and attachments are retained indefinitely.

All accepted manual-QA capabilities remain required. No Jira/GitLab connectors, SSO, webhook, automated-result ingestion or CI/CD integrations. The explicitly requested internally exposed APIs remain in scope. There is no legacy migration dataset now; retain the product’s import facility.

## Language, calendar, theme and layout

Persian and English initially, extensible translation keys, RTL/LTR according to language. Every displayed/selectable date is Solar Hijri in **both languages**. Exchange date-only ISO `YYYY-MM-DD` and UTC RFC3339 timestamps; interpret inclusive date filters and expiry in Asia/Tehran. Never parse a localized display as an API date.

Persist light/dark/system choice; follow OS changes in system mode. Dark mode includes background, surfaces, menus, dialogs, inputs and tables. Vivid orange, readable slightly larger type and accessible contrast; avoid unnecessarily enlarging controls. Application Persian uses Vazirmatn. Word guides use **Vazir for Persian and Cambria for Latin**, with cover, Afra logo, headers/footers and native Word TOC.

Seven primary areas consolidate related capabilities:

| Area | Contents |
|---|---|
| Overview | KPIs, my work, attention queue, trends, recent activity |
| Tests & requirements | Cases, requirements, shared steps, reviews, templates, datasets, saved views |
| Planning | Plans, configuration profiles, environments, builds |
| Execution & history | Runs, assigned items, attempts, evidence, linked retests |
| Defects | Lifecycle, retest, verified and forced closure |
| Quality & releases | Releases/gates, traceability/configuration matrices, reports |
| Project & people | Projects, members, users, roles/groups/action ACL/section restrictions, audit, import/export, custom fields, system services/usage |

Use one header avatar popup (profile, password, appearance/language, logout); remove duplicate or broken sidebar-footer controls. Remove meaningless `AFRA / TEAM` labels. Calendar icon occupies its own cell; date text is LTR. Avatar buttons wrap independently. Switches represent binary settings; checkboxes represent multiple selection. Group/search permissions with distinct inherit/allow/deny icons and accessible labels, retaining hidden edited values. Widen desktop tree to about 290px and allow collapse.

Mobile: four primary bottom-navigation entries plus More, bottom sheets with grip, focus management, inert background, independent scrolling, sticky actions, safe areas and keyboard/visual-viewport handling. At least 44px targets and 16px inputs. Checklist layout separates number, action, expected result and outcome controls without one-character wrapping.

## Projects, cases and review

Projects have names, descriptions, archive state and optimistic version. Sections form a project-owned tree; reject cycles/cross-project parents/depth >8. Archive preserves history and prevents writes. Members have project administrator, tester, reviewer/viewer or custom roles. Cases can be assigned to a project member and substitute.

A case contains title, section, priority critical/high/medium/low, type functional/regression/smoke/security/performance, author/assignee/substitute, preconditions, overall expected result, ordered checklist, tags, estimate 1–1440 minutes, variables, dataset, requirement links and custom fields. **Every checklist item has action, its own expected result and required flag.** Templates preserve all three. Put Add step below existing rows.

Content changes append immutable revisions and return to draft. Independent review is mandatory; authors cannot approve their revision. Review requests pin exact case/requirement versions. Edits while pending invalidate approval. Record reason/author/reviewer/time. Never rewrite approved content or existing run snapshots.

Shared blocks have immutable, independently reviewed versions. Pin an approved version in a case. Block edits do not silently update consumers; explicit updates create new case drafts and preserve local steps. Detach copies steps locally. Archive retains consumed history.

## Requirements and impact

Requirement content revision differs from target product/release version. The compatibility identity is `(case_id,case_revision,requirement_id,requirement_revision)`. Changes flag impacted cases. An independent reviewer may confirm compatibility with reason for that exact tuple, or request a revised case. Compatibility does not turn an old Pass into verification of a changed requirement. Traceability distinguishes uncovered, planned, not run, failed, blocked, passed and stale/unverified. Historic reports use versions actually tested.

## Views, planning, configuration and data

Saved views are personal/team views with typed allowlisted AND/OR conditions (title, priority, type, assignee, section descendants, tags, review state, requirement, custom fields), ordering and columns. Enforce authorization server-side; never accept SQL.

Plans contain explicit cases or a dynamic saved view. Reevaluate dynamic scope for preview, then freeze at run creation. Preview/confirmation uses a short-lived version/scope fingerprint. Changes to membership/source revisions/configuration/schema between preview and confirmation require refresh.

Configuration rows specify environment/build/browser/OS/device/dataset mode (none/case/explicit) and required/optional state. Expand cases × configurations × data rows; 2×2×2 means **8 items and 2 unique cases**. Required variants cannot be deselected. Dataset values override case defaults. Unresolved `{{variables}}` and invalid required typed values block creation. Show expansion preview; baseline safeguards are 20 configuration rows and 1000 items.

Freeze source references and materialized content, data, schema, requirement bindings and configuration per execution item. Later edits never change the snapshot.

## Execution and defects

Execution supports assignee/substitute, per-user/item drafts, pause/resume, time, comments and evidence. Step states Pass/Fail/Skipped/Not run; overall Pass/Fail/Blocked/Skipped. Required steps must Pass for overall Pass; optional skip needs explanation. Fail identifies failed step(s); Blocked/Skipped need reason. Select all Pass changes a draft only; **Record result appends an immutable attempt** with request idempotency.

Aggregate latest applicable results per baseline variant and linked same-key retests. Untested/failed required variants prevent all-Pass. Latest Not run must not resurrect older Pass. Keep unique-case and item denominators distinct.

Defects originate from an actual recorded failure and pin run/item/result, not just case ID. Include severity, description, reproduction/evidence, owner, lifecycle open → in fix → resolved → closed, plus reopen/deferred/rejected where authorized. Show history description below actor.

Details must visibly offer Retest with disabled explanations. Assigned/substitute testers need retest permission, not plan-management permission. Retest the exact original item/configuration. Normal closure requires a linked recorded Pass for the same defect, strictly after `resolved_at`; drafts, old/unrelated Pass and wrong variants are insufficient. Explain the next action plainly.

Separate `defects.force_close` defaults to system/project administrators and is grantable. Require confirmation and reason 10–1000 characters; disallow closed defects/archived projects. Store closure type forced, actor/time/reason/bypassed conditions, `verified_at=null`, audit and history. Never create a synthetic Pass. Reports and release gates distinguish unverified forced closure. Reopen/later closure retain old history.

## Quality, evidence, collaboration and import

Release gates consider critical open defects, approved coverage, required variants, fresh requirement verification and latest results. Explain unmet criteria and sources; forced closure cannot imply verified quality.

Comments/replies/mentions/follows/internal notifications obey authorization; no unsolicited email/SMS integration. Structured activity filters operation, actor, entity, inclusive Shamsi interval and search; default page size20. Preserve source/actor/project/section snapshots and avoid secrets/PII in logs.

Private attachments ≤20 MiB: validate actual MIME/signature/name, reject active HTML/SVG/executable files, store indexed checksum/metadata and encrypted database chunks, stream downloads with authorization/no-sniff/safe disposition/private caching. Recorded-result evidence is immutable. Keys are part of recoverability, not database backup alone.

CSV/XLSX import has validation preview and explicit valid-row confirmation, nested sections, checklist JSON preserving expected/required, and `CF:key` columns; baseline500 rows/2MiB. Detect duplicates. Exports for cases/reports/matrices escape spreadsheet formula injection. No mandatory legacy import at setup.

Custom field types: text, textarea, number, date, boolean, select, multiselect on case/requirement/plan/run/defect. Key/type/entity immutable once used; schema versioned/frozen in runs. Required false/zero remain valid; used options cannot be removed. Disable preserves history.

## Users, access and credentials

User fields: name, normalized unique email, private avatar, mobile, birth date, Iranian national ID, department/job title/employee code/bio, active/expiry. National ID is a leading-zero-preserving string; ten-digit checksum, reject repeated digits, encrypted value and keyed unique fingerprint. Format checking is not government identity verification. Do not expose sensitive profile fields in project lists/JWTs.

Self-edit name/mobile/birth/avatar/bio, and first-fill national ID once; later corrections need administrator. Organization/email/role/active/expiry/grants remain administrative. Reject forbidden fields; reject future birth. Expiry ends selected day in Tehran and applies to existing sessions/delegation immediately. Prevent self-lockout and removal/restriction of last permanent active system admin under serialized transaction.

Avatar JPEG/PNG/WebP≤512KiB, genuine decode≤16 megapixels, strip metadata, reencode256×256, private retrieval. Clear, wrapping photo controls.

Initial password assigned by admin; force change defaults on. Own change requires current/new/confirmation, 12–128 characters and different value; tuned Argon2id. Admin reset: reason, temporary password expiring24h, mandatory change, session revocation. Never display old password, log credentials, commit fixed production passwords or automatically send them.

Catalog actions are immutable keys. Role/custom role/active groups/direct grants use inherit/allow/deny. Any source’s explicit deny wins; direct allow cannot bypass group deny. Category deny restricts children. Section rules resolve root→leaf nearest explicit values, with effective section denial overriding action allowance. Membership/view/ownership constraints remain mandatory. System override follows live account/session checks.

Access managers cannot grant beyond their authority, promote system administrators or create system rights through project groups. Audit changes. Capabilities endpoint aids UI; server rechecks actual actions. Guessed IDs must not disclose nested resources across projects or hidden sections.

## JWT and service security

Use maintained JWT library, fixed RS256 and server-selected kid/key map. Access15min only in React memory; claims issuer, aud=afra-internal, subject,jti,sid,iat,nbf,exp,auth_version,acl_version,token_use. No sensitive profile fields; live permissions authoritative.

Refresh opaque≥256bits, hash-only storage, HttpOnly/Secure cookie restricted `/api/auth`, absolute7-day family. Transactional rotation and all-generation replay detection revoke family; commit revocation before responding error. Origin allowlist and CSRF nonce required for refresh/logout, safe CSRF bootstrap and cross-tab refresh serialization. No tokens in localStorage. Password-change login uses separate five-minute audience challenge, with no full product session until change. Check account/status/expiry/revocation/security versions every request. Shared IP/account login limits, generic credential errors, exact CORS/trusted proxies, TLS, request IDs, redacted errors.

System-admin service catalog initially disabled:

* cases.create — POST `/api/external/v1/test-cases`, draft requiring review.
* reports.read — GET `/api/external/v1/test-progress?project_id=…`, **per-project progress**; required project, optional plan/release/tester/interval.

Clients have hashed once-revealed secrets, projects/scopes/expiry/rate1–600 per minute(default60), immediate rotation/revocation. ExternalJWT5min, separate audience, no refresh/interchange with internal. Fixed handlers, no arbitrary executable endpoints/URLs.

One-hop administrator-managed delegation A→B narrows client/projects/scopes/expiry/reason. Effective rights intersect live A,B,client,delegation,service. Both users must exist/active; no unknown-subject admin fallback. Exchange authenticates client + B subject token + delegation ID. Result sub=A, act.sub=B, act.client_id. Machine credentials have separate policy and cannot fabricate human authors. Revocation/ACL changes apply immediately. Creation idempotency24h: same body replay, different body409, reauthorize replay.

Usage logs one deduplicated event/request including success/4xx/5xx/429 and auth failures: requestID,time,duration,service/client/project/delegation,safe outcome. No bodies/headers/tokens/secrets/PII. Baseline telemetry retention90days configurable; product audit/evidence/history indefinite. Admin-only filters Tehran inclusive interval/service/client/project/outcome/requestID/error. Aggregate **all filtered rows before pagination**: total,2xx%,4xx/5xx/other,median (mean middle pair),nearest-rankp95,daily/service breakdown. Synthetic examples are visibly distinct from actual usage.

## Overview and paper reports

Six KPIs with denominators, baseline-variant progress, fresh verified coverage, critical defects, ready releases, actual recent-six-run trends, mywork/teamload, pending reviews/requirement impacts/stale results/forced closure/invalid assignee queue.

Separate data-driven print layout, report-title/A4 orientation/detail/chart preview, all filtered rows (not current page), repeated headers, meaningful breaks, no clipped matrices (labeled column groups), logo/filters/preparer/Shamsi timestamp/reference. Execution prints revision/build/result actor/time. High-contrast light paper in dark UI, no secrets/forms, restore title/UI after print. Verify actual pagination/page-number support in chosen engine.

## Contract, integrity and acceptance

Versioned `/api/v1`, `/api/auth`, `/api/external/v1`, `/api/external/token`. String IDs, bounded pagination25/max100, typed allowlisted filters/sort, localized field errors/stable codes/requestIDs. 401 invalid auth;403 denied;404 invisible;409/412 conflicts;422 validation;429 Retry-After. Mutable versions/ETag/conditional writes preserve entered UI data on conflicts. Revisions/results/audit append-only. Serialize review/scope confirmation/result idempotency/refresh/admin protection/closure using transactions, locks and uniqueness constraints.

Test project/section IDOR, all grant sources/deny precedence, ownership, self-review/stale revisions, preview races, expired accounts, password challenge/refresh replay/CSRF/audience, revoked delegation, duplicates, required variants, exact-item retest and force closure without Pass, typed false/zero,20MiB authorized streaming, Shamsi boundaries. Run PostgreSQL feature tests, frontend typecheck/build and actual browser flows across mobile/desktop/RTL/LTR/dark/keyboard/print. Guides use real screenshots, label prototypes. Document measured limits honestly. A generic record editor or prototype screenshot does not establish finished production behavior.


## Complete target permission catalog

| Key | Action |
|---|---|
| `project.view` | View project |
| `project.update` | Edit project |
| `cases.create` | Create case |
| `cases.update` | Edit case |
| `cases.delete` | Delete/archive case |
| `cases.assign` | Assign case |
| `cases.export` | Export cases |
| `cases.import` | Import cases |
| `sections.manage` | Manage sections |
| `cases.review` | Review cases |
| `shared.manage` | Manage shared steps |
| `templates.manage` | Manage templates and data |
| `views.manage` | Manage team views |
| `requirements.create` | Create requirement |
| `requirements.update` | Edit requirement |
| `requirements.delete` | Delete requirement |
| `plans.create` | Create plan |
| `plans.update` | Edit plan |
| `plans.delete` | Delete plan |
| `runs.create` | Start execution |
| `runs.execute` | Record assigned results |
| `runs.pause` | Pause/resume execution |
| `runs.retest` | Retest assigned cases |
| `defects.create` | Create defect |
| `defects.update` | Edit defect |
| `defects.transition` | Transition defect |
| `defects.close` | Close verified defect |
| `defects.force_close` | Force close without retest |
| `releases.manage` | Manage releases |
| `environments.manage` | Manage environments and builds |
| `reports.read` | View reports and coverage |
| `reports.export` | Export reports |
| `attachments.upload` | Upload evidence |
| `attachments.delete` | Delete permitted attachments |
| `comments.write` | Write comments |
| `members.manage` | Manage project members |
| `access.manage` | Manage project access |
| `fields.manage` | Manage custom fields |
| `system.users.manage` | Manage system users |
| `system.services.manage` | Manage services and delegation |
| `sections.create` | Create section |
| `sections.update` | Edit section |
| `sections.delete` | Delete section |
| `shared.create` | Create shared block |
| `shared.update` | Edit shared block |
| `shared.archive` | Archive shared block |
| `shared.request_review` | Request block review |
| `shared.update_cases` | Update cases from shared block |
| `templates.create` | Create template |
| `templates.update` | Edit template |
| `datasets.create` | Create dataset |
| `datasets.update` | Edit dataset |
| `profiles.create` | Create execution profile |
| `profiles.update` | Edit execution profile |
| `releases.create` | Create release |
| `releases.update` | Edit release |
| `environments.create` | Create environment |
| `environments.update` | Edit environment |
| `builds.create` | Create build |
| `builds.update` | Edit build |
| `members.add` | Add project member |
| `members.update` | Edit membership |
| `members.remove` | Remove membership |
| `roles.create` | Create custom role |
| `roles.update` | Edit custom role |
| `roles.delete` | Delete custom role |
| `groups.create` | Create access group |
| `groups.update` | Edit group and members |
| `grants.update` | Edit direct grants |
| `section_access.manage` | Edit section access |
| `fields.create` | Create custom field |
| `fields.update` | Edit custom field |
| `fields.archive` | Disable custom field |
| `cases.request_review` | Request case review |
| `shared.approve` | Approve shared block |
| `requirements.confirm_compatibility` | Confirm requirement compatibility |
