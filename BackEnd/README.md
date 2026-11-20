# Afra BackEnd

Laravel13 / PHP8.4 REST API. **پیاده‌سازی هنوز کامل و آماده تولید نیست. / Work in progress, not production-ready.**

- شرح مستقل فارسی: [SPEC.fa.md](SPEC.fa.md)
- Standalone English specification: [SPEC.en.md](SPEC.en.md)
- [راه‌اندازی فارسی](../docs/operations/setup.fa.md) · [English setup](../docs/operations/setup.en.md)
- [وضعیت فارسی](../docs/implementation-status.fa.md) · [English status](../docs/implementation-status.en.md)
- [Current core OpenAPI draft](../docs/openapi.json)

`composer install` → configure `.env` and PostgreSQL/Redis → `php artisan key:generate` → `php artisan afra:keys` → `php artisan migrate --seed` → `php artisan afra:admin email@example.org Administrator`.

No fixed administrator password is seeded. Development: `php artisan serve --host=127.0.0.1 --port=8000 --no-reload`. Never use the CLI server as production packaging. Tests use disposable PostgreSQL database `afra_test`; see root `compose.test.yaml`. All target product capabilities and security acceptance requirements are in SPEC files; generic storage is not a completed workflow.
