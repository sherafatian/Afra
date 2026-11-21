# Browser smoke flow / بررسی مرورگر

Use a disposable local training database with an administrator, project, approved cases and an execution run. Never use production accounts or real personal data for guide screenshots. Install Python Playwright through a virtual environment; the script currently uses `/usr/bin/chromium` (adjust to your installed browser).

Provide `AFRA_TEST_EMAIL`, `AFRA_TEST_PASSWORD` and optionally `AFRA_TEST_URL` in the process environment. Run `python tests/browser_smoke.py` from FrontEnd. Screenshots default to `/tmp/afra-smoke-screenshots`; use `AFRA_SCREENSHOT_DIR` explicitly to regenerate approved guide images. Never commit the credential environment or print it. This smoke test is not comprehensive browser coverage.

فقط با دیتابیس آموزشی دورریختنی، کاربر مدیر، پروژه، تست تأییدشده و اجرای موجود اجرا کنید. رمز را در محیط فرایند بگذارید، نه سورس یا خروجی لاگ. تصاویر پیش‌فرض در پوشه موقت‌اند. این جریان جای آزمون جامع رابط و دسترس‌پذیری را نمی‌گیرد.

## Planning and shared steps / برنامه‌ریزی و مراحل مشترک

`planning_workflow.py` and `shared_steps_workflow.py` are **mutating** acceptance checks. Use a disposable synthetic database with two distinct training system administrators and the same synthetic password. Set `AFRA_TEST_EMAIL`, `AFRA_TEST_REVIEWER_EMAIL`, and `AFRA_TEST_PASSWORD_FILE` (outside Git); optional `AFRA_TEST_URL` and `AFRA_TEST_API` override localhost. Each script creates uniquely named training records and leaves them in place. Do not point them at organization data. These checks do not modify user guides.

```bash
python FrontEnd/tests/planning_workflow.py
python FrontEnd/tests/shared_steps_workflow.py
```

The planning flow creates typed data, configurations, a plan and a frozen run, verifies required selection and mobile overflow, and opens the quality report. The shared-step flow creates a block in the UI, requests review, independently approves through the API, attaches the pinned revision in the case editor and verifies stored resolved/local steps. Broader API tests cover explicit upgrades, detachment, archival, revision pagination and cross-project rejection.

این دو اسکریپت داده می‌نویسند؛ فقط روی محیط آموزشی دورریختنی اجرا شوند. دو مدیر مستقل آموزشی و مسیر فایل رمز خارج مخزن لازم است. رمز و اطلاعات واقعی سازمان را در سورس یا لاگ قرار ندهید. تصاویر در پوشه موقت تولید می‌شوند و راهنمای کاربر را تغییر نمی‌دهند.
