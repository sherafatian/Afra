# Setup and operations

This is an implementation in progress, **not a production release**. See `docs/implementation-status.en.md` before using it. Do not expose development servers to an organization network.

## Conventional source setup

Prerequisites: PHP8.4 with PDO PostgreSQL, intl, mbstring, openssl, sodium, fileinfo, tokenizer, XML/DOM and zip; Composer2; PostgreSQL16+; Redis7; Node22.12+ and npm. Sources are editable Laravel and React applications.

1. In `BackEnd`, run `composer install` (include development dependencies for tests).
2. Copy `.env.example` to `.env`; generate a unique DB password and set database credentials. Create `afra` and a **separate disposable** `afra_test` database. Never point tests at real data.
3. Run `php artisan key:generate`, then `php artisan afra:keys`. Keep `.env`, `storage/keys/private.pem` and backups outside Git. Key generation refuses overwrite.
4. Run `php artisan migrate --seed`. There is no fixed production administrator password. Run `php artisan afra:admin admin@example.org 'Administrator'` and enter a strong initial password at its hidden prompt.
5. Start Redis/PostgreSQL. Run `php artisan serve --host=127.0.0.1 --port=8000` for local development only.
6. In `FrontEnd`, run `npm ci` and `npm run dev`. Open http://localhost:5173. Vite proxies `/api` to localhost8000. Production uses a reverse proxy on a controlled same-site origin.
7. First administrator login requires password change and signs out afterward. Sign in again. Create a project; create another account using system API until the full account-management UI is finished; add an independent reviewer. Cases cannot be self-approved.

`npm run build` produces `FrontEnd/dist`. Serve this with an ordinary static server and SPA fallback; route `/api` to Laravel’s public entry point. Keep PHP source and secrets outside webroot. The included Docker CLI server is **development only**, not a hardened production PHP-FPM configuration.

## Docker local setup

Create root `.env` containing only a newly generated `DB_PASSWORD`; create `BackEnd/.env` as above with matching password. Run `docker compose up -d --build`, then `docker compose exec backend php artisan key:generate`, `docker compose exec backend php artisan afra:keys`, `docker compose exec backend php artisan migrate --seed`, and `docker compose exec backend php artisan afra:admin admin@example.org Administrator`. The RSA-key directory mount must be writable by container user (UID82 on Alpine). APP_KEY must be saved in the host `BackEnd/.env` and containers recreated after generation; do not rely on an edit inside a disposable container. An easier safe sequence is generating APP_KEY and RSA keys with host PHP first.

The root compose password is required; there is no default. Database/Redis are not published to host. Backend binds localhost8000. Run frontend separately as above. Dockerfile supports optional BuildKit secret `build_ca` for a corporate build CA: `docker build --secret id=build_ca,src=/path/to/ca.pem -t afra-backend ./BackEnd`. The build adds it only to a temporary bundle in that RUN and removes the bundle; do not bake proxy certificates or disable TLS verification.

## Verification

`npm ci && npm run build` in FrontEnd. `DB_HOST=127.0.0.1 DB_DATABASE=afra_test DB_USERNAME=... DB_PASSWORD=... php artisan test` in BackEnd. phpunit.xml sets test database `afra_test`; RefreshDatabase resets it. Use a dedicated account without privileges on production. `vendor/bin/pint --format agent` formats PHP. Do not report unexecuted tests as passed.

## Multiple nodes and production work remaining

Use shared PostgreSQL and Redis, shared APP_KEY/previous keys and RSA signing keys, exact origins, TLS, secure cookies and exact trusted proxy addresses. Do not run separate signing keys on each node. Stateless PHP nodes receive matching releases/configuration; migration is a serialized release step, not run independently on every request/node. Shared workers need graceful restart and a scheduler singleton/lock. Configure health/readiness probes and resource limits; local `/up` alone does not prove DB readiness.

Set APP_DEBUG=false. Do not use `artisan serve` in production. PHP-FPM/reverse-proxy hardening, worker/scheduler packaging and full security acceptance remain prerequisites. Align web-server/PHP upload limits with20MiB plus multipart overhead. Static frontend caching must not cache authenticated JSON; index.html short-lived, hashed assets immutable. Never log Authorization/cookies/passwords/national IDs or raw request payloads.

## Backup and retention

Operations backs up PostgreSQL consistently and maintains restoration drills. Include encrypted chunks, metadata, audit and revision tables; keep APP_KEY and previous encryption keys separately secured. Database without decryption keys cannot recover files. Protect current/previous JWT keys according to session retention/rotation policy. Never delete attachment/history rows through routine telemetry cleanup. Keep service usage indefinitely by default, alongside product history. Do not enable a purge job without a later explicit organizational retention decision. Test a restored copy before declaring recovery successful.

No capacity guarantee has been measured.100k is registered accounts. Benchmark representative ACL-filtered pagination, case trees, report aggregation and20MiB concurrent streaming with realistic concurrency before deployment.
