# Release acceptance — not ready

The requested full release is NOT accepted. This document records remaining release blockers; infrastructure readiness alone cannot clear them. User guides are intentionally unchanged in this iteration.

| Area | Evidence / blocker |
|---|---|
| Core cases/review/execution/defects | Focused API/browser regression passes; full transition/ownership acceptance still required. |
| Project/section/action authorization | Existing regression passes; extend to every unfinished module/report. |
| Requirement revisions and compatibility | Implemented API/UI, scoped impact, frozen bindings, stale-review rejection and old-Pass isolation. Historical/configuration baseline traceability remains. |
| Shared steps/templates/data/custom fields/views | Full reviewed versions, consumer updates and typed workflows remain blockers. |
| Plans/configurations | Dynamic scope, preview fingerprints, case/configuration/data expansion and required-variant aggregation remain blockers. |
| Accounts/profile | Account management/reset implemented. National-ID privacy/identity workflow, avatars and full calendar picker remain blockers. |
| Sessions | CSRF bootstrap, session/subject binding, Web Locks, cross-tab logout, previous-kid verification and live revocation tested. Full device/browser/security acceptance remains. |
| Collaboration/execution drafts | Comments, mentions, notifications, pause/resume/timing and resumable drafts remain blockers. |
| Quality/releases/reporting | Release gates, historical matrices, rich reports and complete print/PDF remain blockers. |
| Import/export | Validated CSV/XLSX preview/transactional import/export remains a blocker. |
| Internal exposed services | Clients, credential handling, scope/project intersection, delegation, idempotency and actual usage reports remain blockers. |
| Deployment | Native Nginx/FPM, Supervisor and IIS/FastCGI/ARR templates supplied. Actual server/TLS/NTFS/worker acceptance not executed. |
| Capacity/recovery | No measured100k-account workload acceptance or restoration drill. Operations owns backups; retain encrypted chunks and key history. |

No default production credentials, real organization data or deployment access are required to keep implementing source. No organization server has been modified. `/api/ready` and `afra:check` check dependency/configuration readiness only and explicitly report incomplete release acceptance. Do not relabel unfinished modules as delivered because their generic storage/forms exist.
