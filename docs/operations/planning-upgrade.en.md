# Updating the development installation for planning/shared steps

This source update is not acceptance of the complete requested release. The outstanding software features are listed in `docs/release-acceptance.en.md`; exposed services are deferred. Organization deployment belongs to the owner. User guides are unchanged.

From the existing checkout on Windows, without Docker or Herd:

```powershell
git pull --ff-only
cd BackEnd
composer install
php artisan optimize:clear
php artisan migrate
php artisan afra:check
cd ..\FrontEnd
npm ci
npm test
npm run build
```

Run migrations once against the shared PostgreSQL database. This update adds immutable record revisions, preview confirmations, frozen plan/release/configuration/data metadata, observation/baseline ordering and shared-step review metadata. Existing results/evidence remain; existing generic shared-step records need a new attributed revision and independent approval before new consumption. Do not use `migrate:fresh`, reseed real data, regenerate existing signing keys or change APP_KEY. Existing encrypted attachments require the existing encryption key.

For local development, start `php artisan serve --host=127.0.0.1 --port=8000` in BackEnd and `npm run dev -- --host 127.0.0.1` in FrontEnd in separate terminals. The existing frontend proxy targets port8000. Production uses built frontend assets and native web/PHP workers from the deployment templates, rather than these development servers. No new Composer/npm runtime package is introduced by these workflows. Multiple nodes must run the same source, migrations, shared database/cache and configured key history.

Try the real application: create a typed dataset and configuration profile, independently approve cases, create a plan, preview its expanded items, keep all required variants and confirm. In Shared steps, save a checklist, submit it and have a different authorized reviewer approve it; attach the approved revision in a case draft. Editing shared content never silently upgrades consumers. Use the run/release Quality report button for scoped metrics and dedicated all-row print/PDF.

Composer now explicitly checks the existing required PHP extensions (including intl, PostgreSQL PDO and sodium). Enable these in the CLI and web-server php.ini; do not ignore platform requirements on the deployment machine.
