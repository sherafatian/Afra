# Afra FrontEnd

React19 / TypeScript / Ant Design / Vite, separate from Laravel and using actual `/api` endpoints. **پیاده‌سازی کامل و آماده تولید نیست. / Work in progress, not production-ready.**

- شرح مستقل فارسی: [SPEC.fa.md](SPEC.fa.md)
- Standalone English specification: [SPEC.en.md](SPEC.en.md)
- [راه‌اندازی فارسی](../docs/operations/setup.fa.md) · [English setup](../docs/operations/setup.en.md)
- [وضعیت فارسی](../docs/implementation-status.fa.md) · [English status](../docs/implementation-status.en.md)

`npm ci` → `npm run dev` (local5173, API proxy8000). `npm run build` performs strict typechecking and creates `dist`. In production serve static assets with SPA fallback and `/api` reverse proxy. Never place secrets in VITE variables or browser storage. Only appearance/language preferences are persisted; access token stays in memory.

The root HTML is an offline design reference and is not embedded as the real frontend. Full domain forms, localization, calendar input, comprehensive security acceptance and professional reporting remain subject to completion/status documentation.

Verification and browser-fixture requirements: [English](../docs/operations/validation.en.md) · [فارسی](../docs/operations/validation.fa.md). Source formatting: `npm run format` / `npm run format:check`.
