# Release acceptance — not ready

The requested full release is NOT accepted. This document records remaining release blockers; infrastructure readiness alone cannot clear them. User guides are intentionally unchanged in this iteration.

| Area | Evidence / blocker |
|---|---|
| Core cases/review/execution/defects | Focused API/browser regression passes; full transition/ownership acceptance still required. |
| Project/section/action authorization | Existing regression passes; extend to every unfinished module/report. |
| Requirement revisions and compatibility | Implemented API/UI, scoped impact, frozen bindings, stale-review rejection and old-Pass isolation. Historical/configuration baseline traceability remains. |
| Shared steps/templates/data/custom fields/views | Reviewed pinned shared steps, explicit upgrades/detach, typed templates/datasets and nested saved views implemented. Typed custom-field schemas/entity values remain a blocker. |
| Plans/configurations | Implemented dynamic scope, expiring fingerprints, idempotent freeze, case/configuration/data expansion and required/optional aggregation; focused regression/browser evidence recorded. |
| Accounts/profile | Account management/reset implemented. National-ID privacy/identity workflow, avatars and full calendar picker remain blockers. |
| Sessions | CSRF bootstrap, session/subject binding, Web Locks, cross-tab logout, previous-kid verification and live revocation tested. Full device/browser/security acceptance remains. |
| Collaboration/execution drafts | Comments, mentions, notifications, pause/resume/timing and resumable drafts remain blockers. |
| Quality/releases/reporting | Current-baseline release gates and all-row run/release print/PDF implemented. Complete historical matrices, overview printing and rich dashboard remain blockers. |
| Import/export | Validated CSV/XLSX preview/transactional import/export remains a blocker. |
| Internal exposed services | Deferred by owner for this phase; not a release blocker. |
| Deployment | Owner performs organization deployment. Native templates supplied; actual organization TLS/NTFS/LB/worker checks belong to operations, not this software-completion scope. |
| Capacity/recovery | No measured100k-account workload acceptance or restoration drill. Operations owns backups; retain encrypted chunks and key history. |

No default production credentials, real organization data or deployment access are required to keep implementing source. No organization server has been modified. `/api/ready` and `afra:check` check dependency/configuration readiness only and explicitly report incomplete release acceptance. Do not relabel unfinished modules as delivered because their generic storage/forms exist.

Scope updated21 November2026. User guides remain unchanged as requested.
