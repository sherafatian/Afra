# افرا | Afra

سامانه مدیریت آزمون دستی با تم نارنجی و نام‌گذاری خانواده درخت‌ها.

**وضعیت: پیاده‌سازی در حال تکمیل است؛ نسخه آماده تولید نیست.** هسته واقعی Laravel/React اضافه شده، اما کل امکانات درخواستی هنوز پیاده نشده‌اند. [وضعیت دقیق فارسی](docs/implementation-status.fa.md) · [English status](docs/implementation-status.en.md)

## شرح مستقل سیستم

| سند | فارسی | English |
|---|---|---|
| شرح کلی محصول | [فارسی](docs/specifications/system.fa.md) | [English](docs/specifications/system.en.md) |
| شرح مستقل بک‌اند | [فارسی](BackEnd/SPEC.fa.md) | [English](BackEnd/SPEC.en.md) |
| شرح مستقل فرانت‌اند | [فارسی](FrontEnd/SPEC.fa.md) | [English](FrontEnd/SPEC.en.md) |
| راه‌اندازی و عملیات | [فارسی](docs/operations/setup.fa.md) | [English](docs/operations/setup.en.md) |
| امنیت و پذیرش | [فارسی](docs/operations/security.fa.md) | [English](docs/operations/security.en.md) |
| راهنمای کاربر Word | [فارسی](docs/guides/afra-user-guide-fa.docx) | [English](docs/guides/afra-user-guide-en.docx) |
| راهنمای قابل جست‌وجوی Markdown | [فارسی](docs/guides/user-guide.fa.md) | [English](docs/guides/user-guide.en.md) |
| نسخه PDF راهنما | [فارسی](docs/guides/afra-user-guide-fa.pdf) | [English](docs/guides/afra-user-guide-en.pdf) |

فایل‌های SPEC مستقل شامل شرح دامنه محصول‌اند و برای تحویل جداگانه به تیم یا AI طراحی شده‌اند. شرح نیازمندی با وضعیت واقعی کد متفاوت است. فایل‌های README قبلی در پوشه‌های بک‌اند و فرانت‌اند شرح طراحی تاریخی‌اند؛ SPECهای بالا مرجع جدیدند.

## سورس و پیش‌نمایش

- [BackEnd](BackEnd): Laravel13 / PHP8.4، REST/JWT، PostgreSQL و Redis.
- [FrontEnd](FrontEnd): React19، TypeScript و Ant Design، متصل به API واقعی.
- [پیش‌نمایش HTML مستقل نسخه۴](afra-ui-v4.html): مرجع تعاملی آفلاین؛ احراز هویت یا سرور عملیاتی ندارد. نسخه‌های قدیمی برای تاریخچه حفظ شده‌اند.
- [تصاویر خود برنامه](docs/screenshots): از اجرای محلی React با داده آموزشی ساختگی؛ تصویر طراحی به‌جای برنامه واقعی معرفی نشده است.
- [فهرست۷۷مجوز هدف](docs/specifications/permission-catalog.json).
- [Docker توسعه](compose.yaml) و [محیط جداگانه آزمون](compose.test.yaml).

شروع معمولی: دستورهای دقیق، ساخت دیتابیس و مدیر اولیه در راهنمای راه‌اندازی آمده است. رمز مدیر یا کلید تولید پیش‌فرض نداریم. پوشه‌های vendor، node_modules، کلید و فایل محیط وارد Git نمی‌شوند.

## English

Afra is an orange-themed manual QA/test management product. This repository now includes a real separate Laravel13/PHP8.4 API and React/TypeScript/Ant Design frontend, bilingual standalone specifications, setup/security notes and Word/Markdown/PDF guides with actual application screenshots.

**The complete requested implementation is unfinished and this is not production-ready.** Read the explicit status document before deployment. Typed planning, configuration/data expansion, reviewed pinned shared steps, requirement revision impact and scoped quality reporting are implemented. Custom-field value workflows, full identity/avatar profiles, execution drafts/collaboration, imports and historical matrices remain unfinished; the status documents distinguish them. Exposed services are deferred by the owner for this phase.

Start with the setup guide linked above.10000–100000 means registered accounts, not concurrent users. The target supports multi-node deployment using shared PostgreSQL/Redis/keys and database attachments up to20MiB; full software/security/load acceptance remains work in progress; the owner handles organization deployment. No organization server has been accessed.

The guides contain QA principles and test case design in chapter1, and step-by-step application/target-workflow instructions in chapter2. Word uses embedded Vazir for Persian, Cambria for Latin, an Afra cover, running header/footer and a native TOC field. In Word, update the entire TOC to calculate page numbers. PDF generation on hosts without Cambria may use a compatible fallback; the Word font declaration remains Cambria.

بررسی آمادگی زیرساخت: `php artisan afra:check --production`. این فرمان جای پذیرش کامل محصول نیست. [موانع انتشار](docs/release-acceptance.fa.md) · [Release blockers](docs/release-acceptance.en.md) · [استقرار بدون Docker](deploy/README.fa.md) · [Native deployment templates](deploy/README.en.md).

به‌روزرسانی برنامه‌ریزی، داده، نما، مراحل مشترک و گزارش کیفیت: [دستورهای ویندوز بدون Docker/Herd](docs/operations/planning-upgrade.fa.md) · [English upgrade notes](docs/operations/planning-upgrade.en.md). راهنماهای کاربری این نوبت تغییر نکرده‌اند. سرویس اکسپوزی از این فاز خارج و استقرار سازمان با مالک است.
